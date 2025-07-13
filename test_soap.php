<?php
try {
    $wsdl = 'http://wsvmin.minsa.gob.pe/wsreniecmq/serviciomq.asmx?WSDL';
    $client = new SoapClient($wsdl, array(
        'connection_timeout' => 10,
        'trace' => 1,
        'exceptions' => true
    ));
    echo "✅ Conexión SOAP exitosa";
} catch (Exception $e) {
    echo "❌ Error de conexión SOAP:<br>";
    echo nl2br(htmlentities($e->getMessage()));
}
