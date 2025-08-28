<?php
header('Content-Type: application/x-www-form-urlencoded');
$nombreZIP = $_GET["nombreZip"] ?? '';
$iCodFirma = $_GET["iCodFirma"] ?? '';
$documentToSign = "https://tramite.heves.gob.pe/STDD_marchablanca/{$nombreZIP}.7z";

if (!$nombreZIP || !$iCodFirma) die("Faltan parámetros");

include_once("conexion/conexion.php");
$sqlTipo = "SELECT tipoFirma, posicion FROM Tra_M_Tramite_Firma WHERE iCodFirma = ?";
$stmtTipo = sqlsrv_query($cnx, $sqlTipo, [$iCodFirma]);
$row = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
$tipoFirma = intval($row["tipoFirma"] ?? 2);
$posicionLetra = trim($row["posicion"] ?? 'B');

$coords = [
    "A" => [465, 10], "B" => [10, 720], "C" => [10, 680], "D" => [10, 640],
    "E" => [10, 600], "F" => [10, 560], "G" => [10, 520], "H" => [10, 480],
    "I" => [10, 440], "J" => [10, 400], "K" => [10, 360],
    "P" => [105, 350], "Q" => [360, 350], "R" => [225, 250],
    "S" => [225, 500], "T" => [420, 550], "U" => [320, 550],
    "V" => [245, 650], "W" => [25, 700], "X" => [140, 700], "Y" => [275, 700], "Z" => [415, 700]
];
$positionx = $coords[$posicionLetra][0] ?? 10;
$positiony = $coords[$posicionLetra][1] ?? 10;

// Token Firma Perú
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://apps.firmaperu.gob.pe/admin/api/security/generate-token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => 'client_id=IbGT0FAY7TIwMTMxMzczMjM3Gr5VZJ43vg&client_secret=FhSJjScRKIGHKZHDF_PBJ9m9doqShobUdR4',
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$token = curl_exec($curl);
curl_close($curl);

$params = new stdClass();
$params->signatureFormat = "PAdES";
$params->signatureLevel = "B";
$params->signaturePackaging = "enveloped";
$params->documentToSign = $documentToSign;
$params->certificateFilter = ".*FIR.*.*";
$params->theme = "claro";
$params->visiblePosition = false;
$params->signatureReason = "Doy visto bueno del documento principal";
$params->bachtOperation = true;
$params->oneByOne = false;
$params->signatureStyle = 1;
$params->imageToStamp = "https://tramite.heves.gob.pe/STDD_marchablanca/img/VB.png";
$params->stampTextSize = 15;
$params->stampPage = 1;
$params->positionx = $positionx;
$params->positiony = $positiony;
$params->uploadDocumentSigned = "https://tramite.heves.gob.pe/STDD_marchablanca/uploadFileVBprincipal.php?nombreZip=$nombreZIP&iCodFirma=$iCodFirma";
$params->token = $token;

echo base64_encode(json_encode($params));
