<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
  echo json_encode(['ok'=>false,'msg'=>'Sesión expirada']); exit;
}

require_once 'conexion/conexion.php';
global $cnx;

$iCodFirma    = isset($_POST['iCodFirma']) ? (int)$_POST['iCodFirma'] : 0;
$observacion  = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';

if ($iCodFirma <= 0) { echo json_encode(['ok'=>false,'msg'=>'Parámetro inválido']); exit; }

$sql = "UPDATE Tra_M_Tramite_Firma SET observaciones = ? WHERE iCodFirma = ?";
$stmt = sqlsrv_query($cnx, $sql, [$observacion, $iCodFirma]);

if ($stmt === false) {
  $err = sqlsrv_errors();
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar']); exit;
}

echo json_encode(['ok'=>true]);
