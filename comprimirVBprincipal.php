<?php
session_start();
header('Content-Type: application/json');

$documento = $_POST["documento"] ?? '';
$iCodFirma = $_POST["iCodFirma"] ?? 0;

if (!$documento || !$iCodFirma) {
    echo json_encode(["success" => false, "error" => "Faltan parámetros."]);
    exit;
}

$origen = __DIR__ . "/cDocumentosFirmados/" . $documento;
$destino = __DIR__ . "/VBP_{$iCodFirma}.7z";

if (!file_exists($origen)) {
    echo json_encode(["success" => false, "error" => "El archivo principal no existe."]);
    exit;
}

$cmd = "C:/7-Zip/7z.exe a \"$destino\" \"$origen\"";
exec($cmd, $output, $result);

if ($result !== 0) {
    echo json_encode(["success" => false, "error" => "Error al comprimir el documento principal."]);
} else {
    echo json_encode(["success" => true, "archivo" => "VBP_{$iCodFirma}"]);
}
