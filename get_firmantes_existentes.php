<?php
include_once("conexion/conexion.php");
header('Content-Type: application/json');

// Leer JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    echo json_encode(["error" => "No se recibió JSON válido"]);
    exit;
}

$result = [];

foreach ($input as $item) {
    $iCodTramite = intval($item['iCodTramite']);
    $iCodDigital = intval($item['iCodDigital']);

    $sql = "SELECT 
                tf.iCodTramite,
                tf.iCodDigital,
                tf.iCodTrabajador AS id,
                tf.posicion,
                tf.tipoFirma,
                tr.cNombresTrabajador AS nombre,
                tr.cApellidosTrabajador AS apellidos,
                td.cDescripcion AS descripcion, -- ✅ corregido
                tf.iCodOficina,
                ofi.cNomOficina AS oficinaNombre
            FROM Tra_M_Tramite_Firma tf
            INNER JOIN Tra_M_Trabajadores tr ON tf.iCodTrabajador = tr.iCodTrabajador
            INNER JOIN Tra_M_Oficinas ofi ON tf.iCodOficina = ofi.iCodOficina
            INNER JOIN Tra_M_Tramite_Digitales td 
                ON tf.iCodTramite = td.iCodTramite AND tf.iCodDigital = td.iCodDigital
            WHERE tf.iCodTramite = ? AND tf.iCodDigital = ?";

    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);

    if ($stmt === false) {
        echo json_encode(["error" => sqlsrv_errors()]);
        exit;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result[] = $row;
    }
}

echo json_encode($result);
