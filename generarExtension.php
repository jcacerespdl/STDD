<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodMovimiento = $_GET['iCodMovimiento'] ?? null;

if (!$iCodTramite || !$iCodMovimiento) {
    echo "<h2>Faltan parámetros obligatorios.</h2>";
    exit;
}

$iCodOficina = $_SESSION['iCodOficinaLogin'];
$esLogistica = ($iCodOficina == 112);

// Obtener expediente y asunto
$sqlTramite = "SELECT expediente, cAsunto FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$datos = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
$expediente = $datos['expediente'] ?? 'N/A';
$asunto = $datos['cAsunto'] ?? '(Sin asunto)';

// Obtener extensiones existentes (mayores a 1)
$sqlExtensiones = "SELECT nro_extension, observaciones 
                   FROM Tra_M_Tramite_Extension 
                   WHERE iCodTramite = ? AND nro_extension > 1 
                   ORDER BY nro_extension";
$stmtExt = sqlsrv_query($cnx, $sqlExtensiones, [$iCodTramite]);
$extensiones = [];
$ultimaExtension = 1;
while ($row = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC)) {
    $extensiones[] = $row;
    $ultimaExtension = max($ultimaExtension, $row['nro_extension']);
}

// Obtener ítems SIGA de este trámite y derivados
$tramites = [$iCodTramite];
$stmtDeriv = sqlsrv_query($cnx, "SELECT iCodTramiteDerivar FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? AND iCodTramiteDerivar IS NOT NULL", [$iCodTramite]);
while ($r = sqlsrv_fetch_array($stmtDeriv, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $r['iCodTramiteDerivar'];
}
$in = implode(',', array_fill(0, count($tramites), '?'));
$sqlItems = "SELECT iCodTramiteSIGAPedido, codigo_item, cantidad, extension 
             FROM Tra_M_Tramite_SIGA_Pedido 
             WHERE iCodTramite IN ($in)";
$stmtItems = sqlsrv_query($cnx, $sqlItems, $tramites);

$sigaConn = sqlsrv_connect("192.168.32.135", [
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
]);

$items = [];
while ($row = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
    $items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Extensiones por Ítems SIGA</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        h2, h3 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        input, select, textarea {
            width: 100%;
            padding: 5px;
            font-size: 14px;
        }
        button {
            margin-top: 15px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
        }
        button:hover { background-color: #1a242f; }
    </style>
</head>
<body>

<h2>Extensiones por Ítems SIGA</h2>
<p><strong>Expediente:</strong> <?= htmlspecialchars($expediente) ?> | <strong>Asunto:</strong> <?= htmlspecialchars($asunto) ?></p>

<?php if ($esLogistica): ?>

<!-- BLOQUE 1: Generar más extensiones -->
<form id="formExtension">
    <label>¿Cuántas extensiones adicionales desea generar?</label>
    <input type="number" id="cantidad" name="cantidad" min="1" required>
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">
    <button type="submit">Generar Nuevas Extensiones</button>
</form>

<div id="bloqueAsignacion" style="display:block;">
    <form id="formGuardarAsignaciones">
        <!-- BLOQUE 2: Observaciones -->
        <h3>Observaciones por Extensión</h3>
        <table>
            <thead><tr><th>Extensión</th><th>Observaciones</th></tr></thead>
            <tbody>
            <?php foreach ($extensiones as $ext): ?>
                <tr>
                    <td><?= $ext['nro_extension'] ?></td>
                    <td><textarea name="observaciones[<?= $ext['nro_extension'] ?>]" rows="2"><?= htmlspecialchars($ext['observaciones']) ?></textarea></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- BLOQUE 3: Ítems SIGA -->
        <h3>Asignar Ítems SIGA a Extensiones</h3>
        <table>
            <thead><tr><th>Código</th><th>Nombre</th><th>Cantidad</th><th>Ext. Actual</th><th>Reasignar a</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $nombre = '';
                if ($sigaConn) {
                    $stmtNom = sqlsrv_query($sigaConn, "SELECT NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?", [$item['codigo_item']]);
                    if ($r = sqlsrv_fetch_array($stmtNom, SQLSRV_FETCH_ASSOC)) {
                        $nombre = $r['NOMBRE_ITEM'];
                    }
                }
            ?>
                <tr>
                    <td><?= $item['codigo_item'] ?></td>
                    <td><?= $nombre ?></td>
                    <td><?= $item['cantidad'] ?></td>
                    <td><?= $item['extension'] ?? '-' ?></td>
                    <td>
                        <input type="number" name="asignaciones[<?= $item['iCodTramiteSIGAPedido'] ?>]" value="<?= $item['extension'] ?>" min="2" required>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <br>
        <button type="submit">Guardar Asignaciones</button>
    </form>
</div>

<script>
document.getElementById('formExtension').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    const res = await fetch('generarExtensionLote.php', {
        method: 'POST',
        body: formData
    });

    const json = await res.json();
    if (json.success) {
        alert(json.message);
        location.reload(); // recargar para ver nuevas extensiones
    } else {
        alert('Error: ' + json.message);
        if (json.sqlsrv) console.error(json.sqlsrv);
    }
});

document.getElementById('formGuardarAsignaciones').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('iCodTramite', '<?= $iCodTramite ?>');

    const res = await fetch('guardarAsignacionesExtensiones.php', {
        method: 'POST',
        body: formData
    });

    const json = await res.json();
    if (json.success) {
        alert(json.message);
        if (window.opener) window.opener.location.reload();
        window.close();
    } else {
        alert('Error: ' + json.message);
        if (json.sqlsrv) console.error(json.sqlsrv);
    }
});
</script>

<?php else: ?>
    <p style="color:red"><strong>Este módulo solo está disponible para la oficina logística (112).</strong></p>
<?php endif; ?>
</body>
</html>
