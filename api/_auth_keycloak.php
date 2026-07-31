<?php

require_once(dirname(__FILE__) . "/../src/core/models/EnvFileModel.php");

use biometric\src\core\models\EnvFileModel;

function keycloak_require_auth()
{
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    if (!isset($headers['authorization'])) {
        unauthorized();
    }

    $auth = $headers['authorization'];

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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
