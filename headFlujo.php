<?php
include_once("conexion/conexion.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Sistema de Gestión Documental</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #005a86;
            --secondary: #c69157;
            --gris-claro: #e2e2e2;
            --light: #f8f9fa;
            --dark: #343a40;
            --font-sm: 0.875rem;
            --font-md: 0.95rem;
            --font-lg: 1.125rem;
            --danger: #dc3545;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead {
            background-color: #f2f2f2;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th.checkbox-col, td.checkbox-col {
            text-align: center;
            width: 60px;
        }
        body {
            margin: 0;
            font-family: 'Montserrat', sans-serif;
        }
        .chip-adjunto {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    border-radius: 999px;
    padding: 6px 12px;
    margin: 4px 6px 4px 0;
    font-size: 13px;
    font-family: 'Segoe UI', sans-serif;
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    border: 1px solid #dadce0;
    transition: background 0.2s;
    text-decoration: none;
    color: black;
}
.chip-adjunto:hover {
    background-color: #e8eaed;
    text-decoration: none;
}
.material-icons.chip-icon {
    font-size: 18px;
    margin-right: 8px;
    vertical-align: middle;
    color: #d93025;
}
.material-icons.chip-doc {
    color: #1a73e8;
}
.chip-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    max-width: 180px;
}
.detail-content {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  font-family: 'Segoe UI', sans-serif;
  overflow: hidden;
}

.detail-content summary {
  background: #f7f9fc;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  transition: background 0.2s ease;
}

.detail-content summary:hover {
  background: #eef2f7;
}


.detail-content div {
  line-height: 1.6;
  font-size: 14px;
  color: #333;
}

.detail-content div > b {
  color: #1a1a1a;
  display: inline-block;
  min-width: 140px;
}
.detail-header {
  background: #f7f9fc;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 15px;
  border-bottom: 1px solid #eee;
} 
.detail-body {
  padding: 1rem;
  line-height: 1.6;
  font-size: 14px;
  color: #333;
}
.detail-body > b { /* si lo usas */
  color: #1a1a1a;
  display: inline-block;
  min-width: 140px;
}
table {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1rem;
}
/* separador vertical extra entre secciones clave */
.section { margin-top: 1.25rem; }
table thead {
  background: #f1f5f9;
  font-weight: bold;
}

table th, table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e6e6e6;
  text-align: left;
}

table tbody tr:hover {
  background-color: #f9fbfd;
}

table td {
  font-size: 13px;
  color: #444;
}
h3 {
  font-size: 18px;
  color: #0c2d5d;
  margin: 1.5rem 0 0.75rem 0;
  font-family: 'Segoe UI', sans-serif;
  border-left: 4px solid #0072CE;
  padding-left: 10px;
}
/* Dos columnas fijas para Doc. Principal y Complementarios */
.docs-grid{
  display:grid;
  grid-template-columns: 1fr 1fr; /* izquierda: principal | derecha: complementarios */
  gap:18px;
  align-items:start;
}
/* Fila label + chips en una misma línea */
.kv-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.kv-row > b{white-space:nowrap}
.kv-row > .chips-wrap{flex:1 1 auto} /* que los chips ocupen el resto del ancho */
    </style>
</head>
<body>
    <div style="text-align:center; margin-bottom: 10px;">
     </div>
