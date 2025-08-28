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

$nombreFinal = null;

// Verificar si hay archivo subido
if (isset($_FILES['archivoFinal']) && $_FILES['archivoFinal']['error'] === UPLOAD_ERR_OK) {
    $archivo = $_FILES['archivoFinal'];
    $nombreOriginal = $archivo['name'];
    $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $nombreLimpio = preg_replace('/\s+/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
    $nombreFinal = $iCodMovimiento . '-' . $nombreLimpio . '.' . $extension;
    $rutaDestino = __DIR__ . "/cAlmacenArchivos/" . $nombreFinal;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo']);
        exit;
    }
}

// Actualizar movimiento
$sql = "UPDATE Tra_M_Tramite_Movimientos
        SET nEstadoMovimiento = 5,
            iCodTrabajadorFinalizar = ?,
            cObservacionesFinalizar = ?,
            fFecFinalizar = ?,
            cDocumentoFinalizacion = ?
        WHERE iCodMovimiento = ?";

$params = [$iCodTrabajador, $cObservaciones, $fFecFinalizacion, $nombreFinal, $iCodMovimiento];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt) {
    header("Location: bandejaFinalizados.php");
    exit;
} else {
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => $error[0]['message'] ?? 'Error al guardar en base de datos']);
}
