<?php
include_once("conexion/conexion.php");
session_start();

$iCodTramite = $_GET['iCodTramite'] ?? null;
$pedidoSiga = $_GET['pedido_siga'] ?? null;
$tipo = isset($_POST['tipoComplementario']) ? (int)$_POST['tipoComplementario'] : 0;


if (!$iCodTramite || !$pedidoSiga) {
    die("<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>Error: Datos incompletos.</div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo "<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>Error: Archivo no válido.</div>";
        exit();
    }

    $archivoOriginal = $_FILES['archivo']['name'];
    $extension = strtolower(pathinfo($archivoOriginal, PATHINFO_EXTENSION));

    if ($extension !== 'pdf') {
        echo "<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>Error: Solo se permiten archivos PDF.</div>";
        exit();
    }

    $nombreFinal = $iCodTramite . '-' . str_replace(' ', '_', $archivoOriginal);
    $rutaDestino = __DIR__ . "/cAlmacenArchivos/" . $nombreFinal;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
        echo "<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>Error: No se pudo guardar el archivo.</div>";
        exit();
    }
    // Comprobación si ya existe ese archivo para ese trámite y pedido
$sqlExiste = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND pedido_siga = ? AND cDescripcion = ?";
$stmtExiste = sqlsrv_query($cnx, $sqlExiste, [$iCodTramite, $pedidoSiga, $nombreFinal]);
$total = 0;
if ($stmtExiste && ($fila = sqlsrv_fetch_array($stmtExiste, SQLSRV_FETCH_ASSOC))) {
    $total = (int)$fila['total'];
}

if ($total > 0) {
    echo "<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>⚠️ Ya existe un archivo con ese nombre para este pedido.</div>";
    exit();
}

    $sqlInsert = "INSERT INTO Tra_M_Tramite_Digitales (iCodTramite, pedido_siga, cDescripcion, cTipoComplementario) VALUES (?, ?, ?, ?)";
    $stmt = sqlsrv_query($cnx, $sqlInsert, [$iCodTramite, $pedidoSiga, $nombreFinal, $tipo]);

    if ($stmt) {
        echo "<div style='padding: 20px; font-family: sans-serif; color: #155724; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px;'>✅ Archivo guardado correctamente.</div>";
        echo "<script>
        alert('✅ Archivo guardado correctamente.');
        if (window.opener && !window.opener.closed) {
            window.opener.location.reload(); // recarga la ventana principal
        }
        window.close(); // cierra el popup
    </script>";
    } else {
        echo "<div style='padding: 20px; font-family: sans-serif; color: #b00020;'>Error SQL: " . print_r(sqlsrv_errors(), true) . "</div>";
    }
    exit();
}

// Consultar archivos ya subidos para este pedido SIGA
$sqlArchivos = "SELECT cDescripcion FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND pedido_siga = ?";
$stmtArchivos = sqlsrv_query($cnx, $sqlArchivos, [$iCodTramite, $pedidoSiga]);
$archivos = [];
if ($stmtArchivos) {
    while ($row = sqlsrv_fetch_array($stmtArchivos, SQLSRV_FETCH_ASSOC)) {
        $archivos[] = $row['cDescripcion'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<body style="font-family: sans-serif; background: white; margin: 0; padding: 20px;">
    <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 500px; margin: auto;">
        <h3 style="margin-top: 0; color: #364897;">Subir archivo PDF complementario</h3>
        <p><strong>Trámite:</strong> <?= htmlspecialchars($iCodTramite) ?><br>
           <strong>Pedido SIGA:</strong> <?= htmlspecialchars($pedidoSiga) ?></p>
           <form method="POST" enctype="multipart/form-data" onsubmit="return validarSubida(this)">
                <label for="archivo">Seleccionar PDF:</label>
                <input type="file" name="archivo" accept="application/pdf" required><br><br>

                <label for="tipo">Tipo de Complementario:</label><br>
                <select name="tipoComplementario" required>
                    <option value="0">Ninguno</option>
                    <option value="1">Pedido SIGA</option>
                    <option value="2">TDR o ETT</option>
                    
                </select><br><br>

                <button type="submit" style="background:#364897;color:white;padding:6px 14px;border:none;border-radius:4px;cursor:pointer;">
                    Subir
                </button>
            </form>


        <?php if (!empty($archivos)): ?>
            <hr>
            <h4 style="margin-top: 20px; color: #364897;">Archivos ya subidos</h4>
            <ul style="padding-left: 20px;">
                <?php foreach ($archivos as $archivo): ?>
                    <li>
                        <a href="cAlmacenArchivos/<?= urlencode($archivo) ?>" target="_blank" style="color: #364897; text-decoration: underline;">
                            <?= htmlspecialchars($archivo) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <script>
function validarSubida(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn.disabled) return false; // ya está en proceso

    btn.disabled = true;
    btn.textContent = "Subiendo...";
    return true;
}
</script>
</body>
</html>
