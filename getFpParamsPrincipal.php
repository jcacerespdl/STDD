<?php
header('Content-Type: application/x-www-form-urlencoded');

$nombreZIP = $_GET["nombreZip"] ?? '';
$iCodTramite = $_GET["iCodTramite"] ?? '';

$documentToSign = "https://tramite.heves.gob.pe/STDD_marchablanca/$nombreZIP.7z";

// Firma en posición A
$positionx = 465;
$positiony = 10;

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
$params->documentToSign = $documentToSign;
$params->certificateFilter = ".*FIR.*.*";
$params->theme = "claro";
$params->visiblePosition = false;
$params->signatureReason = "Firma documento principal";
$params->bachtOperation = true;
$params->oneByOne = false;
$params->signatureStyle = 1;
$params->imageToStamp = "https://tramite.heves.gob.pe/STDD_marchablanca/img/isotipo.png";
$params->stampTextSize = 15;
$params->stampPage = 1;
$params->positionx = $positionx;
$params->positiony = $positiony;
$params->uploadDocumentSigned = "https://tramite.heves.gob.pe/STDD_marchablanca/uploadFilePrincipal.php?nombreZip=$nombreZIP&iCodTramite=$iCodTramite";
$params->token = $token;

echo base64_encode(json_encode($params));
