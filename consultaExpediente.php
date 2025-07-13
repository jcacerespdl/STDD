<?php
include 'header_mesadepartes.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Seguimiento de Trámites</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      margin: 0;
      background-color: #f8f8f8;
    }

    .contenedor {
      max-width: 1000px;
      margin: 80px auto;
      padding: 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }

    h1 {
      text-align: center;
      color: #1b53b2;
      font-size: 28px;
      margin-bottom: 30px;
    }

    .contenido {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 40px;
    }

    .instrucciones {
      flex: 1 1 50%;
      font-size: 16px;
      color: #222;
    }

    .instrucciones strong {
      color: #111;
    }

    .instrucciones .info {
      margin: 12px 0;
      line-height: 1.6;
    }

    .instrucciones .icono-texto {
      display: flex;
      align-items: center;
      margin-top: 15px;
    }

    .material-icons {
      font-size: 28px;
      margin-right: 10px;
      color: #00acc1;
    }

    .formulario {
      flex: 1 1 40%;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .formulario input {
      padding: 10px 14px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #ccc;
      outline: none;
    }

    .formulario button {
      background-color: #364897;
      color: white;
      font-weight: bold;
      border: none;
      padding: 12px;
      font-size: 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .formulario button:hover {
      background-color: #2a3970;
    }

    .formulario label {
      font-weight: 600;
      margin-bottom: 5px;
    }

    @media screen and (max-width: 768px) {
      .contenido {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

<div class="contenedor">
  <h1>Seguimiento de Trámites</h1>
  <div class="contenido">
    <div class="instrucciones">
      <p><strong>Señor Ciudadano (a):</strong></p>
      <p class="info">
      ➜ Esta consulta es solo para solicitudes ingresadas en la mesa de partes
        <small>(Trámite regulado por el TUPA INSN - Resolución Directoral Nro. 937-DG-INSN-2012), 
        TUPA MINSA (Resolución Ministerial Nro. 046-2018/MINSA)</small>.
      </p>
      <p class="info">
      ➜ Ingrese su número de expediente asignado del Sistema de Trámite Documentario al momento de presentar su solicitud. 
        Ingrese la clave, y haga clic en Buscar para ver el detalle de atención de su expediente.         
      </p>
      <div class="icono-texto">
        <span class="material-icons">call</span>
        <span><strong>Teléfono:</strong> (01) 6409875 Anexo: 127</span>
      </div>
      <div class="icono-texto">
        <span class="material-icons">email</span>
        <span><strong>Email:</strong> mesadepartes@heves.gob.pe</span>
      </div>
    </div>

    <form class="formulario" id="formSeguimiento">
      <label for="registro">Número de Registro</label>
      <input type="text" name="registro" id="registro" placeholder="Ingrese su número de registro" required>

      <label for="clave">Clave</label>
      <input type="text" name="clave" id="clave" placeholder="Ingrese su clave secreta" required>

      <button type="submit">Buscar</button>
    </form>
  </div>
</div>

<script>
document.getElementById('formSeguimiento').addEventListener('submit', function(e) {
  e.preventDefault();
  const registro = document.getElementById('registro').value.trim();
  const clave = document.getElementById('clave').value.trim();

  if (!registro || !clave) {
    Swal.fire('Campos requeridos', 'Debe ingresar tanto registro como clave', 'warning');
    return;
  }

  fetch('verificar_clave_tramite.php?registro=' + encodeURIComponent(registro) + '&clave=' + encodeURIComponent(clave))
    .then(res => res.json())
    .then(data => {
      if (data.valido) {
        window.location.href = 'estadotramiteseguimiento.php?registro=' + registro + '&clave=' + clave;
      } else {
        Swal.fire('Clave incorrecta', 'La clave ingresada no corresponde al registro indicado.', 'error');
      }
    })
    .catch(err => {
      console.error(err);
      Swal.fire('Error', 'Ocurrió un problema al validar los datos.', 'error');
    });
});
</script>

</body>
</html>
