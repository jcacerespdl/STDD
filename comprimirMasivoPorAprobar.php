<?php
session_start();
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo json_encode(["status" => "error", "message" => "Sesión inválida"]);
    exit;
}

$codTrabajador = $_SESSION['CODIGO_TRABAJADOR'];

// Leer entrada JSON del cuerpo de la petición
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['documentos']) || !is_array($data['documentos']) || empty($data['documentos'])) {
    echo json_encode(["status" => "error", "message" => "No se recibieron documentos válidos"]);
    exit;
}

// Crear carpeta temporal
$tmpDir = sys_get_temp_dir() . "/firma_" . uniqid();
if (!mkdir($tmpDir, 0777, true)) {
    echo json_encode(["status" => "error", "message" => "No se pudo crear carpeta temporal"]);
    exit;
}

// Copiar documentos al temporal
foreach ($data['documentos'] as $doc) {
    $nombre = $doc['archivo'] ?? '';
    if (!$nombre) continue;

    $origen = realpath(__DIR__ . "/cDocumentosFirmados") . DIRECTORY_SEPARATOR . $nombre;
    $destino = $tmpDir . DIRECTORY_SEPARATOR . $nombre;

    if (!file_exists($origen)) {
        echo json_encode(["status" => "error", "message" => "No se encontró: $nombre"]);
        exit;
    }

    if (!copy($origen, $destino)) {
        echo json_encode(["status" => "error", "message" => "No se pudo copiar: $nombre"]);
        exit;
    }
}

// Generar nombre de ZIP
$nombreZip = "porAprobar_" . $codTrabajador . "_" . uniqid() . ".7z";
$rutaZip = __DIR__ . DIRECTORY_SEPARATOR . $nombreZip;

// Ejecutar compresión
$comando = "\"C:/7-Zip/7z.exe\" a \"$rutaZip\" \"$tmpDir/*\"";
exec($comando, $output, $exitCode);

if ($exitCode !== 0 || !file_exists($rutaZip)) {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo crear ZIP",
        "comando" => $comando,
        "salida" => $output
    ]);
    exit;
}

// Crear JSON de firmantes para upload
$firmantes = array_map(function($doc) {
    return [
        'archivo' => $doc['archivo'],
        'iCodTramite' => $doc['iCodTramite']
    ];
}, $data['documentos']);

file_put_contents(__DIR__ . "/firmantes_{$codTrabajador}.json", json_encode($firmantes, JSON_PRETTY_PRINT));

// Limpiar temporales
array_map('unlink', glob("$tmpDir/*"));
rmdir($tmpDir);

// Respuesta OK
echo json_encode([
    "status" => "ok",
    "zipPath" => $nombreZip,
    "message" => "ZIP creado correctamente"
]);
