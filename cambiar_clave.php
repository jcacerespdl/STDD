<?php
session_start();
include_once "./conexion/conexion.php";

if (empty($_SESSION['FORZAR_CAMBIO_CLAVE']) || empty($_SESSION['USUARIO_CAMBIO_CLAVE'])) {
    header("Location: index.php"); 
    exit;
}

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

function decrypt_password($encryptedData) {
  $key = hash('sha256', ENCRYPTION_KEY, true);
  $iv = FIXED_IV;
  $encrypted = base64_decode($encryptedData);
  return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
}

// === OBTENER CONTRASEÑA ACTUAL DESDE BD ===
$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pwdAct = $_POST['clave_actual']    ?? '';
  $pwdNew = $_POST['nueva_clave']     ?? '';
  $pwdCnf = $_POST['confirmar_clave'] ?? '';
  $iCodTrabajador = $_SESSION['USUARIO_CAMBIO_CLAVE'];

  // Obtener clave actual y método de validación
  $st = sqlsrv_query($cnx,
      "SELECT cPassword, nEstadoClave, cUsuario FROM Tra_M_Trabajadores WHERE iCodTrabajador = ?",
      [$iCodTrabajador]
  );
  $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);

  $claveBD = $row['cPassword'] ?? '';
  $estadoClave = $row['nEstadoClave'] ?? 0;
  $usuario = $row['cUsuario'] ?? '';

  
      // Método nuevo: AES
      $claveDesencriptada = decrypt_password($claveBD);
      $coincideClaveActual = ($pwdAct === $claveDesencriptada);
   

  // Reglas
      function esSecuencia($txt) {
      $txt = strtolower($txt);
      $abc = 'abcdefghijklmnopqrstuvwxyz';
      $num = '0123456789';
      for ($i = 0, $n = strlen($txt) - 4; $i < $n; $i++) {
          $seg = substr($txt, $i, 5);
          if (strpos($abc, $seg) !== false || strpos(strrev($abc), $seg) !== false ||
              strpos($num, $seg) !== false || strpos(strrev($num), $seg) !== false) {
              return true;
          }
      }
      return false;
  }

    switch (true) {
      case (!$coincideClaveActual):
            $alert = 'La contraseña actual no coincide.'; break;
        case ($pwdNew !== $pwdCnf):
            $alert = 'La confirmación no coincide.'; break;
            case ($pwdNew === $pwdAct):
            $alert = 'La nueva contraseña no puede ser igual a la anterior.'; break;
        case (strlen($pwdNew) < 6 || strlen($pwdNew) > 20):
            $alert = 'Debe tener entre 6 y 20 caracteres.'; break;
        case (!preg_match('/[A-Z]/', $pwdNew) ||
              !preg_match('/[0-9]/', $pwdNew) ||
              !preg_match('/[^A-Za-z0-9]/', $pwdNew)):
            $alert = 'Debe incluir al menos una mayúscula, un número y un símbolo.'; break;
        case (esSecuencia($pwdNew)):
            $alert = 'No se permiten secuencias alfanuméricas consecutivas.'; break;
        default:
        $pwdEnc = encrypt_password($pwdNew);
            $upd = "UPDATE Tra_M_Trabajadores
                    SET cPassword = ?, nEstadoClave = 1, fUltimoCambioClave = GETDATE()
                    WHERE iCodTrabajador = ?";
           $ok = sqlsrv_query($cnx, $upd, [$pwdEnc, $iCodTrabajador]);

            if (!$ok) {
                error_log(print_r(sqlsrv_errors(), true));
                $alert = 'Error al guardar la nueva contraseña.'; break;
            }

            unset($_SESSION['FORZAR_CAMBIO_CLAVE'], $_SESSION['USUARIO_CAMBIO_CLAVE']);
            session_unset();        // Elimina todas las variables de sesión
            session_destroy();      // Cierra completamente la sesión
            header("Location: index.php?alter=6");
            exit;
    }
}
?>
<!-- A partir de aquí el HTML permanece igual -->
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cambiar contraseña</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* estilos visuales (idénticos al original) */
body{margin:0;font-family:'Roboto',Arial,sans-serif;background:#f5f5f5;
     display:flex;align-items:center;justify-content:center;height:100vh;overflow:hidden}
.container{width:90%;max-width:1200px;display:flex;border-radius:12px;overflow:hidden;
          box-shadow:0 8px 20px rgba(0,0,0,.2)}
.left-section{width:50%;background:#f5f5f5;display:flex;flex-direction:column;align-items:center;
              justify-content:center;padding:40px;gap:30px;z-index:2}
              .right-section {
  width: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  background-size: cover;
  background-position: center;
}

.info-box {
  background-color: #ffffffee;
  padding: 20px;
  border-radius: 12px;
  max-width: 400px;
  width: 90%;
  text-align: left;
  color: #333;
  font-size: 15px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.info-box strong {
  font-size: 16px;
  color: #005a86;
}

.info-box ul {
  margin: 12px 0 0 18px;
  padding: 0;
}

.info-box li {
  margin-bottom: 6px;
}

.info-box .note {
  margin-top: 12px;
  background: #ffebee;
  border: 1px solid #d32f2f;
  border-radius: 6px;
  padding: 10px;
  color: #b71c1c;
  font-size: 13px;
}
.left-section img{max-width:70%}
h1{font-size:24px;color:#333;margin:0}
.form-box{width:100%;max-width:350px;display:flex;flex-direction:column;gap:30px}
.input-container{position:relative;width:100%}
.input-container input{font-size:16px;width:100%;border:none;border-bottom:2px solid #ccc;padding:5px 0;
                       background:transparent;outline:none}
.input-container .label{position:absolute;top:0;left:0;color:#ccc;transition:.3s;pointer-events:none}
.input-container input:focus~.label,
.input-container input:valid~.label{top:-20px;font-size:16px;color:#333}
.input-container .underline{position:absolute;bottom:0;left:0;height:2px;width:100%;background:#333;
                            transform:scaleX(0);transition:.3s}
.input-container input:focus~.underline{transform:scaleX(1)}
.toggle-password{position:absolute;right:0;top:50%;transform:translateY(-50%);cursor:pointer;
                 color:#888;font-size:22px}
button{padding:10px;background:#005a86;color:#fff;border:none;border-radius:4px;font-size:16px;cursor:pointer}
button:hover{background:#004568}
.alert{margin-top:15px;background:#d32f2f22;border:1px solid #d32f2f;color:#d32f2f;
       padding:10px;border-radius:4px;text-align:center;font-weight:bold}
.info-box{border:1px solid #ccc;border-radius:6px;background:#fff;padding:20px;font-size:14px;line-height:1.4;color:#333}
.info-box strong{font-size:15px;color:#005a86}
.info-box ul{margin:8px 0 0 15px;padding:0}
.info-box li{margin-bottom:5px}
.note{margin-top:10px;background:#ffebee;border:1px solid #d32f2f;border-radius:6px;padding:15px;color:#b71c1c;font-size:13px}
.shape{position:absolute;border-radius:50%;z-index:0}
.shape-top-left{width:400px;height:400px;background:#5797ad;top:-200px;left:-200px}
.shape-bottom-right{width:300px;height:300px;background:#005a86;bottom:-150px;right:-150px}
</style>
</head>
<body>
<div class="shape shape-top-left"></div>
<div class="shape shape-bottom-right"></div>
<div class="container">
  <div class="left-section">
    <img src="./img/logo.png" alt="Logo">
    <h1>Cambiar contraseña</h1>
    <form method="post" class="form-box" novalidate>
      <div class="input-container">
        <input type="password" id="clave_actual" name="clave_actual" required>
        <label class="label" for="clave_actual">Contraseña actual</label>
        <span class="material-icons toggle-password" data-target="clave_actual">visibility_off</span>
        <div class="underline"></div>
      </div>
      <div class="input-container">
        <input type="password" id="nueva_clave" name="nueva_clave" required>
        <label class="label" for="nueva_clave">Nueva contraseña</label>
        <span class="material-icons toggle-password" data-target="nueva_clave">visibility_off</span>
        <div class="underline"></div>
      </div>
      <div class="input-container">
        <input type="password" id="confirmar_clave" name="confirmar_clave" required>
        <label class="label" for="confirmar_clave">Confirmar contraseña</label>
        <span class="material-icons toggle-password" data-target="confirmar_clave">visibility_off</span>
        <div class="underline"></div>
      </div>
      <button type="submit">Guardar nueva contraseña</button>
      <?php if ($alert): ?>
        <div class="alert"><?= htmlspecialchars($alert) ?></div>
      <?php endif; ?>
    </form>
    
  </div>
  <div class="right-section">
  <div class="info-box">
      <strong>Sugerencias y condiciones:</strong>
      <ul>
        <li>Entre 6 y 20 caracteres.</li>
        <li>Al menos 1 mayúscula, 1 número y 1 símbolo.</li>
        <li>Sin secuencias numéricas (12345) ni alfabéticas (abcde).</li>
        <li>Diferente a la contraseña anterior.</li>
      </ul>
      <div class="note">El uso de sus claves es personal e intransferible.</div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.toggle-password').forEach(icon=>{
  icon.addEventListener('click',()=>{
    const inp=document.getElementById(icon.dataset.target);
    inp.type = inp.type==='password' ? 'text' : 'password';
    icon.textContent = inp.type==='password' ? 'visibility_off' : 'visibility';
  });
});
function esSeq(txt){
  txt=txt.toLowerCase();
  const abc='abcdefghijklmnopqrstuvwxyz', num='0123456789';
  for(let i=0;i<=txt.length-5;i++){
    const seg=txt.substr(i,5);
    if(abc.includes(seg)||abc.split('').reverse().join('').includes(seg)||
       num.includes(seg)||num.split('').reverse().join('').includes(seg)) return true;
  }
  return false;
}
document.querySelector('form').addEventListener('submit',e=>{
  const prev=document.getElementById('clave_actual').value;
  const pwd=document.getElementById('nueva_clave').value;
  const cnf=document.getElementById('confirmar_clave').value;

  if(pwd!==cnf) {
    e.preventDefault();
    Swal.fire('Error', 'La confirmación no coincide.', 'error');
    return;
    }
  if(pwd===prev){
    e.preventDefault();
    Swal.fire('Advertencia', 'La nueva contraseña no puede ser igual a la anterior.', 'warning');
    return;
    }
  if(pwd.length<6||pwd.length>20){
    e.preventDefault();
    Swal.fire('Longitud inválida', 'Debe tener entre 6 y 20 caracteres.', 'warning');
    return;
  }
  if(!/[A-Z]/.test(pwd)||!/[0-9]/.test(pwd)||!/[^A-Za-z0-9]/.test(pwd)){
    e.preventDefault();
    Swal.fire('Formato incorrecto', 'Debe incluir al menos una mayúscula, un número y un símbolo.', 'warning');
     return;
    }
  if(esSeq(pwd)) {
  e.preventDefault();
  Swal.fire('Secuencia no permitida', 'No se permiten secuencias alfanuméricas consecutivas.', 'warning');
  }
});
</script>
</body>
</html>
