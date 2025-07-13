<?php
include_once("conexion/conexion.php");
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$iCodTramite = $input['iCodTramite'] ?? null;
$iCodOficinaSession = $_SESSION['iCodOficinaLogin'] ?? null;
$iCodPerfil = $_SESSION['iCodPerfilLogin'] ?? null;

if (!$iCodTramite || !$iCodOficinaSession) {
    echo json_encode(["status" => "error", "message" => "Código de trámite u oficina no recibido"]);
    exit;
}

$fechaActual = date("Y-m-d H:i:s");
$iCodPerfil = $_SESSION['iCodPerfilLogin'] ?? null;

// 1. Actualizar T.fFecRegistro y T.FlgEstado SIEMPRE
$sqlTramite = "
    UPDATE Tra_M_Tramite 
    SET fFecRegistro = ?, nFlgEstado = 1 
    WHERE iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$fechaActual, $iCodTramite]);

if ($stmtTramite === false) {
    echo json_encode(["status" => "error", "message" => "Error al actualizar fFecRegistro y nFlgEstado en Tra_M_Tramite."]);
    exit;
}

//   Si es jefe, actualizar T.nFlgEnvio = 1
if ($iCodPerfil == 3) {
    $sqlEnvio = "UPDATE Tra_M_Tramite SET nFlgEnvio = 1 WHERE iCodTramite = ?";
    $stmtEnvio = sqlsrv_query($cnx, $sqlEnvio, [$iCodTramite]);

    if ($stmtEnvio === false) {
        echo json_encode(["status" => "error", "message" => "Error al actualizar nFlgEnvio."]);
        exit;
    }

    // También actualizar TM.fFecDerivar en movimientos
    $sqlMov = "UPDATE Tra_M_Tramite_Movimientos SET fFecDerivar = ? WHERE iCodTramite = ?";
    $stmtMov = sqlsrv_query($cnx, $sqlMov, [$fechaActual, $iCodTramite]);

    if ($stmtMov === false) {
        echo json_encode(["status" => "error", "message" => "Error al actualizar fFecDerivar."]);
        exit;
    }
}

    // Actualizar fecha de plazo en movimientos 
   
    if ($iCodPerfil == 3) {
        $sqlMovimientos = "
        SELECT iCodMovimiento, fFecDerivar, nTiempoRespuesta
        FROM Tra_M_Tramite_Movimientos
        WHERE iCodTramite = ?  
    ";
    $stmtMovimientos = sqlsrv_query($cnx, $sqlMovimientos, [$iCodTramite]);
        if ($stmtMovimientos !== false) {
            while ($mov = sqlsrv_fetch_array($stmtMovimientos, SQLSRV_FETCH_ASSOC)) {
                $iCodMovimiento = $mov['iCodMovimiento'];
                $fFecDerivar = $mov['fFecDerivar'];
                $nTiempoRespuesta = intval($mov['nTiempoRespuesta']);
        
                if ($fFecDerivar instanceof DateTime) {
                    // Calcular fecha plazo
                    $horaOriginal = $fFecDerivar->format('H:i:s');
                    $fFecPlazo = clone $fFecDerivar;
                    $fFecPlazo->modify("+{$nTiempoRespuesta} days");
                
                    // Asegurar misma hora
                    $fFecPlazo->setTime(
                        (int)$fFecDerivar->format('H'),
                        (int)$fFecDerivar->format('i'),
                        (int)$fFecDerivar->format('s')
                    );
        
                    // Actualizar fFecPlazo en el movimiento
                    $sqlUpdatePlazo = "UPDATE Tra_M_Tramite_Movimientos SET fFecPlazo = ? WHERE iCodMovimiento = ?";
                    sqlsrv_query($cnx, $sqlUpdatePlazo, [$fFecPlazo->format('Y-m-d H:i:s'), $iCodMovimiento]);
                }
            }
        }
    }  

// 2. Buscar complementarios con tipo especial definido
$sqlDocs = "SELECT iCodDigital, cTipoComplementario 
            FROM Tra_M_Tramite_Digitales 
            WHERE iCodTramite = ? AND cTipoComplementario IS NOT NULL AND cTipoComplementario > 0";
$stmtDocs = sqlsrv_query($cnx, $sqlDocs, [$iCodTramite]);

if ($stmtDocs) {
    while ($doc = sqlsrv_fetch_array($stmtDocs, SQLSRV_FETCH_ASSOC)) {
        $iCodDigital = $doc['iCodDigital'];
        $tipo = intval($doc['cTipoComplementario']);

        switch ($tipo) {
            case 1: // Pedido SIGA
                $jerarquia = obtenerJerarquiaOficinas($cnx, $iCodOficinaSession);
                $oficinaBase = end($jerarquia);
                $oficinaTop  = reset($jerarquia);
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaBase, 1, 'P');
                // Asegurar siempre inserción en Q, incluso si coincide con P
                if ($oficinaTop != $oficinaBase) {
                    asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'Q');
                } else {
                    asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaBase, 1, 'Q');
                }
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
                asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, 3, $oficinaTop, 1, 'Z');
                break;
        }
    }
}

echo json_encode(["status" => "success"]);

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

// 🔧 Asignar firmante fijo por trabajador único (jefe)
function asignarFirmanteFijo($cnx, $iCodTramite, $iCodDigital, $iCodPerfil, $iCodOficina, $tipoFirma, $posicion) {
    $sql = "SELECT iCodTrabajador FROM Tra_M_Perfil_Ususario WHERE iCodPerfil = ? AND iCodOficina = ?";
    $stmt = sqlsrv_query($cnx, $sql, [$iCodPerfil, $iCodOficina]);
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $iCodTrabajador = $row['iCodTrabajador'];
        // Validar duplicado
        $sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
                     WHERE iCodTramite = ? AND iCodDigital = ? AND iCodTrabajador = ? AND posicion = ?";
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

// 🔧 Asignar firmante por perfil (grupo), sin trabajador específico
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
