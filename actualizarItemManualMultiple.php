<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite   = $_POST['iCodTramite'] ?? 0;
$codigoItem    = $_POST['codigoItem'] ?? '';
$cantidad      = $_POST['cantidad'] ?? 0;
$stock         = $_POST['stock'] ?? 0;
$consumo       = $_POST['consumo'] ?? 0;
$meses         = $_POST['meses'] ?? 0;
$situacion     = $_POST['situacion'] ?? '';

if (!$iCodTramite || !$codigoItem) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$sql = "UPDATE Tra_M_Tramite_SIGA_Pedido
        SET cantidad = ?, stock = ?, 
            consumo_promedio = CAST(? AS DECIMAL(10,2)), 
            meses_consumo = CAST(? AS DECIMAL(10,2)), 
            situacion = ?
        WHERE iCodTramite = ? AND codigo_item = ? AND pedido_siga IS NULL";

// ✅ Pasar float puros para consumo y meses, NO string, NO number_format
$params = [
    (int)$cantidad,
    (int)$stock,
    (float)$consumo,
    (float)$meses,
    $situacion,
    (int)$iCodTramite,
    $codigoItem
];

error_log("🛰️ Enviando SQL PARAMS: " . json_encode($params));

$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error SQL: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

echo json_encode(['status' => 'ok']);
