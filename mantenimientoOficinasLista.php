<?php
include("head.php");
include("conexion/conexion.php");
global $cnx;

$iCodOficina = isset($_GET['iCodOficina']) ? intval($_GET['iCodOficina']) : 0;

$sql = "
SELECT 
    T.iCodTrabajador,
    T.cApellidosTrabajador,
    T.cNombresTrabajador,
    T.cUsuario,
    P.iCodPerfil,
    PF.cDescPerfil,
    O.cNomOficina
FROM Tra_M_Perfil_Ususario P
JOIN Tra_M_Trabajadores T ON P.iCodTrabajador = T.iCodTrabajador
JOIN Tra_M_Perfil PF ON P.iCodPerfil = PF.iCodPerfil
JOIN Tra_M_Oficinas O ON P.iCodOficina = O.iCodOficina
WHERE O.iCodOficina = ?
ORDER BY T.cApellidosTrabajador, T.cNombresTrabajador;
";

$params = [$iCodOficina];
$stmt = sqlsrv_query($cnx, $sql, $params);
$datos = [];
$nombreOficina = "";
$trabajadoresUnicos = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $datos[] = $row;
        $nombreOficina = $row['cNomOficina'];
        $trabajadoresUnicos[$row['iCodTrabajador']] = true;
    }
}
$totalTrabajadores = count($trabajadoresUnicos);
?>

<div class="container" style="margin: 120px auto; max-width: 1600px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <div class="titulo-principal">LISTADO DE TRABAJADORES POR OFICINA: <?= htmlspecialchars($nombreOficina) ?></div>
  <div style="margin-bottom: 15px; color: #555; font-weight: bold;">Total de trabajadores: <?= $totalTrabajadores ?></div>

  <div class="card" style="margin-top: 20px;">
    <table class="table table-bordered table-hover">
      <thead class="table-secondary">
        <tr>
          <th>Trabajador</th>
          <th>Usuario</th>
          <th>Perfil</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($datos as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['cApellidosTrabajador'] . ', ' . $row['cNombresTrabajador']) ?></td>
            <td><?= htmlspecialchars($row['cUsuario']) ?></td>
            <td><?= htmlspecialchars($row['cDescPerfil']) ?></td>
            <td>
              <button class="btn btn-danger"
                      style="padding: 4px 10px; font-size: 13px;"
                      onclick="eliminarPerfil(<?= $row['iCodTrabajador'] ?>, <?= $row['iCodPerfil'] ?>)">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">delete</span>
                Eliminar Perfil
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  function eliminarPerfil(iCodTrabajador, iCodPerfil) {
    if (!confirm('¿Estás seguro de eliminar este perfil de este trabajador?')) return;

    fetch('mantenimientoOficinaListaEliminarPerfil.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `iCodTrabajador=${iCodTrabajador}&iCodPerfil=${iCodPerfil}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        location.reload();
      } else {
        alert('Error al eliminar perfil');
      }
    })
    .catch(() => alert('Error de conexión'));
  }
</script>
