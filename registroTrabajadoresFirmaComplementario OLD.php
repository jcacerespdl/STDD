<?php 
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    header("Location: ../index.php?alter=5");
    exit();
}

include_once("./conexion/conexion.php");

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodDigital = $_GET['iCodDigital'] ?? null;

// Eliminación de firmante
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['accion'] === 'eliminar') {
    $iCodTrabajador = intval($_POST['iCodTrabajador'] ?? 0);
    $sqlDel = "DELETE FROM Tra_M_Tramite_Firma 
               WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ?";
    sqlsrv_query($cnx, $sqlDel, [$iCodTramite, $iCodDigital, $iCodTrabajador]);
    echo "<script>alert('Firmante eliminado correctamente.'); window.location.href=window.location.href;</script>";
    exit();
}

// Registro de firmantes nuevos
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['firmantesJson'])) {
    $firmantesData = json_decode($_POST['firmantesJson'], true);

    foreach ($firmantesData as $firmante) {
        $idTrab = intval($firmante['id']);
        $posicion = $firmante['posicion'];
        $tipoFirma = $firmante['tipoFirma'];
        $iCodOficina = intval($firmante['iCodOficina']);

        $sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
                     WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ?";
        $paramsCheck = [$iCodTramite, $iCodDigital, $idTrab];
        $rsCheck = sqlsrv_query($cnx, $sqlCheck, $paramsCheck);
        $rowCheck = sqlsrv_fetch_array($rsCheck);

        if ($rowCheck['total'] == 0) {
            $sqlAdd = "INSERT INTO Tra_M_Tramite_Firma (
                iCodTramite, iCodDigital, iCodTrabajador, iCodOficina,
                nFlgFirma, nFlgEstado, posicion, tipoFirma
            ) VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
            $params = [$iCodTramite, $iCodDigital, $idTrab, $iCodOficina, $posicion, $tipoFirma];
            sqlsrv_query($cnx, $sqlAdd, $params);
        }
    }

    echo "<script>alert('Firmantes insertados correctamente.'); window.location.href=window.location.href;</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Firmantes Complementario</title>
    <link rel="stylesheet" href="css/tramite.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fc;
            margin: 20px;
        }

        .popup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .popup-table th {
            background-color: #364897;
            color: #fff;
            padding: 10px;
            text-align: left;
        }

        .popup-table td {
            padding: 10px;
            border-top: 1px solid #e1e1e1;
        }

        .input-container {
            margin-bottom: 20px;
            position: relative;
        }

        .select-flotante {
            position: relative;
            margin-bottom: 25px;
            width: 100%;
        }

        .select-flotante input[type="text"] {
            width: 100%;
            padding: 12px 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: none;
            box-sizing: border-box;
        }

        .select-flotante label {
            position: absolute;
            top: 12px;
            left: 12px;
            color: #999;
            font-size: 14px;
            pointer-events: none;
            background-color: #fff;
            padding: 0 4px;
            transition: all 0.2s ease;
        }

        .select-flotante input[type="text"]:focus + label,
        .select-flotante input[type="text"]:not(:placeholder-shown) + label {
            top: -10px;
            left: 8px;
            font-size: 11px;
            color: #364897;
        }

        #sugerenciasOficina {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            width: 100%;
            max-height: 150px;
            overflow-y: auto;
            display: none;
            z-index: 999;
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        #sugerenciasOficina li {
            padding: 8px 10px;
            cursor: pointer;
            color: #333;
        }

        #sugerenciasOficina li:hover {
            background-color: #f0f0f0;
        }

        .botonFirma {
            background-color: #e6e9f2;
            border-radius: 6px;
            padding: 6px 10px;
            margin-right: 5px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid #ccc;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .botonFirma:hover {
            background-color: #d1d6e3;
        }
    </style>
</head>
<body>
<form method="POST">
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
    <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">
    <input type="hidden" id="firmantesJson" name="firmantesJson">

    <!-- Oficina con label flotante -->
    <div class="input-container select-flotante">
        <input type="text" id="nombreOficina" autocomplete="off"
               placeholder=" " onclick="buscarOficina('')" oninput="buscarOficina(this.value)" required>
        <label for="nombreOficina">Oficina</label>
        <input type="hidden" id="iCodOficinaHidden" name="iCodOficina">
        <ul id="sugerenciasOficina"></ul>
    </div>

    <!-- Tabla trabajadores -->
    <div>
        <table class="popup-table">
            <thead><tr><th>Nombre completo</th><th>Perfil</th><th>Seleccionar</th></tr></thead>
            <tbody id="tablaTrabajadores"></tbody>
        </table>
    </div>

    <div style="margin-top: 15px">
        <h3>Trabajadores Seleccionados:</h3>
        <div id="listaSeleccionados"></div>
    </div>
</form>
<?php
// Obtener firmantes ya registrados
$sqlFirmantes = "SELECT F.iCodTrabajador, T.cNombresTrabajador, T.cApellidosTrabajador, 
                        F.posicion, F.tipoFirma, O.cNomOficina
                 FROM Tra_M_Tramite_Firma F
                 JOIN Tra_M_Trabajadores T ON F.iCodTrabajador = T.iCodTrabajador
                 JOIN Tra_M_Oficinas O ON F.iCodOficina = O.iCodOficina
                 WHERE F.iCodTramite = ? AND F.iCodDigital = ?
                 ORDER BY 
                    CASE WHEN F.tipoFirma = 1 THEN 0 ELSE 1 END, F.posicion ASC";

$stmtFirmantes = sqlsrv_query($cnx, $sqlFirmantes, [$iCodTramite, $iCodDigital]);
$firmantesExistentesVB = 0;
?>

<h3>Firmantes ya registrados:</h3>
<table class="popup-table">
    <thead><tr><th>Posición</th><th>Nombre</th><th>Oficina</th><th>Tipo Firma</th><th>Acción</th></tr></thead>
    <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmtFirmantes, SQLSRV_FETCH_ASSOC)): ?>
        <tr>
            <td><?= $row['posicion'] ?></td>
            <td><?= $row['cNombresTrabajador'] . " " . $row['cApellidosTrabajador'] ?></td>
            <td><?= $row['cNomOficina'] ?></td>
            <td><?= $row['tipoFirma'] == 1 ? 'Principal' : 'Visto Bueno' ?></td>
            <td>
                <form method="POST" onsubmit="return confirm('¿Eliminar firmante?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
                    <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">
                    <input type="hidden" name="iCodTrabajador" value="<?= $row['iCodTrabajador'] ?>">
                    <button type="submit" style="color:red; border:none; background:none; cursor:pointer;">
                        ❌
                    </button>
                </form>
            </td>
        </tr>
        <?php if ($row['tipoFirma'] == 2) $firmantesExistentesVB++; ?>
        <?php endwhile; ?>
    </tbody>
</table>

<script>
let firmantesExistentesVB = <?= $firmantesExistentesVB ?>;
let seleccionados = [];
</script>
<script>
function asignarFirma(id, nombre, apellidos, oficinaId, oficinaNombre, perfil, tipoFirma) {
    if (seleccionados.some(t => t.id === id)) {
        alert("Este trabajador ya fue seleccionado.");
        return;
    }

    let posicion = 'A';
    if (tipoFirma === '2') {
        const letras = ['B','C','D','E','F','G','H','I','J'];
        const indexVB = firmantesExistentesVB + seleccionados.filter(t => t.tipoFirma === '2').length;
        posicion = letras[indexVB] || 'Z';
    }

    seleccionados.push({ id, nombre, apellidos, iCodOficina: oficinaId, oficinaNombre, perfil, posicion, tipoFirma });
    document.getElementById(`fila-${id}`).style.display = 'none';
    renderLista();
}

function eliminarSeleccionado(id) {
    seleccionados = seleccionados.filter(t => t.id != id);
    renderLista();
}

function renderLista() {
    const contenedor = document.getElementById("listaSeleccionados");
    const jsonFirmantes = [];
    let html = "<table class='popup-table'><thead><tr><th>Orden</th><th>Nombres</th><th>Apellidos</th><th>Oficina</th><th>Perfil</th><th>Tipo Firma</th><th>Acción</th></tr></thead><tbody>";

    seleccionados.forEach(t => {
        html += `<tr>
            <td>${t.posicion}</td>
            <td>${t.nombre}</td>
            <td>${t.apellidos}</td>
            <td>${t.oficinaNombre}</td>
            <td>${t.perfil}</td>
            <td>${t.tipoFirma === '1' ? 'Firma Principal' : 'Visto Bueno'}</td>
            <td><button onclick="eliminarSeleccionado('${t.id}')">❌</button></td>
        </tr>`;
        jsonFirmantes.push({
            id: t.id,
            posicion: t.posicion,
            tipoFirma: t.tipoFirma,
            iCodOficina: t.iCodOficina
        });
    });

    html += "</tbody></table>";
    contenedor.innerHTML = html;
    document.getElementById("firmantesJson").value = JSON.stringify(jsonFirmantes);
}

function cargarTrabajadores() {
    const oficina = document.getElementById("iCodOficinaHidden").value;
    if (!oficina) return;

    fetch(`./ajax_trabajadores_por_oficina.php?iCodOficina=${oficina}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById("tablaTrabajadores");
            tbody.innerHTML = "";
            data.forEach(trab => {
                tbody.innerHTML += `<tr id="fila-${trab.id}">
                    <td>${trab.nombre} ${trab.apellidos}</td>
                    <td>${trab.perfil}</td>
                    <td>
                        <button class="botonFirma" onclick="asignarFirma('${trab.id}', '${trab.nombre}', '${trab.apellidos}', '${trab.iCodOficina}', '${trab.oficinaNombre}', '${trab.perfil}', '1')">
                            <span class="material-symbols-outlined">signature</span> Principal
                        </button>
                        <button class="botonFirma" onclick="asignarFirma('${trab.id}', '${trab.nombre}', '${trab.apellidos}', '${trab.iCodOficina}', '${trab.oficinaNombre}', '${trab.perfil}', '2')">
                            <span class="material-icons">task_alt</span> Visto Bueno
                        </button>
                    </td>
                </tr>`;
            });
        });
}

function buscarOficina(texto) {
    const ul = document.getElementById("sugerenciasOficina");
    fetch(`./ajax_buscar_oficina.php?q=${encodeURIComponent(texto)}`)
        .then(res => res.json())
        .then(data => {
            ul.innerHTML = "";
            if (!data.length) {
                ul.style.display = "none";
                return;
            }
            data.forEach(ofi => {
                const li = document.createElement("li");
                li.textContent = ofi.cNomOficina;
                li.addEventListener("click", () => {
                    document.getElementById("nombreOficina").value = ofi.cNomOficina;
                    document.getElementById("iCodOficinaHidden").value = ofi.iCodOficina;
                    ul.innerHTML = "";
                    ul.style.display = "none";
                    cargarTrabajadores();
                });
                ul.appendChild(li);
            });
            ul.style.display = "block";
        });
}

document.addEventListener("click", (e) => {
    const ul = document.getElementById("sugerenciasOficina");
    const input = document.getElementById("nombreOficina");
    if (!ul.contains(e.target) && e.target !== input) {
        ul.style.display = "none";
    }
});
</script>
</body>
</html>
