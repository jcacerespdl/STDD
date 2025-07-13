<?php
include("head.php");
include("conexion/conexion.php");

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina = $_SESSION['iCodOficinaLogin'];

// Capturar filtros
$filtroExpediente = isset($_GET['expediente']) ? trim($_GET['expediente']) : '';
$valorExpediente  = htmlspecialchars($filtroExpediente);

$filtroExtension = isset($_GET['extension']) ? trim($_GET['extension']) : '';
$valorExtension  = htmlspecialchars($filtroExtension);

$filtroAsunto = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valorAsunto  = htmlspecialchars($filtroAsunto);

$filtroDesde = isset($_GET['desde']) ? $_GET['desde'] : '';
$valorDesde  = htmlspecialchars($filtroDesde);

$filtroHasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';
$valorHasta  = htmlspecialchars($filtroHasta);

// Obtener tipos de documento internos
$tipoDocQuery = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

// Obtener oficinas
$oficinasQuery = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

$sql = "
    SELECT 
        M1.iCodMovimiento,
        M1.nEstadoMovimiento,
        T.expediente,
        M1.extension,
        T.iCodTramite,
        T.cCodificacion,
        T.cAsunto,
        T.fFecRegistro,
        T.documentoElectronico,
        M1.cDocumentoFinalizacion,
        M1.cObservacionesFinalizar,
        O1.cNomOficina AS OficinaOrigen,
        O2.cNomOficina AS OficinaDestino
    FROM Tra_M_Tramite_Movimientos M1
    INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M1.iCodTramite
    INNER JOIN Tra_M_Oficinas O1 ON O1.iCodOficina = M1.iCodOficinaOrigen
    INNER JOIN Tra_M_Oficinas O2 ON O2.iCodOficina = M1.iCodOficinaDerivar
    WHERE M1.iCodOficinaDerivar = ?
      AND M1.nEstadoMovimiento = 5
    ORDER BY T.fFecRegistro DESC";

$params = [$iCodOficina];
$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

$tramites = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $row;
}
?>

<table class="table table-bordered">
  <thead class="table-secondary">
    <tr>
      <th>Expediente</th>
      <th>Extensión</th>
      <th>Documento</th>
      <th>Asunto</th>
      <th>Oficina de Origen</th>
      <th>Opciones</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tramites as $tramite): ?>
      <tr>
        <td><?= htmlspecialchars($tramite['expediente']) ?></td>
        <td><?= htmlspecialchars($tramite['extension']) ?></td>
        <td>
          <?php if (!empty($tramite['cDocumentoFinalizacion'])): ?>
            <a href="./cAlmacendeArchivos/<?= rawurlencode($tramite['cDocumentoFinalizacion']) ?>" target="_blank">
              <span class="material-icons">insert_drive_file</span>
            </a>
          <?php else: ?>
            <span style="color: gray;">Sin documento</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($tramite['cAsunto']) ?></td>
        <td><?= htmlspecialchars($tramite['OficinaOrigen']) ?></td>
        <td class="acciones">
          <!-- Ver Flujo -->
          <button class="btn btn-link ver-flujo-btn" data-id="<?= $tramite['iCodTramite'] ?>" data-extension="<?= $tramite['extension'] ?? 1 ?>" title="Ver Flujo">
            <span class="material-icons">device_hub</span>
          </button>
          <!-- Revertir -->
          <button class="btn btn-link" title="Revertir Finalización" onclick="revertirFinalizacion(<?= $tramite['iCodMovimiento'] ?>)">
            <span class="material-icons">undo</span>
          </button>
          <!-- Editar -->
          <button class="btn btn-link" title="Editar Finalización" onclick="abrirEditarFinalizacion(<?= $tramite['iCodMovimiento'] ?>)">
            <span class="material-icons">edit</span>
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    const extension = this.dataset.extension ?? 1;
    window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + extension, '_blank');
  });
});
</script>
