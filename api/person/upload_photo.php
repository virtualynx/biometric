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
    echo 'Missing NIK';
    exit;
}

$is_base64 = false;
if(!empty($_POST['is_base64']) && filter_var($_POST['is_base64'], FILTER_VALIDATE_BOOLEAN) == true){
    $is_base64 = true;
}

if(!$is_base64 && empty($_FILES['photo'])){
    http_response_code(400);
    echo 'Missing Photo File';
    exit;
}

$photoType = PhotoModel::PHOTO_TYPE_BIOMETRIC;
if(!empty($_POST['photo_type'])){
    $photoType = $_POST['photo_type'];
}

$desc = null;
if(!empty($_POST['description'])){
    $desc = $_POST['description'];
}

$filename = null;
$targetPath = 'person/'.$_POST['nik'];
if($photoType == PhotoModel::PHOTO_TYPE_BIOMETRIC){
    $filename = $_POST['nik'].'.jpeg';
}else if($photoType == PhotoModel::PHOTO_TYPE_DOCUMENTATION){
    $targetPath = $targetPath.'/photos';
}else{
    http_response_code(400);
    echo 'Valid "photo_type" is either "'.PhotoModel::PHOTO_TYPE_BIOMETRIC.'" or "'.PhotoModel::PHOTO_TYPE_DOCUMENTATION.'"';
    exit;
}

$files = null;
if(!$is_base64){
    $files = $_FILES["photo"];
}else{
    $files = $_POST["photo"];
}

$fu = new FileUploadModel();
$filedata = $fu->upload($files, $filename, "$targetPath/", true, $is_base64);

$phm = new PhotoModel();    
try{
    $phm->add($_POST['nik'], $filedata->filename, $filedata->path, $photoType, $desc, $filedata->extension);
}catch(\mysqli_sql_exception $e){
    if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
        throw $e;
    }
}
