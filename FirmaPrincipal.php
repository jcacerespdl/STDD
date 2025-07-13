<?php
include_once("./conexion/conexion.php");
global $cnx;

$tramite = $_POST["iCodTramite"];

$pathTo7z = "C:/7-Zip/7z.exe";
$CompressedFile = "$tramite.7z";

$pathRoot = "./cDocumentosFirmados";
$sql = "SELECT documentoElectronico FROM Tra_M_Tramite WHERE iCodTramite = ?";
$rs = sqlsrv_query($cnx,$sql,array($tramite));
if( $rs === false ) {
    die( "Statement error: ".print_r( sqlsrv_errors(), true));
   }
if( sqlsrv_fetch( $rs ) === false) {
    die( "fetch error: ".print_r( sqlsrv_errors(), true));
   }
$documentEdit = sqlsrv_get_field( $rs, 0 );

$comando = "C:/7-Zip/7z.exe a -r $CompressedFile $pathRoot/$documentEdit";
exec($comando, $salida, $codigoSalida);
if($codigoSalida !== 0) echo json_encode("No se pudo comprimir con el codigo $");
else echo json_encode("Iniciando proceso de firma");



?>