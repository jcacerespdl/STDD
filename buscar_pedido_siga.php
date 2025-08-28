<?php
$sigaConn = sqlsrv_connect("192.168.32.135", [
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
]);

header('Content-Type: application/json');

$nroPedido = trim($_GET['nro_pedido'] ?? '');
$tipoBien = trim($_GET['tipo_bien'] ?? '');

$response = ["status" => "error", "datos" => []];

if ($nroPedido && $tipoBien && $sigaConn) {
    $sql = "
        SELECT 
            D.NRO_PEDIDO,
            (D.GRUPO_BIEN + D.CLASE_BIEN + D.FAMILIA_BIEN + D.ITEM_BIEN) AS CODIGO_ITEM,
            C.NOMBRE_ITEM,
            D.CANT_SOLICITADA
        FROM SIG_DETALLE_PEDIDOS D
        INNER JOIN CATALOGO_BIEN_SERV C 
            ON (D.GRUPO_BIEN + D.CLASE_BIEN + D.FAMILIA_BIEN + D.ITEM_BIEN) = C.CODIGO_ITEM
        WHERE D.ANO_EJE = '2025'
            AND D.TIPO_PEDIDO = '2'
            AND D.NRO_PEDIDO = ?
            AND D.TIPO_BIEN = ?
            AND C.ESTADO_MEF = 'A'
    ";

    $stmt = sqlsrv_query($sigaConn, $sql, [$nroPedido, $tipoBien]);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $response['datos'][] = $row;
        }
        if (!empty($response['datos'])) {
            $response['status'] = 'success';
        }
    } else {
        error_log("❌ Error SQL: " . print_r(sqlsrv_errors(), true));
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
