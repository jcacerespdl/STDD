<?php
session_start();
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

// Crear carpeta de logs si no existe
$logDir = __DIR__ . "/logs";
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function log_zip($msg) {
    $logFile = __DIR__ . "/logs/firmar_zip.log";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// Validar sesión
if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    log_zip("❌ Sesión no activa");
    echo json_encode(["success" => false, "error" => "Sesión inválida"]);
    exit;
}

$codTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$documento     = $_POST['documento'] ?? '';
$iCodTramite   = $_POST['iCodTramite'] ?? null;

if (!$documento || !$iCodTramite) {
    log_zip("❌ Datos incompletos: documento o iCodTramite faltan");
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit;
}

// Ruta original del PDF
$pathOrigen = realpath(__DIR__ . "/cDocumentosFirmados") . DIRECTORY_SEPARATOR . $documento;
if (!file_exists($pathOrigen)) {
    log_zip("❌ No se encontró el PDF en: $pathOrigen");
    echo json_encode(["success" => false, "error" => "No se encontró el documento", "ruta" => $pathOrigen]);
    exit;
}

// Crear carpeta temporal
$tmpDir = sys_get_temp_dir() . "/firma_principal_" . uniqid();
if (!mkdir($tmpDir, 0777, true)) {
    log_zip("❌ No se pudo crear carpeta temporal: $tmpDir");
    echo json_encode(["success" => false, "error" => "No se pudo crear carpeta temporal"]);
    exit;
}

// Copiar el PDF al temporal
$rutaPDFTemporal = $tmpDir . DIRECTORY_SEPARATOR . $documento;
if (!copy($pathOrigen, $rutaPDFTemporal)) {
    log_zip("❌ No se pudo copiar el PDF a temporal: $rutaPDFTemporal");
    echo json_encode(["success" => false, "error" => "Error al copiar el PDF temporal"]);
    exit;
}

// Nombre y ruta del ZIP
$nombreZip = "principal_" . $iCodTramite . "_.7z";
$rutaZip = __DIR__ . DIRECTORY_SEPARATOR . $nombreZip;

// Eliminar ZIP anterior si existe
if (file_exists($rutaZip)) {
    @unlink($rutaZip);
    log_zip("🧹 ZIP anterior eliminado: $rutaZip");
}

// Ejecutar compresión
$comando = "\"C:/7-Zip/7z.exe\" a \"$rutaZip\" \"$rutaPDFTemporal\"";
log_zip("📦 Ejecutando: $comando");

exec($comando . " 2>&1", $output, $codigoSalida);
log_zip("📤 Salida ZIP: " . implode(" | ", $output));
log_zip("🔁 Código salida: $codigoSalida");

if ($codigoSalida !== 0 || !file_exists($rutaZip)) {
    log_zip("❌ ZIP no generado: $rutaZip");
    echo json_encode([
        "success" => false,
        "error" => "No se pudo crear ZIP",
        "comando" => $comando,
        "salida" => $output
    ]);
    exit;
}

// Limpiar temporales
@unlink($rutaPDFTemporal);
@rmdir($tmpDir);

log_zip("✅ ZIP generado correctamente: $rutaZip");

echo json_encode([
    "success" => true,
    "archivo" => $nombreZip,
    "rutaZip" => $rutaZip,
    "documentoIncluido" => $documento,
    "iCodTramite" => $iCodTramite
]);
