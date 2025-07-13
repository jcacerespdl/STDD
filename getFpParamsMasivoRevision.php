<?php
header('Content-Type: application/x-www-form-urlencoded');

$nombreZIP = isset($_GET["iCodTramite"]) ? $_GET["iCodTramite"] : "";
$iCodTrabajador = intval(str_replace("vb_revision_lote_", "", $nombreZIP));
$archivoJSON = __DIR__ . "/firmantes_{$iCodTrabajador}.json";

$posicionLetra = "A";
$tipoFirma = 0;

if (file_exists($archivoJSON)) {
    $contenido = file_get_contents($archivoJSON);
    $firmantes = json_decode($contenido, true);
    if (is_array($firmantes) && count($firmantes)) {
        $posicionLetra = $firmantes[0]['posicion'];
        $tipoFirma = $firmantes[0]['tipoFirma'];
    }
}

$coords = [
    "A" => [465, 10], "B" => [10, 720], "C" => [10, 680], "D" => [10, 640],
    "E" => [10, 600], "F" => [10, 560], "G" => [10, 520], "H" => [10, 480],

    "I" => [10, 440], "J" => [10, 400], 
    "K" => [465, 50], 
    
    "O" => [232, 350],
    "P" => [105, 350], "Q" => [360, 350], "R" => [250, 250],  "S" => [250, 620],
    "T" => [320, 550], "U" => [420, 550], "V" => [245, 650], "W" => [25, 700],   
    "X" => [140, 700], "Y" => [275, 700], "Z" => [415, 700]
    
];
$positionx = $coords[$posicionLetra][0] ?? 10;
$positiony = $coords[$posicionLetra][1] ?? 10;

// Token desde Firma Perú
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
$params->documentToSign = "https://tramite.heves.gob.pe/sgd/$nombreZIP.7z";
$params->certificateFilter = ".*FIR.*.*";
$params->theme = "claro";
$params->visiblePosition = false;
$params->signatureReason = $tipoFirma == 1 
    ?   "Firma Documento Principal"
    :   "Doy visto bueno del documento";
$params->bachtOperation = true;
$params->oneByOne = false;
$params->signatureStyle = 1;
$params->imageToStamp = $tipoFirma == 1 
    ? "https://tramite.heves.gob.pe/SGD/img/isotipo.png"
    : "https://tramite.heves.gob.pe/SGD/img/VB.png";
$params->stampTextSize = 15;
$params->stampWordWrap = 37;
$params->stampPage = 1;
$params->positionx = $positionx;
$params->positiony = $positiony;
$params->uploadDocumentSigned = "https://tramite.heves.gob.pe/sgd/uploadFileMasivoRevision.php?nombreZip=$nombreZIP";
$params->token = $token;

echo base64_encode(json_encode($params));
