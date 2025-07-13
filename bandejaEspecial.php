<?php
include("head.php");
include("conexion/conexion.php");

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? 0;
$iCodPerfil     = $_SESSION['ID_PERFIL'] ?? 0;

// Capturar filtros
$filtroExpediente = $_GET['expediente'] ?? '';
$filtroExtension  = $_GET['extension'] ?? '';
$filtroAsunto     = $_GET['asunto'] ?? '';
$filtroDesde      = $_GET['desde'] ?? '';
$filtroHasta      = $_GET['hasta'] ?? '';
$filtroTipoDoc    = $_GET['tipo_documento'] ?? '';
$filtroOficinaOri = $_GET['oficina_origen'] ?? '';

$valorExpediente  = htmlspecialchars($filtroExpediente);
$valorExtension   = htmlspecialchars($filtroExtension);
$valorAsunto      = htmlspecialchars($filtroAsunto);
$valorDesde       = htmlspecialchars($filtroDesde);
$valorHasta       = htmlspecialchars($filtroHasta);

// Obtener tipos de documento internos
$tipoDocResult = sqlsrv_query($cnx, "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc");

// Obtener oficinas
$oficinasResult = sqlsrv_query($cnx, "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina");

// Armar condiciones dinámicas
$condiciones = [];
$params = [];

if ($filtroExpediente !== '') {
    $condiciones[] = "T.expediente LIKE ?";
    $params[] = "%$filtroExpediente%";
}
if ($filtroExtension !== '') {
    $condiciones[] = "T.extension = ?";
    $params[] = $filtroExtension;
}
if ($filtroAsunto !== '') {
    $condiciones[] = "T.cAsunto LIKE ?";
    $params[] = "%$filtroAsunto%";
}
if ($filtroDesde !== '') {
    $condiciones[] = "T.fFecRegistro >= ?";
    $params[] = $filtroDesde;
}
if ($filtroHasta !== '') {
    $condiciones[] = "T.fFecRegistro <= ?";
    $params[] = $filtroHasta;
}
if ($filtroTipoDoc !== '') {
    $condiciones[] = "T.cCodTipoDoc IN (SELECT cCodTipoDoc FROM Tra_M_Tipo_Documento WHERE cDescTipoDoc = ?)";
    $params[] = $filtroTipoDoc;
}
if ($filtroOficinaOri !== '') {
    $condiciones[] = "O1.cNomOficina = ?";
    $params[] = $filtroOficinaOri;
}

// Consulta principal (por trámite, no por movimiento)
$sql = "
    SELECT 
        T.iCodTramite,
        T.extension,
        T.expediente,
        T.cAsunto,
        T.fFecRegistro,
        T.cCodificacion,
        O1.cNomOficina AS OficinaOrigen
    FROM Tra_M_Tramite T
    LEFT JOIN Tra_M_Oficinas O1 ON O1.iCodOficina = T.iCodOficinaRegistro
";

// Aplicar condiciones si hay filtros
if (!empty($condiciones)) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY T.fFecRegistro DESC";

$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

// Obtener trámites
$tramites = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $row;
}
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.row {
  display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;
}
.input-container {
  position: relative; flex: 1; min-width: 250px;
}
.input-container input, .input-container select {
  width: 100%; padding: 20px 12px 8px; font-size: 15px;
  border: 1px solid #ccc; border-radius: 4px; background: #fff;
}
.input-container label {
  position: absolute; top: 20px; left: 12px; font-size: 14px;
  color: #666; background: #fff; padding: 0 4px; pointer-events: none;
  transition: 0.2s ease;
}
.input-container input:focus + label,
.input-container input:not(:placeholder-shown) + label,
.input-container select:focus + label,
.input-container select:valid + label {
  top: 0px; font-size: 12px; color: #333;
}
.titulo-principal {
  color: var(--primary, #005a86); font-size: 22px; font-weight: bold;
  margin-top: 0; margin-bottom: 20px;
}
td.acciones .btn-link {
  background: none; border: none; padding: 4px; cursor: pointer;
  color: #364897; font-size: 18px;
}
td.acciones .btn-link:hover { color: #1a237e; }
</style>

<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">BANDEJA ESPECIAL</div>

  <!-- Filtros -->
  <div class="card">
    <div class="card-title">CRITERIOS DE BÚSQUEDA</div>
    <form>
      <div class="row">
        <div class="input-container">
          <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" ">
          <label>N° Expediente</label>
        </div>
        <div class="input-container">
          <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" ">
          <label>Asunto</label>
        </div>
      </div>
      <div class="row">
        <div class="input-container">
          <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" ">
          <label>Desde</label>
        </div>
        <div class="input-container">
          <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" ">
          <label>Hasta</label>
        </div>
      </div>
      <div class="row">
        <div class="input-container select-flotante">
          <select name="tipo_documento">
            <option value="" hidden></option>
            <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $td['cDescTipoDoc'] ?>" <?= ($td['cDescTipoDoc'] == $filtroTipoDoc) ? 'selected' : '' ?>>
                <?= $td['cDescTipoDoc'] ?>
              </option>
            <?php endwhile; ?>
          </select>
          <label>Tipo de Documento</label>
        </div>
        <div class="input-container select-flotante">
          <select name="oficina_origen">
            <option value="" hidden></option>
            <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $of['cNomOficina'] ?>" <?= ($of['cNomOficina'] == $filtroOficinaOri) ? 'selected' : '' ?>>
                <?= $of['cNomOficina'] ?>
              </option>
            <?php endwhile; ?>
          </select>
          <label>Oficina de Origen</label>
        </div>
      </div>
      <div class="row" style="justify-content: flex-end;">
        <button type="submit" class="btn btn-primary">
          <span class="material-icons">search</span> Buscar
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='bandejaEspecial.php'">
          <span class="material-icons">autorenew</span> Reestablecer
        </button>
      </div>
    </form>
  </div>

  <!-- Resultados -->
  <div style="text-align: center; font-weight: bold; color: var(--primary); font-size: 18px; margin: 30px 0 10px;">
    REGISTROS
  </div>
  <table class="table table-bordered">
    <thead class="table-secondary">
      <tr>
        <th>Expediente</th>
        <th>Extensión</th>
        <th>Asunto</th>
        <th>Oficina de Origen</th>
        <th>Opciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tramites as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['expediente']) ?></td>
        <td><?= htmlspecialchars($t['extension']) ?></td>
        <td><?= htmlspecialchars($t['cAsunto']) ?></td>
        <td><?= htmlspecialchars($t['OficinaOrigen']) ?></td>
        <td class="acciones">
          <button class="btn btn-link ver-flujo-btn" data-id="<?= $t['iCodTramite'] ?>" data-extension="<?= $t['extension'] ?? 1 ?>" title="Ver Flujo">
            <span class="material-icons">saved_search</span>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    const ext = this.dataset.extension ?? 1;
    window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + ext, '_blank');
  });
});
</script>
