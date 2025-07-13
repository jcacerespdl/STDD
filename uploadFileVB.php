<?php
session_start();
include_once("conexion/conexion.php");
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

$iCodFirma = isset($_GET['iCodFirma']) ? intval($_GET['iCodFirma']) : 0;
$nombreZip = $_GET['nombreZip'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["signed_file"]) && $nombreZip) {
    $archivoFirmado7z = __DIR__ . "/{$nombreZip}.signed.7z";
    if (!move_uploaded_file($_FILES["signed_file"]["tmp_name"], $archivoFirmado7z)) {
        echo json_encode(["success" => false, "error" => "No se pudo guardar el archivo firmado."]);
        exit;
    }

    // Descomprimir en carpeta destino
    $carpetaDestino = __DIR__ . "/cAlmacenArchivos";
    $comando = "C:/7-Zip/7z.exe e \"$archivoFirmado7z\" -o\"$carpetaDestino\" -y";
    exec($comando, $output, $codigo);

    if ($codigo !== 0) {
        echo json_encode(["success" => false, "error" => "Error al descomprimir el archivo firmado."]);
        exit;
    }

    // Renombrar el archivo con [FP]
    $archivos = scandir($carpetaDestino);
    $renombrado = false;
    foreach ($archivos as $archivo) {
        if (strpos($archivo, '[FP].pdf') !== false) {
            $rutaFP = "$carpetaDestino/$archivo";
            $rutaOriginal = str_replace('[FP]', '', $rutaFP);

            if (file_exists($rutaOriginal)) unlink($rutaOriginal);
            rename($rutaFP, $rutaOriginal);
            $renombrado = true;
            break;
        }
    }

    // Limpiar residuos
    @unlink($archivoFirmado7z);
    @unlink(__DIR__ . "/{$nombreZip}.7z");

    // Marcar como firmado en base de datos
    if ($renombrado && $iCodFirma > 0) {
        $sql = "UPDATE Tra_M_Tramite_Firma SET nFlgFirma = 3 WHERE iCodFirma = ?";
        $stmt = sqlsrv_prepare($cnx, $sql, [$iCodFirma]);
        if (!sqlsrv_execute($stmt)) {
            error_log("❌ Error al actualizar nFlgFirma para iCodFirma = $iCodFirma: " . print_r(sqlsrv_errors(), true));
        }
    }

    echo json_encode(["success" => $renombrado]);
} else {
    echo json_encode(["success" => false, "error" => "Archivo no recibido o faltan parámetros."]);
}
