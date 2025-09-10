<?php
session_start();
include("head.php");
include("conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo "<p>Sesión expirada</p>";
    exit;
}

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina    = $_SESSION['iCodOficinaLogin'];

$filtroFirma = $_GET['estado'] ?? '0'; // Por defecto: No firmados
$isFirmado  = ($filtroFirma === '1');
$titulo = $filtroFirma === '1' ? "DOCUMENTOS PRINCIPALES FIRMADOS" : "DOCUMENTOS PRINCIPALES POR APROBAR";

// Armar consulta según filtro
// ⬇️ Agregamos 2 subconsultas (no cambian tu lógica ni parámetros) para saber si hay firmantes y si faltan
$sql = "SELECT 
          t.iCodTramite, 
          t.cCodificacion, 
          t.cAsunto, 
          t.fFecDocumento, 
          t.documentoElectronico,
          (SELECT COUNT(*) 
             FROM Tra_M_Tramite_Firma f 
            WHERE f.iCodTramite = t.iCodTramite 
              AND f.iCodDigital IS NULL 
              AND f.nFlgEstado = 1) AS totalFirmantes,
          (SELECT SUM(CASE WHEN f.nFlgFirma = 3 THEN 1 ELSE 0 END) 
             FROM Tra_M_Tramite_Firma f
            WHERE f.iCodTramite = t.iCodTramite 
              AND f.iCodDigital IS NULL 
              AND f.nFlgEstado = 1) AS firmantesFirmados
        FROM Tra_M_Tramite t
        WHERE t.iCodOficinaRegistro = ?
          AND " . ($isFirmado ? "t.nFlgFirma = 1" : "(t.nFlgFirma = 0 OR t.nFlgFirma IS NULL)") . "
          AND t.nFlgEstado = 1
          AND t.documentoElectronico IS NOT NULL
        ORDER BY t.fFecDocumento DESC";

// ⚠️ Dejamos intacta tu asignación original de params, aunque el query solo usa 1 placeholder:
$params = [$iCodOficina, (int)$filtroFirma];
$stmt = sqlsrv_query($cnx, $sql, $params);
?>
<style>
.titulo-principal{color:var(--primary,#005a86);font-size:22px;font-weight:bold;margin-top:0;margin-bottom:20px}
.chip-bloqueo{display:inline-block;font-size:11px;color:#b00;margin-top:4px}

/* Modal estilo simple (igual patrón visual que usas en bandejas) */
.modal-firmantes{
  display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.35);
}
.modal-firmantes .box{
  position:absolute;top:10%;left:50%;transform:translateX(-50%);
  background:#fff;max-width:700px;width:92%;
  border:1px solid #ddd;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.25);
  overflow:hidden;
}
.modal-firmantes .hdr{
  display:flex;align-items:center;justify-content:space-between;
  background:#005a86;color:#fff;padding:10px 14px;font-weight:600
}
.modal-firmantes .cnt{padding:16px;max-height:65vh;overflow:auto;background:#fff}
.modal-firmantes .ftr{display:flex;justify-content:flex-end;gap:8px;padding:10px 14px;background:#fafafa}
.btn{cursor:pointer}
</style>

<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <h2 class="titulo-principal"><?= htmlspecialchars($titulo) ?></h2>

  <form method="get" class="mb-4">
    <label for="estado">Filtrar por estado de firma:</label>
    <select name="estado" id="estado" onchange="this.form.submit()" class="form-select" style="width: 200px; display: inline-block; margin-left: 10px;">
      <option value="0" <?= $filtroFirma === '0' ? 'selected' : '' ?>>Pendientes</option>
      <option value="1" <?= $filtroFirma === '1' ? 'selected' : '' ?>>Firmados</option>
    </select>
  </form>
  <br>

  <?php if ($filtroFirma === '0'): ?>
    <button id="firmarSeleccionados" class="btn btn-primary mb-3">Firmar seleccionados</button>
  <?php endif; ?>
  <br>

  <table class="table table-bordered table-sm" id="tablaFirmaPrincipal">
    <thead class="table-light">
      <tr>
        <?php if ($filtroFirma === '0'): ?>
          <th><input type="checkbox" id="seleccionarTodos"></th>
        <?php endif; ?>
        <th>Expediente</th>
        <th>Documento</th>
        <th>Asunto</th>
        <th>Fecha</th>
        <th>Firmantes</th> <!-- NUEVA COLUMNA -->
      </tr>
    </thead>
    <tbody>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) : 
        $total = (int)($row['totalFirmantes'] ?? 0);
        $ok    = (int)($row['firmantesFirmados'] ?? 0);
        $tieneFirmantes = $total > 0;
        $pendientes     = $tieneFirmantes && ($ok < $total); // si hay firmantes pero faltan
      ?>
        <tr>
          <?php if ($filtroFirma === '0'): ?>
            <td>
              <!-- BLOQUEO: si hay pendientes => disabled (no se puede seleccionar para firma) -->
              <input 
                type="checkbox" 
                class="chkDocumento" 
                data-id="<?= $row['iCodTramite'] ?>" 
                data-archivo="<?= $row['documentoElectronico'] ?>"
                <?= $pendientes ? 'disabled title="Hay firmantes pendientes. No se puede firmar aún."' : '' ?>
              >
              <?php if ($pendientes): ?>
                <div class="chip-bloqueo">Firmantes pendientes</div>
              <?php endif; ?>
            </td>
          <?php endif; ?>

          <td><?= htmlspecialchars($row['cCodificacion']) ?></td>
          <td><a href="./cDocumentosFirmados/<?= rawurlencode($row['documentoElectronico']) ?>" target="_blank"><?= htmlspecialchars($row['documentoElectronico']) ?></a></td>
          <td><?= htmlspecialchars($row['cAsunto']) ?></td>
          <td><?= isset($row['fFecDocumento']) && $row['fFecDocumento'] instanceof DateTime ? $row['fFecDocumento']->format('d/m/Y H:i') : '' ?></td>

          <!-- NUEVA COLUMNA: botón a detallesFirmantes2.php SOLO si hay firmantes -->
          <td style="text-align:center">
            <?php if ($tieneFirmantes): ?>
              <a href="detallesFirmantes2.php?iCodTramite=<?= $row['iCodTramite'] ?>&iCodDigital=null"
                 title="Ver firmantes"
                 style="text-decoration: none;"
                 onclick="return abrirModalFirmantes(this.href)">
                <span class="material-icons" style="font-size: 18px; color: #555;">group</span>
              </a>
            <?php else: ?>
              <span style="color:#999">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Modal para firmantes (sin iframe) -->
<div id="modalFirmantes" class="modal-firmantes" aria-hidden="true">
  <div class="box">
    <div class="hdr">
      <div>Firmantes</div>
      <button class="btn btn-light" onclick="cerrarModalFirmantes()" title="Cerrar" style="background:#fff;border:0;border-radius:6px;padding:6px 10px;">
        <span class="material-icons" style="color:#005a86;vertical-align:middle">close</span>
      </button>
    </div>
    <div class="cnt" id="contenidoModalFirmantes">Cargando...</div>
    <div class="ftr">
      <button class="btn btn-secondary" onclick="cerrarModalFirmantes()">Cerrar</button>
    </div>
  </div>
</div>

<div id="addComponent" style="display: none;"></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="./scripts/jquery.blockUI.js"></script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>

<script>
  var jqFirmaPeru = jQuery.noConflict(true);

  function signatureInit() {
    console.log("🟡 Firma Perú: Inicio del proceso");
    alert("PROCESO INICIADO");
  }

  function signatureOk() {
    console.log("🟢 Firma Perú: Documento firmado correctamente");
    alert("DOCUMENTO FIRMADO");
    top.location.reload();
  }

  function signatureCancel() {
    console.log("🔴 Firma Perú: Operación cancelada por el usuario");
    alert("OPERACION CANCELADA");
  }

  jqFirmaPeru(document).ready(function () {
    const $jq = jqFirmaPeru;

    // Seleccionar todos: NO toca checkboxes deshabilitados (para respetar el bloqueo)
    $jq('#seleccionarTodos').on('change', function () {
      const check = this.checked;
      $jq('.chkDocumento').each(function(){
        if (!this.disabled) { $(this).prop('checked', check); }
      });
    });

    // >>> Mantengo INTACTO tu flujo de firma masiva (solo ignora los disabled por el bloqueo)
    $jq('#firmarSeleccionados').on('click', function () {
      const seleccionados = [];
      $jq('.chkDocumento:checked').each(function () {
        if (this.disabled) return; // respeta el bloqueo
        seleccionados.push({
          iCodTramite: $jq(this).data('id'),
          archivo: $jq(this).data('archivo')
        });
      });

      if (seleccionados.length === 0) {
        alert("Seleccione al menos un documento.");
        return;
      }

      fetch('comprimirMasivoPorAprobar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ documentos: seleccionados })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {
          const nombreZip = data.zipPath.replace('.7z', '');
          const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsMasivoPorAprobar.php?iCodTramite=${nombreZip}`;

          const paramPrev = {
            param_url: param_url,
            param_token: "123456",
            document_extension: "pdf"
          };
          const param = btoa(JSON.stringify(paramPrev));
          const port = "48596";

          startSignature(port, param);
        } else {
          alert("Error al preparar documentos.");
        }
      });
    });
  });

  // Mantengo nombres/flujo existentes
  function startSignature(port, param){
    try{
      window.FirmaPeru.startSignature(port, param, signatureInit, signatureOk, signatureCancel);
    }catch(e){
      alert("No se pudo iniciar Firma Perú.");
    }
  }

    // === Modal firmantes (igual patrón que bandejaFirma.php, sin iframes) ===
    function abrirModalFirmantes(url){
    const overlay = document.getElementById('modalFirmantes');
    const cont    = document.getElementById('contenidoModalFirmantes');
    cont.innerHTML = 'Cargando...';
    overlay.style.display = 'block';

    fetch(url, { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => { cont.innerHTML = html; })
      .catch(() => { cont.innerHTML = '<p style="color:#b00">No se pudo cargar firmantes.</p>'; });

    return false; // prevenir navegación
  }
  function cerrarModalFirmantes(){
    document.getElementById('modalFirmantes').style.display = 'none';
    document.getElementById('contenidoModalFirmantes').innerHTML = '';
  }

</script>
