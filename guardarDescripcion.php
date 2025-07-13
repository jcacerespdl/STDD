<?php
include("conexion/conexion.php");
session_start();

$iCodTramite = $_POST["iCodTramite"] ?? null;
$descripcion = $_POST["descripcion"] ?? null;

if (!$iCodTramite || !$descripcion) {
    echo json_encode(["status" => "error", "message" => "Datos incompletos."]);
    exit;
}

$sql = "UPDATE Tra_M_Tramite SET descripcion = ? WHERE iCodTramite = ?";
$params = [$descripcion, $iCodTramite];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt === false) {
    echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(), true)]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Guardado correctamente."]);
?>