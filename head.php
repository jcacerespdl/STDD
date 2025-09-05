<?php
include_once("conexion/conexion.php");
global $cnx;
date_default_timezone_set('America/Lima');
// Iniciar sesión si aún no está iniciada
 
    session_start();
    if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
        header("Location: index.php?alter=6");
        exit();
    }

// Consulta para obtener el nombre del perfil
$nombrePerfil = $_SESSION['cDescPerfil'] ?? '';
$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodOficina = $_SESSION['iCodOficinaLogin'] ?? null;

// Consulta para obtener el nombre completo del trabajador
$sqlTrabajadores = "SELECT cNombresTrabajador, cApellidosTrabajador FROM TRA_M_Trabajadores WHERE iCodTrabajador = " . $_SESSION['CODIGO_TRABAJADOR'];
$resultTrabajadores = sqlsrv_query($cnx, $sqlTrabajadores);
if ($resultTrabajadores && $rowTrabajador = sqlsrv_fetch_array($resultTrabajadores, SQLSRV_FETCH_ASSOC)) {
    $nombreCompleto = $rowTrabajador['cNombresTrabajador'] . " " . $rowTrabajador['cApellidosTrabajador'];
} else {
    $nombreCompleto = "";
}

// Consulta para obtener el nombre de la oficina
$sqlOficinas = "SELECT cNomOficina FROM Tra_M_Oficinas WHERE iCodOficina = " . $_SESSION['iCodOficinaLogin'];
$resultOficinas = sqlsrv_query($cnx, $sqlOficinas);
if ($resultOficinas && $rowOficina = sqlsrv_fetch_array($resultOficinas, SQLSRV_FETCH_ASSOC)) {
    $nombreOficina = $rowOficina['cNomOficina'];
} else {
    $nombreOficina = "";
}

 // Total de pendientes
// $sqlPendientes = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Movimientos M
// INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M.iCodTramite
// WHERE M.iCodOficinaDerivar = ? 
//   AND T.nFlgEnvio = 1
//   AND NOT EXISTS (
//       SELECT 1 FROM Tra_M_Tramite_Movimientos M2
//       WHERE M2.iCodMovimientoDerivo = M.iCodMovimiento
//   )";
// $stmtPendientes = sqlsrv_query($cnx, $sqlPendientes, [$iCodOficina]);
// $pendientes = sqlsrv_fetch_array($stmtPendientes)['total'] ?? 0;

// Total de documentos por aprobar
// $sqlPorAprobar = "SELECT COUNT(*) AS total FROM Tra_M_Tramite 
// WHERE iCodOficinaRegistro = ? 
//   AND (nFlgFirma = 0 OR nFlgFirma IS NULL)
//   AND nFlgEstado = 1
//   AND documentoElectronico IS NOT NULL";
// $stmtPorAprobar = sqlsrv_query($cnx, $sqlPorAprobar, [$iCodOficina]);
// $porAprobar = sqlsrv_fetch_array($stmtPorAprobar)['total'] ?? 0;

// Total de documentos para firma
// $sqlParaFirma = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma F
// INNER JOIN Tra_M_Tramite T ON T.iCodTramite = F.iCodTramite
// WHERE F.iCodTrabajador = ? 
//   AND F.nFlgFirma = 0 
//   AND F.nFlgEstado = 1 
//   AND T.nFlgEstado = 1";
// $stmtParaFirma = sqlsrv_query($cnx, $sqlParaFirma, [$iCodTrabajador]);
// $paraFirma = sqlsrv_fetch_array($stmtParaFirma)['total'] ?? 0;


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title> Sistema de Gestión Documental </title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="includes/lytebox.css">
    <script src="includes/lytebox.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    :root {
        --primary: #005a86; 
        --secondary: #c69157; 
        --gris-claro: #e2e2e2; /* Gris claro navbar */
        --light: #f8f9fa; 
        --dark: #343a40;
        --font-sm: 0.875rem;   
        --font-md: 0.95rem; 
        --font-lg: 1.125rem;   
        --danger: #dc3545; 
        --stick-top: 105px;    /* 75 (header fijo) + 30 (navbar fija) */
  --barra-altura: 44px;  /* alto aprox. de la barra .barra-titulo */
    }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
            /* -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale; */
        }

        .header-bar {
            background: white;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* sombra gris muy ligera */
                }

        .header-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

        .header-left img {
            height: 75px;
    width: auto;
     
    object-fit: contain;
        }

        .header-right {
        display: flex;
        align-items: center;
        gap: 10px;
        }

        .header-right .user-info {
        text-align: right;
        }

        .header-right .user-name,
        .header-right .user-role {
        color: var(--primary);
        }

        .system-title {
      color: var(--primary);
      font-size: 1.1rem;
      font-weight: 600;
    }
         /* Estilos del Navbar */
         .navbar {
            background: linear-gradient(to bottom, #f1f1f1, #dcdcdc); /* efecto metálico */
  height: 30px;
  padding: 0 20px;
  display: flex;
  align-items: center;
  position: fixed;
  top: 75px;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* misma sombra ligera que header */
         }

        .navbar:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            height: 45px;
            margin-right: 2rem;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            
            justify-content: flex-start;
        }

        .nav-item {
            position: relative;
            padding: 0 8px;
            font-size: 0.8rem;
            line-height: 1;
            height: 100%;
            display: flex;
            align-items: center;
            color: var(--primary);
        }

        .nav-item:hover {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }

        .nav-item::after {
            content: '▾';
            margin-left: 0.5rem;
            font-size: 0.8em;
            opacity: 0.7;
        }

        .nav-item:last-child::after {
            display: none;
        }

        .nav-item:hover .submenu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .submenu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 280px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 3px;
            padding: 0.5rem 0;
        }

        .submenu-item {
            padding: 0.75rem 1.5rem;
            display: block;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: var(--font-sm);
            font-weight: 500;
        }

        .submenu-item:hover {
            background: rgba(0, 90, 134, 0.1);
            color: var(--primary);
            padding-left: 2rem;
        }
       
        
       
        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(0, 90, 134, 0.1);
        }

        .user-info {
            margin-right: 0.75rem;
            text-align: right;
        }

        .user-name {
            font-size: var(--font-md);
            font-weight: 600;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: var(--font-sm);
            color: white;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            width: 250px;
            overflow: hidden;
            z-index: 2001;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .profile-dropdown.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: rgba(0, 90, 134, 0.1);
            color: var(--primary);
            padding-left: 2rem;
        }

        .dropdown-item i {
            color: var(--primary);
            font-size: 1.25rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover i {
            color: var(--secondary);
            transform: scale(1.1);
        }

        .profile-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 16px;
            height: 16px;
            background: white;
            transform: rotate(45deg);
            box-shadow: -3px -3px 5px rgba(0, 0, 0, 0.04);
        }

        /* Contenido principal */
        .container {
            width: 95vw;             /* toma 90% del ancho de la ventana */
            max-width: 2400px;       /* límite superior para evitar que se expanda mucho */
            min-width: 800px;        /* límite inferior para mantener legibilidad */
            margin: 0 auto;
            padding-inline: 40px;
            padding-top: 0.5rem;
            animation: fadeIn 0.5s ease-out;
        }

        .input-container {
          position: relative;
          flex: 1;
          min-width: 250px;
        }

        .input-container input {
          width: 100%;
          padding: 20px 40px 8px 12px;
          font-size: 15px;
          border: 1px solid #ccc;
          border-radius: 4px;
          background: #fff;
          box-sizing: border-box;
          transition: background-color 0.3s;
        }

        .input-container label {
          position: absolute;
          top: 20px;
          left: 12px;
          font-size: 14px;
          color: #666;
          padding: 0 4px;
          pointer-events: none;
          transform: translateY(-50%);
          transition: all 0.2s ease-in-out;
          background: transparent;
        }

        .input-container label {
          background: #f0f0f0;
        }

        .input-container input:disabled {
          background-color: #f0f0f0;
          cursor: not-allowed;
        }

        .input-container input:enabled + label {
          background: #fff;
        }

        .input-container input:focus + label,
        .input-container input:not(:placeholder-shown) + label {
          top: 0px;
          font-size: 12px;
          color: #333;
        }
        
        .input-container.select-flotante {
          position: relative;
        }

        .input-container.select-flotante select {
          width: 100%;
          padding: 20px 12px 8px;
          font-size: 15px;
          border: 1px solid #ccc;
          border-radius: 4px;
          background: #fff;
          box-sizing: border-box;
          appearance: none;
          -webkit-appearance: none;
          -moz-appearance: none;
          color: #000;
        }

        /* Asegura que el label esté en la misma posición que en inputs */
        .input-container.select-flotante label {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: 14px;
            color: #666;
            background-color: #fff;
            padding: 0 4px;
            pointer-events: none;
            transition: 0.2s ease all;
          }

          /* Hace flotar el label al seleccionar o al hacer focus */
          .input-container.select-flotante select:focus + label,
          .input-container.select-flotante select:valid + label {
            top: 0;
            font-size: 12px;
            color: #333;
            transform: translateY(-50%);
          }

          /* Color gris cuando no se ha seleccionado nada */
          .input-container.select-flotante select:required:invalid {
            color: #aaa;
          }

        .titulo-principal {
            color: var(--primary);
            font-size: 22px;
            font-weight: bold;
            margin: 30px 0 20px 0px; /* top - right - bottom - left */
             
        }

        .card {
            width: 100%;                    /* ✅ ocupar todo el ancho del container */
            box-sizing: border-box;         /* ✅ que el padding y border no lo desborden */
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .card-title {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }

        /* Formulario */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1.25rem;
            border: 2px solid var(--light);
            border-radius: 8px;
            background: var(--light);
            font-size: var(--font-md);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 2px rgba(0, 90, 134, 0.2);
        }

        /* Agregar estilos para el layout de dos columnas */
        .form-row {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .form-col {
            flex: 1;
        }

        /* Tabla */
        .table-container {
            margin: 2rem 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
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

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-brand {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-profile {
                margin-top: 1rem;
                width: 100%;
            }

            .profile-dropdown {
                width: 100%;
            }
        }

        /* Animaciones */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }


            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estilos para botones */
        .btn-primary,
        .btn-secondary,
        .btn-success {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: var(--font-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #004a6e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: black;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background-color: #e6a840;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Estilo para el contenedor de input file */
        .file-input {
            position: relative;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .file-input input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Animación al hacer click */
        .btn-primary:active,
        .btn-secondary:active,
        .btn-success:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Actualización de fuentes generales */
        h1 {
            font-size: 2rem; /* más visible */
            font-weight: 700;
            margin-top: 1.5rem; /* 🚀 separarlo del navbar */
            margin-bottom: 1rem; /* espacio hacia el formulario */
            color: var(--primary); /* azul institucional */
        }

        label {
            font-size: var(--font-md);
            font-weight: 500;
        }

        /* Estilos para el card del formulario */
        .form-card {
            background: linear-gradient(to right bottom, #ffffff, #f8f9fa);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 90, 134, 0.1);
            margin-bottom: 2rem;
            padding-bottom: 60px; 
        }

        .form-card h2 {
            color: var(--primary);
            font-size: var(--font-lg);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        body {
        margin-top: 75px; /* 45 (header) + 30 (navbar) */
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
    width: 300px;                 /* Ancho fijo */
    max-width: 300px;
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
    width: 220px; /* suficiente para mostrar algo antes de los puntos suspensivos */
}

.input-container textarea.form-textarea {
  width: 100%;
  height: 140px; /* cuadriplicado */
  padding: 20px 12px 8px;
  font-size: 15px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  box-sizing: border-box;
  resize: none;
}

.input-container textarea.form-textarea:focus {
  border-color: var(--primary);
  background: #fff;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 90, 134, 0.2);
}

.input-container textarea.form-textarea:focus + label,
.input-container textarea.form-textarea:not(:placeholder-shown) + label {
  top: 0px;
  font-size: 12px;
  color: #333;
}

.input-container label {
  position: absolute;
  top: 20px;
  left: 12px;
  font-size: 14px;
  color: #666;
  padding: 0 4px;
  pointer-events: none;
  transition: all 0.2s ease-in-out;
  background: white;
}
.input-container textarea.relleno + label {
  top: 0px;
  font-size: 12px;
  color: #333;
}

.input-container.prioridad-reducida {
            flex: 0.5;
            min-width: 120px;
        }
        .input-container.oficina-ancha {
            flex: 2;
        }
        .sugerencias-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 180px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ccc;
            z-index: 1000;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 14px;
            display: none;
            }

            .sugerencia-item {
            padding: 8px 10px;
            cursor: pointer;
            }

            .sugerencia-item:hover {
            background-color: #f0f0f0;
            }
    </style>
</head>
 
<body>
  <!-- HEADER INSTITUCIONAL -->
  <header class="header-bar">
    <div class="header-left">
      <img src="img/logo.jpeg" alt="Logo Horizontal"> 
      <span class="system-title">Sistema de Trámite Documentario Digital</span>
    </div>

    <div class="header-right user-profile" onclick="toggleDropdown()">
      <div class="user-info">
        <div class="user-name"><?php echo $nombreCompleto; ?></div>
        <div class="user-role"><?php echo $nombrePerfil . ' - ' . $nombreOficina; ?></div>
      </div>
      <span class="material-icons">account_circle</span>
      <!-- Dropdown -->
      <div class="profile-dropdown" id="profileDropdown">
        <!-- <a href="main.php" class="dropdown-item"><i class="material-icons">home</i> Principal</a>
        <a href="perfil.php" class="dropdown-item"><i class="material-icons">person</i> Perfil</a>
        <a href="acceso.php" class="dropdown-item"><i class="material-icons">vpn_key</i> Acceso</a> -->
        <a href="logout.php" class="dropdown-item"><i class="material-icons">exit_to_app</i> Salir</a>
      </div>
    </div>
  </header>

  <!-- NAVBAR FUNCIONAL -->
  <nav class="navbar">
     <div class="nav-menu">
            <?php if ($_SESSION['iCodPerfilLogin'] == 1): ?>
                <div class="nav-item">MANTENIMIENTO
                <div class="submenu">
                    <a href="mantenimientoOficinas.php" class="submenu-item">Oficinas</a>
                    <a href="mantenimientoTrabajadores.php" class="submenu-item">Trabajadores</a>
                </div>
                </div>
            <?php else: ?>

            <div class="nav-item">REGISTRO
                <div class="submenu">
                    <a href="registroOficina.php" class="submenu-item">Redactar Nuevo</a>
                    <?php if ($_SESSION['iCodOficinaLogin'] == 236): ?>
                    <a href="mesadepartes.php" class="submenu-item">Registrar Doc. Mesa de Partes Físico</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-item">RECIBIDOS
                <div class="submenu">
                    <?php if ($_SESSION['iCodPerfilLogin'] == 3 || $_SESSION['iCodPerfilLogin'] == 19): ?>
                    <a href="BandejaPendientes.php" class="submenu-item">Pendientes</a>
                    <?php endif; ?>
                    <?php if ($_SESSION['iCodPerfilLogin'] == 4): ?>
                    <a href="bandejaProfesional.php" class="submenu-item">Bandeja Profesional</a>
                    <?php endif; ?>

                    <?php if ($_SESSION['iCodPerfilLogin'] == 3):  ?>
                    <a href="bandejaPorAprobar.php" class="submenu-item">Documentos por Aprobar</a>
                    
                    <?php endif; ?>
                    
                    <a href="BandejaFirma.php" class="submenu-item">Docs. para Visto Bueno y Firma</a>
                    <!-- <a href="BandejaObservados.php" class="submenu-item">Documentos Observados</a> -->
                    <a href="BandejaFinalizados.php" class="submenu-item">Documentos Finalizados</a>
                    <!-- <a href="BandejaEspecial.php" class="submenu-item">Bandeja Especial</a> -->
                </div>
            </div>

            <div class="nav-item">ENVIADOS
                <div class="submenu">
                <a href="BandejaEnviados.php" class="submenu-item">Doc. Internos Enviados </a>
                </div>
            </div>

            <?php if ($_SESSION['iCodOficinaLogin'] == 46): ?>
            <div class="nav-item">DASHBOARDS
                <div class="submenu">
                    <a href="dashboardRequerimientos.php" class="submenu-item">Dashboard Requerimientos</a>
                    
                </div>
            </div>
            <?php endif; ?>
            
            <div class="nav-item">VALIDACIÓN
                <div class="submenu">
                <a href="https://apps.firmaperu.gob.pe/web/validador.xhtml" class="submenu-item" target="_blank">Validar Firmas</a>
                </div>
            </div>
        <?php endif; ?>
     </div>
   </nav>
<!-- </body>
</html> -->


    <script>
        // Función para mostrar/ocultar el dropdown
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }
        // Cerrar el dropdown al hacer clic fuera
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('profileDropdown');
            const userProfile = document.querySelector('.user-profile');
            if (!userProfile.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        setInterval(() => {
                fetch('verificar_sesion.php')
                    .then(res => res.json())
                    .then(data => {
                        if (!data.activa) {
                            alert("🔒 Su sesión ha expirado. Por favor vuelva a iniciar sesión.");
                            window.location.href = "index.php?alter=6";
                        }
                    });
            }, 60000); // 60 segundos



 
        
    </script>
