<?php
require_once 'tcpdf/tcpdf.php';
include 'conexion/conexion.php';
session_start();
header('Content-Type: application/json');

// Clase personalizada TCPDF
class MYPDF extends TCPDF {
    public $imgCabecera = '';
    public $cvv = '';
    public $expediente = '';

    public function Header() {
        if ($this->imgCabecera && file_exists($this->imgCabecera)) {
            $this->Image($this->imgCabecera, 30, 4, 135, 12, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(130, 130, 130); // gris suave
        $this->SetY(17);
        $this->Cell(0, 5, '“Decenio de la Igualdad de Oportunidades para mujeres y hombres”', 0, 1, 'C');
        $this->SetY(20);
        $this->Cell(0, 4, '“Año de la recuperación y consolidación de la economía peruana”', 0, 1, 'C');
        $this->Ln(15); // ⬅️ agrega espacio extra después del encabezado
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer() {
        $qrPath   = __DIR__ . '/img/QR_verificar.png';
        $osito    = __DIR__ . '/img/osito.png';
        $scep     = __DIR__ . '/img/scep.png';

        $this->SetY(-40);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);

        // QR + texto legal
        $xStart = 25;
        if (file_exists($qrPath)) {
            $this->Image($qrPath, $xStart, $this->GetY()-1, 20, 20, '', '', '', false, 300);
        }

        $this->SetXY($xStart + 22, $this->GetY());
        $texto = "Esta es una copia auténtica imprimible de un documento electrónico archivado por el Hospital Villa el Salvador, aplicando lo dispuesto por el Art. 25 de D.S. 070-2013PCM y la Tercera Disposición Complementaria Final del D.S. 026-2016-PCM. Su autenticidad e integridad pueden ser contrastadas a través de la lectura del código QR o el siguiente enlace: https://tramite.heves.gob.pe/sgd/consultaExpediente.php EXPEDIENTE: {$this->expediente} - CVV: {$this->cvv}";
        $this->MultiCell(0, 4, $texto, 0, 'J');

        // Línea separadora
        $this->Line(25, $this->GetY() + 1, $this->getPageWidth() - 25, $this->GetY() + 1);

        // Parte inferior: osito + dirección + scep + página
        $this->SetY(-15);
        $y = $this->GetY();
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);

        if (file_exists($osito)) {
            $this->Image($osito, 25, $y - 3, 20, 10, '', '', '', false, 300);
        }

        $this->SetXY(50, $y - 2);
        $this->MultiCell(80, 4, "Av. 200 Millas S/N cruce con Av.\nPastor Sevilla - Villa El Salvador\nT: (01) 640-9875 Anexo:", 0, 'L');

        if (file_exists($scep)) {
            $this->Image($scep, 130, $y - 3, 25, 10, '', '', '', false, 300);
        }

        // Paginación
        $this->SetXY(-45, $y -1);
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

// Validación
$iCodTramite = $_POST['iCodTramite'] ?? null;
$cuerpo = $_POST['descripcion'] ?? '';
if (!$iCodTramite || !$cuerpo) {
    echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
    exit;
}

// Datos del trámite
$sqlTramite = "SELECT td.cCodTipoDoc, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.fFecRegistro, t.cPassword, t.EXPEDIENTE
               FROM Tra_M_Tramite t 
               JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
               WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$tramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
$cPassword = $tramite['cPassword'] ?? '';

// Oficina
$sqlOficina = "SELECT cNomOficina, cImgCabecera FROM Tra_M_Oficinas WHERE iCodOficina = ?";
$resultOficina = sqlsrv_query($cnx, $sqlOficina, [$_SESSION['iCodOficinaLogin']]);
$rowOficina = sqlsrv_fetch_array($resultOficina, SQLSRV_FETCH_ASSOC);
$cNomOficina = $rowOficina['cNomOficina'] ?? '';
$cImgCabecera = $rowOficina['cImgCabecera'] ?? '';
$rutaCabecera = __DIR__ . '/' . $cImgCabecera;

// Nombre de archivo
$tituloPDF = "{$tramite['cDescTipoDoc']} Nº {$tramite['cCodificacion']}";
$nombreArchivo = str_replace(['/', ' '], ['_', '-'], "{$tramite['cDescTipoDoc']}-{$tramite['cCodificacion']}.pdf");

// Determinar si es derivado
$sqlTipo = "SELECT nFlgTipoDerivo FROM Tra_M_Tramite WHERE iCodTramite = ?";
$stmtTipo = sqlsrv_query($cnx, $sqlTipo, [$iCodTramite]);
$rowTipo = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);

// Si es derivado, los movimientos deben buscarse donde iCodTramiteDerivar = $iCodTramite
if ($rowTipo && $rowTipo['nFlgTipoDerivo'] == 1) {
    $campoTramiteDestino = 'iCodTramiteDerivar';
} else {
    $campoTramiteDestino = 'iCodTramite';
}

// DESTINOS
$destinosHTML = '';
$sqlDest = "SELECT o.cNomOficina, t.cNombresTrabajador, t.cApellidosTrabajador
            FROM Tra_M_Tramite_Movimientos tm
            JOIN Tra_M_Oficinas o ON tm.iCodOficinaDerivar = o.iCodOficina
            JOIN Tra_M_Trabajadores t ON tm.iCodTrabajadorDerivar = t.iCodTrabajador
            WHERE tm.{$campoTramiteDestino} =? AND ISNULL(tm.cFlgTipoMovimiento, '') <> '4'";
$resDest = sqlsrv_query($cnx, $sqlDest, [$iCodTramite]);
$lastTipo = '';
$i = 0;

while ($row = sqlsrv_fetch_array($resDest, SQLSRV_FETCH_ASSOC)) {
    $tipo = ($row['tipoMov'] === '4') ? 'CC' : 'PARA';
    $mostrarTitulo = ($tipo !== $lastTipo);
    $mostrarDosPuntos = ($mostrarTitulo && $i === 0);
    if ($mostrarTitulo && $i > 0) $destinosHTML .= '<tr><td colspan="3"><br></td></tr>';
    $destinosHTML .= '
    <tr>
        <td style="width:17%"><strong>' . ($mostrarTitulo ? $tipo : '') . '</strong></td>
        <td style="width:3%">' . ($mostrarDosPuntos ? ':' : '') . '</td>
        <td style="width:80%"><strong>' . $row['cNombresTrabajador'] . ' ' . $row['cApellidosTrabajador'] . '</strong></td>
    </tr>
    <tr>
        <td></td><td></td>
        <td>' . $row['cNomOficina'] . '</td>
    </tr>';
    $lastTipo = $tipo;
    $i++;
}
$destinosHTML .= '<tr><td colspan="3"><br></td></tr>';

// COPIAS
$sqlCopia = "SELECT o.cNomOficina, t.cNombresTrabajador, t.cApellidosTrabajador
             FROM Tra_M_Tramite_Movimientos tm
             JOIN Tra_M_Oficinas o ON tm.iCodOficinaDerivar = o.iCodOficina
             JOIN Tra_M_Trabajadores t ON tm.iCodTrabajadorDerivar = t.iCodTrabajador
             WHERE tm.{$campoTramiteDestino} = ? AND tm.cFlgTipoMovimiento = '4'";
$resCopia = sqlsrv_query($cnx, $sqlCopia, [$iCodTramite]);
$j = 0;
while ($row = sqlsrv_fetch_array($resCopia, SQLSRV_FETCH_ASSOC)) {
    $destinosHTML .= "
    <tr>
        <td style='width:17%'><strong>" . ($j === 0 ? 'CC' : '') . "</strong></td>
        <td style='width:3%'>" . ($j === 0 ? ':' : '') . "</td>
        <td style='width:80%' colspan='2'><strong>{$row['cNombresTrabajador']} {$row['cApellidosTrabajador']}</strong></td>
    </tr>
    <tr>
        <td style='width:17%'></td><td style='width:3%'></td>
        <td colspan='2'>{$row['cNomOficina']}</td>
    </tr>";
    $j++;
}
$destinosHTML .= "<tr><td colspan='4'><br></td></tr>";
// Jefe
$sqlJefe = "SELECT t.cNombresTrabajador, t.cApellidosTrabajador
            FROM Tra_M_Perfil_Ususario pu
            JOIN Tra_M_Trabajadores t ON pu.iCodTrabajador = t.iCodTrabajador
            WHERE pu.iCodPerfil = 3 AND pu.iCodOficina = ?";
$resultJefe = sqlsrv_query($cnx, $sqlJefe, [$_SESSION['iCodOficinaLogin']]);
$rowJefe = sqlsrv_fetch_array($resultJefe, SQLSRV_FETCH_ASSOC);
$nombreJefe = $rowJefe['cNombresTrabajador'] . ' ' . $rowJefe['cApellidosTrabajador'];

// Referencias
$sqlRef = "SELECT DISTINCT r.cReferencia, m.expediente 
           FROM Tra_M_Tramite_Referencias r 
           JOIN Tra_M_Tramite_Movimientos m ON r.iCodTramiteRef = m.iCodTramite 
           WHERE r.iCodTramite = ?";
$stmtRef = sqlsrv_query($cnx, $sqlRef, [$iCodTramite]);
$referencias = '';
while ($r = sqlsrv_fetch_array($stmtRef, SQLSRV_FETCH_ASSOC)) {
    $ref = str_replace(".pdf", "", $r["cReferencia"]);
    $referencias .= "{$r['expediente']}-{$ref}<br>";
}
$bloqueReferencias = $referencias ? "
<tr><td colspan='3'><br></td></tr>
<tr><td style='width:17%'><b>REFERENCIAS</b></td><td style='width:3%'>:</td><td style='width:80%'>$referencias</td></tr>" : '';

// Fecha
setlocale(LC_TIME, 'es_PE.UTF-8', 'es_PE', 'Spanish_Peru', 'es');
$fecha = strftime("%d de %B de %Y", $tramite["fFecRegistro"]->getTimestamp());
$hora = $tramite["fFecRegistro"]->format("H:i");
$fechaCompleta = "$fecha  $hora";

// Crear PDF
$pdf = new MYPDF();
$pdf->imgCabecera = $rutaCabecera;
$pdf->cvv = $cPassword;
$pdf->expediente = $tramite['EXPEDIENTE'];
$pdf->SetCreator('SGD HEVES');
$pdf->SetAuthor($nombreJefe);
$pdf->SetTitle($tituloPDF);
$pdf->SetMargins(25, 30, 25);
$pdf->SetAutoPageBreak(true, 40);
$pdf->AddPage();

// HTML
$html = <<<HTML
<h2 style="text-align:center; font-size:10px; text-decoration: underline; margin-bottom: 20px;">$tituloPDF</h2>
<div><br></div> <!-- ⬅️ salto adicional -->
<table style="width:100%; font-size:9px;">
$destinosHTML
<tr><td style="width:17%"><b>DE</b></td><td style="width:3%">:</td><td style="width:80%"><b>$nombreJefe</b><br>$cNomOficina</td></tr>
<tr><td colspan="3"><br></td></tr>
<tr><td style="width:17%"><b>ASUNTO</b></td><td style="width:3%">:</td><td style="width:80%">{$tramite["cAsunto"]}</td></tr>
$bloqueReferencias
 
<tr><td style="width:17%"><b>FECHA</b></td><td style="width:3%">:</td><td style="width:80%">$fechaCompleta</td></tr>
<tr><td colspan="3"><br></td></tr>
</table>
<hr>
<div style="text-align:justify; font-size:9px;">$cuerpo</div>
<br><br>
<div style="font-size:9px;">
Documento firmado digitalmente<br>
<b>$nombreJefe</b><br>
<span>$cNomOficina</span>
</div>
HTML;

$pdf->writeHTML($html, true, false, true, false, '');
$rutaDestino = __DIR__ . "/cDocumentosFirmados/{$nombreArchivo}";
$pdf->Output($rutaDestino, 'F');

// Actualizar BD
sqlsrv_query($cnx, "UPDATE Tra_M_Tramite SET documentoElectronico = ? WHERE iCodTramite = ?", [$nombreArchivo, $iCodTramite]);
echo json_encode(["status" => "success", "filename" => $nombreArchivo]);
exit;
