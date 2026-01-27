<?php

use biometric\src\core\models\EnvFileModel;

require_once(dirname(__FILE__)."/../src/core/models/EnvFileModel.php");

function getClientOrigin(){
    if(isset($_SERVER['HTTP_ORIGIN']) && !empty($_SERVER['HTTP_ORIGIN'])){
        return $_SERVER['HTTP_ORIGIN'];
    }
    
    if(isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])){
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        if(isset($parsed['scheme']) && isset($parsed['host'])){
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            return $parsed['scheme'] . '://' . $parsed['host'] . $port;
        }
    }
    
    return "";
}

function generateCorsHeaders(){
    $env = new EnvFileModel();
    $whitelist_raw = $env->get('BIOMETRIC_CORS_WHITELIST');
    
    $whitelist_raw = trim($whitelist_raw, '"\' ');
    
    $allowed_domain_raws = explode(",", $whitelist_raw);
    $allowed_domains = [];
    foreach($allowed_domain_raws as $domain){
        $allowed_domains []= trim($domain);
    }

    $origin = getClientOrigin();
    $allow_origin = "";
    
    if(in_array("*", $allowed_domains)){
        $allow_origin = "*";
    } else {
        foreach($allowed_domains as $allowed){
            if(strpos($allowed, 'http') === 0){
                if($origin === $allowed){
                    $allow_origin = $origin;
                    break;
                }
            } else {
                $origin_host = parse_url($origin, PHP_URL_HOST);
                if($origin_host === $allowed){
                    $allow_origin = $origin;
                    break;
                }
            }
        }
    }

    if(!empty($allow_origin)){
        header("Access-Control-Allow-Origin: $allow_origin");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    
    if($_SERVER["REQUEST_METHOD"] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

header('Content-Type: application/json; charset=utf-8');
generateCorsHeaders();
