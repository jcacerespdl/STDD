<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

    $iCodTramiteDerivar = $_POST['iCodTramite'] ?? null;
    $iCodMovimientoDerivo = $_POST['iCodMovimientoDerivo'] ?? null;
    $tipoDocumento = $_POST['tipoDocumento'] ?? null;
    $correlativo = $_POST['correlativo'] ?? null;
    $asunto = $_POST['asunto'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    $folios = $_POST['folios'] ?? 1;
    $destinos = $_POST['destinos'] ?? [];

    if (!$iCodTramiteDerivar || !$tipoDocumento || !$correlativo) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }
    
    sqlsrv_begin_transaction($cnx);
    
    try {
               // ─────────────────────────────────────────────────────────────
    // Obtener iCodTramite raíz
    // ─────────────────────────────────────────────────────────────
    $sqlRaiz = "SELECT TOP 1 iCodTramite FROM Tra_M_Tramite_Movimientos WHERE iCodTramiteDerivar = ?";
    $stmtRaiz = sqlsrv_query($cnx, $sqlRaiz, [$iCodTramiteDerivar]);
    $rowRaiz = $stmtRaiz ? sqlsrv_fetch_array($stmtRaiz, SQLSRV_FETCH_ASSOC) : null;
    $iCodTramiteRaiz = $rowRaiz['iCodTramite'] ?? null;

    if (!$iCodTramiteRaiz) {
        throw new Exception("No se pudo determinar el trámite raíz.");
    }

    // ─────────────────────────────────────────────────────────────
    // Actualizar cabecera del trámite derivado
    // ─────────────────────────────────────────────────────────────
    $sqlUpdate = "UPDATE Tra_M_Tramite 
    SET cCodTipoDoc = ?, 
        cCodificacion = ?, 
        cAsunto = ?, 
        cObservaciones = ?, 
        nNumFolio = ?
        WHERE iCodTramite = ?";
    $paramsUpdate = [$tipoDocumento, $correlativo, $asunto, $observaciones, $folios, $iCodTramiteDerivar];
    $stmt = sqlsrv_query($cnx, $sqlUpdate, $paramsUpdate);

    if (!$stmt) {
        throw new Exception("Error al actualizar la cabecera.");    
    }

  // Obtener expediente y extensión desde movimiento anterior
  $sqlPrev = "SELECT expediente, extension FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
  $stmtPrev = sqlsrv_query($cnx, $sqlPrev, [$iCodMovimientoDerivo]);
  $rowPrev = sqlsrv_fetch_array($stmtPrev, SQLSRV_FETCH_ASSOC);
  $expediente = $rowPrev['expediente'] ?? '';
  $extension = $rowPrev['extension'] ?? 1;

     // Eliminar movimientos anteriores
    $sqlDeleteDestinos = "DELETE FROM Tra_M_Tramite_Movimientos WHERE iCodTramiteDerivar = ?";
    $stmtDel = sqlsrv_query($cnx, $sqlDeleteDestinos, [$iCodTramiteDerivar]);
    if (!$stmtDel) {
        throw new Exception("Error al eliminar destinos anteriores.");
    }
    
 // Insertar nuevos movimientos
    foreach ($destinos as $dest) {
        [$oficina, $trabajador, $indicacion, $prioridad, $copia] = explode('_', $dest);
        $tipoMov = ($copia == '1') ? 4 : 1;

        $nTiempoRespuesta = 3;
        if (strtolower($prioridad) === 'alta') {
            $nTiempoRespuesta = 1;
        } elseif (strtolower($prioridad) === 'baja') {
            $nTiempoRespuesta = 5;
        }

        $sqlInsertMov = "INSERT INTO Tra_M_Tramite_Movimientos
         (iCodTramite, 
         iCodTramiteDerivar, 
         iCodOficinaDerivar, iCodTrabajadorDerivar,
         iCodIndicacionDerivar, cPrioridadDerivar,
         cFlgTipoMovimiento, 
         iCodOficinaOrigen,
         iCodMovimientoDerivo,
         expediente, extension, nTiempoRespuesta, nEstadoMovimiento)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)";
        $paramsInsert = [
            $iCodTramiteRaiz,
            $iCodTramiteDerivar,
               $oficina,
            $trabajador,
            $indicacion,
            $prioridad,
            $tipoMov,
            $_SESSION['iCodOficinaLogin'],
            $iCodMovimientoDerivo,
            $expediente,
            $extension,
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
