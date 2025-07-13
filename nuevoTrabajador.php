<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

// === CIFRADO AES-256-CBC CON CLAVE FIJA ===
define("ENCRYPTION_KEY", "Heves");
define("ENCRYPTION_METHOD", "AES-256-CBC");
define("FIXED_IV", substr(hash('sha256', 'Heves-IV'), 0, 16));

function encrypt_password($password) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = FIXED_IV;
    $encrypted = openssl_encrypt($password, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($encrypted);
}

$sqlOficinas = "SELECT iCodOficina, cNomOficina, cSiglaOficina FROM Tra_M_Oficinas WHERE iFlgEstado = 1 ORDER BY cNomOficina";
$resOficinas = sqlsrv_query($cnx, $sqlOficinas);
$oficinas = [];

while ($row = sqlsrv_fetch_array($resOficinas, SQLSRV_FETCH_ASSOC)) {
  $oficinas[] = [
    'iCodOficina' => $row['iCodOficina'],
    'cNomOficina' => $row['cNomOficina'],
    'cSiglaOficina' => $row['cSiglaOficina']
  ];
}

// === Cargar perfiles ===
$sqlPerfiles = "SELECT iCodPerfil, cDescPerfil FROM Tra_M_Perfil ORDER BY cDescPerfil ASC";
$rsPerfiles = sqlsrv_query($cnx, $sqlPerfiles);
$perfiles = [];
while ($row = sqlsrv_fetch_array($rsPerfiles, SQLSRV_FETCH_ASSOC)) {
    $perfiles[] = $row;
}
// === Guardado ===
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nombres  = trim($_POST['nombres'] ?? '');
  $apellidos = trim($_POST['apellidos'] ?? '');
  $usuario  = trim($_POST['usuario'] ?? '');
  $clave    = trim($_POST['clave'] ?? '');
  $oficina  = intval($_POST['oficina'] ?? 0);
  $perfil   = intval($_POST['perfil'] ?? 0);

  if ($nombres && $apellidos && $usuario && $clave && $oficina > 0 && $perfil > 0) {
      $claveCifrada = encrypt_password($clave);

                $sqlInsert = "INSERT INTO Tra_M_Trabajadores (
                    cNombresTrabajador, 
                    cApellidosTrabajador, 
                    cUsuario, 
                    cPassword, 
                    nFlgEstado, 
                    nEstadoClave
                )
                OUTPUT INSERTED.iCodTrabajador
                VALUES (?, ?, ?, ?, 1, 0)";
            $stmt = sqlsrv_query($cnx, $sqlInsert, [$nombres, $apellidos, $usuario, $claveCifrada]);

            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            $id = intval($row['iCodTrabajador']);

            if ($id > 0) {
            $sqlPerfil = "INSERT INTO Tra_M_Perfil_Ususario (iCodTrabajador, iCodPerfil, iCodOficina) 
                        VALUES (?, ?, ?)";
            $stmtPerfil = sqlsrv_query($cnx, $sqlPerfil, [$id, $perfil, $oficina]);

            if ($stmtPerfil) {
            echo "<script>alert('Trabajador creado correctamente'); window.location.href='mantenimientoTrabajadores.php';</script>";
            exit;
            } else {
            $error = "Error al asignar perfil/oficina al trabajador.";
            }
            } else {
            $error = "No se pudo recuperar el ID del trabajador.";
            }
            } else {
            $error = "Error al registrar el trabajador.";
            }
        } else {
            $error = "Todos los campos son obligatorios.";
        }
        }
?>
<div class="container" style="margin: 120px auto 60px auto; max-width: 650px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">CREACIÓN DE USUARIO</div>

  <div class="card" style="margin-top: 20px;">
    <?php if (!empty($error)): ?>
      <div style="color: red; font-weight: bold; margin-bottom: 15px;"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="nombres" required placeholder=" ">
        <label>Nombres del Trabajador</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="apellidos" required placeholder=" ">
        <label>Apellidos del Trabajador</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="usuario" required placeholder=" ">
        <label>Usuario</label>
      </div>

      <div class="input-container" style="margin-bottom: 20px; position: relative;">
        <input id="input-contraseña" type="password" name="clave" required placeholder=" ">
        <label for="input-contraseña">Contraseña Inicial</label>
        <span id="togglePassword" class="material-icons toggle-password"
              style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
          visibility_off
        </span>
      </div>

                <div class="form-row">
            <div class="input-container oficina-ancha" style="position: relative; margin-bottom: 5px;">
                <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off" required>
                <label for="nombreOficinaInput">Nombre de Oficina</label>
                <input type="hidden" id="oficinasDestino" name="oficina">
                <div id="sugerenciasOficinas" class="sugerencias-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: white; border: 1px solid #ccc; max-height: 150px; overflow-y: auto;"></div>
            </div>
            </div>

            <div class="input-container select-flotante" style="margin-bottom: 20px;">
            <select name="perfil" required>
                <option value="" disabled selected></option>
                <?php foreach ($perfiles as $per): ?>
                <option value="<?= $per['iCodPerfil'] ?>"><?= trim($per['cDescPerfil']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Perfil</label>
            </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Guardar</button>
        <button type="button" onclick="window.location.href='mantenimientoTrabajadores.php'" class="btn btn-secondary"><span class="material-icons">arrow_back</span> Cancelar</button>
      </div>
    </form>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
  // Mostrar/Ocultar contraseña
  document.getElementById('togglePassword')
    .addEventListener('click', function () {
      const input = document.getElementById('input-contraseña');
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      this.textContent = isHidden ? 'visibility' : 'visibility_off';
    });

  // Validación de reglas de contraseña
  function tieneSecuencia(str) {
    str = str.toLowerCase();
    const abc = 'abcdefghijklmnopqrstuvwxyz';
    const num = '0123456789';
    for (let i = 0; i <= str.length - 5; i++) {
      const seg = str.substring(i, i + 5);
      if (abc.includes(seg) || abc.split('').reverse().join('').includes(seg) ||
          num.includes(seg) || num.split('').reverse().join('').includes(seg)) {
        return true;
      }
    }
    return false;
  }

  document.querySelector('form').addEventListener('submit', function(e) {
    const clave = document.getElementById('input-contraseña').value;

    if (clave.length < 6 || clave.length > 20) {
      e.preventDefault();
      alert("La contraseña debe tener entre 6 y 20 caracteres.");
      return;
    }

    if (!/[A-Z]/.test(clave) || !/[0-9]/.test(clave) || !/[^A-Za-z0-9]/.test(clave)) {
      e.preventDefault();
      alert("Debe incluir al menos una mayúscula, un número y un símbolo.");
      return;
    }

    if (tieneSecuencia(clave)) {
      e.preventDefault();
      alert("No se permiten secuencias numéricas o alfabéticas consecutivas.");
      return;
    }
  });

  //PARA LA SUGERENCIA DE OFICINAS
  const oficinas = <?= json_encode($oficinas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  console.log(oficinas);

 

  function mostrarSugerenciasOficinas(filtro = "") {
    const contenedor = $('#sugerenciasOficinas');
    contenedor.empty();

    const filtroLower = filtro.toLowerCase();
    const resultados = oficinas.filter(ofi =>
      ofi.cNomOficina.toLowerCase().includes(filtroLower) ||
      ofi.cSiglaOficina.toLowerCase().includes(filtroLower)
    );

    if (resultados.length === 0) {
      contenedor.append('<div class="sugerencia-item" style="color:#888">Sin resultados</div>');
    }

    resultados.forEach(ofi => {
      const textoCompleto = `${ofi.cNomOficina} - ${ofi.cSiglaOficina}`;
      const item = $('<div class="sugerencia-item">').text(textoCompleto);
      item.on('click', function () {
        $('#nombreOficinaInput').val(textoCompleto);
        $('#oficinasDestino').val(ofi.iCodOficina);
        contenedor.hide();
      });
      contenedor.append(item);
    });

    contenedor.show();
  }

  $('#nombreOficinaInput').on('focus', function () {
    if ($(this).val().trim() === '') {
      mostrarSugerenciasOficinas('');
    }
  });

  $('#nombreOficinaInput').on('input', function () {
    const texto = $(this).val().trim();
    if (texto.length >= 1) {
      mostrarSugerenciasOficinas(texto);
    } else {
      $('#sugerenciasOficinas').hide();
    }
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('#nombreOficinaInput, #sugerenciasOficinas').length) {
      $('#sugerenciasOficinas').hide();
    }
  });
</script>
