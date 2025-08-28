<?php
include("conexion/conexion.php");
session_start();

$idTrabajador = isset($_POST['idTrabajador']) ? intval($_POST['idTrabajador']) : 0;
$perfil = isset($_POST['perfil']) ? intval($_POST['perfil']) : 0;
$oficina = isset($_POST['oficina']) ? intval($_POST['oficina']) : 0;

if ($idTrabajador <= 0 || $perfil <= 0 || $oficina <= 0) {
    echo "<script>alert('Datos incompletos.'); window.history.back();</script>";
    exit;
}

// Validar que no se duplique combinación perfil-oficina para el trabajador
$sqlCheck = "SELECT COUNT(*) AS total FROM Tra_M_Perfil_Ususario 
             WHERE iCodTrabajador = ? AND iCodPerfil = ? AND iCodOficina = ?";
$stmtCheck = sqlsrv_query($cnx, $sqlCheck, [$idTrabajador, $perfil, $oficina]);
$row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if ($row && $row['total'] > 0) {
    echo "<script>alert('Este perfil ya está asignado a esta oficina para el trabajador.'); window.history.back();</script>";
    exit;
}

// Insertar nueva asignación
$sqlInsert = "INSERT INTO Tra_M_Perfil_Ususario (iCodTrabajador, iCodPerfil, iCodOficina) 
              VALUES (?, ?, ?)";
$stmt = sqlsrv_query($cnx, $sqlInsert, [$idTrabajador, $perfil, $oficina]);

if ($stmt) {
    echo "<script>alert('Perfil agregado correctamente.'); window.location.href='mantenimientoTrabajadoresEditar.php?iCodTrabajador=$idTrabajador';</script>";
} else {
    echo "<script>alert('Error al agregar el perfil.'); window.history.back();</script>";
}
?>
