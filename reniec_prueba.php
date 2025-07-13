<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    if (!preg_match('/^\d{8}$/', $dni)) {
        echo "DNI no válido.";
        exit;
    }

    $wsdl = "http://wsvmin.minsa.gob.pe/wsreniecmq/serviciomq.asmx";
    $usuario = "46309557";
    $clave = "w1*2DbQY172#M";
    $app = "heves";

    $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">
   <soapenv:Header>
      <tem:Credencialmq>
         <tem:app>{$app}</tem:app>
         <tem:usuario>{$usuario}</tem:usuario>
         <tem:clave>{$clave}</tem:clave>
      </tem:Credencialmq>
   </soapenv:Header>
   <soapenv:Body>
      <tem:obtenerDatosCompletos>
         <tem:nrodoc>{$dni}</tem:nrodoc>
      </tem:obtenerDatosCompletos>
   </soapenv:Body>
</soapenv:Envelope>
XML;

    $headers = [
        "Content-Type: text/xml; charset=utf-8",
        "SOAPAction: \"http://tempuri.org/obtenerDatosCompletos\"",
        "Content-Length: " . strlen($xml)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $wsdl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ Error cURL: " . curl_error($ch);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    // Extraer <string>
    preg_match_all('/<string[^>]*>(.*?)<\/string>/', $response, $matches);
    $datos = $matches[1];

    if (count($datos) >= 12) {
        echo "<h2>✅ Resultado de consulta:</h2>";
        echo "<ul>";
        echo "<li><strong>DNI:</strong> {$datos[1]}</li>";
        echo "<li><strong>Apellido Paterno:</strong> {$datos[3]}</li>";
        echo "<li><strong>Apellido Materno:</strong> {$datos[4]}</li>";
        echo "<li><strong>Nombres:</strong> {$datos[6]}</li>";
        echo "<li><strong>Departamento (Ubigeo):</strong> {$datos[9]}</li>";
        echo "<li><strong>Provincia (Ubigeo):</strong> {$datos[10]}</li>";
        echo "<li><strong>Distrito (Ubigeo):</strong> {$datos[11]}</li>";
        echo "</ul>";
    } else {
        echo "❌ Respuesta incompleta o inválida del servicio RENIEC.";
    }
}
?>

<!-- Formulario simple -->
<form method="post">
    <label for="dni">Ingrese DNI:</label>
    <input type="text" name="dni" id="dni" maxlength="8" required>
    <button type="submit">Consultar RENIEC</button>
</form>
