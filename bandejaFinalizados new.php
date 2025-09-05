<?php
include("head.php");
include("conexion/conexion.php");

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
$valDesde = htmlspecialchars($fltDesde, ENT_QUOTES, 'UTF-8');
$dtDesde  = ($fltDesde !== '' ? $fltDesde.' 00:00:00' : 0); // 0 para que el SP ignore

$fltHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$valHasta = htmlspecialchars($fltHasta, ENT_QUOTES, 'UTF-8');
$dtHasta  = ($fltHasta !== '' ? $fltHasta.' 23:59:59' : 0); // 0 para que el SP ignore

$fltCodificacion   = isset($_GET['codificacion']) ? trim($_GET['codificacion']) : '';
$valCodificacion   = htmlspecialchars($fltCodificacion, ENT_QUOTES, 'UTF-8');

$fltAsunto   = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valAsunto   = htmlspecialchars($fltAsunto, ENT_QUOTES, 'UTF-8');

$tipoDocumentoSel = isset($_GET['tipoDocumento']) ? trim($_GET['tipoDocumento']) : '';
$valTipoDocumento = htmlspecialchars($tipoDocumentoSel, ENT_QUOTES, 'UTF-8');

$dir = isset($_GET['dir']) ? strtoupper($_GET['dir']) : 'DESC';
if (!in_array($dir, ['ASC','DESC'])) $dir = 'DESC';

/* =========================
   CATÁLOGOS
   ========================= */
$tipoDocQuery  = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

/* =========================
   SP: FINALIZADOS (op1 por defecto)
   ========================= */
$sqlSp = "{CALL SP_BANDEJA_FINALIZADOS(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
$paramsSp = array(
  'op1',                         // @opcion (orden por fFecFinalizar)
  0,                             // @i_Entrada
  0,                             // @i_Interno
  $dtDesde,                      // @i_fDesde  (0 ignora fecha)
  $dtHasta,                      // @i_fHasta  (0 ignora fecha)
  $fltCodificacion,              // @i_cCodificacion
  $fltAsunto,                    // @i_cAsunto
  $iCodOficinaLogin,             // @i_iCodOficinaLogin
  ($tipoDocumentoSel!=='' ? (int)$tipoDocumentoSel : 0), // @i_cCodTipoDoc
  0,                             // @i_iCodTema
  $dir                           // @i_dir
);

$stmt = sqlsrv_query($cnx, $sqlSp, $paramsSp);
if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }

/* =========================
   NORMALIZACIÓN
   ========================= */
$items = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $items[] = array(
    'iCodTramite'             => isset($r['iCodTramite']) ? (int)$r['iCodTramite'] : 0,
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
   MAPAS AUXILIARES
   ========================= */
/* EXPEDIENTE por iCodTramite */
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

/* Nombre del finalizador */
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
   HELPERS (colócalos ANTES del HTML)
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

<div class="contenedor-principal">
  <div class="panel-fijo" id="panelFijo">
    <div class="barra-titulo">BANDEJA DE FINALIZADOS</div>

    <!-- Filtros -->
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

    <!-- Barra de registros + paginación -->
    <div class="barra-titulo">
      <div class="bt-left">
        REGISTROS
        <span class="badge-registros"><?= number_format($totalRegistros) ?></span>
        <small>Mostrando <?= $desdeN ?>–<?= $hastaN ?> de <?= number_format($totalRegistros) ?></small>
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

    <!-- Cabecera (7 columnas) -->
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
        <!-- 1) Expediente / Registro -->
        <div class="cell">
          <div><?= safe($exped) ?></div>
          <div><?= dmy($fReg) ?></div>
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
          <div><?= safe($whoFin) ?></div>
          <div><?= safe($obsFin) ?></div>
          <div><?= dmy($fFin) ?> <?= hi($fFin) ?></div>
        </div>

        <!-- 7) Opciones -->
        <div class="cell">
          <a href="bandejaFlujoRaiz.php?iCodTramite=<?= $flowId ?>" target="_blank">Ver flujo</a>
          <!-- agrega aquí tus botones de revertir/editar si corresponde -->
        </div>
      </div>

      <?php endforeach; // === FIN BUCLE QUE PINTA REGISTROS === ?>

    </div>
  </div>
</div>

<script>
function fixPanelOffset(){
  const root  = document.documentElement;
  const panel = document.getElementById('panelFijo');
  if(!panel) return;
  const h = Math.ceil(panel.getBoundingClientRect().height);
  requestAnimationFrame(()=> root.style.setProperty('--panel-h', h + 'px'));
}
window.addEventListener('load', fixPanelOffset);
window.addEventListener('resize', fixPanelOffset);
if('ResizeObserver' in window){
  new ResizeObserver(fixPanelOffset).observe(document.getElementById('panelFijo'));
}
</script>
