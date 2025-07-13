<?php
include("conexion/conexion.php");
session_start();

$iCodTramite = $_POST['iCodTramite'] ?? null;
$iCodDigital = $_POST['iCodDigital'] ?? null;
$nombreTrabajador = trim($_POST['trabajador'] ?? '');
$posicion = strtoupper(trim($_POST['posicion'] ?? ''));
$tipoFirma = $_POST['tipoFirma'] ?? 0;

if (!$iCodTramite || !$iCodDigital || !$nombreTrabajador || !$posicion) {
    die("Datos incompletos.");
}

// Buscar trabajador por nombre o DNI
$sql = "SELECT TOP 1 iCodTrabajador, iCodOficina 
        FROM TRA_M_Trabajadores 
        WHERE cNombresTrabajador + ' ' + cApellidosTrabajador LIKE ? OR cNumDocIdentidad = ?";
$stmt = sqlsrv_query($cnx, $sql, ["%$nombreTrabajador%", $nombreTrabajador]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    die("Trabajador no encontrado.");
}

$iCodTrabajador = $row['iCodTrabajador'];
$iCodOficina = $row['iCodOficina'];

// Verificar duplicados
$sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
             WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ? AND posicion = ?";
$stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $iCodDigital, $iCodTrabajador, $posicion]);
$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if ($rowCheck['total'] == 0) {
    $sqlInsert = "INSERT INTO Tra_M_Tramite_Firma 
                  (iCodTramite, iCodDigital, iCodTrabajador, iCodOficina, nFlgFirma, nFlgEstado, posicion, tipoFirma)
                  VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
    sqlsrv_query($cnx, $sqlInsert, [$iCodTramite, $iCodDigital, $iCodTrabajador, $iCodOficina, $posicion, $tipoFirma]);
}

echo "<script>window.location.href = 'registroTrabajadoresFirmaComplementario.php?iCodTramite=$iCodTramite&iCodDigital=$iCodDigital';</script>";
exit;
