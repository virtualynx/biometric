<?php

use biometric\src\core\models\FaceModel;
use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
// require_once(dirname(__FILE__)."/../../src/core/helpers/helpers.php");

$requestBody = file_get_contents('php://input');
$json = json_decode($requestBody);

$faces = [];
$fm = new FaceModel();
$person_ids = [];
if(!empty($json->person_ids)){
    $person_ids = $json->person_ids;
}else if(!empty($json->sk_number)){
    $pm = new PersonModel();
    $persons = $pm->list();
    foreach($persons as $row){
        if($row->sk_number == $json->sk_number){
            $person_ids []= $row->nik;
        }
    }
}

$faces = $fm->list($person_ids);

echo json_encode([
    'status' => 'success',
    'data' => $faces
]);
