<?php
include("conexion/conexion.php");
header('Content-Type: application/json');

try {
    $iCodTrabajador = isset($_POST['iCodTrabajador']) ? intval($_POST['iCodTrabajador']) : 0;
    $iCodPerfil     = isset($_POST['iCodPerfil']) ? intval($_POST['iCodPerfil']) : 0;

    if ($iCodTrabajador > 0 && $iCodPerfil > 0) {
        $sql = "DELETE FROM Tra_M_Perfil_Ususario WHERE iCodTrabajador = ? AND iCodPerfil = ?";
        $stmt = sqlsrv_query($cnx, $sql, [$iCodTrabajador, $iCodPerfil]);

        if ($stmt) {
            echo json_encode(["status" => "success"]);
        } else {
            $errors = sqlsrv_errors();
            echo json_encode(["status" => "error", "message" => "SQL Error", "details" => $errors]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Parámetros incompletos"]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Exception", "details" => $e->getMessage()]);
}
?>
