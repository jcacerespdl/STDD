<?php
include("conexion/conexion.php");

$iCodTramite = $_POST['iCodTramite'] ?? 0;
$tipoNuevo = $_POST['tipoNuevo'] ?? '';

if (!$iCodTramite || !in_array($tipoNuevo, ['B', 'S'])) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Determinar tipo de bien/servicio en texto
$tipoTexto = $tipoNuevo === 'B' ? 'BIEN' : 'SERVICIO';

$sql = "DELETE FROM Tra_M_Tramite_SIGA_Pedido 
        WHERE iCodTramite = ? AND (tipo_bien <> ? OR tipo_bien IS NULL)";
$params = [$iCodTramite, $tipoTexto];
sqlsrv_query($cnx, $sql, $params);

echo json_encode(['status' => 'success']);

?>