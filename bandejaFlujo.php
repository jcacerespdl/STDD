<?php
include("headFlujo.php");
include("conexion/conexion.php");

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;
$sql = "SELECT EXPEDIENTE 
        FROM Tra_M_Tramite 
        WHERE iCodTramite = ?";

$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$expediente = $row['EXPEDIENTE'] ?? '';


// ─────────────────────────────────────────────────────────────
// 1) DATOS GENERALES DEL TRÁMITE
// ─────────────────────────────────────────────────────────────
$sqlTramite = "SELECT 
    t.EXPEDIENTE,
    t.cCodificacion,
    t.cAsunto,
    t.fFecRegistro,
    t.cObservaciones,
    t.documentoElectronico,
    t.cCodTipoDoc,
    td.cDescTipoDoc,
    o.cSiglaOficina
FROM Tra_M_Tramite t
LEFT JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
LEFT JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
WHERE t.iCodTramite = ?";

$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
if ($stmtTramite === false) die('Error al consultar Trámite: '.print_r(sqlsrv_errors(), true));
$info = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
if (!$info) die('No se encontró el trámite con iCodTramite='.$iCodTramite);

// ─────────────────────────────────────────────────────────────
// 2) FLUJO (SELECT con todos los datos + iCodMovimientoDerivo/Rel)
//    Usamos iCodMovimientoDerivo como padre; si NULL, iCodMovimientoRel;
//    si ambos NULL ⇒ raíz (p.ej. tu 207774).
// ─────────────────────────────────────────────────────────────
$sqlFlujo = "
SELECT
    TM.iCodMovimiento,
    TM.iCodTramite,
    TM.iCodTramiteDerivar,
    TM.iCodTramiteRel,
    TM.iCodMovimientoDerivo,
    TM.iCodMovimientoRel,
    TM.cFlgTipoMovimiento,
    TM.nEstadoMovimiento,
    TM.fFecDerivar,
    TM.fFecRecepcion,
    TM.cAsuntoDerivar,
    TM.cObservacionesDerivar,
    TM.iCodIndicacionDerivar,
    I.cIndicacion,
    O1.cSiglaOficina AS OficinaOrigenAbbr,
    O2.cSiglaOficina AS OficinaDestinoAbbr,

    -- Estado como en la bandeja antigua
    CASE
        WHEN TM.fFecRecepcion IS NULL THEN
            CASE
                WHEN TM.cFlgTipoMovimiento = 5 THEN ''
                WHEN TM.nEstadoMovimiento = 7 THEN 'Anulado'
                ELSE 'Pendiente'
            END
        ELSE
            CASE TM.nEstadoMovimiento
                WHEN 1 THEN 'En Proceso'
                WHEN 2 THEN 'Derivado'
                WHEN 3 THEN 'Delegado'
                WHEN 4 THEN 'Respondido'
                WHEN 5 THEN 'Finalizado'
                ELSE 'En Proceso'
            END
    END AS EstadoTexto,

    -- Tramite de la fila (Derivar si existe, si no el propio)
    Tpdf.iCodTramite           AS iCodTramitePDF,
    Tpdf.documentoElectronico  AS DocElectronico,
    Tpdf.descripcion           AS DocDescripcion,
    TDdoc.cDescTipoDoc,
    Tpdf.cCodificacion,
    Tpdf.nFlgTipoDoc,
    Tpdf.nFlgFirma,

    -- Nombre ‘legacy’ cuando no hay documentoElectronico
    CASE
        WHEN Tpdf.documentoElectronico IS NOT NULL AND LTRIM(RTRIM(Tpdf.documentoElectronico)) <> ''
            THEN Tpdf.documentoElectronico
        ELSE
            (CASE WHEN Tpdf.nFlgTipoDoc = 2 THEN 'I_' ELSE 'S_' END) +
            REPLACE(REPLACE(LTRIM(RTRIM(TDdoc.cDescTipoDoc)),' ','_'),'/','-') +
            '_N_' + LTRIM(RTRIM(Tpdf.cCodificacion)) +
            CASE WHEN Tpdf.nFlgFirma = 0 THEN '' ELSE '[FP]' END + '.pdf'
    END AS DocumentNameOldStyle,

    -- Complementarios (cuenta)
    ISNULL(D.CountDigitales, 0) AS ComplementariosCount
FROM Tra_M_Tramite_Movimientos TM
INNER JOIN Tra_M_Tramite T
    ON T.iCodTramite = TM.iCodTramite
LEFT JOIN Tra_M_Oficinas O1
    ON O1.iCodOficina = TM.iCodOficinaOrigen
LEFT JOIN Tra_M_Oficinas O2
    ON O2.iCodOficina = TM.iCodOficinaDerivar
LEFT JOIN Tra_M_Indicaciones I
    ON I.iCodIndicacion = TM.iCodIndicacionDerivar

-- Tramite ‘de la fila’
OUTER APPLY (
    SELECT TOP (1) *
    FROM Tra_M_Tramite Tp
    WHERE Tp.iCodTramite = ISNULL(TM.iCodTramiteDerivar, TM.iCodTramite)
) AS Tpdf

LEFT JOIN Tra_M_Tipo_Documento TDdoc
    ON TDdoc.cCodTipoDoc = Tpdf.cCodTipoDoc

-- Complementarios del tramite ‘de la fila’
OUTER APPLY (
    SELECT COUNT(*) AS CountDigitales
    FROM Tra_M_Tramite_Digitales Dg
    WHERE Dg.iCodTramite = ISNULL(TM.iCodTramiteDerivar, TM.iCodTramite)
) AS D

WHERE (TM.iCodTramite = ? OR TM.iCodTramiteRel = ?)
ORDER BY TM.iCodMovimiento ASC";   

$stmtFlujo = sqlsrv_query($cnx, $sqlFlujo, [$iCodTramite, $iCodTramite]);
if ($stmtFlujo === false) die('Error al consultar Flujo: '.print_r(sqlsrv_errors(), true));

// Cargamos todos los movimientos
$rows = [];
while ($r = sqlsrv_fetch_array($stmtFlujo, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

// ─────────────────────────────────────────────────────────────
// 3) ARMADO DEL ÁRBOL (ramas plegables)
//    Regla de parentesco:
//      parentId = iCodMovimientoDerivo ? iCodMovimientoDerivo : iCodMovimientoRel
//    Si parentId NULL → raíz (p.ej. 176006 y 207774 del ejemplo).
// ─────────────────────────────────────────────────────────────
$nodes = [];
$children = [];
$roots = [];

// índice por id
foreach ($rows as $r) {
    $id = (int)$r['iCodMovimiento'];
    $nodes[$id] = $r;
    $children[$id] = []; // inicializa
}

// segundo pase: enlazamos
foreach ($nodes as $id => $n) {
    $parentId = null;
    if (!empty($n['iCodMovimientoDerivo'])) {
        $parentId = (int)$n['iCodMovimientoDerivo'];
    } elseif (!empty($n['iCodMovimientoRel'])) {
        $parentId = (int)$n['iCodMovimientoRel'];
    }

    if ($parentId !== null && isset($nodes[$parentId])) {
        $children[$parentId][] = $id;
    } else {
        // raíz (sin padre en el set: inicio o referencia)
        $roots[] = $id;
    }
}

// ─────────────────────────────────────────────────────────────
// 4) ÍTEMS SIGA (opcional) → No mostrar sección si no hay datos
// ─────────────────────────────────────────────────────────────
// === NUEVA LÓGICA: buscar ítems SIGA directamente por EXPEDIENTE y solo los código_item reales ===
$itemsSIGA = [];
 
$sqlSIGA = "SELECT pedido_siga, codigo_item, cantidad, extension FROM Tra_M_Tramite_SIGA_Pedido WHERE EXPEDIENTE = ?";
$stmtSIGA = sqlsrv_query($cnx, $sqlSIGA, [$expediente]);

while ($pedido = sqlsrv_fetch_array($stmtSIGA, SQLSRV_FETCH_ASSOC)) {
    $pedidoSiga = $pedido['pedido_siga'];
    $codigoItem = $pedido['codigo_item'];
    $cantidad = $pedido['cantidad'];
    $extSIGA    = isset($pedido['extension']) ? (int)$pedido['extension'] : null;

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
                    "extension"   => $extSIGA,                
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
                "extension"   => $extSIGA,   
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

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return ($d instanceof DateTimeInterface) ? $d->format('d/m/Y H:i:s') : '—'; }

function renderDocPrincipal($cnx, $row){
  $principal = trim((string)($row['DocElectronico'] ?? ''));
  $calc      = $row['DocumentNameOldStyle'] ?? '';
  $docFile   = $principal !== '' ? $principal : $calc;

  if ($docFile === '') {
      return '<span class="badge">Sin documento principal</span>';
  }

  $repo = (
    ($row['fFecDerivar'] instanceof DateTimeInterface
      && $row['fFecDerivar']->format('Y-m-d H:i') >= '2025-09-04 16:00'
    ) ? 'STDD_marchablanca' : 'STD'
  );

  // chip de descarga
  $chip = '<a href="https://tramite.heves.gob.pe/'.$repo.'/cDocumentosFirmados/'.urlencode($docFile).'"
             class="chip-adjunto" target="_blank" title="'.h($docFile).'">
             <span class="material-icons chip-icon">picture_as_pdf</span>
             <span class="chip-text">'.h($docFile).'</span>
           </a>';

  // botón "group" SOLO si hay firmantes en principal
  $btn = '';
  $iCodTramitePDF = (int)($row['iCodTramitePDF'] ?? 0);
  if ($iCodTramitePDF && tieneFirmantesPrincipal($cnx, $iCodTramitePDF)) {
    $url = 'detallesFirmantes2.php?iCodTramite='.$iCodTramitePDF.'&iCodDigital=null';
    $btn = ' <a href="'.$url.'" class="btn-firmantes" title="Ver firmantes" onclick="return abrirModalFirmantes(this.href)">
               <span class="material-icons">group</span>
             </a>';
}

// antes: return $chip.$btn;
return '<span class="chip-line">'.$chip.$btn.'</span>';
}

 

function renderComplementariosTodos($cnx, int $iCodTramite, bool $mostrarFirmantes = true){
  $s = sqlsrv_query(
    $cnx,
    "SELECT iCodDigital, cDescripcion, cNombreNuevo, cNombreOriginal, iCodTramite, fFechaRegistro
       FROM Tra_M_Tramite_Digitales
      WHERE iCodTramite = ?
      ORDER BY fFechaRegistro ASC",
    [$iCodTramite]
  );
  if(!$s) return '<span class="badge">—</span>';

  $chips = [];
  while($r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)){
    $iCodDigital = (int)($r['iCodDigital'] ?? 0);

    $nombre = trim((string)($r['cDescripcion'] ?? ''));
    if($nombre === '') $nombre = trim((string)($r['cNombreNuevo'] ?? ''));
    if($nombre === ''){
      $orig   = preg_replace('/\s+/', '_', trim((string)($r['cNombreOriginal'] ?? 'adjunto.pdf')));
      $nombre = ((int)$r['iCodTramite']).'-'.$orig;
    }

    $chip =
      '<a href="https://tramite.heves.gob.pe/STDD_marchablanca/cAlmacenArchivos/'.urlencode($nombre).'"
          class="chip-adjunto" target="_blank" title="'.htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8').'">
         <span class="material-icons chip-icon chip-doc">article</span>
         <span class="chip-text">'.htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8').'</span>
       </a>';

    $btn = '';
    if ($mostrarFirmantes && $iCodDigital && tieneFirmantesDigital($cnx, $iCodTramite, $iCodDigital)) {
      $url = 'detallesFirmantes2.php?iCodTramite='.$iCodTramite.'&iCodDigital='.$iCodDigital;
      $btn = ' <a href="'.$url.'" class="btn-firmantes" title="Ver firmantes" onclick="return abrirModalFirmantes(this.href)">
                 <span class="material-icons">group</span>
               </a>';
    }

    $chips[] = '<span class="chip-line">'.$chip.$btn.'</span>';
  }

  if(empty($chips)) return '<span class="badge">Ninguno</span>';
  return '<div class="chips-wrap">'.implode('', $chips).'</div>';
}


// Render recursivo de ramas
function renderNodo($id, $nodes, $children, $nivel = 0){
  
    $n = $nodes[$id];

    // Etiquetas de relación
    $esReferencia = empty($n['iCodMovimientoDerivo']) && empty($n['iCodMovimientoRel']);
    $badge = $esReferencia ? ' <span class="badge">Referencia</span>' : (
              !empty($n['iCodMovimientoDerivo']) ? '' : ' <span class="badge">Relacionado</span>'
            );

    // SUMMARY (línea compacta)
    echo "<details".($nivel===0?' open':'').">";
    echo "<summary style='font-weight:600; padding-left:".($nivel*18)."px'>";
    echo h($n['OficinaOrigenAbbr'])." → ".h($n['OficinaDestinoAbbr'])."";
    // echo h($n['cAsuntoDerivar']).$badge." ";
    // echo "<small style='color:#555;margin-left:8px'>#".(int)$n['iCodMovimiento']."</small>";
    echo "</summary>";

    // CUERPO DEL NODO
    echo "<div class='node-body'>";
      echo "<div class='row-flex'>";
        echo "<div class='kv'><b>Fecha Derivación:</b> ".fmtDate($n['fFecDerivar'] ?? null)."</div>";
        echo "<div class='kv'><b>Fecha Recepción:</b> ".fmtDate($n['fFecRecepcion'] ?? null)."</div>";
        echo "<div class='kv'><b>Estado:</b> ".h($n['EstadoTexto'])."</div>";
      echo "</div>";

      if (!empty($n['cIndicacion'])) {
        echo "<div class='kv'><b>Indicación:</b> ".h($n['cIndicacion'])."</div>";
      }
      if (!empty($n['cObservacionesDerivar'])) {
        echo "<div class='kv'><b>Observaciones:</b> ".h($n['cObservacionesDerivar'])."</div>";
      }

      // Documentos
      echo "<div class='kv'><b>Documento principal:</b> "
   . renderDocPrincipal($GLOBALS['cnx'], $n)
   . "</div>";
      echo "<div class='kv kv-row'><b>Documentos Complementarios:</b> "
         . renderComplementariosTodos($GLOBALS['cnx'], (int)($n['iCodTramitePDF'] ?? 0))
         . "</div>";       
      

      // Hijos
      if (!empty($children[$id])) {
        foreach ($children[$id] as $hijoId) {
          renderNodo($hijoId, $nodes, $children, $nivel+1);
        }
      }
    echo "</div>"; // node-body
    echo "</details>";
}

function tieneFirmantesPrincipal($cnx, int $iCodTramite): bool {
  $sql = "SELECT COUNT(*) AS total
            FROM Tra_M_Tramite_Firma
           WHERE iCodTramite = ? AND iCodDigital IS NULL AND nFlgEstado = 1";
  $st  = sqlsrv_query($cnx, $sql, [$iCodTramite]);
  if (!$st) return false;
  $rw = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  return (int)($rw['total'] ?? 0) > 0;
}

function tieneFirmantesDigital($cnx, int $iCodTramite, int $iCodDigital): bool {
  $sql = "SELECT COUNT(*) AS total
            FROM Tra_M_Tramite_Firma
           WHERE iCodTramite = ? AND iCodDigital = ? AND nFlgEstado = 1";
  $st  = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);
  if (!$st) return false;
  $rw = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
  return (int)($rw['total'] ?? 0) > 0;
}


?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.chip-adjunto{display:inline-flex;align-items:center;background:#fff;border-radius:999px;padding:6px 12px;margin:4px 6px 4px 0;font-size:13px;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border:1px solid #dadce0;color:#000;text-decoration:none}
.chip-adjunto:hover{background:#f1f3f4;text-decoration:none}
.material-icons.chip-icon{font-size:18px;margin-right:8px;color:#d93025}
.material-icons.chip-doc{color:#1a73e8}
.chip-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;max-width:200px}
.detail-content{background:#fff;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:1.25rem;box-shadow:0 2px 6px rgba(0,0,0,.05);font-family:'Segoe UI',sans-serif;overflow:hidden}
.detail-header{background:#f7f9fc;padding:12px 16px;font-weight:600;font-size:15px;border-bottom:1px solid #eee}
.detail-body{padding:1rem;font-size:14px;color:#333;line-height:1.6}
.kv b{display:inline-block;min-width:160px;color:#111}
h3{font-size:18px;color:#0c2d5d;margin:1.25rem 0 .75rem;border-left:4px solid #0072CE;padding-left:10px}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;background:#eef2ff;color:#173b7a;margin-left:6px}
.row-flex{display:flex;gap:18px;flex-wrap:wrap}
.row-flex > .kv{flex:1 1 260px}
.node-body{padding:10px 16px;border-top:1px solid #eee;margin-bottom:6px}
summary{cursor:pointer;padding:8px 12px}
details{border:1px solid #eee;border-radius:8px;margin:8px 0}
details > summary:hover{background:#f8fafc}
.chips-wrap{display:flex;flex-wrap:wrap;gap:6px}
.chip-line{
    display:inline-flex;
    align-items:center;
    gap:6px;
    vertical-align:middle;
  }
  .btn-firmantes{
    display:inline-flex;
    align-items:center;
    text-decoration:none;
  }
  .btn-firmantes .material-icons{
    font-size:18px;
    line-height:1;
    color:#555;
  }
  /* mejora la alineación de los renglones de chips */
  .chips-wrap{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    align-items:center;
  }
</style>

<h3>DETALLES DEL EXPEDIENTE:  </h3>
<div class="detail-content">
  <div class="detail-header">DATOS GENERALES</div>
  <div class="detail-body">
    <div class="row-flex">
      <div class="kv"><b>Expediente:</b> <?= h($info['EXPEDIENTE'] ?? '') ?></div>
      <div class="kv"><b>Tipo de Documento:</b> <?= h($info['cDescTipoDoc'] ?? '') ?></div>
      <div class="kv"><b>Codificación:</b> <?= h($info['cCodificacion'] ?? '') ?></div>
    </div>
    <div class="row-flex">
      <div class="kv">
        <b>Asunto:</b> <?= h($info['cAsunto'] ?? '') ?>
      </div>
    </div>
    <div class="row-flex">
    <div class="kv">
        <b>Fecha Registro:</b> <?= ($info['fFecRegistro'] instanceof DateTimeInterface ? $info['fFecRegistro']->format('d/m/Y H:i:s') : '—') ?>
      </div>
    </div>
    <div class="kv"><b>Observaciones:</b> <?= h($info['cObservaciones'] ?? '') ?></div>
    <div class="kv" style="margin-top:8px;"><b>Documento Principal:</b>
      <?php if (!empty($info['documentoElectronico'])): ?>
      <?php
      // Normaliza a 'Y-m-d H:i' aunque venga string
      $freg = ($info['fFecRegistro'] instanceof DateTimeInterface)
        ? $info['fFecRegistro']->format('Y-m-d H:i')
        : date('Y-m-d H:i', strtotime($info['fFecRegistro'] ?? '1970-01-01 00:00'));
      $repo = ($freg >= '2025-09-04 16:00') ? 'STDD_marchablanca' : 'STD';
      ?>
      <a href="https://tramite.heves.gob.pe/<?= $repo ?>/cDocumentosFirmados/<?= urlencode($info['documentoElectronico']) ?>"
        class="chip-adjunto" target="_blank" title="<?= h($info['documentoElectronico']) ?>">
        <span class="material-icons chip-icon">picture_as_pdf</span>
        <span class="chip-text"><?= h($info['documentoElectronico']) ?></span>
      </a>
      <div class="kv kv-row" style="margin-top:6px;"><b>Documentos Complementarios:</b>
      <?= renderComplementariosTodos($cnx, (int)$iCodTramite, false) ?>
      </div>
      <?php else: ?>
      <span class="badge">No disponible</span>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- iTEMS SIGA: INICIO -->

<?php if (!empty($itemsSIGA)): ?>
    <h3>DETALLES DEL REQUERIMIENTO para el EXPEDIENTE <?= htmlspecialchars($expediente) ?></h3>
  <div class="detail-content section">
    <div class="detail-header">ÍTEMS SIGA</div>
    <div class="detail-body">
      <table style="width:100%; border-collapse: collapse; font-size: 14px;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th>PEDIDO SIGA</th>
            <th>EXTENSION</th>
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
              <td><?= $item['extension'] ?></td>
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

<h3>FLUJO DEL EXPEDIENTE  </h3>
<div class="detail-content">
  <div class="detail-header">Movimientos</div>
  <div class="detail-body">
    <?php
    if (empty($roots)) {
      echo "<em>No hay movimientos para este expediente.</em>";
    } else {
      foreach ($roots as $rootId) {
        renderNodo($rootId, $nodes, $children, 0);
      }
    }
    ?>
  </div>
</div>


<!-- Modal firmantes -->
<div id="modalFirmantes" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%);
     background:white; padding:20px; border:1px solid #ccc; box-shadow:0 4px 12px rgba(0,0,0,0.2); z-index:9999; max-width:700px; width:92%;">
    <div id="contenidoModalFirmantes">Cargando...</div>
    <div style="text-align:right; margin-top:10px;">
        <button onclick="cerrarModalFirmantes()">Cerrar</button>
    </div>
</div>

<script>
function abrirModalFirmantes(url){
  const cont = document.getElementById('contenidoModalFirmantes');
  const md   = document.getElementById('modalFirmantes');
  cont.innerHTML = 'Cargando...';
  fetch(url, {credentials:'same-origin'})
    .then(r=>r.text())
    .then(html => { cont.innerHTML = html; md.style.display = 'block'; })
    .catch(()=>{ cont.innerHTML = '<p style="color:#b00">No se pudo cargar.</p>'; md.style.display='block'; });
  return false;
}
function cerrarModalFirmantes(){
  document.getElementById('modalFirmantes').style.display = 'none';
  document.getElementById('contenidoModalFirmantes').innerHTML = '';
}
</script>