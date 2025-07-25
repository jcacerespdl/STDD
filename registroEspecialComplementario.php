<?php
include("conexion/conexion.php");
session_start();
global $cnx;

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodDigital = $_GET['iCodDigital'] ?? null;

if (!$iCodTramite || !$iCodDigital) {
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
    <title>Asignar Tipo Complementario</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fc;
            margin: 20px;
        }

        .form-card {
            max-width: 600px;
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

        select, button {
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

        p {
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="form-card">
    <h2>Asignar Tipo de Complementario</h2>
    <p><strong>Documento:</strong> <?= htmlspecialchars($cDescripcion) ?></p>
    <form method="POST">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="hidden" name="iCodDigital" value="<?= $iCodDigital ?>">

        <label for="tipo">Tipo de Complementario</label>
        <select name="tipo" id="tipo" required>
            <option value="">Seleccione</option>
            <option value="1" <?= $cTipoActual == 1 ? 'selected' : '' ?>>Pedido SIGA</option>
            <option value="2" <?= $cTipoActual == 2 ? 'selected' : '' ?>>TDR o ETT</option>
            <option value="3" <?= $cTipoActual == 3 ? 'selected' : '' ?>>Solicitud de Crédito Presupuestario</option>
            <option value="4" <?= $cTipoActual == 4 ? 'selected' : '' ?>>Aprobación de Crédito Presupuestario</option>
            <option value="5" <?= $cTipoActual == 5 ? 'selected' : '' ?>>Orden de Servicio</option>
            <option value="6" <?= $cTipoActual == 6 ? 'selected' : '' ?>>Orden de Compra</option>
        </select>

        <button type="submit" name="guardar">Guardar y Asignar Firmantes</button>
    </form>
</div>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $tipo = (int) $_POST['tipo'];

    // Actualizar tipo complementario
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite_Digitales SET cTipoComplementario = ? WHERE iCodTramite = ? AND iCodDigital = ?", [$tipo, $iCodTramite, $iCodDigital]);

    // Asignar firmantes automáticamente
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
        // Paso 1: Buscar el movimiento asociado a este trámite derivado
        $sqlMov = "SELECT TOP 1 iCodMovimiento FROM Tra_M_Tramite_Movimientos WHERE iCodTramiteDerivar = ?";
        $stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramiteDerivar]);
        if (!$stmtMov || !($rowMov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC))) return null;
    
        $iCodMovimiento = $rowMov['iCodMovimiento'];
    
        // Paso 2: Obtener iCodTramite del movimiento original
        $sqlTramite = "SELECT iCodTramite FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
        $stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodMovimiento]);
        if (!$stmtTramite || !($rowTramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC))) return null;
    
        $iCodTramiteOriginal = $rowTramite['iCodTramite'];
    
        // Paso 3: Obtener oficina que registró el trámite original
        $sqlOfi = "SELECT iCodOficinaRegistro FROM Tra_M_Tramite WHERE iCodTramite = ?";
        $stmtOfi = sqlsrv_query($cnx, $sqlOfi, [$iCodTramiteOriginal]);
        if (!$stmtOfi || !($rowOfi = sqlsrv_fetch_array($stmtOfi, SQLSRV_FETCH_ASSOC))) return null;
    
        $iCodOficinaRegistro = $rowOfi['iCodOficinaRegistro'];
    
        // Paso 4: Obtener su oficina padre
        $sqlPadre = "SELECT iCodOficina_Padre FROM Tra_M_Oficinas WHERE iCodOficina = ?";
        $stmtPadre = sqlsrv_query($cnx, $sqlPadre, [$iCodOficinaRegistro]);
        if ($stmtPadre && ($rowPadre = sqlsrv_fetch_array($stmtPadre, SQLSRV_FETCH_ASSOC))) {
            return $rowPadre['iCodOficina_Padre'];
        }
    
        return null;
    }
    

    $iCodOficinaSession = $_SESSION['iCodOficinaLogin'];

    switch ($tipo) {
        case 1: // Pedido SIGA
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $oficinaBase = end($jerarquia);
            $oficinaTop  = reset($jerarquia);
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaBase, 1, 'P');
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'Q');
            break;
        case 2: // TDR o ETT
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $nivelActual = count($jerarquia) - 1;
            $oficinaTop = $jerarquia[0];
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'A');
            if ($nivelActual >= 1) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual], 0, 'B');
            if ($nivelActual >= 2) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 1], 0, 'C');
            if ($nivelActual >= 3) asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $jerarquia[$nivelActual - 2], 0, 'D');
            break;
        case 3: // Solicitud de crédito presupuestario
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'R'); // Jefe Logística
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'S');  // Jefe OPP
            break;
        case 4: // Aprobación de crédito presupuestario
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 71, 1, 'U');  // Jefe OPP
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 23, 0, 'T');  // Jefe Presupuesto (VB)
            break;
        case 5: // Orden de Servicio para Servicios
            asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, 4, 3, 0, 'W'); // Profesional adquisiciones (VB)
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 3, 1, 'X');       // Jefe adquisiciones
            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'Y');     // Jefe logística
            $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
            $oficinaTop = reset($jerarquia);
            $iCodOficinaPadreGeneradora = obtenerOficinaPadreGeneradora($cnx, $iCodTramite);

            asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $iCodOficinaPadreGeneradora, 1, 'Z');
            break;
        case 6: // Orden de Compra
                asignarFirmantePorPerfil($cnx, $iCodTramite, $iCodDigital, 4, 3, 0, 'W'); // Profesional adquisiciones (VB)
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 3, 1, 'X');       // Jefe adquisiciones
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 112, 1, 'Y');     // Jefe logística
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, 4, 1, 'Z');      // Jefe almacén
                break;
    }

    echo "<script>alert('Firmantes asignados correctamente.'); window.close();</script>";
    exit;
}
?>
