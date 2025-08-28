<?php
include("conexion/conexion.php");
global $cnx;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<script>alert('ID inválido'); window.history.back();</script>";
    exit;
}

// Obtener el iCodTrabajador asociado antes de eliminar
$sql = "SELECT iCodTrabajador FROM Tra_M_Perfil_Ususario WHERE iCodPerfilUsuario = ?";
$stmt = sqlsrv_query($cnx, $sql, [$id]);

if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $iCodTrabajador = $row['iCodTrabajador'];

    // Eliminar registro
    $sqlDelete = "DELETE FROM Tra_M_Perfil_Ususario WHERE iCodPerfilUsuario = ?";
    $stmtDelete = sqlsrv_query($cnx, $sqlDelete, [$id]);

    if ($stmtDelete) {
        echo "<script>alert('Perfil eliminado correctamente'); window.location.href='mantenimientoTrabajadoresEditar.php?iCodTrabajador=$iCodTrabajador';</script>";
        exit;
    } else {
        echo "<script>alert('Error al eliminar el perfil'); window.history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Registro no encontrado'); window.history.back();</script>";
    exit;
}
?>
