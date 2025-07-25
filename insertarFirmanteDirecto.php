<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite = $_POST['iCodTramite'] ?? null;
$iCodDigital = $_POST['iCodDigital'] ?? null;
$iCodTrabajador = $_POST['iCodTrabajador'] ?? null;
$iCodOficina = $_POST['iCodOficina'] ?? null;
$posicion = $_POST['posicion'] ?? null;
$tipoFirma = $_POST['tipoFirma'] ?? null;

if (!$iCodTramite || !$iCodDigital || !$iCodTrabajador || !$iCodOficina || !$posicion || !$tipoFirma) {
    echo "Error: datos incompletos";
    exit;
}

 
    $sqlAdd = "INSERT INTO Tra_M_Tramite_Firma (
        iCodTramite, iCodDigital, iCodTrabajador, iCodOficina,
        nFlgFirma, nFlgEstado, posicion, tipoFirma
    ) VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
    $params = [$iCodTramite, $iCodDigital, $iCodTrabajador, $iCodOficina, $posicion, $tipoFirma];
    sqlsrv_query($cnx, $sqlAdd, $params);
    // ✅ Verificar si el INSERT falló
   

echo "ok";
