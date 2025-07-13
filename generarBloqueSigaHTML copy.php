<?php
include_once("conexion/conexion.php");
header("Content-Type: text/html; charset=utf-8");

$iCodTramite = $_POST['iCodTramite'] ?? null;

if (!$iCodTramite) {
    http_response_code(400);
    echo "Código de trámite no válido.";
    exit;
}

// Consulta ítems SIGA
$sql = "SELECT codigo_item, cantidad, stock, consumo_promedio, meses_consumo, situacion
        FROM Tra_M_Tramite_SIGA_Pedido
        WHERE iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);

$bloque = '<p>Tengo el agrado de dirigirme a usted para informarle lo siguiente:</p>';

if (!$stmt || sqlsrv_has_rows($stmt) === false) {
    $bloque .= '<p><em>No se encontraron ítems SIGA.</em></p>';
    echo $bloque;
    exit;
}

$bloque .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-size: 8px; width: 100%; margin-top: 10px;">';
$bloque .= '<thead style="background-color: #f2f2f2; font-weight: bold;">
    <tr>
        <th style="text-align: center;">Código SIGA</th>
        <th style="text-align: center;">Cantidad</th>
        <th style="text-align: center;">Stock</th>
        <th style="text-align: center;">Consumo Promedio</th>
        <th style="text-align: center;">Meses Consumo</th>
        <th style="text-align: center;">Situación</th>
    </tr>
</thead><tbody>';

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $bloque .= '<tr>';
    $bloque .= '<td>' . htmlspecialchars($row['codigo_item']) . '</td>';
    $bloque .= '<td style="text-align: center;">' . (int)$row['cantidad'] . '</td>';
    $bloque .= '<td style="text-align: center;">' . (int)$row['stock'] . '</td>';
    $bloque .= '<td style="text-align: center;">' . (float)$row['consumo_promedio'] . '</td>';
    $bloque .= '<td style="text-align: center;">' . (float)$row['meses_consumo'] . '</td>';
    $bloque .= '<td>' . htmlspecialchars($row['situacion']) . '</td>';
    $bloque .= '</tr>';
}

$bloque .= '</tbody></table>';
echo $bloque;
