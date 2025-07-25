<?php
include("headFlujo.php");
include("conexion/conexion.php");

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;
$extension = isset($_GET['extension']) ? intval($_GET['extension']) : 1;

// Obtener datos del trámite
$sqlTramite = "SELECT 
    t.expediente, t.cAsunto, t.fFecRegistro, t.cObservaciones, t.documentoElectronico, t.cCodTipoDoc,
    td.cDescTipoDoc,
    t.extension, 
    t.cTipoDocumentoSolicitante, t.cNumeroDocumentoSolicitante, t.cCelularSolicitante, t.cCorreoSolicitante,
    t.cApePaternoSolicitante, t.cApeMaternoSolicitante, t.cNombresSolicitante,
    t.cDepartamentoSolicitante, t.cProvinciaSolicitante, t.cDistritoSolicitante, t.cDireccionSolicitante,
    t.cRUCEntidad, t.cRazonSocialEntidad,
    t.cTipoDocumentoAsegurado, t.cNumeroDocumentoAsegurado, t.cCelularAsegurado, t.cCorreoAsegurado,
    t.cApePaternoAsegurado, t.cApeMaternoAsegurado, t.cNombresAsegurado,
    t.cLinkArchivo
 FROM Tra_M_Tramite t
 LEFT JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
 WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$info = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
?>
<!-- Estilos básicos -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
    text-decoration: none;
    color: black;
}
.chip-adjunto:hover {
    background-color: #e8eaed;
}
.material-icons.chip-icon { font-size: 18px; margin-right: 8px; color: #d93025; }
.chip-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; max-width: 180px; }
</style>

<!-- DATOS GENERALES -->
<h3>DATOS GENERALES DEL TRÁMITE</h3>
<div style="padding: 1rem;">
    <div><b>Expediente:</b> <?= htmlspecialchars($info['expediente']) ?></div>
    <div><b>Extensión:</b> <?= intval($info['extension']) ?></div>
    <div><b>Tipo de Documento:</b> <?= $info['cDescTipoDoc'] ?></div>
    <div><b>Asunto:</b> <?= htmlspecialchars($info['cAsunto']) ?></div>
    <div><b>Fecha Registro:</b> <?= ($info['fFecRegistro'] instanceof DateTime) ? $info['fFecRegistro']->format("d/m/Y H:i:s") : '' ?></div>
    <div><b>Observaciones:</b> <?= htmlspecialchars($info['cObservaciones']) ?: '(Sin observaciones)' ?></div>
<!-- ===== DATOS DEL SOLICITANTE ===== -->
<h4 style="margin-top: 1.5rem;">Solicitante</h4>
<div style="padding: 0.5rem 1rem;">
    <div><b>Tipo de Documento:</b> <?= htmlspecialchars($info['cTipoDocumentoSolicitante']) ?></div>
    <div><b>N° Documento:</b> <?= htmlspecialchars($info['cNumeroDocumentoSolicitante']) ?></div>
    <div><b>Celular:</b> <?= htmlspecialchars($info['cCelularSolicitante']) ?></div>
    <div><b>Correo:</b> <?= htmlspecialchars($info['cCorreoSolicitante']) ?></div>
    <div><b>Apellidos y Nombres:</b>
        <?= htmlspecialchars(trim("{$info['cApePaternoSolicitante']} {$info['cApeMaternoSolicitante']} {$info['cNombresSolicitante']}")) ?>
    </div>
    <div><b>Departamento:</b> <?= htmlspecialchars($info['cDepartamentoSolicitante']) ?></div>
    <div><b>Provincia:</b> <?= htmlspecialchars($info['cProvinciaSolicitante']) ?></div>
    <div><b>Distrito:</b> <?= htmlspecialchars($info['cDistritoSolicitante']) ?></div>
    <div><b>Dirección:</b> <?= htmlspecialchars($info['cDireccionSolicitante']) ?></div>
</div>

<!-- ===== DATOS DE LA ENTIDAD ===== -->
<?php if (!empty($info['cRUCEntidad'])): ?>
<h4>Entidad Representada</h4>
<div style="padding: 0.5rem 1rem;">
    <div><b>RUC:</b> <?= htmlspecialchars($info['cRUCEntidad']) ?></div>
    <div><b>Razón Social:</b> <?= htmlspecialchars($info['cRazonSocialEntidad']) ?></div>
</div>
<?php endif; ?>

<!-- ===== DATOS DEL ASEGURADO ===== -->
<?php if (!empty($info['cNumeroDocumentoAsegurado']) || !empty($info['cNombresAsegurado'])): ?>
<h4>Asegurado</h4>
<div style="padding: 0.5rem 1rem;">
    <div><b>Tipo de Documento:</b> <?= htmlspecialchars($info['cTipoDocumentoAsegurado']) ?></div>
    <div><b>N° Documento:</b> <?= htmlspecialchars($info['cNumeroDocumentoAsegurado']) ?></div>
    <div><b>Celular:</b> <?= htmlspecialchars($info['cCelularAsegurado']) ?></div>
    <div><b>Correo:</b> <?= htmlspecialchars($info['cCorreoAsegurado']) ?></div>
    <div><b>Apellidos y Nombres:</b>
        <?= htmlspecialchars(trim("{$info['cApePaternoAsegurado']} {$info['cApeMaternoAsegurado']} {$info['cNombresAsegurado']}")) ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== LINK ADICIONAL (si existe) ===== -->
<?php if (!empty($info['cLinkArchivo'])): ?>
<div style="padding: 0.5rem 1rem;">
    <b>Link de Descarga Adicional:</b>
    <a href="<?= htmlspecialchars($info['cLinkArchivo']) ?>" target="_blank">
        <?= htmlspecialchars($info['cLinkArchivo']) ?>
    </a>
</div>
<?php endif; ?>
    <div><b>Doc. Principal:</b>
        <?php if (!empty($info['documentoElectronico'])): ?>
            <a href="./cDocumentosFirmados/<?= urlencode($info['documentoElectronico']) ?>" class="chip-adjunto" target="_blank">
                <span class="material-icons chip-icon">picture_as_pdf</span>
                <span class="chip-text"><?= htmlspecialchars($info['documentoElectronico']) ?></span>
            </a>
        <?php else: ?>
            <span>No disponible</span>
        <?php endif; ?>
    </div>
</div>
<?php
// === CONSULTAR MOVIMIENTOS DE ESTE TRÁMITE Y EXTENSIÓN ===
$sqlMov = "SELECT 
    M.iCodMovimiento,
    M.iCodMovimientoDerivo,
    M.fFecDerivar,
    M.fFecRecepcion,
    M.cObservacionesDerivar,
    M.cPrioridadDerivar,
    M.nEstadoMovimiento,
    M.cFlgTipoMovimiento,
    O1.cNomOficina AS OficinaOrigen,
    O2.cNomOficina AS OficinaDestino
 FROM Tra_M_Tramite_Movimientos M
 LEFT JOIN Tra_M_Oficinas O1 ON M.iCodOficinaOrigen = O1.iCodOficina
 LEFT JOIN Tra_M_Oficinas O2 ON M.iCodOficinaDerivar = O2.iCodOficina
 WHERE M.iCodTramite = ? AND M.extension = ?
 ORDER BY M.fFecDerivar ASC";

$stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramite, $extension]);

$movimientos = [];
while ($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)) {
    $row['hijos'] = [];
    $movimientos[$row['iCodMovimiento']] = $row;
}

// === ARMAR ÁRBOL DE MOVIMIENTOS ===
$arbol = [];
foreach ($movimientos as $id => &$mov) {
    $padre = $mov['iCodMovimientoDerivo'];
    if ($padre && isset($movimientos[$padre])) {
        $movimientos[$padre]['hijos'][] = &$mov;
    } else {
        $arbol[] = &$mov;
    }
}
unset($mov); // liberar referencia

// === RENDER ===
function renderMovimientoMesaPartes($mov, $nivel = 0) {
    $sangria = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $nivel);
    $estadoMovimiento = (int)$mov['nEstadoMovimiento'];
switch ($estadoMovimiento) {
    case 0:
        $estado = 'Sin aceptar';
        break;
    case 1:
        $estado = 'Recibido';
        break;
    case 3:
        $estado = 'Delegado';
        break;
    case 5:
        $estado = 'Finalizado';
        break;
    default:
        $estado = '—';
}
    ?>
    <div style="margin-top: 10px;">
        <details open>
        <summary style="font-weight: bold; padding-left: <?= $nivel * 20 ?>px;">
            <?= $sangria ?><?= htmlspecialchars($mov['OficinaOrigen']) ?> → <?= htmlspecialchars($mov['OficinaDestino']) ?>
        </summary>
        <div style="padding-left: 1rem;">
            <div><b>Prioridad:</b> <?= htmlspecialchars($mov['cPrioridadDerivar']) ?></div>
            <div><b>Fecha de Envío:</b> <?= ($mov['fFecDerivar'] instanceof DateTime) ? $mov['fFecDerivar']->format("d/m/Y H:i:s") : '—' ?></div>
            <div><b>Fecha de Recepción:</b> <?= ($mov['fFecRecepcion'] instanceof DateTime) ? $mov['fFecRecepcion']->format("d/m/Y H:i:s") : '—' ?></div>
            <div><b>Estado:</b> <?= $estado ?></div>
            <?php if (!empty($mov['cObservacionesDerivar'])): ?>
            <div><b>Observaciones:</b> <?= htmlspecialchars($mov['cObservacionesDerivar']) ?></div>
            <?php endif; ?>
        </div>
        </details>
    </div>
    <?php
    foreach ($mov['hijos'] as $hijo) {
        renderMovimientoMesaPartes($hijo, $nivel + 1);
    }
}
?>

<!-- MOSTRAR FLUJO -->
<h3>FLUJO DE MOVIMIENTOS</h3>
<div style="padding: 1rem;">
    <?php foreach ($arbol as $mov) renderMovimientoMesaPartes($mov); ?>
</div>
