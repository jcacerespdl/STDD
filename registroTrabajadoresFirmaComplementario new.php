<?php 
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    header("Location: ../index.php?alter=5");
    exit();
}

include_once("./conexion/conexion.php");
global $cnx;

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodDigital = $_GET['iCodDigital'] ?? null;

if (!$iCodTramite || !$iCodDigital) {
    die("Faltan parámetros obligatorios.");
}

// === BLOQUE: GUARDAR NUEVO TIPO DE COMPLEMENTARIO Y ASIGNAR AUTOMÁTICAMENTE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignarTipoComplementario'])) {
    $tipo = (int) $_POST['tipo'];

    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Digitales SET cTipoComplementario = ? WHERE iCodTramite = ? AND iCodDigital = ?", [$tipo, $iCodTramite, $iCodDigital]);

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

        $sqlPadre = "SELECT iCodOficina_Padre FROM Tra_M_Oficinas WHERE iCodOficina = ?";
        $stmtPadre = sqlsrv_query($cnx, $sqlPadre, [$rowOfi['iCodOficinaRegistro']]);
        if ($stmtPadre && ($rowPadre = sqlsrv_fetch_array($stmtPadre, SQLSRV_FETCH_ASSOC))) {
            return $rowPadre['iCodOficina_Padre'];
        }

        return null;
    }

    $iCodOficinaSession = $_SESSION['iCodOficinaLogin'];

    switch ($tipo) {
        case 1: //PEDIDO SIGA
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $oficinaBase = end($jerarquia);
            $oficinaTop  = reset($jerarquia);
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaBase, 1, 'P');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'Q');
            break;
        // case 2:
        //     $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
        //     $nivelActual = count($jerarquia) - 1;
        //     $oficinaTop = $jerarquia[0];
        //     asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'A');
        //     if ($nivelActual >= 1) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual], 0, 'B');
        //     if ($nivelActual >= 2) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 1], 0, 'C');
        //     if ($nivelActual >= 3) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 2], 0, 'D');
        //     break;
        case 3: // Pre certi
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'R');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'S');
            break;
        case 4: // Aprobacion certi 
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'U');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 23, 0, 'T');
            break;
        case 5: // Orden de Servicio 
            asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, 4, 3, 0, 'W');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 3, 1, 'X');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'Y');
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $iCodOficinaPadreGeneradora = obtenerOficinaPadreGeneradora($cnx, $iCodTramite);
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $iCodOficinaPadreGeneradora, 1, 'Z');
            break;
        case 6: // Orden de Compra
            asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, 4, 3, 0, 'W');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 3, 1, 'X');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'Y');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 4, 1, 'Z');
            break;
    }

    echo "<script>alert('Firmantes asignados correctamente.'); window.location.href=window.location.href;</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Firmantes Complementario</title>
   <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link rel="stylesheet" href="stylespopups.css">
</head>
<body>
<?php
// Obtener nombre y tipo actual del documento
$sqlDoc = "SELECT cDescripcion, cTipoComplementario FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND iCodDigital = ?";
$stmtDoc = sqlsrv_query($cnx, $sqlDoc, [$iCodTramite, $iCodDigital]);
$docInfo = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);
$cDescripcion = $docInfo['cDescripcion'] ?? '';
$cTipoActual = $docInfo['cTipoComplementario'] ?? null;
?>

<!-- FORMULARIO PRINCIPAL -->
<form method="POST">
  <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
  <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">
  <input type="hidden" id="firmantesJson" name="firmantesJson">

  <!-- Selección tipo complementario -->
  <div class="form-card">
    <h2>Asignar Tipo de Complementario</h2>
    <p><strong>Documento:</strong> <?= htmlspecialchars($cDescripcion) ?></p>

    <?php
        $iOficinaLogin = $_SESSION['iCodOficinaLogin'];
        ?>
        <label for="tipo">Tipo de Complementario</label>
        <select name="tipo" id="tipo" required>
        <option value="">Seleccione</option>
        <option value="1" <?= $cTipoActual == 1 ? 'selected' : '' ?>>Pedido SIGA</option>
        <!-- <option value="2" <?= $cTipoActual == 2 ? 'selected' : '' ?>>TDR o ETT</option> -->
        <?php if ($iOficinaLogin == 112): ?>
            <option value="3" <?= $cTipoActual == 3 ? 'selected' : '' ?>>Solicitud de Crédito Presupuestario</option>
            <option value="5" <?= $cTipoActual == 5 ? 'selected' : '' ?>>Orden de Servicio</option>
            <option value="6" <?= $cTipoActual == 6 ? 'selected' : '' ?>>Orden de Compra</option>
        <?php endif; ?>
        <?php if ($iOficinaLogin == 23): ?>
            <option value="4" <?= $cTipoActual == 4 ? 'selected' : '' ?>>Aprobación de Crédito Presupuestario</option>
        <?php endif; ?>
        </select>


    <button type="submit" name="asignarTipoComplementario">Guardar y Asignar Firmantes</button>
  </div>

  <!-- Oficina flotante -->
  <div class="input-container select-flotante">
    <input type="text" id="nombreOficina" autocomplete="off"
           placeholder=" " onclick="buscarOficina('')" oninput="buscarOficina(this.value)"  >
    <label for="nombreOficina">Oficina</label>
    <input type="hidden" id="iCodOficinaHidden" name="iCodOficina">
    <ul id="sugerenciasOficina"></ul>
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

  <!-- Lista seleccionados -->
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

<!-- Firmantes ya registrados -->
<h3>Firmantes ya registrados:</h3>
<table class="popup-table">
  <thead>
    <tr>
      <th>Posición</th><th>Nombre</th><th>Oficina</th><th>Tipo Firma</th><th>Acción</th>
    </tr>
  </thead>
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
          <button type="submit" style="color:red; border:none; background:none; cursor:pointer;">❌</button>
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
const iCodTramite = <?= (int)$iCodTramite ?>;
const iCodDigital = <?= (int)$iCodDigital ?>;

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
            <td>
  <button class="btn-eliminar" onclick="eliminarSeleccionado('${t.id}')" title="Eliminar">
    <span class="material-icons">delete</span>
  </button>
</td>
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

 
const campoOficina = document.getElementById("nombreOficina");
const campoOficinaHidden = document.getElementById("iCodOficinaHidden");
const tablaTrabajadores = document.getElementById("tablaTrabajadores");
const eliminarButtons = document.querySelectorAll("form[method='POST'] button[type='submit']");

function bloquearPorTipo(bloquear) {
    campoOficina.disabled = bloquear;
    if (bloquear) {
        campoOficina.value = '';
        campoOficinaHidden.value = '';
        tablaTrabajadores.innerHTML = `<tr><td colspan="3"><em> </em></td></tr>`;
        eliminarButtons.forEach(btn => {
            if (btn.innerText.trim() === "❌") btn.disabled = true;
        });
    } else {
        campoOficina.disabled = false;
        tablaTrabajadores.innerHTML = "";
        eliminarButtons.forEach(btn => {
            if (btn.innerText.trim() === "❌") btn.disabled = false;
        });
    }
}

document.getElementById("tipo").addEventListener("change", function () {
    const tipo = parseInt(this.value);
    const bloquear = [3, 4, 5, 6].includes(tipo);
    bloquearPorTipo(bloquear);
});

// Ejecutar también al cargar si ya había un tipo seleccionado
window.addEventListener("DOMContentLoaded", () => {
    const tipoInicial = parseInt(document.getElementById("tipo").value);
    if ([3, 4, 5, 6].includes(tipoInicial)) {
        bloquearPorTipo(true);
    }
});
 

//ELIMINAR FIRMANTES ANTERIORES AL CAMBIAR TIPO
document.getElementById("tipo").addEventListener("change", function () {
    const tipo = parseInt(this.value);
    const bloquear = [3, 4, 5, 6].includes(tipo);

    // Llamar al servidor para eliminar firmantes anteriores
    fetch('eliminarFirmantesPorTipo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&iCodDigital=${iCodDigital}`
})
.then(res => res.text())
.then(res => {
    if (res.trim() === "ok") {
        document.querySelector("table.popup-table tbody").innerHTML = "<tr><td colspan='5'><em>Firmantes eliminados por cambio de tipo</em></td></tr>";
        document.getElementById("listaSeleccionados").innerHTML = "";
        document.getElementById("firmantesJson").value = "";
    } else {
        alert("Error al eliminar firmantes anteriores:\n" + res);
    }
});

    bloquearPorTipo(bloquear);
});


</script>
</body>
</html>
