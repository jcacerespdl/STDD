<?php
include("conexion/conexion.php");

header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? null;
$iCodTramiteRef = $_POST['iCodRelacionado'] ?? null;
$accion = $_POST['accion'] ?? 'insertar';

if (!$iCodTramite || !$iCodTramiteRef) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

if ($accion === 'eliminar') {
    $sql = "DELETE FROM Tra_M_Tramite_Referencias WHERE iCodTramite = ? AND iCodTramiteRef = ?";
    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodTramiteRef]);
    if ($stmt) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
    }
    exit;
}

// Verificar duplicado
$sqlCheck = "SELECT 1 FROM Tra_M_Tramite_Referencias WHERE iCodTramite = ? AND iCodTramiteRef = ?";
$stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $iCodTramiteRef]);
if ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) {
    echo json_encode(['status' => 'success']); // ya existe, no se duplica
    exit;
}

// Obtener documentoElectronico del iCodTramiteRef
$sqlDoc = "SELECT documentoElectronico FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmtDoc = sqlsrv_query($cnx, $sqlDoc, [$iCodTramiteRef]);
$rowDoc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);

if (!$rowDoc || empty($rowDoc['documentoElectronico'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró el documento referenciado']);
    exit;
}

$cReferencia = $rowDoc['documentoElectronico'];

// Insertar referencia
$sqlInsert = "INSERT INTO Tra_M_Tramite_Referencias (iCodTramite, iCodTramiteRef, cReferencia)
              VALUES (?, ?, ?)";
$params = [$iCodTramite, $iCodTramiteRef, $cReferencia];
$stmtInsert = sqlsrv_query($cnx, $sqlInsert, $params);

if ($stmtInsert) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo insertar']);
}
