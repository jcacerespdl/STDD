<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$registro = $_GET['registro'] ?? '';
$clave = $_GET['clave'] ?? '';

if (!$registro || !$clave) {
    echo json_encode(["valido" => false]);
    exit;
}

$sql = "SELECT COUNT(*) AS total FROM Tra_M_Tramite WHERE expediente = ? AND cPassword = ?";
$stmt = sqlsrv_query($cnx, $sql, [$registro, $clave]);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ((int)$row['total'] > 0) {
        echo json_encode(["valido" => true]);
        exit;
    }
}

echo json_encode(["valido" => false]);
exit;
