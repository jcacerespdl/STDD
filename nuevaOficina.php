<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nombre = trim($_POST['nombre'] ?? '');
  $sigla = trim($_POST['sigla'] ?? '');

  if ($nombre && $sigla) {
    $sql = "INSERT INTO Tra_M_Oficinas (cNomOficina, cSiglaOficina, iFlgEstado) VALUES (?, ?, 1)";
    $stmt = sqlsrv_query($cnx, $sql, [$nombre, $sigla]);

    if ($stmt) {
      echo "<script>alert('Oficina creada correctamente'); window.location.href='mantenimientoOficinas.php';</script>";
      exit;
    } else {
      $error = "Error al crear la oficina.";
    }
  } else {
    $error = "Debe completar todos los campos.";
  }
}
?>

<div class="container" style="margin: 120px auto 60px auto; max-width: 600px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">CREACIÓN DE OFICINA</div>

  <div class="card" style="margin-top: 20px;">
    <?php if (!empty($error)): ?>
      <div style="color: red; font-weight: bold; margin-bottom: 15px;"><?= $error ?></div>
    <?php endif; ?>
    <form method="post">
    <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="nombre" required placeholder=" ">
        <label>Nombre de Oficina</label>
      </div>
      <div class="input-container" style="margin-bottom: 20px;">
        <input type="text" name="sigla" required placeholder=" ">
        <label>Sigla de Oficina</label>
      </div>
      <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
        <button type="submit" class="btn btn-primary"><span class="material-icons">save</span> Guardar</button>
        <button type="button" onclick="window.location.href='mantenimientoOficinas.php'" class="btn btn-secondary"><span class="material-icons">arrow_back</span> Cancelar</button>
      </div>
    </form>
  </div>
</div>
