<?php
include("head.php");
include("conexion/conexion.php");

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina = $_SESSION['iCodOficinaLogin'];

// Capturar filtros
$filtroExpediente = isset($_GET['expediente']) ? trim($_GET['expediente']) : '';
$valorExpediente  = htmlspecialchars($filtroExpediente);

$filtroExtension = isset($_GET['extension']) ? trim($_GET['extension']) : '';
$valorExtension  = htmlspecialchars($filtroExtension);

$filtroAsunto = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valorAsunto  = htmlspecialchars($filtroAsunto);

$filtroDesde = isset($_GET['desde']) ? $_GET['desde'] : '';
$valorDesde  = htmlspecialchars($filtroDesde);

$filtroHasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';
$valorHasta  = htmlspecialchars($filtroHasta);

// Obtener tipos de documento internos
$tipoDocQuery = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

// Obtener oficinas
$oficinasQuery = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

$tipoDocQuery = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

$oficinasQuery = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

$sql = "
    SELECT 
        M1.iCodMovimiento,
        M1.nEstadoMovimiento,
        T.expediente,
        M1.extension,
        T.iCodTramite,
        T.cCodificacion,
        T.cAsunto,
        T.fFecRegistro,
        T.documentoElectronico,
        M1.cDocumentoFinalizacion,
        M1.cObservacionesFinalizar,
        O1.cNomOficina AS OficinaOrigen,
        O2.cNomOficina AS OficinaDestino
    FROM Tra_M_Tramite_Movimientos M1
    INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M1.iCodTramite
    INNER JOIN Tra_M_Oficinas O1 ON O1.iCodOficina = M1.iCodOficinaOrigen
    INNER JOIN Tra_M_Oficinas O2 ON O2.iCodOficina = M1.iCodOficinaDerivar
    WHERE M1.iCodOficinaDerivar = ?
    AND M1.nEstadoMovimiento = 5
    ORDER BY T.fFecRegistro DESC";

$params = [$iCodOficina];
$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

$tramites = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $row;
}
?>
 

<!-- Material Icons y CSS -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
 <style>
    .row {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.input-container {
  position: relative;
  flex: 1;
  min-width: 250px;
}
.input-container input,
.input-container select {
  width: 100%;
  padding: 20px 12px 8px;
  font-size: 15px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #fff;
  box-sizing: border-box;
}
.input-container label {
  position: absolute;
  top: 20px;
  left: 12px;
  font-size: 14px;
  color: #666;
  background: #fff;
  padding: 0 4px;
  pointer-events: none;
  transition: 0.2s ease;
}
.input-container input:focus + label,
.input-container input:not(:placeholder-shown) + label,
.input-container select:focus + label,
.input-container select:valid + label {
  top: 0px;
  font-size: 12px;
  color: #333;
}
.titulo-principal {
  color: var(--primary, #005a86);
  font-size: 22px;
  font-weight: bold;
  margin-top: 0;      /* 🔧 quitar espacio innecesario arriba */
  margin-bottom: 20px;
}
.button-row a.btn {
    text-decoration: none;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content.small{
    max-width: 450px;
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 20px;
    width: 90%;
    max-width: 1000px;
    border-radius: 8px;
    position: relative;
}
.modal-close {
    position: absolute;
    top: 10px; right: 20px;
    font-size: 24px;
    cursor: pointer;
}
td.acciones .btn-link {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #364897;
    font-size: 18px;
    vertical-align: middle;
}
td.acciones .btn-link:hover {
    color: #1a237e;
}
 </style>

<div class="container" style="margin: 120px auto; max-width: 1200px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
<div class="titulo-principal">BANDEJA DE DOCUMENTOS FINALIZADOS</div>

<div class="card">
    <div class="card-title">CRITERIOS DE BÚSQUEDA</div>
    <form>

        <!-- FILA 1 -->
  <div class="row">
    <!-- Grupo: Documentos -->
    <div style="flex: 1; display: flex; align-items: center; gap: 10px;">
      <label style="font-weight:bold;">Enviado</label>
      <label><input type="checkbox" name="tipo_doc_externo"> SI</label>
      <label><input type="checkbox" name="tipo_doc_interno"> NO</label>
    </div>

    <!-- Grupo: Desde - Hasta -->
    <div style="flex: 2; display: flex; gap: 20px;">
      <div class="input-container" style="flex: 1;">
        <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" ">
        <label>Desde</label>
      </div>
      <div class="input-container" style="flex: 1;">
        <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" ">
        <label>Hasta</label>
      </div>
    </div>
  </div>

  <!-- FILA 2 -->
  <div class="row">
    <div class="input-container" style="flex: 1;">
      <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" ">
      <label>N° Expediente</label>
    </div>
    <div class="input-container" style="flex: 2;">
      <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" ">
      <label>Asunto</label>
    </div>
  </div>

  <!-- FILA 3 -->
  <div class="row">
    <div class="input-container select-flotante" style="flex: 1;">
      <select name="tipo_documento" required>
        <option value="" disabled selected hidden> </option>
        <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): ?>
          <option value="<?= $td['cDescTipoDoc'] ?>"><?= $td['cDescTipoDoc'] ?></option>
        <?php endwhile; ?>
      </select>
      <label>Tipo de Documento</label>
    </div>

    <div class="input-container select-flotante" style="flex: 2;">
      <select name="oficina_destino" required>
        <option value="" disabled selected hidden> </option>
        <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
          <option value="<?= $of['cNomOficina'] ?>"><?= $of['cNomOficina'] ?></option>
        <?php endwhile; ?>
      </select>
      <label>Oficina de Destino</label>
    </div>
  </div>

 

        <!-- FILA 5: Botones -->
        <div class="row" style="justify-content: flex-end;">
        <button type="submit" class="btn btn-primary">
            <span class="material-icons">search</span> Buscar
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='bandejaPendientes.php'">
            <span class="material-icons">autorenew</span> Reestablecer
        </button>
        </div>

    </form>
</div>

<div style="text-align: center; font-weight: bold; color: var(--primary); font-size: 18px; margin: 30px 0 10px;">
REGISTROS
</div>

<table class="table table-bordered">
    <thead class="table-secondary">
        <tr>                 
                <th>Expediente</th>
                <th>Extensión</th>
                <th>Documento</th>
                <th>Asunto</th>
                <th>Oficina de Origen</th>
      <th>Opciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tramites as $tramite): ?>
                <tr>
                <td><?= htmlspecialchars($tramite['expediente']) ?></td>   
                    <td><?= htmlspecialchars($tramite['extension']) ?></td>   
                    <td>
                    <?php if (!empty($tramite['cDocumentoFinalizacion'])): ?>
            <a href="./cAlmacenArchivos/<?= rawurlencode($tramite['cDocumentoFinalizacion']) ?>" target="_blank">
              <span class="material-icons">insert_drive_file</span>
            </a>
          <?php else: ?>
            <span style="color: gray;">Sin documento</span>
          <?php endif; ?>
        </td>               
        <td><?= htmlspecialchars($tramite['cAsunto']) ?></td>
        <td><?= htmlspecialchars($tramite['OficinaOrigen']) ?></td>    
                    <td class="acciones">
                        <!-- Ver Flujo -->
                        <button class="btn btn-link ver-flujo-btn" data-id="<?= $tramite['iCodTramite'] ?>" data-extension="<?= $tramite['extension'] ?? 1 ?>" title="Ver Flujo">
                        <span class="material-icons">device_hub</span>
          </button>
          <!-- Revertir -->
          <button class="btn btn-link" title="Revertir Finalización" onclick="revertirFinalizacion(<?= $tramite['iCodMovimiento'] ?>)">
            <span class="material-icons">undo</span>
          </button>
          <!-- Editar -->
          <button class="btn btn-link" title="Editar Finalización" onclick="abrirEditarFinalizacion(<?= $tramite['iCodMovimiento'] ?>, '<?= addslashes($tramite['expediente']) ?>')">
            <span class="material-icons">edit</span>
          </button>      
</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table> 


  <!-- INICIO modal editar finalizacion -->
    <div id="modalEditarFinal" class="modal">
  <form id="formEditarFinal" class="modal-content small" enctype="multipart/form-data">
    <input type="hidden" name="iCodMovimiento" id="editCodMovimiento">
    <span class="modal-close cerrarModal" onclick="cerrarModal('modalEditarFinal')">&times;</span>
    <h2>Editar Finalización <span id="expedienteEditar"></span></h2>

    <div style="margin-bottom: 1rem;">
      <label>Observaciones</label>
      <textarea name="observaciones" id="editObservaciones" rows="4" style="width: 100%;"></textarea>
    </div>
   <!-- 🔽 Aquí se insertará dinámicamente el documento actual -->
   <div id="archivoFinalActual" style="margin-bottom: 1rem;"></div>

    <div style="margin-bottom: 1rem;">
      <label>Nuevo documento (opcional)</label>
      <input type="file" name="archivoFinal" accept="application/pdf">
    </div>

    <div style="text-align: right;">
      <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarFinal')">Cancelar</button>
      <button type="submit" class="btn-primary">Guardar Cambios</button>
    </div>
  </form>
</div>
  <!-- FIN modal editar finalizacion -->

<script>
document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const extension = this.dataset.extension ?? 1;
        window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + extension, '_blank');
    });
});
 
function revertirFinalizacion(iCodMovimiento) {
  if (!confirm("¿Deseas revertir esta finalización?")) return;

  fetch("revertirFinalizacion.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `iCodMovimiento=${iCodMovimiento}`
  })
  .then(res => res.json())
  .then(json => {
    if (json.status === "ok") {
      alert("Finalización revertida.");
      location.reload();
    } else {
      alert("Error: " + json.message);
    }
  });
}

function abrirEditarFinalizacion(iCodMovimiento, expediente) {
  fetch(`getDocumentoFinal.php?iCodMovimiento=${iCodMovimiento}`)
    .then(res => res.json())
    .then(json => {
      if (json.status === 'ok') {
        document.getElementById("editCodMovimiento").value = iCodMovimiento;
        document.getElementById("editObservaciones").value = json.observaciones ?? '';
        document.getElementById("expedienteEditar").innerText = expediente;

        const contenedor = document.getElementById("archivoFinalActual");
        if (json.nombre) {
          contenedor.innerHTML = `
            <div style="margin-bottom: 1rem;">
              <strong>Documento Actual:</strong><br>
              <a href="cAlmacenArchivos/${encodeURIComponent(json.nombre)}" target="_blank" style="color: #0066cc;">
                <span class="material-icons">insert_drive_file</span> ${json.nombre}
              </a>
            </div>`;
        } else {
          contenedor.innerHTML = '';
        }

        document.getElementById("modalEditarFinal").style.display = "block";
      } else {
        alert("No se pudo cargar los datos.");
      }
    });
}



function cerrarModal(id) {
  document.getElementById(id).style.display = 'none';
}

// Guardar edición
document.getElementById("formEditarFinal")?.addEventListener("submit", async function(e) {
  e.preventDefault();
  const form = e.target;
  const body = new FormData(form);

  const res = await fetch("editarFinalizacion.php", { method: "POST", body });
  const json = await res.json();

  if (json.status === "ok") {
    alert("Finalización actualizada.");
    location.reload();
  } else {
    alert("Error: " + json.message);
  }
});
</script>

