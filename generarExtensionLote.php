<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

$iCodTramite    = $_POST['iCodTramite'] ?? null;
$iCodMovimiento = $_POST['iCodMovimiento'] ?? null;
$cantidad       = intval($_POST['cantidad'] ?? 0);

if (!$iCodTramite || !$iCodMovimiento || $cantidad < 1) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o incompletos.']);
    exit;
}

// Obtener último número de extensión
$sqlExt = "SELECT CASE WHEN MAX(nro_extension) IS NULL THEN 1 ELSE MAX(nro_extension) END AS ultimaExt
           FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
$stmtExt = sqlsrv_query($cnx, $sqlExt, [$iCodTramite]);
$rowExt = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC);
$ultimaExt = intval($rowExt['ultimaExt'] ?? 1);

// Obtener movimiento base
$sqlMov = "SELECT * FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
$stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodMovimiento]);
$mov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC);

if (!$mov) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el movimiento base.']);
    exit;
}

$generadas = 0;

for ($i = 1; $i <= $cantidad; $i++) {
    $nuevaExtension = $ultimaExt + $i;

    // Insertar nuevo movimiento
    $sqlInsert = "INSERT INTO Tra_M_Tramite_Movimientos (
        iCodTramite,
        iCodOficinaOrigen,
        iCodTrabajadorRegistro,
        iCodOficinaDerivar,
        iCodTrabajadorDerivar,
        cAsuntoDerivar,
        cObservacionesDerivar,
        cPrioridadDerivar,
        fFecMovimiento,
        nEstadoMovimiento,
        cFlgTipoMovimiento,
        iCodTramiteDerivar,
        iCodMovimientoDerivo,
        iCodIndicacionDerivar,
        EXPEDIENTE,
        extension,
        fFecDerivar,
        nFlgEnvio
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $mov['iCodTramite'],
        $mov['iCodOficinaOrigen'],
        $mov['iCodTrabajadorRegistro'],
        $mov['iCodOficinaDerivar'],
        $mov['iCodTrabajadorDerivar'],
        $mov['cAsuntoDerivar'],
        $mov['cObservacionesDerivar'],
        $mov['cPrioridadDerivar'],
        $mov['nEstadoMovimiento'],
        $mov['cFlgTipoMovimiento'],
        $mov['iCodTramiteDerivar'],
        $mov['iCodMovimientoDerivo'],
        $mov['iCodIndicacionDerivar'],
        $mov['EXPEDIENTE'],
        $nuevaExtension,
        $mov['fFecDerivar'],
        $mov['nFlgEnvio']
    ];

    $stmtInsert = sqlsrv_query($cnx, $sqlInsert, $params);
    if (!$stmtInsert) {
        echo json_encode([
            'success' => false,
            'message' => "Error al crear movimiento de la extensión #$nuevaExtension.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }

    // Obtener nuevo iCodMovimiento
    $stmtNewId = sqlsrv_query($cnx, "SELECT SCOPE_IDENTITY() AS newId");
    $rowNewId = sqlsrv_fetch_array($stmtNewId, SQLSRV_FETCH_ASSOC);
    $iCodMovimientoNuevo = $rowNewId['newId'];

    // Registrar en tabla de extensiones
    $sqlLog = "INSERT INTO Tra_M_Tramite_Extension (
        iCodTramite, iCodMovimientoOrigen, nro_extension, observaciones
    ) VALUES (?, ?, ?, NULL)";

    $stmtLog = sqlsrv_query($cnx, $sqlLog, [$iCodTramite, $iCodMovimientoNuevo, $nuevaExtension]);
    if (!$stmtLog) {
        echo json_encode([
            'success' => false,
            'message' => "Extensión #$nuevaExtension creada, pero error al registrar en tabla de extensiones.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }

    $generadas++;
}

echo json_encode([
    'success' => true,
    'message' => "Se generaron correctamente $generadas extensión(es)."
]);
