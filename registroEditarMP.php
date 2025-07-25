<?php
include 'conexion/conexion.php';
include 'head.php';

session_start();

$iCodTramite = $_GET['iCodTramite'] ?? null;

if (!$iCodTramite) {
    die("Código de trámite no proporcionado.");
}

// Consulta del trámite
$sql = "SELECT
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
    cLinkArchivo,
    documentoElectronico
FROM Tra_M_Tramite
WHERE iCodTramite = ?";

$params = [$iCodTramite];
$stmt = sqlsrv_query($cnx, $sql, $params);

if (!$stmt || !($info = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die("No se encontraron datos del trámite.");
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
    body {
      font-family: 'Montserrat', sans-serif;
      margin: 0;
      background: #fff;
    }
    .form-wrapper {
      display: flex;
      flex-direction: column;
      gap: 30px;
      max-width: 1200px;
    }
    .row {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    .input-container {
      position: relative;
      flex: 1;
      min-width: 250px;
    }
    .input-container input,
    .input-container select {
      width: 100%;
      padding: 20px 12px 8px;
      font-size: 15px;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      box-sizing: border-box;
    }
    .input-container label {
      position: absolute;
      top: 20px;
      left: 12px;
      font-size: 14px;
      color: #666;
      background: #fff;
      padding: 0 4px;
      pointer-events: none;
      transition: 0.2s ease;
    }
    .input-container input:focus + label,
    .input-container input:not(:placeholder-shown) + label,
    .input-container select:focus + label,
    .input-container select:valid + label {
      top: 0px;
      font-size: 12px;
      color: #333;
    }
    .titulo {
      font-size: 22px;
      font-weight: bold;
      margin-bottom: 20px;
      color: #1b53b2;
    }
  </style>
</head>
<body>

<div style="margin: 120px auto; max-width: 1000px; background:white; border: 1px solid #ccc; border-radius: 10px; padding: 40px;">
  <form class="form-wrapper" method="POST" enctype="multipart/form-data">
    <h3 class="titulo">Editar Trámite Externo</h3>

    <!-- Sección: Datos del Solicitante -->
    <div class="row">
      <div class="input-container">
        <input type="text" name="tipoDocumento" value="<?= htmlspecialchars($info['cTipoDocumentoSolicitante']) ?>" required>
        <label for="tipoDocumento">Tipo de Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="nroDocumento" value="<?= htmlspecialchars($info['cNumeroDocumentoSolicitante']) ?>" required>
        <label for="nroDocumento">N° de Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="celular" value="<?= htmlspecialchars($info['cCelularSolicitante']) ?>">
        <label for="celular">Celular</label>
      </div>
      <div class="input-container">
        <input type="email" name="correo" value="<?= htmlspecialchars($info['cCorreoSolicitante']) ?>">
        <label for="correo">Correo</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="apePaterno" value="<?= htmlspecialchars($info['cApePaternoSolicitante']) ?>">
        <label for="apePaterno">Apellido Paterno</label>
      </div>
      <div class="input-container">
        <input type="text" name="apeMaterno" value="<?= htmlspecialchars($info['cApeMaternoSolicitante']) ?>">
        <label for="apeMaterno">Apellido Materno</label>
      </div>
      <div class="input-container">
        <input type="text" name="nombres" value="<?= htmlspecialchars($info['cNombresSolicitante']) ?>">
        <label for="nombres">Nombres</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="departamento" value="<?= htmlspecialchars($info['cDepartamentoSolicitante']) ?>">
        <label for="departamento">Departamento</label>
      </div>
      <div class="input-container">
        <input type="text" name="provincia" value="<?= htmlspecialchars($info['cProvinciaSolicitante']) ?>">
        <label for="provincia">Provincia</label>
      </div>
      <div class="input-container">
        <input type="text" name="distrito" value="<?= htmlspecialchars($info['cDistritoSolicitante']) ?>">
        <label for="distrito">Distrito</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container" style="flex: 1 1 100%;">
        <input type="text" name="direccion" value="<?= htmlspecialchars($info['cDireccionSolicitante']) ?>">
        <label for="direccion">Dirección</label>
      </div>
    </div>
    <!-- Sección: Entidad Representada -->
    <h3>Entidad Representada</h3>
    <div class="row">
      <div class="input-container">
        <input type="text" name="ruc" value="<?= htmlspecialchars($info['cRUCEntidad']) ?>">
        <label for="ruc">RUC</label>
      </div>
      <div class="input-container" style="flex: 2;">
        <input type="text" name="razonSocial" value="<?= htmlspecialchars($info['cRazonSocialEntidad']) ?>">
        <label for="razonSocial">Razón Social</label>
      </div>
    </div>

    <!-- Sección: Asegurado -->
    <h3>Datos del Asegurado</h3>
    <div class="row">
      <div class="input-container">
        <input type="text" name="tdoc_asegurado" value="<?= htmlspecialchars($info['cTipoDocumentoAsegurado']) ?>">
        <label for="tdoc_asegurado">Tipo Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="ndoc_asegurado" value="<?= htmlspecialchars($info['cNumeroDocumentoAsegurado']) ?>">
        <label for="ndoc_asegurado">N° Documento</label>
      </div>
      <div class="input-container">
        <input type="text" name="cel_asegurado" value="<?= htmlspecialchars($info['cCelularAsegurado']) ?>">
        <label for="cel_asegurado">Celular</label>
      </div>
      <div class="input-container">
        <input type="email" name="email_asegurado" value="<?= htmlspecialchars($info['cCorreoAsegurado']) ?>">
        <label for="email_asegurado">Correo</label>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" name="apePaterno_asegurado" value="<?= htmlspecialchars($info['cApePaternoAsegurado']) ?>">
        <label for="apePaterno_asegurado">Apellido Paterno</label>
      </div>
      <div class="input-container">
        <input type="text" name="apeMaterno_asegurado" value="<?= htmlspecialchars($info['cApeMaternoAsegurado']) ?>">
        <label for="apeMaterno_asegurado">Apellido Materno</label>
      </div>
      <div class="input-container">
        <input type="text" name="nombres_asegurado" value="<?= htmlspecialchars($info['cNombresAsegurado']) ?>">
        <label for="nombres_asegurado">Nombres</label>
      </div>
    </div>

    <!-- Sección: Descripción -->
    <h3>Descripción del Trámite / Solicitud</h3>
    <div class="row">
      <div class="input-container" style="flex: 1 1 100%;">
        <input type="text" name="asunto" value="<?= htmlspecialchars($info['cAsunto']) ?>">
        <label for="asunto">Asunto</label>
      </div>
    </div>
    <div class="row">
      <div class="input-container" style="flex: 1 1 100%;">
        <input type="text" name="descripcion" value="<?= htmlspecialchars($info['cObservaciones']) ?>" style="height: 80px; padding-top: 24px; line-height: 1.4;">
        <label for="descripcion">Descripción</label>
      </div>
    </div>

    <!-- Sección: Archivo y link -->
    <div class="row" style="flex-direction: column; gap: 10px;">
      <label style="font-weight: bold;">Archivo Actual:</label>
      <?php if (!empty($info['documentoElectronico'])): ?>
        <a href="cDocumentosFirmados/<?= htmlspecialchars($info['documentoElectronico']) ?>" target="_blank">
          <?= htmlspecialchars($info['documentoElectronico']) ?>
        </a>
      <?php else: ?>
        <span>No se adjuntó archivo</span>
      <?php endif; ?>
    </div>

    <div class="row">
      <div class="input-container" style="flex: 1 1 100%;">
        
        <input type="file" name="archivo" accept="application/pdf">
      </div>
    </div>

    <div class="row">
      <div class="input-container" style="flex: 1 1 100%;">
        <input type="text" name="link" value="<?= htmlspecialchars($info['cLinkArchivo']) ?>">
        <label for="link">Link de descarga (opcional)</label>
      </div>
    </div>

    <!-- Botón Actualizar -->
    <div class="row" style="justify-content: center; margin-top: 30px;">
      <button type="submit" style="padding: 12px 24px; background-color: #007bff; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">Actualizar</button>
    </div>

    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
  </form>
</div>
</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iCodTramite = $_POST['iCodTramite'] ?? null;

    // Campos solicitante
    $tipoDocumento = $_POST['tipoDocumento'] ?? null;
    $nroDocumento = $_POST['nroDocumento'] ?? null;
    $celular = $_POST['celular'] ?? null;
    $correo = $_POST['correo'] ?? null;
    $apePaterno = $_POST['apePaterno'] ?? null;
    $apeMaterno = $_POST['apeMaterno'] ?? null;
    $nombres = $_POST['nombres'] ?? null;
    $departamento = $_POST['departamento'] ?? null;
    $provincia = $_POST['provincia'] ?? null;
    $distrito = $_POST['distrito'] ?? null;
    $direccion = $_POST['direccion'] ?? null;

    // Entidad
    $ruc = $_POST['ruc'] ?? null;
    $razonSocial = $_POST['razonSocial'] ?? null;

    // Asegurado
    $tdoc_asegurado = $_POST['tdoc_asegurado'] ?? null;
    $ndoc_asegurado = $_POST['ndoc_asegurado'] ?? null;
    $cel_asegurado = $_POST['cel_asegurado'] ?? null;
    $email_asegurado = $_POST['email_asegurado'] ?? null;
    $apePaterno_asegurado = $_POST['apePaterno_asegurado'] ?? null;
    $apeMaterno_asegurado = $_POST['apeMaterno_asegurado'] ?? null;
    $nombres_asegurado = $_POST['nombres_asegurado'] ?? null;

    // Descripción
    $asunto = $_POST['asunto'] ?? null;
    $descripcion = $_POST['descripcion'] ?? null;
    $link = $_POST['link'] ?? null;

    // Procesar archivo si se subió uno nuevo
    $nombreArchivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $nombreArchivo = $iCodTramite . '-' . preg_replace('/\s+/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME)) . '.pdf';
            $destino = __DIR__ . "/cDocumentosFirmados/" . $nombreArchivo;
            move_uploaded_file($archivo['tmp_name'], $destino);
        } else {
            echo "<script>alert('El archivo debe ser PDF');</script>";
            $nombreArchivo = null;
        }
    }

    // UPDATE
    $sqlUpdate = "
        UPDATE Tra_M_Tramite SET
            cTipoDocumentoSolicitante = ?, cNumeroDocumentoSolicitante = ?, cCelularSolicitante = ?, cCorreoSolicitante = ?,
            cApePaternoSolicitante = ?, cApeMaternoSolicitante = ?, cNombresSolicitante = ?,
            cDepartamentoSolicitante = ?, cProvinciaSolicitante = ?, cDistritoSolicitante = ?, cDireccionSolicitante = ?,
            cRUCEntidad = ?, cRazonSocialEntidad = ?,
            cTipoDocumentoAsegurado = ?, cNumeroDocumentoAsegurado = ?, cCelularAsegurado = ?, cCorreoAsegurado = ?,
            cApePaternoAsegurado = ?, cApeMaternoAsegurado = ?, cNombresAsegurado = ?,
            cLinkArchivo = ?, cAsunto = ?, cObservaciones = ?
            " . ($nombreArchivo ? ", documentoElectronico = ?" : "") . "
        WHERE iCodTramite = ?
    ";

    $params = [
        $tipoDocumento, $nroDocumento, $celular, $correo,
        $apePaterno, $apeMaterno, $nombres,
        $departamento, $provincia, $distrito, $direccion,
        $ruc, $razonSocial,
        $tdoc_asegurado, $ndoc_asegurado, $cel_asegurado, $email_asegurado,
        $apePaterno_asegurado, $apeMaterno_asegurado, $nombres_asegurado,
        $link, $asunto, $descripcion
    ];
    if ($nombreArchivo) $params[] = $nombreArchivo;
    $params[] = $iCodTramite;

    $stmtUpdate = sqlsrv_prepare($cnx, $sqlUpdate, $params);

    if (sqlsrv_execute($stmtUpdate)) {
        echo "<script>alert('Trámite actualizado correctamente'); location.href='bandejaEnviados.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error al actualizar');</script>";
    }
}

?>