<?php
 include_once("conexion/conexion.php");
session_start();
global $cnx;
date_default_timezone_set('America/Lima');

try {
    // Recibir datos del formulario
    $tipoDocumento = $_POST['tipoDocumento'] ?? null;
    $correlativo = $_POST['correlativo'] ?? null;
    $asunto = $_POST['asunto'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
     // Del formulario hidden
    $iCodTramiteAnterior = $_POST['iCodTramite'] ?? null;
    $iCodOficinaOrigen = $_SESSION['iCodOficinaLogin'];
    $iCodTrabajadorOrigen = $_SESSION['CODIGO_TRABAJADOR'];
    $cTipoBien = $_POST['tipoBien'] ?? null;
    $pedidosSiga = $_POST['pedidosSiga'] ?? [];
    $destinos = $_POST['destinos'] ?? [];
    $fFecRegistro = date('Y-m-d\TH:i:s');
    $nNumAno = date('Y');
    $fase = isset($_POST['fase']) ? intval($_POST['fase']) : null;
    $nNumFolio = isset($_POST['folios']) ? intval($_POST['folios']) : 1;
    $nTienePedidoSiga = $_POST['pedidoSiga'] ?? null;

    // Obtener correlativo actual (ya debe estar validado desde el frontend)
    if (!$tipoDocumento || !$correlativo || !$asunto || !$iCodTramiteAnterior) {
        throw new Exception("Faltan datos obligatorios.");
    }

    // Obtener extensión desde el movimiento que está siendo derivado
    $sqlExt = "SELECT extension FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?";
    $stmtExt = sqlsrv_query($cnx, $sqlExt, [$_POST['iCodMovimiento']]);
    $rowExt = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC);
    $extension = $rowExt['extension'] ?? 1;

    // Obtener expediente del trámite original
    $sqlExp = "SELECT expediente FROM Tra_M_Tramite WHERE iCodTramite = ?";
    $stmtExp = sqlsrv_query($cnx, $sqlExp, [$iCodTramiteAnterior]);
    $rowExp = sqlsrv_fetch_array($stmtExp, SQLSRV_FETCH_ASSOC);
    $expediente = $rowExp['expediente'] ?? null;

    // Insertar nuevo trámite derivado
    $sqlInsertTramite = "INSERT INTO Tra_M_Tramite (
        cCodTipoDoc, cCodificacion, cAsunto, cObservaciones,
        iCodOficinaRegistro, iCodTrabajadorRegistro,
         fFecRegistro, fFecDocumento,
         extension, nFlgTipoDoc, expediente, nFlgTipoDerivo, 
         nFlgEnvio, nFlgEstado, nFlgFirma, nNumFolio, fase, cTipoBien, nTienePedidoSiga
    ) OUTPUT INSERTED.iCodTramite VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)";

    $paramsTramite = [
        $tipoDocumento, 
        $correlativo, 
        $asunto, 
        $observaciones,
        $iCodOficinaOrigen,
         $iCodTrabajadorOrigen,
          $fFecRegistro, 
          $fFecRegistro, 
          $extension, 
        2, 
        $expediente, 
        1, 
        0, 
        0, 0, $nNumFolio, $fase, $cTipoBien, $nTienePedidoSiga
    ];
    $stmt = sqlsrv_query($cnx, $sqlInsertTramite, $paramsTramite);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $iCodTramiteNuevo = $row['iCodTramite'];
    //AGREGANDO CVV
    // Generar cPassword alfanumérico de 10 caracteres
    $semilla = "SGD2025";
    $raw = $iCodTramiteNuevo . date('YmdHis') . $semilla;
    // Convertir a hash base36 (letras y números)
    $hash = base_convert(crc32($raw), 10, 36); 
    // Asegurar 10 caracteres (relleno con hash md5 si es necesario)
    $cPassword = strtoupper(substr($hash . md5($raw), 0, 10)); 

    // Guardar en la tabla
    $sqlUpdateClave = "UPDATE Tra_M_Tramite SET cPassword = ? WHERE iCodTramite = ?";
    $stmtClave = sqlsrv_query($cnx, $sqlUpdateClave, [$cPassword, $iCodTramiteNuevo]);
    // Insertar cada pedido SIGA asociado
foreach ($pedidosSiga as $pedidoCompleto) {
    $pedidoParts = explode("_", $pedidoCompleto);
    $pedidoSiga = $pedidoParts[0] ?? null;
    if ($pedidoSiga) {
        $sqlSiga = "INSERT INTO Tra_M_Tramite_SIGA_Pedido (iCodTramite, pedido_siga, extension) VALUES (?, ?, 1)";
        $stmtSiga = sqlsrv_query($cnx, $sqlSiga, [$iCodTramiteNuevo, $pedidoSiga]);
        if ($stmtSiga === false) {
            echo json_encode(["status" => "error", "message" => "Error al registrar pedido SIGA: " . print_r(sqlsrv_errors(), true)]);
            exit();
        }
    }
}

// Insertar ítems SIN pedido SIGA (catálogo manual)
$itemsManual = $_POST['itemsSigaManual'] ?? [];
foreach ($itemsManual as $registro) {
    $parts = explode("_", $registro);
    $codigoItem = $parts[0] ?? null;
    $cantidad = isset($parts[1]) ? intval($parts[1]) : null;
    if ($codigoItem && $cantidad) {
        $sqlManual = "INSERT INTO Tra_M_Tramite_SIGA_Pedido (iCodTramite, pedido_siga, codigo_item, cantidad, extension)
                      VALUES (?, NULL, ?, ?, 1)";
        $stmtManual = sqlsrv_query($cnx, $sqlManual, [$iCodTramiteNuevo, $codigoItem, $cantidad]);
        if ($stmtManual === false) {
            echo json_encode(["status" => "error", "message" => "Error al registrar ítem manual: " . print_r(sqlsrv_errors(), true)]);
            exit();
        }
    }
}

    // Obtener el movimiento original para derivación
    $iCodMovimientoDerivo = $_POST['iCodMovimiento'] ?? null;
    

    // Insertar en tra_M_tramite_movimientos    
    foreach ($destinos as $key => $destino) {
        $prev = explode("_", $destino);
        $iCodOficinaDerivar = is_numeric($prev[0]) ? (int)$prev[0] : null; //ok 
        $iCodTrabajadorDerivar = is_numeric($prev[1]) ? (int)$prev[1] : null; //ok
        $iCodIndicacionDerivar = is_numeric($prev[2]) ? (int)$prev[2] : null; //ok
        $cPrioridadDerivar = $prev[3] ?? ''; //ok
        $esCopia = isset($prev[4]) && $prev[4] === '1'; // nuevo campo

        $nTiempoRespuesta = 3; // media por defecto
            if (strtolower($cPrioridadDerivar) === 'alta') {
                $nTiempoRespuesta = 1;
            } elseif (strtolower($cPrioridadDerivar) === 'baja') {
                $nTiempoRespuesta = 5;
            }

            $cFlgTipoMovimiento = $esCopia ? '4' : '1'; // 4 para copia, 1 para normal

                // Buscar delegado previo
               // Buscar delegado si la oficina actual ya recibió este trámite antes y fue delegado
                $iCodTrabajadorDelegado = null;
                $fFecDelegado = null;
                $iCodIndicacionDelegado = null;

                $sqlDelegado = "SELECT TOP 1 
                        tm.iCodTrabajadorDelegado,
                        tm.fFecDelegado,
                        tm.iCodIndicacionDelegado
                    FROM Tra_M_Tramite_Movimientos tm
                    JOIN Tra_M_Tramite_Movimientos origen ON tm.iCodMovimiento = origen.iCodMovimientoDerivo
                    WHERE tm.iCodOficinaDerivar = ? -- oficina actual
                    AND origen.iCodMovimiento = ? -- movimiento anterior
                    AND tm.iCodTrabajadorDelegado IS NOT NULL";

                $stmtDelegado = sqlsrv_query($cnx, $sqlDelegado, [$iCodOficinaDerivar, $iCodMovimientoDerivo]);
                $rowDelegado = sqlsrv_fetch_array($stmtDelegado, SQLSRV_FETCH_ASSOC);

                if ($rowDelegado) {
                    $iCodTrabajadorDelegado = $rowDelegado['iCodTrabajadorDelegado'];
                    $fFecDelegado = date('Y-m-d H:i:s');
                        $iCodIndicacionDelegado = $rowDelegado['iCodIndicacionDelegado'] ?? 1;
                }

                // fin buscar delegado previo 


        $sqlMov = "INSERT INTO Tra_M_Tramite_Movimientos (
            iCodTramite,   iCodOficinaOrigen,    iCodTrabajadorRegistro,     iCodOficinaDerivar,    iCodTrabajadorDerivar,        cAsuntoDerivar, 
            cObservacionesDerivar,           cPrioridadDerivar,        fFecMovimiento,       nEstadoMovimiento,          cFlgTipoMovimiento,
            iCodTramiteDerivar,            iCodMovimientoDerivo,          iCodIndicacionDerivar,
            Expediente,        extension,        iCodTrabajadorDelegado,          iCodIndicacionDelegado,           fFecDelegado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $paramsMov = [
            $iCodTramiteAnterior, $iCodOficinaOrigen, $iCodTrabajadorOrigen,  $iCodOficinaDerivar, $iCodTrabajadorDerivar,    $asunto, 
            $observaciones, $cPrioridadDerivar,        $fFecRegistro,     $cFlgTipoMovimiento   , 
            $iCodTramiteNuevo, $iCodMovimientoDerivo, $iCodIndicacionDerivar,
            $expediente, $extension,       $iCodTrabajadorDelegado, ($iCodTrabajadorDelegado ? 1 : null), $fFecDelegado
        ];
        $stmtMov = sqlsrv_query($cnx, $sqlMov, $paramsMov);
        if ($stmtMov === false) throw new Exception(print_r(sqlsrv_errors(), true));
    }

    echo json_encode([
        "status" => "success", 
        "iCodTramite" => $iCodTramiteNuevo,
        "iCodMovimientoDerivo" => $iCodMovimientoDerivo
    
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
