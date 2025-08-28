<?php
// Redirigir logs a un archivo específico
ini_set("log_errors", 1);
ini_set("error_log", $_SERVER["DOCUMENT_ROOT"] . "/SGD/error_correlativo.log");
include_once("conexion/conexion.php");
session_start();
global $cnx;

error_log("== obtenerCorrelativo.php iniciado ==");

 
$tipoDocumento = $_POST['tipoDocumento'] ?? $_GET['cCodTipoDoc'] ?? null;
$anio = date("Y");
$oficina = $_SESSION['iCodOficinaLogin'];
error_log("Tipo de documento: " . $tipoDocumento);
error_log("Año: " . $anio);
error_log("Oficina: " . $oficina);

if (!$tipoDocumento) {
    error_log("Error: Tipo de documento no proporcionado");
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
    error_log("Correlativo encontrado: " . $row['nCorrelativo']);
    echo json_encode([
        'status' => 'success', 
        'correlativo' => str_pad($row['nCorrelativo'], 5, "0", STR_PAD_LEFT) . '-' . $anio . '/' . $row["cSiglaOficina"]
     ]);
     exit();
    }
    
    error_log("No existe correlativo, se procederá a crear uno nuevo");

    // Si no existe, crear uno nuevo con nCorrelativo = 1
    // Insertar nuevo correlativo
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
            error_log("Nuevo correlativo creado correctamente");
            echo json_encode([
                'status' => 'success',
                'correlativo' => str_pad(1, 5, "0", STR_PAD_LEFT) . '-' . $anio . '/' . $row2["cSiglaOficina"]
            ]);
            exit();
        } else {
            error_log("Error: No se pudo obtener sigla de oficina tras insertar");
            echo json_encode(['status' => 'error', 'message' => 'Correlativo creado, pero no se pudo obtener sigla de oficina.']);
            exit();
        }
    } else {
        error_log("Error al insertar nuevo correlativo: " . print_r(sqlsrv_errors(), true));
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar correlativo nuevo.']);
        exit();
    }
