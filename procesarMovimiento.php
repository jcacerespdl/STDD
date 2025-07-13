<?php
global $cnx;
include_once("./conexion/conexion.php");


$iCodTramite = $_GET["iCodTramite"] ?? null;

if(!$iCodTramite){
    echo json_encode(["status" => "error", "message" => "No se recibio el iCodTramite"]);
    exit();
}
$tipoOperacion = $_GET["tipo"] ?? "crear";

$mode = "";

if ($tipoOperacion === "crear") {
    // Trámite generado

    $sqlMov = "SELECT iCodMovimiento FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? AND iCodTramiteDerivar IS NULL";
    $stmtMov = sqlsrv_query($cnx,$sqlMov,array($iCodTramite));

    $movs = [];
    while($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)){
        $movs[] = $row["iCodMovimiento"];
    }

    if(count($movs) == 1){
        $mode = "single";
    } else {
        $mode = "multiple";
    }

} elseif ($tipoOperacion === "derivar") {
    // Trámite derivado
    $sqlMov = "SELECT iCodMovimiento FROM Tra_M_Tramite_Movimientos WHERE iCodTramiteDerivar = ?";
    $stmtMov = sqlsrv_query($cnx,$sqlMov,array($iCodTramite));

    $movs = [];
    while($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)){
        $movs[] = $row["iCodMovimiento"];
    }

    if(count($movs) == 1){
        $mode = "single";
    } else {
        $mode = "multiple";
    }
}

$sqlUpdateMov = "UPDATE Tra_M_Tramite_Movimientos
                    SET fFecDerivar = GETDATE(), nFlgEnvio = 1
                    WHERE iCodMovimiento ".($mode === "single" ? "= {$movs[0]}" : "IN (".implode(",",$movs).")" );

$rsUpdateMov = sqlsrv_query($cnx,$sqlUpdateMov,array(), array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));

if($rsUpdateMov == false){
    echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(),true)]);
    exit();
}

echo json_encode(["status" => "success", "message" => "Movimientos actualizados", "query" => $sqlUpdateMov]);
exit();