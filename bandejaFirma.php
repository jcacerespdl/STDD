<?php
session_start();
include("head.php");
include("conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo "<p>Sesión expirada</p>";
    exit;
}

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$filtroTipoComplementario = $_GET['tipoDoc'] ?? '';
$filtroFirmado = $_GET['firmado'] ?? '';

// === Consulta principal para render ===
$sql = "SELECT 
    F.iCodFirma, F.iCodTramite, F.iCodDigital, F.posicion, F.tipoFirma, F.nFlgFirma,
    T.cAsunto, T.fFecDocumento, T.expediente, T.documentoElectronico,
    D.cDescripcion, ISNULL(D.cTipoComplementario, 0) AS cTipoComplementario
FROM Tra_M_Tramite_Firma F
JOIN Tra_M_Tramite T ON T.iCodTramite = F.iCodTramite
LEFT JOIN Tra_M_Tramite_Digitales D 
    ON D.iCodTramite = F.iCodTramite AND D.iCodDigital = F.iCodDigital
WHERE F.iCodTrabajador = ?
  AND F.nFlgEstado = 1";

$params = [$iCodTrabajador];

if ($filtroTipoComplementario === 'principal') {
    $sql .= " AND F.iCodDigital IS NULL";
} elseif (preg_match('/^1_[PQO]$/', $filtroTipoComplementario)) {
    // Pedido SIGA por posición
    $sql .= " AND ISNULL(D.cTipoComplementario, 0) = 1 AND F.posicion = ?";
    $params[] = substr($filtroTipoComplementario, 2, 1);
} elseif (in_array($filtroTipoComplementario, ['0','3','4','5','6'])) {
    // Tipos estándar de complementarios
    $sql .= " AND ISNULL(D.cTipoComplementario, 0) = ?";
    $params[] = (int)$filtroTipoComplementario;
}

if ($filtroFirmado === "0") {
    $sql .= " AND F.nFlgFirma = 0";
} elseif ($filtroFirmado === "1") {
    $sql .= " AND F.nFlgFirma = 3";
} else {
    $sql .= " AND F.nFlgFirma IN (0,3)";
}

$sql .= " ORDER BY T.fFecDocumento DESC";
$stmt = sqlsrv_query($cnx, $sql, $params);

// === Consulta de conteo por tipo ===
$sqlResumen = "
SELECT tipoDoc, COUNT(*) AS total
FROM (
  SELECT 
    CASE 
      WHEN F.iCodDigital IS NULL THEN 'PRINCIPAL'
      WHEN ISNULL(D.cTipoComplementario, 0) = 1 AND F.posicion = 'P' THEN '1_P'
      WHEN ISNULL(D.cTipoComplementario, 0) = 1 AND F.posicion = 'Q' THEN '1_Q'
      WHEN ISNULL(D.cTipoComplementario, 0) = 1 AND F.posicion = 'O' THEN '1_O'
      WHEN ISNULL(D.cTipoComplementario, 0) = 0 THEN 'SIMPLE'
      WHEN ISNULL(D.cTipoComplementario, 0) = 3 THEN '3'
      WHEN ISNULL(D.cTipoComplementario, 0) = 4 THEN '4'
      WHEN ISNULL(D.cTipoComplementario, 0) = 5 THEN '5'
      WHEN ISNULL(D.cTipoComplementario, 0) = 6 THEN '6'
      ELSE 'OTRO'
    END AS tipoDoc
  FROM Tra_M_Tramite_Firma F
  JOIN Tra_M_Tramite T ON T.iCodTramite = F.iCodTramite
  LEFT JOIN Tra_M_Tramite_Digitales D 
      ON D.iCodTramite = F.iCodTramite AND D.iCodDigital = F.iCodDigital
  WHERE F.iCodTrabajador = ? 
    AND F.nFlgFirma = 0 
    AND F.nFlgEstado = 1 
    AND T.nFlgEstado = 1
) AS sub
GROUP BY tipoDoc";
 
$resumen = [
    "PRINCIPAL" => 0,
    "SIMPLE"    => 0,
    "1_P"       => 0,
    "1_Q"       => 0,
    "1_O"       => 0,
    "3"         => 0,
    "4"         => 0,
    "5"         => 0,
    "6"         => 0
  ];
$stmtResumen = sqlsrv_query($cnx, $sqlResumen, [$iCodTrabajador]);
if ($stmtResumen) {
    while ($row = sqlsrv_fetch_array($stmtResumen, SQLSRV_FETCH_ASSOC)) {
        $key = trim($row['tipoDoc']);
    
        if (isset($resumen[$key])) {
            $resumen[$key] = $row['total'];
        }
    }
}

// echo "<pre>";
// print_r($resumen);
// echo "</pre>";

// $sqlDebug = "
// SELECT 
//   F.iCodFirma, F.iCodTramite, F.iCodDigital,
//   D.iCodDigital AS digitalExistente, D.cTipoComplementario,
//   F.posicion, F.nFlgFirma
// FROM Tra_M_Tramite_Firma F
// JOIN Tra_M_Tramite T ON T.iCodTramite = F.iCodTramite
// LEFT JOIN Tra_M_Tramite_Digitales D 
//   ON D.iCodTramite = F.iCodTramite AND D.iCodDigital = F.iCodDigital
// WHERE F.iCodTrabajador = ?
//   AND F.nFlgFirma = 0 
//   AND F.nFlgEstado = 1 
//   AND T.nFlgEstado = 1
// ORDER BY F.iCodTramite DESC";

// $stmtDebug = sqlsrv_query($cnx, $sqlDebug, [$iCodTrabajador]);
// while ($row = sqlsrv_fetch_array($stmtDebug, SQLSRV_FETCH_ASSOC)) {
//     echo "<pre>";
//     print_r($row);
//     echo "</pre>";
// }


?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="bandejas.css" rel="stylesheet">

<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
    <div class="titulo-principal">DOCUMENTOS PARA FIRMA</div>

    <!-- Formulario de búsqueda -->
    <form method="get" class="row" style="margin-bottom: 20px; gap: 20px;">
        <div style="flex: 1;">
            <label style="font-weight: bold;">Tipo de Documento</label>
            <select name="tipoDoc" class="form-control" style="min-width:250px">
                <optgroup label="DOCUMENTOS PRINCIPALES">
                    <option value="principal" <?= $filtroTipoComplementario === "principal" ? 'selected' : '' ?>>Documento Principal</option>
                </optgroup>
                <optgroup label="DOCUMENTOS COMPLEMENTARIOS">
                    <option value="0" <?= $filtroTipoComplementario === "0" ? 'selected' : '' ?>>Complementarios</option>
                </optgroup>
                <optgroup label="DOCUMENTOS COMPLEMENTARIOS de REQUERIMIENTO">

                    <?php if ($resumen["1_P"] > 0): ?>
                        <option value="1_P" <?= $filtroTipoComplementario === "1_P" ? 'selected' : '' ?>>Pedido SIGA (Solicita)</option>
                    <?php endif; ?>

                    <?php if ($resumen["1_Q"] > 0): ?>
                        <option value="1_Q" <?= $filtroTipoComplementario === "1_Q" ? 'selected' : '' ?>>Pedido SIGA (Autoriza)</option>
                    <?php endif; ?>

                    <?php if ($resumen["1_O"] > 0): ?>
                        <option value="1_O" <?= $filtroTipoComplementario === "1_O" ? 'selected' : '' ?>>Pedido SIGA (Revisa)</option>
                    <?php endif; ?>
                    <option value="5" <?= $filtroTipoComplementario === "5" ? 'selected' : '' ?>>Orden de Servicio</option>

                    <?php if (in_array($_SESSION['iCodOficinaLogin'], [112, 3])): ?>
                        <option value="3" <?= $filtroTipoComplementario === "3" ? 'selected' : '' ?>>Solicitud de Crédito Presupuestario</option>
                        <option value="6" <?= $filtroTipoComplementario === "6" ? 'selected' : '' ?>>Orden de Compra</option>
                    <?php endif; ?>

                    <?php if (in_array($_SESSION['iCodOficinaLogin'], [71, 23])): ?>
                        <option value="4" <?= $filtroTipoComplementario === "4" ? 'selected' : '' ?>>Aprobación de Crédito Presupuestario</option>
                    <?php endif; ?>
                </optgroup>

            </select>
        </div>

        <div style="flex: 1;">
            <label style="font-weight: bold;">Estado de Firma</label>
            <select name="firmado" class="form-control" style="min-width:200px">
                <option value="">Todos</option>
                <option value="0" <?= $filtroFirmado === "0" ? 'selected' : '' ?>>No Firmado</option>
                <option value="1" <?= $filtroFirmado === "1" ? 'selected' : '' ?>>Firmado</option>
            </select>
        </div>
<br>
        <div style="display:flex; align-items:end; gap: 10px;">
            <button class="btn btn-primary" type="submit">
                <span class="material-icons">search</span> Buscar
            </button>
            <a href="bandejaFirma.php" class="btn btn-secondary" style="text-decoration: none;">
                <span class="material-icons">autorenew</span> Reestablecer
            </a>
        </div>
    </form>

    <!-- Tabla de resumen de pendientes -->
    <table class="table table-bordered table-sm" style="max-width: 500px;">
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Cantidad Pendientes de Firma</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resumen['PRINCIPAL'] > 0): ?>
                <tr><td>Documento Principal</td><td><?= $resumen['PRINCIPAL'] ?></td></tr>
            <?php endif; ?>

            <?php if ($resumen['SIMPLE'] > 0): ?>
                <tr><td>Complementarios</td><td><?= $resumen['SIMPLE'] ?></td></tr>
            <?php endif; ?>

            <?php
            $pedidoSigaTotal = $resumen['1_P'] + $resumen['1_Q'] + $resumen['1_O'];
            if ($pedidoSigaTotal > 0): ?>
            <tr><td>Pedido SIGA</td><td><?= $pedidoSigaTotal ?></td></tr>
            <?php endif; ?>
            
            <?php if ($resumen['3'] > 0): ?>
                <tr><td>Solicitud de crédito</td><td><?= $resumen['3'] ?></td></tr>
            <?php endif; ?>
            <?php if ($resumen['4'] > 0): ?>
                <tr><td>Aprobación de crédito</td><td><?= $resumen['4'] ?></td></tr>
            <?php endif; ?>
            <?php if ($resumen['5'] > 0): ?>
                <tr><td>Orden de servicio</td><td><?= $resumen['5'] ?></td></tr>
            <?php endif; ?>
            <?php if ($resumen['6'] > 0): ?>
                <tr><td>Orden de compra</td><td><?= $resumen['6'] ?></td></tr>
            <?php endif; ?>
        </tbody>

    </table>
<br>


    <?php
$filtradoActivo = $filtroTipoComplementario !== ''; // solo permite firmar si hay filtro por tipo
?>

<?php if ($stmt && sqlsrv_has_rows($stmt)): ?>
    <table class="tabla-firmas">
        <thead>
            <tr>
                <th>Firma </th> <!-- NUEVO: para checkbox -->
                <th>Expediente</th>
                <th>Fecha</th>
                <th>Asunto</th>
                <th>Archivo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <?php
                    $tipo = intval($row['cTipoComplementario']);
                    $esPrincipal = $row['iCodDigital'] === null;
                    $archivo = $esPrincipal ? $row['documentoElectronico'] : $row['cDescripcion'];
                    $iCodFirma = $row['iCodFirma'];
                    $firmado = intval($row['nFlgFirma']);
                ?>
                <tr>
                    <td> 
                    <?php if ($firmado == 3): ?>Firmado

                        <?php elseif (!$filtradoActivo): ?>
                            <span style="color: #888;"> Filtre para habilitar</span>

                        <?php elseif ($esPrincipal): ?>
                            <!-- ✅ Caso 3: Documento principal -->
                            <button class="btn btn-primary firmarVBPrincipal" data-firma="<?= $iCodFirma ?>" data-archivo="<?= $archivo ?>">
                                <i class="material-icons">edit_document</i> Firmar Principal
                            </button>

                        <?php elseif ($tipo > 0): ?>
                            <!-- ✅ Caso 1: Masivo -->
                            <input type="checkbox" class="chkFirma" value="<?= $archivo ?>" data-icodfirma="<?= $iCodFirma ?>">

                        <?php else: ?>
                            <!-- ✅ Caso 2: Individual sin tipo -->
                            <button class="btn btn-primary firmarBtn" data-firma="<?= $iCodFirma ?>" data-archivo="<?= $archivo ?>">
                                <i class="material-icons">edit_document</i> Firmar
                            </button>
                        <?php endif; ?>



                    </td>
                    <td><?= htmlspecialchars($row['expediente']) ?></td>
                    <td><?= $row['fFecDocumento'] ? $row['fFecDocumento']->format("d/m/Y H:i") : '' ?></td>
                    <td><?= htmlspecialchars($row['cAsunto']) ?></td>
                    <td>
                        <a href="<?= $esPrincipal ? 'cDocumentosFirmados/' : 'cAlmacenArchivos/' ?><?= rawurlencode($archivo) ?>" target="_blank">
                            <span class="material-icons">article</span> <?= htmlspecialchars($archivo) ?>
                        </a>
                    </td>
                    <td>
                        <a href="detallesFirmantes2.php?iCodTramite=<?= $row['iCodTramite'] ?>&iCodDigital=<?= $row['iCodDigital'] ?? 'null' ?>"
                                title="Ver firmantes"
                                style="text-decoration: none;"
                                onclick="return abrirModalFirmantes(this.href)">
                                <span class="material-icons" style="font-size: 18px; color: #555;">group</span>
                        </a>

                       
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <p id="contadorSeleccionados">(0 seleccionados)</p>
<br>
<?php
$esComplementarioRequerimiento = preg_match('/^1_[PQO]$/', $filtroTipoComplementario) || in_array($filtroTipoComplementario, ['3','4','5','6']);
if ($filtradoActivo && $esComplementarioRequerimiento):
?>
    <button id="btnFirmarSeleccionados" class="btn btn-primary">Firmar seleccionados</button>
<?php endif; ?>
<?php else: ?>
    <p>No hay documentos que coincidan con el filtro actual.</p>
<?php endif; ?>
</div>
<!-- Modal para firmantes -->
<div id="modalFirmantes" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%);
     background:white; padding:20px; border:1px solid #ccc; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index:9999; max-width:600px;">
    <div id="contenidoModalFirmantes">Cargando...</div>
    <div style="text-align:right; margin-top:10px;">
        <button onclick="cerrarModalFirmantes()">Cerrar</button>
    </div>
</div>
<div id="addComponent" style="display: none;"></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="./scripts/jquery.blockUI.js"></script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>

<script>
// 👉 Cuenta los seleccionados para firma masiva
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

function contarSeleccionados() {
    const total = document.querySelectorAll('.chkFirma:checked').length;
    document.getElementById("contadorSeleccionados").innerText = `(${total} seleccionados)`;
}

// ✅ Caso 1: Firmar varios documentos (complementarios especiales)
function firmarSeleccionadosLoteComples() {
    const seleccionados = Array.from(document.querySelectorAll(".chkFirma:checked"));
    if (seleccionados.length === 0) return alert("Seleccione al menos un documento.");

    const firmantes = seleccionados.map(chk => ({
        documento: chk.value,
        iCodFirma: chk.dataset.icodfirma
    }));

    const formData = new FormData();
    seleccionados.forEach(chk => formData.append("documentos[]", chk.value));
    formData.append("firmantesJson", JSON.stringify(firmantes));

    fetch("comprimirMasivoVBRevision.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
    if (data.success) {
        const nombreZip = data.archivo.replace('.7z', '');
        const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsMasivoRevision.php?iCodTramite=${nombreZip}`;
        const paramPrev = {
            param_url: param_url,
            param_token: "123456",
            document_extension: "pdf"
        };
        const param = btoa(JSON.stringify(paramPrev));
        startSignature("48596", param);
    } else {
        alert("Error al comprimir: " + (data.error || "Desconocido"));
    }
});
}

// ✅ Caso 2: Firmar complementario simple (individual)
document.querySelectorAll(".firmarBtn").forEach(btn => {
    btn.addEventListener("click", function () {
        const iCodFirma = this.dataset.firma;
        const archivo = this.dataset.archivo;


        const formData = new URLSearchParams();
        formData.append("documento", archivo);
        formData.append("iCodFirma", iCodFirma);

        fetch("comprimirVB.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
    if (data.success) {
        const nombreZip = data.archivo.replace('.7z', '');
        const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsVB.php?nombreZip=${nombreZip}&iCodFirma=${iCodFirma}`;
        const paramPrev = {
            param_url: param_url,
            param_token: "123456",
            document_extension: "pdf"
        };
        const param = btoa(JSON.stringify(paramPrev));
        const port = "48596";

                startSignature(port, param);
    } else {
        alert("Error al generar ZIP: " + (data.error || "Desconocido"));
    }
})
        .catch(err => {
            console.error("Error en firma:", err);
            alert("Error en firma.");
        });
    });
});


// ✅ Caso 3: Firmar documento principal (visto bueno)
document.querySelectorAll(".firmarVBPrincipal").forEach(btn => {
    btn.addEventListener("click", function () {
        const iCodFirma = this.dataset.firma;
        const archivo = this.dataset.archivo;

        const formData = new URLSearchParams();
        formData.append("documento", archivo);
        formData.append("iCodFirma", iCodFirma);

        fetch("comprimirVBprincipal.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
    if (data.success) {
        const nombreZip = data.archivo.replace('.7z', '');
        const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsVBprincipal.php?nombreZip=${nombreZip}&iCodFirma=${iCodFirma}`;
        const paramPrev = {
            param_url: param_url,
            param_token: "123456",
            document_extension: "pdf"
        };
        const param = btoa(JSON.stringify(paramPrev));
        startSignature("48596", param);
    } else {
        alert("Error al comprimir principal: " + (data.error || "Desconocido"));
    }
});

});
});
 

// Inicializa eventos
const btnFirmar = document.getElementById("btnFirmarSeleccionados");
if (btnFirmar) btnFirmar.addEventListener("click", firmarSeleccionadosLoteComples);

document.querySelectorAll(".chkFirma").forEach(cb => cb.addEventListener("change", contarSeleccionados));

// detalles firmantes

function abrirModalFirmantes(url) {
    fetch(url)
        .then(res => res.text())
        .then(html => {
            document.getElementById('contenidoModalFirmantes').innerHTML = html;
            document.getElementById('modalFirmantes').style.display = 'block';
        });
    return false; // evitar redirección
}

function cerrarModalFirmantes() {
    document.getElementById('modalFirmantes').style.display = 'none';
}
</script>
