<?php
include("conexion/conexion.php");
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$iCodMovimiento = isset($_POST['iCodMovimiento']) ? intval($_POST['iCodMovimiento']) : 0;
$cObservacionesEnviar = isset($_POST['cObservacionesEnviar']) ? trim($_POST['cObservacionesEnviar']) : '';

if ($iCodMovimiento === 0 || $cObservacionesEnviar === '') {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Iniciar transacción
sqlsrv_begin_transaction($cnx);

// 1. Actualizar el movimiento
$sql1 = "
    UPDATE Tra_M_Tramite_Movimientos
    SET 
        nEstadoMovimiento = 6,
        fFecEnviar = GETDATE(),
        cObservacionesEnviar = ?
    WHERE iCodMovimiento = ?
";

$params1 = [$cObservacionesEnviar, $iCodMovimiento];
$stmt1 = sqlsrv_query($cnx, $sql1, $params1);

// 2. Actualizar el trámite principal (nFlgEnvio = 0)
$sql2 = "
    UPDATE Tra_M_Tramite
    SET nFlgEnvio = 0
    WHERE iCodTramite = (
        SELECT iCodTramite
        FROM Tra_M_Tramite_Movimientos
        WHERE iCodMovimiento = ?
    )
";
$params2 = [$iCodMovimiento];
$stmt2 = sqlsrv_query($cnx, $sql2, $params2);

// Evaluar transacción
if ($stmt1 && $stmt2) {
    sqlsrv_commit($cnx);
    echo json_encode(['status' => 'ok']);
} else {
    sqlsrv_rollback($cnx);
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar: ' . $error[0]['message']]);
}
