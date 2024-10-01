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
    $filedata = $fu->upload($_FILES["photo"], $_POST['nik'], 'person/'.$_POST['nik'], true);

    $phm = new PhotoModel();
    try{
        $phm->add($_POST['nik'], $filedata->filename, $filedata->path);
    }catch(\mysqli_sql_exception $e){
        if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
            throw $e;
        }
    }
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
        $filedata = $fu->upload($row, null, 'person/'.$_POST['nik'].'/documents', true);
        try{
            $dcm->add($_POST['nik'], $filedata->filename, $filedata->path);
        }catch(\mysqli_sql_exception $e){
            if(!Helper::startsWith($e->getMessage(), 'Duplicate entry')){
                throw $e;
            }
        }
    }
}

$person_arr = json_decode(json_encode($person), true);

$qm = new QueueModel();
$current_queue = $qm->findByNik($person->nik, [QueueModel::STATUS_PENDING, QueueModel::STATUS_PULLED]);
if(empty($current_queue)){
    $current_queue = $qm->add('BMT', $person->nik);
}
$person_arr['queue'] = $current_queue;
$person = json_decode(json_encode($person_arr));;

echo json_encode($person);
