<?php
header('Content-Type: application/json');
include_once("conexion/conexion.php");
session_start();
global $cnx;
date_default_timezone_set('America/Lima');

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $iCodTramite = $input["iCodTramite"] ?? null;

    if (!$iCodTramite) {
        throw new Exception("Código de trámite no proporcionado.");
    }

    $iCodPerfil = $_SESSION['iCodPerfilLogin'] ?? null;
    $fechaActual = date("Y-m-d H:i:s");

    // 1. Actualizar estado del trámite base (Tra_M_Tramite)
    $sqlTramite = "UPDATE Tra_M_Tramite SET fFecRegistro = ?, nFlgEstado = 1 WHERE iCodTramite = ?";
    $stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$fechaActual, $iCodTramite]);

    if ($stmtTramite === false) {
        throw new Exception("Error al actualizar fFecRegistro y nFlgEstado.");
    }

    // 2. Si es jefe, actualizar envío y fecha de derivación en movimientos
    if ($iCodPerfil == 3) {
        // nFlgEnvio y fFecDerivar
    $sqlMov = "UPDATE Tra_M_Tramite_Movimientos 
               SET nFlgEnvio = 1, fFecDerivar = ?
               WHERE iCodTramiteDerivar = ?";
     $stmtMov = sqlsrv_query($cnx, $sqlMov, [$fechaActual, $iCodTramite]);

     if ($stmtMov === false) {
         throw new Exception("Error al actualizar nFlgEnvio y fFecDerivar.");
     }

     // 3. Actualizar fFecPlazo en base a nTiempoRespuesta
     $sqlMovimientos = "
         SELECT iCodMovimiento, fFecDerivar, nTiempoRespuesta
         FROM Tra_M_Tramite_Movimientos
         WHERE iCodTramiteDerivar = ?";
     $stmtMovimientos = sqlsrv_query($cnx, $sqlMovimientos, [$iCodTramite]);

     if ($stmtMovimientos !== false) {
         while ($mov = sqlsrv_fetch_array($stmtMovimientos, SQLSRV_FETCH_ASSOC)) {
             $iCodMovimiento = $mov['iCodMovimiento'];
             $fFecDerivar = $mov['fFecDerivar'];
             $nTiempoRespuesta = intval($mov['nTiempoRespuesta']);

             if ($fFecDerivar instanceof DateTime) {
                 $fFecPlazo = clone $fFecDerivar;
                 $fFecPlazo->modify("+{$nTiempoRespuesta} days");
                 $fFecPlazo->setTime(
                     (int)$fFecDerivar->format('H'),
                     (int)$fFecDerivar->format('i'),
                     (int)$fFecDerivar->format('s')
                 );

                 $sqlUpdatePlazo = "UPDATE Tra_M_Tramite_Movimientos SET fFecPlazo = ? WHERE iCodMovimiento = ?";
                 sqlsrv_query($cnx, $sqlUpdatePlazo, [$fFecPlazo->format('Y-m-d H:i:s'), $iCodMovimiento]);
             }
         }
     }
 }

 echo json_encode(["status" => "success", "message" => "Trámite derivado correctamente."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
