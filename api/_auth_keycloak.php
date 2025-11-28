<?php

require_once(dirname(__FILE__) . "/models/EnvFileModel.php");

use biometric\src\core\models\EnvFileModel;

function keycloak_require_auth()
{
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        unauthorized();
    }

    $auth = $headers['Authorization'];

    if (strpos($auth, 'Bearer ') !== 0) {
        unauthorized();
    }

    $token = substr($auth, 7);

    $env = new EnvFileModel();
    $base = rtrim($env->get('KEYCLOAK_BASE_URL'), '/');
    $realm = $env->get('KEYCLOAK_REALM');

    if (!$base || !$realm) {
        http_response_code(500);
        echo json_encode(["error" => "Keycloak env not configured"]);
        exit;
    }

    $userinfoUrl = "$base/realms/$realm/protocol/openid-connect/userinfo";

    $ch = curl_init($userinfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Token invalid
    if ($status !== 200) {
        unauthorized();
    }

    return json_decode($response, true);
}

function unauthorized()
{
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit;
}
