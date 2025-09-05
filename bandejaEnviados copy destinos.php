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
       'iCodTramite'         => isset($r['iCodTramite']) ? (int)$r['iCodTramite'] : 0, // raíz
       'cDescTipoDoc'        => $r['cDescTipoDoc'] ?? '',
       'cCodificacion'       => $r['cCodificacion'] ?? '',
       'cNomOficina'         => $r['cNomOficina'] ?? '',            
       'cNomOficinaDerivar'  => $r['cNomOficinaDerivar'] ?? null,   
       'fFecRegistro'        => $r['fFecRegistro'] ?? null,
       'cAsunto'             => $r['cAsunto'] ?? '',
       'cObservaciones'      => $r['cObservaciones'] ?? '',
       'cReferencia'         => $r['cReferencia'] ?? '',
       'apellidos'           => $r['cApellidosTrabajador'] ?? '',
       'nombres'             => $r['cNombresTrabajador'] ?? '',
       'nFlgEnvio'           => isset($r['nFlgEnvio']) ? (int)$r['nFlgEnvio'] : null,
       'nFlgNew'             => isset($r['nFlgNew']) ? (int)$r['nFlgNew'] : null,
       'nFlgTipoDerivo'      => isset($r['nFlgTipoDerivo']) ? (int)$r['nFlgTipoDerivo'] : 0,
       'iCodTramtieDerivo'   => isset($r['iCodTramtieDerivo']) ? (int)$r['iCodTramtieDerivo'] : 0,
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
   
/* =========================
   DESTINOS POR TRÁMITE (PHP)
   Regla: para CADA iCodTramite listado,
          buscar el bloque (iCodMovimientoDerivo) cuyo iCodTramiteDerivar = ese iCodTramite,
          con cFlgTipoMovimiento=1 y iCodOficinaOrigen = mi oficina.
          Luego, con ese bloque, agrupar oficinas destino.
   ========================= */
   $tramitesListado = [];
   foreach ($itemsPagina as $it) {
     $tid = (int)($it['iCodTramite'] ?? 0);
     if ($tid > 0) $tramitesListado[$tid] = true;
   }
   $ids = array_map('intval', array_keys($tramitesListado));
   $destinosByTramite = []; // [iCodTramiteDerivar] => ['label'=>'Varios|Oficina', 'title'=>'lista', 'n'=>int]
   
   if (!empty($ids)) {
     // placeholders para IN (...)
     $ph = implode(',', array_fill(0, count($ids), '?'));
   
     // Nota: filtramos por mi oficina de origen (bandeja Enviados "lo que yo envié")
     // y aplicamos (opcional) el filtro de destino elegido en la UI.
     $sqlDest = "
       -- 1) Bloque que CREÓ el trámite derivado que estoy viendo
       WITH bloques AS (
         SELECT
           K.iCodTramiteDerivar AS tramite,
           MAX(M.iCodMovimientoDerivo) AS iCodMov
         FROM Tra_M_Tramite_Movimientos M
         JOIN (VALUES ".implode(',', array_fill(0, count($ids), '(?)')).") AS K(iCodTramiteDerivar)
              ON K.iCodTramiteDerivar = M.iCodTramiteDerivar
         WHERE M.cFlgTipoMovimiento = 1
           AND M.iCodOficinaOrigen  = ?
         GROUP BY K.iCodTramiteDerivar
       )
       -- 2) Oficinas destino de ese bloque
       SELECT
         b.tramite,
         COUNT(*)                                     AS n_destinos,
         STRING_AGG(O.cNomOficina, ', ')              AS oficinas,
         CASE WHEN COUNT(*) > 1 THEN 'Varios' ELSE MAX(O.cNomOficina) END AS etiqueta
       FROM bloques b
       JOIN Tra_M_Tramite_Movimientos MD
         ON MD.iCodMovimientoDerivo = b.iCodMov
       JOIN Tra_M_Oficinas O
         ON O.iCodOficina = MD.iCodOficinaDerivar
       WHERE
         -- filtro opcional por una oficina destino concreta
         ( ? IS NULL OR ? = 0 OR MD.iCodOficinaDerivar = ? )
       GROUP BY b.tramite
     ";
   
     // Armado de parámetros: primero todos los ids para el VALUES(...), luego mi oficina, luego 3 veces el filtro opcional
     $params = $ids;                          // para el JOIN (VALUES)
     $params[] = $iCodOficinaLogin;           // mi oficina origen
     $params[] = $oficinaDerivarSel;          // filtro destino opcional (1)
     $params[] = $oficinaDerivarSel;          // (2)
     $params[] = $oficinaDerivarSel;          // (3)
   
     $stDest = sqlsrv_query($cnx, $sqlDest, $params);
     if ($stDest) {
       while ($r = sqlsrv_fetch_array($stDest, SQLSRV_FETCH_ASSOC)) {
         $tram = (int)$r['tramite'];
         $destinosByTramite[$tram] = [
           'label' => (string)$r['etiqueta'],
           'title' => (string)$r['oficinas'],
           'n'     => (int)$r['n_destinos'],
         ];
       }
     }
   
     // Si además quieres aplicar el filtro por destino en PHP (por si el SP devolvió de más):
     if ($oficinaDerivarSel !== null && $oficinaDerivarSel > 0) {
       $itemsPagina = array_values(array_filter($itemsPagina, function($it) use ($destinosByTramite){
         $tid = (int)($it['iCodTramite'] ?? 0);
         return isset($destinosByTramite[$tid]); // solo deja los que tienen match con ese destino
       }));
     }
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
    grid-template-columns: 180px 180px 220px minmax(280px,1fr) 240px 100px;
    align-items:center; 
    border:1px solid #e9edf1; 
    border-radius:10px; 
    background:#fff; 
    box-shadow:0 2px 8px rgba(7,23,42,.04); 
    transition: box-shadow .15s ease, transform .15s ease; }
.grid-row:hover{ box-shadow:0 8px 22px rgba(7,23,42,.10); transform:translateY(-1px); }
.grid-row .cell{ padding:14px 16px; min-height:56px; display:flex; align-items:center; gap:10px; }
.grid-row .cell.center{ justify-content:center; }
.muted{ color:#6b7280; }
.badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; line-height:1; }
.badge-ok{ background:#e6ffe6; color:#1b5e20; }
.badge-warn{ background:#fff3cd; color:#8a6d3b; }
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
        <div class="th" style="width:240px;">Oficina de Destino</div>
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
          </div>

          <!-- 4) Asunto -->
          <div class="cell" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?= htmlspecialchars($it['cAsunto']) ?>
          </div>

           <!-- 5) Oficina de Destino (según regla icodtramitederivar -> bloque -> destinos) -->
<div class="cell">
  <?php
    $tid = (int)($it['iCodTramite'] ?? 0);
    if (isset($destinosByTramite[$tid])) {
      $d = $destinosByTramite[$tid];
      $label = htmlspecialchars($d['label'], ENT_QUOTES, 'UTF-8');
      $title = htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8');
      if ($d['n'] > 1) {
        echo '<span title="'.$title.'">Varios</span>';
      } else {
        echo $label;
      }
    } else {
      // Sin info (no hubo envío desde mi oficina o no hay bloque asociado)
      echo '-';
    }
  ?>
</div>

          <!-- 6) Opciones -->
          <div class="cell" style="justify-content:center; gap:10px;">
            <a style="color:#0067CE; text-decoration:none;"
               href="<?= $scriptPhp ?>?iCodTramite=<?= (int)$flowLinkId ?>"
               target="_blank" title="Detalle del Trámite">
              <span class="material-icons" style="font-size:20px;vertical-align:middle;">device_hub</span>
            </a>

            <!-- <?php if ($hasPdf): ?>
              <a href="../STD/cDocumentosFirmados/<?= urlencode($docInfo['documentoElectronico']) ?>"
                 target="_blank" title="Abrir documento">
                <img src="./img/pdf.png" alt="PDF" style="width:18px;height:auto;vertical-align:middle;">
              </a>
            <?php endif; ?> -->

            <?php if ($hasPdf): ?>
        <a href="../<?= (
  ($docInfo['fFecRegistro'] instanceof DateTimeInterface
    && $docInfo['fFecRegistro']->format('Y-m-d H:i') >= '2025-08-29 00:00'
  ) ? 'STDD_marchablanca' : 'STD'
) ?>/cDocumentosFirmados/<?= urlencode($docInfo['documentoElectronico']) ?>"
   target="_blank" title="Abrir documento">
          <img src="./img/pdf.png" alt="PDF" style="width:18px;height:auto;vertical-align:middle;">
        </a>
      <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
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