<?php
include("conexion/conexion.php");
session_start();

$iCodFirma = $_POST['iCodFirma'] ?? null;
$iCodTramite = $_POST['iCodTramite'] ?? null;
$iCodDigital = $_POST['iCodDigital'] ?? null;

if (!$iCodFirma || !$iCodTramite || !$iCodDigital) {
    die("Faltan parámetros obligatorios.");
}

// Eliminar firmante por ID
$sql = "DELETE FROM Tra_M_Tramite_Firma WHERE iCodFirma = ?";
$result = sqlsrv_query($cnx, $sql, [$iCodFirma]);

// Redirigir de regreso
echo "<script>window.location.href = 'registroTrabajadoresFirmaComplementario.php?iCodTramite=$iCodTramite&iCodDigital=$iCodDigital';</script>";
exit;
