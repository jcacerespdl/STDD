 
<?php
include("head.php");
include("conexion/conexion.php");

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina    = $_SESSION['iCodOficinaLogin'];
$iCodPerfil     = $_SESSION['ID_PERFIL'];

// -------------------------------
// Captura de filtros (opcionales)
// -------------------------------
$filtroExpediente     = isset($_GET['expediente']) ? trim($_GET['expediente']) : '';
$valorExpediente      = htmlspecialchars($filtroExpediente);
$filtroExpedienteParam= ($filtroExpediente !== '' ? '%'.$filtroExpediente.'%' : '');

$filtroExtension      = isset($_GET['extension']) ? trim($_GET['extension']) : '';
$valorExtension       = htmlspecialchars($filtroExtension);
$filtroExtensionParam = ($filtroExtension !== '' ? (int)$filtroExtension : null);

$filtroAsunto = isset($_GET['asunto']) ? trim($_GET['asunto']) : '';
$valorAsunto  = htmlspecialchars($filtroAsunto);

$filtroDesde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$valorDesde  = htmlspecialchars($filtroDesde);

$filtroHasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$valorHasta  = htmlspecialchars($filtroHasta);

$filtroResp = isset($_GET['iCodTrabajadorResponsable']) ? trim($_GET['iCodTrabajadorResponsable']) : '';
// Por defecto NO DELEGADOS (-1) si no viene nada por GET
$filtroDelg = isset($_GET['iCodTrabajadorDelegado'])    ? trim($_GET['iCodTrabajadorDelegado'])    : '-1';

// Normalización de fechas a DATETIME (NULL si vacío)
$fDesde = $filtroDesde !== '' ? $filtroDesde . ' 00:00:00' : null;
$fHasta = $filtroHasta !== '' ? $filtroHasta . ' 23:59:59' : null;

$chkEntrada = isset($_GET['entrada']) && $_GET['entrada'] === '1';
$chkInterno = isset($_GET['interno']) && $_GET['interno'] === '1';

$nroDocumento = isset($_GET['nro_documento']) ? trim($_GET['nro_documento']) : '';
$valorNroDocumento = htmlspecialchars($nroDocumento, ENT_QUOTES, 'UTF-8');

// ------------------------------------------
// Catálogos: Tipos de Doc y Oficinas (1 sola vez)
// ------------------------------------------
$tipoDocQuery   = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult  = sqlsrv_query($cnx, $tipoDocQuery);

$oficinasQuery  = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

// ------------ seleccionar tipo documento interno o externo
$entradaFlag = $chkEntrada ? '1' : '';
$internoFlag = $chkInterno ? '1' : '';

// ------------

// Estado: checkboxes
$fltAceptado   = isset($_GET['estado_aceptado'])   ? '1' : '';
$fltSinAceptar = isset($_GET['estado_sinaceptar']) ? '1' : '';

// RESPONSABLES (todos los jefes que hubo en la oficina, activos o históricos)
$sqlResp = "
  SELECT TT.iCodTrabajador, TT.cNombresTrabajador, TT.cApellidosTrabajador
  FROM Tra_M_Perfil_Ususario TPU
  INNER JOIN Tra_M_Trabajadores TT ON TPU.iCodTrabajador = TT.iCodTrabajador
  WHERE TPU.iCodOficina = ?
    AND TPU.iCodPerfil = 3   -- Jefe
  GROUP BY TT.iCodTrabajador, TT.cNombresTrabajador, TT.cApellidosTrabajador
  ORDER BY TT.cApellidosTrabajador, TT.cNombresTrabajador
";
$rsResp = sqlsrv_query($cnx, $sqlResp, [$iCodOficina]);

// DELEGADOS (profesionales activos de la oficina)
$sqlDelg = "
  SELECT TT.iCodTrabajador, TT.cNombresTrabajador, TT.cApellidosTrabajador
  FROM Tra_M_Perfil_Ususario TPU
  INNER JOIN Tra_M_Trabajadores TT ON TPU.iCodTrabajador = TT.iCodTrabajador
  WHERE TT.nFlgEstado = 1
    AND TPU.iCodOficina = ?
    AND TPU.iCodPerfil  = 4   -- Profesional
  ORDER BY TT.cApellidosTrabajador, TT.cNombresTrabajador
";
$rsDelg = sqlsrv_query($cnx, $sqlDelg, [$iCodOficina]);

// Jefe actual (activo) para preseleccionar RESPONSABLE si no vino por GET
$defaultRespId = null; // ← antes era ''
if ($filtroResp === '') {
    $sqlRespActual = "
        SELECT TOP 1 TT.iCodTrabajador
        FROM Tra_M_Perfil_Ususario TPU
        INNER JOIN Tra_M_Trabajadores TT ON TT.iCodTrabajador = TPU.iCodTrabajador
        WHERE TPU.iCodOficina = ? AND TPU.iCodPerfil = 3 AND TT.nFlgEstado = 1
        ORDER BY TT.cApellidosTrabajador, TT.cNombresTrabajador
    ";
    $stmtRespActual = sqlsrv_query($cnx, $sqlRespActual, [$iCodOficina]);
    if ($stmtRespActual && ($rowRA = sqlsrv_fetch_array($stmtRespActual, SQLSRV_FETCH_ASSOC))) {
        $defaultRespId = (int)$rowRA['iCodTrabajador']; // ← fuerza int
    }
}

// Este será el valor que se usará tanto para el SP como para marcar la opción seleccionada
$responsableParam = ($filtroResp !== '')
  ? (int)$filtroResp               // si el usuario eligió uno
  : ($defaultRespId ?? null);      // si no, jefe actual o NULL si no hay

// -------------------------------
// Llamada al SP (22 parámetros)
// -------------------------------
// Por compatibilidad con la lógica original, muchos filtros van como '' (cadena vacía) para “no filtrar”.
$sqlSp = "{CALL SP_BANDEJA_PENDIENTES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
$paramsSp = [
  $fDesde,                      // 1  @i_fDesde
  $fHasta,                      // 2  @i_fHasta
  $entradaFlag,                 // 3  @i_Entrada
  $internoFlag,                 // 4  @i_Interno
  '',                           // 5  @i_Anexo
  (strlen($nroDocumento) ? '%'.$nroDocumento.'%' : '%%'), // 6 @i_cCodificacion
  '%'.$filtroAsunto.'%',        // 7  @i_cAsunto
  ($_GET['tipoDocumento'] ?? ''), // 8  @i_cCodTipoDoc
  $responsableParam,            // 9  @i_iCodTrabajadorResponsable
  $filtroDelg,                  // 10 @i_iCodTrabajadorDelegado
  '',                           // 11 @i_iCodTema
  '',                           // 12 @i_EstadoMov
  $fltAceptado,                 // 13 @i_Aceptado
  $fltSinAceptar,               // 14 @i_SAceptado
  $iCodOficina,                 // 15 @i_iCodOficinaLogin
  'Fecha',                      // 16 @i_columna
  'DESC',                       // 17 @i_dir
  '',                           // 18 @i_remitente
  '',                           // 19 @i_nSIGA
  0,                            // 20 @i_indicacion
  $filtroExpedienteParam,       // 21 @i_expediente   ← NUEVO
  $filtroExtensionParam         // 22 @i_extension    ← NUEVO
];

$stmt = sqlsrv_query($cnx, $sqlSp, $paramsSp);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// -------------------------------
// Post-proceso: mapear nombres de Oficina y Delegado en 1 query por catálogo
// -------------------------------
$raw = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $raw[] = $r;
}



// Map de Oficinas (solo las que aparecen en el set)
$oficinaIds = [];
foreach ($raw as $r) {
    if (!empty($r['iCodOficinaOrigen'])) {
        $oficinaIds[(int)$r['iCodOficinaOrigen']] = true;
    }
}
$oficinaNombres = [];
if (count($oficinaIds)) {
    $ids = implode(',', array_keys($oficinaIds));
    $q   = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas WHERE iCodOficina IN ($ids)";
    $stO = sqlsrv_query($cnx, $q);
    while ($o = sqlsrv_fetch_array($stO, SQLSRV_FETCH_ASSOC)) {
        $oficinaNombres[(int)$o['iCodOficina']] = $o['cNomOficina'];
    }
}

// Map de Delegados (solo los que aparecen)
$delegadosIds = [];
foreach ($raw as $r) {
    if (!empty($r['iCodTrabajadorDelegado'])) {
        $delegadosIds[(int)$r['iCodTrabajadorDelegado']] = true;
    }
}
$delegadosNombres = [];
if (count($delegadosIds)) {
    $ids = implode(',', array_keys($delegadosIds));
    $q   = "SELECT iCodTrabajador, CONCAT(cApellidosTrabajador, ', ', cNombresTrabajador) AS nombre
            FROM Tra_M_Trabajadores WHERE iCodTrabajador IN ($ids)";
    $stT = sqlsrv_query($cnx, $q);
    while ($t = sqlsrv_fetch_array($stT, SQLSRV_FETCH_ASSOC)) {
        $delegadosNombres[(int)$t['iCodTrabajador']] = $t['nombre'];
    }
}
// === Map de documentos principales por iCodTramite (1 sola query en bloque) ===
$tramiteIds = [];
foreach ($raw as $r) {
    if (!empty($r['iCodTramite'])) {
        $tramiteIds[(int)$r['iCodTramite']] = true;
    }
}

$docsByTramite = [];
if (!empty($tramiteIds)) {
    $ids = array_keys($tramiteIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $qDocs = "SELECT t.iCodTramite,
             t.documentoElectronico,
             t.nFlgTipoDoc,
                t.cCodTipoDoc,
                t.cNroDocumento,
                t.iCodTrabajadorRegistro,
                td.cDescTipoDoc
              FROM Tra_M_Tramite t
              LEFT JOIN Tra_M_Tipo_Documento td ON td.cCodTipoDoc = t.cCodTipoDoc
              WHERE t.iCodTramite IN ($placeholders)";
    $stDocs = sqlsrv_query($cnx, $qDocs, $ids);
    if ($stDocs !== false) {
        while ($d = sqlsrv_fetch_array($stDocs, SQLSRV_FETCH_ASSOC)) {
            $docsByTramite[(int)$d['iCodTramite']] = [
              'documentoElectronico'  => $d['documentoElectronico'] ?? null,
              'nFlgTipoDoc'           => isset($d['nFlgTipoDoc']) ? (int)$d['nFlgTipoDoc'] : null,
              'cCodTipoDoc'           => $d['cCodTipoDoc'] ?? null,
              'cNroDocumento'         => $d['cNroDocumento'] ?? null,
              'iCodTrabajadorRegistro'=> isset($d['iCodTrabajadorRegistro']) ? (int)$d['iCodTrabajadorRegistro'] : null,
              'tipoDocDesc'           => $d['cDescTipoDoc'] ?? null,
            ];
        }
    } // si falla, simplemente se quedará sin documento y verás la etiqueta "Sin documento"
}
// === Nombres del registrador ===
$registradores = [];
foreach ($traInfoById as $tinfo) {
    if (!empty($tinfo['iCodTrabajadorRegistro'])) {
        $registradores[$tinfo['iCodTrabajadorRegistro']] = true;
    }
}
$regNombreById = [];
if (!empty($registradores)) {
    $ids = implode(',', array_keys($registradores));
    $qReg = "SELECT iCodTrabajador,
                    CONCAT(cApellidosTrabajador, ', ', cNombresTrabajador) AS nombre
             FROM Tra_M_Trabajadores
             WHERE iCodTrabajador IN ($ids)";
    $stReg = sqlsrv_query($cnx, $qReg);
    if ($stReg) {
        while ($t = sqlsrv_fetch_array($stReg, SQLSRV_FETCH_ASSOC)) {
            $regNombreById[(int)$t['iCodTrabajador']] = $t['nombre'];
        }
    }
}

$tinfo = isset($r['iCodTramite']) ? ($traInfoById[(int)$r['iCodTramite']] ?? []) : [];
$tipoDocumentoSel = $_GET['tipoDocumento'] ?? ''; // puede venir '' o un int

// -------------------------------
// Normalización de filas para que la tabla no cambie
// -------------------------------
$tramites = [];
foreach ($raw as $r) {

  $tinfo = isset($r['iCodTramite']) ? ($docsByTramite[(int)$r['iCodTramite']] ?? []) : [];
  $docElec = isset($tinfo['documentoElectronico']) ? trim((string)$tinfo['documentoElectronico']) : '';
    $tramites[] = [
        'iCodMovimiento'         => $r['iCodMovimiento'] ?? null,
        'iCodTramite'            => $r['iCodTramite'] ?? null, // se usa en URLs
        'iCodTramitePadre'       => $r['iCodTramite'] ?? null, // raíz (para ver flujo)
        'nEstadoMovimiento'      => $r['nEstadoMovimiento'] ?? null,
        'fFecRecepcion'          => $r['fFecRecepcion'] ?? null,
        
         // fechas/flags de SP
    'fFecDerivar'            => $r['fFecDerivar'] ?? null,                // ← fecha de este movimiento
    'cPrioridadDerivar'      => $r['cPrioridadDerivar'] ?? '',  // ← prioridad del movimiento
       // expediente/extensión
        'expediente'             => $r['expediente'] ?? '', // ← viene del SP modificado
        'extensionMovimiento'    => $r['extensionMovimiento'] ,
        'extensionTramite'       => $r['extensionMovimiento'] , // para tu condicional de “flujo raíz”
    
            // tramite (asunto, registro, etc.)
        'cCodificacion'          => $r['cCodificacion'] ?? '',
        'cAsunto'                => $r['cAsunto'] ?? '',
        'fFecRegistro'           => $r['fFecRegistro'] ?? null,

            // documento principal (map nuevo)
            'documentoElectronico'   => $tinfo['documentoElectronico'] ?? null,

            // datos para mostrar doc externo cuando no hay principal
            'nFlgTipoDoc'            => $tinfo['nFlgTipoDoc'] ?? null,
            'cCodTipoDoc'            => $tinfo['cCodTipoDoc'] ?? null,
            'tipoDocDesc'            => $tinfo['tipoDocDesc'] ?? null,
            'cNroDocumento'          => $tinfo['cNroDocumento'] ?? null,
        
            // fecha del tramite raiz
            'fFecDocumento' => $r['fFecDocumento'] ?? null,
        
            // registrador
            'registradorNombre'      => isset($tinfo['iCodTrabajadorRegistro']) ? ($regNombreById[(int)$tinfo['iCodTrabajadorRegistro']] ?? '') : '',
        
            // oficina/delegado (como ya tenías)
             'iCodTrabajadorDelegado' => $r['iCodTrabajadorDelegado'] ?? null,
        'iCodIndicacionDelegado' => $r['iCodIndicacionDerivar'] ?? null, // el SP trae iCodIndicacionDerivar
        'cObservacionesDelegado' => $r['cObservacionesDelegado'] ?? null, // si no existe, quedará vacío
        'fFecDelegado'           => isset($r['fFecDelegadoRecepcion']) ? date_create($r['fFecDelegadoRecepcion']) : null,
        'OficinaOrigen'          => isset($r['iCodOficinaOrigen']) 
                                    ? ($oficinaNombres[(int)$r['iCodOficinaOrigen']] ?? ('Of. ' . (int)$r['iCodOficinaOrigen']))
                                    : '',
        'OficinaDestino'         => '', // no lo necesitamos en la grilla actual
        'nombreDelegado'         => isset($r['iCodTrabajadorDelegado']) 
                                    ? ($delegadosNombres[(int)$r['iCodTrabajadorDelegado']] ?? '')
                                    : ''
    ];
}

// ==== Conteo y paginación ====
$totalRegistros = count($tramites);

// Tamaño de página (puedes cambiar el 40 si deseas)
$porPagina = isset($_GET['pp']) ? max(5, min(200, (int)$_GET['pp'])) : 40;

// Página actual
$pagina = isset($_GET['pag']) ? max(1, (int)$_GET['pag']) : 1;

// Total de páginas
$paginas = max(1, (int)ceil($totalRegistros / $porPagina));
if ($pagina > $paginas) { $pagina = $paginas; }

// Cálculo de slice
$inicio = ($pagina - 1) * $porPagina;
$tramitesPagina = array_slice($tramites, $inicio, $porPagina);

// Rango visual “Mostrando X–Y de Z”
$desdeN = ($totalRegistros === 0) ? 0 : ($inicio + 1);
$hastaN = min($inicio + $porPagina, $totalRegistros);

// Helper para links de paginación preservando filtros
function linkPag($p) {
    $q = $_GET;
    $q['pag'] = $p;
    $qs = http_build_query($q);
    return 'bandejaPendientes.php?' . $qs;
}
?>

<!-- Material Icons y CSS -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root {
  --primary: #005a86;
  --secondary: #c69157;
  --stick-top: 105px;
  --panel-h: 0px;
--panel-gap: 70px;  /* antes 14px */}

body {
  margin: 0;
  padding: 0;
}
body > .contenedor-principal { margin-top: var(--stick-top); }
.contenedor-principal { position: relative; }
.panel-fijo{
  position: fixed;
  top: var(--stick-top);
  left: 0; right: 0;
  z-index: 950;
  background: #fff;
  box-shadow: 0 2px 0 rgba(0,0,0,.04);
}
/* CONTENEDOR DE LA TABLA: inicia debajo del panel fijo */
.lista-scroll{
  margin-top: calc(var(--panel-h) + var(--panel-gap)); /* ← antes solo var(--panel-h) */
    padding-bottom: 24px;               /* respiro */
}
 
/* IMPORTANTE: no uses 100vw, usa 100% para evitar scroll lateral que rompe sticky */
.barra-titulo{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  background-color:var(--primary);
  color:#fff;
  padding:10px 20px;
  font-weight:bold;
  font-size:15px;
  width:100%;              /* ← antes 100vw */
  box-sizing:border-box;
  margin:0;
  z-index:910;             /* por encima de la tabla */
}

/* Sticky individual por barra */
.barra-top{               /* BANDEJA DE PENDIENTES */
  position:-webkit-sticky;
  position:sticky;
  top:var(--stick-top);
}

.barra-registros{         /* REGISTROS + paginación */
  position:-webkit-sticky;
  position:sticky;
  top:calc(var(--stick-top) + var(--barra-altura));
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
 

/* contador junto a “REGISTROS” */
.badge-registros{
  display:inline-block;
  margin-left:8px;
  padding:2px 8px;
  font-size:12px;
  border-radius:999px;
  color:#fff;
  background:#1f2937;
  vertical-align:baseline;
}

/* paginación compacta (estilo general) */
.paginacion{
  display:flex; align-items:center; gap:6px;
  /* sin ancho ni borde: se aloja dentro de la barra */
}
.paginacion a,
.paginacion span.current,
.paginacion span.disabled,
.paginacion .ellipsis{
  display:inline-block; min-width:32px; text-align:center;
  padding:6px 8px; border:1px solid #ddd; border-radius:6px;
  text-decoration:none; color:#333; font-size:14px; line-height:1;
}
.paginacion span.current{
  font-weight:700; background:#f2f6ff; border-color:#cfe2ff; color:#114a77;
}
.paginacion span.disabled{ opacity:.45; border-style:dashed; pointer-events:none; }
.paginacion .ellipsis{ border-color:transparent; }

/* overrides cuando la paginación está dentro de la barra azul */
.barra-titulo .paginacion a,
.barra-titulo .paginacion span.current,
.barra-titulo .paginacion span.disabled,
.barra-titulo .paginacion .ellipsis{
  color:#fff; border-color:rgba(255,255,255,.55); background:transparent;
}
.barra-titulo .paginacion span.current{
  background:rgba(255,255,255,.18);
  border-color:rgba(255,255,255,.85);
  color:#fff;
}
.barra-titulo .paginacion span.disabled{ border-color:rgba(255,255,255,.35); }
.barra-titulo .paginacion .ellipsis{ color:rgba(255,255,255,.8); }
/* Encabezado sticky (vive dentro del .panel-fijo) */
.tabla-head-sticky{ 
  width:100%;
   background:#f6f7f9;
    border-top:1px solid #e7ebef; 
    border-bottom:1px solid #e7ebef; 
    font-weight:600; font-size:13.5px; 
    color:#2f3a44; 
    box-sizing:border-box; }
.tabla-head-sticky .ths{
  display:grid;
  grid-template-columns: 70px 110px 90px minmax(240px, 1.15fr) minmax(220px, 1fr) 180px 100px 220px;
  gap:0;
  align-items:center;
}
.tabla-head-sticky .th{ padding:8px 12px; border-right:1px solid #edf1f4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tabla-head-sticky .th:last-child{ border-right:none; }
.tabla-head-sticky .th.center{ text-align:center; }

/* Lista tipo “tabla” con grid */
.lista-grid{
  display:flex;
  flex-direction:column;
  gap:10px;                 /* antes 8px */
  margin-top:16px;
}
.grid-row{
  display:grid;
  grid-template-columns: 70px 110px 90px minmax(240px, 1.15fr) minmax(220px, 1fr) 180px 100px 220px;
  gap:0; align-items:center;
  border:1px solid #e9edf1; border-radius:10px; background:#fff;
  box-shadow: 0 2px 8px rgba(7,23,42,.04);
  transition: box-shadow .15s ease, transform .15s ease;
}
.grid-row:hover{
  box-shadow:0 8px 22px rgba(7,23,42,.10);
  transform:translateY(-1px);
}
/* Un poco más de padding y altura mínima por fila */
.grid-row .cell{
  padding:14px 16px;        /* antes 10px 12px */
  min-height:56px;          /* antes 42px */
  display:flex;
  align-items:center;
  gap:10px;                 /* antes 8px */
}
.grid-row .cell:last-child{ border-right:none; }
/* apilar contenido de la celda expediente y que crezca en alto */
.grid-row .cell.expediente-cell{
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  text-align:left;
  min-height:72px;  /* ↑ más alto para que entre fecha + registrador + prioridad */
}
.grid-row .cell.expediente-cell small{ line-height:1.25; }
.cell.doc .link-ico{ color:#6c757d; text-decoration:none; display:inline-flex; align-items:center; }
.cell.asunto{ color:#1b2b3a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.muted{ color:#6b7280; }

/* chips y badges */
.chip-adjunto{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; border:1px solid #e4eaf0; text-decoration:none; color:#0a3b5a; background:#f8fbfe; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chip-icon{ font-size:18px; }
.badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; line-height:1; }
.badge-danger{ background:#fde2e1; color:#c62828; }
.badge-info{ background:#e3f2fd; color:#0d47a1; }
.badge-warn{ background:#fff3cd; color:#8a6d3b; }

/* Botones “link” */
.btn.btn-link{ background:none; border:none; padding:4px; cursor:pointer; color:var(--primary,#005a86); font-size:18px; vertical-align:middle; }
.btn.btn-link:hover{ color:#00496b; }


 /* Grupo horizontal de checks sin label flotante */
.input-group{
  display:flex; align-items:center; gap:14px; height:42px;
}
.input-group .group-label{
  font-size:13px; color:#666; margin-right:2px; white-space:nowrap;
}
.input-group label.check{
  position:static; transform:none; font-size:14px; color:#333;
  display:flex; align-items:center; gap:6px; margin:0;
}
.input-group input[type="checkbox"]{ margin:0; }

</style>

<div class="contenedor-principal">
 
  <!-- TÍTULO PRINCIPAL PEGADO AL HEADER -->
  <div class="panel-fijo" id="panelFijo">
  <div class="barra-titulo">BANDEJA DE PENDIENTES</div>
    <!-- FORMULARIO OCUPANDO TODA LA PANTALLA -->
  <form class="filtros-formulario">
    <!-- COLUMNA IZQUIERDA -->
    <div class="columna-izquierda">
      <div class="fila">
        <!-- <div class="input-container">
          <input type="text" name="anio" placeholder=" "  >
          <label>Año</label>
        </div> -->
        <div class="input-container">
          <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" "  >
          <label>N° Expediente</label>
        </div>
        <div class="input-container">
          <input type="text" name="extension" value="<?= $valorExtension ?>" placeholder=" "  >
          <label>Extensión</label>
        </div>
      </div>

      <!-- <div class="fila">
        <div class="input-container">
          <select name="oficina_origen"  >
            <option value="" disabled selected hidden></option>
            <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $of['cNomOficina'] ?>"><?= $of['cNomOficina'] ?></option>
            <?php endwhile; ?>
          </select>
          <label>Oficina de Origen</label>
        </div>
      </div> -->

      <div class="fila">
        <div class="input-container">
          <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" "  >
          <label>Desde</label>
        </div>
        <div class="input-container">
          <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" "  >
          <label>Hasta</label>
        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div class="columna-derecha">
      <div class="fila">

        <div class="input-container">


          <select name="tipoDocumento" id="tipoDocumento" class="FormPropertReg">
            <option value="">-- Todos --</option>
            <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): 
                  $val = (string)$td['cCodTipoDoc'];
                  $txt = $td['cDescTipoDoc'];
                  $selected = ($tipoDocumentoSel !== '' && (string)$tipoDocumentoSel === $val) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($val) ?>" <?= $selected ?>>
                <?= htmlspecialchars($txt) ?>
              </option>
            <?php endwhile; ?>
          </select>


          <label for="tipoDocumento">Tipo de Documento</label>
        </div>

        <div class="input-container">
  <input type="text" name="nro_documento" value="<?= $valorNroDocumento ?>" placeholder=" ">
  <label>Nro de Documento</label>
</div>

<div class=" input-group">
        <span class="group-label">Tipo de Trámite</span>
        <label class="check">
          <input type="checkbox" name="entrada" value="1" <?= $chkEntrada?'checked':'' ?>> Externo
        </label>
        <label class="check">
          <input type="checkbox" name="interno" value="1" <?= $chkInterno?'checked':'' ?>> Interno
        </label>
      </div>

<div class="input-group" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
  <span style="font-size:13px; color:#666;">Estado</span>
  <label class="chk">
    <input type="checkbox" name="estado_aceptado" value="1" <?= $fltAceptado==='1' ? 'checked' : '' ?>>
    Aceptado
  </label>
  <label class="chk">
    <input type="checkbox" name="estado_sinaceptar" value="1" <?= $fltSinAceptar==='1' ? 'checked' : '' ?>>
    Sin aceptar
  </label>
</div>

      </div>   <!-- fin primera fila de segunda columna -->

      <div class="fila">
        <div class="input-container" style="flex: 1;">
          <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" "  >
          <label>Asunto</label>
        </div>
      </div>

      <div class="fila">
  <!-- RESPONSABLE (Jefes históricos) -->
  <div class="input-container" style="flex:0 0 230px; min-width:230px;">
    <select name="iCodTrabajadorResponsable" id="iCodTrabajadorResponsable"  >
      <option value="" <?= $filtroResp===''?'selected':'' ?> disabled hidden></option>
      <?php
        // clonar cursor si hace falta volver a recorrer:
        // $rsResp = sqlsrv_query($cnx, $sqlResp, [$iCodOficina]);
        while ($r = sqlsrv_fetch_array($rsResp, SQLSRV_FETCH_ASSOC)):
          $val = (int)$r['iCodTrabajador'];
          $txt = htmlspecialchars($r['cApellidosTrabajador'].', '.$r['cNombresTrabajador'], ENT_QUOTES, 'UTF-8');
          $sel = ($responsableParam !== '' && (int)$responsableParam === $val) ? 'selected' : '';
        ?>
          <option value="<?= $val ?>" <?= $sel ?>><?= $txt ?></option>
        <?php endwhile; ?>
    </select>
    <label>Responsable</label>
  </div>

  <!-- DELEGADO A (Profesionales) -->
  <div class="input-container" style="flex:0 0 230px; min-width:230px;">
    <select name="iCodTrabajadorDelegado" id="iCodTrabajadorDelegado"  >
      <option value="" <?= $filtroDelg===''?'selected':'' ?> disabled hidden></option>

      <!-- Opciones especiales de filtro -->
      <option value="-1" <?= ($filtroDelg==='-1')?'selected':'' ?>>NO DELEGADOS</option>
      <option value="-2" <?= ($filtroDelg==='-2')?'selected':'' ?>>DELEGADOS</option>

      <optgroup label="‑‑ Delegado a: ‑‑">
        <?php
          // $rsDelg = sqlsrv_query($cnx, $sqlDelg, [$iCodOficina]); // reejecutar si ya se consumió
          while ($d = sqlsrv_fetch_array($rsDelg, SQLSRV_FETCH_ASSOC)):
            $val = (int)$d['iCodTrabajador'];
            $txt = htmlspecialchars($d['cApellidosTrabajador'].', '.$d['cNombresTrabajador'], ENT_QUOTES, 'UTF-8');
            $sel = ($filtroDelg !== '' && (int)$filtroDelg === $val) ? 'selected' : '';
        ?>
          <option value="<?= $val ?>" <?= $sel ?>><?= $txt ?></option>
        <?php endwhile; ?>
      </optgroup>
    </select>
    <label>Delegado a</label>
  </div>

  <div class="botones-filtro" style="margin-left:auto;">
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

 <!-- barra de registros + paginación -->
  <div class="barra-titulo">
      <div class="bt-left">
    REGISTROS
    <span class="badge-registros"><?= number_format($totalRegistros) ?></span>
    <small style="font-weight:normal; margin-left:10px;">
      Mostrando <?= $desdeN ?>–<?= $hastaN ?> de <?= number_format($totalRegistros) ?>
    </small>
  </div>
  <div class="bt-right">
    <div class="paginacion">
      <?php
        $win  = 2;                               // ventana de páginas a cada lado
        $from = max(1, $pagina - $win);
        $to   = min($paginas, $pagina + $win);

        // «Anterior»
        if ($pagina > 1) {
          echo '<a href="'.htmlspecialchars(linkPag($pagina-1)).'">&laquo;</a>';
        } else {
          echo '<span class="disabled">&laquo;</span>';
        }

        // Primera + puntos suspensivos
        if ($from > 1) {
          echo '<a href="'.htmlspecialchars(linkPag(1)).'">1</a>';
          if ($from > 2) echo '<span class="ellipsis">…</span>';
        }

        // Rango central
        for ($p=$from; $p<=$to; $p++) {
          if ($p == $pagina) {
            echo '<span class="current">'.$p.'</span>';
          } else {
            echo '<a href="'.htmlspecialchars(linkPag($p)).'">'.$p.'</a>';
          }
        }

        // Última + puntos suspensivos
        if ($to < $paginas) {
          if ($to < $paginas-1) echo '<span class="ellipsis">…</span>';
          echo '<a href="'.htmlspecialchars(linkPag($paginas)).'">'.$paginas.'</a>';
        }

        // «Siguiente»
        if ($pagina < $paginas) {
          echo '<a href="'.htmlspecialchars(linkPag($pagina+1)).'">&raquo;</a>';
        } else {
          echo '<span class="disabled">&raquo;</span>';
        }
      ?>
      </div>
    </div>
  </div>


<!-- CONTENEDOR SCROLL DE LA LISTA -->

    <!-- Cabecera fija como tabla -->
    <div class="tabla-head-sticky" id="tablaHeadSticky">
    <div class="ths">
    <div class="th  "  style="width:100px;">Seleccion</div>
    <div class="th  "         style="width:140px;">Expediente</div>
    <div class="th  "         style="width:120px;">Extensión</div>
    <div class="th  "         style="width:160px;">Documento</div>
    <div class="th  "         style="width:200px;">Asunto</div>
    <div class="th  "         style="width:180px;">Derivado por</div>
    <div class="th  "  style="width:90px;">Estado</div>
    <div class="th  "  style="width:220px;">Opciones</div>
    </div> 
    </div>
  </div><!-- /panel-fijo -->

  <!-- ⬇️ cuerpo scrollable (una sola vez) -->
<div class="lista-scroll" id="listaScroll">
  <!-- Cuerpo simulado con grid (ya no <tbody>) -->
  <div class="grid-rows">
            <?php foreach ($tramitesPagina as $tramite): ?>
              <div class="grid-row" id="fila-<?= $tramite['iCodMovimiento'] ?>">


<!-- SELECCION (checkbox) -->
<div class="cell center" style="width:100px;">
  <input type="checkbox"
         class="select-row"
         name="Seleccion[]"
         value="<?= $tramite['iCodMovimiento'].'|'.$tramite['iCodTramite'] ?>"
         data-mov="<?= (int)$tramite['iCodMovimiento'] ?>"
         data-tramite="<?= (int)$tramite['iCodTramite'] ?>">
</div>


<!-- EXPEDIENTE -->
<div class="cell expediente-cell" style="width:140px;">
  <div><strong><?= htmlspecialchars($tramite['expediente']) ?></strong></div>

  <?php if (!empty($tramite['cPrioridadDerivar'])): ?>
    <small class="muted"><?= htmlspecialchars($tramite['cPrioridadDerivar']) ?></small>
  <?php endif; ?>
  
  <?php if (!empty($tramite['fFecDocumento']) && ($tramite['fFecDocumento'] instanceof DateTimeInterface)): ?>
  <small class="muted"><?= $tramite['fFecDocumento']->format("d/m/Y H:i") ?></small>
<?php endif; ?>


  <!-- <?php if (!empty($tramite['registradorNombre'])): ?>
    <small class="muted"><?= htmlspecialchars($tramite['registradorNombre']) ?></small>
  <?php endif; ?> -->


</div>

<!-- EXTENSIÓN -->
<div class="cell" style="width:90px;">
  <?= htmlspecialchars($tramite['extensionMovimiento']) ?>
</div>

                <!-- DOCUMENTO -->
<?php
$hasPdf       = !empty($tramite['documentoElectronico']);
$isExterno    = isset($tramite['nFlgTipoDoc']) && (int)$tramite['nFlgTipoDoc'] === 1;

$tipoNombre   = $tramite['tipoDocDesc'] ?: $tramite['cCodTipoDoc'];  // cDescTipoDoc ya viene como tipoDocDesc
$codificacion = trim($tramite['cCodificacion'] ?? '');
$nroDoc       = trim((string)($tramite['cNroDocumento'] ?? ''));

$flowId     = $isExterno ? ($tramite['iCodTramite'] ?? '') : ($tramite['iCodTramitePadre'] ?? '');
$flowUrl    = $isExterno ? 'bandejaFlujoMesaDePartes.php' : 'bandejaFlujo.php';
$extension  = (int)($tramite['extensionMovimiento'] ?? $tramite['extension'] ?? 1);
?>

<div class="doc-col" >
  <?php if (!$isExterno): ?>
    <!-- INTERNOS -->
    <!-- Fila 1: Tipo de documento -->
    <div class="doc-row" style="user-select:text;cursor:text;">
      <?= htmlspecialchars($tipoNombre) ?>
    </div>
    <!-- Fila 2: cCodificacion -->
    <div class="doc-row" style="user-select:text;cursor:text;">
      <?= htmlspecialchars($codificacion) ?>
    </div>
    <!-- Fila 3: íconos PDF + Flujo -->
    <div class="doc-row" style="display:flex;align-items:center;gap:10px;">
      <?php if ($hasPdf): ?>
        <a href="../<?= (
  ($tramite['fFecDocumento'] instanceof DateTimeInterface
    && $tramite['fFecDocumento']->format('Y-m-d') >= '2025-08-27'
  ) ? 'STDD_marchablanca' : 'STD'
) ?>/cDocumentosFirmados/<?= urlencode($tramite['documentoElectronico']) ?>"
   target="_blank" title="Abrir documento">
          <img src="./img/pdf.png" alt="PDF" style="width:18px;height:auto;vertical-align:middle;">
        </a>
      <?php endif; ?>
      <a href="#"
         class="ver-flujo-btn"
         data-id="<?= htmlspecialchars($flowId) ?>"
         data-extension="<?= (int)$extension ?>"
         data-url="<?= $flowUrl ?>"
         title="Ver flujo"
         style="color:#6c757d;text-decoration:none;">
        <span class="material-icons" style="font-size:20px;vertical-align:middle;">device_hub</span>
      </a>
    </div>

  <?php else: ?>
    <!-- EXTERNOS -->
    <!-- Fila 1: cCodificacion (sin negrita / sin color institucional) -->
    <div class="doc-row" style="user-select:text;cursor:text;color:#222;">
      <?= htmlspecialchars($codificacion) ?>
    </div>
    <!-- Fila 2: Tipo de documento + número -->
    <div class="doc-row" style="user-select:text;cursor:text;color:#222;">
      <?= htmlspecialchars(trim(($tipoNombre ? $tipoNombre.' ' : '') . $nroDoc)) ?>
    </div>
    <!-- Fila 3: ícono Flujo -->
    <div class="doc-row">
      <a href="#"
         class="ver-flujo-btn"
         data-id="<?= htmlspecialchars($flowId) ?>"
         data-extension="<?= (int)$extension ?>"
         data-url="<?= $flowUrl ?>"
         title="Ver flujo"
         style="color:#6c757d;text-decoration:none;">
        <span class="material-icons" style="font-size:20px;vertical-align:middle;">device_hub</span>
      </a>
    </div>
  <?php endif; ?>
</div>




                     <!-- ASUNTO -->
        <div class="grid-cell" style="width:200px;">
          <?= htmlspecialchars($tramite['cAsunto']) ?>
        </div>

                          <!-- DERIVADO POR -->
                       <div class="grid-cell" style="width:180px;">
                        <?= htmlspecialchars($tramite['OficinaOrigen']) ?><br>
                          <?php if (!empty($tramite['iCodIndicacionDerivar'])): ?>
    <small style="color:#444;">Indic.: <?= htmlspecialchars($tramite['iCodIndicacionDerivar']) ?></small><br>
  <?php endif; ?>
                        <small style="color: gray; font-size: 12px;">
                        <?php
      if (!empty($tramite['fFecDerivar']) && ($tramite['fFecDerivar'] instanceof DateTimeInterface)) {
          echo $tramite['fFecDerivar']->format("d/m/Y H:i");
      }
    ?>
                            </small>
                        </div>

                     <!-- ESTADO -->
        <div class="grid-cell" style="width:90px;" id="estado-<?= $tramite['iCodMovimiento'] ?>">
                    <?php if (empty($tramite['fFecRecepcion'])): ?>
                          <span style="font-weight: bold; color: #d9534f;">Sin Aceptar</span>
                      <?php else: ?>
                          <span style="font-weight: bold; color: #0d6efd;">Aceptado</span><br>
                          <small style="color: gray;"><?= $tramite['fFecRecepcion']->format("d/m/Y H:i") ?></small>
                          <?php if (!empty($tramite['nombreDelegado'])): ?>
                              <br><span style="font-weight: bold; color: #ff9800;">Delegado</span><br>
                              <small style="color: gray;"><?= htmlspecialchars($tramite['nombreDelegado']) ?></small>
                          <?php endif; ?>
                      <?php endif; ?>
                      </div>

                     <!-- OPCIONES -->
                    <div class="grid-cell acciones" style="width:220px;" id="acciones-<?= $tramite['iCodMovimiento'] ?>">
                    <?php if (empty($tramite['fFecRecepcion'])): ?>
                          <!-- Mostrar botón Aceptar -->
                          <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                              <button class="btn btn-primary aceptar-btn" 
                                      data-movimiento="<?= $tramite['iCodMovimiento'] ?>" 
                                      data-tramite="<?= $tramite['iCodTramite'] ?>"
                                      title="Aceptar">
                                  <span class="material-icons">drafts</span>
                              </button>
                          </div>
                      <?php else: ?>
                        <!-- Agrupar todos los botones en un mismo contenedor flex -->
                        <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                                <!-- Derivar -->
                        <a href="registroDerivar.php?iCodTramite=<?= $tramite['iCodTramite'] ?>&iCodMovimiento=<?= $tramite['iCodMovimiento'] ?>" 
                            class="btn btn-link" title="Derivar">
                            <span class="material-icons">forward_to_inbox</span>
                        </a>
                            <!-- Delegar -->
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
                        </div>

</div>
            <?php endforeach; ?>
</div>
</div>
 
 

<!-- $$$$$$$$$$$$$$ INICIO MODAL DELEGAR -->
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
<!-- $$$$$$$$$$$$$$$ FIN MODAL DELEGAR-->

<!-- $$$$$$$$$$$$$$$$$$ inicio MODAL OBSERVAR -->
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
<!-- $$$$$$$$$$$$$$$$$$ fin MODAL OBSERVAR -->


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

(function(){
  const panel = document.getElementById('panelFijo');
  const lista = document.getElementById('listaScroll');

  function updateOffsets(){
    // getBoundingClientRect() suele ser más fiel con fuentes/iconos ya renderizados
    const h = panel.getBoundingClientRect().height;
    document.documentElement.style.setProperty('--panel-h', Math.ceil(h) + 'px');
  }

  // correr ahora y cuando cambie el layout
  updateOffsets();
  new ResizeObserver(updateOffsets).observe(panel);
  window.addEventListener('load', updateOffsets);   // por si faltaba alguna fuente/icono
  window.addEventListener('resize', updateOffsets);
})();

//// $$$$ CHECKBOXES DE SELECCION : INICIO $$$$
// === Selección de filas ===
function getSeleccionados(){
  const arr = [];
  document.querySelectorAll('.select-row:checked').forEach(chk => {
    arr.push({
      iCodMovimiento: parseInt(chk.dataset.mov, 10),
      iCodTramite:    parseInt(chk.dataset.tramite, 10),
    });
  });
  return arr;
}

// Log automático cada vez que cambie una selección
document.addEventListener('change', (e) => {
  if (e.target && e.target.classList.contains('select-row')) {
    console.log('Seleccionados:', getSeleccionados());
  }
});

// Opción: exponer una función global para llamarla cuando quieras
window.logSeleccion = () => console.log('Seleccionados:', getSeleccionados());
//// $$$$ CHECKBOXES DE SELECCION : FIN $$$$

</script>