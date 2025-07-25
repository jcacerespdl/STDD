<?php
require_once 'tcpdf/tcpdf.php';
include("conexion/conexion.php");

// Recibir parámetros
$iCodTramite = $_GET['iCodTramite'] ?? 0;
$extension = $_GET['extension'] ?? 1;
if (!$iCodTramite) die("Trámite no válido");

// Obtener datos generales del trámite raíz
$sql = "SELECT 
    t.expediente, t.cCodificacion, t.cAsunto, t.fFecRegistro, t.cObservaciones, t.documentoElectronico, t.cCodTipoDoc,
    td.cDescTipoDoc, td.cCodTipoDoc, o.cSiglaOficina
 FROM Tra_M_Tramite t
 JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
 JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
 WHERE t.iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Crear PDF
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('SGD VES');
$pdf->SetAuthor('Hospital de Emergencias Villa El Salvador');
$pdf->SetTitle('HOJA DE ENVIO DE TRAMITE GENERAL');

$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Logo + Encabezado
$logo = 'ruta/logo_ves.png'; // Cambiar por ruta real
$pdf->Image($logo, 15, 10, 25);
$pdf->SetXY(45, 12);
$pdf->SetFont('', 'B', 12);
$pdf->Cell(0, 8, 'HOJA DE ENVIO DE TRAMITE GENERAL', 0, 1, 'C');

// Datos generales
$pdf->SetFont('', '', 10);
$pdf->Ln(3);
// Documento
$pdf->Cell(35, 6, "Documento:", 0, 0, 'L');
$pdf->Cell(100, 6, $data['cDescTipoDoc'] . ' N° ' . $data['cCodificacion'], 0, 1, 'L');

// Estado
$pdf->Cell(35, 6, "Estado:", 0, 0, 'L');
$pdf->Cell(100, 6, 'En Proceso', 0, 1, 'L');

// Fecha de registro
$pdf->Cell(35, 6, "Fecha de Registro:", 0, 0, 'L');
$pdf->Cell(100, 6, $data['fFecRegistro']->format("d-m-Y H:i"), 0, 1, 'L');

$pdf->Ln(4);

// Cabecera de tabla
$pdf->SetFont('', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(45, 10, 'Documento', 1, 0, 'C', 1);
$pdf->Cell(50, 10, 'Asunto / Indicación', 1, 0, 'C', 1);
$pdf->Cell(35, 10, 'Origen', 1, 0, 'C', 1);
$pdf->Cell(35, 10, 'Destino', 1, 0, 'C', 1);
$pdf->Cell(30, 10, 'Fecha Creación', 1, 0, 'C', 1);
$pdf->MultiCell(50, 10, "Responsable\nFecha de Aceptado", 1, 'C', 1, 0);
$pdf->Cell(22, 10, 'Estado', 1, 1, 'C', 1);


// Mapa de fases
$fasesMap = [
    0 => "No Corresponde", 1 => "Indagación", 2 => "Validación",
    3 => "Reformulación", 4 => "Dispon. Presup.", 5 => "Notificación"
];

// Obtener todas las extensiones
$sqlExt = "SELECT DISTINCT extension FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ? ORDER BY extension ASC";
$stmtExt = sqlsrv_query($cnx, $sqlExt, [$iCodTramite]);
$extensiones = [];
while ($r = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC)) {
    $extensiones[] = (int)$r['extension'];
}

// Dibujar por cada extensión
foreach ($extensiones as $ext) {
    $sqlMov = "SELECT 
        M.iCodTramiteDerivar, 
        M.iCodMovimiento,
        M.iCodMovimientoDerivo,
        ISNULL(M.fFecDerivar, GETDATE()) AS fFecDerivar,
        ISNULL(M.fFecRecepcion, GETDATE()) AS fFecRecepcion,
        M.cAsuntoDerivar, 
        M.cObservacionesDerivar, 
        M.cPrioridadDerivar, 
        M.nEstadoMovimiento,
        M.fFecDelegado,
        M.fFecDelegadoRecepcion,
        M.iCodTrabajadorDelegado,
        M.iCodIndicacionDelegado,
        M.cObservacionesDelegado,
        M.extension,
        O1.cNomOficina AS OficinaOrigen,
        O1.cSiglaOficina AS OficinaOrigenAbbr,
        O2.cNomOficina AS OficinaDestino,
        O2.cSiglaOficina AS OficinaDestinoAbbr,
        T.fase,
        (SELECT TOP 1 T2.cNombresTrabajador + ' ' + T2.cApellidosTrabajador 
         FROM Tra_M_Perfil_ususario PU
         INNER JOIN Tra_M_Trabajadores T2 ON PU.iCodTrabajador = T2.iCodTrabajador
         WHERE PU.iCodOficina = M.iCodOficinaDerivar AND PU.iCodPerfil = 3
         ORDER BY T2.iCodTrabajador ASC) AS JefeDestino,
        (SELECT TOP 1 T3.cNombresTrabajador + ' ' + T3.cApellidosTrabajador 
         FROM Tra_M_Trabajadores T3 WHERE T3.iCodTrabajador = M.iCodTrabajadorDelegado) AS NombreDelegado,
        (SELECT I.cIndicacion FROM Tra_M_Indicaciones I WHERE I.iCodIndicacion = M.iCodIndicacionDelegado) AS cIndicacionDelegado
    FROM Tra_M_Tramite_Movimientos M
    LEFT JOIN Tra_M_Oficinas O1 ON M.iCodOficinaOrigen = O1.iCodOficina
    LEFT JOIN Tra_M_Oficinas O2 ON M.iCodOficinaDerivar = O2.iCodOficina
    LEFT JOIN Tra_M_Tramite T ON T.iCodTramite = M.iCodTramiteDerivar
    WHERE M.iCodTramite = ? AND M.extension = ?
    ORDER BY ISNULL(M.fFecDerivar, GETDATE()) ASC";

    $stmtMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramite, $ext]);

    while ($mov = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)) {
        // Obtener datos igual que antes...
        $docTexto = '—';
        if ($mov['iCodTramiteDerivar']) {
            $stmtDoc = sqlsrv_query($cnx, "SELECT td.cDescTipoDoc, t.cCodificacion 
                FROM Tra_M_Tramite t 
                JOIN Tra_M_Tipo_Documento td ON td.cCodTipoDoc = t.cCodTipoDoc 
                WHERE t.iCodTramite = ?", [$mov['iCodTramiteDerivar']]);
            if ($d = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC)) {
                $docTexto = $d['cDescTipoDoc'] . ' N° ' . $d['cCodificacion'];
            }
        }
    
        $asunto = $mov['cObservacionesDerivar'] ?: $mov['cAsuntoDerivar'] ?: '—';
        if ($mov['fase'] !== null && isset($fasesMap[$mov['fase']])) {
            $asunto .= ' / ' . $fasesMap[$mov['fase']];
        }
    
        $fDerivar = $mov['fFecDerivar'] instanceof DateTime ? $mov['fFecDerivar']->format("d/m/Y H:i") : '—';
        $fRecep = $mov['fFecRecepcion'] instanceof DateTime ? $mov['fFecRecepcion']->format("d/m/Y H:i") : '—';
    
        $responsable = $mov['NombreDelegado'] ?: ($mov['JefeDestino'] ?: '—');
        switch ((int)$mov['nEstadoMovimiento']) {
            case 0:
                $estado = 'Sin aceptar';
                break;
            case 1:
                $estado = 'Recibido';
                break;
            case 3:
                $estado = 'Delegado';
                break;
            case 5:
                $estado = 'Finalizado';
                break;
            default:
                $estado = 'Enviado';
                break;
        }
    
        $row = [
            ['w' => 45, 'txt' => $docTexto],
            ['w' => 50, 'txt' => $asunto],
            ['w' => 35, 'txt' => $mov['OficinaOrigenAbbr'] ?? '—'],
            ['w' => 35, 'txt' => $mov['OficinaDestinoAbbr'] ?? '—'],
            ['w' => 30, 'txt' => $fDerivar],
            ['w' => 50, 'txt' => $responsable . "\n" . $fRecep],
            ['w' => 22, 'txt' => $estado],
        ];
        
        // Calcular altura máxima
        $heights = [];
        foreach ($row as $cell) {
            $heights[] = $pdf->getStringHeight($cell['w'], $cell['txt']);
        }
        $rowHeight = max($heights);
        
        // Dibujar fila con altura uniforme y alineación según columna
        $pdf->SetFont('', '', 8);
        foreach ($row as $i => $cell) {
            $align = in_array($i, [2, 3, 4, 5, 6]) ? 'C' : 'L'; // columnas centradas
            $pdf->MultiCell($cell['w'], $rowHeight, $cell['txt'], 1, $align, 0, 0);
        }
        $pdf->Ln();
    }
    
}

// Salida en navegador
$pdf->Output('flujo_hoja_ruta.pdf', 'I');
