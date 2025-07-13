<?php
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'conexion/conexion.php';

$registro = $_GET['registro'] ?? 'NO DISPONIBLE';
$clave = $_GET['clave'] ?? 'NO DISPONIBLE';
$fechaActual = date('Y-m-d H:i:s');

// Convertimos el QR a base64
$qrData = base64_encode(file_get_contents('img/qr_mesadepartes.png'));
$rutaQR = 'data:image/png;base64,' . $qrData;

$html = '
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 10px;
      margin: 0;
      padding: 0;
    }
    .ticket {
      width: 400px;
      padding: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    td {
      vertical-align: top;
      padding: 5px;
    }
    .qr {
      width: 70px;
    }
    .texto {
      font-size: 10px;
    }
    .url {
      font-size: 9px;
      word-break: break-all;
    }
  </style>
</head>
<body>
  <div class="ticket">
    <table>
      <tr>
        <td class="qr">
          <img src="' . $rutaQR . '" width="70">
        </td>
        <td class="texto">
          <div><strong>Registro:</strong> ' . htmlspecialchars($registro) . '</div>
          <div><strong>Clave:</strong> ' . htmlspecialchars($clave) . '</div>
          <div class="url">https://tramite.heves.gob.pe/SGD/estadotramite.php</div>
          <div>' . $fechaActual . '</div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->setPaper([0, 0, 420, 200], 'portrait'); // Aumentamos ancho: 420px = ~148mm
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('constancia_mesa_de_partes.pdf', ['Attachment' => false]);
exit;
