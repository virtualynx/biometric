<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");

use biometric\src\core\Database;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\PhotoModel;

if(empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing NIK';
    exit;
}

$pm = new PersonModel();

try{
    $person = $pm->get($_POST['nik']);
}catch(\Exception $e){
    if($e->getMessage() != 'Data not found'){
        http_response_code(500);
        echo $e->getMessage();
        exit;
    }
}

if(empty($person)){
    $person = json_decode(json_encode([
        'nik' => $_POST['nik'],
        'name' => $_POST['name'],
        'address' => $_POST['address'],
        'familycard_no' => $_POST['familycard_no'],
        'village' => $_POST['village'],
        'phone' => $_POST['phone']
    ]));

    $pm->add($person);
}else{
    $person->name = $_POST['name'];
    $person->address = $_POST['address'];
    $person->familycard_no = $_POST['familycard_no'];
    $person->village = $_POST['village'];
    $person->phone = $_POST['phone'];

    $pm->update($person);
}

$fu = new FileUploadModel();

if(!empty($_FILES["photo"])){
    $filedata = $fu->upload($_FILES["photo"], $_POST['nik'], 'profile/');

    $phm = new PhotoModel();
    $phm->add($_POST['nik'], $filedata->filename, $filedata->path);
}

if(!empty($_FILES["documents"])){
    $files = [];
    $filecount = count($_FILES["documents"]['name']);

    for($a=0; $a<$filecount; $a++){
        $files []= [
            'name' => $_FILES["documents"]['name'][$a],
            'type' => $_FILES["documents"]['type'][$a],
            'tmp_name' => $_FILES["documents"]['tmp_name'][$a],
            'error' => $_FILES["documents"]['error'][$a],
            'size' => $_FILES["documents"]['size'][$a]
        ];
    }

    $dcm = new DocumentModel();
    foreach($files as $row){
        $filedata = $fu->upload($row, null, 'documents/'.$_POST['nik']);
        $dcm->add($_POST['nik'], $filedata->filename, $filedata->path);
    }
}

echo json_encode($person);
