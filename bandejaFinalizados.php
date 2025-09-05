<?php
include("head.php");
include("conexion/conexion.php");
// Inyecta el CSS base de bandejas (después del head)
$cssPath = 'bandejas.css';
echo '<link rel="stylesheet" href="'.$cssPath.'?v='.(file_exists($cssPath)?filemtime($cssPath):time()).'">';

/* =========================
   SESIÓN / CONTEXTO
   ========================= */
$iCodTrabajador   = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodOficinaLogin = $_SESSION['iCodOficinaLogin']  ?? null;
$iCodPerfil       = $_SESSION['ID_PERFIL']         ?? null;

/* =========================
   FILTROS (alineados al SP)
   ========================= */
   $fltDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
   $dtDesde  = ($fltDesde !== '' ? $fltDesde.' 00:00:00' : 0);
   
   $fltHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
   $dtHasta  = ($fltHasta !== '' ? $fltHasta.' 23:59:59' : 0);

$fltCodificacion   = isset($_GET['codificacion']) ? trim($_GET['codificacion']) : '';
$valCodificacion   = htmlspecialchars($fltCodificacion, ENT_QUOTES, 'UTF-8');
$paramCodificacion = ($fltCodificacion !== '' ? '%'.$fltCodificacion.'%' : '%%');

$fltAsunto   = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valAsunto   = htmlspecialchars($fltAsunto, ENT_QUOTES, 'UTF-8');
$paramAsunto = ($fltAsunto !== '' ? '%'.$fltAsunto.'%' : '%%');

$tipoDocumentoSel = isset($_GET['tipoDocumento']) ? trim($_GET['tipoDocumento']) : '';
$valTipoDocumento = htmlspecialchars($tipoDocumentoSel, ENT_QUOTES, 'UTF-8');

$dir = isset($_GET['dir']) ? strtoupper($_GET['dir']) : 'DESC';
if (!in_array($dir, ['ASC','DESC'])) $dir = 'DESC';

/* =========================
   CATÁLOGOS
   ========================= */
$tipoDocQuery   = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento ORDER BY cDescTipoDoc ASC";
$tipoDocResult  = sqlsrv_query($cnx, $tipoDocQuery);

/* =========================
   EJECUCIÓN SP (opción por defecto op1)
   ========================= */
$sqlSp = "{CALL SP_BANDEJA_FINALIZADOS(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
$paramsSp = array(
  'op1',             // @opcion (op1: orden por fFecFinalizar)
  0,                 // @i_Entrada (ignorar)
  0,                 // @i_Interno (ignorar)
  $dtDesde,          // @i_fDesde
  $dtHasta,          // @i_fHasta
  $fltCodificacion,  // @i_cCodificacion
  $fltAsunto,        // @i_cAsunto
  $iCodOficinaLogin, // @i_iCodOficinaLogin
  ($tipoDocumentoSel !== '' ? (int)$tipoDocumentoSel : 0), // @i_cCodTipoDoc
  0,                 // @i_iCodTema
  $dir               // @i_dir
);

$stmt = sqlsrv_query($cnx, $sqlSp, $paramsSp);
if ($stmt === false) {
  die(print_r(sqlsrv_errors(), true));
}

/* =========================
   NORMALIZACIÓN RESULTADOS
   ========================= */
$items = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $items[] = array(
    'iCodTramite'             => isset($r['iCodTramite']) ? (int)$r['iCodTramite'] : 0,
    'iCodMovimiento'          => isset($r['iCodMovimiento']) ? (int)$r['iCodMovimiento'] : 0,
    'cDescTipoDoc'            => $r['cDescTipoDoc'] ?? '',
    'cCodificacion'           => $r['cCodificacion'] ?? '',
    'cAsunto'                 => $r['cAsunto'] ?? '',
    'fFecRegistro'            => $r['fFecRegistro'] ?? null,
    'fFecDerivar'             => $r['fFecDerivar'] ?? null,
    'fFecRecepcion'           => $r['fFecRecepcion'] ?? null,
    'fFecFinalizar'           => $r['fFecFinalizar'] ?? null,
    'cObservacionesFinalizar' => $r['cObservacionesFinalizar'] ?? '',
    'iCodTrabajadorFinalizar' => isset($r['iCodTrabajadorFinalizar']) ? (int)$r['iCodTrabajadorFinalizar'] : 0,
  );
}

/* =========================
   PAGINACIÓN
   ========================= */
$totalRegistros = count($items);
$porPagina = isset($_GET['pp']) ? max(5, min(200, (int)$_GET['pp'])) : 40;
$pagina    = isset($_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;
$paginas   = max(1, (int)ceil($totalRegistros / $porPagina));
if ($pagina > $paginas) { $pagina = $paginas; }
$inicio       = ($pagina - 1) * $porPagina;
$itemsPagina  = array_slice($items, $inicio, $porPagina);
$desdeN       = ($totalRegistros === 0) ? 0 : ($inicio + 1);
$hastaN       = min($inicio + $porPagina, $totalRegistros);

function linkPag($p) {
  $q = $_GET; $q['pag'] = $p; $qs = http_build_query($q);
  return 'bandejaFinalizados.php?' . $qs;
}

/* =========================
   MAPA DOC / EXP (Trámite)
   ========================= */
$docsByTramite = array();
if (!empty($itemsPagina)) {
  $ids = array();
  foreach ($itemsPagina as $it) {
    if (!empty($it['iCodTramite'])) $ids[(int)$it['iCodTramite']] = true;
  }
  $ids = array_keys($ids);
  if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $qDocs = "SELECT iCodTramite, EXPEDIENTE, documentoElectronico, extension FROM Tra_M_Tramite WHERE iCodTramite IN ($ph)";
    $stDocs = sqlsrv_query($cnx, $qDocs, $ids);
    if ($stDocs !== false) {
      while ($d = sqlsrv_fetch_array($stDocs, SQLSRV_FETCH_ASSOC)) {
        $docsByTramite[(int)$d['iCodTramite']] = array(
          'expediente'           => isset($d['EXPEDIENTE']) ? trim($d['EXPEDIENTE']) : '',
          'documentoElectronico' => $d['documentoElectronico'] ?? null,
          'extension'            => isset($d['extension']) ? (int)$d['extension'] : 1,
        );
      }
    }
  }
}

/* =========================
   MAPA NOMBRES FINALIZADOR
   ========================= */
$finalizadorById = array();
$idsF = array();
foreach ($itemsPagina as $it) {
  if (!empty($it['iCodTrabajadorFinalizar'])) $idsF[(int)$it['iCodTrabajadorFinalizar']] = true;
}
$idsF = array_keys($idsF);
if (!empty($idsF)) {
  $ph = implode(',', array_fill(0, count($idsF), '?'));
  $qTrab = "SELECT iCodTrabajador, cApellidosTrabajador, cNombresTrabajador
            FROM TRA_M_Trabajadores
            WHERE iCodTrabajador IN ($ph)";
  $stTrab = sqlsrv_query($cnx, $qTrab, $idsF);
  if ($stTrab !== false) {
    while ($tt = sqlsrv_fetch_array($stTrab, SQLSRV_FETCH_ASSOC)) {
      $finalizadorById[(int)$tt['iCodTrabajador']] =
        trim(($tt['cApellidosTrabajador'] ?? '').' '.($tt['cNombresTrabajador'] ?? ''));
    }
  }
}

/* =========================
   HELPERS UI
   ========================= */
   function dmy($dt){
    if ($dt instanceof DateTimeInterface) return $dt->format('d-m-Y');
    if (is_array($dt) && isset($dt['date'])) return date('d-m-Y', strtotime($dt['date']));
    if (is_string($dt) && $dt!=='') return date('d-m-Y', strtotime($dt));
    return '';
  }
  function hi($dt){
    if ($dt instanceof DateTimeInterface) return $dt->format('G:i');
    if (is_array($dt) && isset($dt['date'])) return date('G:i', strtotime($dt['date']));
    if (is_string($dt) && $dt!=='') return date('G:i', strtotime($dt));
    return '';
  }
  function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!-- ======= UI: mismo layout que bandejaEnviados ======= -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
/* ===== Ajustes SOLO para FINALIZADOS ===== */
.bandeja--finalizados{
  /* Anchos locales (7 columnas) */
  --c-exp:  190px;                      /* Expediente / Registro */
  --c-doc:  240px;                      /* Documento */
  --c-asu:  minmax(560px, 1fr);         /* ASUNTO (más ancho) */
  --c-der:  112px;                      /* Derivado (fecha) */
  --c-rec:  112px;                      /* Recepción (fecha) */
  --c-fin:  minmax(380px, .9fr);        /* FINALIZADO (más ancho) */
  --c-opt:   68px;                      /* Opciones compacto */
  --row-h:  118px;                      /* filas más altas */
}

/* Header y filas: su propio grid (7 columnas) */
.bandeja--finalizados .tabla-head-sticky .ths{
  display:grid;
  grid-template-columns: var(--c-exp) var(--c-doc) var(--c-asu) var(--c-der) var(--c-rec) var(--c-fin) var(--c-opt);
  align-items:center;
}
.bandeja--finalizados .grid-row{
  grid-template-columns: var(--c-exp) var(--c-doc) var(--c-asu) var(--c-der) var(--c-rec) var(--c-fin) var(--c-opt);
  align-items:start;
  min-height: var(--row-h);
}

/* Forzar apilado (columna) en celdas 1, 4, 5 y 6 */
.bandeja--finalizados .grid-row > *:nth-child(1),
.bandeja--finalizados .grid-row > *:nth-child(4),
.bandeja--finalizados .grid-row > *:nth-child(5),
.bandeja--finalizados .grid-row > *:nth-child(6){
  display:flex; flex-direction:column; justify-content:center; align-items:center;
  gap:2px; text-align:center; white-space:normal;
}

/* Fechas compactas (Derivado/Recepción) */
.bandeja--finalizados .grid-row > *:nth-child(4),
.bandeja--finalizados .grid-row > *:nth-child(5){
  font-size:12.5px; line-height:1.15; padding:8px 10px;
}

/* Finalizado: orden y jerarquía visual */
.bandeja--finalizados .fin-obs{ font-size:13px; line-height:1.2; }
.bandeja--finalizados .fin-who{ color:#005a86; font-weight:600; }
.bandeja--finalizados .fin-dt { font-size:12.5px; color:#6b7280; }

/* Opciones: realmente compactas y al extremo derecho (última columna) */
.bandeja--finalizados .grid-row > *:nth-child(7){ padding:6px 2px; }
.bandeja--finalizados .acciones{ display:flex; justify-content:center; align-items:center; gap:4px; flex-wrap:nowrap; }
.bandeja--finalizados .btn.btn-link{ padding:2px; }
.bandeja--finalizados .btn.btn-link .material-icons{ font-size:18px; }


/* ====== FINALIZADOS: usar sticky en vez de fixed ====== */
.bandeja--finalizados .contenedor-principal{
  /* el panel sticky ya ocupa altura, no necesitamos empujón extra */
  margin-top: 0;
}

.bandeja--finalizados .panel-fijo{
  position: sticky;            /* << antes era fixed */
  top: var(--stick-top);
  z-index: 950;
}

/* Ya no necesitamos compensar panel-h + gap para el cuerpo */
.bandeja--finalizados {
  --panel-gap: 0;              /* ignora el gap global */
  padding-top: 24px;   /* sube/baja este valor si quieres más/menos aire */

}
.bandeja--finalizados .lista-scroll{
  margin-top: 0;               /* << quita el salto grande */
}
</style>


<div class="bandeja bandeja--finalizados">
<div class="contenedor-principal">
  <div class="panel-fijo" id="panelFijo">
    <div class="barra-titulo">BANDEJA DE FINALIZADOS</div>

    <!-- Filtros (mismo layout visual de Enviados) -->
    <form class="filtros-formulario" method="get" action="bandejaFinalizados.php">
      <div class="columna-izquierda">
        <div class="fila">
          <div class="input-container">
            <input type="date" name="desde" value="<?= $valDesde ?>" placeholder=" ">
            <label>Desde</label>
          </div>
          <div class="input-container">
            <input type="date" name="hasta" value="<?= $valHasta ?>" placeholder=" ">
            <label>Hasta</label>
          </div>
        </div>
        <div class="fila">
          <div class="input-container">
            <input type="text" name="codificacion" value="<?= $valCodificacion ?>" placeholder=" ">
            <label>Codificación / Nro</label>
          </div>
        </div>
        <div class="fila">
          <div class="input-container">
            <input type="text" name="asunto" value="<?= $valAsunto ?>" placeholder=" ">
            <label>Asunto</label>
          </div>
        </div>
      </div>

      <div class="columna-derecha">
        <div class="fila">
          <div class="input-container">
            <select name="tipoDocumento" id="tipoDocumento" <?= $tipoDocumentoSel==='' ? '' : 'value="'.htmlspecialchars($tipoDocumentoSel).'"' ?>>
              <option value="" <?= ($tipoDocumentoSel===''?'selected':'') ?> disabled hidden></option>
              <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)):
                $val = (string)$td['cCodTipoDoc'];
                $txt = $td['cDescTipoDoc'];
                $selected = ($tipoDocumentoSel !== '' && (string)$tipoDocumentoSel === $val) ? 'selected' : '';
              ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= $selected ?>><?= htmlspecialchars($txt) ?></option>
              <?php endwhile; ?>
            </select>
            <label for="tipoDocumento">Tipo de Documento</label>
          </div>

          <div class="botones-filtro" style="margin-left:auto;">
          <button type="submit" class="btn-filtro btn-primary">Buscar</button>
            <button type="button" class="btn-filtro btn-secondary" onclick="window.location.href='bandejaFinalizados.php'">Reestablecer</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Barra de registros + paginación (idéntica visual) -->
    <div class="barra-titulo">
      <div class="bt-left">
        REGISTROS
        <span class="badge-registros"><?= number_format($totalRegistros) ?></span>
        <small style="font-weight:normal; margin-left:10px;">Mostrando <?= $desdeN ?>–<?= $hastaN ?> de <?= number_format($totalRegistros) ?></small>
      </div>
      <div class="bt-right">
        <div class="paginacion">
          <?php
            $win  = 2;
            $from = max(1, $pagina - $win);
            $to   = min($paginas, $pagina + $win);

            if ($pagina > 1) echo '<a href="'.htmlspecialchars(linkPag($pagina-1)).'">&laquo;</a>';
            else echo '<span class="disabled">&laquo;</span>';

            if ($from > 1) {
              echo '<a href="'.htmlspecialchars(linkPag(1)).'">1</a>';
              if ($from > 2) echo '<span class="ellipsis">…</span>';
            }

            for ($p=$from; $p<=$to; $p++) {
              if ($p == $pagina) echo '<span class="current">'.$p.'</span>';
              else echo '<a href="'.htmlspecialchars(linkPag($p)).'">'.$p.'</a>';
            }

            if ($to < $paginas) {
              if ($to < $paginas-1) echo '<span class="ellipsis">…</span>';
              echo '<a href="'.htmlspecialchars(linkPag($paginas)).'">'.$paginas.'</a>';
            }

            if ($pagina < $paginas) echo '<a href="'.htmlspecialchars(linkPag($pagina+1)).'">&raquo;</a>';
            else echo '<span class="disabled">&raquo;</span>';
          ?>
        </div>
      </div>
    </div>

    <!-- Cabecera tipo tabla (mismas 6 columnas/anchos) -->
    <div class="tabla-head-sticky" id="tablaHeadSticky">
      <div class="ths">
        <div class="th">Expediente / Registro</div>
        <div class="th">Documento</div>
        <div class="th">Asunto</div>
        <div class="th">Derivado</div>
        <div class="th">Recepción</div>
        <div class="th">Finalizado</div>
        <div class="th">Opciones</div>
      </div>
    </div>
  </div><!-- /panel-fijo -->

  <!-- Cuerpo scrolleable -->
  <div class="lista-scroll" id="listaScroll">
    <div class="lista-grid">
 
        <?php
          // === BUCLE QUE PINTA REGISTROS ===
      foreach ($itemsPagina as $it):
        $flowId   = (int)$it['iCodTramite'];
        $movId    = (int)$it['iCodMovimiento'];
        $docInfo  = $docsByTramite[$flowId] ?? array();
        $exped    = $docInfo['expediente'] ?? '';

        $docTipo  = $it['cDescTipoDoc'] ?: '-';
        $codif    = $it['cCodificacion'] ?: '';
        $asunto   = $it['cAsunto'] ?: '';

        $fReg     = $it['fFecRegistro'] ?? null;
        $fDer     = $it['fFecDerivar'] ?? null;
        $fRec     = $it['fFecRecepcion'] ?? null;
        $fFin     = $it['fFecFinalizar'] ?? null;

        $obsFin   = $it['cObservacionesFinalizar'] ?: '';
        $whoFin   = isset($finalizadorById[$it['iCodTrabajadorFinalizar']]) ? $finalizadorById[$it['iCodTrabajadorFinalizar']] : '—';
      ?>

<div class="grid-row" id="fila-<?= $flowId ?>">
        <!-- 1) Expediente / Registro: fecha (abajo) + hora (más abajo) -->
  <div class="cell">
    <div><?= safe($exped) ?></div>
    <div><?= dmy($fReg) ?></div>
    <div><?= hi($fReg) ?></div>
  </div>

        <!-- 2) Documento -->
        <div class="cell">
          <div><?= safe($docTipo) ?> — <?= safe($codif) ?></div>
        </div>

        <!-- 3) Asunto -->
        <div class="cell">
          <div><?= safe($asunto) ?></div>
        </div>

        <!-- 4) Derivado -->
        <div class="cell">
          <div><?= dmy($fDer) ?></div>
          <div><?= hi($fDer) ?></div>
        </div>

        <!-- 5) Recepción -->
        <div class="cell">
          <?php if (empty($fRec)): ?>
            <div>sin aceptar</div>
          <?php else: ?>
            <div><?= dmy($fRec) ?></div>
            <div><?= hi($fRec) ?></div>
          <?php endif; ?>
        </div>

        <!-- 6) Finalizado -->
        <div class="cell">
        <div class="fin-obs"><?= safe($obsFin) ?></div>
    <div class="fin-who"><?= safe($whoFin) ?></div>
    <div class="fin-dt"><?= dmy($fFin) ?> <?= hi($fFin) ?></div>
        </div>

       <!-- 7) Opciones -->
              <?php
                $ext = (int)($docInfo['extension'] ?? 1);
                $expedView = htmlspecialchars($exped ?? '', ENT_QUOTES, 'UTF-8'); // para título del modal
              ?>
              <div class="cell center" style="gap:8px;">
                <!-- Ver Flujo -->
                <button class="btn btn-link ver-flujo-btn"
                        data-id="<?= $flowId ?>"
                        data-extension="<?= $ext ?>"
                        title="Ver Flujo">
                  <span class="material-icons">device_hub</span>
                </button>

                <!-- Revertir -->
                <button class="btn btn-link"
                        title="Revertir Finalización"
                        onclick="revertirFinalizacion(<?= $movId  ?>)">
                  <span class="material-icons">undo</span>
                </button>

                <!-- Editar -->
                <button class="btn btn-link"
                        title="Editar Finalización"
                        onclick="abrirEditarFinalizacion(<?= $movId  ?>, '<?= $expedView ?>')">
                  <span class="material-icons">edit</span>
                </button>
              </div>





      </div>

      <?php endforeach; // === FIN BUCLE QUE PINTA REGISTROS === ?>
<!-- Modal Editar Finalización -->
<div id="modalEditarFinal" class="modal" style="display:none;">
  <form id="formEditarFinal" class="modal-content small" enctype="multipart/form-data">
    <input type="hidden" name="iCodMovimiento" id="editCodMovimiento">
    <span class="modal-close cerrarModal" onclick="cerrarModal('modalEditarFinal')">&times;</span>
    <h2>Editar Finalización <span id="expedienteEditar" style="font-weight:600;"></span></h2>

    <div style="margin-bottom:1rem;">
      <label>Observaciones</label>
      <textarea name="observaciones" id="editObservaciones" rows="4" style="width:100%;"></textarea>
    </div>

    <!-- Documento actual (se llena dinámicamente) -->
    <div id="archivoFinalActual" style="margin-bottom:1rem;"></div>

    <div style="margin-bottom:1rem;">
      <label>Nuevo documento (opcional)</label>
      <input type="file" name="archivoFinal" accept="application/pdf">
    </div>

    <div style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" class="btn-filtro btn-secondary" onclick="cerrarModal('modalEditarFinal')">Cancelar</button>
      <button type="submit" class="btn-filtro btn-primary">Guardar Cambios</button>
    </div>
  </form>
</div>
    </div>
  </div>
</div>

<script>
function calcPanelH(){
  const root  = document.documentElement;
  const panel = document.getElementById('panelFijo');
  if(!panel) return;
  // Medimos todo el bloque fijo (incluye filtros, REGISTROS y cabecera)
  const h = Math.ceil(panel.getBoundingClientRect().height);
  root.style.setProperty('--panel-h', h + 'px');
}

function fixPanelOffset(){
  // 1) inmediato
  calcPanelH();
  // 2) tras el siguiente frame (por si faltó layout)
  requestAnimationFrame(calcPanelH);
  // 3) tras pequeña espera (por fuentes o contenidos diferidos)
  setTimeout(calcPanelH, 200);
  setTimeout(calcPanelH, 600);
}

window.addEventListener('load', fixPanelOffset);
window.addEventListener('resize', fixPanelOffset);

if ('ResizeObserver' in window) {
  const panel = document.getElementById('panelFijo');
  if (panel) new ResizeObserver(fixPanelOffset).observe(panel);
}

// Ver flujo (abre en nueva pestaña)
document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    const extension = this.dataset.extension || 1;
    // Usa la vista que prefieras (Raíz o estándar). Mantengo tu preferencia a bandejaFlujo.php.
    window.open('bandejaFlujo.php?iCodTramite=' + encodeURIComponent(id) + '&extension=' + encodeURIComponent(extension), '_blank');
  });
});

// Revertir finalización
async function revertirFinalizacion(iCodMovimiento) {
  if (!confirm('¿Deseas revertir esta finalización?')) return;
  try {
    const res = await fetch('revertirFinalizacion.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'iCodMovimiento=' + encodeURIComponent(iCodMovimiento)
    });
    const json = await res.json();
    if (json.status === 'ok') {
      alert('Finalización revertida.');
      location.reload();
    } else {
      alert('Error: ' + (json.message || 'No se pudo completar la operación.'));
    }
  } catch (e) {
    alert('Error de conexión.');
  }
}

// Abrir modal de edición
async function abrirEditarFinalizacion(iCodMovimiento, expediente) {
  try {
    const res = await fetch('getDocumentoFinal.php?iCodMovimiento=' + encodeURIComponent(iCodMovimiento));
    const json = await res.json();

    if (json.status === 'ok') {
      document.getElementById('editCodMovimiento').value = iCodMovimiento;
      document.getElementById('editObservaciones').value = json.observaciones || '';
      document.getElementById('expedienteEditar').innerText = expediente || '';

      const cont = document.getElementById('archivoFinalActual');
      if (json.nombre) {
        // Si tu carpeta difiere, ajústala aquí:
        const href = 'cAlmacenArchivos/' + encodeURIComponent(json.nombre);
        cont.innerHTML = `
          <div>
            <strong>Documento actual:</strong><br>
            <a href="${href}" target="_blank" style="color:#0066cc; text-decoration:none;">
              <span class="material-icons" style="vertical-align:middle;">insert_drive_file</span>
              <span style="vertical-align:middle;"> ${json.nombre}</span>
            </a>
          </div>`;
      } else {
        cont.innerHTML = '';
      }

      document.getElementById('modalEditarFinal').style.display = 'block';
    } else {
      alert('No se pudo cargar los datos.');
    }
  } catch (e) {
    alert('Error de conexión.');
  }
}

// Cerrar modal
function cerrarModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

// Guardar edición
document.getElementById('formEditarFinal')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const body = new FormData(this);
  try {
    const res = await fetch('editarFinalizacion.php', { method: 'POST', body });
    const json = await res.json();
    if (json.status === 'ok') {
      alert('Finalización actualizada.');
      location.reload();
    } else {
      alert('Error: ' + (json.message || 'No se pudo actualizar.'));
    }
  } catch (err) {
    alert('Error de conexión.');
  }
});
</script>
