<?php

use biometric\src\core\Fingerprint;
use biometric\src\core\models\FingerprintModel;

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

$fp = new Fingerprint();
$response = $fp->enroll($indexFmds, $thumbFmds);
$finger1 = $response->finger1;
$finger2 = $response->finger2;

$status = 'success';
if($finger1 == null || $finger2 == null){
    $status = 'failed';
}

if($status == 'success'){
    $fpm = new FingerprintModel();
    $fpm->add($_POST["nik"], FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_INDEX, $finger1);
    $fpm->add($_POST["nik"], FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_THUMB, $finger2);
}

echo json_encode(['status' => $status]);
