<?php

use biometric\src\core\models\EnvFileModel;

require_once(dirname(__FILE__)."/../src/core/models/EnvFileModel.php");
require_once(dirname(__FILE__)."/_security_observability.php");
require_once(dirname(__FILE__)."/_auth_keycloak.php");

biometricSecurityBootstrap();

function getClientOrigin(){
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return '';
    }

    $parsed = parse_url($origin);
    if (!is_array($parsed)) {
        return '';
    }
    $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
    $host = strtolower((string) ($parsed['host'] ?? ''));
    if (
        !in_array($scheme, ['http', 'https'], true) ||
        $host === '' ||
        isset($parsed['user']) ||
        isset($parsed['pass']) ||
        (isset($parsed['path']) && $parsed['path'] !== '')
    ) {
        return '';
    }

    $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
    return $scheme . '://' . $host . $port;
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
    
    $uses_wildcard = in_array("*", $allowed_domains, true);

    if(!$uses_wildcard){
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
        header("Access-Control-Allow-Credentials: true");
    }
    header("Vary: Origin", false);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization, X-Requested-With, X-Request-ID");
    header("Access-Control-Expose-Headers: X-Request-ID, Content-Disposition");
    header("Access-Control-Max-Age: 86400");

    biometricSecurityRecordCorsDecision($origin, $allow_origin, $uses_wildcard);
    
    if(($_SERVER["REQUEST_METHOD"] ?? '') == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

header('Content-Type: application/json; charset=utf-8');
generateCorsHeaders();
keycloak_apply_auth_policy();
