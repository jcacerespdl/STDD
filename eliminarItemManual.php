<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$codigoItem = $_POST['codigoItem'] ?? null;

if (!$iCodTramite || !$codigoItem) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

$sqlDelete = "DELETE FROM Tra_M_Tramite_SIGA_Pedido 
              WHERE iCodTramite = ? AND codigo_item = ?  ";

$stmtDelete = sqlsrv_query($cnx, $sqlDelete, [$iCodTramite, $codigoItem]);

if ($stmtDelete === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error al eliminar']);
    exit;
}

echo json_encode(['status' => 'deleted']);
