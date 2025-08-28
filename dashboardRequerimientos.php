<?php
include("head.php");
include("conexion/conexion.php");
date_default_timezone_set('America/Lima');

// --- Filtros (opcionales) ---
$fExpediente = isset($_GET['expediente']) ? trim($_GET['expediente']) : '';
$fAsunto     = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$fDesde      = isset($_GET['desde']) ? $_GET['desde'] : '';
$fHasta      = isset($_GET['hasta']) ? $_GET['hasta'] : '';

$params = [];
$wheres = [];

// Solo iniciales tipo 108 o 109, enviados y extensión 1
$wheres[] = "T.nFlgEnvio = 1";
$wheres[] = "T.extension = 1";
$wheres[] = "T.cCodTipoDoc IN (108,109)";

if ($fExpediente !== '') { $wheres[] = "T.EXPEDIENTE LIKE ?";    $params[] = "%$fExpediente%"; }
if ($fAsunto     !== '') { $wheres[] = "T.cAsunto   LIKE ?";      $params[] = "%$fAsunto%"; }
if ($fDesde      !== '') { $wheres[] = "(T.fFecRegistro >= ?)";   $params[] = $fDesde . " 00:00:00"; }
if ($fHasta      !== '') { $wheres[] = "(T.fFecRegistro <= ?)";   $params[] = $fHasta . " 23:59:59"; }

$whereSql = implode("\nAND ", $wheres);

// 1) CTE 'Iniciales' elige 1 solo trámite inicial por EXPEDIENTE (extensión=1, 108/109)
// 2) OUTER APPLY toma el último movimiento TERMINAL de la extensión 1 para saber la oficina actual
$sql = "
WITH Iniciales AS (
  SELECT
      T.iCodTramite,
      T.EXPEDIENTE,
      T.extension,
      T.cCodificacion,
      T.cAsunto,
      T.fFecRegistro,
      T.documentoElectronico,
      T.iCodOficinaRegistro,
      ROW_NUMBER() OVER (
        PARTITION BY T.EXPEDIENTE
        ORDER BY T.fFecRegistro DESC, T.iCodTramite DESC
      ) AS rn
  FROM Tra_M_Tramite T
  WHERE
      $whereSql
)
SELECT
    I.iCodTramite,
    I.EXPEDIENTE,
    I.extension,
    I.cCodificacion,
    I.cAsunto,
    I.fFecRegistro,
    I.documentoElectronico,
    OREG.cNomOficina                              AS OficinaOrigen,
    ISNULL(OACT.cNomOficina, OREG.cNomOficina)    AS OficinaActual,
    MX.nEstadoMovimiento,
    MX.fFecRecepcion,
    MX.iCodMovimiento
FROM Iniciales I
JOIN Tra_M_Oficinas OREG ON OREG.iCodOficina = I.iCodOficinaRegistro
OUTER APPLY (
    SELECT TOP 1 M.*
    FROM Tra_M_Tramite_Movimientos M
    WHERE M.iCodTramite = I.iCodTramite
      AND M.extension   = 1
      AND NOT EXISTS (
          SELECT 1 FROM Tra_M_Tramite_Movimientos M2
          WHERE M2.iCodMovimientoDerivo = M.iCodMovimiento
      )
    ORDER BY M.iCodMovimiento DESC
) MX
LEFT JOIN Tra_M_Oficinas OACT ON OACT.iCodOficina = MX.iCodOficinaDerivar
WHERE I.rn = 1
ORDER BY I.fFecRegistro DESC";

$stmt = sqlsrv_query($cnx, $sql, $params);

$rows = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
  }
}

?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root { --primary:#005a86; --secondary:#c69157; }
body > .contenedor { margin-top: 105px; }
.barra { background:var(--primary); color:#fff; padding:10px 18px; font-weight:600; font-size:15px; }
.filtros { display:flex; gap:12px; flex-wrap:wrap; padding:14px 18px; border:1px solid #ddd; background:#fff; }
.filtros .box { position:relative; }
.filtros input { height:38px; padding:6px 10px; border:1px solid #ccc; border-radius:6px; }
.filtros .btn { height:38px; border:none; border-radius:6px; padding:0 14px; cursor:pointer; display:flex; align-items:center; gap:6px; }
.btn.primary { background:var(--primary); color:#fff; }
.btn.secondary { background:var(--secondary); color:#fff; }
.table { width:100%; background:#fff; }
.table thead { background:#f1f5f9; }
.table th, .table td { padding:10px; border-bottom:1px solid #e6e6e6; font-size:14px; }
.chip-adjunto {
  display:inline-flex; align-items:center; gap:6px; border:1px solid #dadce0;
  padding:5px 10px; border-radius:999px; text-decoration:none; color:#000; max-width:260px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.chip-adjunto:hover { background:#f1f3f4; }
.material-icons.chip { font-size:18px; color:#d93025; }
.badge { font-weight:600; font-size:12px; padding:2px 8px; border-radius:999px; display:inline-block; }
.badge.pend { color:#d9534f; background:#fdecea; }
.badge.proc { color:#0d6efd; background:#e7f0ff; }
.badge.fin  { color:#2e7d32; background:#e6f4ea; }
.badge.obs  { color:#8d6e63; background:#f3e5f5; }
</style>

<div class="contenedor">
  <div class="barra">DASHBOARD DE REQUERIMIENTOS  </div>

  <form class="filtros" method="get">
    <div class="box">
      <input type="text" name="expediente" placeholder="Expediente" value="<?=htmlspecialchars($fExpediente)?>">
    </div>
    <div class="box">
      <input type="text" name="asunto" placeholder="Asunto" value="<?=htmlspecialchars($fAsunto)?>">
    </div>
    <div class="box">
      <input type="date" name="desde" value="<?=htmlspecialchars($fDesde)?>">
    </div>
    <div class="box">
      <input type="date" name="hasta" value="<?=htmlspecialchars($fHasta)?>">
    </div>
    <button class="btn primary" type="submit"><span class="material-icons">search</span>Buscar</button>
    <button class="btn secondary" type="button" onclick="location.href='dashboardRequerimientos.php'">
      <span class="material-icons">autorenew</span>Reiniciar
    </button>
  </form>

  <div class="barra">REGISTROS</div>

  <table class="table table-bordered">
    <thead>
      <tr>
        <th style="width:120px;">Expediente</th>
        <th style="width:90px;">Extensión</th>
        <th style="width:320px;">Documento Inicial</th>
        <th>Asunto</th>
        <th style="width:220px;">Oficina Origen</th>
        <th style="width:220px;">Oficina Actual</th>
        <th style="width:110px;">Ver flujo</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" style="text-align:center; color:#777;">Sin resultados</td></tr>
      <?php else: foreach ($rows as $r): 
        $estado = (int)($r['nEstadoMovimiento'] ?? -1);
        $badge = '<span class="badge pend">Sin aceptar</span>';
        if ($estado === 1 || $estado === 3) $badge = '<span class="badge proc">En proceso</span>';
        if ($estado === 5) $badge = '<span class="badge fin">Finalizado</span>';
        if ($estado === 6) $badge = '<span class="badge obs">Observado</span>';

        $docLink = 'Sin documento';
        if (!empty($r['documentoElectronico'])) {
          $file = './cDocumentosFirmados/' . urlencode($r['documentoElectronico']);
          $docLink = '<a class="chip-adjunto" href="'.$file.'" target="_blank" title="'.htmlspecialchars($r['documentoElectronico']).'">
                        <span class="material-icons chip">picture_as_pdf</span>
                        <span style="overflow:hidden;text-overflow:ellipsis;">'.htmlspecialchars($r['cCodificacion'] ?: $r['documentoElectronico']).'</span>
                      </a>';
        } else {
          // Si quieres mostrar la codificación aunque no haya PDF:
          if (!empty($r['cCodificacion'])) {
            $docLink = htmlspecialchars($r['cCodificacion']);
          }
        }

        $fecReg = ($r['fFecRegistro'] instanceof DateTime) ? $r['fFecRegistro']->format("d/m/Y H:i") : '';
        $fecRec = ($r['fFecRecepcion'] instanceof DateTime) ? $r['fFecRecepcion']->format("d/m/Y H:i") : '';
      ?>
        <tr>
          <td><?=htmlspecialchars($r['EXPEDIENTE'])?></td>
          <td><?=htmlspecialchars($r['extension'])?></td>
          <td>
            <?= $docLink ?>
            <div style="font-size:12px; color:#666; margin-top:4px;">
              <b>Registro:</b> <?=$fecReg?> 
              <?php if ($fecRec): ?> | <b>Recepción:</b> <?=$fecRec?><?php endif; ?>
              &nbsp;|&nbsp; <?= $badge ?>
            </div>
          </td>
          <td><?=htmlspecialchars($r['cAsunto'])?></td>
          <td><?=htmlspecialchars($r['OficinaOrigen'])?></td>
          <td><?=htmlspecialchars($r['OficinaActual'])?></td>
          <td style="text-align:center;">
            <a href="bandejaFlujoRaiz.php?iCodTramite=<?=$r['iCodTramite']?>"
               target="_blank" title="Ver flujo raíz" style="color:#6c757d; text-decoration:none;">
              <span class="material-icons" style="font-size:22px;">device_hub</span>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
