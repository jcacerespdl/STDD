<?php
session_start();
include("head.php");
include("conexion/conexion.php");

$iCodOficina = $_SESSION['iCodOficinaLogin'];

// Capturar filtros
$filtroExpediente = $_GET['expediente'] ?? '';
$filtroAsunto = $_GET['asunto'] ?? '';
$filtroDesde = $_GET['desde'] ?? '';
$filtroHasta = $_GET['hasta'] ?? '';
$filtroTipoDoc = $_GET['tipoDocumento'] ?? '';
$filtroOficina = $_GET['oficinasDestino'] ?? '';

// Tipos de documentos
$sqlTiposDoc = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$resultTiposDoc = sqlsrv_query($cnx, $sqlTiposDoc);
$tiposDoc = [];
while ($row = sqlsrv_fetch_array($resultTiposDoc, SQLSRV_FETCH_ASSOC)) {
    $tiposDoc[] = $row;
}

// Oficinas
$sqlOficinas = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas";
$resultOficinas = sqlsrv_query($cnx, $sqlOficinas);
$oficinas = [];
while ($row = sqlsrv_fetch_array($resultOficinas, SQLSRV_FETCH_ASSOC)) {
    $oficinas[] = $row;
}

// Trámites enviados por esta oficina
$sql = "
    SELECT 
        ISNULL(m.iCodTramiteDerivar, m.iCodTramite) AS iCodTramite,
        t.cAsunto,
        t.documentoElectronico,
        t.expediente,
        t.fFecRegistro,
        t.nFlgEnvio,
        t.nFlgTipoDerivo,
        t.extension,
        t.nFlgTipoDoc, 
        m.iCodMovimiento,
        m.iCodTramite AS iCodTramitePadre,
        m.iCodTramiteDerivar,
        m.iCodMovimientoDerivo
    FROM Tra_M_Tramite_Movimientos m
    JOIN Tra_M_Tramite t 
        ON t.iCodTramite = ISNULL(m.iCodTramiteDerivar, m.iCodTramite)
    WHERE m.iCodOficinaOrigen = ?
";
$params = [$iCodOficina];
if ($filtroExpediente !== '') {
    $sql .= " AND t.expediente LIKE ?";
    $params[] = "%$filtroExpediente%";
}
if ($filtroAsunto !== '') {
    $sql .= " AND t.cAsunto LIKE ?";
    $params[] = "%$filtroAsunto%";
}
if ($filtroDesde !== '') {
    $sql .= " AND t.fFecRegistro >= ?";
    $params[] = $filtroDesde;
}
if ($filtroHasta !== '') {
    $sql .= " AND t.fFecRegistro <= DATEADD(day, 1, ?)";
    $params[] = $filtroHasta;
}
$sql .= " ORDER BY t.fFecRegistro DESC";

$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

$tramites = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $row;
}

$tramitesUnicos = [];
$icodTramiteVistos = [];

foreach ($tramites as $t) {
    if (!in_array($t['iCodTramite'], $icodTramiteVistos)) {
        $tramitesUnicos[] = $t;
        $icodTramiteVistos[] = $t['iCodTramite'];
    }
}
$tramites = $tramitesUnicos;

// Complementarios
$sql_docs = "SELECT iCodTramite, iCodDigital, cDescripcion  FROM Tra_M_Tramite_digitales";
$stmt_docs = sqlsrv_query($cnx, $sql_docs);
$docsComplementarios = [];
while ($doc = sqlsrv_fetch_array($stmt_docs, SQLSRV_FETCH_ASSOC)) {
    $docsComplementarios[$doc['iCodTramite']][] = $doc;
}
?>
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
.sugerencias-dropdown {
    position: absolute;
    background: white;
    border: 1px solid #ccc;
    max-height: 150px;
    overflow-y: auto;
    z-index: 10;
}
.titulo-principal {
    color: var(--primary, #005a86);
    font-size: 22px;
    font-weight: bold;
    margin-top: 0;
    margin-bottom: 20px;
}

</style>

<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
    <div class="titulo-principal">BANDEJA DE ENVIADOS</div>

    <div class="card">
        <div class="card-title">CRITERIOS DE BÚSQUEDA</div>
        <form method="GET">
        <div class="row">
    <!-- PRIMERA FILA -->
    <div style="flex: 1; display: flex; gap: 10px;">
        <div class="input-container">
            <input type="text" name="expediente" value="<?= htmlspecialchars($filtroExpediente) ?>" placeholder=" ">
            <label>N° Expediente</label>
        </div>
        <div class="input-container">
            <input type="text" name="extension" placeholder=" ">
            <label>Extensión</label>
        </div>
    </div>

    <div style="flex: 1; display: flex; gap: 10px;">
        <div class="input-container" style="flex: 1;">
            <input type="date" name="desde" value="<?= htmlspecialchars($filtroDesde) ?>" placeholder=" ">
            <label>Desde</label>
        </div>
        <div class="input-container" style="flex: 1;">
            <input type="date" name="hasta" value="<?= htmlspecialchars($filtroHasta) ?>" placeholder=" ">
            <label>Hasta</label>
        </div>
    </div>
</div>

<div class="row">
    <!-- SEGUNDA FILA -->
    <div style="flex: 1; display: flex; gap: 10px;">
        <div class="input-container" style="flex: 1;">
            <select name="tipoDocumento">
                <option value="" disabled selected hidden></option>
                <?php foreach ($tiposDoc as $tipo): ?>
                    <option value="<?= $tipo['cCodTipoDoc'] ?>"><?= $tipo['cDescTipoDoc'] ?></option>
                <?php endforeach; ?>
            </select>
            <label>Tipo de Documento</label>
        </div>
        <div class="input-container" style="flex: 1;">
            <input type="text" name="nro_doc" placeholder=" ">
            <label>N° de Documento</label>
        </div>
    </div>
    <div class="input-container" style="flex: 1;">
        <input type="text" name="asunto" value="<?= htmlspecialchars($filtroAsunto) ?>" placeholder=" ">
        <label>Asunto</label>
    </div>
</div>

<div class="row">
    <!-- TERCERA FILA -->
    <div class="input-container" style="flex: 1;">
        <input type="text" name="registrador" placeholder=" ">
        <label>Registrador</label>
    </div>
    <div class="input-container oficina-ancha" style="flex: 1; position: relative;">
        <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off">
        <label for="nombreOficinaInput">Nombre de Oficina</label>
        <input type="hidden" id="oficinasDestino" name="oficinasDestino" value="<?= htmlspecialchars($filtroOficina) ?>">
        <div id="sugerenciasOficinas" class="sugerencias-dropdown"></div>
    </div>
</div>

<!-- CHECKBOXES -->
<div class="row" style="margin-top: 10px;">
    <div style="flex: 1;">
        <label><input type="checkbox" name="tipo_doc_externo"> Externos</label>
        <label><input type="checkbox" name="tipo_doc_interno"> Internos</label>
     
        <label><input type="checkbox" name="enviado_si"> Enviados</label>
        <label><input type="checkbox" name="enviado_no"> Por Aprobar</label>
    </div>
</div>


            <!-- Botones -->
            <div class="row" style="justify-content: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">search</span> Buscar
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='bandejaEnviados.php'">
                    <span class="material-icons">autorenew</span> Reestablecer
                </button>
                <button type="button" class="btn btn-primary" onclick="exportarExcel()">
                    <span class="material-icons">grid_on</span> Exportar Excel
                </button>
            </div>
        </form>
    </div>
    <div class="card">
    <table>
        <thead>
            <tr>
            <th style="width: 110px;">Expediente</th>
        <th style="width: 80px;">Ext.</th> <!-- Nueva columna -->

                <th style="width: 130px;">Fecha</th>
                <th style="width: 200px;">Asunto</th>
                <th style="width: 240px;">Documento Principal</th>
                <th style="width: 340px;">Complementarios</th>
                <th style="width: 100px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tramites as $tram): ?>
                <?php $idDoc = $tram["iCodTramite"]; ?>
                <tr>
                    <td>
                        <?= $tram['expediente'] ?>
                        <div style="font-size: 12px; color: #666;">
                            <?= ($tram['nFlgTipoDerivo'] == 1) ? '(derivado)' : '(generado)' ?>
                        </div>
                    
                    </td>
                    <td style="text-align: center;"><?= htmlspecialchars($tram['extension']) ?></td> <!-- NUEVA CELDA -->


                    <td><?= isset($tram['fFecRegistro']) ? $tram['fFecRegistro']->format("d/m/Y H:i") : '' ?></td>
                    <td><?= htmlspecialchars($tram['cAsunto'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($tram['documentoElectronico'])): ?>
                            <a href="./cDocumentosFirmados/<?= urlencode($tram['documentoElectronico']) ?>"
                               class="chip-adjunto"
                               target="_blank"
                               title="<?= htmlspecialchars($tram['documentoElectronico']) ?>">
                                <span class="material-icons chip-icon">picture_as_pdf</span>
                                <span class="chip-text"><?= htmlspecialchars($tram['documentoElectronico']) ?></span>
                            </a>
                            <?php if ($tram['nFlgEnvio'] == 0): ?>
                                <div style="font-size: 12px; color: #d9534f;">(por aprobar)</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span>Sin documento</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($docsComplementarios[$idDoc])): ?>
                            <?php foreach ($docsComplementarios[$idDoc] as $doc): ?>
                                <div class="complementario-item" style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <input type="checkbox" class="chk-complementario"
                                           data-icodtramite="<?= $idDoc ?>"
                                           data-icoddigital="<?= $doc['iCodDigital'] ?>">

                                    <a href="./cAlmacenArchivos/<?= urlencode($doc['cDescripcion']) ?>"
                                       class="chip-adjunto"
                                       target="_blank"
                                       title="<?= htmlspecialchars($doc['cDescripcion']) ?>">
                                        <span class="material-icons chip-icon chip-doc">article</span>
                                        <span class="chip-text"><?= htmlspecialchars($doc['cDescripcion']) ?></span>
                                    </a>

                                    <a href="detallesFirmantes.php?iCodTramite=<?= $idDoc ?>&iCodDigital=<?= $doc['iCodDigital'] ?>"
                                       title="Ver firmantes"
                                       style="text-decoration: none;"
                                       onclick="return abrirModalFirmantes(this.href)">
                                        <span class="material-icons" style="font-size: 18px; color: #555;">group</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span>Sin Complementarios</span>
                        <?php endif; ?>
                    </td>
                    <td>
                    <div style="display: flex; flex-direction: row; justify-content: center; align-items: center; gap: 10px;">
                            <!-- Ícono de Editar -->
                                    <?php
                                    if ($tram['nFlgTipoDoc'] == 1) {
                                        $archivoEditar = 'registroEditarMP.php';
                                    } else {
                                        $archivoEditar = ($tram['nFlgTipoDerivo'] == 1)
                                            ? 'registroDerivarSubsanar.php'
                                            : 'registroOficinaSubsanar.php';
                                    }
                                    ?>

                                    <a href="<?= $archivoEditar ?>?iCodTramite=<?= $tram['iCodTramite'] ?>"
                                    title="Editar"
                                    style="color: #0d6efd; text-decoration: none;">
                                        <span class="material-icons" style="font-size: 22px;">edit</span>
                                    </a>

                            <!-- Ícono de Flujo -->
                            <!-- <?php if ($tram['nFlgTipoDoc'] == 1): ?>
                                <a href="bandejaFlujoMesaDePartes.php?iCodTramite=<?= $tram['iCodTramite'] ?>&extension=<?= $tram['extension'] ?>"
                                title="Ver flujo"
                                target="_blank"
                                style="color: #6c757d; text-decoration: none;">
                                    <span class="material-icons" style="font-size: 22px;">device_hub</span>
                                </a>
                            <?php else: ?>
                                <a href="<?= $tram['extension'] == 1 ? 'bandejaFlujoraiz.php' : 'bandejaFlujo.php' ?>?iCodTramite=<?= $tram['iCodTramitePadre'] ?>&extension=<?= $tram['extension'] ?>"
                                title="Ver flujo"
                                target="_blank"
                                style="color: #6c757d; text-decoration: none;">
                                    <span class="material-icons" style="font-size: 22px;">device_hub</span>
                                </a>
                            <?php endif; ?> -->

                            <a href="#" 
                                class="ver-flujo-btn" 
                                data-id="<?= $tram['nFlgTipoDoc'] == 1 ? $tram['iCodTramite'] : $tram['iCodTramitePadre'] ?>" 
                                data-extension="<?= $tram['extension'] ?>" 
                                data-url="<?= $tram['nFlgTipoDoc'] == 1 ? 'bandejaFlujoMesaDePartes.php' : ($tram['extension'] == 1 ? 'bandejaFlujoraiz.php' : 'bandejaFlujo.php') ?>"
                                title="Ver flujo" 
                                style="color: #6c757d; text-decoration: none;">
                                <span class="material-icons" style="font-size: 22px;">device_hub</span>
                            </a>
                            
                            <!-- Ícono de Eliminar -->
                            <a href="#" title="Eliminar"
                            style="color: var(--danger); text-decoration: none;"
                            onclick="confirmarEliminar(<?= $tram['iCodTramite'] ?>, <?= $tram['nFlgTipoDerivo'] ?>)">
                                <span class="material-icons" style="font-size: 22px;">delete</span>
                            </a>




                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>


<!-- MODAL FLUJO -->
<link rel="stylesheet" href="modal-flujo.css">

<div id="modalFlujo">
  <div class="contenido">
    <span class="cerrar" onclick="cerrarModalFlujo()">&times;</span>
    <iframe id="iframeFlujo" src=""></iframe>
  </div>
</div>
<!-- MODAL FLUJO -->

        </tbody>
    </table>
</div>
<script>
function abrirModalFirmantes(url) {
    fetch(url)
        .then(res => res.text())
        .then(html => {
            document.getElementById('contenidoModalFirmantes').innerHTML = html;
            document.getElementById('modalFirmantes').style.display = 'block';
        });
    return false;
}

function cerrarModalFirmantes() {
    document.getElementById('modalFirmantes').style.display = 'none';
}

function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open('exportarExcelEnviados.php?' + params.toString(), '_blank');
}

// document.querySelectorAll('.ver-flujo-btn').forEach(btn => {
//     btn.addEventListener('click', function(e) {
//         e.preventDefault();
//         const id = this.dataset.id;
//         const extension = this.dataset.extension ?? 1;
//         window.open('bandejaFlujo.php?iCodTramite=' + id + '&extension=' + extension, '_blank');
//     });
// });

// Autocompletado oficinas
const inputOficina = document.getElementById('nombreOficinaInput');
const sugerencias = document.getElementById('sugerenciasOficinas');
const hiddenOficina = document.getElementById('oficinasDestino');

const oficinas = <?= json_encode($oficinas) ?>;

inputOficina.addEventListener('input', function () {
    const valor = this.value.toLowerCase();
    sugerencias.innerHTML = '';
    if (valor.trim() === '') return;

    const coincidencias = oficinas.filter(of => of.cNomOficina.toLowerCase().includes(valor));
    coincidencias.forEach(of => {
        const div = document.createElement('div');
        div.textContent = of.cNomOficina;
        div.style.padding = '5px 10px';
        div.style.cursor = 'pointer';
        div.addEventListener('click', () => {
            inputOficina.value = of.cNomOficina;
            hiddenOficina.value = of.iCodOficina;
            sugerencias.innerHTML = '';
        });
        sugerencias.appendChild(div);
    });
});

// Cierre de sugerencias al hacer clic fuera
document.addEventListener('click', function (e) {
    if (!inputOficina.contains(e.target) && !sugerencias.contains(e.target)) {
        sugerencias.innerHTML = '';
    }
});

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
</script>

<!-- Modal Firmantes -->
<div id="modalFirmantes" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%);
     background:white; padding:20px; border:1px solid #ccc; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index:9999; max-width:600px;">
    <div id="contenidoModalFirmantes">Cargando...</div>
    <div style="text-align:right; margin-top:10px;">
        <button onclick="cerrarModalFirmantes()">Cerrar</button>
    </div>
</div>

</div> <!-- cierre del .container -->
