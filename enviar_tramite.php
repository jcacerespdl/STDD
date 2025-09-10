<?php
include_once("conexion/conexion.php");
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$iCodTramite = $input['iCodTramite'] ?? null;

if (!$iCodTramite) {
    echo json_encode(["status" => "error", "message" => "Código de trámite no recibido"]);
    exit;
}

$fechaActual = date("Y-m-d H:i:s");

/* 0) Cerrar redacción del cuerpo siempre */
$sqlTramite = "UPDATE Tra_M_Tramite SET fFecRegistro = ?, nFlgEstado = 1 WHERE iCodTramite = ?";
if (!sqlsrv_query($cnx, $sqlTramite, [$fechaActual, $iCodTramite])) {
    echo json_encode(["status" => "error", "message" => "No se pudo actualizar fFecRegistro/nFlgEstado"]);
    exit;
}

/* 1) Leer tipo y V°B° principal */
$stmtTipo = sqlsrv_query($cnx, "SELECT cCodTipoDoc FROM Tra_M_Tramite WHERE iCodTramite = ?", [$iCodTramite]);
$rowTipo  = $stmtTipo ? sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC) : null;
$esProveido = ((string)($rowTipo['cCodTipoDoc'] ?? '') === '97');

$stmtVB = sqlsrv_query($cnx, "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma WHERE iCodTramite = ? AND iCodDigital IS NULL", [$iCodTramite]);
$rowVB  = $stmtVB ? sqlsrv_fetch_array($stmtVB, SQLSRV_FETCH_ASSOC) : null;
$tieneVB = ((int)($rowVB['total'] ?? 0) > 0);

/* Helper: recalcular plazos */
function recalcularPlazos($cnx, $iCodTramite) {
    $stmt = sqlsrv_query($cnx, "SELECT iCodMovimiento, fFecDerivar, nTiempoRespuesta FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ?", [$iCodTramite]);
    if ($stmt) {
        while ($m = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $fFecDerivar = $m['fFecDerivar'];
            if ($fFecDerivar instanceof DateTime) {
                $plazo = clone $fFecDerivar;
                $plazo->modify('+' . (int)$m['nTiempoRespuesta'] . ' days');
                $plazo->setTime((int)$fFecDerivar->format('H'), (int)$fFecDerivar->format('i'), (int)$fFecDerivar->format('s'));
                sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Movimientos SET fFecPlazo = ? WHERE iCodMovimiento = ?", [$plazo->format('Y-m-d H:i:s'), $m['iCodMovimiento']]);
            }
        }
    }
}

/* 2) Reglas: Proveído => directo; lo demás => Por Aprobar */
if ($esProveido) {
    // Directo
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 1 WHERE iCodTramite = ?", [$iCodTramite]);
    // Sellar envío en movimientos si faltaba y alinear nflgenvio
    sqlsrv_query(
        $cnx,
        "UPDATE Tra_M_Tramite_Movimientos
            SET fFecDerivar = ?, nflgenvio = 1
          WHERE iCodTramite = ?
            AND (fFecDerivar IS NULL OR nflgenvio <> 1)",
        [$fechaActual, $iCodTramite]
    );
    recalcularPlazos($cnx, $iCodTramite);
    echo json_encode(["status" => "success", "destino" => "directo", "rule" => "PROVEIDO"]);
    exit;
}

// No-Proveído (tenga o no V°B°) => Por Aprobar
sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 0 WHERE iCodTramite = ?", [$iCodTramite]);
// Alinear nflgenvio con “Por Aprobar” para no disparar el envío
sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Movimientos SET nflgenvio = 0 WHERE iCodTramite = ?", [$iCodTramite]);

echo json_encode(["status" => "success", "destino" => "por_aprobar", "rule" => ($tieneVB ? "TIENE_VB" : "NO_PROVEIDO")]);
exit;
