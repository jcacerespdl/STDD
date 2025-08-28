<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite   = $_POST['iCodTramite']   ?? 0;
$pedidoSiga    = $_POST['pedidoSiga']    ?? null;
$codigoItem    = $_POST['codigoItem']    ?? null;
$cantidad      = $_POST['cantidad']      ?? null;

if (!$iCodTramite || !$pedidoSiga || !$codigoItem || !$cantidad) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

// Verificar si ya existe
$sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_SIGA_Pedido 
             WHERE iCodTramite = ? AND pedido_siga = ? AND codigo_item = ?";
$paramsCheck = [$iCodTramite, $pedidoSiga, $codigoItem];
$stmtCheck = sqlsrv_query($cnx, $sqlCheck, $paramsCheck);

if ($stmtCheck === false) {
    echo json_encode(['status' => 'error', 'message' => 'Error al verificar existencia']);
    exit;
}

$row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
$existe = $row['total'] > 0;

if ($existe) {
    // Ya existe, actualizar cantidad
    $sqlUpdate = "UPDATE Tra_M_Tramite_SIGA_Pedido 
                  SET cantidad = ? 
                  WHERE iCodTramite = ? AND pedido_siga = ? AND codigo_item = ?";
    $paramsUpdate = [$cantidad, $iCodTramite, $pedidoSiga, $codigoItem];
    $stmtUpdate = sqlsrv_query($cnx, $sqlUpdate, $paramsUpdate);

    if ($stmtUpdate === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar ítem']);
        exit;
    }

    echo json_encode(['status' => 'updated', 'message' => 'Ítem actualizado']);
} else {
    // Insertar nuevo
    $sqlInsert = "INSERT INTO Tra_M_Tramite_SIGA_Pedido 
                  (iCodTramite, pedido_siga, extension, codigo_item, cantidad)
                  VALUES (?, ?, 1, ?, ?)";
    $paramsInsert = [$iCodTramite, $pedidoSiga, $codigoItem, $cantidad];
    $stmtInsert = sqlsrv_query($cnx, $sqlInsert, $paramsInsert);

    if ($stmtInsert === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar ítem SIGA']);
        exit;
    }

    echo json_encode(['status' => 'inserted', 'message' => 'Ítem agregado']);
}
