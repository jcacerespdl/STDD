<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$codigoItem = $_POST['codigoItem'] ?? null;
$nuevaCantidad = $_POST['nuevaCantidad'] ?? null;

$stock = $_POST['stock'] ?? null;
$consumo = $_POST['consumo'] ?? null;
$meses = $_POST['meses'] ?? null;
$situacion = $_POST['situacion'] ?? null;

if (!$iCodTramite || !$codigoItem || !$nuevaCantidad) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}
/* 1) Traer EXPEDIENTE (y extension por si acaso) desde el trámite */
$sqlTr = "SELECT  EXPEDIENTE  FROM Tra_M_Tramite WHERE iCodTramite = ?";
$rsTr  = sqlsrv_query($cnx, $sqlTr, [$iCodTramite]);
if ($rsTr === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error al obtener EXPEDIENTE del trámite']);
    exit;
}
$tr = sqlsrv_fetch_array($rsTr, SQLSRV_FETCH_ASSOC);
$expedienteTram = $tr['EXPEDIENTE'] ?? null;


// Verificar si ya existe
$sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_SIGA_Pedido 
             WHERE iCodTramite = ? AND codigo_item = ? AND pedido_siga IS NULL";
$stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $codigoItem]);

if ($stmtCheck === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error al verificar existencia']);
    exit;
}

$row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
$existe = $row['total'] > 0;

if ($existe) {
    // Actualizar cantidad
    $sqlUpdate = "UPDATE Tra_M_Tramite_SIGA_Pedido 
    SET cantidad = ?, stock = ?, consumo_promedio = ?, meses_consumo = ?, situacion = ?
    WHERE iCodTramite = ? AND codigo_item = ? AND pedido_siga IS NULL";
$stmtUpdate = sqlsrv_query($cnx, $sqlUpdate, [
$nuevaCantidad, $stock, $consumo, $meses, $situacion,
$iCodTramite, $codigoItem
]);

    if ($stmtUpdate === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar ítem']);
        exit;
    }

    echo json_encode(['status' => 'updated', 'message' => 'Ítem actualizado']);
} else {
    $sqlInsert = "INSERT INTO Tra_M_Tramite_SIGA_Pedido 
    (iCodTramite, pedido_siga, extension, codigo_item, cantidad, stock, consumo_promedio, meses_consumo, situacion, EXPEDIENTE)
    VALUES (?, NULL, 1, ?, ?, ?, ?, ?, ?,?)";
$stmtInsert = sqlsrv_query($cnx, $sqlInsert, [
    $iCodTramite, $codigoItem, $nuevaCantidad,
    $stock, $consumo, $meses, $situacion, $expedienteTram
]);

    if ($stmtInsert === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar ítem manual']);
        exit;
    }

    echo json_encode(['status' => 'inserted', 'message' => 'Ítem agregado']);
}
