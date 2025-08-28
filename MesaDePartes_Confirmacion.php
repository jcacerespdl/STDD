<?php
include 'head.php';
include 'conexion/conexion.php';

$iCodTramite = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cPassword = isset($_GET['clave']) ? htmlspecialchars($_GET['clave']) : '';

$expediente = '';

if ($iCodTramite > 0) {
    $sql = "SELECT TOP 1 expediente FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ?";
    $params = [$iCodTramite];
    $stmt = sqlsrv_query($cnx, $sql, $params);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $expediente = $row['expediente'];
    } else {
        $expediente = 'NO DISPONIBLE';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmación - Mesa de Partes Digital</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      margin: 0;
      background: #f5f5f5;
    }
    header {
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 100;
    }
    .contenedor {
      margin: 0 auto;
      padding: 100px 20px 40px;
      max-width: 800px;
      background: #fff;
      text-align: center;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .logos {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 40px;
      margin-bottom: 0px;
      margin-top: -40px;
    }
    .logos img {
      max-height: 70px;
    }
    .imagen-exito {
      max-width: 300px;
      width: 100%;
      height: auto;
      margin: 0px auto 5px;
    }
    h1 {
      color: #1b53b2;
      font-size: 26px;
      margin-bottom: 20px;
      margin-bottom: 15px;
      margin-top: 0;
    }
    p {
      font-size: 17px;
      line-height: 1.6;
      color: #333;
      margin: 0 auto 20px;
      max-width: 650px;
    }
    .datos {
      margin-top: 20px;
      font-size: 18px;
      font-weight: bold;
      color: #000;
    }
    .dato {
      margin-top: 8px;
    }
    .qr {
      margin-top: 25px;
    }
    .qr img {
      width: 150px;
    }
    .acciones {
      margin-top: 35px;
    }
    .acciones button, .acciones a button {
      background-color: #364897;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      margin: 5px;
      font-size: 16px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<div class="contenedor">
  <div class="logos">
    
  </div>

 
  <h1>¡Documento Aceptado con Éxito!</h1>

  

  <div class="datos">
  <div class="dato">Registro: <?= htmlspecialchars($expediente) ?></div>
  <div class="dato">Clave: <?= $cPassword ?></div>
  </div>
  
  <div class="qr">
    <img src="img/qr_mesadepartes.png" alt="Código QR">
  </div>

  <div class="acciones">
    <!-- <button onclick="window.print()">🖨 Imprimir constancia</button> -->
    <a href="generarConstanciaPDF.php?registro=<?= urlencode($expediente) ?>&clave=<?= urlencode($cPassword) ?>" target="_blank">
      <button>📄 Descargar constancia PDF</button>
    </a>
  </div>
</div>

</body>
</html>
