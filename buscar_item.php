<?php
$sigaConn = sqlsrv_connect("192.168.32.135", array(
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
));

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

$data = [];

if ($q !== '' && $sigaConn) {
    $sql = "SELECT TOP 5 CODIGO_ITEM, NOMBRE_ITEM, PRECIO_COMPRA, TIPO_BIEN
            FROM CATALOGO_BIEN_SERV
            WHERE CODIGO_ITEM = ?";
    $params = [$q];
    $stmt = sqlsrv_query($sigaConn, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
