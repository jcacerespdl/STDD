<?php
include("head.php");
include("conexion/conexion.php");
session_start();

if (!isset($_GET['iCodMovimiento'])) {
    echo "<p>Error: Movimiento no especificado.</p>";
    exit;
}

$iCodMovimiento = intval($_GET['iCodMovimiento']);

// Consulta corregida con campos existentes
$sql = "SELECT T.expediente, T.cAsunto, T.documentoElectronico
        FROM Tra_M_Tramite_Movimientos M
        INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M.iCodTramite
        WHERE M.iCodMovimiento = ?";

$stmt = sqlsrv_query($cnx, $sql, [$iCodMovimiento]);

if ($stmt === false) {
    echo "<pre>Error en la consulta:\n";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo "<p>Error: Movimiento no encontrado.</p>";
    exit;
}

$expediente = $data['expediente'];
$asunto = $data['cAsunto'];
$docPrincipal = $data['documentoElectronico'];
?>

<div class="container" style="max-width: 600px; margin: 140px auto 2rem;">
  <h2>Finalizar Expediente: <?= htmlspecialchars($expediente) ?></h2>

  <div style="margin-top: 1rem;">
    <strong>Documento Principal:</strong><br>
    <a href="cDocumentosFirmados/<?= urlencode($docPrincipal) ?>" target="_blank" style="color: #0066cc;">
      <span class="material-icons">picture_as_pdf</span> <?= htmlspecialchars($docPrincipal) ?>
    </a>
  </div>

  <div style="margin-top: 1.5rem;">
    <strong>Asunto:</strong><br>
    <p style="margin-top: 0.2rem;"><?= nl2br(htmlspecialchars($asunto)) ?></p>
  </div>

  <form id="finalizarForm" method="POST" enctype="multipart/form-data" action="archivarMovimiento.php" style="margin-top: 2rem;">
    <input type="hidden" name="iCodMovimiento" value="<?= $iCodMovimiento ?>">

    <div style="margin-bottom: 1rem;">
      <label for="observaciones">Observaciones de Finalización:</label><br>
      <textarea name="observaciones" rows="4" required style="width: 100%; padding: 0.5rem;"></textarea>
    </div>

    <div style="margin-bottom: 1.5rem;">
      <label for="archivoFinal">Subir Documento Final (PDF):</label><br>
      <input type="file" name="archivoFinal" accept="application/pdf"  style="width: 100%; padding: 0.5rem;">
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 1rem;">
      <a href="bandejaPendientes.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Finalizar</button>
    </div>
  </form>
</div>
