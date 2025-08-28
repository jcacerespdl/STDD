<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

define('ENCRYPTION_KEY', 'Heves');
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('FIXED_IV', substr(hash('sha256', 'Heves-IV'), 0, 16));

function encrypt_password($password) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = FIXED_IV;
    $encrypted = openssl_encrypt($password, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($encrypted);
}

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

function decrypt_password($encrypted_password) {
  $key = hash('sha256', 'Heves', true);
  $iv = substr(hash('sha256', 'Heves-IV'), 0, 16);
  $decoded = base64_decode($encrypted_password);
  return openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

$claveTextoPlano = decrypt_password($trabajador['cPassword']);

$cNumDocIdentidad = $trabajador['cNumDocIdentidad'] ?? '';
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
<style>
  .titulo-principal{font-size:20px;font-weight:700;margin-bottom:10px}
  .input-container{position:relative}
  .input-container input,
  .input-container select{width:100%;padding:10px 40px 10px 12px;border:1px solid #ccc;border-radius:8px;outline:none;background:#fff}
  .input-container label{position:absolute;left:12px;top:-9px;background:#fff;padding:0 6px;font-size:12px;color:#666}
  .toggle-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#555}
  .badge-estado{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;border:1px solid #ddd}
  .badge-activo{background:#e6f4ea;color:#137333;border-color:#cce9d6}
  .badge-inactivo{background:#fce8e6;color:#c5221f;border-color:#fad2cf}
</style>

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
      <input type="text" name="usuario" value="<?= htmlspecialchars($trabajador['cUsuario']) ?>" required placeholder=" " style="text-transform: uppercase;">
      <label>Usuario</label>
    </div>

    <!-- Estado del usuario -->
    <div class="input-container select-flotante" style="margin-bottom: 20px;">
      <select name="nFlgEstado" required>
        <option value="" disabled <?= ($trabajador['nFlgEstado'] !== 0 && $trabajador['nFlgEstado'] !== 1) ? 'selected' : '' ?>>Seleccione estado</option>
        <option value="1" <?= (intval($trabajador['nFlgEstado']) === 1 ? 'selected' : '') ?>>ACTIVO</option>
        <option value="0" <?= (intval($trabajador['nFlgEstado']) === 0 ? 'selected' : '') ?>>INACTIVO</option>
      </select>
      <label>Estado</label>
    </div>

    <!-- Contraseña actual (SOLO con toggle de visibilidad, readonly) -->
    <div class="input-container" style="margin-bottom: 20px;">
      <input id="claveActual" type="password" value="<?= htmlspecialchars($claveTextoPlano) ?>" readonly placeholder=" ">
      <label>Contraseña Actual</label>
      <span id="btnToggleClaveActual" class="toggle-eye" title="Mostrar/Ocultar">
        <span class="material-icons" id="iconClaveActual">visibility</span>
      </span>
    </div>

    <!-- Nueva contraseña (SIN toggle) -->
    <div class="input-container" style="margin-bottom: 20px;">
      <input id="nuevaClave" type="password" name="clave" placeholder=" ">
      <label for="nuevaClave">Nueva Contraseña (opcional)</label>
    </div>

    <div style="margin-bottom: 30px; text-align: right;">
      <button type="submit" class="btn btn-primary">
        <span class="material-icons">save</span> Actualizar Datos
      </button>
    </div>

    <hr>
    <h5>PERFILES POR OFICINA</h5>
    <div style="margin-bottom: 10px;">
      <?php if (count($perfilesAsignados) === 0): ?>
        <div style="color:#777; font-size:14px; margin-bottom:6px;">Sin perfiles asignados.</div>
      <?php endif; ?>

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
      <a href="mantenimientoTrabajadores.php" class="btn btn-secondary" style="text-decoration: none;">Volver</a>
    </div>
  </form>
</div>

<!-- Modal agregar perfil -->
<div id="modalAgregarPerfil" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
  <div style="background:#fff; max-width:400px; margin:10% auto; padding:20px; border-radius:10px; position:relative;">
    <form method="post" action="agregarPerfilUsuario.php">
      <input type="hidden" name="idTrabajador" value="<?= $id ?>">
      <h5>Agregar Perfil</h5>
      <div class="input-container select-flotante" style="margin-bottom: 14px;">
        <select name="perfil" required>
          <option value="" disabled selected>Seleccione perfil</option>
          <?php foreach ($perfiles as $per): ?>
            <option value="<?= $per['iCodPerfil'] ?>"><?= htmlspecialchars($per['cDescPerfil']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Perfil</label>
      </div>
      <div class="input-container select-flotante" style="margin-bottom: 6px;">
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
  if (confirm('¿Deseas eliminar este perfil asignado?')) {
    window.location.href = 'eliminarPerfilUsuario.php?id=' + idPerfilUsuario;
  }
}

// Toggle SOLO para "Contraseña Actual"
(function(){
  var input = document.getElementById('claveActual');
  var btn = document.getElementById('btnToggleClaveActual');
  var icon = document.getElementById('iconClaveActual');

  if (btn && input && icon) {
    btn.addEventListener('click', function(){
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility';
      }
    });
  }
})();
</script>
