<?php
session_start();
include_once("conexion/conexion.php");
date_default_timezone_set('America/Lima');
header('Content-Type: application/json');

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["signed_file"])) {
    $nombreZip = $_GET['nombreZip'] ?? null;
    if (!$nombreZip) {
        echo json_encode(["success" => false, "error" => "Falta el nombre del ZIP."]);
        exit;
    }

    $archivoFirmado7z = __DIR__ . "/$nombreZip.signed.7z";
    if (!move_uploaded_file($_FILES["signed_file"]["tmp_name"], $archivoFirmado7z)) {
        echo json_encode(["success" => false, "error" => "No se pudo guardar el archivo firmado."]);
        exit;
    }

    // Descomprimir en carpeta de documentos principales
    $carpetaDestino = __DIR__ . "/cDocumentosFirmados";
    $comando = "C:/7-Zip/7z.exe e \"$archivoFirmado7z\" -o\"$carpetaDestino\" -y";
    exec($comando, $output, $codigo);

    if ($codigo !== 0) {
        echo json_encode(["success" => false, "error" => "Error al descomprimir el archivo firmado."]);
        exit;
    }

    // Buscar el PDF con sufijo [FP] y renombrarlo
    $archivos = scandir($carpetaDestino);
    $renombrado = false;

    foreach ($archivos as $archivo) {
        if (strpos($archivo, '[FP].pdf') !== false) {
            $rutaFP = "$carpetaDestino/$archivo";
            $rutaOriginal = str_replace('[FP]', '', $rutaFP);

            if (file_exists($rutaOriginal)) {
                unlink($rutaOriginal);
            }

            rename($rutaFP, $rutaOriginal);
            $renombrado = true;
            break;
        }
    }

    // Limpiar residuos
    if (file_exists($archivoFirmado7z)) unlink($archivoFirmado7z);
    if (file_exists(__DIR__ . "/$nombreZip.7z")) unlink(__DIR__ . "/$nombreZip.7z");

    // ✅ Actualizar el estado de envío si se renombró correctamente
     
        $sqlUpdate = "UPDATE Tra_M_Tramite SET nFlgEnvio = 1, nflgfirma=1  WHERE iCodTramite = ?";
        $stmtUpdate = sqlsrv_prepare($cnx, $sqlUpdate, [$iCodTramite]);
        if (!sqlsrv_execute($stmtUpdate)) {
            error_log("❌ Error al actualizar nFlgEnvio en iCodTramite = $iCodTramite: " . print_r(sqlsrv_errors(), true));
        } else {
            error_log("✅ Trámite $iCodTramite marcado como enviado correctamente.");
        }
     

    if ($renombrado) {
        error_log("✅ Archivo firmado renombrado correctamente a: $rutaOriginal");
    }

    echo json_encode(["success" => $renombrado]);
} else {
    echo json_encode(["success" => false, "error" => "Archivo firmado no recibido."]);
}
