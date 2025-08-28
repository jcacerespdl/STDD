<?php
include_once("conexion/conexion.php");
session_start();
global $cnx;

if (!isset($_POST['iCodTramite']) || !isset($_FILES['archivos'])) {
    die("Error: Parámetros incompletos.");
}

$iCodTramite = $_POST['iCodTramite'];
$uploadDir = "cAlmacenArchivos/";
$subidos = 0;
$errores = [];
$nombresArchivos = $_POST['nombresArchivos'] ?? [];
$tiposComplementario = $_POST['tipoComplementario'] ?? [];

foreach ($_FILES['archivos']['tmp_name'] as $index => $tmpPath) {
    if ($_FILES['archivos']['error'][$index] === UPLOAD_ERR_OK) {
        $originalName = basename($_FILES['archivos']['name'][$index]);
        $fileName = $iCodTramite . '-' . str_replace(' ', '_', $originalName);
        $destino = $uploadDir . $fileName;

        $tipo = intval($tiposComplementario[$index] ?? 0);

        if (move_uploaded_file($tmpPath, $destino)) {
            $sql = "INSERT INTO Tra_M_Tramite_Digitales (iCodTramite, cDescripcion, pedido_siga, cTipoComplementario)
                    VALUES (?, ?, NULL, ?)";
            $params = [$iCodTramite, $fileName, $tipo];
            $stmt = sqlsrv_query($cnx, $sql, $params);

            if ($stmt) {
                $subidos++;
            } else {
                $errores[] = "Error SQL al insertar {$fileName}: " . print_r(sqlsrv_errors(), true);
            }
        } else {
            $errores[] = "No se pudo mover el archivo {$fileName}.";
        }
    } else {
        $errores[] = "Error al subir archivo en índice {$index}.";
    }
}

// if ($subidos > 0) {
//     echo "<script>alert('Se subieron correctamente {$subidos} archivos.'); window.history.back();</script>";
    
// } else {
//     echo "<script>alert('No se pudo subir ningún archivo.'); window.history.back();</script>";
// }

if ($subidos > 0) {
    echo "<script>  window.history.back();</script>";
    
} else {
    echo "<script>  window.history.back();</script>";
}




if (!empty($errores)) {
    foreach ($errores as $err) {
        error_log($err);
    }
}
?>
