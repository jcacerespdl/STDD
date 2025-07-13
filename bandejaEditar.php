<?php
include("head.php");
include_once("conexion/conexion.php");
session_start();
global $cnx;

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    header("Location: index.php?error=No tiene una sesión activa.");
    exit();
}

$iCodTramite = $_GET['iCodTramite'] ?? null;
if (!$iCodTramite) {
    die("Error: Código de trámite no proporcionado.");
}

// Obtener datos del trámite
$sqlTramite = "SELECT iCodOficinaRegistro, cCodTipoDoc, cCodificacion, cAsunto, cObservaciones, descripcion 
               FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$tramite = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$tramite) {
    die("Trámite no encontrado.");
}

// Tipos de documentos internos
$sqlTipos = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc";
$resultTiposDoc = sqlsrv_query($cnx, $sqlTipos);

// Obtener correlativo ya generado (no editable)
$anio = date('Y');
$sqlCorr = "SELECT co.nCorrelativo, o.cSiglaOficina
            FROM Tra_M_Correlativo_Oficina co
            JOIN Tra_M_Oficinas o ON co.iCodOficina = o.iCodOficina
            WHERE co.cCodTipoDoc = ? AND co.iCodOficina = ? AND co.nNumAno = ?";
$paramsCorr = [$tramite['cCodTipoDoc'], $tramite['iCodOficinaRegistro'], $anio];
$stmtCorr = sqlsrv_query($cnx, $sqlCorr, $paramsCorr);
$rowCorr = sqlsrv_fetch_array($stmtCorr, SQLSRV_FETCH_ASSOC);

$correlativo = $rowCorr
    ? str_pad($rowCorr['nCorrelativo'], 5, "0", STR_PAD_LEFT) . '-' . $anio . '/' . $rowCorr['cSiglaOficina']
    : $tramite['cCodificacion'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Trámite</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
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
            border-radius: 5px;
        }
        .input-container label {
            position: absolute;
            top: 5px;
            left: 12px;
            font-size: 12px;
            color: #555;
            background: white;
            padding: 0 4px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-card {
            max-width: 1300px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="container" style="margin-top: 120px;">
    <form id="formEditarCabecera" method="POST" action="guardarCambiosEditar.php">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">

        <div class="form-card">
            <h2>Encabezado del Documento</h2>

            <div class="form-row">
                <!-- Tipo de documento -->
                <div class="input-container select-flotante">
                    <select name="tipoDocumento" id="tipoDocumento" required>
                        <option value="" disabled hidden></option>
                        <?php while ($tipo = sqlsrv_fetch_array($resultTiposDoc, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?= $tipo['cCodTipoDoc'] ?>" 
                                <?= $tipo['cCodTipoDoc'] === $tramite['cCodTipoDoc'] ? 'selected' : '' ?>>
                                <?= $tipo['cDescTipoDoc'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <label for="tipoDocumento">Tipo de Documento</label>
                </div>

                <!-- Correlativo (solo lectura) -->
                <div class="input-container">
                    <input type="text" name="correlativo" id="correlativo" value="<?= $correlativo ?>" readonly>
                    <label for="correlativo">Correlativo</label>
                </div>
            </div>

            <div class="form-row">
                <!-- Asunto -->
                <div class="input-container">
                    <input type="text" name="asunto" id="asunto" value="<?= htmlspecialchars($tramite['cAsunto']) ?>" required>
                    <label for="asunto">Asunto</label>
                </div>

                <!-- Observaciones -->
                <div class="input-container">
                    <input type="text" name="observaciones" id="observaciones" value="<?= htmlspecialchars($tramite['cObservaciones']) ?>">
                    <label for="observaciones">Observaciones</label>
                </div>
            </div>

            <div class="form-row">
                <button type="submit" class="btn-primary">
                    <i class="material-icons">save</i> Guardar Cambios Generales
                </button>
            </div>
        </div>
    </form>
</div>
<?php
$sigaItems = [];
$sqlSiga = "SELECT pedido_siga, codigo_item, cantidad FROM Tra_M_Tramite_SIGA_Pedido WHERE iCodTramite = ?";
$resSiga = sqlsrv_query($cnx, $sqlSiga, [$iCodTramite]);

if ($resSiga) {
    while ($row = sqlsrv_fetch_array($resSiga, SQLSRV_FETCH_ASSOC)) {
        $pedido = $row['pedido_siga'];
        $codigo = $row['codigo_item'];
        $cantidad = $row['cantidad'];

        $infoCatalogo = null;
        $sqlCat = "SELECT NOMBRE_ITEM, TIPO_BIEN FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?";
        $resCat = sqlsrv_query($sigaConn, $sqlCat, [$codigo]);
        if ($resCat) {
            $infoCatalogo = sqlsrv_fetch_array($resCat, SQLSRV_FETCH_ASSOC);
        }

        $sigaItems[] = [
            'pedido_siga' => $pedido,
            'codigo_item' => $codigo,
            'nombre_item' => $infoCatalogo['NOMBRE_ITEM'] ?? '(no encontrado)',
            'tipo_bien' => $infoCatalogo['TIPO_BIEN'] ?? 'N.A.',
            'cantidad' => $cantidad
        ];
    }
}
?>

<!-- ÍTEMS SIGA - SOLO SI TIPO 109 -->
<?php if ($tramite['cCodTipoDoc'] === '109'): ?>
<div class="form-card" style="margin-top: 35px;" id="grupoRequerimiento">
    <h2>Ítems SIGA Asociados</h2>

    <?php if (!empty($sigaItems)): ?>
        <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
            <thead style="background: #f5f5f5;">
                <tr>
                    <th>Pedido SIGA</th>
                    <th>Código Item</th>
                    <th>Nombre Item</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody id="tbodySiga">
                <?php foreach ($sigaItems as $item): ?>
                    <tr data-codigo="<?= $item['codigo_item'] ?>" data-pedido="<?= $item['pedido_siga'] ?>">
                        <td><?= $item['pedido_siga'] ?? 'N.A.' ?></td>
                        <td><?= $item['codigo_item'] ?></td>
                        <td><?= htmlspecialchars($item['nombre_item']) ?></td>
                        <td><?= $item['tipo_bien'] === 'B' ? 'Bien' : ($item['tipo_bien'] === 'S' ? 'Servicio' : 'N.A.') ?></td>
                        <td>
                            <input type="number" name="cantidad_item[]" min="1" value="<?= $item['cantidad'] ?>"
                                   data-codigo="<?= $item['codigo_item'] ?>" style="width: 60px;">
                        </td>
                        <td>
                            <button type="button" class="btn-secondary" onclick="quitarItemSiga(this)">Quitar</button>
                            <input type="hidden" name="itemsSigaGuardados[]" value="<?= $item['pedido_siga'] . '|' . $item['codigo_item'] ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay ítems SIGA registrados.</p>
    <?php endif; ?>
</div>

<script>
function quitarItemSiga(btn) {
    const fila = btn.closest('tr');
    fila.remove();
}
</script>
<?php endif; ?>
<?php
// Obtener oficinas
$sqlOficinas = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas";
$resOficinas = sqlsrv_query($cnx, $sqlOficinas);
$oficinas = [];
while ($row = sqlsrv_fetch_array($resOficinas, SQLSRV_FETCH_ASSOC)) {
    $oficinas[] = $row;
}

// Obtener jefes
$sqlJefes = "SELECT t.iCodOficina, t.iCodTrabajador, tr.cNombresTrabajador, tr.cApellidosTrabajador 
             FROM Tra_M_Perfil_Ususario t 
             JOIN Tra_M_Trabajadores tr ON t.iCodTrabajador = tr.iCodTrabajador
             WHERE t.iCodPerfil = 3";
$resJefes = sqlsrv_query($cnx, $sqlJefes);
$jefes = [];
while ($row = sqlsrv_fetch_array($resJefes, SQLSRV_FETCH_ASSOC)) {
    $jefes[$row['iCodOficina']] = [
        "name" => $row['cNombresTrabajador'] . " " . $row['cApellidosTrabajador'],
        "id" => $row['iCodTrabajador']
    ];
}

// Obtener indicaciones
$sqlIndic = "SELECT iCodIndicacion, cIndicacion FROM Tra_M_Indicaciones";
$resIndic = sqlsrv_query($cnx, $sqlIndic);
$indicaciones = [];
while ($row = sqlsrv_fetch_array($resIndic, SQLSRV_FETCH_ASSOC)) {
    $indicaciones[] = $row;
}

// Obtener destinos ya existentes
$sqlDestinos = "SELECT tm.iCodOficinaDerivar, tm.iCodTrabajadorDerivar, tm.iCodIndicacionDerivar, tm.cPrioridadDerivar, tm.cFlgTipoMovimiento,
                       o.cNomOficina, t.cNombresTrabajador, t.cApellidosTrabajador, i.cIndicacion
                FROM Tra_M_Tramite_Movimientos tm
                LEFT JOIN Tra_M_Oficinas o ON tm.iCodOficinaDerivar = o.iCodOficina
                LEFT JOIN Tra_M_Trabajadores t ON tm.iCodTrabajadorDerivar = t.iCodTrabajador
                LEFT JOIN Tra_M_Indicaciones i ON tm.iCodIndicacionDerivar = i.iCodIndicacion
                WHERE tm.iCodTramite = ?";
$resDest = sqlsrv_query($cnx, $sqlDestinos, [$iCodTramite]);
$destinosExistentes = [];
while ($row = sqlsrv_fetch_array($resDest, SQLSRV_FETCH_ASSOC)) {
    $destinosExistentes[] = $row;
}
?>

<!-- BLOQUE DESTINOS -->
<div class="form-card" style="margin-top: 40px;">
    <h2>Oficinas de Destino</h2>

    <!-- Fila para agregar -->
    <div class="form-row">
        <div class="input-container oficina-ancha" style="position: relative;">
            <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off" required>
            <label for="nombreOficinaInput">Nombre de Oficina</label>
            <input type="hidden" id="oficinasDestino">
            <div id="sugerenciasOficinas" class="sugerencias-dropdown"></div>
        </div>

        <div class="input-container">
            <input type="text" id="jefeOficina" name="jefeOficina" placeholder=" " readonly>
            <label for="jefeOficina">Jefe</label>
        </div>

        <div class="input-container select-flotante">
            <select id="indicacion" name="indicacion" required>
                <option value="" disabled selected hidden></option>
                <?php foreach($indicaciones as $ind): ?>
                    <option value="<?= $ind['iCodIndicacion'] ?>"><?= $ind['cIndicacion'] ?></option>
                <?php endforeach; ?>
            </select>
            <label for="indicacion">Indicación</label>
        </div>

        <div class="input-container select-flotante prioridad-reducida">
            <select id="prioridad" name="prioridad" required>
                <option value="1">Baja</option>
                <option value="2" selected>Media</option>
                <option value="3">Alta</option>
            </select>
            <label for="prioridad">Prioridad</label>
        </div>

        <label style="margin-left: 10px; align-self: center;">
            <input type="checkbox" id="copiaCheck"> Copia
        </label>

        <button type="button" class="btn-primary" onclick="agregarDestino()">Agregar</button>
    </div>

    <!-- Tabla destinos -->
    <div class="form-row" id="tablaDestinos" style="margin-top: 20px;">
        <div class="input-container" style="width: 100%; overflow-x: auto;">
            <table id="tablaDestinosExistentes" style="width: 100%; font-size: 14px; border-collapse: collapse;">
                <thead style="background: #f5f5f5;">
                    <tr>
                        <th>Oficina</th>
                        <th>Jefe</th>
                        <th>Indicación</th>
                        <th>Prioridad</th>
                        <th>Copia</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($destinosExistentes as $d): ?>
                    <tr data-id="<?= $d['iCodOficinaDerivar'] ?>">
                        <td><?= $d['cNomOficina'] ?></td>
                        <td><?= $d['cNombresTrabajador'] . ' ' . $d['cApellidosTrabajador'] ?></td>
                        <td><?= $d['cIndicacion'] ?></td>
                        <td><?= $d['cPrioridadDerivar'] ?></td>
                        <td><?= $d['cFlgTipoMovimiento'] == '4' ? 'Sí' : 'No' ?></td>
                        <td>
                            <button type="button" class="btn-secondary" onclick="eliminarDestino(this, '<?= $d['iCodOficinaDerivar'] ?>')">Eliminar</button>
                            <input type="hidden" name="destinos[]" value="<?= $d['iCodOficinaDerivar'] ?>_<?= $d['iCodTrabajadorDerivar'] ?>_<?= $d['iCodIndicacionDerivar'] ?>_<?= $d['cPrioridadDerivar'] ?>_<?= $d['cFlgTipoMovimiento'] ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const oficinas = <?= json_encode($oficinas, JSON_UNESCAPED_UNICODE) ?>;
const jefes = <?= json_encode($jefes, JSON_UNESCAPED_UNICODE) ?>;
const oficinasAgregadas = new Set();

function mostrarSugerenciasOficinas(filtro = "") {
    const contenedor = $('#sugerenciasOficinas');
    contenedor.empty();

    const resultados = oficinas.filter(ofi => 
        ofi.cNomOficina.toLowerCase().includes(filtro.toLowerCase())
    );

    resultados.forEach(ofi => {
        const item = $('<div class="sugerencia-item">').text(ofi.cNomOficina);
        item.on('click', function () {
            $('#nombreOficinaInput').val(ofi.cNomOficina);
            $('#oficinasDestino').val(ofi.iCodOficina);

            const jefe = jefes[ofi.iCodOficina];
            $('#jefeOficina').val(jefe ? jefe.name : '');
            $('#jefeOficina').data('jefeid', jefe ? jefe.id : '');

            contenedor.hide();
        });
        contenedor.append(item);
    });

    contenedor.show();
}

$('#nombreOficinaInput').on('focus', function () {
    if ($(this).val().trim() === '') mostrarSugerenciasOficinas('');
});

$('#nombreOficinaInput').on('input', function () {
    const texto = $(this).val().trim();
    mostrarSugerenciasOficinas(texto);
});

$(document).on('click', function (e) {
    if (!$(e.target).closest('#nombreOficinaInput, #sugerenciasOficinas').length) {
        $('#sugerenciasOficinas').hide();
    }
});

function agregarDestino() {
    const idOfi = $('#oficinasDestino').val();
    const nomOfi = $('#nombreOficinaInput').val();
    const jefe = $('#jefeOficina').val();
    const jefeId = $('#jefeOficina').data('jefeid');
    const indId = $('#indicacion').val();
    const indText = $('#indicacion option:selected').text();
    const prio = $('#prioridad option:selected').text();
    const copia = $('#copiaCheck').is(':checked') ? 1 : 0;

    if (!idOfi || !jefeId || !indId) {
        alert("Complete todos los campos");
        return;
    }

    if (oficinasAgregadas.has(idOfi)) {
        alert("Oficina ya fue agregada");
        return;
    }

    oficinasAgregadas.add(idOfi);

    const fila = `
        <tr data-id="${idOfi}">
            <td>${nomOfi}</td>
            <td>${jefe}</td>
            <td>${indText}</td>
            <td>${prio}</td>
            <td>${copia ? 'Sí' : 'No'}</td>
            <td>
                <button type="button" class="btn-secondary" onclick="eliminarDestino(this, '${idOfi}')">Eliminar</button>
                <input type="hidden" name="destinos[]" value="${idOfi}_${jefeId}_${indId}_${prio}_${copia}">
            </td>
        </tr>
    `;
    $('#tablaDestinosExistentes tbody').append(fila);

    $('#nombreOficinaInput').val('');
    $('#oficinasDestino').val('');
    $('#jefeOficina').val('').removeData('jefeid');
    $('#indicacion').val('');
    $('#prioridad').val('2');
    $('#copiaCheck').prop('checked', false);
}

function eliminarDestino(btn, id) {
    oficinasAgregadas.delete(id);
    $(btn).closest('tr').remove();
}
</script>
<!-- DOCUMENTO PRINCIPAL -->
<div class="form-card" style="margin-top: 40px;">
    <h2>Documento Principal</h2>

    <!-- Selección de modo -->
    <div class="form-row">
        <label><input type="radio" name="modoDocumento" value="generar" checked onchange="cambiarModoDocumento()"> Generar Documento</label>
        <label style="margin-left: 30px;"><input type="radio" name="modoDocumento" value="adjuntar" onchange="cambiarModoDocumento()"> Adjuntar Documento</label>
    </div>

    <!-- Redacción -->
    <form id="formularioEditor" method="POST">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <div id="contenedorEditor">
            <textarea id="descripcion" name="descripcion"><?= htmlspecialchars($tramite['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="form-row" style="margin-top: 15px; display: flex; gap: 10px;">
            <button type="submit" id="guardarBtn" class="btn-primary">
                <i class="material-icons">save</i> Guardar
            </button>

            <a id="descargarBtn"
               class="btn-primary"
               target="_blank"
               style="text-decoration: none; background-color: #ccc; color: #666; cursor: not-allowed; pointer-events: none;">
                <i class="material-icons">download</i> Descargar
            </a>

            <button type="button" onclick="abrirPopupFirmantesPrincipal(<?= $iCodTramite ?>)" class="btn-primary">
                <i class="material-icons">group_add</i> Visar Documento
            </button>

            <?php if ($_SESSION['iCodPerfilLogin'] == 3): ?>
                <button type="button" id="btnFirmarPrincipal" class="btn-primary" <?= $tramite['documentoElectronico'] ? '' : 'disabled' ?>>
                    <i class="material-icons">edit_document</i> Firmar
                </button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Adjunto PDF -->
    <div id="contenedorAdjunto" style="display: none; margin-top: 25px;">
        <form id="formAdjuntoPrincipal" enctype="multipart/form-data">
            <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
            <input type="file" name="archivoPrincipal" accept="application/pdf" required>
            <small>Solo PDF de hasta 20 MB.</small><br>
            <button type="submit" class="btn-primary" style="margin-top: 10px;">
                <i class="material-icons">upload</i> Subir Documento
            </button>
        </form>
    </div>
</div>

<script src="js/tinymce/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#descripcion',
    height: 500,
    menubar: false,
    plugins: 'advlist autolink lists link image charmap print preview anchor textcolor',
    toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
    language: 'es',
});

function cambiarModoDocumento() {
    const modo = document.querySelector('input[name="modoDocumento"]:checked').value;
    document.getElementById('contenedorEditor').style.display = (modo === 'generar') ? 'block' : 'none';
    document.getElementById('contenedorAdjunto').style.display = (modo === 'adjuntar') ? 'block' : 'none';
    document.getElementById('guardarBtn').disabled = (modo === 'adjuntar');
}

// Guardar y generar PDF
document.getElementById('formularioEditor').addEventListener('submit', function(e) {
    e.preventDefault();
    tinymce.triggerSave();
    const formData = new FormData(this);
    const guardarBtn = document.getElementById('guardarBtn');
    guardarBtn.disabled = true;
    guardarBtn.innerHTML = 'Guardando...';

    fetch('actualizarDescripcion.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            return fetch('exportarTramitePDF.php', { method: 'POST', body: formData });
        } else {
            throw new Error(res.message);
        }
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            const link = document.getElementById('descargarBtn');
            link.href = `cDocumentosFirmados/${res.filename}`;
            link.style.backgroundColor = '';
            link.style.color = '';
            link.style.pointerEvents = '';
            link.style.cursor = '';

            const btnFirmar = document.getElementById('btnFirmarPrincipal');
            if (btnFirmar && btnFirmar.disabled) btnFirmar.disabled = false;

            alert("✅ Documento generado correctamente.");
        } else {
            throw new Error(res.message || "No se pudo generar el PDF.");
        }
    })
    .catch(err => alert("❌ " + err.message))
    .finally(() => {
        guardarBtn.disabled = false;
        guardarBtn.innerHTML = '<i class="material-icons">save</i> Guardar';
    });
});
</script>
<!-- COMPLEMENTARIOS -->
<div class="form-card" style="margin-top: 40px;">
    <h2>Documentos Complementarios</h2>

    <?php
    $complementarios = [];
    $sqlComp = "SELECT iCodDigital, cDescripcion, pedido_siga, cTipoComplementario
                FROM Tra_M_Tramite_Digitales
                WHERE iCodTramite = ?";
    $resComp = sqlsrv_query($cnx, $sqlComp, [$iCodTramite]);
    while ($row = sqlsrv_fetch_array($resComp, SQLSRV_FETCH_ASSOC)) {
        $complementarios[] = $row;
    }
    ?>

    <?php if (!empty($complementarios)): ?>
    <table style="width: 100%; font-size: 14px;">
        <thead style="background: #f5f5f5;">
            <tr>
                <th>Archivo</th>
                <th>Pedido SIGA</th>
                <th>Tipo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($complementarios as $doc): ?>
            <tr>
                <td>
                    <a href="cAlmacenArchivos/<?= urlencode($doc['cDescripcion']) ?>" target="_blank">
                        <i class="material-icons">picture_as_pdf</i> <?= htmlspecialchars($doc['cDescripcion']) ?>
                    </a>
                </td>
                <td><?= $doc['pedido_siga'] ?? 'N.A.' ?></td>
                <td>
                    <?php
                    $mapa = [1 => 'Pedido SIGA', 2 => 'TDR o ETT', 3 => 'Solicitud Crédito', 4 => 'Aprobación Crédito', 5 => 'Orden de Servicio', 0 => 'Ninguno', null => 'Ninguno'];
                    echo $mapa[$doc['cTipoComplementario']] ?? 'Ninguno';
                    ?>
                </td>
                <td>
                    <a href="eliminarComplementario.php?iCodTramite=<?= $iCodTramite ?>&archivo=<?= urlencode($doc['cDescripcion']) ?>"
                       onclick="return confirm('¿Eliminar archivo?')" style="color:red;">
                        <i class="material-icons">delete</i>
                    </a>

                    <a href="#" onclick="abrirPopupFirmantes(<?= $iCodTramite ?>, <?= $doc['iCodDigital'] ?>)" title="Visar">
                        <i class="material-icons">person_add</i>
                    </a>

                    <a href="#" onclick="abrirTipoComplementario(<?= $iCodTramite ?>, <?= $doc['iCodDigital'] ?>)" title="Tipo">
                        <i class="material-icons">assignment</i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No hay documentos complementarios.</p>
    <?php endif; ?>

    <!-- Subir nuevos -->
    <form id="formSubirComplementarios" action="subirComplementarioMASIVO.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="file" id="inputArchivos" name="archivos[]" multiple accept="application/pdf">
        <button type="submit" class="btn-primary" id="btnSubirComplementarios" style="margin-top: 10px;" disabled>
            <i class="material-icons">upload</i> Subir Complementarios
        </button>
    </form>

    <!-- Enviar documento -->
    <div class="form-row" style="margin-top: 25px;">
        <button type="button" id="btnEnviar" class="btn-primary">
            <i class="material-icons">send</i> Generar Trámite
        </button>
    </div>
</div>

<script>
document.getElementById('inputArchivos').addEventListener('change', function () {
    document.getElementById('btnSubirComplementarios').disabled = !this.files.length;
});

document.getElementById('btnEnviar').addEventListener('click', async function () {
    if (!confirm("¿Desea enviar el documento? No podrá editar luego.")) return;
    try {
        const res = await fetch('enviar_Tramite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ iCodTramite: <?= $iCodTramite ?> })
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert("Documento enviado correctamente.");
            window.location.href = 'bandejaEnviados.php';
        } else {
            alert("Error: " + data.message);
        }
    } catch (err) {
        alert("Error en el envío.");
    }
});

function abrirPopupFirmantes(iCodTramite, iCodDigital) {
    const url = `registroTrabajadoresFirmaComplementario.php?iCodTramite=${iCodTramite}&iCodDigital=${iCodDigital}`;
    window.open(url, 'Firmantes', 'width=1200,height=600,resizable=yes,scrollbars=yes');
}

function abrirTipoComplementario(iCodTramite, iCodDigital) {
    const url = `registroEspecialComplementario.php?iCodTramite=${iCodTramite}&iCodDigital=${iCodDigital}`;
    window.open(url, 'TipoComplementario', 'width=800,height=500,resizable=yes,scrollbars=yes');
}
</script>

<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>
</body>
</html>
