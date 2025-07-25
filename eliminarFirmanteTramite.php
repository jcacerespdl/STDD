<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite     = $_POST['iCodTramite'] ?? null;
$iCodDigital     = $_POST['iCodDigital'] ?? null;
$iCodTrabajador  = $_POST['iCodTrabajador'] ?? null;

if (!$iCodTramite || !$iCodDigital || !$iCodTrabajador) {
    echo "Error: datos incompletos";
    exit;
}

$sqlDel = "DELETE FROM Tra_M_Tramite_Firma 
           WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ?";
$stmt   = sqlsrv_query($cnx, $sqlDel, [$iCodTramite, $iCodDigital, $iCodTrabajador]);

echo $stmt ? "ok" : "error";
