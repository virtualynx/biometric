<?php

use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

if(empty($_POST["fmd"])){
    http_response_code(400);
    echo 'Missing required parameter';
    exit;
}

$pm = new PersonModel();
$res = $pm->getByFmd($_POST["fmd"]);

echo json_encode($res);