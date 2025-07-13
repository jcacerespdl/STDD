<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

$id = isset($_GET['iCodTrabajador']) ? intval($_GET['iCodTrabajador']) : 0;

if ($id <= 0) {
    echo "<script>alert('ID de trabajador no válido'); window.location.href='mantenimientoTrabajadores.php';</script>";
    exit;
}

// Obtener datos del trabajador
$sql = "SELECT * FROM Tra_M_Trabajadores WHERE iCodTrabajador = ?";
$stmt = sqlsrv_query($cnx, $sql, [$id]);
$trabajador = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$trabajador) {
    echo "<script>alert('Trabajador no encontrado'); window.location.href='mantenimientoTrabajadores.php';</script>";
    exit;
}

$cNumDocIdentidad = $trabajador['cNumDocIdentidad'];
$tieneDNI = !empty($cNumDocIdentidad);

// Obtener oficinas y perfiles
$sqlOficinas = "SELECT iCodOficina, cNomOficina, cSiglaOficina FROM Tra_M_Oficinas WHERE iFlgEstado = 1 ORDER BY cNomOficina";
$resOficinas = sqlsrv_query($cnx, $sqlOficinas);
$oficinas = [];
while ($row = sqlsrv_fetch_array($resOficinas, SQLSRV_FETCH_ASSOC)) {
    $oficinas[] = $row;
}

$sqlPerfiles = "SELECT iCodPerfil, cDescPerfil FROM Tra_M_Perfil ORDER BY cDescPerfil ASC";
$rsPerfiles = sqlsrv_query($cnx, $sqlPerfiles);
$perfiles = [];
while ($row = sqlsrv_fetch_array($rsPerfiles, SQLSRV_FETCH_ASSOC)) {
    $perfiles[] = $row;
}

// Obtener perfiles asignados
$sqlAsignados = "SELECT PU.*, O.cNomOficina, P.cDescPerfil
                 FROM Tra_M_Perfil_Ususario PU
                 JOIN Tra_M_Oficinas O ON PU.iCodOficina = O.iCodOficina
                 JOIN Tra_M_Perfil P ON PU.iCodPerfil = P.iCodPerfil
                 WHERE PU.iCodTrabajador = ?";
$rsAsignados = sqlsrv_query($cnx, $sqlAsignados, [$id]);
$perfilesAsignados = [];
while ($row = sqlsrv_fetch_array($rsAsignados, SQLSRV_FETCH_ASSOC)) {
    $perfilesAsignados[] = $row;
}
?>
<div class="container" style="margin: 120px auto 60px auto; max-width: 700px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">EDICIÓN DE USUARIO</div>
  <form method="post" action="actualizarTrabajador.php">
    <input type="hidden" name="id" value="<?= $id ?>">

    <?php if ($tieneDNI): ?>
      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="dni" value="<?= htmlspecialchars($cNumDocIdentidad) ?>" readonly>
        <label>DNI</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" value="<?= htmlspecialchars($trabajador['cNombresTrabajador']) ?>" readonly>
        <label>Nombres</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" value="<?= htmlspecialchars($trabajador['cApellidosTrabajador']) ?>" readonly>
        <label>Apellidos</label>
      </div>
    <?php else: ?>
      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="dni" value="" placeholder=" ">
        <label>DNI</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="nombres" value="<?= htmlspecialchars($trabajador['cNombresTrabajador']) ?>" required placeholder=" ">
        <label>Nombres</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="apellidos" value="<?= htmlspecialchars($trabajador['cApellidosTrabajador']) ?>" required placeholder=" ">
        <label>Apellidos</label>
      </div>
    <?php endif; ?>

    <div class="input-container" style="margin-bottom: 20px;">
      <input type="text" name="usuario" value="<?= htmlspecialchars($trabajador['cUsuario']) ?>" required placeholder=" " style="text-transform: lowercase;">
      <label>Usuario</label>
    </div>

    <div class="input-container" style="margin-bottom: 20px; position: relative;">
      <input id="nuevaClave" type="password" name="clave" placeholder=" ">
      <label for="nuevaClave">Nueva Contraseña (opcional)</label>
      <span class="material-icons toggle-password"
            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
        visibility_off
      </span>
    </div>

    <hr>
    <h5>PERFILES POR OFICINA</h5>
    <div style="margin-bottom: 10px;">
      <?php foreach ($perfilesAsignados as $asignado): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
          <div><?= htmlspecialchars($asignado['cDescPerfil']) ?> - <?= htmlspecialchars($asignado['cNomOficina']) ?></div>
          <button type="button" class="btn btn-danger btn-sm" onclick="eliminarPerfil(<?= $asignado['iCodPerfilUsuario'] ?>)">Eliminar</button>
        </div>
      <?php endforeach; ?>
    </div>

    <div>
      <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modalAgregarPerfil').style.display='block'">Agregar Perfil</button>
    </div>

    <br>
    <div style="display: flex; justify-content: flex-end; gap: 10px;">
      <button type="submit" class="btn btn-primary">Guardar Cambios</button>
      <a href="mantenimientoTrabajadores.php" class="btn btn-secondary" style="text-decoration: none;">Cancelar</a>
    </div>
  </form>
</div>

<!-- Modal agregar perfil -->
<div id="modalAgregarPerfil" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
  <div style="background:#fff; max-width:400px; margin:10% auto; padding:20px; border-radius:10px; position:relative;">
    <form method="post" action="agregarPerfilUsuario.php">
      <input type="hidden" name="idTrabajador" value="<?= $id ?>">
      <h5>Agregar Perfil</h5>
      <div class="input-container select-flotante">
        <select name="perfil" required>
          <option value="" disabled selected>Seleccione perfil</option>
          <?php foreach ($perfiles as $per): ?>
            <option value="<?= $per['iCodPerfil'] ?>"><?= htmlspecialchars($per['cDescPerfil']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Perfil</label>
      </div>
      <div class="input-container select-flotante">
        <select name="oficina" required>
          <option value="" disabled selected>Seleccione oficina</option>
          <?php foreach ($oficinas as $ofi): ?>
            <option value="<?= $ofi['iCodOficina'] ?>"><?= htmlspecialchars($ofi['cNomOficina']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Oficina</label>
      </div>
      <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <button type="button" onclick="document.getElementById('modalAgregarPerfil').style.display='none'" class="btn btn-secondary">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function eliminarPerfil(idPerfilUsuario) {
  if (confirm('Deseas eliminar este perfil asignado?')) {
    window.location.href = 'eliminarPerfilUsuario.php?id=' + idPerfilUsuario;
  }
}
</script>
