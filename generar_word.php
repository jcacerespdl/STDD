<?php
// Incluir la conexión a la base de datos y sesión
global $cnx;
include_once("conexion/conexion.php");
session_start();

require 'vendor/autoload.php'; // Cargar PHPWord
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Iniciar buffer de salida para evitar contenido extra en el archivo
ob_start();

// Obtener datos de la URL
$tipoDocumento = $_GET['tipoDocumento'] ?? '';
$correlativo = $_GET['correlativo'] ?? '';
$asunto = $_GET['asunto'] ?? '';
$cuerpo = $_GET['cuerpo'] ?? '';
$fecha = strftime("%d de %B de %Y");

// Obtener el código del documento desde la BD
$sqlTipoDoc = "SELECT cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE cDescTipoDoc = ?";
$params = array($tipoDocumento);
$resultTipoDoc = sqlsrv_query($cnx, $sqlTipoDoc, $params);
if (!$resultTipoDoc) {
    die("Error en la consulta de tipo de documento: " . print_r(sqlsrv_errors(), true));
}
$rowTipoDoc = sqlsrv_fetch_array($resultTipoDoc, SQLSRV_FETCH_ASSOC);
$cDescTipoDoc = $rowTipoDoc['cDescTipoDoc'] ?? 'Desconocido';

// Obtener la sigla de la oficina del usuario en sesión y el jefe de la oficina
$sqlOficina = "SELECT o.cSiglaOficina, o.cNomOficina,  t.cNombresTrabajador, t.cApellidosTrabajador 
               FROM Tra_M_Oficinas o
               JOIN Tra_M_Perfil_Ususario pu ON o.iCodOficina = pu.iCodOficina
               JOIN Tra_M_Trabajadores t ON pu.iCodTrabajador = t.iCodTrabajador
               WHERE pu.iCodPerfil = 3 AND o.iCodOficina = ?";
$params = array($_SESSION['iCodOficinaLogin']);
$resultOficina = sqlsrv_query($cnx, $sqlOficina, $params);
if (!$resultOficina) {
    die("Error en la consulta de oficina de origen: " . print_r(sqlsrv_errors(), true));
}
$rowOficina = sqlsrv_fetch_array($resultOficina, SQLSRV_FETCH_ASSOC);

$cSiglaOficina = $rowOficina['cSiglaOficina'] ?? '';
$cNomOficina = $rowOficina['cNomOficina'] ?? '';
$jefeOficinaOrigen = ($rowOficina['cNombresTrabajador'] ?? 'No asignado') . ' ' . ($rowOficina['cApellidosTrabajador'] ?? '');

 

$tituloPDF = "$cDescTipoDoc N° $correlativo-2025/$cSiglaOficina/INR(e)";

$phpWord = new PhpWord();
$section = $phpWord->addSection();

// HEADER
$header = $section->addHeader();
$header->addImage('CABEZERA_INR.jpg', ['width' => 420, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$header->addText('"Decenio de la Igualdad de Oportunidades para mujeres y hombres"', ['size' => 10], ['alignment' => 'center']);
$header->addText('"Año de la recuperación y consolidación de la economía peruana"', ['size' => 10], ['alignment' => 'center']);

// TÍTULO
$section->addTextBreak(1);
$section->addText($tituloPDF, ['bold' => true, 'size' => 14, 'underline' => 'single'], ['alignment' => 'center']);
$section->addTextBreak(1);

// BLOQUE PARA
foreach ($destinosTexto as $i => $destino) {
    $textrun = $section->addTextRun(['alignment' => 'left']);
    if ($i === 0) {
        $textrun->addText("PARA: ", ['bold' => true, 'size' => 12]);
    } else {
        $textrun->addText("      ");
    }
    $textrun->addText("{$destino['jefe']}", ['bold' => true, 'size' => 12]);
    $section->addText($destino['oficina'], ['size' => 12], ['indentation' => ['left' => 400]]);
}
$section->addTextBreak(1);

// BLOQUE DE
$textrun = $section->addTextRun();
$textrun->addText("DE: ", ['bold' => true, 'size' => 12]);
$textrun->addText($jefeOficinaOrigen, ['bold' => true, 'size' => 12]);
$section->addText($cNomOficina, ['size' => 12], ['indentation' => ['left' => 400]]);
$section->addTextBreak(1);

// ASUNTO
$textrun = $section->addTextRun();
$textrun->addText("ASUNTO: ", ['bold' => true, 'size' => 12]);
$textrun->addText($asunto, ['size' => 12]);
$section->addTextBreak(1);

// FECHA
$textrun = $section->addTextRun();
$textrun->addText("FECHA: ", ['bold' => true, 'size' => 12]);
$textrun->addText($fecha, ['size' => 12]);
$section->addTextBreak(1);

// LÍNEA HORIZONTAL
$section->addText(str_repeat("_", 80), [], ['alignment' => 'center']);
$section->addTextBreak(1);

// LEYENDA DE FIRMA DIGITAL
$section->addText("Documento firmado digitalmente", ['italic' => true, 'size' => 12]);
$section->addText($jefeOficinaOrigen, ['bold' => true, 'size' => 12]);
$section->addText($cNomOficina, ['size' => 12]);

// FOOTER
$footer = $section->addFooter();
$table = $footer->addTable();

// Primera fila: QR y texto legal
$table->addRow();
$table->addCell(1000)->addImage('QR_verificar.jpg', ['width' => 60]);
$table->addCell(8000)->addText(
    "Esta es una copia auténtica imprimible de un documento electrónico archivado por el INR, aplicando lo dispuesto por el Art. 25 de D.S. 070-2013PCM y la Tercera Disposición Complementaria Final del D.S. 026-2016-PCM.",
    ['size' => 8],
    ['alignment' => 'both']
);

// Línea divisoria
$footer->addText(str_repeat("_", 80), [], ['alignment' => 'center']);

// Segunda fila: Dirección + Logo + página
$table->addRow();
$table->addCell(2000)->addImage('logo-horizontal.jpeg', ['width' => 100]);
$table->addCell(6000)->addText(
    "Av. Prolongación Defensores del Morro\nCdra. 2 – Chorrillos – Lima\nTeléf.: (01) 717-3200",
    ['size' => 8]
);
$table->addCell(2000)->addText('Página {PAGE} de {NUMPAGES}', ['italic' => true, 'size' => 8]);

// Exportar documento
$fileName = "documento_prueba.docx";
$tempFile = tempnam(sys_get_temp_dir(), 'doc');
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($tempFile);

// Asegurar que no haya salida de contenido antes de los encabezados
if (ob_get_length()) {
    ob_end_clean();
}

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=$fileName");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($tempFile));
readfile($tempFile);
unlink($tempFile);
exit;
?>
