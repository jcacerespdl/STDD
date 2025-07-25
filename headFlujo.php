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
    </style>
</head>
<body>
    <div style="text-align:center; margin-bottom: 10px;">
     </div>
