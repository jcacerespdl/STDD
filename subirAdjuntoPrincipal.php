<?php
include 'conexion/conexion.php';
session_start();
header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? null;

if (!$iCodTramite || !isset($_FILES['archivoPrincipal'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos o archivo.']);
    exit;
}

$archivo = $_FILES['archivoPrincipal'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Error al subir archivo.']);
    exit;
}

// Validar extensión PDF
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['status' => 'error', 'message' => 'Solo se permite PDF.']);
    exit;
}

// Validar tamaño (máx 20 MB)
if ($archivo['size'] > 20 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'El archivo supera los 20 MB.']);
    exit;
}

// Obtener datos para construir el nombre estándar
$sql = "SELECT t.cCodificacion, td.cDescTipoDoc
        FROM Tra_M_Tramite t
        JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
        WHERE t.iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);

if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo json_encode(['status' => 'error', 'message' => 'Trámite no encontrado.']);
    exit;
}

$cod = str_replace(['/', ' '], ['_', '-'], $row['cCodificacion']);
$tipo = str_replace(['/', ' '], ['_', '-'], $row['cDescTipoDoc']);
$nombreFinal = "{$tipo}-{$cod}.pdf";

// Mover archivo
$rutaDestino = __DIR__ . "/cDocumentosFirmados/" . $nombreFinal;
if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo.']);
    exit;
}

// Actualizar base de datos
$sqlUpdate = "UPDATE Tra_M_Tramite SET documentoElectronico = ? WHERE iCodTramite = ?";
$resUpdate = sqlsrv_query($cnx, $sqlUpdate, [$nombreFinal, $iCodTramite]);

if (!$resUpdate) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el trámite.']);
    exit;
}

echo json_encode(['status' => 'success', 'filename' => $nombreFinal]);
exit;
