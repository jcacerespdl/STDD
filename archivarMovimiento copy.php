<?php
session_start();
include_once("conexion/conexion.php");
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

$iCodMovimiento = isset($_POST['iCodMovimiento']) ? intval($_POST['iCodMovimiento']) : 0;
$cObservaciones = trim($_POST['observaciones'] ?? '');
$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$fFecFinalizacion = date("Y-m-d H:i:s");

if (!$iCodMovimiento || !$iCodTrabajador) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$sql = "UPDATE Tra_M_Tramite_Movimientos
        SET nEstadoMovimiento = 5,
            iCodTrabajadorFinalizar = ?,
            cObservacionesFinalizar = ?,
            fFecFinalizar = ?
        WHERE iCodMovimiento = ?";

$params = [$iCodTrabajador, $cObservaciones, $fFecFinalizacion, $iCodMovimiento];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt) {
    echo json_encode(['status' => 'ok']);
} else {
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => $error[0]['message'] ?? 'Error desconocido']);
}
