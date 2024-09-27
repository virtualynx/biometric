<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

if(empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing NIK';
    exit;
}

$pm = new PersonModel();

try{
    $person = $pm->get($_POST['nik']);
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}

echo json_encode($person);
