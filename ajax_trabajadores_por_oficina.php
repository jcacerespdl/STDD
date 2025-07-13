<?php
session_start();
include_once("./conexion/conexion.php");

$oficina = $_GET['iCodOficina'] ?? '';
 

$sql = "SELECT 
            T.iCodTrabajador, 
            T.cNombresTrabajador, 
            T.cApellidosTrabajador,
            O.iCodOficina,
            O.cNomOficina,
            P.cDescPerfil
        FROM Tra_M_Trabajadores T
        INNER JOIN Tra_M_Perfil_Ususario PU ON T.iCodTrabajador = PU.iCodTrabajador AND PU.iCodPerfil IN (3,4)
        INNER JOIN Tra_M_Oficinas O ON PU.iCodOficina = O.iCodOficina AND O.iFlgEstado = 1
        INNER JOIN Tra_M_Perfil P ON P.iCodPerfil = PU.iCodPerfil
        WHERE T.nFlgEstado = 1";

if ($oficina !== '') {
    $sql .= " AND O.iCodOficina = '$oficina'";
}

$sql .= " GROUP BY T.iCodTrabajador, T.cNombresTrabajador, T.cApellidosTrabajador, O.iCodOficina, O.cNomOficina, P.cDescPerfil
          ORDER BY T.cNombresTrabajador, T.cApellidosTrabajador";

$rs = sqlsrv_query($cnx, $sql);
$data = [];

while ($r = sqlsrv_fetch_array($rs)) {
    $data[] = [
        'id' => $r['iCodTrabajador'],
        'nombre' => trim($r['cNombresTrabajador']),
        'apellidos' => trim($r['cApellidosTrabajador']),
        'iCodOficina' => $r['iCodOficina'],
        'oficinaNombre' => trim($r['cNomOficina']),
        'perfil' => trim($r['cDescPerfil'])
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
