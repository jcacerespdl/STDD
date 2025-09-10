<?php
include_once("conexion/conexion.php");
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$iCodTramite         = $input['iCodTramite']        ?? null;
$iCodOficinaSession  = $_SESSION['iCodOficinaLogin'] ?? null;
$iCodPerfil          = isset($_SESSION['iCodPerfilLogin']) ? (int)$_SESSION['iCodPerfilLogin'] : null;

if (!$iCodTramite || !$iCodOficinaSession) {
    echo json_encode(["status" => "error", "message" => "Código de trámite u oficina no recibido"]);
    exit;
}

$fechaActual = date("Y-m-d H:i:s");

/* --------------------------------------------------------------------------
   0) Cerrar redacción del cuerpo siempre
--------------------------------------------------------------------------- */
$sqlTramite = "
    UPDATE Tra_M_Tramite 
       SET fFecRegistro = ?, 
           nFlgEstado   = 1       
     WHERE iCodTramite  = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$fechaActual, $iCodTramite]);
if ($stmtTramite === false) {
    echo json_encode(["status" => "error", "message" => "Error al actualizar fFecRegistro/nFlgEstado."]);
    exit;
}

/* --------------------------------------------------------------------------
   1) Leer tipo de documento (para PROVEÍDO = 97) y si tiene V°B° del principal
--------------------------------------------------------------------------- */
$tipoRow = null;
$stmtTipo = sqlsrv_query(
    $cnx,
    "SELECT cCodTipoDoc FROM Tra_M_Tramite WHERE iCodTramite = ?",
    [$iCodTramite]
);
if ($stmtTipo) $tipoRow = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
$cCodTipoDoc = (string)($tipoRow['cCodTipoDoc'] ?? '');
$esProveido  = ($cCodTipoDoc === '97'); // PROVEÍDO

$vbRow = null;
$stmtVB = sqlsrv_query(
    $cnx,
    "SELECT COUNT(*) AS total 
       FROM Tra_M_Tramite_Firma 
      WHERE iCodTramite = ? 
        AND iCodDigital  IS NULL",      // V°B° del principal
    [$iCodTramite]
);
$totalVB = 0;
if ($stmtVB) {
    $vbRow   = sqlsrv_fetch_array($stmtVB, SQLSRV_FETCH_ASSOC);
    $totalVB = (int)($vbRow['total'] ?? 0);
}
$tieneVB = ($totalVB > 0);

/* --------------------------------------------------------------------------
   2) Reglas de negocio:
      A) PROVEÍDO  => se manda directo SIEMPRE (sin "por aprobar")
      B) Tiene V°B° => queda "por aprobar" (NO enviar)
      C) Resto:
         - Jefe (perfil 3)  => enviar directo
         - Asist/Profes/otros => "por aprobar"
--------------------------------------------------------------------------- */

/* Helper para actualizar plazos a partir de fFecDerivar */
function recalcularPlazos($cnx, $iCodTramite) {
    $stmtMovs = sqlsrv_query($cnx, "
        SELECT iCodMovimiento, fFecDerivar, nTiempoRespuesta
          FROM Tra_M_Tramite_Movimientos
         WHERE iCodTramite = ?", 
        [$iCodTramite]
    );
    if ($stmtMovs) {
        while ($mov = sqlsrv_fetch_array($stmtMovs, SQLSRV_FETCH_ASSOC)) {
            $iCodMovimiento    = $mov['iCodMovimiento'];
            $fFecDerivar       = $mov['fFecDerivar'] ?? null;
            $nTiempoRespuesta  = (int)($mov['nTiempoRespuesta'] ?? 0);

            if ($fFecDerivar instanceof DateTime) {
                // conservar la hora original
                $plazo = clone $fFecDerivar;
                $plazo->modify("+{$nTiempoRespuesta} days");
                $plazo->setTime(
                    (int)$fFecDerivar->format('H'),
                    (int)$fFecDerivar->format('i'),
                    (int)$fFecDerivar->format('s')
                );
                sqlsrv_query(
                    $cnx,
                    "UPDATE Tra_M_Tramite_Movimientos SET fFecPlazo = ? WHERE iCodMovimiento = ?",
                    [$plazo->format('Y-m-d H:i:s'), $iCodMovimiento]
                );
            }
        }
    }
}

/* A) PROVEÍDO => directo */
if ($esProveido) {
    $okEnv = sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 1 WHERE iCodTramite = ?", [$iCodTramite]);
    if ($okEnv === false) {
        echo json_encode(["status" => "error", "message" => "No se pudo marcar nFlgEnvio=1 (PROVEÍDO)."]);
        exit;
    }

    // Fecha de envío sobre movimientos (solo donde aún esté NULL)
    $okMov = sqlsrv_query(
        $cnx,
        "UPDATE Tra_M_Tramite_Movimientos 
            SET fFecDerivar = ? 
          WHERE iCodTramite = ? 
            AND (fFecDerivar IS NULL)",
        [$fechaActual, $iCodTramite]
    );
    if ($okMov === false) {
        echo json_encode(["status" => "error", "message" => "No se pudo actualizar fFecDerivar (PROVEÍDO)."]);
        exit;
    }

    recalcularPlazos($cnx, $iCodTramite);
    echo json_encode(["status" => "success", "destino" => "directo", "rule" => "PROVEÍDO"]);
    exit;
}

/* B) Tiene vistos buenos => por aprobar (no enviar) */
if ($tieneVB) {
    // Asegurar que NO esté marcado como enviado
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 0 WHERE iCodTramite = ?", [$iCodTramite]);
    // No tocamos fFecDerivar para que no “salga” todavía
    echo json_encode(["status" => "success", "destino" => "por_aprobar", "rule" => "TIENE_VB"]);
    exit;
}

/* C) Resto de documentos */
if ($iCodPerfil === 3) { // Jefe => directo
    $okEnv = sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 1 WHERE iCodTramite = ?", [$iCodTramite]);
    if ($okEnv === false) {
        echo json_encode(["status" => "error", "message" => "No se pudo marcar nFlgEnvio=1 (Jefe)."]);
        exit;
    }

    $okMov = sqlsrv_query(
        $cnx,
        "UPDATE Tra_M_Tramite_Movimientos SET fFecDerivar = ? WHERE iCodTramite = ?",
        [$fechaActual, $iCodTramite]
    );
    if ($okMov === false) {
        echo json_encode(["status" => "error", "message" => "Error al actualizar fFecDerivar (Jefe)."]);
        exit;
    }

    recalcularPlazos($cnx, $iCodTramite);
    echo json_encode(["status" => "success", "destino" => "directo", "rule" => "JEFE_SIN_VB"]);
    exit;
}

/* Asistente/Profesional => por aprobar */
sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 0 WHERE iCodTramite = ?", [$iCodTramite]);
echo json_encode(["status" => "success", "destino" => "por_aprobar", "rule" => "OTRO_SIN_VB"]);
