<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/DocumentModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;

$jsonInput = json_decode((string) file_get_contents('php://input'), true);
$input = array_replace(
    is_array($_GET) ? $_GET : [],
    is_array($_POST) ? $_POST : [],
    is_array($jsonInput) ? $jsonInput : []
);

if(empty($input['nik']) || empty($input['filename'])){
    http_response_code(400);
    echo 'Parameter nik & filename is required';
    exit;
}

$dcm = new DocumentModel();

$documents = $dcm->get((string) $input['nik']);

$file = null;
foreach($documents as $row){
    if($input['filename'] == $row->filename){
        $file = $row;
    }
}

if(empty($file)){
    echo 'File not found';
    exit;
}

$fu = new FileUploadModel();
// $is_base64 = filter_var($_GET['is_base64'], FILTER_VALIDATE_BOOLEAN);
// $isBase64String = $is_base64 && in_array($file->extension, ['gif', 'png', 'jpg', 'jpeg']);

// if($isBase64String){
if(!empty($input['is_base64']) && filter_var($input['is_base64'], FILTER_VALIDATE_BOOLEAN) == true){
    try{
        echo $fu->getBase64String((string) $input['filename'], $file->file_path);
    }catch(\Exception $e){
        http_response_code(404);
        echo 'File not found';
    }
}else{
    try{
        $fu->downloadFile((string) $input['filename'], $file->file_path);
    }catch(\Exception $e){
        http_response_code(404);
        echo 'File not found';
    }
}
