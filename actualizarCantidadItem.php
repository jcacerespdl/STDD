<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$codigoItem = $_POST['codigoItem'] ?? null;
$nuevaCantidad = $_POST['nuevaCantidad'] ?? null;

if (!$codigoItem || !$nuevaCantidad) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

$sql = "UPDATE Tra_M_Tramite_SIGA_Pedido SET cantidad = ? WHERE codigo_item = ?";
$params = [$nuevaCantidad, $codigoItem];

$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar SQL',
        'sql_errors' => $errors
    ]);
    exit;
}

echo json_encode(['status' => 'ok']);
