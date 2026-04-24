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

$with_photo = true;
if(!empty($_POST['without_photo']) && filter_var($_POST['without_photo'], FILTER_VALIDATE_BOOLEAN) == true){
    $with_photo = false;
}

$pm = new PersonModel();
$fum = new FileUploadModel();

try{
    $person = $pm->get($_POST['nik']);

    $person = json_decode(json_encode($person), true);
    $person['documents'] = array_values(array_filter(
        $person['documents'] ?? [],
        fn($row) => !empty($row['file_path']) && $fum->fileExists($row['file_path'])
    ));

    $photosRaw = array_values(array_filter(
        $person['photos'] ?? [],
        fn($row) => !empty($row['photo_path']) && $fum->fileExists($row['photo_path'])
    ));

    //removes biometric photo from photos
    $photos = [];
    foreach($photosRaw as $row){
        if($row['type'] == 'biometric')continue;

        $photos []= $row;
    }
    $person['photos'] = $photos;

    if($with_photo){
        $bioPhoto = null;
        foreach($photosRaw as $row){
            if($row['type'] == 'biometric'){
                $bioPhoto = $row;
                break;
            }
        }
        if(!empty($bioPhoto)){
            try{
                $bioPhoto = $fum->getBase64String($bioPhoto['filename'], $bioPhoto['photo_path']);
            }catch(\Exception $photoException){
                if(str_starts_with($photoException->getMessage(), 'File ') && str_ends_with($photoException->getMessage(), ' does not exists')){
                    error_log($photoException->getMessage());
                    $bioPhoto = null;
                    $person['photo_warning'] = 'Biometric photo file is missing';
                }else{
                    throw $photoException;
                }
            }
        }
        $person['photo'] = $bioPhoto;
    }

    $person['status_list'] = $pm->getStatusList($_POST['nik']);

    echo json_encode($person);
}catch(\Exception $e){
    if($e->getCode() >= 900){
        echo json_encode([
            'status' => $e->getCode(),
            'message' => $e->getMessage()
        ]);
    }else{
        header("HTTP/1.1 500 Internal Server Error");
        echo $e->getMessage();
    }
}
