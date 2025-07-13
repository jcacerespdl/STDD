<?php
$wsdl = 'http://wsvmin.minsa.gob.pe/wsreniecmq/serviciomq.asmx?WSDL';

$options = [
    "trace" => 1,
    "exceptions" => true,
    "connection_timeout" => 10
];

try {
    $soap = new SoapClient($wsdl, $options);

    // Probar sin headers personalizados
    $params = ["nrodoc" => "70364718"];
    $response = $soap->obtenerDatosCompletos($params);

    echo "<pre>";
    print_r($response);
    echo "</pre>";
} catch (Exception $e) {
    echo "❌ Error al invocar método: " . htmlspecialchars($e->getMessage());
}
