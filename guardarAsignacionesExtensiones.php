<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

header('Content-Type: application/json');

$iCodTramite   = $_POST['iCodTramite']   ?? null;
$observaciones = $_POST['observaciones']  ?? [];
$asignaciones  = $_POST['asignaciones']   ?? [];

if (!$iCodTramite) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (iCodTramite).']);
    exit;
}

$iCodOficina = $_SESSION['iCodOficinaLogin'] ?? null;
$esLogistica = ($iCodOficina == 112);

// --- Paso 1: Actualizar observaciones por extensión (incluye la 1) ---
if (!empty($observaciones)) {
    foreach ($observaciones as $nro_extension => $obs) {
        $sqlObs = "UPDATE Tra_M_Tramite_Extension
                   SET observaciones = ?
                   WHERE iCodTramite = ? AND nro_extension = ?";
        $stmtObs = sqlsrv_query($cnx, $sqlObs, [$obs, $iCodTramite, $nro_extension]);

        if (!$stmtObs) {
            echo json_encode([
                'success' => false,
                'message' => "Error al guardar observación de la extensión $nro_extension.",
                'sqlsrv'  => sqlsrv_errors()
            ]);
            exit;
        }
    }
}

// --- Paso 2: (Solo Logística) Asignar ítems SIGA a extensiones ---
if (!empty($asignaciones)) {
    if (!$esLogistica) {
        echo json_encode([
            'success' => false,
            'message' => 'Solo la oficina 112 puede reasignar ítems SIGA por extensión.'
        ]);
        exit;
    }

    // 2.a Validar que TODAS las extensiones utilizadas existan (incluye la 1)
    $extsUsadas = array_unique(array_map('intval', array_values($asignaciones)));
    foreach ($extsUsadas as $extN) {
        $sqlCheck = "SELECT 1 FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = ?";
        $stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $extN]);
        if (!sqlsrv_fetch_array($stmtCheck)) {
            echo json_encode([
                'success' => false,
                'message' => "Extensión #$extN no existe para este trámite. Verifique las asignaciones."
            ]);
            exit;
        }
    }

    // 2.b Validación dura por pedido_siga: un pedido -> una sola extensión
    $ids = array_map('intval', array_keys($asignaciones));
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $sqlPedidos = "SELECT iCodTramiteSIGAPedido, pedido_siga
                   FROM Tra_M_Tramite_SIGA_Pedido
                   WHERE iCodTramiteSIGAPedido IN ($in)";
    $stmtPedidos = sqlsrv_query($cnx, $sqlPedidos, $ids);

    $pedidoExt = []; // pedido_siga => nro_extension elegido
    while ($r = sqlsrv_fetch_array($stmtPedidos, SQLSRV_FETCH_ASSOC)) {
        $pedido = $r['pedido_siga'];
        $idSIGA = (int)$r['iCodTramiteSIGAPedido'];
        $extSel = (int)$asignaciones[$idSIGA];

        if ($pedido !== null && $pedido !== '') {
            if (!isset($pedidoExt[$pedido])) {
                $pedidoExt[$pedido] = $extSel;
            } else if ($pedidoExt[$pedido] !== $extSel) {
                echo json_encode([
                    'success' => false,
                    'message' => "Todos los ítems del pedido SIGA [$pedido] deben ir a la MISMA extensión."
                ]);
                exit;
            }
        }
    }

    // 2.c Actualizar SIGA: set extension = nro_extension para cada ítem
    foreach ($asignaciones as $idSIGA => $nro_extension) {
        $sqlUpdate = "UPDATE Tra_M_Tramite_SIGA_Pedido
                      SET extension = ?
                      WHERE iCodTramiteSIGAPedido = ?";
        $stmtUpdate = sqlsrv_query($cnx, $sqlUpdate, [(int)$nro_extension, (int)$idSIGA]);

        if (!$stmtUpdate) {
            echo json_encode([
                'success' => false,
                'message' => "Error al asignar ítem SIGA $idSIGA a la extensión $nro_extension.",
                'sqlsrv'  => sqlsrv_errors()
            ]);
            exit;
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Asignaciones y observaciones guardadas correctamente.'
]);
