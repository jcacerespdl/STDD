<?php
    // se generan los parametros para la firma:

    header('Content-Type: application/x-www-form-urlencoded');
    $iCodTramite = $_GET["iCodTramite"] ?? "";
    $tipoOperacion = $_GET["tipoOperacion"] ?? null;
    $params = new StdClass();

    //crear el token
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://apps.firmaperu.gob.pe/admin/api/security/generate-token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'client_id=nkyyy9BAgzIwMTMxMzc3NTc3uJgDndKzIA&client_secret=yRPKRQi2NHlzG1K_SylKtWJNBTNkzkp4guk',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    $token = $response ;

    $params->signatureFormat = "PAdES";
    $params->signatureLevel = "B";
    $params->signaturePackaging = "enveloped";
    $params->documentToSign =  "https://app2.inr.gob.pe/STDD_marchablanca/$iCodTramite.7z";
    $params->certificateFilter = ".*FIR.*.*";
    $params->webTsa = "";
    $params->userTsa = "";
    $params->passwordTsa = "";
    $params->theme = "claro";
    $params->visiblePosition = false;
    $params->contactInfo = "";
    $params->signatureReason = "Soy el autor del documento";
    $params->bachtOperation = true;
    $params->oneByOne = false;
    $params->signatureStyle = 1;
    $params->imageToStamp = "https://app2.inr.gob.pe/STDD_marchablanca/isotipo.png";
    $params->stampTextSize = 15;
    $params->stampWordWrap = 37;
    $params->role = "";
    $params->stampPage = 1;
    $params->positionx = 465;
    $params->positiony = 10;
    $params->uploadDocumentSigned = $tipoOperacion == "crearmasivo" ? "https://app2.inr.gob.pe/STDD_marchablanca/uploadMulitpleFile.php?batch={$iCodTramite}" : "https://app2.inr.gob.pe/STDD_marchablanca/uploadFile.php?iCodTramite=$iCodTramite&tipo=$tipoOperacion";
    $params->token = $token;

    echo base64_encode(json_encode($params));
