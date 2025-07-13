<?php
session_start();
include_once("./conexion/conexion.php");
global $cnx;

$iCodJefe = $_SESSION['CODIGO_TRABAJADOR'];
$iCodTramite = $_POST['iCodTramite'];

if(!isset($_POST['iCodTramite'])){
    echo json_encode(["status" => "error", "message" => "No se recibio el iCodTramite"]);
}

$sqlTrabajador = "SELECT * FROM Tra_M_Trabajadores WHERE iCodTrabajador = ".$iCodJefe;		
$rsTrabajador = sqlsrv_query($cnx,$sqlTrabajador,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));	
$RsTrabajador  = sqlsrv_fetch_array($rsTrabajador);

$cNomJefe = trim($RsTrabajador['cNombresTrabajador'])." ".trim($RsTrabajador['cApellidosTrabajador']);

$sqlUpdate = "UPDATE Tra_M_Tramite
                        SET nFlgEnvio       = 0,
                            FECHA_DOCUMENTO = getdate(),
                            iCodJefe        = '$iCodJefe',
                            cNomJefe        = '$cNomJefe',
                            nFlgAnulado     = 0
                        WHERE iCodTramite = ".$iCodTramite;
    
$rsUpdate = sqlsrv_query($cnx,$sqlUpdate,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));

// Verificar el tipo de trámite
// $queryTipoDerivo = "SELECT nFlgTipoDerivo FROM Tra_M_Tramite  WHERE iCodTramite =  $iCodTramite";
// $result = sqlsrv_query($cnx, $queryTipoDerivo, array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
// $Result = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

$tipoOperacion = $_POST["tipoOperacion"] ?? "crear";

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
                    SET fFecDerivar = GETDATE(), nFlgEnvio = 0
                    WHERE iCodMovimiento ".($mode === "single" ? "= {$movs[0]}" : "IN (".implode(",",$movs).")" );

$rsUpdateMov = sqlsrv_query($cnx,$sqlUpdateMov,array(), array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));

if($rsUpdateMov == false){
    echo json_encode(["status" => "error", "message" => "Error SQL: ".print_r(sqlsrv_errors(),true)]);
    exit();
}

echo json_encode(["status" => "success", "message" => "Movimientos actualizados", "query" => $sqlUpdateMov]);
exit();