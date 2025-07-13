<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

$iCodTramite = $_POST['iCodTramite'] ?? null;
$observaciones = $_POST['observaciones'] ?? [];
$asignaciones = $_POST['asignaciones'] ?? [];

if (!$iCodTramite || empty($asignaciones)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

// Paso 1: Actualizar observaciones por extensión
foreach ($observaciones as $nro_extension => $obs) {
    $sqlObs = "UPDATE Tra_M_Tramite_Extension
               SET observaciones = ?
               WHERE iCodTramite = ? AND nro_extension = ?";
    $stmtObs = sqlsrv_query($cnx, $sqlObs, [$obs, $iCodTramite, $nro_extension]);

    if (!$stmtObs) {
        echo json_encode([
            'success' => false,
            'message' => "Error al guardar observación de extensión $nro_extension.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }
}

// Paso 2: Asignar ítems SIGA a extensiones (colocando en campo "extension" de SIGA)
foreach ($asignaciones as $idSIGA => $nro_extension) {
    // Validar que la extensión exista
    $sqlCheck = "SELECT 1 FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = ?";
    $stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $nro_extension]);
    if (!sqlsrv_fetch_array($stmtCheck)) {
        echo json_encode([
            'success' => false,
            'message' => "Extensión #$nro_extension no existe. Verifique las asignaciones."
        ]);
        exit;
    }

    $sqlUpdate = "UPDATE Tra_M_Tramite_SIGA_Pedido
                  SET extension = ?
                  WHERE iCodTramiteSIGAPedido = ?";
    $stmtUpdate = sqlsrv_query($cnx, $sqlUpdate, [$nro_extension, $idSIGA]);

    if (!$stmtUpdate) {
        echo json_encode([
            'success' => false,
            'message' => "Error al asignar ítem SIGA $idSIGA a extensión $nro_extension.",
            'sqlsrv' => sqlsrv_errors()
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'message' => "Asignaciones y observaciones guardadas correctamente."
]);
