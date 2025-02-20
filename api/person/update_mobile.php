<?php
require_once(dirname(__FILE__)."/../../src/utils/Helper.php");
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/DocumentModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/PhotoModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/QueueModel.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\utils\Helper;
use biometric\src\core\models\FaceModel;

if(empty($_POST['nik']) || empty($_POST['sk_number'])){
    http_response_code(400);
    echo 'Missing NIK or sk_number';
    exit;
}

$pm = new PersonModel();

try{
    $person = $pm->get($_POST['nik'], $_POST['sk_number']);
}catch(\Exception $e){
    if($e->getMessage() != 'Data not found'){
        http_response_code(500);
        echo $e->getMessage();
        exit;
    }
}

if(!empty($_POST['luas_tanah']) && $person->luas_tanah !== $_POST['luas_tanah']){
    $person->luas_tanah = floatval($_POST['luas_tanah']);
}
if(!empty($_POST['luas_bangunan']) && $person->luas_bangunan !== $_POST['luas_bangunan']){
    $person->luas_bangunan = floatval($_POST['luas_bangunan']);
}

$fu = new FileUploadModel();
$dcm = new DocumentModel();

if(!empty($_POST['photo_ktp'])){
    $filedata = $fu->upload($_POST['photo_ktp'], 'KTP_'.$_POST['nik'].'.jpeg', "person/".$_POST['nik']."/documents/", true, true);
    try{
        $dcm->add($_POST['nik'], $filedata->filename, $filedata->path, 'KTP', null, $filedata->extension);
    }catch(\mysqli_sql_exception $e){
        if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
            throw $e;
        }
    }
}
if(!empty($_POST['photo_kk'])){
    $filedata = $fu->upload($_POST['photo_kk'], 'KK_'.$_POST['nik'].'.jpeg', "person/".$_POST['nik']."/documents/", true, true);
    try{
        $dcm->add($_POST['nik'], $filedata->filename, $filedata->path, 'KK', null, $filedata->extension);
    }catch(\mysqli_sql_exception $e){
        if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
            throw $e;
        }
    }
}
if(!empty($_POST['photo_profile'])){
    $filedata = $fu->upload($_POST['photo_profile'], $_POST['nik'].'.jpeg', 'person/'.$_POST['nik'].'/', true, true);

    $phm = new PhotoModel();
    try{
        $phm->add($_POST['nik'], $filedata->filename, $filedata->path, PhotoModel::PHOTO_TYPE_BIOMETRIC, null, $filedata->extension);
    }catch(\mysqli_sql_exception $e){
        if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
            throw $e;
        }
    }
}
if(!empty($_POST['face_encoding'])){
    $fm = new FaceModel();
    $encoding = json_encode($_POST['face_encoding']);
    $fm->enroll($_POST['nik'], 'NIK', $encoding);
}

$pm->update_mobile($person, $_POST['sk_number']);

echo json_encode(['status' => 'success']);
