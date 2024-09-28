<?php

use biometric\src\core\models\EnvFileModel;

require_once(dirname(__FILE__)."/../src/core/models/EnvFileModel.php");

function getClientOrigin(){
    $referer_headers = ['HTTP_ORIGIN', 'HTTP_REFERER', 'REMOTE_ADDR'];
    $referer = "";
    foreach($referer_headers as $header){
        if(isset($_SERVER[$header])){
            $referer = $_SERVER[$header];
        }
    }
    if(!empty($referer) && $referer == '::1'){
        $referer = 'localhost';
    }

    return $referer;
}

function generateCorsHeaders(){
    $env = new EnvFileModel();
    $allowed_domain_raws = explode(",", $env->get('BIOMETRIC_CORS_WHITELIST'));
    $allowed_domains = [];
    foreach($allowed_domain_raws as $domain){
        $allowed_domains []= trim($domain);
    }

    $referer = getClientOrigin();
    $allow_origin = "";
    if(in_array("*", $allowed_domains)){
        $allow_origin = "*";
    }else if(in_array($referer, $allowed_domains)){
        $allow_origin = $referer;
    }

    header("Access-Control-Allow-Origin: $allow_origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization");
    // if($_SERVER["REQUEST_METHOD"] == 'OPTIONS') {
    //     die();
    // }
}

header('Content-Type: application/json; charset=utf-8');
generateCorsHeaders();
