<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

// Validaciones
$iCodTramite     = $_POST['iCodTramite'] ?? null;
$iCodMovimiento  = $_POST['iCodMovimiento'] ?? null;
$pedidos         = $_POST['itemsSeleccionados'] ?? [];

if (!$iCodTramite || !$iCodMovimiento || count($pedidos) === 0) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos o no se seleccionaron ítems.']);
    exit;
}

// Obtener última extensión registrada (debe iniciar en 1 como base)
$sqlExt = "SELECT ISNULL(MAX(nro_extension), 1) AS ultimaExt FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
$stmtExt = sqlsrv_query($cnx, $sqlExt, [$iCodTramite]);
$rowExt = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC);
$ultimaExt = intval($rowExt['ultimaExt'] ?? 1);

// Obtener el movimiento original base
$sqlMov = "SELECT * FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
$stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodMovimiento]);
$mov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC);

if (!$mov) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el movimiento original.']);
    exit;
}

$generadas = 0;

foreach ($pedidos as $idSIGAPedido) {
    // Generar nuevo número de extensión
    $nuevaExtension = $ultimaExt + 1;
    $ultimaExt = $nuevaExtension;

    // 1. Insertar copia del movimiento con nuevo campo extension
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
            'message' => "Error al insertar movimiento con extensión #$nuevaExtension.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }

    // Obtener el ID del nuevo movimiento generado
    $sqlNewId = "SELECT SCOPE_IDENTITY() AS newId";
    $stmtNewId = sqlsrv_query($cnx, $sqlNewId);
    $rowNewId = sqlsrv_fetch_array($stmtNewId, SQLSRV_FETCH_ASSOC);
    $iCodMovimientoNuevo = $rowNewId['newId'];

    // 2. Insertar en tabla de extensiones con vínculo al pedido SIGA
    $sqlLog = "INSERT INTO Tra_M_Tramite_Extension (
        iCodTramite, iCodMovimientoorigen, nro_extension, iCodTramiteSIGAPedido
    ) VALUES (?, ?, ?, ?)";

    $stmtLog = sqlsrv_query($cnx, $sqlLog, [$iCodTramite, $iCodMovimientoNuevo, $nuevaExtension, $idSIGAPedido]);
    if (!$stmtLog) {
        echo json_encode([
            'success' => false,
            'message' => "Extensión #$nuevaExtension creada, pero error al registrar vínculo con ítem SIGA.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }

    $generadas++;
}

echo json_encode([
    'success' => true,
    'message' => "Se generaron correctamente $generadas extensiones por ítem SIGA."
]);
