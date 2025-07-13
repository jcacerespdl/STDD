<?php
global $cnx;
include_once("./conexion/conexion.php");

$mode = $_GET["mode"] ?? null;

$zipFile = "";
$docs = [];

switch($mode){
    case "single":
        $iCodDigital = $_POST["iCodDigital"];
        $sql = "SELECT cNombreOriginal FROM Tra_M_Tramite_Digitales WHERE iCodDigital = ?";
        $stmt = sqlsrv_query($cnx,$sql,array($iCodDigital));

        if(!$stmt){
            echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(),true)]);
            exit();
        }

        $prev = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $docs[] = "./cAlmacenArchivos/".$prev["cNombreOriginal"];
        $zipFile = "single-".$_POST["iCodDigital"].".7z";
        break;
    case "multiple":
        $batch = $_POST["batch"];
        $sql = "SELECT cNombreOriginal FROM Tra_M_Tramite_Digitales WHERE iCodDigital IN (".str_replace("_",", ", $batch).")";
        $stmt = sqlsrv_query($cnx,$sql,array());

        if(!$stmt){
            echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(),true)]);
            exit();
        }

        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $docs[] = "./cAlmacenArchivos/".$row["cNombreOriginal"];
        }

        $zipFile = "multiple-$batch.7z";
        break;
    default: 
        echo json_encode(["status" => "error", "message" => "no se especifico el modo"]);
        exit();
}


$command = "C:/7-Zip/7z.exe a -r $zipFile ".implode(" ",$docs);

exec($command, $salida, $codigoSalida);

if($codigoSalida !== 0) {
    echo json_encode(["status" => "error", "message" => "Error al comprimir el archivo: $command"]);
} else {
    echo json_encode(["status" => "success", "message" => "Complementarios empaquetados correctamente", "zip" => $zipFile]);
}