<?
// se zippea la carpeta tmp:

// session_start(); 
include_once("./conexion/conexion.php");


$pathTo7z = "C:/7-Zip/7z.exe";
$CompressedFile = "$_POST[iCodTramite].7z";

$pathRoot = "./cDocumentosFirmados";
$sql = "SELECT documentoElectronico FROM Tra_M_Tramite WHERE iCodTramite = $_POST[iCodTramite]";
$rs = sqlsrv_query($cnx,$sql);
if( $rs === false ) {
    die( print_r( sqlsrv_errors(), true));
   }
if( sqlsrv_fetch( $rs ) === false) {
    die( print_r( sqlsrv_errors(), true));
   }
$documentEdit = sqlsrv_get_field( $rs, 0 );

$comando = "C:/7-Zip/7z.exe a -r $CompressedFile $pathRoot/$documentEdit";
exec($comando, $salida, $codigoSalida);
if($codigoSalida !== 0) echo json_encode("No se pudo comprimir con el codigo $");
else echo json_encode("Iniciando proceso de firma ");



?>