<?php
 
include_once("conexion/conexion.php");
session_start();

$iCodTramite = $_GET['iCodTramite'] ?? null;
if (!$iCodTramite) {
    die("Código de trámite no especificado.");
}
?>
<!DOCTYPE html>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Referencias</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { text-align: center; }
        .form-row { margin-bottom: 10px; }
        input[type="text"], select { width: 100%; padding: 6px; margin-top: 4px; }
        button { padding: 10px 20px; background-color: #35459c; color: white; border: none; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #2a367a; }
        .btn-danger { background-color: #a33; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        .center { text-align: center; }
    </style>
</head>
<body>

<h2>Seleccionar Referencias</h2>

<div class="form-row">
    <label><input type="radio" name="tipoDoc" value="1" checked> Externo</label>
    <label><input type="radio" name="tipoDoc" value="2"> Interno</label>
</div>

<div class="form-row">
    <input type="text" id="filtroAsunto" placeholder="Asunto">
</div>
<div class="form-row">
    <input type="text" id="filtroDocumento" placeholder="Documento Electrónico">
</div>

<div class="form-row center">
    <button onclick="buscarReferencias()">Buscar</button>
</div>

<div class="form-row" id="resultadosBusqueda">
    <!-- Resultados aquí -->
</div>

<hr>

<h3>Referencias agregadas</h3>
<div id="referenciasAgregadas">
    <?php
        $_GET['iCodTramite'] = $iCodTramite;
        include("listarReferenciasAgregadas.php");
    ?>
</div>

<div class="form-row center">
    <button onclick="finalizarReferencias()" style="margin-right: 10px;">Guardar</button>
    <button class="btn-danger" onclick="window.close()">Cerrar</button>
</div>

<script>
function buscarReferencias() {
    const tipo = document.querySelector('input[name="tipoDoc"]:checked').value;
    const asunto = document.getElementById("filtroAsunto").value.trim();
    const doc = document.getElementById("filtroDocumento").value.trim();
    const iCodTramite = <?= (int)$iCodTramite ?>;

    const params = new URLSearchParams({
        iCodTramite,
        tipo,
        asunto,
        doc
    });

    fetch('buscarReferenciaAvanzado.php', {
        method: 'POST',
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params.toString()
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("resultadosBusqueda").innerHTML = html;
    })
    .catch(err => {
        console.error("Error en búsqueda:", err);
        document.getElementById("resultadosBusqueda").innerHTML = "<p style='color:red;'>Error al buscar referencias</p>";
    });
}

function agregarReferencia(iCodRelacionado) {
    const iCodTramite = <?= (int)$iCodTramite ?>;

    fetch("insertarReferencia.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `iCodTramite=${iCodTramite}&iCodRelacionado=${iCodRelacionado}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            cargarReferenciasAgregadas();
        } else {
            alert("Error: " + (data.message || "No se pudo agregar la referencia."));
        }
    })
    .catch(err => {
        console.error("Error al insertar referencia:", err);
        alert("Ocurrió un error al insertar la referencia.");
    });
}

function cargarReferenciasAgregadas() {
    const iCodTramite = <?= (int)$iCodTramite ?>;
    fetch("listarReferenciasAgregadas.php?iCodTramite=" + iCodTramite)
    .then(res => res.text())
    .then(html => {
        document.getElementById("referenciasAgregadas").innerHTML = html;
    });
}

function finalizarReferencias() {
    if (window.opener && typeof window.opener.cargarReferenciasAgregadas === "function") {
        window.opener.cargarReferenciasAgregadas(); // actualiza visualmente en padre
    }
    window.close();
}
</script>
</body>
</html>
