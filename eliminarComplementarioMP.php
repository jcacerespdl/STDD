<?php
include 'conexion/conexion.php';
header('Content-Type: application/json');

$iCodDigital = isset($_POST['iCodDigital']) ? intval($_POST['iCodDigital']) : 0;
if ($iCodDigital <= 0) { echo json_encode(['status'=>'error','message'=>'ID inválido']); exit; }

$sql = "DELETE FROM Tra_M_Tramite_Digitales WHERE iCodDigital = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodDigital]);
if ($stmt) echo json_encode(['status'=>'ok']);
else echo json_encode(['status'=>'error','message'=>'Error al eliminar']);
