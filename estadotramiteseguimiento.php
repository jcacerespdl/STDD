<?php
 include("headFlujo.php");
include("conexion/conexion.php");

$registro = $_GET['registro'] ?? '';
$clave = $_GET['clave'] ?? '';

if (empty($registro) || empty($clave)) {
    die("Datos incompletos para el seguimiento del trámite.");
}

// Validar existencia y recuperar datos del trámite externo
$sql = "SELECT TOP 1 * FROM Tra_M_Tramite WHERE expediente = ? AND cPassword = ? AND nFlgTipoDoc = 1";
$stmt = sqlsrv_query($cnx, $sql, [$registro, $clave]);
$tramite = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$tramite) {
    die("No se encontró el expediente externo con los datos proporcionados.");
}

$iCodTramite = $tramite['iCodTramite'];
?>
<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<!-- Estilos -->
<style>
    body {
    margin-top: 0 !important;
  }
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
</style>

<!-- Encabezado visual superior -->
<div style="margin: 0; padding: 0;">
  <img src="img/head_mesadepartes.jpg" style="display: block; width: 100%; margin: 0; padding: 0;">
</div>

<!-- Título centrado -->
<div style="text-align: center; margin-top: 10px; margin-bottom: 25px;">
  <h2 style="color: #1b53b2; font-weight: 700;">Estado de tu Trámite</h2>
</div>
<!-- Cuerpo HTML -->
<h3>DETALLE DE MOVIMIENTOS</h3>
<details class="detail-content" open style="margin-left: 40px; margin-right: 20px;">
<summary>DATOS GENERALES</summary>
<div style="padding: 1rem;">
  <div><b>Expediente:</b> <?= htmlspecialchars($tramite['expediente']) ?></div>
  <div><b>Extensión:</b> 1</div>
  <div><b>Asunto:</b> <?= htmlspecialchars($tramite['cAsunto']) ?></div>
  <div><b>Observaciones:</b> <?= htmlspecialchars($tramite['cObservaciones']) ?></div>
  <div><b>Fecha de Registro:</b> <?= $tramite['fFecRegistro']->format("d/m/Y H:i:s") ?></div>

  <h4>Datos del Solicitante</h4>
  <div><b>Documento:</b> <?= $tramite['cTipoDocumentoSolicitante'] ?> <?= $tramite['cNumeroDocumentoSolicitante'] ?></div>
  <div><b>Nombre:</b> <?= $tramite['cApePaternoSolicitante'] . ' ' . $tramite['cApeMaternoSolicitante'] . ', ' . $tramite['cNombresSolicitante'] ?></div>
  <div><b>Celular:</b> <?= $tramite['cCelularSolicitante'] ?></div>
  <div><b>Correo:</b> <?= $tramite['cCorreoSolicitante'] ?></div>
  <div><b>Dirección:</b> <?= $tramite['cDireccionSolicitante'] ?>, <?= $tramite['cDistritoSolicitante'] ?>, <?= $tramite['cProvinciaSolicitante'] ?>, <?= $tramite['cDepartamentoSolicitante'] ?></div>

  <h4>Entidad (si aplica)</h4>
  <div><b>RUC:</b> <?= $tramite['cRUCEntidad'] ?></div>
  <div><b>Razón Social:</b> <?= $tramite['cRazonSocialEntidad'] ?></div>

  <h4>Datos del Asegurado (si aplica)</h4>
  <div><b>Documento:</b> <?= $tramite['cTipoDocumentoAsegurado'] ?> <?= $tramite['cNumeroDocumentoAsegurado'] ?></div>
  <div><b>Nombre:</b> <?= $tramite['cApePaternoAsegurado'] . ' ' . $tramite['cApeMaternoAsegurado'] . ', ' . $tramite['cNombresAsegurado'] ?></div>
  <div><b>Celular:</b> <?= $tramite['cCelularAsegurado'] ?></div>
  <div><b>Correo:</b> <?= $tramite['cCorreoAsegurado'] ?></div>

  <div><b>Documento principal:</b><br>
  <?php if (!empty($tramite['documentoElectronico'])): ?>
  <?php
    $nombreOriginal = $tramite['documentoElectronico'];
    $nombreSinEspacios = preg_replace('/\s+/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
    $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $archivoFinal = $iCodTramite . '-' . $nombreSinEspacios . '.' . $extension;
  ?>
  <a href="./cAlmacenArchivos/<?= urlencode($archivoFinal) ?>" class="chip-adjunto" target="_blank">
    <span class="material-icons chip-icon">picture_as_pdf</span>
    <span class="chip-text"><?= htmlspecialchars($tramite['documentoElectronico']) ?></span>
  </a>
<?php else: ?>
  <span>No disponible</span>
<?php endif; ?>




  </div>
</div>
</details>
<?php
// CONSULTAR MOVIMIENTOS DEL TRÁMITE
$sqlMov = "SELECT 
    M.iCodMovimiento,
    M.iCodMovimientoDerivo,
    M.iCodOficinaOrigen,
    M.iCodOficinaDerivar,
    M.fFecDerivar,
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
    O1.cSiglaOficina AS OficinaOrigenAbbr,
    O2.cSiglaOficina AS OficinaDestinoAbbr,
    (SELECT TOP 1 T2.cNombresTrabajador + ' ' + T2.cApellidosTrabajador 
     FROM Tra_M_Perfil_ususario PU
     INNER JOIN Tra_M_Trabajadores T2 ON PU.iCodTrabajador = T2.iCodTrabajador
     WHERE PU.iCodOficina = M.iCodOficinaDerivar AND PU.iCodPerfil = 3
    ) AS JefeDestino,
    (SELECT T3.cNombresTrabajador + ' ' + T3.cApellidosTrabajador 
     FROM Tra_M_Trabajadores T3 WHERE T3.iCodTrabajador = M.iCodTrabajadorDelegado) AS NombreDelegado,
    (SELECT I.cIndicacion FROM Tra_M_Indicaciones I WHERE I.iCodIndicacion = M.iCodIndicacionDelegado) AS cIndicacionDelegado
FROM Tra_M_Tramite_Movimientos M
LEFT JOIN Tra_M_Oficinas O1 ON M.iCodOficinaOrigen = O1.iCodOficina
LEFT JOIN Tra_M_Oficinas O2 ON M.iCodOficinaDerivar = O2.iCodOficina
WHERE M.iCodTramite = ?
ORDER BY M.iCodMovimiento ASC";

$stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramite]);
$movimientos = [];
while ($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)) {
    $movimientos[] = $row;
}

// CONSTRUIR FLUJO JERÁRQUICO USANDO iCodMovimientoDerivo
function construirArbolPorMovimientoDerivo($movimientos) {
    $mapa = [];
    foreach ($movimientos as $mov) {
        $mov['hijos'] = [];
        $mapa[$mov['iCodMovimiento']] = $mov;
    }

    $raiz = [];
    foreach ($mapa as $id => &$mov) {
        $padre = $mov['iCodMovimientoDerivo'];
        if ($padre && isset($mapa[$padre])) {
            $mapa[$padre]['hijos'][] = &$mov;
        } else {
            $raiz[] = &$mov;
        }
    }

    return $raiz;
}
function obtenerDocumentoPrincipalMovimiento($cnx, $iCodTramite) {
    $stmt = sqlsrv_query($cnx, "SELECT documentoElectronico FROM Tra_M_Tramite WHERE iCodTramite = ?", [$iCodTramite]);
    if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $r['documentoElectronico'] ?? null;
    }
    return null;
}
// FUNCIÓN PARA RENDERIZAR UN MOVIMIENTO Y SUS HIJOS
function renderMovimiento($mov, $nivel = 0) {
    $sangria = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $nivel * 2);

    echo "<div style='margin-left: " . ($nivel * 20) . "px; margin-top: 10px;'>";
    echo "<details open>";
    echo "<summary style='font-weight: bold; cursor: pointer;'>{$sangria}{$mov['OficinaOrigenAbbr']} → {$mov['OficinaDestinoAbbr']}</summary>";
    echo "<div style='padding-left: 1rem;'>";

    echo "<div><b>Asunto:</b> " . htmlspecialchars($mov['cAsuntoDerivar']) . "</div>";
    echo "<div><b>Observaciones:</b> " . htmlspecialchars($mov['cObservacionesDerivar']) . "</div>";
    echo "<div><b>Prioridad:</b> " . htmlspecialchars($mov['cPrioridadDerivar']) . "</div>";
    echo "<div><b>Fecha de Envío:</b> " . ($mov['fFecDerivar'] instanceof DateTime ? $mov['fFecDerivar']->format("d/m/Y H:i:s") : '—') . "</div>";
    echo "<div><b>Fecha de Recepción:</b> " . ($mov['fFecRecepcion'] instanceof DateTime ? $mov['fFecRecepcion']->format("d/m/Y H:i:s") : '—') . "</div>";
    echo "<div><b>Dirigido a:</b> " . htmlspecialchars($mov['JefeDestino'] ?? '') . "</div>";

    $estadoTexto = 'Enviado';
    if ($mov['nEstadoMovimiento'] == 0) {
        $estadoTexto = 'Sin aceptar';
    } elseif ($mov['nEstadoMovimiento'] == 1) {
        $estadoTexto = 'Recibido';
    } elseif ($mov['nEstadoMovimiento'] == 3) {
        $estadoTexto = 'Delegado';
    } elseif ($mov['nEstadoMovimiento'] == 5) {
        $estadoTexto = 'Finalizado';
    }
    echo "<div><b>Estado:</b> " . $estadoTexto . "</div>";

    // Delegación (si existe)
    if (!empty($mov['iCodTrabajadorDelegado']) || !empty($mov['iCodIndicacionDelegado']) || !empty($mov['cObservacionesDelegado'])) {
        echo "<hr>";
        echo "<div><b>Delegado a:</b> " . htmlspecialchars($mov["NombreDelegado"] ?? 'N/A') . "</div>";
        if (!empty($mov["cIndicacionDelegado"])) {
            echo "<div><b>Indicación:</b> " . htmlspecialchars($mov["cIndicacionDelegado"]) . "</div>";
        }
        if (!empty($mov["cObservacionesDelegado"])) {
            echo "<div><b>Observaciones:</b> " . htmlspecialchars($mov["cObservacionesDelegado"]) . "</div>";
        }
        if (!empty($mov["fFecDelegado"]) && $mov["fFecDelegado"] instanceof DateTime) {
            echo "<div><b>Fecha de Delegación:</b> " . $mov["fFecDelegado"]->format("d/m/Y H:i:s") . "</div>";
        }
        if (!empty($mov["fFecDelegadoRecepcion"]) && $mov["fFecDelegadoRecepcion"] instanceof DateTime) {
            echo "<div><b>Recepción Delegación:</b> " . $mov["fFecDelegadoRecepcion"]->format("d/m/Y H:i:s") . "</div>";
        }
    }


        // DOCUMENTO PRINCIPAL
                 



    echo "</div>"; // cierre interno
    echo "</details>";

    // Renderizar hijos recursivamente
    foreach ($mov['hijos'] as $hijo) {
        renderMovimiento($hijo, $nivel + 1);
    }

    echo "</div>";
}

// CONSTRUIR Y MOSTRAR ÁRBOL
$estructuraJerarquica = construirArbolPorMovimientoDerivo($movimientos);
?>

<details class="detail-content" open style="margin-left: 40px; margin-right: 20px;">
<summary>FLUJO DEL EXPEDIENTE</summary>
<div style="padding: 1rem;">
    <?php foreach ($estructuraJerarquica as $mov): ?>
        <?php renderMovimiento($mov); ?>
    <?php endforeach; ?>
</div>
</details>
