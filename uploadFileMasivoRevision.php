<?php
include_once("conexion/conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["signed_file"])) {
    $nombreZip = $_GET['nombreZip'] ?? "firmado_lote";
    $iCodTrabajador = intval(str_replace("vb_revision_lote_", "", $nombreZip));
    $archivoFirmado7z = __DIR__ . "/$nombreZip.signed.7z";

    move_uploaded_file($_FILES["signed_file"]["tmp_name"], $archivoFirmado7z);

    $cmd = "C:/7-Zip/7z.exe e \"$archivoFirmado7z\" -o\"" . __DIR__ . "/cAlmacenArchivos\" -y";
    exec($cmd, $output, $result);

    $jsonPath = __DIR__ . "/firmantes_{$iCodTrabajador}.json";
    if (file_exists($jsonPath)) {
        $firmantes = json_decode(file_get_contents($jsonPath), true);

        foreach ($firmantes as $firmante) {
            $archivoOriginal = __DIR__ . "/cAlmacenArchivos/" . $firmante['documento'];
            $archivoFirmado = preg_replace("/\.pdf$/", "[FP].pdf", $archivoOriginal);

            if (file_exists($archivoFirmado)) {
                unlink($archivoOriginal);
                rename($archivoFirmado, $archivoOriginal);
            }

            $iCodFirma = intval($firmante['iCodFirma']);
            $sql = "UPDATE Tra_M_Tramite_Firma SET nFlgFirma = 3 WHERE iCodFirma = ?";
            sqlsrv_query($cnx, $sql, [$iCodFirma]);
        }
    }

    if (file_exists($archivoFirmado7z)) unlink($archivoFirmado7z);
    if (file_exists(__DIR__ . "/$nombreZip.7z")) unlink(__DIR__ . "/$nombreZip.7z");
    if (file_exists($jsonPath)) unlink($jsonPath);

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Archivo no recibido."]);
}