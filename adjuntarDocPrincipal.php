<?php
header('Content-Type: application/json');
session_start();

global $cnx;
include_once("./conexion/conexion.php");

try {
    $iCodTramite = $_POST['iCodTramite'];

    if(empty($_FILES['file'])){
        echo json_encode(["status" => "error", "message" => "No se recibieron archivos", "data" => null]);
        exit();
    }

    $sqlTramite = "SELECT td.cCodTipoDoc, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.fFecRegistro
               FROM Tra_M_Tramite t 
               JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
               WHERE t.iCodTramite = ?";
    $stmtTramite = sqlsrv_query($cnx,$sqlTramite, array($iCodTramite));

    $tramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);

    $sqlOficina = "SELECT cSiglaOficina FROM Tra_M_Oficinas WHERE iCodOficina = ?";
    $params = array($_SESSION['iCodOficinaLogin']);
    $resultOficina = sqlsrv_query($cnx, $sqlOficina, $params);
    $rowOficina = sqlsrv_fetch_array($resultOficina, SQLSRV_FETCH_ASSOC);
    $cSiglaOficina = $rowOficina['cSiglaOficina'] ?? '';

    $tituloDocumento = str_replace(" ", "-", $tramite['cDescTipoDoc'])."-{$tramite['cCodificacion']}-$cSiglaOficina.pdf";

    $sqlUpdt = "UPDATE Tra_M_Tramite set documentoElectronico = ? WHERE iCodTramite = ?";
    $stmtUpdt = sqlsrv_query($cnx,$sqlUpdt, array($tituloDocumento, $iCodTramite));

    move_uploaded_file($_FILES['file']['tmp_name'], "./cDocumentosFirmados/{$tituloDocumento}");

    echo json_encode(["status" => "success", "message" => null, "data" => null]);
} catch(Exception $e){
    echo json_encode(["status" => "error", "message" => "Error inesperado: {$e}", "data" => null]);
    exit();
}