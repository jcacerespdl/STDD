<?php
session_start();
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');
include("conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo json_encode(["success" => false, "error" => "Sesión inválida"]);
    exit;
}

$codTrabajador = $_SESSION['CODIGO_TRABAJADOR'];

if (!isset($_POST['documentos']) || empty($_POST['documentos'])) {
    echo json_encode(["success" => false, "error" => "No se recibieron documentos"]);
    exit;
}

$documentos = $_POST['documentos'];
$firmantesJson = $_POST['firmantesJson'] ?? '[]';
$firmantesRaw = json_decode($firmantesJson, true);

if (!is_array($firmantesRaw) || count($firmantesRaw) === 0) {
    file_put_contents(__DIR__ . "/json_error_revision_{$codTrabajador}.txt", $firmantesJson);
    echo json_encode(["success" => false, "error" => "firmantesJson vacío o malformado"]);
    exit;
}

$firmantes = [];

foreach ($documentos as $docNombre) {
    foreach ($firmantesRaw as $entrada) {
        if ($entrada['documento'] === $docNombre) {
            $iCodFirma = (int)$entrada['iCodFirma'];

            $sql = "SELECT posicion, tipoFirma FROM Tra_M_Tramite_Firma WHERE iCodFirma = ?";
            $stmt = sqlsrv_query($cnx, $sql, [$iCodFirma]);

            if ($stmt && ($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $firmantes[] = [
                    'documento' => $docNombre,
                    'iCodFirma' => $iCodFirma,
                    'posicion' => $fila['posicion'],
                    'tipoFirma' => (int)$fila['tipoFirma']
                ];
            } else {
                error_log("❌ No se encontró firma con iCodFirma=$iCodFirma");
            }
        }
    }
}

if (count($firmantes) === 0) {
    echo json_encode(["success" => false, "error" => "No se generaron firmantes válidos"]);
    exit;
}

// Rutas
$pathPDFs = realpath(__DIR__ . "/cAlmacenArchivos");
$pathZIP = realpath(__DIR__);
$nombreZip = "vb_revision_lote_" . $codTrabajador . ".7z";
$rutaZip = $pathZIP . "/" . $nombreZip;
$nombreJson = "firmantes_{$codTrabajador}.json";
$rutaJson = $pathZIP . "/" . $nombreJson;

// Guardar JSON final
file_put_contents($rutaJson, json_encode($firmantes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Comprimir
$comando = "\"C:/7-Zip/7z.exe\" a -r \"$rutaZip\"";
$logArchivos = [];

foreach ($documentos as $archivo) {
    $archivoPath = $pathPDFs . "/" . $archivo;
    if (file_exists($archivoPath)) {
        $comando .= " \"$archivoPath\"";
        $logArchivos[] = $archivoPath;
    } else {
        error_log("❌ No encontrado: $archivoPath");
    }
}

$comando .= " \"$rutaJson\"";
exec($comando, $output, $codigoSalida);

if ($codigoSalida === 0 && file_exists($rutaZip)) {
    echo json_encode([
        "success" => true,
        "archivo" => $nombreZip,
        "url" => "/SGD/$nombreZip",
        "archivosIncluidos" => $logArchivos,
        "firmantes" => $firmantes
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Error al comprimir con 7z",
        "comando" => $comando,
        "output" => $output
    ]);
}
