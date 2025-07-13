<?php
include("conexion/conexion.php");
header("Content-Type: application/json");

$iCodMovimiento = intval($_POST['iCodMovimiento'] ?? 0);
if (!$iCodMovimiento) {
    echo json_encode(['status' => 'error', 'message' => 'Movimiento no válido']);
    exit;
}

// Obtener documento final para eliminar
$sqlGet = "SELECT cDocumentoFinalizacion FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
$stmtGet = sqlsrv_query($cnx, $sqlGet, [$iCodMovimiento]);
$row = sqlsrv_fetch_array($stmtGet, SQLSRV_FETCH_ASSOC);
$archivo = $row['cDocumentoFinalizacion'] ?? null;

if ($archivo) {
    $ruta = __DIR__ . "/cAlmacenArchivos/" . $archivo;
    if (file_exists($ruta)) {
        unlink($ruta);
    }
}

// Revertir estado y eliminar nombre del documento
$sqlUpd = "UPDATE Tra_M_Tramite_Movimientos
           SET nEstadoMovimiento = 1,
               cDocumentoFinalizacion = NULL,
               cObservacionesFinalizar = NULL,
               fFecFinalizar = NULL,
               iCodTrabajadorFinalizar = NULL
           WHERE iCodMovimiento = ?";
$stmtUpd = sqlsrv_query($cnx, $sqlUpd, [$iCodMovimiento]);

if ($stmtUpd) {
    echo json_encode(['status' => 'ok']);
} else {
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'error', 'message' => $error[0]['message'] ?? 'Error en base de datos']);
}
