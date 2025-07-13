<?php
include_once("conexion/conexion.php");

$iCodTramite = intval($_GET['iCodTramite'] ?? 0);

$sql = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
        WHERE iCodTramite = ? AND iCodDigital IS NULL";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

echo json_encode(['hayFirmantes' => $row['total'] > 0]);
?>