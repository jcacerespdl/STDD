<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

// Filtros
$nombres = $_GET['nombres'] ?? '';
$apellidos = $_GET['apellidos'] ?? '';
$estado = $_GET['estado'] ?? '';

// Paginación
$tampag = 45;
$pag = isset($_GET['pag']) && is_numeric($_GET['pag']) ? intval($_GET['pag']) : 1;
$reg1 = ($pag - 1) * $tampag;

// Consulta al SP
$sql = "{CALL SP_TRABAJADORES_LISTA (?, ?, ?)}";
$params = [$nombres ?: null, $apellidos ?: null, $estado !== '' ? intval($estado) : null];
$stmt = sqlsrv_query($cnx, $sql, $params);

$trabajadores = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $trabajadores[] = $row;
    }
}

$total = count($trabajadores);
$trabajadores = array_slice($trabajadores, $reg1, $tampag);
?>
<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">MANTENIMIENTO DE TRABAJADORES</div>

  <div class="card">
    <div class="card-title" style="margin-bottom: 20px;">FILTROS DE BÚSQUEDA</div>
    <form method="get">
      <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="input-container" style="flex: 1 1 30%;">
          <input type="text" name="nombres" value="<?= htmlspecialchars($nombres) ?>" placeholder=" ">
          <label>Nombres</label>
        </div>
        <div class="input-container" style="flex: 1 1 30%;">
          <input type="text" name="apellidos" value="<?= htmlspecialchars($apellidos) ?>" placeholder=" ">
          <label>Apellidos</label>
        </div>
        <div class="input-container select-flotante" style="flex: 1 1 30%;">
          <select name="estado">
            <option value="" <?= $estado === '' ? 'selected' : '' ?> hidden> </option>
            <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivo</option>
          </select>
          <label>Estado</label>
        </div>
      </div>

      <div class="row" style="display: flex; justify-content: flex-end; margin-top: 30px; gap: 15px;">
        <button type="submit" class="btn btn-primary" style="min-width: 180px;">
          <span class="material-icons">search</span> Buscar
        </button>
        <button type="button" onclick="window.location.href='mantenimientoTrabajadores.php'" class="btn btn-secondary" style="min-width: 180px;">
          <span class="material-icons">autorenew</span> Reestablecer
        </button>
        <button type="button" onclick="window.location.href='nuevoTrabajador.php'" class="btn btn-primary" style="min-width: 180px;">
          <span class="material-icons">add</span> Nuevo Trabajador
        </button>
      </div>
    </form>
  </div>
  <div class="card">
    <div style="margin-bottom: 10px; color: var(--primary); font-weight: bold;">
      Total de registros: <?= $total ?>
    </div>

    <table class="table table-bordered">
      <thead class="table-secondary">
        <tr>
          <th>Nombres</th>
          <th>Apellidos</th>
          <th>Estado</th>
          <th style="width: 180px;">Opciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($trabajadores as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['cNombresTrabajador']) ?></td>
            <td><?= htmlspecialchars($t['cApellidosTrabajador']) ?></td>
            <td><?= $t['nFlgEstado'] == 1 ? 'Activo' : 'Inactivo' ?></td>
            <td style="display: flex; gap: 10px;">
              <a href="mantenimientoTrabajadoresEditar.php?iCodTrabajador=<?= $t['iCodTrabajador'] ?>"
                 class="btn btn-primary"
                 style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 14px;"
                 title="Editar">
                 <span class="material-icons">edit</span> Editar
              </a>

              <a href="asignarClaveTrabajador.php?iCodTrabajador=<?= $t['iCodTrabajador'] ?>"
                 class="btn btn-primary"
                 style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 14px;"
                 title="Contraseña">
                 <span class="material-icons">key</span> Contraseña
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="margin-top: 20px; display: flex; justify-content: center;">
      <?php
        $total_paginas = ceil($total / $tampag);
        if ($total_paginas > 1) {
          for ($i = 1; $i <= $total_paginas; $i++) {
            $clase = $i == $pag ? 'btn btn-primary' : 'btn btn-secondary';
            $query = "mantenimientoTrabajadores.php?nombres=$nombres&apellidos=$apellidos&estado=$estado&pag=$i";
            echo "<a href=\"$query\" class=\"$clase\" style=\"margin: 0 5px; text-decoration: none;\">$i</a>";
          }
        }
      ?>
    </div>
  </div>
</div>
