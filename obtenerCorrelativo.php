<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo json_encode(['status' => 'error', 'message' => 'No hay sesión de trabajador']);
    exit();
}

// Si no hay CODIGO_OFICINA, lo obtenemos
if (!isset($_SESSION['CODIGO_OFICINA'])) {
    $iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
    $sqlOf = "SELECT TOP 1 iCodOficina FROM Tra_M_Perfil_Ususario WHERE iCodTrabajador = ?";
    $stmtOf = sqlsrv_query($cnx, $sqlOf, [$iCodTrabajador]);
    if ($stmtOf && $of = sqlsrv_fetch_array($stmtOf, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['CODIGO_OFICINA'] = $of['iCodOficina'];
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo obtener oficina del trabajador']);
        exit();
    }
}

$tipoDocumento = $_POST['tipoDocumento'] ?? $_GET['cCodTipoDoc'] ?? null;
$anio = date("Y");
$oficina = $_SESSION['CODIGO_OFICINA'];

if (!$tipoDocumento) {
    echo json_encode(['status' => 'error', 'message' => 'Tipo de documento no proporcionado']);
    exit();
}

// Consulta si ya existe el correlativo
$sql = "SELECT co.nCorrelativo, o.cSiglaOficina 
FROM Tra_M_Correlativo_Oficina co
        JOIN Tra_M_Oficinas o ON co.iCodOficina = o.iCodOficina
        WHERE co.cCodTipoDoc = ? AND co.iCodOficina = ? AND co.nNumAno = ?";
$params = [$tipoDocumento, $oficina, $anio];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Ya existe: devolver correlativo
    echo json_encode([
        'status' => 'success', 
        'correlativo' => str_pad($row['nCorrelativo'], 5, "0", STR_PAD_LEFT) . '-' . $anio . '/' . $row["cSiglaOficina"]
     ]);
     exit();
    }
    
    // Si no existe, crear uno nuevo con nCorrelativo = 1
    $sqlInsert = "INSERT INTO Tra_M_Correlativo_Oficina (cCodTipoDoc, iCodOficina, nNumAno, nCorrelativo)
                  VALUES (?, ?, ?, 1)";
    $paramsInsert = [$tipoDocumento, $oficina, $anio];
    $stmtInsert = sqlsrv_query($cnx, $sqlInsert, $paramsInsert);
    
    // Confirmar y consultar nuevamente para devolver la respuesta formateada
    if ($stmtInsert) {
        $sql = "SELECT 1 AS nCorrelativo, o.cSiglaOficina 
                FROM Tra_M_Oficinas o 
                WHERE o.iCodOficina = ?";
        $stmt2 = sqlsrv_query($cnx, $sql, [$oficina]);
        if ($stmt2 && $row2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            echo json_encode([
                'status' => 'success',
                'correlativo' => str_pad(1, 5, "0", STR_PAD_LEFT) . '-' . $anio . '/' . $row2["cSiglaOficina"]
            ]);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Correlativo creado, pero no se pudo obtener sigla de oficina.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar correlativo nuevo.']);
        exit();
    }
