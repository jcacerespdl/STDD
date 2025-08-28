<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite    = $_GET['iCodTramite']   ?? null;
$iCodMovimiento = $_GET['iCodMovimiento'] ?? null;

if (!$iCodTramite || !$iCodMovimiento) {
    echo "<h2>Faltan parámetros obligatorios.</h2>";
    exit;
}

$iCodOficina     = $_SESSION['iCodOficinaLogin'] ?? null;
$iCodTrabajador  = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$esLogistica     = ($iCodOficina == 112);

// 0) Asegurar que exista registro de la extensión 1 en Tra_M_Tramite_Extension
$sqlEnsureExt1 = "
IF NOT EXISTS (
    SELECT 1 FROM Tra_M_Tramite_Extension
    WHERE iCodTramite = ? AND nro_extension = 1
)
INSERT INTO Tra_M_Tramite_Extension
    (iCodTramite, nro_extension, iCodMovimientoOrigen, iCodTrabajadorRegistro, fFecRegistro, observaciones)
VALUES
    (?, 1, ?, ?, GETDATE(), NULL);";
sqlsrv_query($cnx, $sqlEnsureExt1, [$iCodTramite, $iCodTramite, $iCodMovimiento, $iCodTrabajador]);

// 1) Obtener expediente y asunto
$sqlTramite = "SELECT expediente, cAsunto FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$datos = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
$expediente = $datos['expediente'] ?? 'N/A';
$asunto     = $datos['cAsunto']     ?? '(Sin asunto)';

// 2) Obtener extensiones existentes (>= 1) para observaciones y combos
$sqlExtensiones = "SELECT nro_extension, observaciones
                   FROM Tra_M_Tramite_Extension
                   WHERE iCodTramite = ? AND nro_extension >= 1
                   ORDER BY nro_extension";
$stmtExt = sqlsrv_query($cnx, $sqlExtensiones, [$iCodTramite]);
$extensiones = [];
while ($row = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC)) {
    $extensiones[] = $row;
}

// 3) Preparar datos SIGA SOLO si es Logística (Of. 112)
$items   = [];
$sigaConn = null;
if ($esLogistica) {
    // Tramites del árbol (principal + derivados)
    $tramites = [$iCodTramite];
    $stmtDeriv = sqlsrv_query($cnx, "SELECT iCodTramiteDerivar FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? AND iCodTramiteDerivar IS NOT NULL", [$iCodTramite]);
    while ($r = sqlsrv_fetch_array($stmtDeriv, SQLSRV_FETCH_ASSOC)) {
        $tramites[] = $r['iCodTramiteDerivar'];
    }
    $in = implode(',', array_fill(0, count($tramites), '?'));
    $sqlItems = "SELECT iCodTramiteSIGAPedido, codigo_item, cantidad, extension, pedido_siga
                 FROM Tra_M_Tramite_SIGA_Pedido
                 WHERE iCodTramite IN ($in)";
    $stmtItems = sqlsrv_query($cnx, $sqlItems, $tramites);

    $sigaConn = sqlsrv_connect("192.168.32.135", [
        "Database"     => "SIGA_1670",
        "Uid"          => "fapaza",
        "PWD"          => "2780Fach",
        "CharacterSet" => "UTF-8"
    ]);

    while ($row = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
        $items[] = $row;
    }
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

<!-- BLOQUE 1: Generar más extensiones (TODAS LAS OFICINAS) -->
<form id="formExtension">
    <label>¿Cuántas extensiones adicionales desea generar?</label>
    <input type="number" id="cantidad" name="cantidad" min="1" required>
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">
    <button type="submit">Generar Nuevas Extensiones</button>
</form>

<div id="bloqueAsignacion" style="display:block;">
    <form id="formGuardarAsignaciones">
        <!-- BLOQUE 2: Observaciones (TODAS LAS OFICINAS) -->
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

        <?php if ($esLogistica): ?>
        <!-- BLOQUE 3: Ítems SIGA (SOLO OF. 112) -->
        <h3>Asignar Ítems SIGA a Extensiones</h3>
        <?php
          // Opciones de extensiones: incluir SIEMPRE la 1
          $extDisponibles = [1];
          foreach ($extensiones as $e) {
              $n = intval($e['nro_extension']);
              if (!in_array($n, $extDisponibles)) $extDisponibles[] = $n;
          }
          sort($extDisponibles);

          // Agrupar solo cuando hay pedido_siga
          $agrupados = ['con_pedido' => [], 'sin_pedido' => []];
          foreach ($items as $it) {
              $tienePedido = isset($it['pedido_siga']) && $it['pedido_siga'] !== '' && $it['pedido_siga'] !== null;
              if ($tienePedido) {
                  $agrupados['con_pedido'][$it['pedido_siga']][] = $it;
              } else {
                  $agrupados['sin_pedido'][] = $it;
              }
          }
        ?>
        <table>
          <thead>
            <tr>
              <th>Pedido SIGA</th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Cantidad</th>
              <th>Ext. Actual</th>
              <th>Reasignar a</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // A) Con pedido: rowspan + un solo select por grupo
            foreach ($agrupados['con_pedido'] as $pedido => $grupo):
                $rowspan = count($grupo);
                $extDefault = 1;
                foreach ($grupo as $g) { if (!empty($g['extension'])) { $extDefault = intval($g['extension']); break; } }
                $primera = true;
                foreach ($grupo as $g):
                    $nombre = '';
                    if ($sigaConn) {
                        $stmtNom = sqlsrv_query($sigaConn, "SELECT NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?", [$g['codigo_item']]);
                        if ($r = sqlsrv_fetch_array($stmtNom, SQLSRV_FETCH_ASSOC)) $nombre = $r['NOMBRE_ITEM'];
                    }
                    ?>
                    <tr>
                      <?php if ($primera): ?>
                        <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($pedido) ?></td>
                      <?php endif; ?>

                      <td><?= htmlspecialchars($g['codigo_item']) ?></td>
                      <td><?= htmlspecialchars($nombre) ?></td>
                      <td><?= intval($g['cantidad']) ?></td>
                      <td><?= isset($g['extension']) && $g['extension'] !== null ? intval($g['extension']) : '-' ?></td>

                      <?php if ($primera): ?>
                        <td rowspan="<?= $rowspan ?>">
                          <select class="select-grupo" data-pedido="<?= htmlspecialchars($pedido) ?>" required>
                            <?php foreach ($extDisponibles as $extN): ?>
                              <option value="<?= $extN ?>" <?= $extN === $extDefault ? 'selected' : '' ?>>Ext. <?= $extN ?></option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      <?php endif; ?>
                    </tr>

                    <!-- hidden por ítem -->
                    <input type="hidden"
                        name="asignaciones[<?= intval($g['iCodTramiteSIGAPedido']) ?>]"
                        class="hidden-asig pedido-<?= htmlspecialchars($pedido) ?>"
                        value="<?= $extDefault ?>">
                    <?php $primera = false;
                endforeach;
            endforeach;

            // B) Sin pedido: fila individual + select propio
            foreach ($agrupados['sin_pedido'] as $g):
                $nombre = '';
                if ($sigaConn) {
                    $stmtNom = sqlsrv_query($sigaConn, "SELECT NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?", [$g['codigo_item']]);
                    if ($r = sqlsrv_fetch_array($stmtNom, SQLSRV_FETCH_ASSOC)) $nombre = $r['NOMBRE_ITEM'];
                }
                $extDefault = !empty($g['extension']) ? intval($g['extension']) : 1;
                ?>
                <tr>
                  <td>-</td>
                  <td><?= htmlspecialchars($g['codigo_item']) ?></td>
                  <td><?= htmlspecialchars($nombre) ?></td>
                  <td><?= intval($g['cantidad']) ?></td>
                  <td><?= isset($g['extension']) && $g['extension'] !== null ? intval($g['extension']) : '-' ?></td>
                  <td>
                    <select class="select-individual" data-id="<?= intval($g['iCodTramiteSIGAPedido']) ?>" required>
                      <?php foreach ($extDisponibles as $extN): ?>
                        <option value="<?= $extN ?>" <?= $extN === $extDefault ? 'selected' : '' ?>>Ext. <?= $extN ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <input type="hidden"
                      name="asignaciones[<?= intval($g['iCodTramiteSIGAPedido']) ?>]"
                      id="asig-<?= intval($g['iCodTramiteSIGAPedido']) ?>"
                      value="<?= $extDefault ?>">
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; // fin bloque SIGA solo logística ?>

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
        location.reload();
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
<?php if ($esLogistica): ?>
// Sincroniza selects del grupo SIGA
document.querySelectorAll('.select-grupo').forEach(sel => {
  sel.addEventListener('change', () => {
    const pedido = sel.dataset.pedido;
    const val = sel.value;
    document.querySelectorAll('.hidden-asig.pedido-' + CSS.escape(pedido)).forEach(h => {
      h.value = val;
    });
  });
});
// Sincroniza selects individuales SIGA
document.querySelectorAll('.select-individual').forEach(sel => {
  sel.addEventListener('change', () => {
    const id = sel.dataset.id;
    const h = document.getElementById('asig-' + id);
    if (h) h.value = sel.value;
  });
});
<?php endif; ?>
</script>

<?php
// Cerrar conexión SIGA si se abrió
if ($sigaConn) { sqlsrv_close($sigaConn); }
?>
</body>
</html>
