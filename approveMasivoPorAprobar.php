<?php
// approveMasivoPorAprobar.php
session_start();
header('Content-Type: application/json');
include_once("conexion/conexion.php");
global $cnx;

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo json_encode(["status"=>"error","message"=>"Sesión inválida"]);
    exit;
}

// (Opcional) Restringe por perfil/rol
$iCodPerfil = (int)($_SESSION['ID_PERFIL'] ?? 0);
if ($iCodPerfil !== 1) { // ← ajusta según tu seguridad
    echo json_encode(["status"=>"error","message"=>"No autorizado para aprobar sin FirmaPerú"]);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
$ids = $payload['tramites'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(["status"=>"error","message"=>"Sin IDs"]);
    exit;
}

// Sanitiza a enteros únicos
$ids = array_values(array_unique(array_map('intval', $ids)));

// Actualiza de a uno para evitar IN dinámico
$ok = 0;
foreach ($ids as $id) {
    $stmt = sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET nFlgEnvio = 1 WHERE iCodTramite = ?", [$id]);
    if ($stmt) {
        $ok++;
        // (Opcional) Marca fecha de derivación si aplica a tu flujo
        // sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Movimientos SET fFecDerivar = ISNULL(fFecDerivar, GETDATE()) WHERE iCodTramite = ? AND nFlgEnvio IS NULL", [$id]);
    }
}

echo json_encode(["status"=>"ok","actualizados"=>$ok]);
