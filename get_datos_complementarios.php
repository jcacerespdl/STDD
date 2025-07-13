<?php
include_once("conexion/conexion.php");
header('Content-Type: application/json');

// Leer el input JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    echo json_encode(["error" => "No se recibió JSON válido"]);
    exit;
}

$resultado = [];

foreach ($input as $item) {
    $iCodTramite = intval($item['iCodTramite']);
    $iCodDigital = intval($item['iCodDigital']);

    $sql = "SELECT 
                td.iCodTramite,
                td.iCodDigital,
                td.cDescripcion AS descripcion,
                t.cCodificacion AS expediente,
                t.cAsunto AS asunto
            FROM Tra_M_Tramite_Digitales td
            INNER JOIN Tra_M_Tramite t ON td.iCodTramite = t.iCodTramite
            WHERE td.iCodTramite = ? AND td.iCodDigital = ?";

    $stmt = sqlsrv_query($cnx, $sql, [$iCodTramite, $iCodDigital]);

    if ($stmt === false) {
        echo json_encode(["error" => sqlsrv_errors()]);
        exit;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $resultado[] = $row;
    }
}

echo json_encode($resultado);
