<?php
include 'conexion/conexion.php';
include 'head.php';
session_start();
date_default_timezone_set('America/Lima');
global $cnx;

$iCodTramite = $_GET['iCodTramite'] ?? $_POST['iCodTramite'] ?? null;
if (!$iCodTramite) { die("Código de trámite no proporcionado."); }

$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodOficinaMesaDePartes = 236; // Origen para destinos (Mesa de Partes Física)

// ====== CARGA DE DATOS PRINCIPALES DEL TRÁMITE ======
$sqlT = "SELECT
    cTipoDocumentoSolicitante,
    cNumeroDocumentoSolicitante,
    cCelularSolicitante,
    cCorreoSolicitante,
    cApePaternoSolicitante,
    cApeMaternoSolicitante,
    cNombresSolicitante,
    cDepartamentoSolicitante,
    cProvinciaSolicitante,
    cDistritoSolicitante,
    cDireccionSolicitante,
    cRUCEntidad,
    cRazonSocialEntidad,
    cTipoDocumentoAsegurado,
    cNumeroDocumentoAsegurado,
    cCelularAsegurado,
    cCorreoAsegurado,
    cApePaternoAsegurado,
    cApeMaternoAsegurado,
    cNombresAsegurado,
    cAsunto,
    cObservaciones,
    -- cLinkArchivo,
    documentoElectronico
FROM Tra_M_Tramite
WHERE iCodTramite = ?";
$info = null;
$stmt = sqlsrv_query($cnx, $sqlT, [$iCodTramite]);
if ($stmt) { $info = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC); }
if (!$info) { die("No se encontraron datos del trámite."); }

// ====== CARGA: OFICINAS, JEFES, INDICACIONES ======
$oficinas = [];
$resOf = sqlsrv_query($cnx, "SELECT iCodOficina, cNomOficina, cSiglaOficina FROM Tra_M_Oficinas");
while ($r = sqlsrv_fetch_array($resOf, SQLSRV_FETCH_ASSOC)) { $oficinas[] = $r; }

$jefes = [];
$sqlJ = "SELECT t.iCodOficina, t.iCodTrabajador, tr.cNombresTrabajador, tr.cApellidosTrabajador
         FROM Tra_M_Perfil_Ususario t
         JOIN Tra_M_Trabajadores tr ON t.iCodTrabajador = tr.iCodTrabajador
         WHERE t.iCodPerfil = 3";
$resJ = sqlsrv_query($cnx, $sqlJ);
while ($r = sqlsrv_fetch_array($resJ, SQLSRV_FETCH_ASSOC)) {
  $jefes[$r['iCodOficina']] = [
    'id' => $r['iCodTrabajador'],
    'name' => $r['cNombresTrabajador']." ".$r['cApellidosTrabajador']
  ];
}

$indicaciones = [];
$resInd = sqlsrv_query($cnx, "SELECT iCodIndicacion, cIndicacion FROM Tra_M_Indicaciones");
while ($r = sqlsrv_fetch_array($resInd, SQLSRV_FETCH_ASSOC)) { $indicaciones[] = $r; }

// ====== CARGA: COMPLEMENTARIOS EXISTENTES ======
$complementarios = [];
$sqlD = "SELECT iCodDigital, cDescripcion, fFechaRegistro
         FROM Tra_M_Tramite_Digitales
         WHERE iCodTramite = ?
         ORDER BY iCodDigital DESC";
$resD = sqlsrv_query($cnx, $sqlD, [$iCodTramite]);
while ($r = sqlsrv_fetch_array($resD, SQLSRV_FETCH_ASSOC)) { $complementarios[] = $r; }

// ====== CARGA: DESTINOS EXISTENTES EDITABLES (no recibidos) Y TODOS (para mostrar estado) ======
$destinosTodos = [];
$sqlMovAll = "SELECT M.iCodMovimiento, M.iCodOficinaDerivar, M.fFecRecepcion, M.nEstadoMovimiento,
                     M.iCodTrabajadorDerivar, M.iCodIndicacionDerivar, M.cPrioridadDerivar,
                     O.cNomOficina, O.cSiglaOficina
              FROM Tra_M_Tramite_Movimientos M
              JOIN Tra_M_Oficinas O ON O.iCodOficina = M.iCodOficinaDerivar
              WHERE M.iCodTramite = ? AND M.cFlgTipoMovimiento = '1'
              ORDER BY M.iCodMovimiento ASC";
$resMovAll = sqlsrv_query($cnx, $sqlMovAll, [$iCodTramite]);
while ($r = sqlsrv_fetch_array($resMovAll, SQLSRV_FETCH_ASSOC)) { $destinosTodos[] = $r; }

// Subconjunto editable:
$destinosEditables = array_filter($destinosTodos, function($d){
  return empty($d['fFecRecepcion']) && (intval($d['nEstadoMovimiento']) === 0);
});

// ====== POST: ACTUALIZAR ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1) Campos de solicitante/entidad/asegurado/descripcion
  $tipoDocumento  = $_POST['tipoDocumento'] ?? null;
  $nroDocumento   = $_POST['nroDocumento'] ?? null;
  $celular        = $_POST['celular'] ?? null;
  $correo         = $_POST['correo'] ?? null;
  $apePaterno     = $_POST['apePaterno'] ?? null;
  $apeMaterno     = $_POST['apeMaterno'] ?? null;
  $nombres        = $_POST['nombres'] ?? null;
  $departamento   = $_POST['departamento'] ?? null;
  $provincia      = $_POST['provincia'] ?? null;
  $distrito       = $_POST['distrito'] ?? null;
  $direccion      = $_POST['direccion'] ?? null;

  $ruc            = $_POST['ruc'] ?? null;
  $razonSocial    = $_POST['razonSocial'] ?? null;

  $tdoc_a         = $_POST['tdoc_asegurado'] ?? null;
  $ndoc_a         = $_POST['ndoc_asegurado'] ?? null;
  $cel_a          = $_POST['cel_asegurado'] ?? null;
  $mail_a         = $_POST['email_asegurado'] ?? null;
  $apep_a         = $_POST['apePaterno_asegurado'] ?? null;
  $apem_a         = $_POST['apeMaterno_asegurado'] ?? null;
  $nom_a          = $_POST['nombres_asegurado'] ?? null;

  $asunto         = $_POST['asunto'] ?? null;
  $descripcion    = $_POST['descripcion'] ?? null;
  $link           = $_POST['link'] ?? null;

  // 2) Archivo principal (opcional reemplazo)
  $nuevoPrincipal = null;
  if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { echo "<script>alert('El archivo principal debe ser PDF');</script>"; }
    else {
      $base = pathinfo($_FILES['archivo']['name'], PATHINFO_FILENAME);
      $nuevoPrincipal = $iCodTramite . '-' . preg_replace('/\s+/', '_', $base) . '.pdf';
      move_uploaded_file($_FILES['archivo']['tmp_name'], __DIR__."/cDocumentosFirmados/".$nuevoPrincipal);
    }
  }

  // 3) UPDATE del trámite
  $sqlU = "UPDATE Tra_M_Tramite SET
      cTipoDocumentoSolicitante = ?, cNumeroDocumentoSolicitante = ?, cCelularSolicitante = ?, cCorreoSolicitante = ?,
      cApePaternoSolicitante = ?, cApeMaternoSolicitante = ?, cNombresSolicitante = ?,
      cDepartamentoSolicitante = ?, cProvinciaSolicitante = ?, cDistritoSolicitante = ?, cDireccionSolicitante = ?,
      cRUCEntidad = ?, cRazonSocialEntidad = ?,
      cTipoDocumentoAsegurado = ?, cNumeroDocumentoAsegurado = ?, cCelularAsegurado = ?, cCorreoAsegurado = ?,
      cApePaternoAsegurado = ?, cApeMaternoAsegurado = ?, cNombresAsegurado = ?,
      cLinkArchivo = ?, cAsunto = ?, cObservaciones = ?
      ".($nuevoPrincipal ? ", documentoElectronico = ?" : "")."
      WHERE iCodTramite = ?";
  $paramsU = [
    $tipoDocumento, $nroDocumento, $celular, $correo,
    $apePaterno, $apeMaterno, $nombres,
    $departamento, $provincia, $distrito, $direccion,
    $ruc, $razonSocial,
    $tdoc_a, $ndoc_a, $cel_a, $mail_a,
    $apep_a, $apem_a, $nom_a,
    $link, $asunto, $descripcion
  ];
  if ($nuevoPrincipal) $paramsU[] = $nuevoPrincipal;
  $paramsU[] = $iCodTramite;

  $ok = sqlsrv_query($cnx, $sqlU, $paramsU);

  // 4) Complementarios: subir nuevos (si los hay)
  if (!empty($_FILES['complementarios']['name'][0])) {
    $nombres = $_FILES['complementarios']['name'];
    $tmp     = $_FILES['complementarios']['tmp_name'];
    $errs    = $_FILES['complementarios']['error'];
    for ($i=0; $i<count($nombres); $i++) {
      if ($errs[$i] !== UPLOAD_ERR_OK) continue;
      $ext = strtolower(pathinfo($nombres[$i], PATHINFO_EXTENSION));
      if ($ext !== 'pdf') continue;
      $dest = "cAlmacenArchivos/".$iCodTramite.'-'.str_replace(' ', '_', $nombres[$i]);
      if (move_uploaded_file($tmp[$i], __DIR__.'/'.$dest)) {
        // Inserta usando solo campos requeridos
        $sqlInsD = "INSERT INTO Tra_M_Tramite_Digitales (iCodTramite, cDescripcion, fFechaRegistro)
                    VALUES (?, ?, GETDATE())";
        sqlsrv_query($cnx, $sqlInsD, [$iCodTramite, $nombres[$i]]);
      }
    }
  }

  // 5) Destinos: sincronizar (solo movimientos no recibidos)
  //    a) listado actual editable
  $editables = [];
  $resEd = sqlsrv_query($cnx, $sqlMovAll, [$iCodTramite]);
  while ($r = sqlsrv_fetch_array($resEd, SQLSRV_FETCH_ASSOC)) {
    if (empty($r['fFecRecepcion']) && intval($r['nEstadoMovimiento']) === 0) {
      $editables[(int)$r['iCodOficinaDerivar']] = (int)$r['iCodMovimiento'];
    }
  }

  //    b) nuevos destinos desde el form
  $nuevosDestinos = $_POST['destinos'] ?? []; // elementos "oficina_jefe_indicacion_prioridadTexto"
  $parse = function($val){
    // devuelve [oficinaId, jefeId, indicacionId, prioridadTexto]
    $parts = explode('_', $val);
    return [(int)($parts[0] ?? 0), (int)($parts[1] ?? 0), (int)($parts[2] ?? 0), ($parts[3] ?? '')];
  };

  $nuevosSet = [];
  foreach ($nuevosDestinos as $d) {
    [$ofi,$jefe,$ind,$prioTxt] = $parse($d);
    if ($ofi) $nuevosSet[$ofi] = ['jefe'=>$jefe,'ind'=>$ind,'prioTxt'=>$prioTxt];
  }

  //    c) eliminar editables que ya no vienen en el form
  foreach ($editables as $ofi => $mov) {
    if (!isset($nuevosSet[$ofi])) {
      sqlsrv_query($cnx, "DELETE FROM Tra_M_Tramite_Movimientos WHERE iCodMovimiento = ?", [$mov]);
    }
  }

  //    d) insertar nuevos que no existían como editables
  foreach ($nuevosSet as $ofi => $payload) {
    if (!isset($editables[$ofi])) {
      // cálculo de prioridad en texto → almacenamos como texto (ya viene del form)
      $sqlInsM = "INSERT INTO Tra_M_Tramite_Movimientos
         (EXPEDIENTE, extension, iCodTramite, iCodOficinaOrigen, iCodOficinaDerivar,
          iCodTrabajadorRegistro, iCodTrabajadorDerivar, iCodIndicacionDerivar,
          cPrioridadDerivar, cAsuntoDerivar, cObservacionesDerivar,
          cFlgTipoMovimiento, nEstadoMovimiento, fFecMovimiento, nFlgEnvio, nFlgTipoDoc)
       SELECT T.EXPEDIENTE, T.extension, T.iCodTramite, ?, ?,
              ?, ?, ?,
              ?, T.cAsunto, T.cObservaciones,
              '1', 0, GETDATE(), 0, T.nFlgTipoDoc
       FROM Tra_M_Tramite T WHERE T.iCodTramite = ?";
      sqlsrv_query($cnx, $sqlInsM, [
        $iCodOficinaMesaDePartes,        // Origen MP
        $ofi,                            // Destino
        $iCodTrabajador ?: null,         // Creador movimiento
        $payload['jefe'] ?: null,        // Jefe/Destinatario
        $payload['ind'] ?: null,         // Indicación
        $payload['prioTxt'],             // Prioridad (texto)
        $iCodTramite
      ]);
    }
  }

  echo "<script>alert('Trámite actualizado correctamente'); location.href='bandejaEnviados.php';</script>";
  exit;
}

function cortar30($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    return (mb_strlen($s,'UTF-8') > 30) ? mb_substr($s,0,27,'UTF-8').'...' : $s;
  } else {
    return (strlen($s) > 30) ? substr($s,0,27).'...' : $s;
  }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Editar Trámite Externo</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body{font-family:'Montserrat',sans-serif;margin:0;background:#fff}
    .form-wrapper{display:flex;flex-direction:column;gap:30px;max-width:1200px}
    .row{display:flex;gap:20px;flex-wrap:wrap}
    .input-container{position:relative;flex:1;min-width:250px}
    .input-container input,.input-container select{width:100%;padding:20px 12px 8px;font-size:15px;border:1px solid #ccc;border-radius:4px;background:#fff;box-sizing:border-box}
    .input-container label{position:absolute;top:20px;left:12px;font-size:14px;color:#666;background:#fff;padding:0 4px;pointer-events:none;transition:.2s}
    .input-container input:focus+label,.input-container input:not(:placeholder-shown)+label,.input-container select:focus+label,.input-container select:valid+label{top:0;font-size:12px;color:#333}
    .titulo{font-size:22px;font-weight:bold;margin-bottom:20px;color:#1b53b2}
    .btn{padding:10px 16px;border:none;border-radius:6px;cursor:pointer}
    .btn-primary{background:#005a86;color:#fff}
     .sugerencias-dropdown{position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #ccc;max-height:160px;overflow:auto;display:none}
    .sugerencia-item{padding:8px 10px;cursor:pointer}
    .sugerencia-item:hover{background:#f0f0f0}
    table{width:100%;border-collapse:collapse;font-size:14px}
    thead{background:#f5f5f5}
    th,td{border:1px solid #e5e7eb;padding:8px}
    .chip-adjunto {
    display:inline-flex; align-items:center; background:#fff; border-radius:999px;
    padding:6px 10px; margin:4px 6px 4px 0; font-size:13px; border:1px solid #dadce0;
    text-decoration:none; color:#000; gap:6px
  }
  .chip-adjunto .material-icons { font-size:18px; color:#1a73e8 }
  .chip-close{
    background: transparent;
    border: 0;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    padding: 0 4px;
    color: #d93025;          /* ✅ rojo SIEMPRE */
  }
  .chip-close:hover{
    color: #a30000;          /* ✅ rojo más oscuro al hover (opcional) */
  }
  </style>
</head>
<body>
<div style="margin: 130px auto 0 auto; max-width:1200px; background:white; border:1px solid #ccc; border-radius:10px; padding:40px;">
  <form class="form-wrapper" method="POST" enctype="multipart/form-data">
    <h3 class="titulo">Editar Trámite Externo</h3>

    <!-- ===== Datos del Solicitante ===== -->
    <div class="row">
      <div class="input-container">
        <input type="text" name="tipoDocumento" value="<?= htmlspecialchars($info['cTipoDocumentoSolicitante']) ?>" required>
        <label>Tipo de Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="nroDocumento" value="<?= htmlspecialchars($info['cNumeroDocumentoSolicitante']) ?>" required>
        <label>N° de Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="celular" value="<?= htmlspecialchars($info['cCelularSolicitante']) ?>">
        <label>Celular</label>
      </div>
      
    </div>


    <div class="row">
    <div class="input-container">
        <input type="email" name="correo" value="<?= htmlspecialchars($info['cCorreoSolicitante']) ?>">
        <label>Correo</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="apePaterno" value="<?= htmlspecialchars($info['cApePaternoSolicitante']) ?>">
        <label>Apellido Paterno</label>
      </div>
      <div class="input-container">
        <input type="text" name="apeMaterno" value="<?= htmlspecialchars($info['cApeMaternoSolicitante']) ?>">
        <label>Apellido Materno</label>
      </div>
      <div class="input-container">
        <input type="text" name="nombres" value="<?= htmlspecialchars($info['cNombresSolicitante']) ?>">
        <label>Nombres</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="departamento" value="<?= htmlspecialchars($info['cDepartamentoSolicitante']) ?>">
        <label>Departamento</label>
      </div>
      <div class="input-container">
        <input type="text" name="provincia" value="<?= htmlspecialchars($info['cProvinciaSolicitante']) ?>">
        <label>Provincia</label>
      </div>
      <div class="input-container">
        <input type="text" name="distrito" value="<?= htmlspecialchars($info['cDistritoSolicitante']) ?>">
        <label>Distrito</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container" style="flex:1 1 100%;">
        <input type="text" name="direccion" value="<?= htmlspecialchars($info['cDireccionSolicitante']) ?>">
        <label>Dirección</label>
      </div>
    </div>

    <!-- ===== Entidad ===== -->
    <h3>Entidad Representada</h3>
    <div class="row">
      <div class="input-container">
        <input type="text" name="ruc" value="<?= htmlspecialchars($info['cRUCEntidad']) ?>">
        <label>RUC</label>
      </div>
      <div class="input-container" style="flex:2;">
        <input type="text" name="razonSocial" value="<?= htmlspecialchars($info['cRazonSocialEntidad']) ?>">
        <label>Razón Social</label>
      </div>
    </div>

    <!-- ===== Asegurado ===== -->
    <h3>Datos del Asegurado</h3>
    <div class="row">
      <div class="input-container">
        <input type="text" name="tdoc_asegurado" value="<?= htmlspecialchars($info['cTipoDocumentoAsegurado']) ?>">
        <label>Tipo Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="ndoc_asegurado" value="<?= htmlspecialchars($info['cNumeroDocumentoAsegurado']) ?>">
        <label>N° Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="cel_asegurado" value="<?= htmlspecialchars($info['cCelularAsegurado']) ?>">
        <label>Celular</label>
      </div>
      <div class="input-container">
        <input type="email" name="email_asegurado" value="<?= htmlspecialchars($info['cCorreoAsegurado']) ?>">
        <label>Correo</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="apePaterno_asegurado" value="<?= htmlspecialchars($info['cApePaternoAsegurado']) ?>">
        <label>Apellido Paterno</label>
      </div>
      <div class="input-container">
        <input type="text" name="apeMaterno_asegurado" value="<?= htmlspecialchars($info['cApeMaternoAsegurado']) ?>">
        <label>Apellido Materno</label>
      </div>
      <div class="input-container">
        <input type="text" name="nombres_asegurado" value="<?= htmlspecialchars($info['cNombresAsegurado']) ?>">
        <label>Nombres</label>
      </div>
    </div>

    <!-- ===== Descripción ===== -->
    <h3>Descripción del Trámite / Solicitud</h3>
    <div class="row">
      <div class="input-container" style="flex:1 1 100%;">
        <input type="text" name="asunto" value="<?= htmlspecialchars($info['cAsunto']) ?>">
        <label>Asunto</label>
      </div>
    </div>
    <div class="row">
      <div class="input-container" style="flex:1 1 100%;">
        <input type="text" name="descripcion" value="<?= htmlspecialchars($info['cObservaciones']) ?>" style="height:80px;padding-top:24px;line-height:1.4;">
        <label>Descripción</label>
      </div>
    </div>

    <!-- ===== Archivo Principal ===== -->
    <div class="row" style="flex-direction:column;gap:10px;">
      <label style="font-weight:bold;">Documento Principal:</label>
      <?php if (!empty($info['documentoElectronico'])): 
        $docFull = htmlspecialchars($info['documentoElectronico']);
      $docShort = htmlspecialchars(cortar30($info['documentoElectronico']));?>
          <a class="chip-adjunto" href="cDocumentosFirmados/<?= $docFull ?>" target="_blank" title="<?= $docFull ?>">
    <span class="material-icons">picture_as_pdf</span>
    <span class="chip-text"><?= $docShort ?></span>
  </a>
      <?php else: ?>
        <span>No se adjuntó archivo</span>
      <?php endif; ?>
      <div class="input-container" style="flex:1 1 100%;">
        <input type="file" name="archivo" accept="application/pdf">
        <label>Reemplazar Documento Principal</label>
      </div>
    </div>

    <!-- ===== Link ===== -->
    <!-- <div class="row">
      <div class="input-container" style="flex:1 1 100%;">
        <input type="text" name="link" value="<?= htmlspecialchars($info['cLinkArchivo']) ?>">
        <label>Link de descarga (opcional)</label>
      </div>
    </div> -->

    <!-- ===== Complementarios ===== -->
    <h3>Archivos Complementarios</h3>
    <div class="row" style="flex-direction:column;gap:10px;">
      <!-- Subir nuevos -->
      <div class="input-container" style="flex:1 1 100%;">
        <input type="file" name="complementarios[]" id="complementarios" multiple accept="application/pdf">
        <label>Agregar PDF complementarios</label>
      </div>
      <!-- Listado existentes (chips con aspa) -->
      <div>
        <?php if (count($complementarios) === 0): ?>
          <span style="color:#666;">No hay complementarios cargados.</span>
        <?php else: ?>
          <?php foreach ($complementarios as $c):
                $nameFull  = htmlspecialchars($c['cDescripcion']);
                $nameShort = htmlspecialchars(cortar30($c['cDescripcion']));
          ?>
            <span class="chip-adjunto" title="<?= $nameFull ?>">
              <span class="material-icons">description</span>
              <span class="chip-text"><?= $nameShort ?></span>
              <button
                type="button"
                class="chip-close"
                title="Eliminar archivo"
                aria-label="Eliminar archivo"
                onclick="eliminarComplementario(<?= (int)$c['iCodDigital'] ?>)">
                &times;
              </button>
            </span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <!-- ===== Oficinas de destino (Editor) ===== -->
    <h3>Búsqueda de Oficinas</h3>
    <div class="form-row">

      <div class="input-container oficina-ancha" style="position:relative; ">
        <input type="text" id="nombreOficinaInput" placeholder=" ">
        <label>Nombre de Oficina</label>
        <input type="hidden" id="oficinasDestino">
        <div id="sugerenciasOficinas" class="sugerencias-dropdown"></div>
      </div>

      <div class="input-container" style="min-width:240px;">
        <input type="text" id="jefeOficina" placeholder=" " readonly>
        <label>Jefe</label>
      </div>

      <div class="input-container">
          <select id="indicacion" style="padding:20px 12px 8px">
            <option value="" disabled hidden></option>
            <?php foreach($indicaciones as $ind): ?>
              <option value="<?= $ind['iCodIndicacion'] ?>" <?= ($ind['iCodIndicacion']==2?'selected':'') ?>>
                <?= htmlentities($ind['cIndicacion']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <label>Indicación</label>
      </div>

      <div class="input-container select-flotante prioridad-reducida">
                <select id="prioridad" name="prioridad" required>
          <option value="" disabled hidden></option>
          <option value="Baja">Baja</option>
          <option value="Media" selected>Media</option>
          <option value="Alta">Alta</option>
        </select>
        <label>Prioridad</label>
      </div>
      <div style="display:flex;align-items:flex-end;">
        <button type="button" class="btn btn-primary" onclick="agregarDestino()">Agregar</button>
      </div>
    </div>

    <div class="row" id="tablaDestinosWrap" style="margin-top:10px;">
      <div class="input-container" style="flex:1 1 100%; min-width:100%;">
        <table id="tablaDestinos">
          <thead>
            <tr>
              <th>Oficina</th>
              <th>Jefe</th>
              <th>Indicación</th>
              <th>Prioridad</th>
            
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php
            // Renderizamos todos para visualización, pero solo se podrá eliminar los editables (no recibidos)
            $oficinasAgregadasJS = [];
            foreach ($destinosTodos as $d):
              $editable = (empty($d['fFecRecepcion']) && intval($d['nEstadoMovimiento'])===0);
              $ofiTxt = trim($d['cNomOficina']).' - '.trim($d['cSiglaOficina']);
              if ($editable) { $oficinasAgregadasJS[] = (int)$d['iCodOficinaDerivar']; }
          ?>
            <tr data-oficina="<?= (int)$d['iCodOficinaDerivar'] ?>" data-editable="<?= $editable ? '1':'0' ?>">
              <input type="hidden" name="destinos[]" value="<?= (int)$d['iCodOficinaDerivar'].'_'.((int)$d['iCodTrabajadorDerivar']).'_'.((int)$d['iCodIndicacionDerivar']).'_'.htmlentities($d['cPrioridadDerivar']) ?>">
              <td><?= htmlentities($ofiTxt) ?></td>
              <td><?= isset($jefes[(int)$d['iCodOficinaDerivar']]) ? htmlentities($jefes[(int)$d['iCodOficinaDerivar']]['name']) : '' ?></td>
              <td><?= (int)$d['iCodIndicacionDerivar'] ?></td>
              <td><?= htmlentities($d['cPrioridadDerivar']) ?></td>
               <td>
                <?php if ($editable): ?>
                  <button type="button" class="btn btn-secondary" onclick="eliminarDestino(this)">Eliminar</button>
                <?php else: ?>
                  <span style="color:#888;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Botón Actualizar -->
    <div class="row" style="justify-content:center;margin-top:20px;">
      <button type="submit" class="btn btn-primary">Actualizar</button>
    </div>

    <input type="hidden" name="iCodTramite" value="<?= (int)$iCodTramite ?>">
  </form>
</div>

<script>
  // ====== DATA desde PHP ======
  const OFICINAS = <?= json_encode($oficinas, JSON_UNESCAPED_UNICODE) ?>;
  const JEFES    = <?= json_encode($jefes, JSON_UNESCAPED_UNICODE) ?>;

  // Mantener un set de oficinas ya agregadas (solo las editables)
  const oficinasAgregadas = new Set(<?= json_encode($oficinasAgregadasJS ?? [], JSON_UNESCAPED_UNICODE) ?>);

  function mostrarSugerenciasOficinas(filtro=""){
    const cont = document.getElementById('sugerenciasOficinas');
    cont.innerHTML = "";
    const fl = filtro.toLowerCase();
    const res = OFICINAS.filter(o =>
      o.cNomOficina.toLowerCase().includes(fl) ||
      o.cSiglaOficina.toLowerCase().includes(fl)
    );
    if (res.length === 0) {
      cont.innerHTML = '<div class="sugerencia-item" style="color:#888">Sin resultados</div>';
    } else {
      res.forEach(ofi => {
        const txt = `${ofi.cNomOficina} - ${ofi.cSiglaOficina}`;
        const div = document.createElement('div');
        div.className = 'sugerencia-item';
        div.textContent = txt;
        div.onclick = () => {
          document.getElementById('nombreOficinaInput').value = txt;
          document.getElementById('oficinasDestino').value = ofi.iCodOficina;
          const jefe = JEFES[ofi.iCodOficina];
          const jefeInput = document.getElementById('jefeOficina');
          jefeInput.value = jefe ? jefe.name : '';
          jefeInput.dataset.jefeid = jefe ? jefe.id : '';
          cont.style.display = 'none';
        };
        cont.appendChild(div);
      });
    }
    cont.style.display = 'block';
  }

  document.getElementById('nombreOficinaInput').addEventListener('focus', e=>{
    if (e.target.value.trim()==='') mostrarSugerenciasOficinas('');
  });
  document.getElementById('nombreOficinaInput').addEventListener('input', e=>{
    const t = e.target.value.trim();
    if (t.length>=1) mostrarSugerenciasOficinas(t);
    else document.getElementById('sugerenciasOficinas').style.display='none';
  });
  document.addEventListener('click', e=>{
    if (!e.target.closest('#nombreOficinaInput,#sugerenciasOficinas')) {
      document.getElementById('sugerenciasOficinas').style.display='none';
    }
  });

  function agregarDestino(){
    const oficinaId = document.getElementById('oficinasDestino').value;
    const oficinaTxt= document.getElementById('nombreOficinaInput').value;
    const jefeInput = document.getElementById('jefeOficina');
    const jefeName  = jefeInput.value;
    const jefeId    = jefeInput.dataset.jefeid || '';
    const indSel    = document.getElementById('indicacion');
    const indVal    = indSel.value;
    const indTxt    = indSel.options[indSel.selectedIndex]?.text || '';
    const prSel     = document.getElementById('prioridad');
    const prTxt     = prSel.options[prSel.selectedIndex]?.text || '';

    if (!oficinaId || !indVal || !prTxt) { alert("Completa oficina, indicación y prioridad."); return; }
    if (oficinasAgregadas.has(parseInt(oficinaId))) { alert("Esta oficina ya fue agregada (o es no editable actualmente)."); return; }

    oficinasAgregadas.add(parseInt(oficinaId));
    const tbody = document.querySelector('#tablaDestinos tbody');
    const tr = document.createElement('tr');
    tr.setAttribute('data-oficina', oficinaId);
    tr.setAttribute('data-editable', '1');
    tr.innerHTML = `
      <input type="hidden" name="destinos[]" value="${oficinaId}_${jefeId}_${indVal}_${prTxt}">
      <td>${oficinaTxt}</td>
      <td>${jefeName}</td>
      <td>${indTxt}</td>
      <td>${prTxt}</td>
      <td>Pendiente</td>
      <td><button type="button" class="btn btn-secondary" onclick="eliminarDestino(this)">Quitar</button></td>
    `;
    tbody.appendChild(tr);

    // limpiar campos
    document.getElementById('nombreOficinaInput').value = '';
    document.getElementById('oficinasDestino').value = '';
    jefeInput.value = ''; jefeInput.dataset.jefeid = '';
    document.getElementById('indicacion').value = '2';
    document.getElementById('prioridad').selectedIndex = 1;
  }

  function eliminarDestino(btn){
    const tr = btn.closest('tr');
    if (tr.getAttribute('data-editable') !== '1') { alert("Este destino ya fue recibido y no puede eliminarse."); return; }
    const oficinaId = parseInt(tr.getAttribute('data-oficina'));
    oficinasAgregadas.delete(oficinaId);
    tr.remove();
  }

  // ===== Complementarios: eliminar vía AJAX =====
  async function eliminarComplementario(idDigital){
    if (!confirm('¿Eliminar este archivo complementario?')) return;
    try{
      const fd = new FormData();
      fd.append('iCodDigital', idDigital);
      const res = await fetch('eliminarComplementarioMP.php', { method:'POST', body: fd });
      const js = await res.json();
      if (js.status === 'ok') location.reload();
      else alert(js.message || 'No se pudo eliminar.');
    }catch(e){ alert('Error eliminando complementario.'); }
  }
</script>
</body>
</html>
