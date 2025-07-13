<?php
include("conexion/conexion.php");

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodDigital = $_GET['iCodDigital'] ?? null;

$sql = "SELECT f.iCodFirma, f.posicion, f.tipoFirma, f.nFlgFirma, 
               t.cNombresTrabajador + ' ' + t.cApellidosTrabajador AS nombreCompleto,
               o.cNomOficina
        FROM Tra_M_Tramite_Firma f
        LEFT JOIN Tra_M_Trabajadores t ON f.iCodTrabajador = t.iCodTrabajador
        LEFT JOIN Tra_M_Oficinas o ON f.iCodOficina = o.iCodOficina
        WHERE f.iCodTramite = ? AND f.iCodDigital = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);

if (!$stmt) {
    echo "<p>Error al consultar firmantes.</p>";
    exit;
}

echo "<table border='1' cellpadding='5' cellspacing='0' style='width:100%; font-size: 13px;'>";
echo "<thead><tr><th>Nombre</th><th>Oficina</th><th>Posición</th><th>Tipo</th><th>Estado</th></tr></thead><tbody>";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tipo = $row['tipoFirma'] == 1 ? 'Firma Principal' : 'Visto Bueno';
    switch ($row['nFlgFirma']) {
        case 3:
            $estado = "✅ Firmado";
            break;
        case 1:
        case 2:
            $estado = "⏳ En Proceso";
            break;
        default:
            $estado = "❌ Pendiente";
            break;
    }
    echo "<tr>
        <td>" . htmlspecialchars($row['nombreCompleto'] ?? '-') . "</td>
        <td>" . htmlspecialchars($row['cNomOficina'] ?? '-') . "</td>
        <td align='center'>" . htmlspecialchars($row['posicion']) . "</td>
        <td>" . $tipo . "</td>
        <td>" . $estado . "</td>
    </tr>";
}

echo "</tbody></table>";
?>
