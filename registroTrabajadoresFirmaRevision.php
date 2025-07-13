<?php
session_start();
include_once("./conexion/conexion.php");

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    header("Location: index.php?error=No tiene sesión activa.");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['firmantesJson'])) {
    $firmantesData = json_decode($_POST['firmantesJson'], true);

    foreach ($firmantesData as $firmante) {
        $iCodTramite = intval($firmante['iCodTramite']);
        $iCodDigital = intval($firmante['iCodDigital']);
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
            $sqlInsert = "INSERT INTO Tra_M_Tramite_Firma (
                iCodTramite, iCodDigital, iCodTrabajador, iCodOficina,
                nFlgFirma, nFlgEstado, posicion, tipoFirma
            ) VALUES (?, ?, ?, ?, 0, 1, ?, ?)";
            $paramsInsert = [$iCodTramite, $iCodDigital, $idTrab, $iCodOficina, $posicion, $tipoFirma];
            sqlsrv_query($cnx, $sqlInsert, $paramsInsert);
        }
    }

    echo "<script>alert('Firmantes insertados correctamente para todos los complementarios seleccionados.'); window.close();</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Firmantes - Revisión Masiva</title>
    <link rel="stylesheet" href="css/tramite.css">
    <style>
        .input-container {
            position: relative;
            margin-bottom: 15px;
            max-width: 400px;
        }
        .input-container input {
            width: 100%;
            padding: 10px;
        }
        .input-container label {
            position: absolute;
            left: 12px;
            top: 10px;
            background: white;
            color: #666;
            transition: 0.2s;
            pointer-events: none;
        }
        .input-container input:focus + label,
        .input-container input:not(:placeholder-shown) + label {
            top: -10px;
            font-size: 11px;
            color: #364897;
            padding: 0 4px;
        }
        .popup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .popup-table th, .popup-table td {
            border: 1px solid #ccc;
            padding: 6px;
        }
        .sugerencias {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ccc;
            width: 100%;
            z-index: 999;
            max-height: 150px;
            overflow-y: auto;
        }
        .sugerencias div {
            padding: 6px;
            cursor: pointer;
        }
        .sugerencias div:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
 
<form method="POST">
    <input type="hidden" name="firmantesJson" id="firmantesJson">

    <div class="input-container">
        <input type="text" id="oficinaInput" placeholder=" " autocomplete="off">
        <label for="oficinaInput">Oficina</label>
        <input type="hidden" id="iCodOficinaHidden" name="iCodOficina">
        <div id="sugerenciasOficina" class="sugerencias"></div>
    </div>

    <!-- Selección tipo de firma -->
    <div class="grupo-firma" style="margin-bottom: 10px;">
        <label>Tipo de Firma:</label><br>
        <input type="radio" name="tipoFirmaGrupo" value="1" checked onclick="mostrarOpcionesFirma(this)"> Firma Principal
        <input type="radio" name="tipoFirmaGrupo" value="0" onclick="mostrarOpcionesFirma(this)"> Visto Bueno
    </div>

    <!-- Firma principal: A o posición especial -->
    <div id="bloquePrincipal" style="margin-bottom:10px;">
        <label>¿Qué tipo de posición para Firma Principal?</label><br>
        <input type="radio" name="subtipoPrincipal" value="A" checked onclick="seleccionarPosicionPrincipal()"> Posición Convencional (A)<br>
        <input type="radio" name="subtipoPrincipal" value="especial" onclick="seleccionarPosicionPrincipal()"> Otra Posición Especial<br>

        <div id="posicionesEspecialesPrincipal" style="margin-top: 5px; display:none;">
            <label for="posicionEspecialPrincipal">Seleccione:</label>
            <select id="posicionEspecialPrincipal">
                <option value="">-- Seleccione --</option>
                <option value="P">P - Área en pedido SIGA</option>
                <option value="Q">Q - Unidad en pedido SIGA</option>
                <option value="R">R - Jefe Logística CCP</option>
                <option value="S">S - Jefe OPP CCP</option>
                <option value="U">U - Jefe OPP Aprobación</option>
                <option value="V">V - Anexo 7 o 10</option>
                <option value="X">X - Jefe de Adquisiciones</option>
                <option value="Y">Y - Jefe de Logística OS</option>
                <option value="Z">Z - Área Usuaria OS</option>
            </select>
        </div>
    </div>

    <!-- Firma Visto Bueno -->
    <div id="bloqueVB" style="margin-bottom:10px; display: none;">
        <label for="posicionVB">Posición para Visto Bueno:</label>
        <select id="posicionVB">
            <option value="">-- Seleccione --</option>
            <option value="B">B - 1 DE ABAJO HACIA ARRIBA</option>
            <option value="C">C - 2 DE ABAJO HACIA ARRIBA</option>
            <option value="D">D - 3 DE ABAJO HACIA ARRIBA</option>
            <option value="E">E - 4 DE ABAJO HACIA ARRIBA</option>
            <option value="F">F - 5 DE ABAJO HACIA ARRIBA</option>
            <option value="G">G - 6 DE ABAJO HACIA ARRIBA</option>
            <option value="H">H - 7 DE ABAJO HACIA ARRIBA</option>
            <option value="I">I - 8 DE ABAJO HACIA ARRIBA</option>
            <option value="J">J - 9 DE ABAJO HACIA ARRIBA</option>
            <option value="K">K - 10 DE ABAJO HACIA ARRIBA</option>
            <option value="T">T - VB Jefe Presupuesto CCP</option>
            <option value="W">W - VB Profesional Adquisiciones</option>
        </select>
    </div>

    <!-- Leyenda informativa -->
    <div style="margin-top: 20px; display: flex; align-items: flex-start; gap: 20px; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">
        <div>
            <strong>📌 Guía de Posiciones de Firma:</strong><br>
            A = posición convencional para Firma Principal<br>
            B - K = posiciones para Vistos Buenos (de abajo hacia arriba)<br>
            P = Área en pedido SIGA<br>
            Q = Unidad en pedido SIGA<br>
            R = Jefe Logística en Solicitud CCP<br>
            S = Jefe OPP en Solicitud CCP<br>
            T = VB Jefe de Presupuesto en CCP<br>
            U = Jefe OPP en Aprobación CCP<br>
            V = Firma Principal Anexo 7 o 10<br>
            W = VB Profesional Adquisiciones (OS)<br>
            X = Jefe de Adquisiciones (OS)<br>
            Y = Jefe de Logística (OS)<br>
            Z = Área Usuaria (OS)
        </div>
            <img src="img/posicion_firmas.png" alt="Guía Visual de Posiciones" style="max-height: 300px;">
        <div>
            
        </div>
    </div>
    <!-- Tabla de trabajadores -->
    <div>
        <table class="popup-table">
            <thead>
                <tr><th>Nombre completo</th><th>Perfil</th><th>Seleccionar</th></tr>
            </thead>
            <tbody id="tablaTrabajadores"></tbody>
        </table>
    </div>

    <!-- Lista de complementarios -->
    <div style="margin-top: 20px">
        <h3>Complementarios Seleccionados:</h3>
        <div id="listaSeleccionados">Cargando...</div>
    </div>

    <!-- Botón de envío -->
    <div style="margin-top: 20px">
        <button type="submit" class="FormSubmitReg">Guardar Firmantes para Todos</button>
    </div>
</form>

<script>
const seleccionados = JSON.parse(localStorage.getItem('complementariosSeleccionados') || '[]');
let firmantes = [];

function mostrarOpcionesFirma(radio) {
    const bloquePrincipal = document.getElementById("bloquePrincipal");
    const bloqueVB = document.getElementById("bloqueVB");

    if (radio.value === "1") {
        bloquePrincipal.style.display = 'block';
        bloqueVB.style.display = 'none';
    } else {
        bloquePrincipal.style.display = 'none';
        bloqueVB.style.display = 'block';
    }
}

function seleccionarPosicionPrincipal() {
    const especialChecked = document.querySelector('input[name="subtipoPrincipal"][value="especial"]').checked;
    document.getElementById("posicionesEspecialesPrincipal").style.display = especialChecked ? 'block' : 'none';
}

function renderLista() {
    fetch("get_datos_complementarios.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(seleccionados)
    })
    .then(res => res.json())
    .then(data => {
        let html = '<ul>';
        data.forEach(item => {
            html += `<li><strong>Expediente:</strong> ${item.expediente}<br>
                     <strong>Asunto:</strong> ${item.asunto}<br>
                     <strong>Archivo:</strong> ${item.descripcion}</li><br>`;
        });
        html += '</ul>';
        document.getElementById("listaSeleccionados").innerHTML = html;
    });
}

function cargarTrabajadores() {
    const idOficina = document.getElementById("iCodOficinaHidden").value;
    if (!idOficina) return;

    fetch(`ajax_trabajadores_por_oficina.php?iCodOficina=${idOficina}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById("tablaTrabajadores");
            tbody.innerHTML = "";
            data.forEach(t => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td>${t.nombre} ${t.apellidos}</td>
                    <td>${t.perfil}</td>
                    <td><input type="checkbox" onchange="seleccionarFirmante(this, '${t.id}', '${t.nombre}', '${t.apellidos}', '${t.iCodOficina}', '${t.oficinaNombre}')"></td>
                `;
                tbody.appendChild(row);
            });
        });
}

function seleccionarFirmante(checkbox, id, nombre, apellidos, oficinaId, oficinaNombre) {
    if (!checkbox.checked) return;

    const tipoFirma = document.querySelector('input[name="tipoFirmaGrupo"]:checked').value;
    let posicion = null;

    if (tipoFirma === "1") {
        const subtipo = document.querySelector('input[name="subtipoPrincipal"]:checked').value;
        if (subtipo === "A") {
            posicion = "A";
        } else {
            posicion = document.getElementById("posicionEspecialPrincipal").value;
            if (!posicion) {
                alert("Seleccione una posición especial para Firma Principal.");
                checkbox.checked = false;
                return;
            }
        }
    } else {
        posicion = document.getElementById("posicionVB").value;
        if (!posicion) {
            alert("Seleccione una posición para Visto Bueno.");
            checkbox.checked = false;
            return;
        }
    }

    seleccionados.forEach(c => {
        firmantes.push({
            iCodTramite: c.iCodTramite,
            iCodDigital: c.iCodDigital,
            id, nombre, apellidos,
            iCodOficina: oficinaId,
            oficinaNombre,
            posicion, tipoFirma,
            descripcion: c.descripcion || ''
        });
    });

    actualizarListaFirmantes();
}

function actualizarListaFirmantes() {
    const contenedor = document.getElementById("listaSeleccionados");
    let html = "<table class='popup-table'><thead><tr><th>Archivo</th><th>Nombre</th><th>Apellidos</th><th>Oficina</th><th>Posición</th><th>Tipo Firma</th><th>Acción</th></tr></thead><tbody>";

    firmantes.forEach((f, i) => {
        html += `<tr>
            <td>${f.descripcion}</td>
            <td>${f.nombre}</td>
            <td>${f.apellidos}</td>
            <td>${f.oficinaNombre}</td>
            <td>${f.posicion}</td>
            <td>${f.tipoFirma == '1' ? 'Firma Principal' : 'Visto Bueno'}</td>
            <td><button type="button" onclick="eliminarFirmante(${i})">❌</button></td>
        </tr>`;
    });

    html += "</tbody></table>";
    contenedor.innerHTML = html;
    document.getElementById("firmantesJson").value = JSON.stringify(firmantes);
}

function eliminarFirmante(index) {
    firmantes.splice(index, 1);
    actualizarListaFirmantes();
}

// Autocompletado oficina
document.getElementById('oficinaInput').addEventListener('input', function () {
    const texto = this.value;
    if (texto.length < 2) return;

    fetch(`ajax_buscar_oficina.php?q=${encodeURIComponent(texto)}`)
        .then(res => res.json())
        .then(data => {
            const contenedor = document.getElementById('sugerenciasOficina');
            contenedor.innerHTML = '';
            data.forEach(of => {
                const div = document.createElement('div');
                div.textContent = of.cNomOficina;
                div.dataset.id = of.iCodOficina;
                div.addEventListener('click', () => {
                    document.getElementById('oficinaInput').value = of.cNomOficina;
                    document.getElementById('iCodOficinaHidden').value = of.iCodOficina;
                    contenedor.innerHTML = '';
                    cargarTrabajadores();
                });
                contenedor.appendChild(div);
            });
        });
});

function cargarFirmantesExistentes() {
    if (seleccionados.length === 0) return;

    fetch("get_firmantes_existentes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(seleccionados)
    })
    .then(res => res.json())
    .then(data => {
        data.forEach(f => {
            const yaExiste = firmantes.some(e =>
                e.iCodTramite == f.iCodTramite &&
                e.iCodDigital == f.iCodDigital &&
                e.id == f.id
            );
            if (!yaExiste) {
                firmantes.push({
                    iCodTramite: f.iCodTramite,
                    iCodDigital: f.iCodDigital,
                    id: f.id,
                    nombre: f.nombre,
                    apellidos: f.apellidos,
                    iCodOficina: f.iCodOficina,
                    oficinaNombre: f.oficinaNombre,
                    posicion: f.posicion,
                    tipoFirma: f.tipoFirma,
                    descripcion: f.descripcion
                });
            }
        });
        actualizarListaFirmantes();
    })
    .catch(err => console.error("Error al cargar firmantes ya existentes:", err));
}

document.addEventListener('click', function (e) {
    if (!document.getElementById("oficinaInput").contains(e.target)) {
        document.getElementById("sugerenciasOficina").innerHTML = '';
    }
});

window.addEventListener('DOMContentLoaded', () => {
    renderLista();
    cargarFirmantesExistentes();
    mostrarOpcionesFirma(document.querySelector("input[name='tipoFirmaGrupo']:checked"));
});
</script>
</body>
</html>
