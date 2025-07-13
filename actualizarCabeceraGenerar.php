<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$iCodTramite = $_POST['iCodTramite'] ?? null;
$tipoDocumento = $_POST['tipoDocumento'] ?? null;
$correlativo = $_POST['correlativo'] ?? null;
$asunto = $_POST['asunto'] ?? null;
$observaciones = $_POST['observaciones'] ?? null;
$folios = $_POST['folios'] ?? 1;
$destinos = $_POST['destinos'] ?? [];

if (!$iCodTramite || !$tipoDocumento || !$correlativo) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
    exit;
}

sqlsrv_begin_transaction($cnx);

try {
    // Actualizar cabecera del tramite
    $sqlUpdate = "UPDATE Tra_M_Tramite 
                  SET cCodTipoDoc = ?, 
                      cCodificacion = ?,
                      cAsunto = ?, 
                      cObservaciones = ?,
                      nNumFolio = ?,
                      extension = 1
                  WHERE iCodTramite = ?";
    $paramsUpdate = [$tipoDocumento, $correlativo, $asunto, $observaciones, $folios, $iCodTramite];
    $stmt = sqlsrv_query($cnx, $sqlUpdate, $paramsUpdate);

    if (!$stmt) {
        throw new Exception("Error al actualizar la cabecera.");
    }

        // Obtener expediente del trámite
        $sqlExp = "SELECT expediente FROM Tra_M_Tramite WHERE iCodTramite = ?";
        $stmtExp = sqlsrv_query($cnx, $sqlExp, [$iCodTramite]);
        $rowExp = sqlsrv_fetch_array($stmtExp, SQLSRV_FETCH_ASSOC);
        $expediente = $rowExp['expediente'] ?? '';

    // Eliminar destinos anteriores
    $sqlDeleteDestinos = "DELETE FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ?";
    $stmtDel = sqlsrv_query($cnx, $sqlDeleteDestinos, [$iCodTramite]);
    if (!$stmtDel) {
        throw new Exception("Error al eliminar destinos anteriores.");
    }
    error_log(" Destinos[] recibidos:");
    // Insertar destinos nuevos
    foreach ($destinos as $dest) {
        error_log("🔹 $dest");
        [$oficina, $trabajador, $indicacion, $prioridad, $copia] = explode('_', $dest);
        $tipoMov = ($copia == '1') ? 4 : 1; // 4: Copia, 1: Normal

        $nTiempoRespuesta = 3; // media por defecto
        if (strtolower($prioridad) === 'alta') {
            $nTiempoRespuesta = 1;
        } elseif (strtolower($prioridad) === 'baja') {
            $nTiempoRespuesta = 5;
        }

        $sqlInsertMov = "INSERT INTO Tra_M_Tramite_Movimientos 
                           (iCodTramite, iCodOficinaDerivar, iCodTrabajadorDerivar, 
                           iCodIndicacionDerivar, cPrioridadDerivar, cFlgTipoMovimiento, 
                           iCodOficinaOrigen, expediente, extension, nTiempoRespuesta, nestadomovimiento)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";

        $paramsInsert = [
            $iCodTramite, 
            $oficina, 
            $trabajador, 
            $indicacion, 
            $prioridad, 
            $tipoMov, 
            $_SESSION['iCodOficinaLogin'],
            $expediente,
            1,
            $nTiempoRespuesta,
            0
        ];
        $stmtInsert = sqlsrv_query($cnx, $sqlInsertMov, $paramsInsert);
        if (!$stmtInsert) {
            throw new Exception("Error al insertar destino nuevo.");
        }
    }

    sqlsrv_commit($cnx);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    sqlsrv_rollback($cnx);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
