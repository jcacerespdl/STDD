<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;
date_default_timezone_set('America/Lima');

// Obtener datos de sesión
$icodOficinaRegistro = $_SESSION['iCodOficinaLogin'] ?? null;
$icodTrabajadorRegistro = $_SESSION['CODIGO_TRABAJADOR'] ?? null;

// Recibir datos del formulario
$tipoDocumento = $_POST['tipoDocumento'] ?? null;
$correlativo = $_POST['correlativo'] ?? null;
$asunto = $_POST['asunto'] ?? null;
$observaciones = $_POST['observaciones'] ?? null;
$cTipoBien = $_POST['tipoBien'] ?? null;
$pedidosSiga = $_POST['pedidosSiga'] ?? [];
$destinos = $_POST['destinos'] ?? [];
$fechaRegistro = date('Y-m-d\TH:i:s');
$nNumAno = date('Y');
$fase = isset($_POST['fase']) ? intval($_POST['fase']) : null;
$nNumFolio = isset($_POST['folios']) ? intval($_POST['folios']) : 1;
$nTienePedidoSiga = $_POST['pedidoSiga'] ?? null;

// Validar campos obligatorios
if (!$tipoDocumento || !$correlativo || !$asunto || !$icodOficinaRegistro || !$icodTrabajadorRegistro) {
    echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios."]);
    exit();
}

// Obtener sigla de oficina para codificación
$sqlSigla = "SELECT cSiglaOficina FROM Tra_M_Oficinas WHERE iCodOficina = ?";
$paramsSigla = array($icodOficinaRegistro);
$querySigla = sqlsrv_query($cnx,$sqlSigla, $paramsSigla);
if ($querySigla === false) {
    echo json_encode(["status" => "error", "message" => "Error SQL: " . print_r(sqlsrv_errors(), true)]);
    exit();
}
$sigla = sqlsrv_fetch_array($querySigla, SQLSRV_FETCH_ASSOC);

// Insertar en Tra_M_Tramite
$sqlInsert = "INSERT INTO Tra_M_Tramite 
(cCodTipoDoc,           cCodificacion,                  cAsunto,        cObservaciones,      iCodOficinaRegistro,       fFecDocumento,    
 fFecRegistro,          iCodTrabajadorRegistro,         nFlgTipoDoc,    nFlgEnvio,           nFlgEstado,                nFlgFirma, 
 extension,             cTipoBien,                      nNumFolio,      nFlgTipoDerivo,      nTienePedidoSiga
 )
               OUTPUT INSERTED.iCodTramite 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$paramsInsert = array(
$tipoDocumento,         substr($correlativo, 0, 150),    $asunto,       $observaciones,     $icodOficinaRegistro,       $fechaRegistro, 
$fechaRegistro,         $icodTrabajadorRegistro,        2,              1,                  1,                          0, 
1,                      $cTipoBien,                     $nNumFolio,     null,               $nTienePedidoSiga
);

$stmtInsert = sqlsrv_query($cnx, $sqlInsert, $paramsInsert);
// Capturar error en la consulta SQL
if ($stmtInsert === false) {
    error_log("SQL Insert Error: " . print_r(sqlsrv_errors(), true));
    echo json_encode(["status" => "error", "message" => "Error SQL: " . print_r(sqlsrv_errors(), true)]);
    exit();
}

// Obtener el iCodTramite generado
if (sqlsrv_fetch($stmtInsert) === false) {
    echo json_encode(["status" => "error", "message" => "Error al obtener iCodTramite: " . print_r(sqlsrv_errors(), true)]);
    exit();
}

$iCodTramite = sqlsrv_get_field($stmtInsert, 0);

if (!$iCodTramite) {
    echo json_encode(["status" => "error", "message" => "iCodTramite no fue generado."]);
    exit();
}

// Actualizar el campo expediente ahora que tenemos iCodTramite
$expediente = "I" . str_pad($iCodTramite, 9, "0", STR_PAD_LEFT);
$sqlExp = "UPDATE Tra_M_Tramite SET expediente = ? WHERE iCodTramite = ?";
$stmtExp = sqlsrv_query($cnx, $sqlExp, [$expediente, $iCodTramite]);

$sqlUpdateCorrelativo = "UPDATE Tra_M_Correlativo_Oficina 
                         SET nCorrelativo = nCorrelativo + 1 
                         WHERE cCodTipoDoc = ? AND iCodOficina = ? AND nNumAno = ?";
$paramsUpdate = [$tipoDocumento, $icodOficinaRegistro, $nNumAno];
$resultUpdate = sqlsrv_query($cnx, $sqlUpdateCorrelativo, $paramsUpdate);

//AGREGANDO CVV
    // Generar cPassword alfanumérico de 10 caracteres
    $semilla = "SGD2025";
    $raw = $iCodTramite . date('YmdHis') . $semilla;
    // Convertir a hash base36 (letras y números)
    $hash = base_convert(crc32($raw), 10, 36);  
    // Asegurar 10 caracteres (relleno con hash md5 si es necesario)
    $cPassword = strtoupper(substr($hash . md5($raw), 0, 10));  

    // Guardar en la tabla
    $sqlUpdateClave = "UPDATE Tra_M_Tramite SET cPassword = ? WHERE iCodTramite = ?";
    $stmtClave = sqlsrv_query($cnx, $sqlUpdateClave, [$cPassword, $iCodTramite]);

    // Insertar ítems CON pedido SIGA asociado
foreach ($pedidosSiga as $registro) {
    // esperado: nroPedido_tipoBien_codigoItem_cantidad
    list($nroPedido, $tipoBien, $codigoItem, $cantidad) = explode("_", $registro);

    $sql = "INSERT INTO Tra_M_Tramite_SIGA_Pedido 
            (iCodTramite, pedido_siga, codigo_item, cantidad, extension, EXPEDIENTE)
            VALUES (?, ?, ?, ?, 1, ?)";
    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $nroPedido, $codigoItem, $cantidad, $expediente]);
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Error al registrar pedido SIGA: " . print_r(sqlsrv_errors(), true)]);
        exit();
    }
}

// Insertar ítems SIN pedido SIGA  
$itemsManual = $_POST['itemsSigaManual'] ?? [];
foreach ($itemsManual as $registro) {
    $parts = explode("_", $registro);
    $codigoItem = $parts[0] ?? null;
    $cantidad = isset($parts[1]) ? intval($parts[1]) : null;
    if ($codigoItem && $cantidad) {
        $sqlManual = "INSERT INTO Tra_M_Tramite_SIGA_Pedido (iCodTramite, pedido_siga, codigo_item, cantidad, extension, EXPEDIENTE)
                      VALUES (?, NULL, ?, ?, 1, ?)";
        $stmtManual = sqlsrv_query($cnx, $sqlManual, [$iCodTramite, $codigoItem, $cantidad, $expediente]);
        if ($stmtManual === false) {
            echo json_encode(["status" => "error", "message" => "Error al registrar ítem manual: " . print_r(sqlsrv_errors(), true)]);
            exit();
        }
    }
}

  

// Marca de tiempo única para movimientos
$ahora = date('Y-m-d\TH:i:s');

// Insertar en tra_M_tramite_movimientos    
foreach ($destinos as $key => $destino) {
    $prev = explode("_", $destino);
    $iCodOficinaDerivar    = is_numeric($prev[0]) ? (int)$prev[0] : null;
    $iCodTrabajadorDerivar = is_numeric($prev[1]) ? (int)$prev[1] : null;
    $iCodIndicacionDerivar = is_numeric($prev[2]) ? (int)$prev[2] : null;
    $cPrioridadDerivar     = $prev[3] ?? '';
    $esCopia               = isset($prev[4]) && $prev[4] === '1';

    $nTiempoRespuesta = 3;
    if (strtolower($cPrioridadDerivar) === 'alta') $nTiempoRespuesta = 1;
    elseif (strtolower($cPrioridadDerivar) === 'baja') $nTiempoRespuesta = 5;

    if ($iCodOficinaDerivar === null || $iCodTrabajadorDerivar === null || $iCodIndicacionDerivar === null) {
        echo json_encode(["status" => "error", "message" => "No se pudo generar el movimiento #{$key}: datos incompletos o inválidos"]);
        exit();
    }

    $cFlgTipoMovimiento = $esCopia ? '4' : '1'; // 4=copia, 1=normal

    $sqlGenMov = "INSERT INTO Tra_M_Tramite_Movimientos
    (
        iCodTramite,
        iCodTrabajadorRegistro,
        iCodOficinaOrigen,
        iCodOficinaDerivar,
        iCodTrabajadorDerivar,
        iCodIndicacionDerivar,
        cPrioridadDerivar,
        EXPEDIENTE,
        nEstadoMovimiento,
        cFlgTipoMovimiento,
        extension,
        nTiempoRespuesta,
        nFlgEnvio,
        nFlgTipoDoc,
        fFecDerivar,
        fFecMovimiento,
        cAsuntoDerivar,
        cObservacionesDerivar
    )
    OUTPUT INSERTED.iCodMovimiento
    VALUES
    (
        ?,?,?,?,?,?,?,
        ?,?,?,?,
        ?,?,?,
        ?,?,?,
        ?
    )";

    $paramsGenMov = [
        $iCodTramite,
        $icodTrabajadorRegistro,
        $icodOficinaRegistro,
        $iCodOficinaDerivar,
        $iCodTrabajadorDerivar,
        $iCodIndicacionDerivar,
        $cPrioridadDerivar,
        substr($expediente, 0, 10),
        1,                 // nEstadoMovimiento
        $cFlgTipoMovimiento,
        1,                 // extension
        $nTiempoRespuesta,
        1,                 // nFlgEnvio
        2,                 // nFlgTipoDoc (interno)
        $ahora,            // fFecDerivar
        $ahora,            // fFecMovimiento
        $asunto,           // cAsuntoDerivar
        $observaciones     // cObservacionesDerivar
    ];

    $stmtGenMov = sqlsrv_query($cnx, $sqlGenMov, $paramsGenMov);
    if ($stmtGenMov === false) {
        echo json_encode(["status" => "error", "message" => "No se pudo generar el movimiento #{$key}: " . print_r(sqlsrv_errors(), true)]);
        exit();
    }
}
    // Respuesta final
    echo json_encode(["status" => "success", "message" => "Trámite registrado correctamente", "iCodTramite" => $iCodTramite]);
    exit();
?>