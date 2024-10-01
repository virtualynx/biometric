<?php
require_once(dirname(__FILE__)."/../../src/utils/Helper.php");
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/DocumentModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/PhotoModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/QueueModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\utils\Helper;

if(empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing parameter nik';
    exit;
}

if(empty($_POST['photo_type'])){
    http_response_code(400);
    echo 'Missing parameter photo_type';
    exit;
}

$photoType = $_POST['photo_type'];
if($photoType != PhotoModel::PHOTO_TYPE_BIOMETRIC && empty($_POST['filename'])){
    http_response_code(400);
    echo 'Missing parameter filename';
    exit;
}

$nik = $_POST['nik'];
$phm = new PhotoModel();
$existing = null;

if($photoType == PhotoModel::PHOTO_TYPE_BIOMETRIC){
    $existings = $phm->get($nik);
    foreach($existings as $row){
        if($row->type == PhotoModel::PHOTO_TYPE_BIOMETRIC){
            $existing = $row;
            break;
        }
    }
}else{
    $filename = $_POST['filename'];
    $existings = $phm->get($nik, $filename);
    if(count($existings)>0){
        $existing = $existings[0];
    }
}

if(!empty($existing)){
    $phm->beginTransaction();

    try{
        $fum = new FileUploadModel();
        $fum->deleteFile($existing->photo_path);
        $phm->delete($nik, $existing->filename);
    }catch(\Exception $e){
        echo $e->getMessage();
    }

    $phm->endTransaction();
}

echo 'success';
