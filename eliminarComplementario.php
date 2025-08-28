<?php
include_once("conexion/conexion.php");
session_start();

$iCodTramite = $_GET['iCodTramite'] ?? null;
$archivo = $_GET['archivo'] ?? null;

if (!$iCodTramite || !$archivo) {
    die("Datos incompletos.");
}

$ruta = __DIR__ . "/cAlmacenArchivos/" . $archivo;

// Borrar archivo del servidor
if (file_exists($ruta)) {
    unlink($ruta);
}

// Eliminar firmas asociadas al documento digital (complementario)
$sqlFirma = "DELETE FROM Tra_M_Tramite_Firma WHERE iCodDigital IN (
    SELECT iCodDigital FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND cDescripcion = ?
)";
$paramsFirma = [$iCodTramite, $archivo];
sqlsrv_query($cnx, $sqlFirma, $paramsFirma);

// Borrar registro del documento digital
$sql = "DELETE FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND cDescripcion = ?";
$params = [$iCodTramite, $archivo];
$stmt = sqlsrv_query($cnx, $sql, $params);

header("Location: registroOficinaEditor.php?iCodTramite=" . urlencode($iCodTramite));
exit;
?>
