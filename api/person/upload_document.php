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

if(empty($_FILES['document'])){
    http_response_code(400);
    echo 'Missing Document File';
    exit;
}

$doc_type = DocumentModel::DOCUMENT_TYPE_DOCUMENT;
if(!empty($_POST['document_type'])){
    $doc_type = $_POST['document_type'];
}

$desc = null;
if(!empty($_POST['description'])){
    $desc = $_POST['description'];
}

$fu = new FileUploadModel();
$filedata = $fu->upload($_FILES['document'], null, "person/".$_POST['nik']."/documents/", true);

$dcm = new DocumentModel();
try{
    $dcm->add($_POST['nik'], $filedata->filename, $filedata->path, $doc_type, $desc);
}catch(\mysqli_sql_exception $e){
    if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
        throw $e;
    }
}
