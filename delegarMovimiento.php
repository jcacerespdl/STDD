<?php
include("conexion/conexion.php");
session_start();

$iCodMovimiento = $_POST['iCodMovimiento'];
$iCodTramite = $_POST['iCodTramite'];
$fechaActual = date("Y-m-d H:i:s");

if (isset($_POST['autoAtiende'])) {
        // Caso profesional (auto atención)
    $iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
    $cObs = "Atención directa por el profesional";
    $sql = "UPDATE Tra_M_Tramite_Movimientos 
            SET iCodTrabajadorDelegado = ?, cObservacionesDelegado = ?, fFecDelegado = ?
            WHERE iCodMovimiento = ?";
    $params = [$iCodTrabajador, $cObs, $fechaActual, $iCodMovimiento];
} else {
     // Caso jefe/asistente (delegación)
     $iCodTrabajadorDelegado = $_POST['iCodTrabajadorDelegado'] ?? null;
     $iCodIndicacionDelegado = $_POST['iCodIndicacionDelegado'] ?? null;
     $cObservaciones = $_POST['cObservacionesDelegado'] ?? '';
 
     if (!$iCodTrabajadorDelegado || !$iCodIndicacionDelegado) {
         echo json_encode(["status" => "error", "message" => "Faltan datos para delegar."]);
         exit;
     }
 
    $sql = "UPDATE Tra_M_Tramite_Movimientos 
    SET iCodTrabajadorDelegado = ?, 
        iCodIndicacionDelegado = ?, 
        cObservacionesDelegado = ?, 
        fFecDelegado = ?,
        nEstadoMovimiento = 3
          WHERE iCodMovimiento = ?";
    $params = [$iCodTrabajadorDelegado, $iCodIndicacionDelegado, $cObservaciones, $fechaActual, $iCodMovimiento];
}

$stmt = sqlsrv_query($cnx, $sql, $params);
if ($stmt) {
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "message" => print_r(sqlsrv_errors(), true)]);
}
