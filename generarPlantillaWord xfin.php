<?php
require_once 'vendor/autoload.php';
include 'conexion/conexion.php';
session_start();
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$iCodTramite = $_GET['iCodTramite'] ?? null;
if (!$iCodTramite) {
    die("Trámite no especificado.");
}

// Simulación de datos
$asunto = "Asunto del trámite $iCodTramite";
$fecha = date("d/m/Y");
$contenido = "Contenido de prueba.";

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodOficina = $_SESSION['iCodOficinaLogin'] ?? null;
// === CONSULTA nombre de archivo limpio desde cImgCabeceraWord ===
$sqlOficina = "SELECT cImgCabeceraWord FROM Tra_M_Oficinas WHERE iCodOficina = ?";
$resultOficina = sqlsrv_query($cnx, $sqlOficina, [$iCodOficina]);
$row = sqlsrv_fetch_array($resultOficina, SQLSRV_FETCH_ASSOC);
$nombreArchivo = $row['cImgCabeceraWord'] ;


// Crear Word
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// === ENCABEZADO CON IMAGEN FIJA ===
$header = $section->addHeader();
$imgRealPath = utf8_encode(realpath("img/" . $nombreArchivo));

if ($imgRealPath && file_exists($imgRealPath)) {
    $header->addImage($imgRealPath, [
        'width' => 400,
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);
}
$header->addText('Texto fijo en cabecera', ['italic' => true, 'size' => 10], ['alignment' => 'center']);

// === PIE DE PÁGINA SIMPLE ===
$footer = $section->addFooter();
$footer->addPreserveText('Página {PAGE} de {NUMPAGES}', null, ['alignment' => 'center']);

// === CUERPO ===
$section->addText("Trámite: $iCodTramite");
$section->addText("Fecha: $fecha");
$section->addText("Asunto: $asunto", ['bold' => true]);
$section->addTextBreak(1);
$section->addText($contenido);

// === FORZAR DESCARGA ===
$filename = "Prueba2_Tramite_$iCodTramite.docx";
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save("php://output");
exit;
