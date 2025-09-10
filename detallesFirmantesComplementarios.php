<?php
session_start();
include("conexion/conexion.php");
header('Content-Type: text/html; charset=UTF-8');

$iCodTramite = isset($_GET['iCodTramite']) ? (int)$_GET['iCodTramite'] : 0;
if (!$iCodTramite) { echo "<p>Falta iCodTramite</p>"; exit; }

/* 1) Mapeo: iCodDigital -> Título (cDescripcion, fallback cNombreNuevo) */
$sqlComp = "
  SELECT d.iCodDigital, d.cDescripcion, d.cNombreNuevo
  FROM Tra_M_Tramite_Digitales d
  WHERE d.iCodTramite = ?
  ORDER BY d.iCodDigital DESC
";
$stmt = sqlsrv_query($cnx, $sqlComp, [$iCodTramite]);

$compIds = [];
$titulos = []; // [iCodDigital] => 'Título del documento'
while ($c = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $id = (int)$c['iCodDigital'];
  $compIds[] = $id;

  $titulo = trim((string)($c['cDescripcion'] ?? ''));
  if ($titulo === '') $titulo = trim((string)($c['cNombreNuevo'] ?? ''));
  if ($titulo === '') $titulo = "Documento $id";

  $titulos[$id] = $titulo;
}

if (!$compIds) {
  echo '<p style="color:#777">No hay documentos complementarios.</p>';
  exit;
}

/* 2) Firmantes por cada complementario */
$in = implode(',', array_map('intval', $compIds));
$sqlFir = "
  SELECT f.iCodDigital, f.posicion, f.tipoFirma, f.nFlgFirma,
         t.cNombresTrabajador, t.cApellidosTrabajador, o.cNomOficina
  FROM Tra_M_Tramite_Firma f
  JOIN Tra_M_Trabajadores t ON t.iCodTrabajador = f.iCodTrabajador
  JOIN Tra_M_Oficinas o     ON o.iCodOficina     = f.iCodOficina
  WHERE f.iCodTramite = ? AND f.iCodDigital IN ($in) AND f.nFlgEstado = 1
  ORDER BY f.iCodDigital,
           CASE WHEN f.tipoFirma = 1 THEN 0 ELSE 1 END,
           f.posicion
";
$stmt2 = sqlsrv_query($cnx, $sqlFir, [$iCodTramite]);

$byDig = [];
while ($f = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
  $byDig[(int)$f['iCodDigital']][] = $f;
}

/* 3) Render: Solo “Firmantes por Documento Complementario”, con NOMBRE */
echo '<div style="font-weight:600;margin:10px 0">Firmantes por Documento Complementario</div>';

foreach ($compIds as $id) {
  $tituloDoc = htmlspecialchars($titulos[$id] ?? ("Documento $id"));
  echo '<div style="margin:8px 0;font-weight:600">'.$tituloDoc.'</div>';

  echo '<table class="table table-sm" style="width:100%"><thead>
          <tr><th>Pos.</th><th>Trabajador</th><th>Oficina</th><th>Tipo</th><th>Estado</th></tr>
        </thead><tbody>';

  if (!empty($byDig[$id])) {
    foreach ($byDig[$id] as $f) {
      $tipo   = ((int)$f['tipoFirma'] === 1 ? 'Principal' : 'Visto Bueno');
      $estado = ((int)$f['nFlgFirma'] === 3 ? 'Firmado' : 'Pendiente');
      $nom    = trim($f['cNombresTrabajador'].' '.$f['cApellidosTrabajador']);
      echo '<tr>
              <td>'.htmlspecialchars($f['posicion']).'</td>
              <td>'.htmlspecialchars($nom).'</td>
              <td>'.htmlspecialchars($f['cNomOficina']).'</td>
              <td>'.$tipo.'</td>
              <td>'.$estado.'</td>
            </tr>';
    }
  } else {
    echo '<tr><td colspan="5" style="color:#777">Sin firmantes.</td></tr>';
  }

  echo '</tbody></table>';
}
