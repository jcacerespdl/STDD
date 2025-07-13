<?php
include("conexion/conexion.php");

$iCodTramite = $_POST['iCodTramite'] ?? null;
$tipo = $_POST['tipo'] ?? null; // 1: Externo, 2: Interno según TU lógica
$asunto = trim($_POST['asunto'] ?? '');
$doc = trim($_POST['doc'] ?? '');

if (!$iCodTramite || !in_array($tipo, ['1', '2'])) {
    echo "<p style='color:red;'>Seleccione al menos Interno o Externo.</p>";
    exit;
}

$where = [
    "t.iCodTramite <> ?",
    "t.nFlgEnvio = 1",
    "t.nFlgTipoDoc = ?" // ahora respeta el valor exacto que envías
];
$params = [
    $iCodTramite,
    (int)$tipo
];

if (!empty($asunto)) {
    $where[] = "t.cAsunto LIKE ?";
    $params[] = '%' . $asunto . '%';
}
if (!empty($doc)) {
    $where[] = "t.documentoElectronico LIKE ?";
    $params[] = '%' . $doc . '%';
}

$sql = "
SELECT TOP 30 
    t.iCodTramite, 
    t.fFecRegistro, 
    t.expediente, 
    t.documentoElectronico, 
    t.cAsunto
FROM Tra_M_Tramite t
WHERE " . implode(" AND ", $where) . "
ORDER BY t.fFecRegistro DESC
";

$stmt = sqlsrv_query($cnx, $sql, $params);
if (!$stmt) {
    echo "<p style='color:red;'>Error en la consulta.</p>";
    exit;
}

echo "<table>";
echo "<thead><tr>
        <th>Fecha</th>
        <th>Expediente</th>
        <th>Documento Electrónico</th>
        <th>Asunto</th>
        <th>Acción</th>
      </tr></thead><tbody>";

$encontrado = false;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $encontrado = true;
    $fecha = $row['fFecRegistro'] instanceof DateTime ? $row['fFecRegistro']->format('d/m/Y H:i') : '';
    $expediente = htmlspecialchars($row['expediente'] ?? '');
    $doc = htmlspecialchars($row['documentoElectronico'] ?? '');
    $asunto = htmlspecialchars($row['cAsunto'] ?? '');
    $iCodRelacionado = (int)$row['iCodTramite'];

    echo "<tr>";
    echo "<td>{$fecha}</td>";
    echo "<td>{$expediente}</td>";
    echo "<td>{$doc}</td>";
    echo "<td>{$asunto}</td>";
    echo "<td><button onclick='agregarReferencia({$iCodRelacionado})'>Agregar</button></td>";
    echo "</tr>";
}
echo "</tbody></table>";

if (!$encontrado) {
    echo "<p style='color:#777;'>No se encontraron resultados.</p>";
}
