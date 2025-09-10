<?php
include("head.php");
include("conexion/conexion.php");

/* =========================
   SESIÓN / CONTEXTO
   ========================= */
$iCodTrabajador   = isset($_SESSION['CODIGO_TRABAJADOR'])   ? $_SESSION['CODIGO_TRABAJADOR']   : null;
$iCodOficinaLogin = isset($_SESSION['iCodOficinaLogin'])    ? $_SESSION['iCodOficinaLogin']    : null;
$iCodPerfil       = isset($_SESSION['ID_PERFIL'])           ? $_SESSION['ID_PERFIL']           : null;

/* =========================
   FILTROS (adaptados al SP)
   ========================= */
$fltCodificacion   = isset($_GET['codificacion']) ? trim($_GET['codificacion']) : '';
$valCodificacion   = htmlspecialchars($fltCodificacion, ENT_QUOTES, 'UTF-8');
$paramCodificacion = ($fltCodificacion !== '' ? '%'.$fltCodificacion.'%' : '%%');

$fltAsunto   = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valAsunto   = htmlspecialchars($fltAsunto, ENT_QUOTES, 'UTF-8');
$paramAsunto = ($fltAsunto !== '' ? '%'.$fltAsunto.'%' : '%%');

$fltObs   = isset($_GET['observaciones']) ? trim($_GET['observaciones']) : '';
$valObs   = htmlspecialchars($fltObs, ENT_QUOTES, 'UTF-8');
$paramObs = ($fltObs !== '' ? '%'.$fltObs.'%' : '%%');

$fltDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$valDesde = htmlspecialchars($fltDesde, ENT_QUOTES, 'UTF-8');
$dtDesde  = ($fltDesde !== '' ? $fltDesde.' 00:00:00' : null);

$fltHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$valHasta = htmlspecialchars($fltHasta, ENT_QUOTES, 'UTF-8');
$dtHasta  = ($fltHasta !== '' ? $fltHasta.' 23:59:59' : null);

/* Enviados: SI / NO (nFlgEnvio) */
$fltEnviadoSI = (isset($_GET['enviado_si']) && $_GET['enviado_si'] === '1') ? '1' : '';
$fltEnviadoNO = (isset($_GET['enviado_no']) && $_GET['enviado_no'] === '1') ? '1' : '';

/* Tipo de documento (cCodTipoDoc) */
$tipoDocumentoSel = isset($_GET['tipoDocumento']) ? trim($_GET['tipoDocumento']) : '';
$valTipoDocumento = htmlspecialchars($tipoDocumentoSel, ENT_QUOTES, 'UTF-8');

 
/* Oficina destino (opcional) -> INT o NULL */
$oficinaDerivarSel = (isset($_GET['oficina_derivar']) && $_GET['oficina_derivar']!=='')
  ? (int)$_GET['oficina_derivar']
  : null;
$valOficinaDerivar = $oficinaDerivarSel === null ? '' : (string)$oficinaDerivarSel;

/* Orden (fijo; ya no se muestra en filtros) */
$ordenCol = 'Fecha';
$ordenDir = 'DESC';
$valOrdenCol = $ordenCol;
$valOrdenDir = $ordenDir;

/* =========================
   CATÁLOGOS
   ========================= */
$tipoDocQuery   = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult  = sqlsrv_query($cnx, $tipoDocQuery);

$oficinasQuery  = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

/* =========================
   SP_CONSULTA_INTERNO_OFICINA
   ========================= */
   $sqlSp = "{CALL SP_CONSULTA_INTERNO_OFICINA(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
   $paramsSp = array(
     $dtDesde,            // 1
     $dtHasta,            // 2
     $fltEnviadoSI,       // 3
     $fltEnviadoNO,       // 4
     $paramCodificacion,  // 5
     $paramAsunto,        // 6
     $paramObs,           // 7
     $tipoDocumentoSel,   // 8
     $oficinaDerivarSel,  // 9
     $iCodOficinaLogin,   // 10
     $ordenCol,           // 11  (fijo)
     $ordenDir            // 12  (fijo)
   );
   
   $stmt = sqlsrv_query($cnx, $sqlSp, $paramsSp);
   if ($stmt === false) {
     die(print_r(sqlsrv_errors(), true));
   }
   
   /* =========================
      NORMALIZACIÓN
      ========================= */
   $items = array();
   while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
     $items[] = array(
       'iCodTramite'         =>  (int)$r['iCodTramite']  , // raíz
       'cDescTipoDoc'        => $r['cDescTipoDoc'] ?? '',
       'cCodificacion'       => $r['cCodificacion'] ?? '',
       'cNomOficina'         => $r['cNomOficina'] ?? '',            
    //    'cNomOficinaDerivar'  => $r['cNomOficinaDerivar'] ?? null,   
       'fFecRegistro'        => $r['fFecRegistro'] ?? null,
       'cAsunto'             => $r['cAsunto'] ?? '',
       'cObservaciones'      => $r['cObservaciones'] ?? '',
       'cReferencia'         => $r['cReferencia'] ?? '',
       'apellidos'           => $r['cApellidosTrabajador'] ?? '',
       'nombres'             => $r['cNombresTrabajador'] ?? '',
       'nFlgEnvio'           => isset($r['nFlgEnvio']) ? (int)$r['nFlgEnvio'] : null,
       'nFlgNew'             => isset($r['nFlgNew']) ? (int)$r['nFlgNew'] : null,
       'nFlgTipoDerivo'      => isset($r['nFlgTipoDerivo']) ? (int)$r['nFlgTipoDerivo'] : 0,
       'nFlgTipoDoc'        => isset($r['nFlgTipoDoc'])    ? (int)$r['nFlgTipoDoc']    : 2, // <= AÑADIR

       'iCodTramtieDerivo'   => isset($r['iCodTramtieDerivo']) ? (int)$r['iCodTramtieDerivo'] : 0,

       // NUEVOS (desde el SP)
    'nDestinos'          => isset($r['nDestinos']) ? (int)$r['nDestinos'] : 0,
    'destLista'          => $r['cDestinosLista'] ?? '',
    'destEtiqueta'       => $r['cNomOficinaDerivar'] ?? '', // etiqueta ya viene en esta columna

    'cNomOficinaDerivar' => $r['cNomOficinaDerivar'] ?? null,  // etiqueta: "Varios" o nombre único
    'cDestinosLista'     => $r['cDestinosLista'] ?? '',        // tooltip con toda la lista
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
     $q = $_GET;
     $q['pag'] = $p;
     $qs = http_build_query($q);
     return 'bandejaEnviados.php?' . $qs;
   }
 
   
   function baseDirPorFecha($dt){
     if ($dt instanceof DateTimeInterface) {
       return ($dt->format('Y-m-d H:i') >= '2025-08-29 00:00') ? 'STDD_marchablanca' : 'STD';
     }
     if (is_array($dt) && isset($dt['date'])) {
       return (date('Y-m-d H:i', strtotime($dt['date'])) >= '2025-08-29 00:00') ? 'STDD_marchablanca' : 'STD';
     }
     if (is_string($dt) && $dt!=='') {
       return (date('Y-m-d H:i', strtotime($dt)) >= '2025-08-29 00:00') ? 'STDD_marchablanca' : 'STD';
     }
     return 'STD';
   }
   
   
?>
<!-- Material Icons y CSS   -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root{ --primary:#005a86; --secondary:#c69157; --stick-top:105px; --panel-h:0px; --panel-gap:96px; --panel-cushion: 8px;}
body{ margin:0; padding:0; }
body > .contenedor-principal{ margin-top:var(--stick-top); }
.contenedor-principal{ position:relative; }
.panel-fijo{ position:fixed; top:var(--stick-top); left:0; right:0; z-index:950; background:#fff; box-shadow:0 2px 0 rgba(0,0,0,.04); }
.lista-scroll{ margin-top:calc(var(--panel-h) + var(--panel-gap) + var(--panel-cushion)); padding-bottom:24px; }
.barra-titulo{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; background:#005a86; color:#fff; padding:10px 20px; font-weight:bold; font-size:15px; width:100%; box-sizing:border-box; margin:0; z-index:910; }
.filtros-formulario{ display:flex; gap:30px; background:#fff; border:1px solid #ccc; border-radius:0; padding:20px 20px 10px; width:100%; box-sizing:border-box; flex-wrap:wrap; margin:0; }
.columna-izquierda{ flex:1; max-width:40%; display:flex; flex-direction:column; gap:12px; }
.columna-derecha{ flex:2; display:flex; flex-direction:column; gap:12px; }
.fila{ display:flex; gap:10px; flex-wrap:wrap; }
.input-container{ position:relative; flex:1; min-width:120px; }
.input-container input, .input-container select{ width:100%; padding:14px 12px 6px; font-size:14px; border:1px solid #ccc; border-radius:4px; background:#fff; box-sizing:border-box; appearance:none; height:42px; line-height:1.2; }
.input-container select:required:invalid{ color:#aaa; }
.input-container label{ position:absolute; top:50%; left:12px; transform:translateY(-50%); font-size:13px; color:#666; background:#fff; padding:0 4px; pointer-events:none; transition:.2s ease all; }
.input-container input:focus + label,
.input-container input:not(:placeholder-shown) + label,
.input-container select:focus + label,
.input-container select:valid + label{ top:-7px; font-size:11px; color:#333; transform:translateY(0); }
.input-container input[type="date"]:not(:placeholder-shown) + label,
.input-container input[type="date"]:valid + label{ top:-7px; font-size:11px; color:#333; transform:translateY(0); }
.input-group{ display:flex; align-items:center; gap:14px; height:42px; }
.input-group label.chk{ position:static; transform:none; font-size:14px; color:#333; display:flex; align-items:center; gap:6px; margin:0; }
.botones-filtro{ display:flex; gap:10px; align-items:flex-end; margin-left:auto; }
.btn-filtro{ padding:0 16px; font-size:14px; border-radius:4px; min-width:120px; display:flex; align-items:center; justify-content:center; gap:6px; border:none; cursor:pointer; height:42px; box-sizing:border-box; }
.btn-primary{ background-color:var(--primary); color:#fff; }
.btn-secondary{ background-color:var(--secondary); color:#fff; }

.badge-registros{ display:inline-block; margin-left:8px; padding:2px 8px; font-size:12px; border-radius:999px; color:#fff; background:#1f2937; }
.paginacion{ display:flex; align-items:center; gap:6px; }
.paginacion a, .paginacion span.current, .paginacion span.disabled, .paginacion .ellipsis{
  display:inline-block; min-width:32px; text-align:center; padding:6px 8px; border:1px solid #ddd; border-radius:6px; text-decoration:none; color:#333; font-size:14px; line-height:1;
}
.paginacion span.current{ font-weight:700; background:#f2f6ff; border-color:#cfe2ff; color:#114a77; }
.paginacion span.disabled{ opacity:.45; border-style:dashed; pointer-events:none; }
.paginacion .ellipsis{ border-color:transparent; }
.barra-titulo .paginacion a,
.barra-titulo .paginacion span.current,
.barra-titulo .paginacion span.disabled,
.barra-titulo .paginacion .ellipsis{
  color:#fff; border-color:rgba(255,255,255,.55); background:transparent;
}
.barra-titulo .paginacion span.current{ background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.85); color:#fff; }
.barra-titulo .paginacion span.disabled{ border-color:rgba(255,255,255,.35); }
.barra-titulo .paginacion .ellipsis{ color:rgba(255,255,255,.8); }

.tabla-head-sticky{ width:100%; background:#f6f7f9; border-top:1px solid #e7ebef; border-bottom:1px solid #e7ebef; font-weight:600; font-size:13.5px; color:#2f3a44; box-sizing:border-box; margin-bottom:0px; }
.tabla-head-sticky .ths{ 
    display:grid; 
    grid-template-columns: 180px 180px 220px minmax(280px,1fr) 240px 100px;
    gap:0; 
    align-items:center; }
.tabla-head-sticky .th{ padding:8px 12px; border-right:1px solid #edf1f4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tabla-head-sticky .th:last-child{ border-right:none; }
.grid-row{ 
    display:grid;
    grid-template-columns: 180px 180px 200px minmax(220px,1fr) 240px 120px;
    align-items:center; 
    border:1px solid #e9edf1; 
    border-radius:10px; 
    background:#fff; 
    box-shadow:0 2px 8px rgba(7,23,42,.04); 
    transition: box-shadow .15s ease, transform .15s ease; }
.grid-row:hover{ box-shadow:0 8px 22px rgba(7,23,42,.10); transform:translateY(-1px); }
.grid-row .cell{ padding:14px 16px; min-height:56px; display:flex; align-items:center; gap:10px; }
.grid-row .cell.center{ justify-content:center; }
.cell.asunto{
  white-space: normal;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
  max-height: calc(1.2em * 3);
  word-break: break-word;
  overflow-wrap: anywhere;
}
.muted{ color:#6b7280; }
.badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; line-height:1; }
.badge-ok{ background:#e6ffe6; color:#1b5e20; }
.badge-warn{ background:#fff3cd; color:#8a6d3b; }
.por-aprobar{
    color:#d9534f;           /* rojito */
    font-size:12px;
    font-weight:600;
    line-height:1.1;
    margin-top:2px;
  }

  .modal-compl{ position:fixed; inset:0; z-index:9999; }
.modal-compl[style*="display: none"]{ display:none !important; }
.modal-compl-backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.4); }
.modal-compl-dialog{
  position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
  width:min(720px, 92vw); max-height:86vh; overflow:hidden;
  background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.25);
  display:flex; flex-direction:column;
}
.modal-compl-head{ background:#005a86; color:#fff; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
.modal-compl-head .modal-compl-close{ background:transparent; border:none; color:#fff; font-size:24px; line-height:1; cursor:pointer; }
.modal-compl-body{ padding:16px; overflow:auto; }
.compl-list{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
.compl-item{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid #e9edf1; border-radius:10px; }
.compl-desc{ font-weight:600; }
.compl-date{ font-size:12px; }
.compl-link{ display:inline-block; padding:6px 10px; border-radius:8px; background:#f2f6ff; text-decoration:none; }

</style>

<div class="contenedor-principal">
  <div class="panel-fijo" id="panelFijo">
    <div class="barra-titulo">BANDEJA DE ENVIADOS</div>

    <!-- Filtros (sin “Ordenar por” ni “Dirección”) -->
    <form class="filtros-formulario" method="get" action="bandejaEnviados.php">
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
            <select name="tipoDocumento" id="tipoDocumento">
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

          <div class="input-container">
            <select name="oficina_derivar" id="oficina_derivar">
              <option value="" <?= $oficinaDerivarSel===''?'selected':'' ?> disabled hidden></option>
              <option value="">(Todas las oficinas destino)</option>
              <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)):
                $val = (string)$of['iCodOficina'];
                $txt = $of['cNomOficina'];
                $sel = ($oficinaDerivarSel !== '' && $oficinaDerivarSel === $val) ? 'selected' : '';
              ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>><?= htmlspecialchars($txt) ?></option>
              <?php endwhile; ?>
            </select>
            <label for="oficina_derivar">Oficina de Destino</label>
          </div>
        </div>

        <div class="fila">
          <div class="input-container">
            <input type="text" name="observaciones" value="<?= $valObs ?>" placeholder=" ">
            <label>Observaciones</label>
          </div>

          <div class="input-group" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span style="font-size:13px; color:#666;">Enviado</span>
            <label class="chk"><input type="checkbox" name="enviado_si" value="1" <?= $fltEnviadoSI==='1' ? 'checked' : '' ?>> Sí</label>
            <label class="chk"><input type="checkbox" name="enviado_no" value="1" <?= $fltEnviadoNO==='1' ? 'checked' : '' ?>> No</label>
          </div>

          <div class="botones-filtro" style="margin-left:auto;">
            <button type="submit" class="btn-filtro btn-primary"><span class="material-icons">search</span> Buscar</button>
            <button type="button" class="btn-filtro btn-secondary" onclick="window.location.href='bandejaEnviados.php'"><span class="material-icons">autorenew</span> Reestablecer</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Barra de registros + paginación -->
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

            if ($pagina > 1) {
              echo '<a href="'.htmlspecialchars(linkPag($pagina-1)).'">&laquo;</a>';
            } else {
              echo '<span class="disabled">&laquo;</span>';
            }

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

            if ($pagina < $paginas) {
              echo '<a href="'.htmlspecialchars(linkPag($pagina+1)).'">&raquo;</a>';
            } else {
              echo '<span class="disabled">&raquo;</span>';
            }
          ?>
        </div>
      </div>
    </div>

    <!-- Cabecera tipo tabla (6 columnas) -->
    <div class="tabla-head-sticky" id="tablaHeadSticky">
      <div class="ths">
        <div class="th" style="width:240px;">Expediente / Fecha</div>
        <div class="th" style="width:220px;">Registrador</div>
        <div class="th" style="width:180px;">Documento</div>
        <div class="th">Asunto</div>
        <!-- <div class="th" style="width:240px;">Oficina de Destino</div> -->
        <div class="th" style="width:180px;">Opciones</div>
      </div>
    </div>
  </div><!-- /panel-fijo -->

  <!-- Cuerpo scrolleable -->
  <div class="lista-scroll" id="listaScroll">
    <div class="lista-grid">
      <?php foreach ($itemsPagina as $it): ?>
        <?php
          // === Determinar script y iCodTramite del flujo a abrir (antecesor si derivado)
          $flowId      = isset($it['iCodTramite']) ? (int)$it['iCodTramite'] : 0; // raíz devuelta por el SP
          $scriptPhp   = 'bandejaFlujo.php';
          $_Antecesor  = 0;
            // Siempre a partir del ítem actual
  $tramIdActual      = (int)$it['iCodTramite'];
  $nFlgTipoDocActual = (int)($it['nFlgTipoDoc'] ?? 2);

  // Archivo de edición/subsanación según el ACTUAL
  if ($nFlgTipoDocActual === 1) {
    $archivoEditar = 'registroEditarMP.php';
  } else {
    $archivoEditar = ($isDer ? 'registroDerivarSubsanar.php' : 'registroOficinaSubsanar.php');
  }

          if ((int)$it['nFlgTipoDerivo'] === 1) {
            // Buscar el trámite antecesor por la relación en movimientos
            $sqlMov = "SELECT TOP 1 iCodTramite, iCodMovimientoDerivo
                       FROM Tra_M_Tramite_Movimientos
                       WHERE iCodTramiteDerivar = ? AND cFlgTipoMovimiento != 5";
            $stMov  = sqlsrv_query($cnx, $sqlMov, array($flowId));
            $rowMov = $stMov ? sqlsrv_fetch_array($stMov, SQLSRV_FETCH_ASSOC) : null;
            $fallbackTramite = ($rowMov && isset($rowMov['iCodTramite'])) ? (int)$rowMov['iCodTramite'] : 0;

            if (!empty($it['iCodTramtieDerivo'])) {
              $_Antecesor = (int)$it['iCodTramtieDerivo'];
            } else {
              $_Antecesor = $fallbackTramite > 0 ? $fallbackTramite : $flowId;
            }

            // Elegir script en base al tipo del antecesor
            $sqlTra = "SELECT TOP 1 nFlgTipoDoc FROM Tra_M_Tramite WHERE iCodTramite = ?";
            $stTra  = sqlsrv_query($cnx, $sqlTra, array($_Antecesor));
            $rowTra = $stTra ? sqlsrv_fetch_array($stTra, SQLSRV_FETCH_ASSOC) : null;

            if ($rowTra && isset($rowTra['nFlgTipoDoc'])) {
              if ((int)$rowTra['nFlgTipoDoc'] === 1) {
                $scriptPhp = 'BandejaFlujo.php'; // histórico: flujo mayúscula para externos
              } else {
                $scriptPhp = 'bandejaFlujo.php';
              }
            }

            $flowLinkId = $_Antecesor;
          } else {
            $flowLinkId = $flowId;
          }

          // Datos extra para columna 1 y PDF
          $docInfo   = isset($docsByTramite[$flowLinkId]) ? $docsByTramite[$flowLinkId] : array();
          $hasPdf    = !empty($docInfo['documentoElectronico']);
          $expValue  = $docInfo['expediente'] ?? '';
          $isDer     = ((int)$it['nFlgTipoDerivo'] === 1);
          $fechaTxt  = (!empty($it['fFecRegistro']) && ($it['fFecRegistro'] instanceof DateTimeInterface))
                        ? $it['fFecRegistro']->format("d/m/Y H:i") : '';
          $who       = trim(($it['apellidos'] ?? '').' '.($it['nombres'] ?? ''));

           
  // ...ya tienes $flowLinkId, $isDer y $rowTra calculados arriba...
  // Asegura el tipo de doc del trámite a editar (1=externo/Mesa de Partes, 2=interno, etc.)
  $nFlgTipoDocAntecesor = null;
  if ($rowTra && isset($rowTra['nFlgTipoDoc'])) {
      $nFlgTipoDocAntecesor = (int)$rowTra['nFlgTipoDoc'];
  } else {
      // Fallback por si no vino $rowTra (debería venir)
      $stTipo = sqlsrv_query($cnx, "SELECT TOP 1 nFlgTipoDoc FROM Tra_M_Tramite WHERE iCodTramite = ?", [$flowLinkId]);
      $rwTipo = $stTipo ? sqlsrv_fetch_array($stTipo, SQLSRV_FETCH_ASSOC) : null;
      $nFlgTipoDocAntecesor = $rwTipo ? (int)$rwTipo['nFlgTipoDoc'] : 2; // asume 2 (interno) por defecto
  }

  // Ruta del archivo de edición/subsanación
  if ($nFlgTipoDocAntecesor === 1) {
      $archivoEditar = 'registroEditarMP.php';
  } else {
      $archivoEditar = $isDer ? 'registroDerivarSubsanar.php' : 'registroOficinaSubsanar.php';
  }


// IDs y banderas del ítem actual
$tramIdActual      = (int)$it['iCodTramite'];
$isDer             = ((int)$it['nFlgTipoDerivo'] === 1);
$nFlgTipoDocActual = (int)($it['nFlgTipoDoc'] ?? 2);

// Archivo de edición del ACTUAL
$archivoEditar = ($nFlgTipoDocActual === 1)
  ? 'registroEditarMP.php'
  : ($isDer ? 'registroDerivarSubsanar.php' : 'registroOficinaSubsanar.php');

// ---------- (tu lógica de flow/antecesor puede ir aquí si la necesitas para Ver flujo) ----------
// ... tu cálculo de $flowLinkId ...
// -----------------------------------------------------------------------------------------------

// ===== Documento principal (del TRÁMITE ACTUAL) =====
$docPrincipalNom   = '';
$docPrincipalFecha = null;

$stDoc = sqlsrv_query($cnx,
  "SELECT documentoElectronico, fFecRegistro
   FROM Tra_M_Tramite
   WHERE iCodTramite = ?", [$tramIdActual]);

if ($stDoc && ($rwDoc = sqlsrv_fetch_array($stDoc, SQLSRV_FETCH_ASSOC))) {
  $docPrincipalNom   = trim((string)($rwDoc['documentoElectronico'] ?? ''));
  $docPrincipalFecha = $rwDoc['fFecRegistro'] ?? null;
}
$dirPrincipal = baseDirPorFecha($docPrincipalFecha);

// ===== Documentos complementarios (del TRÁMITE ACTUAL) =====
$comps = [];
$stComp = sqlsrv_query($cnx, "
  SELECT cNombreNuevo, cDescripcion, fFechaRegistro
  FROM Tra_M_Tramite_Digitales
  WHERE iCodTramite = ?
  ORDER BY fFechaRegistro DESC", [$tramIdActual]);

if ($stComp) {
  while ($rC = sqlsrv_fetch_array($stComp, SQLSRV_FETCH_ASSOC)) {
    $comps[] = [
      'nom'  => trim((string)($rC['cNombreNuevo'] ?? '')),
      'desc' => trim((string)($rC['cDescripcion'] ?? '')),
      'fec'  => $rC['fFechaRegistro'] ?? null,
    ];
  }
}

?>

        <div class="grid-row" id="fila-<?= (int)$flowId ?>">
          <!-- 1) Expediente / Fecha -->
          <div class="cell" style="flex-direction:column; align-items:flex-start; gap:4px;">
            <div>
              <span class="badge-chip badge-exp">
              <?= $expValue!=='' ? htmlspecialchars($expValue) : '' ?>
              </span>
            </div>
            <div>
              <?php if ($isDer): ?>
                <span class="badge-chip badge-der">Derivado</span>
              <?php else: ?>
                <span class="badge-chip badge-gen">Generado</span>
              <?php endif; ?>
            </div>
            <div class="muted" style="font-size:12px;"><?= htmlspecialchars($fechaTxt) ?></div>
          </div>

          <!-- 2) Registrador -->
          <div class="cell" style="font-weight:600; font-size:13px;"><?= htmlspecialchars($who) ?></div>

          <!-- 3) Documento -->
          <div class="cell" style="flex-direction:column; align-items:flex-start;">
            <div><?= htmlspecialchars($it['cDescTipoDoc'] ?: '-') ?></div>
            <small class="muted" style="user-select:text;"><?= htmlspecialchars($it['cCodificacion']) ?></small>
            <?php if ((int)($it['nFlgEnvio'] ?? 1) === 0): ?>
                <div class="por-aprobar">(Por Aprobar)</div>
            <?php endif; ?>

          </div>

                


          <!-- 4) Asunto -->
          <div class="cell asunto">
            <?= htmlspecialchars($it['cAsunto']) ?>
          </div>

           <!-- 5) Oficina de Destino (según regla icodtramitederivar -> bloque -> destinos) 
           <div class="cell">
            <?php
              $n     = (int)($it['nDestinos'] ?? 0);
              $etq   = trim((string)($it['cNomOficinaDerivar'] ?? ''));
              $lista = trim((string)($it['cDestinosLista'] ?? ''));

              if ($n <= 0) {
                echo '-';
              } elseif ($n === 1) {
                echo htmlspecialchars($etq, ENT_QUOTES, 'UTF-8');
              } else {
                // Varios + tooltip con la lista de oficinas
                echo '<span title="'.htmlspecialchars($lista, ENT_QUOTES, 'UTF-8').'">Varios</span>';
              }
            ?>
          </div>-->

         
          <!-- 6) Opciones -->
<div class="cell" style="justify-content:center; gap:10px;">
  <!-- Ver flujo -->
  <a style="color:#0067CE; text-decoration:none;"
     href="<?= $scriptPhp ?>?iCodTramite=<?= (int)$flowLinkId ?>"
     target="_blank" title="Detalle del Trámite">
    <span class="material-icons" style="font-size:20px;vertical-align:middle;">device_hub</span>
  </a>

 

<!-- Editar: TRÁMITE ACTUAL -->
<a href="<?= $archivoEditar ?>?iCodTramite=<?= $tramIdActual ?>" ...>
  <span class="material-icons" style="font-size:22px;">edit</span>
</a>

<!-- Eliminar: TRÁMITE ACTUAL -->
<a href="#" onclick="confirmarEliminar(<?= $tramIdActual ?>, <?= (int)$it['nFlgTipoDerivo'] ?>)" ...>
  <span class="material-icons" style="font-size:22px;">delete</span>
</a>

 <!-- Documento principal (del TRÁMITE ACTUAL) -->
 <?php if ($docPrincipalNom !== ''): ?>
    <a href="../<?= $dirPrincipal ?>/cDocumentosFirmados/<?= urlencode($docPrincipalNom) ?>"
       target="_blank" title="Documento principal">
      <img src="./img/pdf.png" alt="PDF" style="width:18px;height:auto;vertical-align:middle;">
    </a>
  <?php endif; ?>

  <!-- Complementarios (modal) -->
  <?php if (count($comps) > 0): ?>
    <a href="#" title="Documentos complementarios"
       onclick="abrirModalComplementarios(<?= $tramIdActual ?>); return false;"
       style="text-decoration:none;">
      <span class="material-icons" style="font-size:22px; vertical-align:middle;">attach_file</span>
    </a>
  <?php endif; ?>


</div>

        </div>

        
<!-- MODAL COMPLEMENTARIOS (por trámite) -->
<div class="modal-compl" id="modalComp-<?= $tramIdActual ?>" style="display:none;">
  <div class="modal-compl-backdrop" onclick="cerrarModalComplementarios(<?= $tramIdActual ?>)"></div>
  <div class="modal-compl-dialog">
    <div class="modal-compl-head">
      <strong>Documentos complementarios — <?= htmlspecialchars($it['cCodificacion']) ?></strong>
      <button class="modal-compl-close" onclick="cerrarModalComplementarios(<?= $tramIdActual ?>)">&times;</button>
    </div>
    <div class="modal-compl-body">
      <?php if (count($comps) === 0): ?>
        <div class="muted">Sin documentos complementarios.</div>
      <?php else: ?>
        <ul class="compl-list">
          <?php foreach ($comps as $cx):
            $nom   = $cx['nom'];
            $desc  = ($cx['desc'] !== '' ? $cx['desc'] : '(sin descripción)');
            $fecTxt = '';
            if ($cx['fec'] instanceof DateTimeInterface)       $fecTxt = $cx['fec']->format('d/m/Y H:i');
            elseif (is_array($cx['fec']) && isset($cx['fec']['date'])) $fecTxt = date('d/m/Y H:i', strtotime($cx['fec']['date']));
            elseif (is_string($cx['fec']) && $cx['fec']!=='')   $fecTxt = date('d/m/Y H:i', strtotime($cx['fec']));
          ?>
            <li class="compl-item">
              <div class="compl-left">
                <div class="compl-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($fecTxt!==''): ?><div class="compl-date muted"><?= htmlspecialchars($fecTxt) ?></div><?php endif; ?>
              </div>
              <div class="compl-right">
                <?php if ($nom !== ''): ?>
                  <a class="compl-link" target="_blank"
                     href="./cAlmacenArchivos/<?= urlencode($nom) ?>"
                     title="<?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>">
                    Abrir
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- /MODAL COMPLEMENTARIOS -->
      <?php endforeach; ?>
    </div>
  </div>
</div>





<!-- MODAL FLUJO -->
<link rel="stylesheet" href="modal-flujo.css">

<div id="modalFlujo">
  <div class="contenido">
    <span class="cerrar" onclick="cerrarModalFlujo()">&times;</span>
    <iframe id="iframeFlujo" src=""></iframe>
  </div>
</div>
<!-- MODAL FLUJO -->



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

function confirmarEliminar(iCodTramite, nFlgTipoDerivo) {
    const opcion = confirm("¿Desea eliminar este trámite? ");

    if (!opcion) return;

    fetch('eliminarTramite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `iCodTramite=${iCodTramite}&nFlgTipoDerivo=${nFlgTipoDerivo}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            alert(data.message || 'Operación exitosa.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error de red: ' + err));
}


// JS PARA MODAL DE FLUJO

function cerrarModalFlujo() {
  document.getElementById('modalFlujo').classList.remove('activo');
  document.getElementById('iframeFlujo').src = '';
}

document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const id = this.dataset.id;
    const extension = this.dataset.extension ?? 1;
    const url = this.dataset.url;

    const iframe = document.getElementById('iframeFlujo');
    iframe.src = `${url}?iCodTramite=${id}&extension=${extension}`;

    document.getElementById('modalFlujo').classList.add('activo');
  });
});

function abrirModalComplementarios(id){
  const m = document.getElementById('modalComp-'+id);
  if (m) m.style.display = 'block';
}
function cerrarModalComplementarios(id){
  const m = document.getElementById('modalComp-'+id);
  if (m) m.style.display = 'none';
}
</script>