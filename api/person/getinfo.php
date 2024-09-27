<?php
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

if(empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing NIK';
    exit;
}

$pm = new PersonModel();
$person = $pm->getInfo($_POST['nik']);

if(empty($person)){
    echo 'Data not found';
    exit;
}

echo json_encode($person);
