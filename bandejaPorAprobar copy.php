<?php
session_start();
include("head.php");
include("conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    echo "<p>Sesión expirada</p>";
    exit;
}

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'];
$iCodOficina    = $_SESSION['iCodOficinaLogin'];

$filtroFirma = $_GET['estado'] ?? '0'; // Por defecto: No firmados
$isFirmado  = ($filtroFirma === '1');
$titulo = $filtroFirma === '1' ? "DOCUMENTOS PRINCIPALES FIRMADOS" : "DOCUMENTOS PRINCIPALES POR APROBAR";

// Armar consulta según filtro
$sql = "SELECT t.iCodTramite, t.cCodificacion, t.cAsunto, t.fFecDocumento, t.documentoElectronico
        FROM Tra_M_Tramite t
        WHERE t.iCodOficinaRegistro = ?
        AND " . ($isFirmado ? "t.nFlgFirma = 1" : "(t.nFlgFirma = 0 OR t.nFlgFirma IS NULL)") . "
         AND t.nFlgEstado = 1
          AND t.documentoElectronico IS NOT NULL
        ORDER BY t.fFecDocumento DESC";

$params = [$iCodOficina, (int)$filtroFirma];
$stmt = sqlsrv_query($cnx, $sql, $params);
?>
<style>
    .titulo-principal {
  color: var(--primary, #005a86);
  font-size: 22px;
  font-weight: bold;
  margin-top: 0;
  margin-bottom: 20px;
}
</style>
<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
    <h2 class="titulo-principal"><?= htmlspecialchars($titulo) ?></h2>

    <form method="get" class="mb-4">
        <label for="estado">Filtrar por estado de firma:</label>
        <select name="estado" id="estado" onchange="this.form.submit()" class="form-select" style="width: 200px; display: inline-block; margin-left: 10px;">
            <option value="0" <?= $filtroFirma === '0' ? 'selected' : '' ?>>Pendientes</option>
            <option value="1" <?= $filtroFirma === '1' ? 'selected' : '' ?>>Firmados</option>
        </select>
    </form>
    <br>
    <?php if ($filtroFirma === '0'): ?>
        <button id="firmarSeleccionados" class="btn btn-primary mb-3">Firmar seleccionados</button>
    <?php endif; ?>
    <br>
    <table class="table table-bordered table-sm" id="tablaFirmaPrincipal">
        <thead class="table-light">
            <tr>
                <?php if ($filtroFirma === '0'): ?>
                    <th><input type="checkbox" id="seleccionarTodos"></th>
                <?php endif; ?>
                <th>Expediente</th>
                <th>Documento</th>
                <th>Asunto</th>
                <th>Fecha</th>
                <th>Firmantes</th> <!-- NUEVA COLUMNA -->
            </tr>
        </thead>
        <tbody>
            <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) : ?>
                <tr>
                <?php if ($filtroFirma === '0'): ?>
                    <td><input type="checkbox" class="chkDocumento" data-id="<?= $row['iCodTramite'] ?>" data-archivo="<?= $row['documentoElectronico'] ?>"></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($row['cCodificacion']) ?></td>
                    <td><a href="./cDocumentosFirmados/<?= rawurlencode($row['documentoElectronico']) ?>" target="_blank"><?= htmlspecialchars($row['documentoElectronico']) ?></a></td>
                    <td><?= htmlspecialchars($row['cAsunto']) ?></td>
                    <td><?= isset($row['fFecDocumento']) && $row['fFecDocumento'] instanceof DateTime ? $row['fFecDocumento']->format('d/m/Y H:i') : '' ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="addComponent" style="display: none;"></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="./scripts/jquery.blockUI.js"></script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>

<script>
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

    jqFirmaPeru(document).ready(function () {
        const $jq = jqFirmaPeru;

        $jq('#seleccionarTodos').on('change', function () {
            $jq('.chkDocumento').prop('checked', this.checked);
        });

        $jq('#firmarSeleccionados').on('click', function () {
            const seleccionados = [];
            $jq('.chkDocumento:checked').each(function () {
                seleccionados.push({
                    iCodTramite: $jq(this).data('id'),
                    archivo: $jq(this).data('archivo')
                });
            });

            if (seleccionados.length === 0) {
                alert("Seleccione al menos un documento.");
                return;
            }

            fetch('comprimirMasivoPorAprobar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ documentos: seleccionados })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    const nombreZip = data.zipPath.replace('.7z', '');
                    const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsMasivoPorAprobar.php?iCodTramite=${nombreZip}`;

                    const paramPrev = {
                        param_url: param_url,
                        param_token: "123456",
                        document_extension: "pdf"
                    };
                    const param = btoa(JSON.stringify(paramPrev));
                    const port = "48596";

                    startSignature(port, param);
                } else {
                    alert("Error al preparar documentos.");
                }
            });
        });
    });
</script>
