<?php
include_once("conexion/conexion.php");

function debug_log_sgd($mensaje) {
    $rutaLog = __DIR__ . "/debug.log";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($rutaLog, "[$timestamp] $mensaje" . PHP_EOL, FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["signed_file"])) {
    $nombreZip = $_GET['nombreZip'] ?? "firmado_lote";
    $iCodTrabajador = intval(str_replace("porAprobar_", "", explode("_", $nombreZip)[1] ?? 0));
    $archivoFirmado7z = __DIR__ . "/$nombreZip.signed.7z";
    $carpetaDestino = __DIR__ . "/cDocumentosFirmados";

    debug_log_sgd("=== INICIO PROCESO FIRMA POR APROBAR ===");
    debug_log_sgd("ZIP recibido: $archivoFirmado7z");

    if (!move_uploaded_file($_FILES["signed_file"]["tmp_name"], $archivoFirmado7z)) {
        debug_log_sgd("❌ No se pudo guardar el archivo firmado.");
        echo json_encode(["error" => "No se pudo guardar el archivo firmado."]);
        exit;
    }

    // Extraer ZIP firmado
    $cmd = "C:/7-Zip/7z.exe e \"$archivoFirmado7z\" -o\"$carpetaDestino\" -y";
    exec($cmd, $output, $result);
    debug_log_sgd("Comando ejecutado: $cmd");
    debug_log_sgd("Código retorno: $result");
    debug_log_sgd("Salida 7z: " . implode(" | ", $output));

    // Leer JSON de firmantes
    $jsonPath = __DIR__ . "/firmantes_{$iCodTrabajador}.json";
    if (!file_exists($jsonPath)) {
        debug_log_sgd("❌ JSON de firmantes no encontrado: $jsonPath");
        echo json_encode(["error" => "JSON no encontrado"]);
        exit;
    }

    $firmantes = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($firmantes)) {
        debug_log_sgd("❌ Error al parsear JSON de firmantes.");
        echo json_encode(["error" => "JSON malformado"]);
        exit;
    }

    foreach ($firmantes as $firmante) {
        $nombreOriginal = $firmante['archivo'];  // Ej: MEMO123.pdf
        $archivoOriginal = "$carpetaDestino/$nombreOriginal";
        $archivoFirmado = preg_replace("/\.pdf$/i", "[FP].pdf", $archivoOriginal);

        debug_log_sgd("Procesando archivo: $nombreOriginal");
        debug_log_sgd("Firmado esperado: $archivoFirmado");
        debug_log_sgd("Original destino: $archivoOriginal");

        if (file_exists($archivoFirmado)) {
            if (file_exists($archivoOriginal)) {
                if (unlink($archivoOriginal)) {
                    debug_log_sgd("🗑️ Original eliminado: $archivoOriginal");
                } else {
                    debug_log_sgd("❌ No se pudo eliminar original: $archivoOriginal");
                }
            }

            if (rename($archivoFirmado, $archivoOriginal)) {
                debug_log_sgd("✅ Renombrado: $archivoFirmado → $archivoOriginal");

                $iCodTramite = intval($firmante['iCodTramite']);
                debug_log_sgd("Actualizando BD para iCodTramite: $iCodTramite");

                $sql = "UPDATE Tra_M_Tramite 
                        SET documentoElectronico = ?, nFlgFirma = 1, nFlgEnvio = 1  
                        WHERE iCodTramite = ?";
                sqlsrv_query($cnx, $sql, [$nombreOriginal, $iCodTramite]);

                $sql2 = "UPDATE Tra_M_Tramite_Movimientos 
                         SET fFecDerivar = GETDATE() 
                         WHERE iCodTramite = ? AND nFlgEnvio IS NULL";
                sqlsrv_query($cnx, $sql2, [$iCodTramite]);
            } else {
                debug_log_sgd("❌ Error al renombrar: $archivoFirmado → $archivoOriginal");
            }
        } else {
            debug_log_sgd("❌ Archivo firmado no encontrado en: $archivoFirmado");
        }
    }

    // Limpieza
    @unlink($archivoFirmado7z);
    @unlink(__DIR__ . "/$nombreZip.7z");
    @unlink($jsonPath);
    debug_log_sgd("🧹 Limpieza completada.");
    debug_log_sgd("=== FIN PROCESO ===");

    echo json_encode(["success" => true]);
} else {
    debug_log_sgd("❌ Archivo firmado no recibido correctamente.");
    echo json_encode(["error" => "Archivo firmado no recibido."]);
}
