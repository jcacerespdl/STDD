<?php
include_once("conexion/conexion.php");
session_start();
header('Content-Type: application/json');

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$iCodTramite = $in['iCodTramite'] ?? null;

if (!$iCodTramite) {
  echo json_encode(["status"=>"error","message"=>"iCodTramite requerido"]);
  exit;
}

/* 1) Leer tipo de documento */
$stmtTipo = sqlsrv_query($cnx,
  "SELECT cCodTipoDoc FROM Tra_M_Tramite WHERE iCodTramite = ?",
  [$iCodTramite]
);
if ($stmtTipo === false) {
  echo json_encode(["status"=>"error","message"=>"Error al leer tipo de doc"]);
  exit;
}
$rowTipo     = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
$cCodTipoDoc = (string)($rowTipo['cCodTipoDoc'] ?? '');
$esProveido  = ($cCodTipoDoc === '97'); // Proveído

/* 2) Contar V°B° del principal (iCodDigital IS NULL) */
$stmtVB = sqlsrv_query($cnx,
  "SELECT COUNT(*) AS total
     FROM Tra_M_Tramite_Firma
    WHERE iCodTramite = ?
      AND iCodDigital IS NULL",
  [$iCodTramite]
);
if ($stmtVB === false) {
  echo json_encode(["status"=>"error","message"=>"Error al contar V°B°"]);
  exit;
}
$rowVB    = sqlsrv_fetch_array($stmtVB, SQLSRV_FETCH_ASSOC);
$tieneVB  = ((int)($rowVB['total'] ?? 0) > 0);

/* 3) Regla: 
      - Proveído y SIN V°B° => nFlgEnvio = 1
      - En cualquier otro caso => nFlgEnvio = 0
*/
$nFlgEnvio = ($esProveido && !$tieneVB) ? 1 : 0;

$ok = sqlsrv_query($cnx,
  "UPDATE Tra_M_Tramite SET nFlgEnvio = ? WHERE iCodTramite = ?",
  [$nFlgEnvio, $iCodTramite]
);
if ($ok === false) {
  echo json_encode(["status"=>"error","message"=>"No se pudo actualizar nFlgEnvio"]);
  exit;
}

echo json_encode([
  "status"     => "success",
  "nFlgEnvio"  => $nFlgEnvio,
  "esProveido" => $esProveido,
  "tieneVB"    => $tieneVB
]);
