<?php
header('Content-Type: application/json');

global $cnx;
include_once("./conexion/conexion.php");

try {
    $iCodTramite = $_POST['iCodTramite'];

    if(!empty($_FILES['files'])){
        $filesUpload = [];

        foreach($_FILES['files']['tmp_name'] as $index => $tmpName){
            $fileName = $iCodTramite."-".str_replace(" ","-",basename($_FILES['files']['name'][$index]));
            $destination = "./cAlmacenArchivos/{$fileName}";

            $sqlInsert = "INSERT INTO tra_M_tramite_digitales (iCodTramite,cNombreOriginal) OUTPUT INSERTED.iCodDigital VALUES (?,?)";
            $params = array($iCodTramite, $fileName);
            $query = sqlsrv_query($cnx, $sqlInsert, $params);

            // Capturar error en la consulta SQL
            if ($query === false) {
                echo json_encode(["status" => "error", "message" => "Error SQL: " . print_r(sqlsrv_errors(), true), "data" => null]);
                exit();
            }

            // Obtener el iCodTramite generado
            if (sqlsrv_fetch($query) === false) {
                echo json_encode(["status" => "error", "message" => "Error al obtener iCodTramite: " . print_r(sqlsrv_errors(), true), "data" => null]);
                exit();
            }

            $iCodDigital = sqlsrv_get_field($query, 0);

            if(move_uploaded_file($tmpName, $destination)){
                $filesUpload[] = ["name" => $fileName, "href" => $destination, "iCodDigital" => $iCodDigital];
            }
        }
        echo json_encode(["status" => "success", "message" => null, "data" => $filesUpload]);
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "No se recibieron archivos", "data" => null]);
        exit();
    }
} catch(Exception $e){
    echo json_encode(["status" => "error", "message" => "Error inesperado: {$e}", "data" => null]);
    exit();
}