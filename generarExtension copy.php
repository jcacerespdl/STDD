<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

// Validaciones iniciales
$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodMovimiento = $_GET['iCodMovimiento'] ?? null;

if (!$iCodTramite || !$iCodMovimiento) {
    echo "<h2>Faltan parámetros obligatorios.</h2>";
    exit;
}

// Obtener expediente y asunto
$sql = "SELECT expediente, cAsunto FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
$datos = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$expediente = $datos['expediente'] ?? 'N/A';
$asunto = $datos['cAsunto'] ?? '(Sin asunto)';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Extensiones por Ítems SIGA</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f9f9f9; }
        h2, h3 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        input[type="number"], select, textarea {
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

<!-- BLOQUE 1: Generar extensiones -->
<form id="formExtension">
    <label>¿Cuántas extensiones desea generar?</label>
    <input type="number" id="cantidad" name="cantidad" min="1" required>
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">
    <button type="submit">Generar</button>
</form>

<div id="bloqueAsignacion" style="display:none;">

    <!-- BLOQUE 2: Observaciones por extensión -->
    <h3>Observaciones por Extensión</h3>
    <form id="formGuardarAsignaciones">
        <table id="tablaObservaciones">
            <thead>
                <tr><th>Extensión</th><th>Observaciones</th></tr>
            </thead>
            <tbody></tbody>
        </table>

        <!-- BLOQUE 3: Asignar ítems SIGA -->
        <h3>Asignar Ítems SIGA a Extensiones</h3>
        <table id="tablaItems">
            <thead>
                <tr><th>Código Ítem</th><th>Nombre</th><th>Cantidad</th><th>Asignar a Extensión</th></tr>
            </thead>
            <tbody>
            <?php
                $tramites = [$iCodTramite];
                $stmtDeriv = sqlsrv_query($cnx, "SELECT iCodTramiteDerivar FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? AND iCodTramiteDerivar IS NOT NULL", [$iCodTramite]);
                while ($r = sqlsrv_fetch_array($stmtDeriv, SQLSRV_FETCH_ASSOC)) {
                    $tramites[] = $r['iCodTramiteDerivar'];
                }
                $in = implode(',', array_fill(0, count($tramites), '?'));
                $stmtItems = sqlsrv_query($cnx, "SELECT iCodTramiteSIGAPedido, codigo_item, cantidad FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite IN ($in)", $tramites);

                $sigaConn = sqlsrv_connect("192.168.32.135", [
                    "Database" => "SIGA_1670",
                    "Uid" => "fapaza",
                    "PWD" => "2780Fach",
                    "CharacterSet" => "UTF-8"
                ]);

                while ($item = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)):
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
                    <td>
                        <input type="number" name="asignaciones[<?= $item['iCodTramiteSIGAPedido'] ?>]" min="1" required>
                    </td>
                </tr>
            <?php endwhile; ?>
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
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const tablaObs = document.querySelector('#tablaObservaciones tbody');
        tablaObs.innerHTML = '';
        for (let i = 1; i <= cantidad; i++) {
                const numeroExtension = i + 1; // porque la extensión 1 es la original
                tablaObs.innerHTML += `
                    <tr>
                        <td>${numeroExtension}</td>
                        <td><textarea name="observaciones[${numeroExtension}]" rows="2"></textarea></td>
                    </tr>`;
            }
        document.getElementById('bloqueAsignacion').style.display = 'block';
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

</body>
</html>
