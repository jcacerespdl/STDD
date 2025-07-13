<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodMovimiento = intval($_GET['iCodMovimiento'] ?? 0);
if (!$iCodMovimiento) {
    echo json_encode(['status' => 'error', 'message' => 'Movimiento no válido']);
    exit;
}

$sql = "SELECT cDocumentoFinalizacion, cObservacionesFinalizar
        FROM Tra_M_Tramite_Movimientos
        WHERE iCodMovimiento = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodMovimiento]);
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode([
        'status' => 'ok',
        'nombre' => $row['cDocumentoFinalizacion'] ?? null,
        'observaciones' => $row['cObservacionesFinalizar'] ?? ''
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
}
