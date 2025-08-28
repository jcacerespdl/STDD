<?php
include("headFlujo.php");
include("conexion/conexion.php");

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;
$extension = isset($_GET['extension']) ? intval($_GET['extension']) : 1;

// Obtener los datos generales del trámite raíz (asunto, expediente, observaciones, tipo de doc, etc.)
$sqlTramite = "SELECT 
    t.expediente, t.cCodificacion, t.cAsunto, t.fFecRegistro, t.cObservaciones, t.documentoElectronico, t.cCodTipoDoc,
    td.cDescTipoDoc, td.cCodTipoDoc, o.cSiglaOficina
 FROM Tra_M_Tramite t
 JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
 JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
 WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$infoInicial = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
// Obtener todas las extensiones del trámite raíz
$sqlExtensiones = "SELECT DISTINCT extension FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? ORDER BY extension ASC";
$stmtExt = sqlsrv_query($cnx, $sqlExtensiones, [$iCodTramite]);

$extensiones = [];
while ($r = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC)) {
    $extensiones[] = (int)$r['extension'];
}
// Organizar todos los movimientos por extensión, para luego renderizar flujo por bloques
$movimientosPorExtension = [];

foreach ($extensiones as $ext) {
    $sqlMov = "SELECT 
        M.iCodTramiteDerivar, 
        M.iCodMovimiento,
        M.iCodMovimientoDerivo,

         -- Fecha de envío calculada segun regla
    CASE 
        WHEN M.iCodMovimientoDerivo IS NULL THEN T.fFecRegistro
        ELSE M.fFecMovimiento
    END AS fFecEnvio,

    M.fFecRecepcion,

        M.cAsuntoDerivar, 
        M.cObservacionesDerivar, 
        M.cPrioridadDerivar, 
        M.nEstadoMovimiento,
        M.fFecDelegado,
        M.fFecDelegadoRecepcion,
        M.iCodTrabajadorDelegado,
        M.iCodIndicacionDelegado,
        M.cObservacionesDelegado,
        M.extension,
        O1.cNomOficina AS OficinaOrigen,
        O1.cSiglaOficina AS OficinaOrigenAbbr,
        O2.cNomOficina AS OficinaDestino,
        O2.cSiglaOficina AS OficinaDestinoAbbr,
        T.fase,
        -- Jefe destino
        (SELECT TOP 1 T2.cNombresTrabajador + ' ' + T2.cApellidosTrabajador 
         FROM Tra_M_Perfil_ususario PU
         INNER JOIN Tra_M_Trabajadores T2 ON PU.iCodTrabajador = T2.iCodTrabajador
         WHERE PU.iCodOficina = M.iCodOficinaDerivar AND PU.iCodPerfil = 3
         ORDER BY T2.iCodTrabajador ASC) AS JefeDestino,
        -- Delegado
        (SELECT TOP 1 T3.cNombresTrabajador + ' ' + T3.cApellidosTrabajador 
         FROM Tra_M_Trabajadores T3 WHERE T3.iCodTrabajador = M.iCodTrabajadorDelegado) AS NombreDelegado,
        -- Indicación
        (SELECT I.cIndicacion FROM Tra_M_Indicaciones I WHERE I.iCodIndicacion = M.iCodIndicacionDelegado) AS cIndicacionDelegado
    FROM Tra_M_Tramite_Movimientos M
    LEFT JOIN Tra_M_Oficinas O1 ON M.iCodOficinaOrigen = O1.iCodOficina
    LEFT JOIN Tra_M_Oficinas O2 ON M.iCodOficinaDerivar = O2.iCodOficina
 LEFT JOIN Tra_M_Tramite T ON T.iCodTramite = ISNULL(M.iCodTramiteDerivar, M.iCodTramite)
    WHERE M.iCodTramite = ? AND M.extension = ?
ORDER BY M.iCodMovimiento ASC";

    $stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramite, $ext]);
    $movs = [];
    while ($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)) {
        $movs[] = $row;
    }
    $movimientosPorExtension[$ext] = array_reverse($movs);
}
// === NUEVA LÓGICA: buscar ítems SIGA directamente por EXPEDIENTE y solo los código_item reales ===
$itemsSIGA = [];

$expediente = $infoInicial['expediente'];
$sqlSIGA = "SELECT pedido_siga, codigo_item, cantidad FROM Tra_M_Tramite_SIGA_Pedido WHERE EXPEDIENTE = ?";
$stmtSIGA = sqlsrv_query($cnx, $sqlSIGA, [$expediente]);

while ($pedido = sqlsrv_fetch_array($stmtSIGA, SQLSRV_FETCH_ASSOC)) {
    $pedidoSiga = $pedido['pedido_siga'];
    $codigoItem = $pedido['codigo_item'];
    $cantidad = $pedido['cantidad'];

    // Si tiene pedido SIGA, buscamos información adicional del pedido
    if ($pedidoSiga) {
        $stmtOrden = sqlsrv_query($sigaConn,
            "SELECT NRO_ORDEN, TIPO_BIEN, PROVEEDOR, MES_CALEND, CONCEPTO, TOTAL_FACT_SOLES, FECHA_REG
             FROM SIG_ORDEN_ADQUISICION
             WHERE ANO_EJE = 2025 AND EXP_SIGA = ?", [$pedidoSiga]);

        if ($stmtOrden && $orden = sqlsrv_fetch_array($stmtOrden, SQLSRV_FETCH_ASSOC)) {
            // Buscamos el nombre del código de ítem exacto
            $stmtCat = sqlsrv_query($sigaConn,
                "SELECT NOMBRE_ITEM, TIPO_BIEN
                 FROM CATALOGO_BIEN_SERV
                 WHERE CODIGO_ITEM = ?", [$codigoItem]);

            echo "<script>console.log('🔍 Procesando PEDIDO {$pedidoSiga}, ITEM {$codigoItem}');</script>";

            if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                $itemsSIGA[] = [
                    "pedido_siga" => $pedidoSiga,
                 
                    "TIPO_BIEN" => $cat['TIPO_BIEN'] ?? $orden['TIPO_BIEN'] ?? 'N.A.',
             
          
            
                     "CODIGO_ITEM" => $codigoItem,
                    "CANTIDAD" => $cantidad,
                    "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'] ?? 'N.A.'
                ];
            } else {
                echo "<script>console.warn('⚠️ No se encontró nombre para código $codigoItem en PEDIDO $pedidoSiga');</script>";
            }
        } else {
            echo "<script>console.warn('⚠️ No se encontró orden para PEDIDO $pedidoSiga');</script>";
        }
    } else {
        // Sin pedido SIGA: buscar solo el nombre del ítem
        $stmtCat = sqlsrv_query($sigaConn,
            "SELECT NOMBRE_ITEM, TIPO_BIEN FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?",
            [$codigoItem]);

        echo "<script>console.log('📦 Item sin pedido SIGA → código: {$codigoItem}');</script>";

        if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
            $itemsSIGA[] = [
                "pedido_siga" => "N.A.",
                 
                "TIPO_BIEN" => $cat['TIPO_BIEN'] ?? 'N.A.',
              
                
             
               
                "CODIGO_ITEM" => $codigoItem,
                "CANTIDAD" => $cantidad,
                "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'] ?? 'N.A.'
            ];
        } else {
            echo "<script>console.warn('⚠️ No se encontró nombre para código sin pedido: {$codigoItem}');</script>";
        }
    }
}

?>
<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<!-- Estilos visuales Gmail-like -->
<style>
.chip-adjunto {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    border-radius: 999px;
    padding: 6px 12px;
    margin: 4px 6px 4px 0;
    font-size: 13px;
    font-family: 'Segoe UI', sans-serif;
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    border: 1px solid #dadce0;
    transition: background 0.2s;
    text-decoration: none;
    color: black;
}
.chip-adjunto:hover {
    background-color: #e8eaed;
    text-decoration: none;
}
.material-icons.chip-icon {
    font-size: 18px;
    margin-right: 8px;
    vertical-align: middle;
    color: #d93025;
}
.material-icons.chip-doc {
    color: #1a73e8;
}
.chip-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    max-width: 180px;
}
.detail-content {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  font-family: 'Segoe UI', sans-serif;
  overflow: hidden;
}

.detail-content summary {
  background: #f7f9fc;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  transition: background 0.2s ease;
}

.detail-content summary:hover {
  background: #eef2f7;
}


.detail-content div {
  line-height: 1.6;
  font-size: 14px;
  color: #333;
}

.detail-content div > b {
  color: #1a1a1a;
  display: inline-block;
  min-width: 140px;
}
.detail-header {
  background: #f7f9fc;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 15px;
  border-bottom: 1px solid #eee;
} 
.detail-body {
  padding: 1rem;
  line-height: 1.6;
  font-size: 14px;
  color: #333;
}
.detail-body > b { /* si lo usas */
  color: #1a1a1a;
  display: inline-block;
  min-width: 140px;
}
table {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1rem;
}
/* separador vertical extra entre secciones clave */
.section { margin-top: 1.25rem; }
table thead {
  background: #f1f5f9;
  font-weight: bold;
}

table th, table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e6e6e6;
  text-align: left;
}

table tbody tr:hover {
  background-color: #f9fbfd;
}

table td {
  font-size: 13px;
  color: #444;
}
h3 {
  font-size: 18px;
  color: #0c2d5d;
  margin: 1.5rem 0 0.75rem 0;
  font-family: 'Segoe UI', sans-serif;
  border-left: 4px solid #0072CE;
  padding-left: 10px;
}

</style>

<!-- DATOS GENERALES: INICIO -->
 
<h3>DETALLES DEL EXPEDIENTE: <?= htmlspecialchars($infoInicial['expediente']) ?></h3>

<div class="detail-content">
<div class="detail-header">DATOS GENERALES</div>
<div class="detail-body">
    <div><b>Expediente:</b> <?= htmlspecialchars($infoInicial['expediente']) ?></div>
    <div><b>Extensión:</b> <?= $extension ?></div>
    <div><b>Tipo de Documento:</b> <?= $infoInicial['cDescTipoDoc'] ?></div>
    <div><b>Asunto:</b> <?= $infoInicial['cAsunto'] ?></div>
    <div><b>Fecha Registro:</b> <?= $infoInicial['fFecRegistro']->format("d/m/Y H:i:s") ?></div>
    <div><b>Observaciones:</b> <?= $infoInicial['cObservaciones'] ?></div>

    <div><b>Doc. Principal:</b> 
    <?php if (!empty($infoInicial['documentoElectronico'])): ?>
        <a href="./cDocumentosFirmados/<?= urlencode($infoInicial['documentoElectronico']) ?>" class="chip-adjunto" target="_blank" title="<?= htmlspecialchars($infoInicial['documentoElectronico']) ?>">
        <span class="material-icons chip-icon">picture_as_pdf</span>
        <span class="chip-text"><?= htmlspecialchars($infoInicial['documentoElectronico']) ?></span>
        </a>
    <?php else: ?>
        <span>No disponible</span>
    <?php endif; ?>
    </div>

    <!-- Botón para exportar a PDF -->
    <div style="margin-top: 10px;">
        <a href="exportarFlujoPDF.php?iCodTramite=<?= $iCodTramite ?>&extension=<?= $extension ?>" 
            target="_blank"
            style="background-color:#005a86; color:white; padding:6px 14px; border-radius:20px; font-size:13px; text-decoration:none; display:inline-block; margin-top:6px;">
            <span class="material-icons" style="vertical-align:middle; font-size:17px; margin-right:4px;">download</span>
            Exportar Flujo a PDF
        </a>
        </div>
  </div>
</div>

<!-- DATOS GENERALES: FIN -->

<!-- iTEMS SIGA: INICIO -->

<?php if (!empty($itemsSIGA)): ?>
    <h3>DETALLES DEL REQUERIMIENTO para el EXPEDIENTE <?= htmlspecialchars($infoInicial['expediente']) ?></h3>
  <div class="detail-content section">
    <div class="detail-header">ÍTEMS SIGA</div>
    <div class="detail-body">
      <table style="width:100%; border-collapse: collapse; font-size: 14px;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th>PEDIDO SIGA</th>
            <th>TIPO BIEN</th>
            <th>CÓDIGO ITEM</th>
            <th>NOMBRE ITEM</th>
            <th>CANTIDAD</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($itemsSIGA as $item): ?>
            <tr>
              <td><?= $item['pedido_siga'] ?></td>
              <td><?= $item['TIPO_BIEN'] === 'S' ? 'SERVICIO' : 'BIEN' ?></td>
              <td><?= $item['CODIGO_ITEM'] ?></td>
              <td><?= $item['NOMBRE_ITEM'] ?></td>
              <td><?= $item['CANTIDAD'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- iTEMS SIGA: FIN -->


<!-- FLUJO DE TODAS LAS EXTENSIONES -->
<?php
// === FUNCIONES PARA CONSTRUIR Y MOSTRAR EL FLUJO ===

function construirArbolMovimientos($movimientos) {
    $movPorId = [];
    foreach ($movimientos as $mov) {
        $mov['hijos'] = [];
        $movPorId[$mov['iCodMovimiento']] = $mov;
    }

    $arbol = [];
    foreach ($movPorId as $id => &$mov) {
        $padreId = $mov['iCodMovimientoDerivo'] ?? null;
        if ($padreId && isset($movPorId[$padreId])) {
            $movPorId[$padreId]['hijos'][] = &$mov;
        } else {
            $arbol[] = &$mov;
        }
    }

    return $arbol;
}

function obtenerDatosTramite($cnx, $iCodTramite) {
    $doc = ['principal'=>null,'codificacion'=>null,'asunto'=>null,'fecha'=>null,'anexos'=>[], 'tipo'=>null];

    $stmt = sqlsrv_query($cnx, "SELECT t.documentoElectronico, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.fFecRegistro 
        FROM Tra_M_Tramite t 
        JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc 
        WHERE t.iCodTramite = ?", [$iCodTramite]);

    if ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $doc['principal'] = $r['documentoElectronico'];
        $doc['codificacion'] = "{$r['cDescTipoDoc']} Nº " . str_pad($r['cCodificacion'], 5, '0', STR_PAD_LEFT);
        $doc['asunto'] = $r['cAsunto'];
        $doc['fecha'] = $r['fFecRegistro'] ? $r['fFecRegistro']->format("d/m/Y H:i:s") : '';
        $doc['tipo'] = $r['cCodTipoDoc'];
    }

    $stmtAnexos = sqlsrv_query($cnx, "SELECT cDescripcion FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ?", [$iCodTramite]);
    while ($r = sqlsrv_fetch_array($stmtAnexos, SQLSRV_FETCH_ASSOC)) {
        $doc['anexos'][] = $r['cDescripcion'];
    }

    return $doc;
}
 

 foreach ($movimientosPorExtension as $ext => $lista): 
 
    if (empty($lista)) continue;
    $estructuraJerarquica = construirArbolMovimientos($lista);
    ?>
    <details class="detail-content" open>
    <summary>FLUJO DEL EXPEDIENTE - EXTENSIÓN <?= $ext ?></summary>
    <div style="padding: 1rem;">
        <?php foreach ($estructuraJerarquica as $mov): ?>
            <?php renderizarMovimiento($mov); ?>
        <?php endforeach; ?>
    </div>
    </details>
<?php endforeach; 
function renderizarMovimiento($mov, $nivel = 0) {
    $doc = obtenerDatosTramite($GLOBALS['cnx'], $mov["iCodTramiteDerivar"] ?: $GLOBALS['iCodTramite']);
    $sangria = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $nivel);

    echo "<div style='margin-top: 10px;'>";
    echo "<details>";
    echo "<summary style='font-weight:bold; cursor:pointer; padding-left: " . ($nivel * 20) . "px;'>{$mov['OficinaOrigenAbbr']} → {$mov['OficinaDestinoAbbr']} - {$doc['codificacion']}</summary>";
    echo "<div style='padding-left: 1rem;'>";

    echo "<div><b>Asunto:</b> " . htmlspecialchars($doc['asunto']) . "</div>";
    echo "<div><b>Prioridad:</b> " . htmlspecialchars($mov['cPrioridadDerivar']) . "</div>";

    if (!empty($mov['fase'])) {
        $fases = [
            0 => "No Corresponde",
            1 => "Indagación",
            2 => "Validación",
            3 => "Reformulación",
            4 => "Disponibilidad Presupuestal",
            5 => "Notificación"
        ];
        echo "<div><b>Fase:</b> " . ($fases[$mov['fase']] ?? 'No definida') . "</div>";
    }

      $fechaEnvio = ($mov['fFecEnvio'] instanceof DateTime) 
    ? $mov['fFecEnvio']->format("d/m/Y H:i:s") 
    : '—';

$fechaRecep = ($mov['nEstadoMovimiento'] == 0) 
    ? '—' 
    : (($mov['fFecRecepcion'] instanceof DateTime) 
        ? $mov['fFecRecepcion']->format("d/m/Y H:i:s") 
        : '—');

echo "<div><b>Fecha de Envío:</b> {$fechaEnvio}</div>";
echo "<div><b>Fecha de Recepción:</b> {$fechaRecep}</div>";
    echo "<div><b>Dirigido a:</b> " . htmlspecialchars($mov["JefeDestino"]) . "</div>";

    $estadoTexto = 'Enviado';
    if ($mov['nEstadoMovimiento'] == 0) $estadoTexto = 'Sin aceptar';
    elseif ($mov['nEstadoMovimiento'] == 1) $estadoTexto = 'Recibido';
    elseif ($mov['nEstadoMovimiento'] == 3) $estadoTexto = 'Delegado';
    elseif ($mov['nEstadoMovimiento'] == 5) $estadoTexto = 'Finalizado';

    echo "<div><b>Estado:</b> " . $estadoTexto . "</div>";

    // Datos de delegación
    if (!empty($mov['iCodTrabajadorDelegado']) || !empty($mov['iCodIndicacionDelegado']) || !empty($mov['cObservacionesDelegado'])) {
        echo "<hr>";
        echo "<div><b>Delegado a:</b> " . htmlspecialchars($mov["NombreDelegado"] ?? 'N/A') . "</div>";
        if (!empty($mov["cIndicacionDelegado"])) echo "<div><b>Indicación / Fase:</b> " . htmlspecialchars($mov["cIndicacionDelegado"]) . "</div>";
        if (!empty($mov["cObservacionesDelegado"])) echo "<div><b>Observaciones:</b> " . htmlspecialchars($mov["cObservacionesDelegado"]) . "</div>";
        if (!empty($mov["fFecDelegado"])) echo "<div><b>Fecha de Delegación:</b> " . $mov["fFecDelegado"]->format("d/m/Y H:i:s") . "</div>";
        if (!empty($mov["fFecDelegadoRecepcion"])) echo "<div><b>Recepción Delegación:</b> " . $mov["fFecDelegadoRecepcion"]->format("d/m/Y H:i:s") . "</div>";
    }

    // Documento principal
    echo "<div><b>Documento principal:</b><br>";
    if ($doc['tipo'] === '97') {
        // Tipo "proveído", se oculta
    } elseif (!empty($doc['principal'])) {
        echo "<a href='./cDocumentosFirmados/" . urlencode($doc['principal']) . "' class='chip-adjunto' target='_blank'>";
        echo "<span class='material-icons chip-icon'>picture_as_pdf</span><span class='chip-text'>" . htmlspecialchars($doc['principal']) . "</span></a>";
    } else {
        echo "<span style='color:#888;'>No hay documento principal</span>";
    }
    echo "</div>";

    // Documentos complementarios
    echo "<div><b>Documentos complementarios:</b><br>";
    if (!empty($doc['anexos'])) {
        foreach ($doc['anexos'] as $anexo) {
            echo "<a href='./cAlmacenArchivos/" . urlencode($anexo) . "' class='chip-adjunto' target='_blank'>";
            echo "<span class='material-icons chip-icon chip-doc'>article</span><span class='chip-text'>" . htmlspecialchars($anexo) . "</span></a> ";
        }
    } else {
        echo "<span style='color: #888;'>No hay documentos complementarios</span>";
    }
    echo "</div>";

    echo "</div></details>";
    echo "</div>";

    // Mostrar hijos (movimientos derivados de este)
    foreach ($mov['hijos'] as $hijo) {
        renderizarMovimiento($hijo, $nivel + 1);
    }
}


?>
