<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

// Parámetros de búsqueda
$nombre = $_GET['nombre'] ?? '';
$sigla = $_GET['sigla'] ?? '';
$estado = $_GET['estado'] ?? '';

// Paginación
$tampag = 15;
$pag = isset($_GET['pag']) && is_numeric($_GET['pag']) ? intval($_GET['pag']) : 1;
$reg1 = ($pag - 1) * $tampag;

// Llamar al SP (sin ordenamiento ni tipo de oficina)
$sql = "{CALL SP_OFICINA_LISTA_NUEVA (?, ?, ?)}";
$params = [$nombre ?: null, $sigla ?: null, $estado !== '' ? intval($estado) : null];
$stmt = sqlsrv_query($cnx, $sql, $params);

$oficinas = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $oficinas[] = $row;
    }
}

$total = count($oficinas);
$oficinas = array_slice($oficinas, $reg1, $tampag);
?>
<div class="container" style="margin: 120px auto; max-width: 1500px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">MANTENIMIENTO DE OFICINAS</div>

  <div class="card">
  <div class="card-title" style="margin-bottom: 20px;">FILTROS DE BÚSQUEDA</div>
    <form method="get">
      <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="input-container" style="flex: 1 1 30%;">
          <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder=" ">
          <label>Nombre de Oficina</label>
        </div>
        <div class="input-container" style="flex: 1 1 30%;">
          <input type="text" name="sigla" value="<?= htmlspecialchars($sigla) ?>" placeholder=" ">
          <label>Sigla de Oficina</label>
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
        <button type="submit" class="btn btn-primary" style="min-width: 180px;"><span class="material-icons">search</span> Buscar</button>
        <button type="button" onclick="window.location.href='mantenimientoOficinas.php'" class="btn btn-secondary" style="min-width: 180px;"><span class="material-icons">autorenew</span> Reestablecer</button>
        <button type="button" onclick="window.location.href='nuevaOficina.php'" class="btn btn-primary" style="min-width: 180px;"><span class="material-icons">add</span> Crear Oficina</button>
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
          <th>Oficina</th>
          <th>Sigla</th>
          <th>Estado</th>
          <th>Opciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($oficinas as $of): ?>
          <tr>
            <td><?= htmlspecialchars($of['cNomOficina']) ?></td>
            <td><?= htmlspecialchars($of['cSiglaOficina']) ?></td>
            <td><?= $of['iFlgEstado'] == 1 ? 'Activo' : 'Inactivo' ?></td>
            <td>
            <a href="mantenimientoOficinasEditar.php?iCodOficina=<?= $of['iCodOficina'] ?>" 
                class="btn btn-primary"
                style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 14px;"
                title="Editar">
                <span class="material-icons" style="font-size: 18px;">edit</span> Editar
                </a>   
              <a href="mantenimientoOficinasLista.php?iCodOficina=<?= $of['iCodOficina'] ?>" 
              class="btn btn-secondary"
              style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 14px; margin-left: 8px;"
              title="Ver Lista de Usuarios">
              <span class="material-icons" style="font-size: 18px;">list</span> Lista
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
            $query = "mantenimientoOficinas.php?nombre=$nombre&sigla=$sigla&estado=$estado&pag=$i";
            echo "<a href=\"$query\" class=\"$clase\" style=\"margin: 0 5px; text-decoration: none;\">$i</a>";
        }
        }
      ?>
    </div>
  </div>
</div>
