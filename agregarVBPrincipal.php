<?php
    include_once("./conexion/conexion.php");
    global $cnx;
    $iCodTramite = isset($_GET['iCodTramite']) ? (int) $_GET['iCodTramite'] : 0;
    $tipoOperacion = $_GET["tipoOperacion"];
    if ($iCodTramite <= 0) {
        echo "<div style='color:red;'>Error: Código de trámite no válido</div>";
        exit;
    }
?>
<form id="listadoFirmantes" style="margin-bottom: 1rem;">
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <input type="hidden" name="tipoTramite" value="<?= $tipoOperacion ?>">
    <div style="display: flex; justify-content: space-between; align-items: center">
        <h2>Listado de Firmantes</h2>
        <button type="submit" class="btn-primary">
            <i class="material-icons">send</i> Continuar
        </button>
    </div>
    <div id="listadoFirmantesBody" style="display: flex; flex-direction: column; gap: 1.25rem; margint-top: 1rem;"></div>
</form>

<h2>Agregar Visto Bueno</h2>
<form id="selectForm" style="margin-top:1rem; display: flex; flex-direction: column; gap: 1.25rem;">
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <div style="width: 100%; display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem;">
        <input type="hidden" name="oficinaSelect" id="oficinaSelect">
        <button as="div" type="button" id="oficinaOpen" style="width: 100%; border-radius: 0.5rem; background-color: lightgray; padding: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 1rem; border: none; transition: background-color 0.2s ease-in-out;" >
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 512 512"><path fill="currentColor" d="M432 176H320V64a48 48 0 0 0-48-48H80a48 48 0 0 0-48 48v416a16 16 0 0 0 16 16h104a8 8 0 0 0 8-8v-71.55c0-8.61 6.62-16 15.23-16.43A16 16 0 0 1 192 416v72a8 8 0 0 0 8 8h264a16 16 0 0 0 16-16V224a48 48 0 0 0-48-48M98.08 431.87a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m80 240a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m80 320a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79m0-80a16 16 0 1 1 13.79-13.79a16 16 0 0 1-13.79 13.79M444 464H320V208h112a16 16 0 0 1 16 16v236a4 4 0 0 1-4 4"/><path fill="currentColor" d="M400 400a16 16 0 1 0 16 16a16 16 0 0 0-16-16m0-80a16 16 0 1 0 16 16a16 16 0 0 0-16-16m0-80a16 16 0 1 0 16 16a16 16 0 0 0-16-16m-64 160a16 16 0 1 0 16 16a16 16 0 0 0-16-16m0-80a16 16 0 1 0 16 16a16 16 0 0 0-16-16m0-80a16 16 0 1 0 16 16a16 16 0 0 0-16-16"/></svg>
            <div id="oficinaContent" style="display:flex; flex-direction: column; align-items: flex-start">BUSCAR OFICINAS</div>
        </button>
    </div>
    <table border="1" width="100%" cellpadding="5" cellspacing="0" id="trabajadoresTable">
        <thead>
            <tr>
                <th>NOMBRES</th>
                <th>OFICINA</th>
                <th>PERFIL</th>
                <th>OPCIÓN</th>
            </tr>
        </thead>
        <tbody id="trabajadoresBody">
            <?php
            // Obtener lista de trabajadores activos
            $sqlTrabajadores = "SELECT top 20 T.iCodTrabajador, T.cNombresTrabajador, T.cApellidosTrabajador, PE.cDescPerfil, O.cSiglaOficina
                                FROM Tra_M_Trabajadores T
                                INNER JOIN Tra_M_Perfil_Ususario P ON T.iCodTrabajador = P.iCodTrabajador
                                INNER JOIN Tra_M_Oficinas O ON P.iCodOficina = O.iCodOficina
                                INNER JOIN Tra_M_Perfil PE ON P.iCodPerfil = PE.iCodPerfil
                                WHERE T.nFlgEstado = 1 AND P.iCodPerfil IN (3, 4)
                                ORDER BY T.cNombresTrabajador, T.cApellidosTrabajador";

            $rsTrabajadores = sqlsrv_query($cnx, $sqlTrabajadores);
            if ($rsTrabajadores === false) {
                die(print_r(sqlsrv_errors(), true));
            }

            while ($trabajador = sqlsrv_fetch_array($rsTrabajadores, SQLSRV_FETCH_ASSOC)) {
                ?>
                <tr>
                    <td><?= htmlspecialchars($trabajador["cNombresTrabajador"].' '.$trabajador["cApellidosTrabajador"]) ?></td>
                    <td><?= htmlspecialchars($trabajador["cSiglaOficina"]) ?></td>
                    <td><?= htmlspecialchars($trabajador["cDescPerfil"]) ?></td>
                    <td align="center">
                        <input type="checkbox" name="lstTrabSel[]" value="<?= htmlspecialchars($trabajador["iCodTrabajador"]) ?>">
                    </td>
                </tr>
                <?php
            }
            sqlsrv_free_stmt($rsTrabajadores);
            ?>
        </tbody>
    </table>
    <div style="text-align: right; margin-top: 10px;">
        <button type="submit" class="btn-primary">Solicitar</button>
    </div>
</form>
<dialog id="oficinaSelection" style="width: 450px; border-radius: 0.5rem;">
    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; border: none; padding: 0.5rem;">
        <label for="oficinaAutoComplete">Buscar Oficina:</label>
        <input type="text" id="oficinaAutoComplete" style="width: 100%; border-radius: 0.5rem; padding: 0.5rem;" />
        <div id="searchResults" style="display: flex; flex-direction: column; gap: 0.5rem; border-radius: 0.5rem; overflow: auto; max-height: 300px; width: 100%; padding: 0.75rem;">
            <?php
                $sqlOfic = "SELECT iCodOficina, cNomOficina, cSiglaOficina
                FROM Tra_M_Oficinas
                WHERE iFlgEstado= 1
                ORDER BY cSiglaOficina";
                $rsOfic = sqlsrv_query($cnx, $sqlOfic);

                if($rsOfic == false){
                    die(print_r(sqlsrv_errors(), true));
                }

                ?>
                    <button class="oficinaOption" data-value="">
                        <div style="font-weight: bold;">
                            TODOS
                        </div>
                        <div style="font-size: 0.6rem; text-align: left;">
                            Mostrar Todo el prosnal de cada oficina
                        </div>
                    </button>
                <?php

                while ($oficina = sqlsrv_fetch_array($rsOfic, SQLSRV_FETCH_ASSOC)) {
                    ?>
                        <button 
                            class="oficinaOption" 
                            data-value="<?= htmlspecialchars($oficina["iCodOficina"]) ?>"
                            data-name="<?= htmlspecialchars($oficina["cNomOficina"]) ?>"
                            data-sigla="<?= htmlspecialchars($oficina["cSiglaOficina"]) ?>"
                        >
                            <div style="font-weight: bold;">
                                <?= htmlspecialchars($oficina["cSiglaOficina"]) ?>
                            </div>
                            <div style="font-size: 0.6rem; text-align: left;">
                                <?= htmlspecialchars($oficina["cNomOficina"]) ?>
                            </div>
                        </button>
                    <?php
                }
            ?>
        </div>
        <button id="oficinaClose">Cancelar</button>
    </div>
</dialog>