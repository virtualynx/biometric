<?php
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/DocumentModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;

if(empty($_GET['nik']) || empty($_GET['filename'])){
    http_response_code(400);
    echo 'Parameter nik & filename is required';
    exit;
}

$dcm = new DocumentModel();

$documents = $dcm->get($_GET['nik']);

$file = null;
foreach($documents as $row){
    if($_GET['filename'] == $row->filename){
        $file = $row;
    }
}

if(empty($file)){
    echo 'File not found';
    exit;
}

$fu = new FileUploadModel();
$fu->downloadFile($_GET['filename'], $file->file_path);