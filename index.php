<?php
 
session_start();
if (isset($_SESSION['CODIGO_TRABAJADOR'])) {
  header("Location: main.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
  <meta name="robots" content="noindex">
  <title>Sistema de Trámite Documentario</title>
  <link rel="shortcut icon" href="cInterfaseUsuario_SITD/images/favicon.ico" type="image/x-icon">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body {
      margin: 0;
      font-family: 'Roboto', Arial, sans-serif;
      background-color: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      overflow: hidden;
    }
    .container {
      width: 90%;
      max-width: 1200px;
      display: flex;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      position: relative;
      transition: all 1s ease;
    }
    .left-section {
      width: 50%;
      background-color: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
      z-index: 2;
      transition: all 1s ease;
    }
    .left-section img {
      max-width: 70%;
      margin-bottom: 30px;
    }
    .login-box {
      width: 100%;
      max-width: 350px;
    }
    .login-box h1 {
      font-size: 24px;
      padding-bottom: 20px;
      color: #333;
    }
    .login-box button {
      width: 100%;
      padding: 10px;
      background-color: #005a86;
      color: white;
      border: none;
      border-radius: 4px;
      font-size: 16px;
      cursor: pointer;
    }
    .login-box button:hover {
      background-color: #004568;
    }
    .alert {
      margin-top: 15px;
    }
    .alert-info {
      color: #005a86;
      font-weight: bold;
      text-align: center;
      margin-top: 15px;
      background-color: #005a8622;
      padding: 10px;
      border-radius: 4px;
      border: 1px solid #005a86;
    }
    .alert-danger {
      color: #d32f2f;
      font-weight: bold;
      text-align: center;
      margin-top: 15px;
      background-color: #d32f2f22;
      padding: 10px;
      border-radius: 4px;
      border: 1px solid #d32f2f;
    }

    .right-section {
      width: 50%;
      background:  url('./img/fotohospital.jpg');
      background-size: cover;
      background-position: center;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      transition: all 1s ease;
    }
    .shape {
      position: absolute;
      border-radius: 50%;
      z-index: 0;
    }
    .shape.shape-top-left {
      width: 400px;
      height: 400px;
      background-color: #5797ad;
      top: -200px;
      left: -200px;
      z-index: -1;
    }
    .shape.shape-bottom-right {
      width: 300px;
      height: 300px;
      background-color: #005a86;
      bottom: -150px;
      right: -150px;
    }

    .input-container {
      position: relative;
      width: 100%;
    }

    .input-container input {
      font-size: 16px;
      width: 100%;
      border: none;
      border-bottom: 2px solid #ccc;
      padding: 5px 0;
      background-color: transparent;
      outline: none;
    }

     .input-container .label {
      position: absolute;
      top: 0;
      left: 0;
      color: #ccc;
      transition: all 0.3s ease;
      pointer-events: none;
    }

    .input-container input:focus ~ .label,
    .input-container input:valid ~ .label {
      top: -20px;
      font-size: 16px;
      color: #333;
    }

    .input-container .underline {
      position: absolute;
      bottom: 0;
      left: 0;
      height: 2px;
      width: 100%;
      background-color: #333;
      transform: scaleX(0);
      transition: all 0.3s ease;
    }

    .input-container input:focus ~ .underline,
    .input-container input:valid ~ .underline {
      transform: scaleX(1);
    }

    .alert-success {
      background: #dff0d8;
      color: #3c763d;
      border: 1px solid #d6e9c6;
      padding: 10px;
      border-radius: 4px;
      margin-top: 15px;
      font-weight: bold;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="shape shape-top-left"></div>
  <div class="shape shape-bottom-right"></div>
  <div class="container">
    <div class="left-section">
      <img src="./img/logo.jpeg" alt="Logo Institucional" style="margin: 0px !important">
      <div class="login-box">
        <h1>Sistema de Trámite Documentario Digital</h1>
        <form method="post" action="login.php" style="display: flex; flex-direction: column; gap: 30px; width: 100%">
          <div class="input-container">
            <input id="input-usuario" type="text" name="usuario" required="" />
            <label class="label" for="input-usuario">Usuario</label>
            <div class="underline"></div>
          </div>
          <div class="input-container" style="position:relative;">
          <input id="input-contraseña" type="password" name="contrasena" required  />
            <label class="label" for="input-contraseña">Contraseña</label>
            <span id="togglePassword" class="material-icons toggle-password"
                  style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); cursor: pointer;">visibility_off</span>
            <div class="underline"></div> 
          </div>
          <button type="submit">Ingresar</button>
        </form>
        <?php
        if (isset($_GET['alter'])) {
          switch ($_GET['alter']) {
            case 2:
              echo "<div class='alert alert-info'>Ud. ha salido correctamente del sistema.</div>";
              break;
            case 3:
              echo "<div class='alert alert-danger'>Error: Datos vacíos. Ingrese correctamente.</div>";
              break;
            case 4:
              echo "<div class='alert alert-danger'>Error: Clave incorrecta o cuenta incorrecta.</div>";
              break;
            case 5:
              echo "<div class='alert alert-danger'>Error: Usuario no autorizado.</div>";
              break;
            case 6:
              echo "<div class='alert alert-success'> Clave cambiada con éxito. Ingrese con su nueva contraseña.</div>";
              break;
            case 7:
              echo "<div class='alert alert-info'>Su sesión ha expirado. Por favor inicie sesión nuevamente.</div>";
              break;
          }
        }
        ?>
      </div>
    </div>
    <div class="right-section">
      <a href="https://tramite.heves.gob.pe/STDL/manual-std.pdf" target="_blank" rel="noopener noreferrer" style="position: absolute; bottom: 10px; right: 10px; color: #fff; text-decoration: none; font-size: 12px; display: flex; align-items: center; gap: 5px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14"><path fill="currentColor" fill-rule="evenodd" d="M12 10.75h-.25v1.75H12a.75.75 0 0 1 0 1.5H3.625a2.375 2.375 0 0 1-2.375-2.375V2.25A2.25 2.25 0 0 1 3.5 0h7a2.25 2.25 0 0 1 2.25 2.25V10a.75.75 0 0 1-.75.75m-8.375 0h6.625v1.75H3.625a.875.875 0 0 1 0-1.75m3.546-7.921a.875.875 0 0 0-1.046.858a.625.625 0 1 1-1.25 0a2.125 2.125 0 1 1 2.75 2.031a.625.625 0 0 1-1.25-.031v-.5c0-.345.28-.625.625-.625a.875.875 0 0 0 .17-1.733ZM7 8.884a.875.875 0 1 1 0-1.75a.875.875 0 0 1 0 1.75" clip-rule="evenodd"/></svg>
      <span>Manual de Usuario</span>
      </a>
    </div>
  </div>

  <script>
/* Permite mostrar/ocultar contraseña en el input */
document.getElementById('togglePassword')
  .addEventListener('click', function () {
    const input = document.getElementById('input-contraseña');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    this.textContent = isHidden ? 'visibility' : 'visibility_off';
  });
</script>
</body>
</html>
