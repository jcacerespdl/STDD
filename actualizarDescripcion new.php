<?php
include_once("conexion/conexion.php");
header('Content-Type: application/json');

$iCodTramite    = $_POST['iCodTramite'] ?? null;
$descripcion    = $_POST['descripcion'] ?? '';
$tipoDocumento  = $_POST['tipoDocumento'] ?? null;

if (empty($iCodTramite) || empty($tipoDocumento)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

if (!$iCodTramite || !$tipoDocumento) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Sanitizar y verificar contenido vacío (TinyMCE envía a veces <p><br></p>)
$contenidoLimpio = trim(strip_tags($descripcion));
if ($contenidoLimpio === '') {
    $descripcion = '';
}

// Si el tipo de documento es 108 o 109, agregar saludo + tabla SIGA automáticamente
if (in_array((int)$tipoDocumento, [108, 109])) {
    if (stripos($descripcion, 'tengo el agrado de dirigirme') === false) {
        $saludoHTML = '<p>Tengo el agrado de dirigirme a usted para informarle lo siguiente:</p>';
        $tablaHTML = generarTablaSiga($iCodTramite);
        $descripcion .= $saludoHTML . $tablaHTML;
    }
}

// Guardar en BD
$sql = "UPDATE Tra_M_Tramite SET descripcion = ? WHERE iCodTramite = ?";
$params = [$descripcion, $iCodTramite];
$stmt = sqlsrv_query($cnx, $sql, $params);

if ($stmt) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
}

exit;

// ==================== FUNCIÓN: Generar tabla SIGA ============================
function generarTablaSiga($iCodTramite) {
    global $cnx;

    $sql = "SELECT codigo_item, cantidad, stock, consumo_promedio, meses_consumo, situacion
            FROM Tra_M_Tramite_SIGA_Pedido
            WHERE iCodTramite = ?";
    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);

    if (!$stmt || sqlsrv_has_rows($stmt) === false) {
        return '<p><em>No se encontraron ítems SIGA.</em></p>';
    }

    $tabla = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-size: 12px; width: 100%; margin-top: 10px;">';
    $tabla .= '<thead style="background-color: #f2f2f2; font-weight: bold;">
        <tr>
            <th style="text-align: center;">Código ITEM</th>
            <th style="text-align: center;">Cantidad</th>
            <th style="text-align: center;">Stock</th>
            <th style="text-align: center;">Consumo Promedio</th>
            <th style="text-align: center;">Meses Consumo</th>
            <th style="text-align: center;">Situación</th>
        </tr>
    </thead><tbody>';

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $tabla .= '<tr>';
        $tabla .= '<td>' . htmlspecialchars($row['codigo_item']) . '</td>';
        $tabla .= '<td style="text-align: center;">' . (int)$row['cantidad'] . '</td>';
        $tabla .= '<td style="text-align: center;">' . (int)$row['stock'] . '</td>';
        $tabla .= '<td style="text-align: center;">' . (float)$row['consumo_promedio'] . '</td>';
        $tabla .= '<td style="text-align: center;">' . (float)$row['meses_consumo'] . '</td>';
        $tabla .= '<td>' . htmlspecialchars($row['situacion']) . '</td>';
        $tabla .= '</tr>';
    }

    $tabla .= '</tbody></table>';
    return $tabla;
}
