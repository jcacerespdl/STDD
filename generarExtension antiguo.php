<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite    = $_GET['iCodTramite'] ?? null;
$iCodMovimiento = $_GET['iCodMovimiento'] ?? null;

if (!$iCodTramite || !$iCodMovimiento) {
    echo "<h2>Faltan parámetros obligatorios.</h2>";
    exit;
}

$iCodOficina = $_SESSION['iCodOficinaLogin'];
$esLogistica = ($iCodOficina == 112);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Extensiones</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        input[type="number"] { width: 60px; text-align: center; }
        button {
            padding: 8px 16px;
            background-color: #364897;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background-color: #2c3c7d; }
        table {
            border-collapse: collapse;
            margin-top: 10px;
            width: 100%;
        }
        table, th, td {
            border: 1px solid #bbb;
            padding: 8px;
            text-align: left;
        }
        h3 {
            margin-top: 2rem;
        }
    </style>
</head>
<body>

    <h2>Generar Extensiones del Trámite</h2>
    <p><strong>Trámite:</strong> <?= htmlspecialchars($iCodTramite) ?> | <strong>Movimiento Base:</strong> <?= htmlspecialchars($iCodMovimiento) ?></p>

    <!-- 🔹 BLOQUE 1: Generación manual de extensiones -->
    <form id="formExtension">
        <label for="cantidad">¿Cuántas extensiones desea generar?</label>
        <input type="number" id="cantidad" name="cantidad" min="1" required>

        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">

        <br><br>
        <button type="submit">Generar Extensiones</button>
    </form>

    <div id="resultado" style="margin-top: 20px;"></div>

    <script>
        const form = document.getElementById('formExtension');
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(form);

            const res = await fetch('generarExtensionLote.php', {
                method: 'POST',
                body: formData
            });

            const json = await res.json();
            const resultado = document.getElementById('resultado');

            if (json.success) {
                resultado.innerHTML = `<p style="color:green">${json.message}</p>`;
            } else {
                resultado.innerHTML = `<p style="color:red">${json.message}</p>`;
                if (json.sqlsrv) console.error(json.sqlsrv);
            }
        });
    </script>

<?PHP // Obtener nombres de ítems
$sigaConn = sqlsrv_connect("192.168.32.135", [
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
]);


$nombresItems = [];
if ($sigaConn && count($items) > 0) {
    $codigos = array_column($items, 'codigo_item');
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));

    $sqlNombres = "SELECT CODIGO_ITEM, NOMBRE_ITEM 
                   FROM CATALOGO_BIEN_SERV 
                   WHERE CODIGO_ITEM IN ($placeholders)";
    $stmtNombres = sqlsrv_query($sigaConn, $sqlNombres, $codigos);

    while ($row = sqlsrv_fetch_array($stmtNombres, SQLSRV_FETCH_ASSOC)) {
        $nombresItems[$row['CODIGO_ITEM']] = $row['NOMBRE_ITEM'];
    }
}
?>







    <!-- 🔸 BLOQUE 2: Solo para oficina 112 - Extensiones por Ítems SIGA -->
    <?php if ($esLogistica): ?>
        <h3>Extensiones por Ítems SIGA</h3>

        <?php
        // 1. Obtener los iCodTramite derivados del trámite base
            $sqlDerivados = "
            SELECT iCodTramiteDerivar 
            FROM Tra_M_Tramite_Movimientos 
            WHERE iCodTramite = ? AND iCodTramiteDerivar IS NOT NULL";
            $stmtDerivados = sqlsrv_query($cnx, $sqlDerivados, [$iCodTramite]);

            $tramitesIn = [$iCodTramite];
            while ($row = sqlsrv_fetch_array($stmtDerivados, SQLSRV_FETCH_ASSOC)) {
            $tramitesIn[] = $row['iCodTramiteDerivar'];
            }

            // 2. Armar placeholders dinámicos (?, ?, ?, ...)
            $placeholders = implode(',', array_fill(0, count($tramitesIn), '?'));

            // 3. Obtener los ítems SIGA de todos esos trámites
            $sqlItems = "
            SELECT iCodTramiteSIGAPedido, pedido_siga, codigo_item, cantidad 
            FROM Tra_M_Tramite_SIGA_Pedido 
            WHERE iCodTramite IN ($placeholders)";
            $stmtItems = sqlsrv_query($cnx, $sqlItems, $tramitesIn);

            $items = [];
            if ($stmtItems) {
            while ($row = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                $items[] = $row;
            }
            }

            function obtenerNombreItem($codigo_item, $sigaConn) {
                $sql = "SELECT NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?";
                $stmt = sqlsrv_query($sigaConn, $sql, [$codigo_item]);
                if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    return $row['NOMBRE_ITEM'];
                }
                return null;
            }



        ?>

        <?php if (count($items) > 0): ?>
            <form id="formPedidosSIGA" method="post">
                <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
                <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">

                <table>
                    <thead>
                        <tr>
                            <th>Pedido SIGA</th>
                            <th>Item</th>
                            <th>Nombre del Ítem</th>
                            <th>Cantidad</th>
                            <th>Separar en extensión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $nombreItem = obtenerNombreItem($item['codigo_item'], $sigaConn);
                            ?>
                            <tr>
                                <td><?= $item['pedido_siga'] ?: 'N.A.' ?></td>
                                <td><?= $item['codigo_item'] ?? 'N.A.' ?></td>
                                <td><?= $nombreItem ?: '<i>No encontrado</i>' ?></td>
                                <td><?= $item['cantidad'] ?? 'N.A.' ?></td>
                                <td><input type="checkbox" name="itemsSeleccionados[]" value="<?= $item['iCodTramiteSIGAPedido'] ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <br>
                <button type="submit">Generar Extensiones por Ítem</button>
            </form>

            <script>
                document.getElementById('formPedidosSIGA').addEventListener('submit', async function (e) {
                        e.preventDefault();
                        const formData = new FormData(this);

                        const res = await fetch('generarExtensionPorPedido.php', {
                            method: 'POST',
                            body: formData
                        });

                        const json = await res.json();
                        if (json.success) {
                            alert(json.message);

                            // ✅ Refresca bandeja padre y cierra popup
                            if (window.opener && !window.opener.closed) {
                                window.opener.location.reload();
                            }
                            window.close();
                        } else {
                            alert('Error: ' + json.message);
                            if (json.sqlsrv) console.error(json.sqlsrv);
                        }
                    });
            </script>
        <?php else: ?>
            <p>No se encontraron ítems SIGA para este trámite.</p>
        <?php endif; ?>
    <?php endif; ?>

</body>
</html>
