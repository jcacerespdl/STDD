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
  :root{
    --primary:#005a86;
    --primary-600:#004c72; /* hover */
    --primary-700:#003f60; /* active */
    --text:#2f3a44;
    --muted:#6b7280;
    --border:#e5e7eb;
    --bg:#ffffff; /* fondo blanco */
  }

  /* Reset de página */
  *{ box-sizing:border-box; }
  html,body{ height:100%; }
  body{
    font-family: Arial, Helvetica, sans-serif;
    margin:0;
    padding:20px;
    background:var(--bg);   /* blanco */
    color:var(--text);
  }

  h2,h3{
    margin:0 0 10px 0;
    color:var(--text);
    font-weight:700;
  }

  p{ margin:0 0 12px 0; color:var(--muted); }

  /* Contenedores y tablas */
  .card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
    box-shadow:0 2px 8px rgba(7,23,42,.04);
  }

  table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    margin-top:12px;
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    overflow:hidden;
  }
  thead th{
    background:#f7f9fb;
    color:#334155;
    font-weight:600;
    border-bottom:1px solid var(--border);
  }
  th, td{
    padding:10px 12px;
    border-bottom:1px solid var(--border);
  }
  tr:last-child td{ border-bottom:none; }

  /* Inputs */
  input, select, textarea{
    width:100%;
    padding:10px 12px;
    font-size:14px;
    border:1px solid var(--border);
    border-radius:8px;
    background:#fff;
    color:var(--text);
    outline:none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  input:focus, select:focus, textarea:focus{
    border-color:#cfe2ff;
    box-shadow:0 0 0 3px rgba(0,90,134,.15);
  }

  /* Botones institucionales */
  button,
  .btn,
  .btn-primary{
    appearance:none;
    border:none;
    border-radius:8px;
    height:42px;
    padding:0 18px;
    font-size:14px;
    font-weight:600;
    color:#fff;
    background:var(--primary);         /* primary institucional */
    cursor:pointer;
    transition:transform .05s ease, background .15s ease, box-shadow .15s ease;
    box-shadow:0 2px 6px rgba(0,90,134,.18);
  }
  button:hover,
  .btn:hover,
  .btn-primary:hover{
    background:var(--primary-600);
  }
  button:active,
  .btn:active,
  .btn-primary:active{
    background:var(--primary-700);
    transform:translateY(1px);
  }
  .btn-secondary{
    background:#c69157;
    color:#fff;
  }

  /* Separadores suaves entre bloques */
  .block + .block{ margin-top:18px; }

  /* Espaciado en el “Guardar Asignaciones” */
  .actions{ margin-top:16px; display:flex; gap:10px; }
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
