<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/PhotoModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\PhotoModel;

if(empty($_GET['nik']) || empty($_GET['filename'])){
    http_response_code(400);
    echo 'Parameter nik & filename is required';
    exit;
}

$phm = new PhotoModel();

$photos = $phm->get($_GET['nik']);

$file = null;
foreach($photos as $row){
    if($_GET['filename'] == $row->filename){
        $file = $row;
    }
}

if(empty($file)){
    echo 'File not found';
    exit;
}

$fu = new FileUploadModel();
if(!empty($_GET['is_base64']) && filter_var($_GET['is_base64'], FILTER_VALIDATE_BOOLEAN) == true){
    echo $fu->getBase64String($_GET['filename'], $file->photo_path);
}else{
    $fu->downloadFile($_GET['filename'], $file->photo_path);
}