<?php
include("conexion/conexion.php");

$iCodTramite = $_POST['iCodTramite'] ?? 0;

if (!$iCodTramite) {
    echo json_encode(['status' => 'error', 'message' => 'Falta iCodTramite']);
    exit;
}

$sql = "DELETE FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite = ?";
$params = [$iCodTramite];
sqlsrv_query($cnx, $sql, $params);

echo json_encode(['status' => 'success']);
?>