<?php
session_start();
include("head.php");
include("conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo "<p>Sesión expirada</p>";
    exit;
}

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];

$filtroEstado = $_GET['estado'] ?? 'pendientes';
switch ($filtroEstado) {
    case 'firmados':
        $condicionFirma = "F.nFlgFirma = 3";
        break;
    case 'todos':
        $condicionFirma = "F.nFlgFirma IN (0,3)";
        break;
    default:
        $condicionFirma = "F.nFlgFirma = 0";
        break;
}

$sql = "SELECT 
    F.iCodFirma, F.iCodTramite, F.iCodDigital, F.posicion, F.tipoFirma, F.nFlgFirma,
    T.cAsunto, T.expediente, T.fFecDocumento, T.documentoElectronico,
    D.cDescripcion, D.cTipoComplementario
FROM Tra_M_Tramite_Firma F
JOIN Tra_M_Tramite T ON T.iCodTramite = F.iCodTramite
LEFT JOIN Tra_M_Tramite_Digitales D 
    ON D.iCodTramite = F.iCodTramite AND D.iCodDigital = F.iCodDigital
WHERE F.iCodTrabajador = ?
  AND $condicionFirma
  AND F.iCodDigital IS NOT NULL
  AND ISNULL(D.cTipoComplementario, 0) = 0
ORDER BY T.fFecDocumento DESC";

$stmt = sqlsrv_query($cnx, $sql, [$iCodTrabajador]);
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
    .card-criterios {
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #fdfdfd;
}

.card-criterios .card-header {
    background-color: transparent;
    color: var(--primary, #005a86);
    font-size: 16px;
    font-weight: bold;
    padding: 8px 15px;
    border-bottom: none;
}

.card-criterios .card-body {
    padding: 15px;
}

.custom-select {
    border: 1px solid #ccc;
    padding: 6px 10px;
    border-radius: 5px;
    background-color: white;
    font-size: 14px;
    min-width: 220px;
}
</style>

<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
    <div class="titulo-principal">DOCUMENTOS COMPLEMENTARIOS PARA FIRMA</div>

    <div class="card card-criterios mb-4">
    <div class="card-header font-weight-bold">CRITERIOS DE BÚSQUEDA</div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <label for="estado" class="mr-2">Estado de Firma:</label>
            <select name="estado" id="estado" onchange="this.form.submit()" class="custom-select">
                <option value="pendientes" <?= $filtroEstado == 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                <option value="firmados" <?= $filtroEstado == 'firmados' ? 'selected' : '' ?>>Firmados</option>
                <option value="todos" <?= $filtroEstado == 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </form>
    </div>
</div>


    <div class="card">
        <?php if ($stmt && sqlsrv_has_rows($stmt)): ?>
            <table class="tabla-firmas">
                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Fecha</th>
                        <th>Asunto</th>
                        <th>Archivo</th>
                        <th>Tipo Firma</th>
                        <th>Firmar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <?php
                            $archivo = $row['cDescripcion'];
                            $tipoFirmaTexto = $row['tipoFirma'] == 1 ? 'Firma Principal' : 'Visto Bueno';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['expediente']) ?></td>
                            <td><?= $row['fFecDocumento'] ? $row['fFecDocumento']->format("d/m/Y H:i") : '' ?></td>
                            <td><?= htmlspecialchars($row['cAsunto']) ?></td>
                            <td>
                                <a href="cAlmacenArchivos/<?= rawurlencode($archivo) ?>"
                                   target="_blank" class="chip-adjunto" title="<?= $archivo ?>">
                                    <span class="material-icons chip-icon chip-doc">article</span>
                                    <span class="chip-text"><?= $archivo ?></span>
                                </a>
                            </td>
                            <td><?= $tipoFirmaTexto ?></td>
                            <td>
                            <?php if ($row['nFlgFirma'] == 0): ?>
                                <button class="btn btn-primary firmarBtn"
                                        data-firma="<?= $row['iCodFirma'] ?>"
                                        data-archivo="<?= $archivo ?>">
                                    <i class="material-icons">edit_document</i> Firmar
                                </button>
                                <?php else: ?>
                                    ✅ Firmado
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay documentos complementarios sin tipo pendientes de firma.</p>
        <?php endif; ?>
    </div>
</div>

<div id="addComponent" style="display: none;"></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="./scripts/jquery.blockUI.js"></script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>

<script>
var jqFirmaPeru = jQuery.noConflict(true);
function signatureInit() {
    jqFirmaPeru.blockUI({ message: '<h1>Iniciando firma...</h1>' });
}
function signatureOk() {
    alert("✅ Documento firmado correctamente");
    location.reload();
}
function signatureCancel() {
    alert("❌ Firma cancelada.");
}

document.querySelectorAll(".firmarBtn").forEach(btn => {
    btn.addEventListener("click", function () {
        const iCodFirma = this.dataset.firma;
        const archivo = this.dataset.archivo;

        if (!archivo) {
            alert("El documento aún no ha sido generado.");
            return;
        }

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
                console.log("✅ ZIP generado:", data.archivo);
                const nombreZip = data.archivo.replace('.7z', '');
                const param_url = `https://tramite.heves.gob.pe/sgd/getFpParamsVB.php?nombreZip=${nombreZip}&iCodFirma=${iCodFirma}`;

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
</script>
