<?php
include("conexion/conexion.php");

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$pedidosSiga = $_POST['pedidosSiga'] ?? [];
$itemsManual = $_POST['itemsSigaManual'] ?? [];

if (!$iCodTramite) {
    echo json_encode(["status" => "error", "message" => "Trámite no especificado"]);
    exit();
}

// Eliminar ítems anteriores
$sqlDelete = "DELETE FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite = ?";
sqlsrv_query($cnx, $sqlDelete, [$iCodTramite]);

// Insertar ítems con pedido SIGA
foreach ($pedidosSiga as $entrada) {
    $parts = explode("_", $entrada); // esperado: pedido_codigoitem_cantidad
    $pedido = $parts[0] ?? null;
    $codigoItem = $parts[1] ?? null;
    $cantidad = isset($parts[2]) ? intval($parts[2]) : null;

    if ($pedido && $codigoItem && $cantidad > 0) {
        $sql = "INSERT INTO Tra_M_Tramite_SIGA_Pedido (iCodTramite, pedido_siga, extension, codigo_item, cantidad)
                VALUES (?, ?, 1, ?, ?)";
        $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $pedido, $codigoItem, $cantidad]);

        if ($stmt === false) {
            echo json_encode(["status" => "error", "message" => "Error al insertar pedido SIGA", "errors" => sqlsrv_errors()]);
            exit();
        }
    }
}

// Insertar ítems sin pedido SIGA
foreach ($itemsManual as $entrada) {
    $parts = explode("_", $entrada); // esperado: codigoitem_cantidad
    $codigoItem = $parts[0] ?? null;
    $cantidad = isset($parts[1]) ? intval($parts[1]) : null;

    if ($codigoItem && $cantidad > 0) {
        $sql = "INSERT INTO Tra_M_Tramite_SIGA_Pedido (iCodTramite, pedido_siga, extension, codigo_item, cantidad)
                VALUES (?, NULL, 1, ?, ?)";
        $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $codigoItem, $cantidad]);

        if ($stmt === false) {
            echo json_encode(["status" => "error", "message" => "Error al insertar ítem manual", "errors" => sqlsrv_errors()]);
            exit();
        }
    }
}

echo json_encode(["status" => "success"]);
?>