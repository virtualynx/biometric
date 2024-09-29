<?php

use biometric\src\core\models\FingerprintModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FingerprintModel.php");
require_once(dirname(__FILE__)."/../../src/core/Fingerprint.php");

if(empty($_POST["fmd"])){
    http_response_code(400);
    echo 'Missing required parameter';
    exit;
}

$fpm = new FingerprintModel();
$res = $fpm->getByFmd($_POST["fmd"]);

echo json_encode($res);