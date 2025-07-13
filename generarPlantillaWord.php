<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

session_start();
include 'conexion/conexion.php';

$iCodTramite = $_GET['iCodTramite'] ?? null;
$iCodOficina = $_SESSION['iCodOficinaLogin'] ?? null;

if (!$iCodTramite || !$iCodOficina) die("Faltan parámetros.");

// === DATOS DEL TRÁMITE ===
$sqlTramite = "SELECT td.cCodTipoDoc, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.fFecRegistro, t.EXPEDIENTE
               FROM Tra_M_Tramite t 
               JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
               WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$tramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);

// === DATOS DE OFICINA Y CABECERA ===
$sqlOficina = "SELECT cNomOficina, cImgCabeceraWord FROM Tra_M_Oficinas WHERE iCodOficina = ?";
$rowOficina = sqlsrv_fetch_array(sqlsrv_query($cnx, $sqlOficina, [$iCodOficina]), SQLSRV_FETCH_ASSOC);
$cNomOficina = $rowOficina['cNomOficina'] ?? '';
$nombreArchivo = $rowOficina['cImgCabeceraWord'] ?? '';
$imgRealPath = $nombreArchivo ? utf8_encode(realpath("img/" . $nombreArchivo)) : null;

// === NOMBRE DEL JEFE ===
$sqlJefe = "SELECT t.cNombresTrabajador, t.cApellidosTrabajador
            FROM Tra_M_Perfil_Ususario pu
            JOIN Tra_M_Trabajadores t ON pu.iCodTrabajador = t.iCodTrabajador
            WHERE pu.iCodPerfil = 3 AND pu.iCodOficina = ?";
$rowJefe = sqlsrv_fetch_array(sqlsrv_query($cnx, $sqlJefe, [$iCodOficina]), SQLSRV_FETCH_ASSOC);
$nombreJefe = $rowJefe['cNombresTrabajador'] . ' ' . $rowJefe['cApellidosTrabajador'];

// === PARA y CC ===
$campoTramiteDestino = 'iCodTramite';
$destinos = []; $copias = [];

$sqlDest = "SELECT o.cNomOficina, t.cNombresTrabajador, t.cApellidosTrabajador, ISNULL(tm.cFlgTipoMovimiento, '') AS tipoMov
            FROM Tra_M_Tramite_Movimientos tm
            JOIN Tra_M_Oficinas o ON tm.iCodOficinaDerivar = o.iCodOficina
            JOIN Tra_M_Trabajadores t ON tm.iCodTrabajadorDerivar = t.iCodTrabajador
            WHERE tm.{$campoTramiteDestino} = ?";
$stmtDest = sqlsrv_query($cnx, $sqlDest, [$iCodTramite]);

while ($row = sqlsrv_fetch_array($stmtDest, SQLSRV_FETCH_ASSOC)) {
    $destino = [
        'nombre' => "{$row['cNombresTrabajador']} {$row['cApellidosTrabajador']}",
        'oficina' => $row['cNomOficina']
    ];
    if ($row['tipoMov'] === '4') $copias[] = $destino;
    else $destinos[] = $destino;
}

// === REFERENCIAS ===
$referencias = [];
$sqlRef = "SELECT DISTINCT r.cReferencia, m.expediente 
           FROM Tra_M_Tramite_Referencias r 
           JOIN Tra_M_Tramite_Movimientos m ON r.iCodTramiteRef = m.iCodTramite 
           WHERE r.iCodTramite = ?";
$stmtRef = sqlsrv_query($cnx, $sqlRef, [$iCodTramite]);
while ($r = sqlsrv_fetch_array($stmtRef, SQLSRV_FETCH_ASSOC)) {
    $ref = str_replace(".pdf", "", $r["cReferencia"]);
    $referencias[] = "{$r['expediente']}-{$ref}";
}

// === FORMATO FECHA ===
setlocale(LC_TIME, 'es_PE.UTF-8');
$fechaObj = $tramite["fFecRegistro"];
$fechaTexto = strftime("%d de %B de %Y", $fechaObj->getTimestamp()) . ' ' . $fechaObj->format("H:i");

// === CREAR DOCUMENTO ===
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// === ENCABEZADO ===
$header = $section->addHeader();
if ($imgRealPath && file_exists($imgRealPath)) {
    $header->addImage($imgRealPath, ['width' => 450, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
}
$header->addText('“Decenio de la Igualdad de Oportunidades para mujeres y hombres”', ['italic' => true, 'size' => 10], ['alignment' => 'center']);
$header->addText('“Año de la recuperación y consolidación de la economía peruana”', ['italic' => true, 'size' => 10], ['alignment' => 'center']);

// === TÍTULO ===
$titulo = "{$tramite['cDescTipoDoc']} Nº {$tramite['cCodificacion']}";
$section->addText($titulo, ['bold' => true, 'underline' => 'single', 'size' => 14], ['alignment' => 'center']);
$section->addTextBreak(1);

// === TABLA DE DATOS ===
$table = $section->addTable(['width' => 100 * 50]);

foreach ($destinos as $i => $dest) {
    if ($i == 0) $table->addRow(); $table->addCell(1500)->addText("PARA");
    $table->addCell(500)->addText(":");
    $table->addCell(7000)->addText("{$dest['nombre']}\n{$dest['oficina']}");
}

foreach ($copias as $j => $cc) {
    $table->addRow(); if ($j == 0) $table->addCell(1500)->addText("CC");
    else $table->addCell(1500)->addText("");
    $table->addCell(500)->addText(":");
    $table->addCell(7000)->addText("{$cc['nombre']}\n{$cc['oficina']}");
}

$table->addRow();
$table->addCell(1500)->addText("DE");
$table->addCell(500)->addText(":");
$table->addCell(7000)->addText("$nombreJefe\n$cNomOficina");

$table->addRow();
$table->addCell(1500)->addText("ASUNTO");
$table->addCell(500)->addText(":");
$table->addCell(7000)->addText($tramite['cAsunto']);

if (count($referencias)) {
    $table->addRow();
    $table->addCell(1500)->addText("REFERENCIAS");
    $table->addCell(500)->addText(":");
    $table->addCell(7000)->addText(implode(", ", $referencias));
}

$table->addRow();
$table->addCell(1500)->addText("FECHA");
$table->addCell(500)->addText(":");
$table->addCell(7000)->addText($fechaTexto);

$section->addTextBreak(2);

// === FIRMADO DIGITALMENTE ===
$section->addText("Documento firmado digitalmente", ['size' => 10]);
$section->addText($nombreJefe, ['bold' => true]);
$section->addText($cNomOficina);

// === PIE DE PÁGINA CON IMÁGENES ===
$footer = $section->addFooter();
$tableFooter = $footer->addTable(['alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
$tableFooter->addRow();
foreach (['QR_verificar.png', 'osito.png', 'scep.png'] as $img) {
    $path = realpath("img/$img");
    if (file_exists($path)) $tableFooter->addCell(2500)->addImage($path, ['width' => 60]);
    else $tableFooter->addCell(2500)->addText('');
}
$footer->addPreserveText('Página {PAGE} de {NUMPAGES}', null, ['alignment' => 'center']);

// === DESCARGA ===
$filename = "Plantilla_Tramite_{$iCodTramite}.docx";
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

IOFactory::createWriter($phpWord, 'Word2007')->save("php://output");
exit;
