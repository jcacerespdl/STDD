<?php
include("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodDigital = $_GET['iCodDigital'] ?? null;
$iCodOficinaLogin = $_SESSION['iCodOficinaLogin'] ?? null;

if (!$iCodTramite || !$iCodDigital || !$iCodOficinaLogin) {
    die("Faltan parámetros obligatorios.");
}

// Obtener nombre del archivo y tipo actual
$sql = "SELECT cDescripcion, cTipoComplementario FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND iCodDigital = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);
$documento = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$cDescripcion = $documento['cDescripcion'] ?? '';
$cTipoActual = $documento['cTipoComplementario'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Firmantes - Documento Complementario</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fc;
            margin: 20px;
        }

        .form-card {
            max-width: 900px;
            margin: auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h2 {
            font-size: 20px;
            font-weight: 600;
            color: #364897;
            margin-bottom: 15px;
        }

        label {
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        select, input, button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }

        button {
            background-color: #364897;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            background-color: #2c3c85;
        }

        .subtitulo {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #444;
        }

        .bloque {
            margin-bottom: 20px;
        }

        .fila-flex {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .material-icons {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="form-card">
    <h2>Asignar Tipo de Complementario y Firmantes</h2>
    <p><strong>Documento:</strong> <?= htmlspecialchars($cDescripcion) ?></p>

    <form id="formTipoComplementario" method="POST">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">

        <label for="tipo">Tipo de Complementario</label>
        <select name="tipo" id="tipo" required onchange="this.form.submit()">
            <option value="">Seleccione...</option>
            <option value="1" <?= $cTipoActual == 1 ? 'selected' : '' ?>>Pedido SIGA</option>
            <option value="2" <?= $cTipoActual == 2 ? 'selected' : '' ?>>TDR o ETT</option>
            <?php if ($iCodOficinaLogin == 112): ?>
                <option value="3" <?= $cTipoActual == 3 ? 'selected' : '' ?>>Solicitud de Crédito Presupuestario</option>
            <?php endif; ?>
            <?php if ($iCodOficinaLogin == 23): ?>
                <option value="4" <?= $cTipoActual == 4 ? 'selected' : '' ?>>Aprobación de Crédito Presupuestario</option>
            <?php endif; ?>
            <?php if ($iCodOficinaLogin == 3): ?>
                <option value="5" <?= $cTipoActual == 5 ? 'selected' : '' ?>>Orden de Servicio</option>
            <?php endif; ?>
            <option value="6" <?= $cTipoActual == 6 ? 'selected' : '' ?>>Otros</option>
        </select>
    </form>
    <?php
// Si se selecciona nuevo tipo, actualizamos el valor y eliminamos firmantes previos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'])) {
    $tipo = (int) $_POST['tipo'];

    // Eliminar firmantes actuales del digital
    sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Firma WHERE iCodTramite = ? AND iCodDigital = ?", [$iCodTramite, $iCodDigital]);

    // Actualizar tipo de complementario
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Digitales SET cTipoComplementario = ? WHERE iCodTramite = ? AND iCodDigital = ?", [$tipo, $iCodTramite, $iCodDigital]);

    // Redirigir para recargar con nuevo tipo
    echo "<script>window.location.href = 'registroTrabajadoresFirmaComplementario.php?iCodTramite=$iCodTramite&iCodDigital=$iCodDigital';</script>";
    exit;
}
?>
<!-- BLOQUE PEDIDO SIGA (P, Q, O) -->
<div id="bloque-siga" class="bloque" style="display: none;">
    <h3 class="subtitulo">Firmantes por Oficina - Pedido SIGA</h3>
    <form method="POST" action="asignarFirmanteSiga.php">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">

        <div class="fila-flex">
            <label for="oficinaP">Solicita (P):</label>
            <input type="text" name="oficinaP" id="oficinaP" class="input-oficina" placeholder="Buscar oficina para posición P" autocomplete="off" required>
            <input type="hidden" name="oficinaP_id" id="oficinaP_id">
        </div>

        <div class="fila-flex">
            <label for="oficinaQ">Autoriza (Q):</label>
            <input type="text" name="oficinaQ" id="oficinaQ" class="input-oficina" placeholder="Buscar oficina para posición Q" autocomplete="off" required>
            <input type="hidden" name="oficinaQ_id" id="oficinaQ_id">
        </div>

        <div class="fila-flex">
            <label for="oficinaO">Visto Bueno (O):</label>
            <input type="text" name="oficinaO" id="oficinaO" class="input-oficina" placeholder="Buscar oficina para posición O" autocomplete="off" required>
            <input type="hidden" name="oficinaO_id" id="oficinaO_id">
        </div>

        <button type="submit">Asignar Firmantes SIGA</button>
    </form>
</div>

<!-- BLOQUE OTROS -->
<div id="bloque-otros" class="bloque" style="display: none;">
    <h3 class="subtitulo">Buscar Trabajador</h3>

    <form method="POST" action="asignarFirmanteComplementario.php">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">

        <div class="fila-flex">
            <label for="trabajador">Nombre o DNI:</label>
            <input type="text" name="trabajador" id="trabajador" placeholder="Buscar trabajador por nombre o DNI" required>
        </div>

        <div class="fila-flex">
            <label for="posicion">Posición (B-J):</label>
            <input type="text" name="posicion" id="posicion" maxlength="1" placeholder="Ej. B, C, D..." required>
        </div>

        <div class="fila-flex">
            <label for="tipoFirma">Tipo de Firma:</label>
            <select name="tipoFirma" required>
                <option value="1">Firma</option>
                <option value="0">Visto Bueno</option>
            </select>
        </div>

        <button type="submit" id="btnAgregarFirmante">Agregar Firmante</button>
    </form>
</div>
<hr>
<h3 class="subtitulo">Firmantes Actuales</h3>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead style="background: #f5f5f5;">
        <tr>
            <th>Trabajador</th>
            <th>Oficina</th>
            <th>Posición</th>
            <th>Tipo Firma</th>
            <th>Opciones</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sqlFirmantes = "SELECT F.iCodFirma, F.iCodTrabajador, F.iCodOficina, F.posicion, F.tipoFirma,
                                T.cNombresTrabajador + ' ' + T.cApellidosTrabajador AS nombre,
                                O.cNomOficina
                         FROM Tra_M_Tramite_Firma F
                         LEFT JOIN TRA_M_Trabajadores T ON F.iCodTrabajador = T.iCodTrabajador
                         LEFT JOIN Tra_M_Oficinas O ON F.iCodOficina = O.iCodOficina
                         WHERE F.iCodTramite = ? AND F.iCodDigital = ?
                         ORDER BY F.posicion";

        $stmtFirmantes = sqlsrv_query($cnx, $sqlFirmantes, [$iCodTramite, $iCodDigital]);
        while ($row = sqlsrv_fetch_array($stmtFirmantes, SQLSRV_FETCH_ASSOC)):
            $tipoDesc = $row['tipoFirma'] == 1 ? 'Firma' : 'Visto Bueno';
        ?>
            <tr>
                <td><?= htmlspecialchars($row['nombre'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['cNomOficina'] ?? '') ?></td>
                <td><?= $row['posicion'] ?></td>
                <td><?= $tipoDesc ?></td>
                <td>
                    <?php if (!in_array((int)$cTipoActual, [3, 4, 5])): ?>
                        <form method="POST" action="eliminarFirmanteComplementario.php" style="display:inline;">
                            <input type="hidden" name="iCodFirma" value="<?= $row['iCodFirma'] ?>">
                            <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
                            <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">
                            <button type="submit" class="btnEliminar" title="Eliminar" onclick="return confirm('¿Eliminar firmante?')">
                                <span class="material-icons">delete</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <span style="color: gray;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div> <!-- fin .form-card -->

<!-- jQuery + jQuery UI para autocompletado -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const tipo = <?= (int) $cTipoActual ?>;

    const contenedorSIGA = document.getElementById("bloque-siga");
    const contenedorOtros = document.getElementById("bloque-otros");
    const botonAgregar = document.getElementById("btnAgregarFirmante");
    const eliminarBtns = document.querySelectorAll(".btnEliminar");

    // Ocultar o mostrar bloques según tipo
    if (tipo === 1) {
        contenedorSIGA.style.display = "block";
        contenedorOtros.style.display = "none";
    } else if (tipo === 6) {
        contenedorSIGA.style.display = "none";
        contenedorOtros.style.display = "block";
    } else {
        contenedorSIGA.style.display = "none";
        contenedorOtros.style.display = "none";
    }

    // Bloquear eliminar en firmantes fijos
    if ([3, 4, 5].includes(tipo)) {
        eliminarBtns.forEach(btn => btn.style.display = "none");
        if (botonAgregar) botonAgregar.style.display = "none";
    }

    // Autocompletado con ajax_buscar_oficina.php
    $(".input-oficina").each(function () {
        const input = $(this);
        const hidden = $("#" + input.attr("id") + "_id");

        input.autocomplete({
            source: function (request, response) {
                $.ajax({
                    url: "ajax_buscar_oficina.php",
                    dataType: "json",
                    data: { q: request.term },
                    success: function (data) {
                        response($.map(data, function (item) {
                            return {
                                label: item.cNomOficina,
                                value: item.cNomOficina,
                                id: item.iCodOficina
                            };
                        }));
                    }
                });
            },
            minLength: 2,
            select: function (event, ui) {
                input.val(ui.item.label);
                hidden.val(ui.item.id);
                return false;
            },
            change: function (event, ui) {
                if (!ui.item) {
                    input.val('');
                    hidden.val('');
                }
            }
        });
    });
});
</script>

</body>
</html>
