<?php
include("conexion/conexion.php");

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$modo = $_POST['modo'] ?? ''; // "con" o "sin"

if (!$iCodTramite || !in_array($modo, ['con', 'sin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

if ($modo === 'con') {
    $sql = "DELETE FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite = ? AND pedido_siga IS NOT NULL";
} else {
    $sql = "DELETE FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite = ? AND pedido_siga IS NULL";
}

$params = [$iCodTramite];
sqlsrv_query($cnx, $sql, $params);

echo json_encode(['status' => 'success']);
?>