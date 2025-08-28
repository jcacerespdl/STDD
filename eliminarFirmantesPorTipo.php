<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

if (!isset($_POST['iCodTramite'], $_POST['iCodDigital'])) {
    http_response_code(400);
    echo "Faltan parámetros: ";
    echo json_encode($_POST);
    exit;
}

$iCodTramite = $_POST['iCodTramite'];
$iCodDigital = $_POST['iCodDigital'];

if (!$iCodTramite || !$iCodDigital) {
    http_response_code(400);
    echo "Parámetros incompletos";
    exit;
}

$sql = "DELETE FROM Tra_M_Tramite_Firma WHERE iCodTramite = ? AND iCodDigital = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);

if ($stmt) {
    echo "ok";
} else {
    http_response_code(500);
    $errors = sqlsrv_errors();
    echo "Error al eliminar firmantes: " . print_r($errors, true);
}
