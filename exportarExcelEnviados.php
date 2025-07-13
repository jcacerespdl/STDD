<?php
require 'vendor/autoload.php'; // Ruta a PHPSpreadsheet
include("conexion/conexion.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="tramites_enviados.xlsx"');
header('Cache-Control: max-age=0');

$iCodOficina = $_SESSION['iCodOficinaLogin'] ?? null;
if (!$iCodOficina) exit('No autorizado');

// Captura filtros
$filtroExpediente = $_GET['expediente'] ?? '';
$filtroAsunto = $_GET['asunto'] ?? '';
$filtroDesde = $_GET['desde'] ?? '';
$filtroHasta = $_GET['hasta'] ?? '';
$filtroTipoDoc = $_GET['tipoDocumento'] ?? '';
$filtroObservaciones = $_GET['observaciones'] ?? '';
$filtroOficina = $_GET['oficinasDestino'] ?? '';

// Consulta
$sql = "
    SELECT t.expediente, t.cAsunto, t.fFecRegistro, t.documentoElectronico,
           o.cNomOficina AS OficinaRegistro, td.cDescTipoDoc
    FROM Tra_M_Tramite t
    INNER JOIN Tra_M_Tramite_Movimientos m ON t.iCodTramite = m.iCodTramite
    LEFT JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
    LEFT JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
    WHERE m.iCodOficinaOrigen = ?
";
$params = [$iCodOficina];

if ($filtroExpediente) {
    $sql .= " AND t.expediente LIKE ?";
    $params[] = "%$filtroExpediente%";
}
if ($filtroAsunto) {
    $sql .= " AND t.cAsunto LIKE ?";
    $params[] = "%$filtroAsunto%";
}
if ($filtroDesde) {
    $sql .= " AND t.fFecRegistro >= ?";
    $params[] = $filtroDesde;
}
if ($filtroHasta) {
    $sql .= " AND t.fFecRegistro <= DATEADD(day, 1, ?)";
    $params[] = $filtroHasta;
}
if ($filtroTipoDoc) {
    $sql .= " AND t.cCodTipoDoc = ?";
    $params[] = $filtroTipoDoc;
}
if ($filtroObservaciones) {
    $sql .= " AND t.cObservaciones LIKE ?";
    $params[] = "%$filtroObservaciones%";
}
if ($filtroOficina) {
    $sql .= " AND m.iCodOficinaDerivar = ?";
    $params[] = $filtroOficina;
}

$sql .= " ORDER BY t.fFecRegistro DESC";

$stmt = sqlsrv_prepare($cnx, $sql, $params);
sqlsrv_execute($stmt);

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Trámites Enviados');

// Encabezados
$sheet->fromArray([
    'Expediente', 'Fecha Registro', 'Asunto', 'Tipo Documento', 'Oficina Registro', 'Archivo'
], NULL, 'A1');

// Datos
$fila = 2;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $sheet->setCellValue("A{$fila}", $row['expediente']);
    $sheet->setCellValue("B{$fila}", $row['fFecRegistro'] ? $row['fFecRegistro']->format("Y-m-d H:i") : '');
    $sheet->setCellValue("C{$fila}", $row['cAsunto']);
    $sheet->setCellValue("D{$fila}", $row['cDescTipoDoc']);
    $sheet->setCellValue("E{$fila}", $row['OficinaRegistro']);
    $sheet->setCellValue("F{$fila}", $row['documentoElectronico']);
    $fila++;
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
