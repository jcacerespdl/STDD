<?php
session_start();
include("conexion/conexion.php");
date_default_timezone_set("America/Lima");
header("Content-Type: application/json");

$iCodMovimiento = intval($_POST['iCodMovimiento'] ?? 0);
$cObservaciones = trim($_POST['observaciones'] ?? '');
$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;

if (!$iCodMovimiento || !$iCodTrabajador) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Consultar documento actual
$sqlSel = "SELECT cDocumentoFinalizacion FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
$stmtSel = sqlsrv_query($cnx, $sqlSel, [$iCodMovimiento]);
$row = sqlsrv_fetch_array($stmtSel, SQLSRV_FETCH_ASSOC);
$nombreAnterior = $row['cDocumentoFinalizacion'] ?? null;

// Si se adjunta un nuevo archivo
if (isset($_FILES['archivoFinal']) && $_FILES['archivoFinal']['error'] === UPLOAD_ERR_OK) {
    $archivo = $_FILES['archivoFinal'];
    $nombreOriginal = $archivo['name'];
    $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $nombreLimpio = preg_replace('/\\s+/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
    $nombreNuevo = $iCodMovimiento . '-' . $nombreLimpio . '.' . $ext;
    $ruta = __DIR__ . "/cAlmacendeArchivos/" . $nombreNuevo;

    if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el nuevo archivo.']);
        exit;
    }
} else {
    $nombreNuevo = $nombreAnterior; // no se reemplazó
}

// Actualizar BD
$sqlUpd = "UPDATE Tra_M_Tramite_Movimientos
            SET cObservacionesFinalizar = ?,
                cDocumentoFinalizacion = ?
            WHERE iCodMovimiento = ?";
$params = [$cObservaciones, $nombreNuevo, $iCodMovimiento];
$stmtUpd = sqlsrv_query($cnx, $sqlUpd, $params);

if ($stmtUpd) {
    echo json_encode(['status' => 'ok']);
} else {
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => $error[0]['message'] ?? 'Error en base de datos']);
}
