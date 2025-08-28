<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

$iCodTramite    = $_POST['iCodTramite']    ?? null;
$iCodMovimiento = $_POST['iCodMovimiento'] ?? null;
$cantidad       = intval($_POST['cantidad'] ?? 0);

if (!$iCodTramite || !$iCodMovimiento || $cantidad < 1) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o incompletos.']);
    exit;
}

/* (1) Asegurar extensión 1 solo si NO existe.
       Marcamos si la sembramos en esta llamada para poder limpiarla si no se generan extensiones > 1. */
       $iCodTrabajadorSesion = $_SESSION['CODIGO_TRABAJADOR'] ?? null;

       $sqlCheckExt1 = "SELECT COUNT(*) AS cnt FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = 1";
       $stmtCheckExt1 = sqlsrv_query($cnx, $sqlCheckExt1, [$iCodTramite]);
       $rowCheckExt1 = sqlsrv_fetch_array($stmtCheckExt1, SQLSRV_FETCH_ASSOC);
       $hadExt1 = (intval($rowCheckExt1['cnt'] ?? 0) > 0);
       
       $seededExt1Now = false;
       if (!$hadExt1) {
           $sqlEnsureExt1 = "
           INSERT INTO Tra_M_Tramite_Extension
               (iCodTramite, nro_extension, iCodMovimientoOrigen, iCodTrabajadorRegistro, fFecRegistro, observaciones)
           VALUES
               (?, 1, ?, ?, GETDATE(), NULL)";
           $okSeed = sqlsrv_query($cnx, $sqlEnsureExt1, [$iCodTramite, $iCodMovimiento, $iCodTrabajadorSesion]);
           if ($okSeed === false) {
               echo json_encode(['success' => false, 'message' => 'No se pudo inicializar la extensión 1.', 'sqlsrv' => sqlsrv_errors()]);
               exit;
           }
           $seededExt1Now = true;
       }
       
/* (2) Obtener última extensión existente (mínimo 1) */
$sqlExt = "SELECT CASE WHEN MAX(nro_extension) IS NULL THEN 1 ELSE MAX(nro_extension) END AS ultimaExt
           FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
$stmtExt = sqlsrv_query($cnx, $sqlExt, [$iCodTramite]);
$rowExt = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC);
$ultimaExt = intval($rowExt['ultimaExt'] ?? 1);

/* (3) Movimiento base */
$sqlMov = "SELECT * FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
$stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodMovimiento]);
$mov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC);

if (!$mov) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el movimiento base.']);
    exit;
}

$generadas = 0;
$baseExt = max(1, $ultimaExt);

for ($i = 1; $i <= $cantidad; $i++) {
    $nuevaExtension = $baseExt + $i;

    /* Guard extra: jamás crear movimiento con extensión <= 1 */
    if ($nuevaExtension <= 1) {
        continue;
    }

    /* (4) Insertar NUEVO movimiento (copia completa, incluye datos de delegación)  
    y obtener el ID con OUTPUT INSERTED.iCodMovimiento */
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
        nFlgEnvio,
        /* ---- campos de delegación que faltaban ---- */
        fFecDelegado,
        fFecDelegadoRecepcion,
        iCodTrabajadorDelegado,
        iCodIndicacionDelegado,
        cObservacionesDelegado
        )
    OUTPUT INSERTED.iCodMovimiento
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $mov['iCodTramite'],
        $mov['iCodOficinaOrigen'],
        $mov['iCodTrabajadorRegistro'],
        $mov['iCodOficinaDerivar'],
        $mov['iCodTrabajadorDerivar'],
        $mov['cAsuntoDerivar'],
        $mov['cObservacionesDerivar'],
        $mov['cPrioridadDerivar'],
        /* fFecMovimiento = GETDATE() */
        $mov['nEstadoMovimiento'],
        $mov['cFlgTipoMovimiento'],
        $mov['iCodTramiteDerivar'],
        $mov['iCodMovimientoDerivo'],
        $mov['iCodIndicacionDerivar'],
        $mov['EXPEDIENTE'],
        $nuevaExtension,
        $mov['fFecDerivar'],
        $mov['nFlgEnvio'],
        /* ---- delegación ---- */
        $mov['fFecDelegado'],
        $mov['fFecDelegadoRecepcion'],
        $mov['iCodTrabajadorDelegado'],
        $mov['iCodIndicacionDelegado'],
        $mov['cObservacionesDelegado']
    ];

    $stmtInsert = sqlsrv_query($cnx, $sqlInsert, $params);
    if (!$stmtInsert) {
         /* Si no generamos nada y sembramos ext1 ahora, limpiamos */
         if ($seededExt1Now) {
            $sqlOnlyOne = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
            $st = sqlsrv_query($cnx, $sqlOnlyOne, [$iCodTramite]);
            $rowCnt = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
            if (intval($rowCnt['total'] ?? 0) == 1) {
                sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = 1", [$iCodTramite]);
            }
        }
        echo json_encode([
            'success' => false,
            'message' => "Error al crear movimiento de la extensión #$nuevaExtension.",
            'sqlsrv'  => sqlsrv_errors()
        ]);
        exit;
    }

    /* (5) ID del nuevo movimiento obtenido del OUTPUT */
    $rowNewId = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC);
    $iCodMovimientoNuevo = isset($rowNewId['iCodMovimiento']) ? (int)$rowNewId['iCodMovimiento'] : 0;

    /* (6) Registrar en tabla de extensiones (usa iCodMovimientoOrigen correcto) */
    $iCodTrabajadorLog = $iCodTrabajadorSesion ?: ($mov['iCodTrabajadorRegistro'] ?? null);

    $sqlLog = "INSERT INTO Tra_M_Tramite_Extension (
        iCodTramite,
        iCodMovimientoOrigen,
        nro_extension,
        iCodTrabajadorRegistro,
        fFecRegistro,
        observaciones
    ) VALUES (?, ?, ?, ?, GETDATE(), NULL)";

    $stmtLog = sqlsrv_query($cnx, $sqlLog, [
        $iCodTramite,
        $iCodMovimiento,   // <-- AHORA SIEMPRE VIENE DEL OUTPUT
        $nuevaExtension,
        $iCodTrabajadorLog
    ]);

    if (!$stmtLog) {
        /* Si no generamos nada y sembramos ext1 ahora, limpiamos */
        if ($seededExt1Now && $generadas == 0) {
            $sqlOnlyOne = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
            $st = sqlsrv_query($cnx, $sqlOnlyOne, [$iCodTramite]);
            $rowCnt = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
            if (intval($rowCnt['total'] ?? 0) == 1) {
                sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = 1", [$iCodTramite]);
            }
        }
        echo json_encode([
            'success' => false,
            'message' => "Extensión #$nuevaExtension creada, pero error al registrar en Tra_M_Tramite_Extension.",
            'sqlsrv'  => sqlsrv_errors()
        ]);
        exit;
    }

    $generadas++;
}

/* (7) Si NO se generó ninguna extensión > 1 y sembramos ext1 en esta llamada,
       entonces ext1 quedó “inútil”: la eliminamos SOLO si quedó como única. */
       if ($generadas == 0 && $seededExt1Now) {
        $sqlOnlyOne = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Extension WHERE iCodTramite = ?";
        $st = sqlsrv_query($cnx, $sqlOnlyOne, [$iCodTramite]);
        $rowCnt = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
        if (intval($rowCnt['total'] ?? 0) == 1) {
            sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = 1", [$iCodTramite]);
        }
    }

echo json_encode([
    'success' => true,
    'message' => "Se generaron correctamente $generadas extensión(es)."
]);
