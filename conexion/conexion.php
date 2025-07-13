<?
$cnx = sqlsrv_connect("TRAMITE-DIGI",array( "Database"=>"BD_QA", "UID"=>"sa", "PWD"=>"sa", "CharacterSet"=>"UTF-8"));

if ($cnx === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Conexión al SIGA
$sigaConn = sqlsrv_connect("192.168.32.135", array(
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
));

if (!$sigaConn) {
    die("Error de conexión a SIGA: " . print_r(sqlsrv_errors(), true));
}
?>