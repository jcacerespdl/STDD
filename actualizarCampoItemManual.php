<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$codigoItem = $_POST['codigoItem'] ?? null;
$campo = $_POST['campo'] ?? null;
$valor = $_POST['valor'] ?? null;

$permitidos = ['cantidad', 'stock', 'consumo_promedio', 'meses_consumo', 'situacion'];
if (!in_array($campo, $permitidos)) {
  echo json_encode(['status' => 'error', 'message' => 'Campo no permitido']);
  exit;
}

$sql = "UPDATE Tra_M_Tramite_SIGA_Pedido SET $campo = ? 
        WHERE iCodTramite = ? AND codigo_item = ? AND pedido_siga IS NULL";

$params = [$valor, $iCodTramite, $codigoItem];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt === false) {
  echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
  exit;
}

echo json_encode(['status' => 'ok']);
