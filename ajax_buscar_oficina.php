<?php
include_once("conexion/conexion.php");
header('Content-Type: application/json; charset=UTF-8');

$q = trim($_GET['q'] ?? '');

$response = [];

if ($q !== '') {
    $sql = "SELECT TOP 10 iCodOficina, cNomOficina 
            FROM Tra_M_Oficinas 
            WHERE cNomOficina LIKE ? 
            ORDER BY cNomOficina";
    $params = ["%$q%"];
    $stmt = sqlsrv_query($cnx, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $response[] = [
                "iCodOficina" => $row["iCodOficina"],
                "cNomOficina" => $row["cNomOficina"]
            ];
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
