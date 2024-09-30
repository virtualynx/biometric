<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;

if(empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing NIK';
    exit;
}

$pm = new PersonModel();

try{
    $person = $pm->get($_POST['nik']);

    $person = json_decode(json_encode($person), true);
    $photos = $person['photos'];
    $bioPhoto = null;
    foreach($photos as $row){
        if($row['type'] == 'biometric'){
            $bioPhoto = $row;
            break;
        }
    }
    if(!empty($bioPhoto)){
        $fum = new FileUploadModel();
        $bioPhoto = $fum->getBase64String($bioPhoto['filename'], $bioPhoto['photo_path']);
    }
    $person['photo'] = $bioPhoto;
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}

echo json_encode($person);
