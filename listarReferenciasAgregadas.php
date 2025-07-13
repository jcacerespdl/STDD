<?php
include("conexion/conexion.php");

$iCodTramite = $_GET['iCodTramite'] ?? null;
if (!$iCodTramite) {
    echo "<p style='color:red;'>Trámite no especificado.</p>";
    exit;
}

$sql = "
SELECT 
    r.iCodTramiteRef,
    t.fFecRegistro,
    t.expediente,
    t.documentoElectronico,
    t.cAsunto,
    t.nFlgTipoDoc
FROM Tra_M_Tramite_Referencias r
JOIN Tra_M_Tramite t ON r.iCodTramiteRef = t.iCodTramite
WHERE r.iCodTramite = ?
ORDER BY t.fFecRegistro DESC
";

$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
if (!$stmt) {
    echo "<p style='color:red;'>Error al consultar referencias.</p>";
    exit;
}

echo "<table>";
echo "<thead><tr>
        <th>Fecha</th>
        <th>Expediente</th>
        <th>Documento Electrónico</th>
        <th>Asunto</th>
        <th>Tipo</th>
        <th>Acción</th>
      </tr></thead><tbody>";

$hay = false;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $hay = true;
    $fecha = $row['fFecRegistro'] instanceof DateTime ? $row['fFecRegistro']->format('d/m/Y H:i') : '';
    $expediente = htmlspecialchars($row['expediente']);
    $doc = htmlspecialchars($row['documentoElectronico']);
    $asunto = htmlspecialchars($row['cAsunto']);
    $tipo = ($row['nFlgTipoDoc'] == 1) ? "Externo" : "Interno";

    echo "<tr>";
    echo "<td>{$fecha}</td>";
    echo "<td>{$expediente}</td>";
   // Reconstruir nombre real del archivo
if ($row['nFlgTipoDoc'] == 1) {
    // Externo
    $nombre = pathinfo($doc, PATHINFO_FILENAME);
    $ext = pathinfo($doc, PATHINFO_EXTENSION);
    $nombreSinEspacios = preg_replace('/\s+/', '_', $nombre);
    $archivoFinal = $row['iCodTramiteRef'] . '-' . $nombreSinEspacios . '.' . $ext;
} else {
    // Interno
    $archivoFinal = $doc;
}

// Mostrar solo ícono como enlace, nombre como texto sin formato
echo "<td>
        <a href='cDocumentosFirmados/" . urlencode($archivoFinal) . "' target='_blank' style='text-decoration: none; color: inherit;'>
            <i class='material-icons'>picture_as_pdf</i>
        </a> " . htmlspecialchars($doc) . "
      </td>";

    echo "<td>{$asunto}</td>";
    echo "<td>{$tipo}</td>";
    echo "<td><a href='#' onclick='eliminarReferencia({$row['iCodTramiteRef']})' style='color: var(--danger);'><i class='material-icons'>delete</i></a></td>";
    echo "</tr>";
}
echo "</tbody></table>";

if (!$hay) {
    echo "<p style='color:#777;'>No hay referencias agregadas.</p>";
}
