<?php
global $cnx;
require_once("./conexion/conexion.php");

$iCodFirma = $_POST["iCodFirma"] ?? null;
$posFirma = $_POST["posFirma"] ?? null;

if(!$iCodFirma){
    echo json_encode(["status" => "error", "message" => "No se recibio el iCodFirma"]);
    exit();
}

$sqlPrev = "SELECT 
                CASE
                    WHEN nFlgEstado = 1
                        THEN (SELECT T.documentoElectronico FROM Tra_M_Tramite T WHERE T.iCodTramite = TF.iCodTramite)
                    ELSE (SELECT TD.cNombreOriginal FROM Tra_M_Tramite_Digitales TD WHERE TD.iCodDigital = TF.iCodDigital)
                END AS documento,
                CASE 
                    WHEN nFlgEstado = 1
                        THEN 'principal'
                    ELSE 'complementario'
                END AS tipo
            FROM Tra_M_Tramite_firma TF WHERE TF.iCodFirma = ?";
$stmtPrev = sqlsrv_query($cnx, $sqlPrev, array($iCodFirma));

if($stmtPrev == false){
    echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(),true)]);
    exit();
}

$prev = sqlsrv_fetch_array($stmtPrev, SQLSRV_FETCH_ASSOC);

if($prev["tipo"] == "principal"){
    $command = "C:/7-Zip/7z.exe a -r ./principal_".$iCodFirma.".7z ./cDocumentosFirmados/".$prev["documento"];
} else {
    $command = "C:/7-Zip/7z.exe a -r ./complementario_".$iCodFirma.".7z ./cAlmacenArchivos/".$prev["documento"];
}

exec($command, $salida, $codigoSalida);

if($codigoSalida !== 0) {
    echo json_encode(["status" => "error", "message" => "Error al comprimir el archivo: $command"]);
} else {
    echo json_encode(["status" => "success", "message" => $prev["tipo"]]);
}
exit();