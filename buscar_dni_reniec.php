<?php
header('Content-Type: application/json');
include_once("conexion/conexion.php");

$dni = $_POST['dni'] ?? null;

if (!$dni || !preg_match('/^\d{8}$/', $dni)) {
    echo json_encode([
        "codigoRespuesta" => "9998",
        "mensajeRespuesta" => "DNI no válido"
    ]);
    exit;
}

// --- Petición SOAP vía cURL ---
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
curl_close($ch);

if (!$response) {
    echo json_encode([
        "codigoRespuesta" => "7777",
        "mensajeRespuesta" => "Error en la conexión con el servicio de RENIEC"
    ]);
    exit;
}

// Extraer los <string>
preg_match_all('/<string[^>]*>(.*?)<\/string>/', $response, $matches);
$datos = $matches[1];

if (count($datos) < 12) {
    echo json_encode([
        "codigoRespuesta" => "7778",
        "mensajeRespuesta" => "Respuesta incompleta del servicio RENIEC"
    ]);
    exit;
}

// Mapeo según posiciones confirmadas
$dni               = $datos[1];
$paterno          = $datos[3];
$materno          = $datos[4];
$nombres          = $datos[6];
$codDep           = $datos[9];
$codProv          = $datos[10];
$codDist          = $datos[11];

// Buscar nombres reales desde BD
$nombreDep = $nombreProv = $nombreDist = "";

if ($codDep) {
    $stmt = sqlsrv_query($cnx, "SELECT nombre_departamento FROM Ubigeo_Departamentos WHERE id_departamento = ?", [$codDep]);
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $nombreDep = $row['nombre_departamento'];
    }
}

if ($codDep && $codProv) {
    $stmt = sqlsrv_query($cnx, "SELECT nombre_provincia FROM Ubigeo_Provincias WHERE id_departamento = ? AND id_provincia = ?", [$codDep, $codProv]);
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $nombreProv = $row['nombre_provincia'];
    }
}

if ($codDep && $codProv && $codDist) {
    $stmt = sqlsrv_query($cnx, "SELECT nombre_distrito FROM Ubigeo_Distritos WHERE id_departamento = ? AND id_provincia = ? AND id_distrito = ?", [$codDep, $codProv, $codDist]);
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $nombreDist = $row['nombre_distrito'];
    }
}

// Enviar respuesta JSON
echo json_encode([
    "codigoRespuesta" => "0000",
    "dni" => $dni,
    "paterno" => $paterno,
    "materno" => $materno,
    "nombres" => $nombres,
    "codigoUbigeoDepartamentoDomicilio" => $codDep,
    "codigoUbigeoProvinciaDomicilio" => $codProv,
    "codigoUbigeoDistritoDomicilio" => $codDist,
    "nombreDepartamento" => $nombreDep,
    "nombreProvincia" => $nombreProv,
    "nombreDistrito" => $nombreDist
]);
