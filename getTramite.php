<?php
global $cnx;
include_once("./conexion/conexion.php");

$iCodTramite = $_GET["iCodTramite"];

$sql = "SELECT * FROM Tra_M_Tramite WHERE iCodTramite = ?";
$params = array($iCodTramite);
$stmt = sqlsrv_query($cnx,$sql,$params);

if($stmt == false){
    die(print_r(sqlsrv_errors(),true));
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);


echo json_encode(["data" => $data]);
