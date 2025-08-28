<?php
include("head.php");
include("conexion/conexion.php");

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina = $_SESSION['iCodOficinaLogin'];
$iCodPerfil = $_SESSION['ID_PERFIL']; 

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
        M1.fFecRecepcion,
        M1.iCodTrabajadorDelegado,
        M1.iCodIndicacionDelegado,
        M1.cObservacionesDelegado,
        M1.fFecDelegado,
        ISNULL(TD.expediente, T.expediente) AS expediente,
        M1.extension AS extensionMovimiento,
        ISNULL(TD.extension, T.extension) AS extensionTramite,
        ISNULL(TD.iCodTramite, T.iCodTramite) AS iCodTramite,
        T.iCodTramite AS iCodTramitePadre,
        ISNULL(TD.cCodificacion, T.cCodificacion) AS cCodificacion,
        ISNULL(TD.cAsunto, T.cAsunto) AS cAsunto,
        ISNULL(TD.fFecRegistro, T.fFecRegistro) AS fFecRegistro,
        ISNULL(TD.documentoElectronico, T.documentoElectronico) AS documentoElectronico,
        O1.cNomOficina AS OficinaOrigen,
        O2.cNomOficina AS OficinaDestino
    FROM Tra_M_Tramite_Movimientos M1
    INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M1.iCodTramite
    LEFT JOIN Tra_M_Tramite TD ON TD.iCodTramite = M1.iCodTramiteDerivar
    INNER JOIN Tra_M_Oficinas O1 ON O1.iCodOficina = M1.iCodOficinaOrigen
    INNER JOIN Tra_M_Oficinas O2 ON O2.iCodOficina = M1.iCodOficinaDerivar
    WHERE 
    M1.iCodOficinaDerivar = ?       
    AND M1.iCodTrabajadorDelegado = ?   
        AND T.nFlgEnvio = 1
        AND M1.nEstadoMovimiento NOT IN (5, 6)
        AND NOT EXISTS (
            SELECT 1 
            FROM Tra_M_Tramite_Movimientos M2
            WHERE M2.iCodMovimientoDerivo = M1.iCodMovimiento
        )
    ORDER BY ISNULL(TD.fFecRegistro, T.fFecRegistro) DESC";


$params = [$iCodOficina, $iCodTrabajador];
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
:root {
  --primary: #005a86;
  --secondary: #c69157;
}

body {
  margin: 0;
  padding: 0;
}

/* Contenedor general para mantener separación del header */
body > .contenedor-principal {
  margin-top: 105px;
}

/* Barra azul superior */
.barra-titulo {
  background-color: var(--primary);
  color: white;
  padding: 8px 20px;
  font-weight: bold;
  font-size: 15px;
  width: 100vw;
  box-sizing: border-box;
  margin: 0;
}

/* Formulario de filtros a pantalla completa */
.filtros-formulario {
  display: flex;
  gap: 30px;
  background: white;
  border: 1px solid #ccc;
  border-radius: 0;
  padding: 20px 20px 10px;
  width: 100vw;
    box-sizing: border-box;
  flex-wrap: wrap;
  margin: 0;
}

.columna-izquierda {
  flex: 1;
  max-width: 40%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.columna-derecha {
  flex: 2;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.fila {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.input-container {
  position: relative;
  flex: 1;
  min-width: 120px;
}

.input-container input,
.input-container select {
  width: 100%;
  padding: 14px 12px 6px;
  font-size: 14px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  box-sizing: border-box;
  appearance: none;
  height: 42px;
  line-height: 1.2;
}

.input-container select:required:invalid {
  color: #aaa;
}

.input-container label {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  font-size: 13px;
  color: #666;
  background: white;
  padding: 0 4px;
  pointer-events: none;
  transition: 0.2s ease all;
}

.input-container input:focus + label,
.input-container input:not(:placeholder-shown) + label,
.input-container select:focus + label,
.input-container select:valid + label {
  top: -7px;
  font-size: 11px;
  color: #333;
  transform: translateY(0);
}

.input-container input[type="date"]:not(:placeholder-shown) + label,
.input-container input[type="date"]:valid + label {
  top: -7px;
  font-size: 11px;
  color: #333;
  transform: translateY(0);
}

.botones-filtro {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  margin-left: auto;
}

.btn-filtro {
  padding: 0 16px;
  font-size: 14px;
  border-radius: 4px;
  min-width: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: none;
  cursor: pointer;
  height: 42px;
  box-sizing: border-box;
}

.btn-primary {
  background-color: var(--primary);
  color: white;
}

.btn-secondary {
  background-color: var(--secondary);
  color: white;
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
    color: var(--primary); /* azul institucional */
    font-size: 18px;
    vertical-align: middle;
}
td.acciones .btn-link:hover {
    color: #00496b; /* tono más oscuro */
}
/* busqueda del profesional */

.typeable-wrap { position: relative; }
.typeable-select { width: 100%; }

.typeable-overlay {
  position: absolute; left: 0; top: 0; right: 0;
  height: 42px; display: none;
  border: 1px solid #ccc; border-radius: 4px;
  background: #fff; box-sizing: border-box;
  padding: 8px 10px;
}
.typeable-dropdown {
  position: absolute; left: 0; right: 0; top: 100%;
  background: #fff; border: 1px solid #ddd; border-radius: 6px;
  box-shadow: 0 6px 18px rgba(0,0,0,.08);
  max-height: 240px; overflow: auto; display: none; z-index: 2000;
  margin-top: 4px;
}
.typeable-item { padding: 8px 10px; cursor: pointer; }
.typeable-item:hover, .typeable-item.active { background: #f2f6ff; }
.typeable-item.current{
  background:#e9f5ff;
  color:#005a86;
  font-weight:600;
  cursor: default; /* no “mano” */
  display:flex; align-items:center; justify-content:space-between;
}
.typeable-badge{
  font-size:11px; padding:2px 6px; border-radius:999px; background:#d0e9ff;
}
</style>

<div class="contenedor-principal">

  <!-- TÍTULO PRINCIPAL PEGADO AL HEADER -->
  <div class="barra-titulo">BANDEJA DE ASIGNADOS AL PROFESIONAL</div>

    <!-- FORMULARIO OCUPANDO TODA LA PANTALLA -->
  <form class="filtros-formulario">
    <!-- COLUMNA IZQUIERDA -->
    <div class="columna-izquierda">
      <div class="fila">
        <div class="input-container">
          <input type="text" name="anio" placeholder=" " required>
          <label>Año</label>
        </div>
        <div class="input-container">
          <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" " required>
          <label>N° Expediente</label>
        </div>
        <div class="input-container">
          <input type="text" name="extension" value="<?= $valorExtension ?>" placeholder=" " required>
          <label>Extensión</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <select name="oficina_origen" required>
            <option value="" disabled selected hidden></option>
            <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $of['cNomOficina'] ?>"><?= $of['cNomOficina'] ?></option>
            <?php endwhile; ?>
          </select>
          <label>Oficina de Origen</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" " required>
          <label>Desde</label>
        </div>
        <div class="input-container">
          <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" " required>
          <label>Hasta</label>
        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div class="columna-derecha">
      <div class="fila">
        <div class="input-container">
          <input type="text" name="tipo_tramite" placeholder=" " required>
          <label>Tipo de Trámite</label>
        </div>
        <div class="input-container">
          <select id="tipoDocumento" name="tipoDocumento" required>
            <option value="" disabled selected hidden></option>
            <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $td['cCodTipoDoc'] ?>"><?= $td['cDescTipoDoc'] ?></option>
            <?php endwhile; ?>
          </select>
          <label for="tipoDocumento">Tipo de Documento</label>
        </div>
        <div class="input-container">
          <input type="text" name="nro_documento" placeholder=" " required>
          <label>Nro de Documento</label>
        </div>
        <div class="input-container">
          <input type="text" name="estado" placeholder=" " required>
          <label>Estado</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container" style="flex: 1;">
          <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" " required>
          <label>Asunto</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <input type="text" name="operador" placeholder=" " required>
          <label>Operador</label>
        </div>
        <div class="input-container">
          <input type="text" name="delegado" placeholder=" " required>
          <label>Delegado a</label>
        </div>
        <div class="botones-filtro">
          <button type="submit" class="btn-filtro btn-primary">
            <span class="material-icons">search</span> Buscar
          </button>
          <button type="button" class="btn-filtro btn-secondary" onclick="window.location.href='bandejaPendientes.php'">
            <span class="material-icons">autorenew</span> Reestablecer
          </button>
        </div>
      </div>
    </div>
  </form>

  <!-- BARRA DE REGISTROS PEGADA AL FORMULARIO -->
  <div class="barra-titulo">REGISTROS</div>
</div>

<table class="table table-bordered">
    <thead class="table-secondary">
        <tr>                 
        <th style="width: 110px;">Expediente</th>
        <th style="width: 90px;">Extensión</th>
        <th style="width: 300px;">Documento</th>
        <th style="width: 260px;">Asunto</th>
                <th style="width: 180px;">Derivado por</th> 
                <th style="width: 90px;">Estado</th>
                <th style="width: 220px;">Opciones</th>
                        </tr>
        </thead>
        <tbody>
            <?php foreach ($tramites as $tramite): ?>
                <tr id="fila-<?= $tramite['iCodMovimiento'] ?>">
                <td><?= htmlspecialchars($tramite['expediente']) ?></td>   
                <td><?= htmlspecialchars($tramite['extensionMovimiento']) ?></td> <!-- o extensionTramite si prefieres -->
                    <td>
                    <!-- Botón de flujo -->
                    <?php if (intval($tramite['extensionTramite']) === 1): ?>
                        <a href="bandejaFlujo.php?iCodTramite=<?= $tramite['iCodTramitePadre'] ?>"
                            title="Ver flujo raíz"
                            target="_blank"
                            style="color: #6c757d; text-decoration: none;">
                                <span class="material-icons" style="font-size: 22px;">device_hub</span>
                            </a>
                        <?php else: ?>
                            <a href="bandejaFlujo.php?iCodTramite=<?= $tramite['iCodTramitePadre'] ?>&extension=<?= $tramite['extensionMovimiento'] ?>"
                            title="Ver flujo"
                            target="_blank"
                            style="color: #6c757d; text-decoration: none;">
                                <span class="material-icons" style="font-size: 22px;">device_hub</span>
                            </a>
                        <?php endif; ?>

                    <?php if (!empty($tramite['documentoElectronico'])): ?>
                        <a href="./cDocumentosFirmados/<?= urlencode($tramite['documentoElectronico']) ?>"
                        class="chip-adjunto"
                        target="_blank"
                        title="<?= htmlspecialchars($tramite['documentoElectronico']) ?>">
                            <span class="material-icons chip-icon">picture_as_pdf</span>
                            <span class="chip-text"><?= htmlspecialchars($tramite['documentoElectronico']) ?></span>
                        </a>
                    <?php else: ?>
                        <span>Sin documento</span>
                    <?php endif; ?>

                     </td>
                       <td><?= htmlspecialchars($tramite['cAsunto']) ?></td>
                       <td>
                        <?= htmlspecialchars($tramite['OficinaOrigen']) ?><br>
                        <small style="color: gray; font-size: 12px;">
                            <?= isset($tramite['fFecRegistro']) ? $tramite['fFecRegistro']->format("d/m/Y H:i") : '' ?>
                        </small>
                    </td>
                    <td id="estado-<?= $tramite['iCodMovimiento'] ?>">
                        <?php if ($tramite['nEstadoMovimiento'] == 0): ?>
                            <span style="font-weight: bold; color: #d9534f;">Pendiente</span>
                        <?php else: ?>
                            <span style="font-weight: bold; color: #0d6efd;">En proceso</span>
                            <br>
                            <small style="color: gray;">
                                <?= isset($tramite['fFecRecepcion']) ? $tramite['fFecRecepcion']->format("d/m/Y H:i") : '' ?>
                            </small>
                        <?php endif; ?>
                    </td>
                   <td class="acciones" id="acciones-<?= $tramite['iCodMovimiento'] ?>">
                    <?php if ($tramite['nEstadoMovimiento'] == 0): ?>
                        <!-- Aceptar -->
                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">

                        <button class="btn btn-primary aceptar-btn" 
                                data-movimiento="<?= $tramite['iCodMovimiento'] ?>" 
                                data-tramite="<?= $tramite['iCodTramite'] ?>"
                                title="Aceptar">
                            <span class="material-icons">drafts</span>
                        </button>

                        <!-- Observar -->
                        <!-- <button class= "btn btn-secondary "
                                data-movimiento="<?= $tramite['iCodMovimiento'] ?>" 
                                data-expediente="<?= $tramite['expediente'] ?>"
                                title="Observar">
                            <span class="material-icons">visibility_off</span>
                        </button> -->
                        </div>
                    <?php else: ?>
                        <!-- Agrupar todos los botones en un mismo contenedor flex -->
                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                <!-- Derivar -->
                        <a href="registroDerivar.php?iCodTramite=<?= $tramite['iCodTramite'] ?>&iCodMovimiento=<?= $tramite['iCodMovimiento'] ?>" 
                            class="btn btn-link" title="Derivar">
                            <span class="material-icons">forward_to_inbox</span>
                        </a>

                        <button class="btn btn-link delegar-btn" 
                            data-tramite="<?= $tramite['iCodTramite'] ?>" 
                            data-movimiento="<?= $tramite['iCodMovimiento'] ?>"
                            data-expediente="<?= $tramite['expediente'] ?>"
                            data-delegado="<?= $tramite['iCodTrabajadorDelegado'] ?? '' ?>"
                            data-indicacion="<?= $tramite['iCodIndicacionDelegado'] ?? '' ?>"
                            data-observacion="<?= htmlspecialchars($tramite['cObservacionesDelegado'] ?? '', ENT_QUOTES) ?>"
                            data-fechadelegado="<?= $tramite['fFecDelegado'] ? $tramite['fFecDelegado']->format('d/m/Y H:i') : '' ?>"
                            title="Delegar">
                            <span class="material-icons">cases</span>
                        </button>
               
                           <!-- Finalizar-->
                            <!-- Finalizar -->
                            <a href="finalizarMovimiento.php?iCodMovimiento=<?= $tramite['iCodMovimiento'] ?>" 
                            class="btn btn-link" title="Finalizar">
                            <span class="material-icons">system_update_alt</span>
                            </a>

                        <!-- Crear Extensión -->
                        <button class="btn btn-link" title="Crear Extensión" onclick="crearExtension(<?= $tramite['iCodMovimiento'] ?>, <?= $tramite['iCodTramite'] ?>)">
                            <span class="material-icons">content_copy</span>
                        </button>
                    </div>
                        <?php endif; ?>
</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
 
 

<!-- INICIO MODAL DELEGAR -->
<div id="modalDelegar" class="modal">
  <form id="formDelegar" class="modal-content small">
    <input type="hidden" name="iCodMovimiento">
    <input type="hidden" name="iCodTramite">

    <h2 style="margin-bottom: 10px;">Delegar Expediente</h2>
    <p id="expedienteDelegar" style="margin-bottom: 5px; font-weight: bold;"></p>
    <p id="fechaDelegadoTexto" style="margin-bottom: 15px; font-style: italic; color: #666;"></p>

      <!-- PROFESIONAL -->
<div style="margin-bottom: 15px;">
  <label style="display: block; font-weight: bold; margin-bottom: 6px;">PROFESIONAL DE LA OFICINA</label>

  <!-- wrapper para el modo “escribible” -->
  <div class="typeable-wrap">
    <select name="iCodTrabajadorDelegado"
            id="iCodTrabajadorDelegado"
            class="typeable-select"
            style="width: 100%; padding: 8px;"
            required>
      <option value="">Seleccione un profesional</option>
      <?php
        $sqlTrabajadores = "
          SELECT T.iCodTrabajador,
                 CONCAT(T.cApellidosTrabajador, ', ', T.cNombresTrabajador) AS nombre
          FROM Tra_M_Trabajadores T
          INNER JOIN Tra_M_Perfil_Ususario PU ON T.iCodTrabajador = PU.iCodTrabajador
          WHERE PU.iCodOficina = ? AND PU.iCodPerfil = 4
          ORDER BY nombre";
        $stmtTrab = sqlsrv_query($cnx, $sqlTrabajadores, [$iCodOficina]);
        while ($trab = sqlsrv_fetch_array($stmtTrab, SQLSRV_FETCH_ASSOC)) {
            $val = (int)$trab['iCodTrabajador'];
            $txt = htmlspecialchars($trab['nombre'], ENT_QUOTES, 'UTF-8');
            echo "<option value=\"{$val}\">{$txt}</option>";
        }
      ?>
    </select>
  </div>
</div>
<!-- /PROFESIONAL -->

    <!-- INDICACION -->
    <div style="margin-bottom: 15px;">
      <label style="display: block; font-weight: bold; margin-bottom: 6px;">INDICACIÓN</label>
      <select name="iCodIndicacionDelegado" id="iCodIndicacionDelegado" style="width: 100%; padding: 8px;" required>
        <option value="">Seleccione una indicación</option>
        <?php
          $sqlInd = "
            SELECT iCodIndicacion, cIndicacion,
                CASE WHEN cIndicacion = 'INDAGACION DE MERCADO' THEN 0 ELSE 1 END AS prioridad
            FROM Tra_M_Indicaciones
            ORDER BY prioridad, iCodIndicacion";
          $stmtInd = sqlsrv_query($cnx, $sqlInd);
          while ($ind = sqlsrv_fetch_array($stmtInd, SQLSRV_FETCH_ASSOC)) {
            echo "<option value='{$ind['iCodIndicacion']}'>{$ind['cIndicacion']}</option>";
          }
        ?>
      </select>
    </div>

    <!-- OBSERVACIONES -->
    <div style="margin-bottom: 20px;">
      <label style="display: block; font-weight: bold; margin-bottom: 6px;">OBSERVACIONES</label>
      <textarea name="cObservacionesDelegado" id="cObservacionesDelegado" rows="4" style="width: 100%; padding: 8px;"></textarea>
    </div>

    <!-- BOTONES -->
    <div style="text-align: right;">
      <button type="button" class="btn-secondary cerrarModalDelegar" style="margin-right: 10px;">Cancelar</button>
      <button type="submit" class="btn-primary">Guardar</button>
    </div>
  </form>
</div>
<!-- FIN MODAL DELEGAR-->

<!-- MODAL OBSERVAR -->
<div id="modalObservar" class="modal">
    <form id="formObservar" class="modal-content small">
        <input type="hidden" name="iCodMovimiento" id="movimientoObservar">
        <span class="modal-close cerrarModal" onclick="cerrarModal('modalObservar')">&times;</span>
        <h2>Observar Expediente <span id="expedienteObservar"></span></h2>

        <div style="margin-top: 20px;">
            <label style="font-weight: bold;">Observaciones</label>
            <textarea name="cObservacionesEnviar" rows="5" style="width: 100%; padding: 10px;" required></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <button type="button" class="btn-secondary cerrarModal" onclick="cerrarModal('modalObservar')">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar Observación</button>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const extension = this.dataset.extension ?? 1;
        window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + extension, '_blank');

    });
});

document.querySelectorAll('.cerrarModal').forEach((el) => {
    el.addEventListener('click', function() {
        document.getElementById('modalFlujo').style.display = 'none';
     });
})

window.addEventListener('click', function(event) {
    const modal = document.getElementById('modalFlujo');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
 

function crearExtension(iCodMovimiento, iCodTramite) {
    const url = `generarExtension.php?iCodMovimiento=${iCodMovimiento}&iCodTramite=${iCodTramite}`;
    window.open(url, '_blank', 'width=1250,height=550,scrollbars=yes,resizable=yes');
}

// INICIO JS DELEGAR

// Abrir modal con datos existentes
document.querySelectorAll(".delegar-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = document.getElementById("formDelegar");

    // Rellenar campos ocultos
    form.iCodMovimiento.value = btn.dataset.movimiento;
    form.iCodTramite.value = btn.dataset.tramite;

    // Mostrar expediente
    document.getElementById("expedienteDelegar").textContent = "Expediente: " + btn.dataset.expediente;

    // Mostrar fecha si ya fue delegado
    const fechaDelegado = btn.dataset.fechadelegado;
    document.getElementById("fechaDelegadoTexto").textContent = fechaDelegado 
      ? "Delegado anteriormente el " + fechaDelegado 
      : "";

    // Rellenar los valores seleccionados si existen
    document.getElementById("iCodTrabajadorDelegado").value = btn.dataset.delegado || '';
    document.getElementById("iCodIndicacionDelegado").value = btn.dataset.indicacion || '';
    document.getElementById("cObservacionesDelegado").value = btn.dataset.observacion || '';

    document.getElementById("modalDelegar").style.display = "block";
  });
});

// Botón para registrar atención directa
document.querySelectorAll(".atender-btn").forEach(btn => {
  btn.addEventListener("click", async () => {
    const confirmado = confirm("¿Desea registrar la atención del trámite?");
    if (!confirmado) return;

    const body = new FormData();
    body.append("iCodMovimiento", btn.dataset.movimiento);
    body.append("iCodTramite", btn.dataset.tramite);
    body.append("autoAtiende", "1");

    const res = await fetch("delegarMovimiento.php", { method: "POST", body });
    const json = await res.json();

    if (json.status === "ok") {
      alert("Atención registrada.");
      location.reload();
    } else {
      alert("Error: " + json.message);
    }
  });
});

// Guardar delegación
document.getElementById("formDelegar").addEventListener("submit", async e => {
  e.preventDefault();
  const body = new FormData(e.target);

  const res = await fetch("delegarMovimiento.php", { method: "POST", body });
  const json = await res.json();

  if (json.status === "ok") {
    alert("Delegación registrada.");
    location.reload();
  } else {
    alert("Error: " + json.message);
  }
});

// Cerrar modal
document.querySelectorAll('.cerrarModalDelegar').forEach(el => {
  el.addEventListener('click', () => {
    document.getElementById('modalDelegar').style.display = 'none';
  });
});

// Normaliza tildes para una búsqueda amable
function fold(s){ return s.normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase(); }

function makeTypeable(select){
  if (select.dataset.typeableApplied) return; // evitar doble init
  select.dataset.typeableApplied = '1';

  // Envolver
  const wrap = document.createElement('div');
  wrap.className = 'typeable-wrap';
  select.parentNode.insertBefore(wrap, select);
  wrap.appendChild(select);

  // Overlay input (aparece para escribir)
  const overlay = document.createElement('input');
  overlay.type = 'text';
  overlay.className = 'typeable-overlay';
  overlay.autocomplete = 'off';

  // Dropdown con resultados
  const dropdown = document.createElement('div');
  dropdown.className = 'typeable-dropdown';

  wrap.appendChild(overlay);
  wrap.appendChild(dropdown);

  // Cache de opciones
  const options = Array.from(select.options).map(o => ({ value: o.value, label: o.text }));

  function labelOfValue(v){
    const o = options.find(x => x.value === v);
    return o ? o.label : '';
  }

  // Abrir modo escritura
  function openTypeMode(){
    // Prefill con la etiqueta actual seleccionada
    overlay.value = labelOfValue(select.value) || '';
    // Igualar altura del select (42px ya funciona con tu CSS)
    overlay.style.display = 'block';
    select.style.visibility = 'hidden';
    renderList('', true); // ← mostrar TODOS (con el actual arriba sombreado)
    overlay.focus();
    overlay.select();
  }

  // Cerrar modo escritura
  function closeTypeMode(){
    dropdown.style.display = 'none';
    overlay.style.display = 'none';
    select.style.visibility = 'visible';
  }

  // Render/filtrado
  let activeIndex = -1;
  function items(){ return Array.from(dropdown.querySelectorAll('.typeable-item')); }
  function clearActive(){ items().forEach(i => i.classList.remove('active')); }
  function setActive(i){ clearActive(); const arr = items(); if (arr[i]) { arr[i].classList.add('active'); activeIndex = i; } }

  function renderList(query, showAll){
  const q = fold((query || '').trim());
  const currentVal   = select.value;
  const currentLabel = labelOfValue(currentVal);

  dropdown.innerHTML = ''; // ← debe ser string vacío

  // 1) Construir base (sin placeholder)
  let data = options.filter(o => o.value !== '');

  // 2) Filtrar por texto si corresponde
  if (!showAll && q) {
    data = data.filter(o => fold(o.label).includes(q));
  }

  // 3) Insertar “item actual” arriba (si hay uno y pasa el filtro)
  const itemsToRender = [];
  if (currentVal && (!q || fold(currentLabel).includes(q))) {
    itemsToRender.push({ value: currentVal, label: currentLabel, isCurrent: true });
  }

  // 4) Agregar el resto (excluyendo el actual)
  data.forEach(o => {
    if (o.value !== currentVal) itemsToRender.push({ ...o, isCurrent: false });
  });

  // 5) Render
  dropdown.innerHTML = '';
  itemsToRender.forEach((o, idx) => {
    const el = document.createElement('div');
    el.className = 'typeable-item' + (o.isCurrent ? ' current' : '');
    el.textContent = o.label;

    if (o.isCurrent) {
      // Etiqueta “Actual” al lado derecho
      const badge = document.createElement('span');
      badge.className = 'typeable-badge';
      badge.textContent = 'Actual';
      el.appendChild(badge);

      // Clic sobre el actual: solo cerrar (no cambia valor)
      el.onmousedown = () => closeTypeMode();
    } else {
      el.dataset.value = o.value;
      el.onmousedown = () => applyValue(o.value);
    }

    dropdown.appendChild(el);
  });

  if (itemsToRender.length) {
    dropdown.style.display = 'block';
    // Si el primero es “actual”, hacer activo el siguiente
    if (itemsToRender[0]?.isCurrent && itemsToRender.length > 1) {
      activeIndex = 1;
    } else {
      activeIndex = 0;
    }
    setActive(activeIndex);
  } else {
    dropdown.style.display = 'none';
    activeIndex = -1;
  }
}


  // Aplicar selección al <select>
  function applyValue(v){
    select.value = v;
    select.dispatchEvent(new Event('change', {bubbles:true}));
    closeTypeMode();
  }

  // Eventos
  select.addEventListener('mousedown', (e) => {
    e.preventDefault(); // evita que el select abra su lista nativa
    openTypeMode();
  });
  select.addEventListener('keydown', (e) => {
    e.preventDefault();
    openTypeMode();
  });

  overlay.addEventListener('input', () => renderList(overlay.value, false)); // ← filtra por texto
  overlay.addEventListener('keydown', (e) => {
    const arr = items();
    if (e.key === 'ArrowDown') {
      if (!arr.length) return;
      setActive(Math.min(arr.length-1, activeIndex+1));
      e.preventDefault();
    } else if (e.key === 'ArrowUp') {
      if (!arr.length) return;
      setActive(Math.max(0, activeIndex-1));
      e.preventDefault();
    } else if (e.key === 'Enter') {
      if (arr[activeIndex]) { arr[activeIndex].dispatchEvent(new Event('mousedown')); }
      e.preventDefault();
    } else if (e.key === 'Escape') {
      closeTypeMode();
      e.preventDefault();
    }
  });

  // Cerrar si se hace click fuera
  document.addEventListener('mousedown', (ev) => {
    if (!wrap.contains(ev.target)) closeTypeMode();
  });

  // Si alguien (tu código) cambia el select, mantener sincronía silenciosa
  select.addEventListener('change', () => {
    // No abrimos ni mostramos overlay; solo dejamos listo su prefill para la próxima vez
    // overlay.value = labelOfValue(select.value) || '';
  });
}

// Inicializa ambos selects del modal
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#modalDelegar .typeable-select').forEach(makeTypeable);
});

// ⚠️ Importante: cuando abres el modal y seteas valores desde data-*,
// dispara 'change' para asegurar que el seleccionado actual ya está aplicado.
(function patchModalOpen(){
  const openers = document.querySelectorAll(".delegar-btn");
  openers.forEach(btn => {
    btn.addEventListener("click", () => {
      const selTrab = document.getElementById("iCodTrabajadorDelegado");
      const selInd  = document.getElementById("iCodIndicacionDelegado");

      // tú ya haces esto:
      // selTrab.value = btn.dataset.delegado || '';
      // selInd.value  = btn.dataset.indicacion || '';

      // añade:
      selTrab.dispatchEvent(new Event('change', {bubbles:true}));
      selInd.dispatchEvent(new Event('change', {bubbles:true}));
    });
  });
})();

// Si recreas botones dinámicamente (aceptar -> reactivarEventosDinamicos),
// no hace falta reinit de combos porque los selects ya existen dentro del modal.
// Solo asegúrate de volver a enganchar el listener del botón que abre el modal
// (tu función reactivarEventosDinamicos ya lo hace).

// FIN JS DELEGAR

document.querySelectorAll(".aceptar-btn").forEach(btn => {
    btn.addEventListener("click", async () => {
        const iCodMovimiento = btn.dataset.movimiento;
        const iCodTramite = btn.dataset.tramite;

        if (!confirm("¿Desea aceptar este expediente?")) return;

        try {
            const res = await fetch("aceptarMovimiento.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `iCodMovimiento=${encodeURIComponent(iCodMovimiento)}`
            });

            const json = await res.json();

            if (json.status === "ok") {
                // ✅ Reemplazar columna ACCIONES
                const accionesTd = document.getElementById("acciones-" + iCodMovimiento);
                accionesTd.innerHTML = `
                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                        

                        <a href="registroDerivar.php?iCodTramite=${iCodTramite}&iCodMovimiento=${iCodMovimiento}" 
                           class="btn btn-link" title="Derivar">
                            <span class="material-icons">forward_to_inbox</span>
                        </a>

                        <button class="btn btn-link delegar-btn" 
                                data-tramite="${iCodTramite}" 
                                data-movimiento="${iCodMovimiento}"
                                title="Delegar">
                            <span class="material-icons">cases</span>
                        </button>

                        <a href="finalizarMovimiento.php?iCodMovimiento=${iCodMovimiento}" 
                        class="btn btn-link" title="Finalizar">
                        <span class="material-icons">system_update_alt</span>
                      </a>

                        <button class="btn btn-link" title="Crear Extensión" onclick="crearExtension(${iCodMovimiento}, ${iCodTramite})">
                            <span class="material-icons">content_copy</span>
                        </button>
                    </div>
                `;

                // ✅ Actualizar columna ESTADO
                const estadoTd = document.getElementById("estado-" + iCodMovimiento);
                if (estadoTd) {
                    const ahora = new Date().toLocaleString("es-PE", {
                        day: "2-digit",
                        month: "2-digit",
                        year: "numeric",
                        hour: "2-digit",
                        minute: "2-digit"
                    });
                    estadoTd.innerHTML = `
                        <span style="font-weight: bold; color: #0d6efd;">En proceso</span><br>
                        <small style="color: gray;">${ahora}</small>
                    `;
                }

                // ✅ Reactivar eventos como delegar y flujo
                reactivarEventosDinamicos();
            } else {
                alert("Error: " + json.message);
            }

        } catch (err) {
            console.error(err);
            alert("Error al procesar la solicitud.");
        }
    });
});


// Reactivar eventos luego de reemplazar HTML dinámicamente
function reactivarEventosDinamicos() {
    document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
        btn.onclick = () => {
            const id = btn.dataset.id;
            const extension = btn.dataset.extension ?? 1;
            window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + extension, '_blank');
        };
    });

    document.querySelectorAll(".delegar-btn").forEach(btn => {
        btn.onclick = () => {
            const form = document.getElementById("formDelegar");
            form.iCodMovimiento.value = btn.dataset.movimiento;
            form.iCodTramite.value = btn.dataset.tramite;
            document.getElementById("modalDelegar").style.display = "block";
        };
    });
}

// Botón "Observar"
document.querySelectorAll(".observar-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const iCodMovimiento = btn.dataset.movimiento;
        const expediente = btn.dataset.expediente;

        document.getElementById("movimientoObservar").value = iCodMovimiento;
        document.getElementById("expedienteObservar").textContent = expediente;

        document.getElementById("modalObservar").style.display = "block";
    });
});

// Enviar formulario de observación
document.getElementById("formObservar").addEventListener("submit", async e => {
    e.preventDefault();
    const form = e.target;
    const body = new FormData(form);

    try {
        const res = await fetch("observarMovimiento.php", {
            method: "POST",
            body
        });

        const json = await res.json();
        if (json.status === "ok") {
            alert("Movimiento observado correctamente.");
            location.reload();
        } else {
            alert("Error: " + json.message);
        }
    } catch (err) {
        console.error(err);
        alert("Error al procesar la observación.");
    }
});

// Función genérica para cerrar cualquier modal por ID
function cerrarModal(id) {
    document.getElementById(id).style.display = "none";
}
</script>