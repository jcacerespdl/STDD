<?php
session_start();
include_once("conexion/conexion.php");
header('Content-Type: application/json');

$iCodFirma = $_GET['iCodFirma'] ?? 0;
$nombreZip = $_GET['nombreZip'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["signed_file"]) && $nombreZip) {
    $archivoFirmado7z = __DIR__ . "/{$nombreZip}.signed.7z";
    if (!move_uploaded_file($_FILES["signed_file"]["tmp_name"], $archivoFirmado7z)) {
        echo json_encode(["success" => false, "error" => "No se pudo guardar el archivo firmado."]);
        exit;
    }

    // Descomprimir en carpeta destino
    $destino = __DIR__ . "/cDocumentosFirmados";
    $cmd = "C:/7-Zip/7z.exe e \"$archivoFirmado7z\" -o\"$destino\" -y";
    exec($cmd, $output, $codigo);

    if ($codigo !== 0) {
        echo json_encode(["success" => false, "error" => "Error al descomprimir el documento firmado."]);
        exit;
    }

    // Renombrar el archivo firmado con [FP]
    $archivos = scandir($destino);
    $renombrado = false;
    foreach ($archivos as $archivo) {
        if (strpos($archivo, '[FP].pdf') !== false) {
            $rutaFP = "$destino/$archivo";
            $rutaOriginal = str_replace('[FP]', '', $rutaFP);
            if (file_exists($rutaOriginal)) unlink($rutaOriginal);
            rename($rutaFP, $rutaOriginal);
            $renombrado = true;
            break;
        }
    }

    // Marcar como firmado
    if ($renombrado && $iCodFirma > 0) {
        $sql = "UPDATE Tra_M_Tramite_Firma SET nFlgFirma = 3 WHERE iCodFirma = ?";
        sqlsrv_query($cnx, $sql, [$iCodFirma]);
    }

    @unlink($archivoFirmado7z);
    @unlink(__DIR__ . "/$nombreZip.7z");

    echo json_encode(["success" => $renombrado]);
} else {
    echo json_encode(["success" => false, "error" => "Archivo no recibido o faltan parámetros."]);
}
