<?php
session_start();
include_once("conexion/conexion.php");
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

// Validar entrada
$iCodMovimiento = isset($_POST['iCodMovimiento']) ? intval($_POST['iCodMovimiento']) : 0;

if ($iCodMovimiento <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Movimiento inválido']);
    exit;
}

// Actualizar la tabla
$sql = "UPDATE Tra_M_Tramite_Movimientos 
        SET fFecRecepcion = GETDATE(), nEstadoMovimiento = 1 
        WHERE iCodMovimiento = ?";
$params = [$iCodMovimiento];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt) {
    echo json_encode(['status' => 'ok']);
} else {
    $error = sqlsrv_errors();
    $mensaje = $error[0]['message'] ?? 'Error desconocido';
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
}
