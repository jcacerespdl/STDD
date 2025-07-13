<?php
include("conexion/conexion.php");
header("Content-Type: application/json");

$iCodTramite = $_POST['iCodTramite'] ?? null;
$nFlgTipoDerivo = isset($_POST['nFlgTipoDerivo']) ? intval($_POST['nFlgTipoDerivo']) : -1;

if (!$iCodTramite || ($nFlgTipoDerivo !== 0 && $nFlgTipoDerivo !== 1)) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos o inválidos']);
    exit;
}

try {
    if ($nFlgTipoDerivo === 0) {
        // 🔥 Caso generado: eliminar todo el trámite raíz y sus movimientos
        sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ?", [$iCodTramite]);
        sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite WHERE iCodTramite = ?", [$iCodTramite]);

        echo json_encode(['status' => 'ok', 'message' => 'Trámite generado eliminado correctamente.']);
        exit;
    }

    // 🔁 Caso derivado: buscar el movimiento que generó este trámite derivado
    $sql = "SELECT TOP 1 iCodMovimiento, iCodMovimientoDerivo 
            FROM Tra_M_Tramite_Movimientos 
            WHERE iCodTramiteDerivar = ?";
    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
    $mov = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$mov) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró el movimiento que generó el derivado']);
        exit;
    }

    $iCodMovimientoDerivado = $mov['iCodMovimiento'];
    $iCodMovimientoAnterior = $mov['iCodMovimientoDerivo'];

    // 🔥 Eliminar el trámite derivado
    sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?", [$iCodMovimientoDerivado]);
    sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite WHERE iCodTramite = ?", [$iCodTramite]);

    // 🔁 Restaurar movimiento anterior como pendiente
    if ($iCodMovimientoAnterior) {
        sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Movimientos SET nEstadoMovimiento = 1 WHERE iCodMovimiento = ?", [$iCodMovimientoAnterior]);
    }

    echo json_encode(['status' => 'ok', 'message' => 'Trámite derivado eliminado y movimiento anterior restaurado.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de servidor: ' . $e->getMessage()]);
}
