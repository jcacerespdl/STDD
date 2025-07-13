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
// Procesar si se ha cambiado el tipo de complementario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'])) {
    $tipo = (int) $_POST['tipo'];

    // Eliminar firmantes actuales del digital
    sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Firma WHERE iCodTramite = ? AND iCodDigital = ?", [$iCodTramite, $iCodDigital]);

    // Actualizar tipo de complementario
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Digitales SET cTipoComplementario = ? WHERE iCodTramite = ? AND iCodDigital = ?", [$tipo, $iCodTramite, $iCodDigital]);

    // === Funciones de utilidad ===
    function obtenerJerarquiaOficinas($cnx, $iCodOficinaInicial) {
        $jerarquia = [];
        $actual = $iCodOficinaInicial;
        while ($actual) {
            $sql = "SELECT iCodOficina, iCodOficina_Padre FROM Tra_M_Oficinas WHERE iCodOficina = ?";
            $stmt = sqlsrv_query($cnx, $sql, [$actual]);
            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $jerarquia[] = $row['iCodOficina'];
                $actual = $row['iCodOficina_Padre'];
            } else {
                break;
            }
        }
        return array_reverse($jerarquia);
    }

    function asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, $iCodPerfil, $iCodOficina, $tipoFirma, $posicion) {
        $sql = "SELECT iCodTrabajador FROM Tra_M_Perfil_Ususario WHERE iCodPerfil = ? AND iCodOficina = ?";
        $stmt = sqlsrv_query($cnx, $sql, [$iCodPerfil, $iCodOficina]);
        if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            $iCodTrabajador = $row['iCodTrabajador'];
            $sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ? AND posicion = ?";
            $stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $iCodDigital, $iCodTrabajador, $posicion]);
            $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            if ($rowCheck['total'] == 0) {
                $sqlInsert = "INSERT INTO Tra_M_Tramite_Firma 
                    (iCodTramite, iCodDigital, iCodTrabajador, iCodOficina, nFlgFirma, nFlgEstado, posicion, tipoFirma)
                    VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
                sqlsrv_query($cnx, $sqlInsert, [$iCodTramite, $iCodDigital, $iCodTrabajador, $iCodOficina, $posicion, $tipoFirma]);
            }
        }
    }

    function asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, $iCodPerfil, $iCodOficina, $tipoFirma, $posicion) {
        $sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
                     WHERE iCodTramite = ? AND iCodDigital = ? AND iCodPerfil = ? AND iCodOficina = ? AND posicion = ?";
        $stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$iCodTramite, $iCodDigital, $iCodPerfil, $iCodOficina, $posicion]);
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        if ($row['total'] == 0) {
            $sqlInsert = "INSERT INTO Tra_M_Tramite_Firma 
                (iCodTramite, iCodDigital, iCodPerfil, iCodOficina, nFlgFirma, nFlgEstado, posicion, tipoFirma)
                VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
            sqlsrv_query($cnx, $sqlInsert, [$iCodTramite, $iCodDigital, $iCodPerfil, $iCodOficina, $posicion, $tipoFirma]);
        }
    }

    function obtenerOficinaPadreGeneradora($cnx, $iCodTramiteDerivar) {
        $sqlMov = "SELECT TOP 1 iCodMovimiento FROM Tra_M_Tramite_Movimientos WHERE iCodTramiteDerivar = ?";
        $stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramiteDerivar]);
        if (!$stmtMov || !($rowMov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC))) return null;

        $iCodMovimiento = $rowMov['iCodMovimiento'];
        $sqlTramite = "SELECT iCodTramite FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
        $stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodMovimiento]);
        if (!$stmtTramite || !($rowTramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC))) return null;

        $iCodTramiteOriginal = $rowTramite['iCodTramite'];
        $sqlOfi = "SELECT iCodOficinaRegistro FROM Tra_M_Tramite WHERE iCodTramite = ?";
        $stmtOfi = sqlsrv_query($cnx, $sqlOfi, [$iCodTramiteOriginal]);
        if (!$stmtOfi || !($rowOfi = sqlsrv_fetch_array($stmtOfi, SQLSRV_FETCH_ASSOC))) return null;

        $iCodOficinaRegistro = $rowOfi['iCodOficinaRegistro'];
        $sqlPadre = "SELECT iCodOficina_Padre FROM Tra_M_Oficinas WHERE iCodOficina = ?";
        $stmtPadre = sqlsrv_query($cnx, $sqlPadre, [$iCodOficinaRegistro]);
        if ($stmtPadre && ($rowPadre = sqlsrv_fetch_array($stmtPadre, SQLSRV_FETCH_ASSOC))) {
            return $rowPadre['iCodOficina_Padre'];
        }

        return null;
    }

    $iCodOficinaSession = $_SESSION['iCodOficinaLogin'];

    switch ($tipo) {
        case 1:
            
            break;
        case 2:
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $nivelActual = count($jerarquia) - 1;
            $oficinaTop = $jerarquia[0];
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'A');
            if ($nivelActual >= 1) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual], 0, 'B');
            if ($nivelActual >= 2) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 1], 0, 'C');
            if ($nivelActual >= 3) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 2], 0, 'D');
            break;
        case 3:
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'R');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'S');
            break;
        case 4:
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'U');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 23, 0, 'T');
            break;
        case 5:
            asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, 4, 3, 0, 'W');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 3, 1, 'X');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'Y');
            $iCodOficinaPadreGeneradora = obtenerOficinaPadreGeneradora($cnx, $iCodTramite);
            if ($iCodOficinaPadreGeneradora) {
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $iCodOficinaPadreGeneradora, 1, 'Z');
            }
            break;
    }

    echo "<script>window.location.href = 'registroTrabajadoresFirmaComplementario.php?iCodTramite=$iCodTramite&iCodDigital=$iCodDigital';</script>";
    exit;
}




?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const tipo = <?= (int) $cTipoActual ?>;
    
    const contenedorSIGA = document.getElementById("bloque-siga");
    const contenedorOtros = document.getElementById("bloque-otros");
    const botonAgregar = document.getElementById("btnAgregarFirmante");
    const eliminarBtns = document.querySelectorAll(".btnEliminar");

    if (tipo === 1) {
        contenedorSIGA.style.display = "block";
        contenedorOtros.style.display = "none";
    } else if (tipo === 6 || tipo === 0) {
        contenedorSIGA.style.display = "none";
        contenedorOtros.style.display = "block";
    } else {
        contenedorSIGA.style.display = "none";
        contenedorOtros.style.display = "none";
    }

    if ([3, 4, 5].includes(tipo)) {
        eliminarBtns.forEach(btn => btn.style.display = "none");
        if (botonAgregar) botonAgregar.style.display = "none";
    }
});
</script>
<!-- BLOQUE PEDIDO SIGA (P, Q, O) -->
<div id="bloque-siga" class="bloque" style="display: none;">
    <h3 class="subtitulo">Firmantes por Oficina - Pedido SIGA</h3>

    <form method="POST" action="asignarFirmanteSiga.php">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">

        <div class="fila-flex">
            <label for="oficinaP">Solicita   </label>
            <input type="text" name="oficinaP" id="oficinaP" placeholder="Buscar oficina para posición P" required>
        </div>

        <div class="fila-flex">
            <label for="oficinaQ">Autoriza</label>
            <input type="text" name="oficinaQ" id="oficinaQ" placeholder="Buscar oficina para posición Q" required>
        </div>

        <div class="fila-flex">
            <label for="oficinaO">Visto </label>
            <input type="text" name="oficinaO" id="oficinaO" placeholder="Buscar oficina para posición O" required>
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
</body>
</html>
