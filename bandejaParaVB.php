<?php
include("head.php");
include("conexion/conexion.php");

$iCodOficina = $_SESSION['iCodOficinaLogin'];
$iCodTrabajador= $_SESSION["CODIGO_TRABAJADOR"];

// Obtener tipos de documento internos
$tipoDocQuery = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

// Obtener oficinas
$oficinasQuery = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);

$condiciones = "TF.iCodTrabajador = ?";
$params = [$iCodTrabajador];



$sql = "SELECT * FROM Tra_M_Tramite_Firma WHERE iCodTrabajador = ? AND nFlgFirma = 0";



$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

$tramites = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tramites[] = $row;
}
?>

<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
 


<div class="container">
<div class="titulo-principal">DOCUMENTOS PARA VISTO BUENO</div>
    <!-- Card 1: Criterios de Búsqueda -->
    <div class="card">
    <div class="card-title">CRITERIOS DE BÚSQUEDA</div>
    <form>

        <!-- FILA 1 -->
        <div class="form-row">
            <div class="input-container">
                <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" " required>
                <label>Expediente</label>
            </div>

            <div class="input-container">
                <input type="text" name="extension" value="<?= $valorExtension ?? '' ?>" placeholder=" ">
                <label>Extensión</label>
            </div>

            <div class="input-container">
                <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" ">
                <label>Desde</label>
            </div>

            <div class="input-container">
                <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" ">
                <label>Hasta</label>
            </div>
        </div>

        <!-- FILA 2 -->
        <div class="form-row">
            <div class="input-container">
                <input type="text" name="documento" class="form-control" placeholder=" ">
                <label>N° Documento</label>
            </div>

            <div class="input-container select-flotante">
                <select name="tipo_documento" required>
                    <option value="" disabled selected></option>
                    <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?= $td['cDescTipoDoc'] ?>"><?= $td['cDescTipoDoc'] ?></option>
                    <?php endwhile; ?>
                </select>
                <label>Tipo de Documento</label>
            </div>

            <div class="input-container">
                <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" ">
                <label>Asunto</label>
            </div>
        </div>

        <!-- FILA 3 -->
        <div class="form-row" style="justify-content: center;">
            <div class="input-container select-flotante" style="min-width: 300px;">
                <select name="oficina_destino" required>
                    <option value="" disabled selected></option>
                    <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
                        <option value="<?= $of['cNomOficina'] ?>"><?= $of['cNomOficina'] ?></option>
                    <?php endwhile; ?>
                </select>
                <label>Oficina de Origen</label>
            </div>
        </div>

        <!-- BOTONES -->
        <div class="form-row" style="justify-content: flex-end; gap: 1rem;">
            <a href="bandejaEnviados.php" class="btn btn-secondary" style="min-width: auto;">
                <span class="material-icons">auto_delete</span> Quitar Filtros
            </a>
            <button type="submit" class="btn btn-primary" style="min-width: auto;">
                <span class="material-icons">search</span> Buscar
            </button>
        </div>
    </form>
</div>

    <!-- Card 2: Resultados -->
    <div class="card">
        <div>
            <!-- <div class="button-row table-action">
                <button type="btn" class="btn btn-primary">
                    <span class="material-icons">check_circle</span> Aprobar
                </button>
            </div> -->

            <table>
                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Fecha</th>
                        <th>Asunto</th>
                        <th>Documento</th>
                        
                        <!-- <th>Autor</th> -->
                        <th>Tipo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tramites as $t): ?>
                    <?php
                        $TraDataSql = "SELECT * FROM Tra_M_Tramite WHERE iCodTramite = ?";
                        $TraStmt = sqlsrv_query($cnx,$TraDataSql, array($t['iCodTramite']));

                        if(!$t["nFlgEstado"]){
                            $extraSql = "SELECT * FROM Tra_M_Tramite_Digitales WHERE iCodDigital = ?";
                            $extraStmt = sqlsrv_query($cnx,$extraSql, array($t["iCodDigital"]));

                            $extraData = sqlsrv_fetch_array($extraStmt,SQLSRV_FETCH_ASSOC);
                        }
                        
                        $TraData = sqlsrv_fetch_array($TraStmt, SQLSRV_FETCH_ASSOC);
                    ?>
                        <tr>
                            <td><?= $TraData["iCodTramite"] ?></td>
                            <td><?= $TraData['fFecRegistro'] ? $TraData['fFecRegistro']->format('d/m/Y H:i') : '' ?></td>

                            <td><?= $TraData["cAsunto"] ?></td>
                            <td>
                                <a href="<?= $t['nFlgEstado'] == 1 ? "./cDocumentosFirmados/".$TraData["documentoElectronico"] : "./cAlmacenArchivos/".$extraData["cNombreOriginal"] ?>" target="_blank" rel="noopener noreferrer">
                                    <?= $t['nFlgEstado'] == 1 ? $TraData["documentoElectronico"] : $extraData["cNombreOriginal"] ?>
                                </a>
                            </td>
                            <!-- <td><?= htmlspecialchars($t['Autor']) ?></td> -->
                            <td><?= $t['nFlgEstado'] == 1 ? "Doc. Principal" : "Doc. Complementario" ?></td>
                            <td>
                                <button type="button" class="btn-primary modalvb" data-id="<?=$TraData["iCodTramite"]?>">Ver firmantes</button>
                                <button type="btn" class="btn-primary" onclick="firmarVB(<?= $t['iCodFirma'] ?>, '<?=  $t['nFlgEstado'] == 1 ? 'principal' : 'complementario' ?>')">
                                    Dar VB
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tramites)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#888;">No se encontraron resultados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="addComponent"></div>
<dialog id="modalVB" class="modal">
    <div class="modal-content">
        <span class="modal-close cerrarModal" id="cerrarModal">&times;</span>
        <div id="contenidoVB">Cargando...</div>
    </div>
</dialog>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Script Modal -->
<script>
document.querySelectorAll('.modalvb').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const modal = document.getElementById("modalVB");
        const contenido = document.getElementById("contenidoVB");

        contenido.innerHTML = "Cargando...";
        modal.style.display = "block";

        fetch(`listadoFirmantes.php?iCodTramite=${id}`)
            .then(res => res.text())
            .then(html => {
                contenido.innerHTML = html;
            });
    })
})
document.querySelectorAll('.cerrarModal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById("modalVB").style.display = "none";
    });
});
</script>
<script>
    var jqFirmaPeru = jQuery.noConflict(true);

    function signatureInit() {
        alert("PROCESO INICIADO");
    }

    function signatureOk() {
        alert("DOCUMENTO FIRMADO");
        
        alert("Proceso Terminado, Se redirigira a la bandeja.");

        top.location.reload();

        // fetch(`./procesarMovimiento.php?iCodTramite=${iCodTramite}&tipo=${tipoOperacion}`).then((res) => res.json()).then((data) => {
        //     if(data.status === "error"){
        //         throw new Error(data.message);
        //     } 
        // }).catch((err) => console.error(error))
    }	

    function signatureCancel() {
        alert("OPERACION CANCELADA");
    }

    document.getElementById('selectAll')?.addEventListener('click', function () {
        document.querySelectorAll('input[name="tramites[]"]').forEach(cb => cb.checked = this.checked);
    });

    

async function firmarVB(iCodFirma, tipoDoc){
    const body = new FormData();
    body.append("iCodFirma", iCodFirma);
    body.append("tipoDoc", tipoDoc);

    try {
        const resPos = await fetch("./getFirmaPosicion.php",{
            method: "POST",
            body
        })
        const dataPos = await resPos.json();
        if(dataPos.status == "error"){
            throw new Error(dataPos.message);
        }
        body.append("posFirma", dataPos.message);
        const zipRes = await fetch("./zipVB.php",{
            method: "POST",
            body
        })
        const zipData = await zipRes.json();
        if(zipData.status == "error"){
            throw new Error(dataPos.message);
        }
        const param_url = "https://app2.inr.gob.pe/sgd/getFpParamsVB.php?iCodFirma="+iCodFirma+"&pos="+dataPos.message+"&tipo="+zipData.message;
        const paramPrev = {
            "param_url": param_url,
            "param_token": "16262369571",
            "document_extension": "pdf"
        }
     
        const param = btoa(JSON.stringify(paramPrev));
        const port = "48596";

        startSignature(port, param);

    } catch (error) {
        console.error(error);
    }
}

</script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>