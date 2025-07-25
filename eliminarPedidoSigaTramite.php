<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$pedidoSiga = $_POST['pedidoSiga'] ?? '';

if (!$iCodTramite || !$pedidoSiga) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

$sql = "DELETE FROM Tra_M_Tramite_SIGA_Pedido 
        WHERE iCodTramite = ? AND pedido_siga = ?";

$params = [$iCodTramite, $pedidoSiga];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
    exit;
}

echo json_encode(['status' => 'deleted']);
