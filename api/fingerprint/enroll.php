<?php

use biometric\src\core\Fingerprint;
use biometric\src\core\models\FingerprintModel;
use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FingerprintModel.php");
require_once(dirname(__FILE__)."/../../src/core/Fingerprint.php");
// require_once(dirname(__FILE__)."/../../src/core/helpers/helpers.php");

if(empty($_POST["nik"]) || empty($_POST["fmds"])){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$fmds = json_decode(json_encode($_POST['fmds']));

$indexFmds = [];
$thumbFmds = [];
foreach($fmds as $row){
    if($row->fingerType == 'index'){
        $indexFmds []= $row->fmd;
    }
    if($row->fingerType == 'thumb'){
        $thumbFmds []= $row->fmd;
    }
}

$pm = new PersonModel();
$existing = $pm->getByFmd($indexFmds[0]);
if(empty($existing->person)){
    $existing = $pm->getByFmd($thumbFmds[0]);
}
if(!empty($existing->person)){
    echo json_encode(['status' => 'Already registered under another person']);
    exit;
}

$fp = new Fingerprint();
$response = $fp->enroll($indexFmds, $thumbFmds);
$finger1 = $response->finger1;
$finger2 = $response->finger2;

if($finger1 == null || $finger2 == null){
    echo json_encode(['status' => 'Insufficient samples']);
    exit;
}

$fpm = new FingerprintModel();
$res = $fpm->clearFingerprintsForNik($_POST["nik"]);
$fpm->add($_POST["nik"], FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_INDEX, $finger1);
$fpm->add($_POST["nik"], FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_THUMB, $finger2);

echo json_encode(['status' => 'success']);
