<?php
include("conexion/conexion.php");
session_start();

$iCodTramite = $_POST['iCodTramite'] ?? null;
$iCodDigital = $_POST['iCodDigital'] ?? null;
$oficinaP = trim($_POST['oficinaP'] ?? '');
$oficinaQ = trim($_POST['oficinaQ'] ?? '');
$oficinaO = trim($_POST['oficinaO'] ?? '');

if (!$iCodTramite || !$iCodDigital || !$oficinaP || !$oficinaQ || !$oficinaO) {
    die("Faltan datos obligatorios.");
}

function obtenerTrabajadorJefe($cnx, $nombreOficina) {
    $sql = "SELECT TOP 1 PU.iCodTrabajador, O.iCodOficina
            FROM Tra_M_Oficinas O
            JOIN Tra_M_Perfil_Ususario PU ON PU.iCodOficina = O.iCodOficina
            WHERE PU.iCodPerfil = 3 AND O.cNomOficina LIKE ?";

    $stmt = sqlsrv_query($cnx, $sql, ["%$nombreOficina%"]);
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function insertarFirma($cnx, $iCodTramite, $iCodDigital, $iCodTrabajador, $iCodOficina, $posicion) {
    $sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
                 WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ? AND posicion = ?";
    $stmt = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $iCodDigital, $iCodTrabajador, $posicion]);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($row['total'] == 0) {
        $sqlInsert = "INSERT INTO Tra_M_Tramite_Firma 
                      (iCodTramite, iCodDigital, iCodTrabajador, iCodOficina, nFlgFirma, nFlgEstado, posicion, tipoFirma)
                      VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
        $tipoFirma = ($posicion == 'O') ? 0 : 1;
        sqlsrv_query($cnx, $sqlInsert, [$iCodTramite, $iCodDigital, $iCodTrabajador, $iCodOficina, $posicion, $tipoFirma]);
    }
}

$trabajadorP = obtenerTrabajadorJefe($cnx, $oficinaP);
$trabajadorQ = obtenerTrabajadorJefe($cnx, $oficinaQ);
$trabajadorO = obtenerTrabajadorJefe($cnx, $oficinaO);

if ($trabajadorP) insertarFirma($cnx, $iCodTramite, $iCodDigital, $trabajadorP['iCodTrabajador'], $trabajadorP['iCodOficina'], 'P');
if ($trabajadorQ) insertarFirma($cnx, $iCodTramite, $iCodDigital, $trabajadorQ['iCodTrabajador'], $trabajadorQ['iCodOficina'], 'Q');
if ($trabajadorO) insertarFirma($cnx, $iCodTramite, $iCodDigital, $trabajadorO['iCodTrabajador'], $trabajadorO['iCodOficina'], 'O');

echo "<script>window.location.href = 'registroTrabajadoresFirmaComplementario.php?iCodTramite=$iCodTramite&iCodDigital=$iCodDigital';</script>";
exit;
