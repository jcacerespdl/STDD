<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;
date_default_timezone_set('America/Lima');

$iCodTrabajadorRegistro = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodOficinaRegistro = 236; // Oficina Mesa de Partes

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $destinos = $_POST['destinos'] ?? [];

    // === 1. Validaciones mínimas obligatorias ===
    if (!$iCodTrabajadorRegistro) {
        echo json_encode(["status" => "error", "message" => "Sesión no iniciada correctamente."]);
        exit;
    }

    if (empty($destinos)) {
        echo json_encode(["status" => "error", "message" => "Debe agregar al menos un destino."]);
        exit;
    }

    // === 2. Recopilar datos del formulario ===
    $asunto = $_POST['asunto'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $cCodTipoDoc = $_POST['tipoDocumentoOficial'] ?? null;
    $iCodTipoRegistro = $_POST['tipoRegistro'] ?? 2;
    $nNumFolio = $_POST['nNumFolio'] ?? 1;
    $tipoDocumento = $_POST['tipoDocumento'] ?? null;
    $nroDocumento = $_POST['nroDocumento'] ?? null;
    $celular = $_POST['celular'] ?? null;
    $correo = $_POST['correo'] ?? null;
    $apePaterno = $_POST['apePaterno'] ?? null;
    $apeMaterno = $_POST['apeMaterno'] ?? null;
    $nombres = $_POST['nombres'] ?? null;
    $departamento = $_POST['departamento'] ?? null;
    $provincia = $_POST['provincia'] ?? null;
    $distrito = $_POST['distrito'] ?? null;
    $direccion = $_POST['direccion'] ?? null;
    $ruc = $_POST['ruc'] ?? null;
    $razonSocial = $_POST['razonSocial'] ?? null;
    $tdoc_asegurado = $_POST['tdoc_asegurado'] ?? null;
    $ndoc_asegurado = $_POST['ndoc_asegurado'] ?? null;
    $cel_asegurado = $_POST['cel_asegurado'] ?? null;
    $email_asegurado = $_POST['email_asegurado'] ?? null;
    $apePaterno_asegurado = $_POST['apePaterno_asegurado'] ?? null;
    $apeMaterno_asegurado = $_POST['apeMaterno_asegurado'] ?? null;
    $nombres_asegurado = $_POST['nombres_asegurado'] ?? null;
    $link = $_POST['link'] ?? null;

    $archivoValido = false;
    $nombreArchivo = null;
    $archivo = $_FILES['archivo'] ?? null;

    if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $tipoMime = $archivo['type'];
if ($extension === 'pdf' && stripos($tipoMime, 'pdf') !== false && $archivo['size'] <= 10 * 1024 * 1024) {
            $archivoValido = true;
            $nombreArchivo = $archivo['name'];
        }
    }

    // === 3. Insertar en Tra_M_Tramite ===
    $clave = substr(str_pad(abs(crc32($nroDocumento)), 5, '0', STR_PAD_LEFT), 0, 5);
    $fechaRegistro = date("Y-m-d H:i:s");

    $sqlInsert = "INSERT INTO Tra_M_Tramite (
        nFlgTipoDoc, iCodOficinaRegistro, cAsunto, cObservaciones,
        cTipoDocumentoSolicitante, cNumeroDocumentoSolicitante, cCelularSolicitante, cCorreoSolicitante,
        cApePaternoSolicitante, cApeMaternoSolicitante, cNombresSolicitante,
        cDepartamentoSolicitante, cProvinciaSolicitante, cDistritoSolicitante, cDireccionSolicitante,
        cRUCEntidad, cRazonSocialEntidad,
        cTipoDocumentoAsegurado, cNumeroDocumentoAsegurado, cCelularAsegurado, cCorreoAsegurado,
        cApePaternoAsegurado, cApeMaternoAsegurado, cNombresAsegurado,
        cLinkArchivo, documentoElectronico,
        fFecRegistro, cPassword, extension, nFlgEstado, nFlgEnvio,
        cCodTipoDoc, iCodTipoRegistro, nNumFolio, iCodTrabajadorRegistro
    ) OUTPUT INSERTED.iCodTramite VALUES (
        1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1,
        ?, ?, ?, ?
    )";

    $params = [
        $iCodOficinaRegistro, $asunto, $descripcion,
        $tipoDocumento, $nroDocumento, $celular, $correo,
        $apePaterno, $apeMaterno, $nombres,
        $departamento, $provincia, $distrito, $direccion,
        $ruc, $razonSocial,
        $tdoc_asegurado, $ndoc_asegurado, $cel_asegurado, $email_asegurado,
        $apePaterno_asegurado, $apeMaterno_asegurado, $nombres_asegurado,
        $link, $nombreArchivo,
        $fechaRegistro, $clave,
        $cCodTipoDoc, $iCodTipoRegistro, $nNumFolio, $iCodTrabajadorRegistro
    ];

    $stmt = sqlsrv_query($cnx, $sqlInsert, $params);
    if (!$stmt || !sqlsrv_fetch($stmt)) {
        echo json_encode(["status" => "error", "message" => "No se pudo registrar el trámite."]);
        exit;
    }


    // OBTENER iCodTramite

    $iCodTramite = sqlsrv_get_field($stmt, 0);


     // === Subir archivos complementarios ===
if (!empty($_FILES['complementarios']['name'][0])) {
    $uploadDir = "cAlmacenArchivos/";
    foreach ($_FILES['complementarios']['tmp_name'] as $index => $tmpPath) {
        if ($_FILES['complementarios']['error'][$index] === UPLOAD_ERR_OK) {
            $originalName = basename($_FILES['complementarios']['name'][$index]);
            $fileName = $iCodTramite . '-' . str_replace(' ', '_', $originalName);
            $destino = $uploadDir . $fileName;

            if (move_uploaded_file($tmpPath, $destino)) {
                $sql = "INSERT INTO Tra_M_Tramite_Digitales (iCodTramite, cDescripcion,    fFechaRegistro )
                        VALUES (?, ?,  GETDATE() )";
                $params = [$iCodTramite, $fileName];
                sqlsrv_query($cnx, $sql, $params);
            }
        }
    }
}

 // ACTUALIZAR EXPEDIENTE

    $expediente = 'E' . str_pad($iCodTramite, 9, '0', STR_PAD_LEFT);
    sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET expediente = ? WHERE iCodTramite = ?", [$expediente, $iCodTramite]);
    // === 4. Subir archivo firmado si es válido ===
    if ($archivoValido && $iCodTramite) {
        $nombreLimpio = preg_replace('/\s+/', '_', pathinfo($nombreArchivo, PATHINFO_FILENAME));
        $extFinal = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
        $archivoFinal = $iCodTramite . '-' . $nombreLimpio . '.' . $extFinal;
        $ruta = __DIR__ . "/cDocumentosFirmados/" . $archivoFinal;

        if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
            echo json_encode(["status" => "error", "message" => "No se pudo guardar el archivo en el servidor."]);
            exit;
        }
        // Actualizar campo documentoElectronico
        sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET documentoElectronico = ? WHERE iCodTramite = ?", [$archivoFinal, $iCodTramite]);
    }

    $destinos = array_unique($destinos);
 



    // === 5. Insertar movimientos para cada destino ===
    foreach ($destinos as $key => $destino) {
        $partes = explode("_", $destino);
        $iCodOficinaDerivar = isset($partes[0]) ? (int)$partes[0] : null;
        $iCodTrabajadorDerivar = isset($partes[1]) ? (int)$partes[1] : null;
        $iCodIndicacionDerivar = isset($partes[2]) ? (int)$partes[2] : null;
        $prioridadTexto = strtolower($partes[3] ?? '');

        $sqlVerificar = "SELECT COUNT(*) as total FROM Tra_M_Tramite_Movimientos 
        WHERE iCodTramite = ? AND iCodOficinaDerivar = ? AND extension = 1 AND cFlgTipoMovimiento = '1'";
$stmtVer = sqlsrv_query($cnx, $sqlVerificar, [$iCodTramite, $iCodOficinaDerivar]);
$yaExiste = sqlsrv_fetch_array($stmtVer, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

if ($yaExiste > 0) continue;



        $tiempoRespuesta = 3;
        if ($prioridadTexto === 'alta') $tiempoRespuesta = 1;
        elseif ($prioridadTexto === 'baja') $tiempoRespuesta = 5;

        if (!$iCodOficinaDerivar || !$iCodTrabajadorDerivar || !$iCodIndicacionDerivar) {
            echo json_encode(["status" => "error", "message" => "Destino $key inválido o incompleto."]);
            exit;
        }

        $sqlMov = "INSERT INTO Tra_M_Tramite_Movimientos (
            iCodTramite, iCodTrabajadorRegistro, iCodOficinaOrigen, iCodOficinaDerivar,
            iCodTrabajadorDerivar, iCodIndicacionDerivar, cPrioridadDerivar,
            expediente, nEstadoMovimiento, cFlgTipoMovimiento, extension, nTiempoRespuesta
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '1', 1, ?)";

        $paramsMov = [
            $iCodTramite, $iCodTrabajadorRegistro, $iCodOficinaRegistro, $iCodOficinaDerivar,
            $iCodTrabajadorDerivar, $iCodIndicacionDerivar, ucfirst($prioridadTexto),
            $expediente, $tiempoRespuesta
        ];

        $stmtMov = sqlsrv_query($cnx, $sqlMov, $paramsMov);
        if (!$stmtMov) {
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo insertar el destino #" . ($key + 1),
                "sql_errors" => sqlsrv_errors()
            ]);
            exit;
        }
    }

    // === 6. Respuesta Final ===
    echo json_encode([
        "status" => "success",
        "iCodTramite" => $iCodTramite,
        "expediente" => $expediente,
        "clave" => $clave
    ]);
    exit;
}
