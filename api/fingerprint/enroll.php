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
// $response_index = $fp->enroll($indexFmds);
// if($response_index === "enrollment failed"){
//     $response_index = null;
// }else{
//     $response_index = $response_index->enrolled_index_finger;
// }
// $response_thumb = $fp->enroll($thumbFmds);
// if($response_thumb === "enrollment failed"){
//     $response_thumb = null;
// }else{
//     $response_thumb = $response_thumb->enrolled_index_finger;
// }

$response = $fp->enroll($indexFmds, $thumbFmds);
$finger1 = $response->finger1;
$finger2 = $response->finger2;

$result = [
    'status' => 'success',
    'data' => [
        'index' => $finger1,
        'thumb' => $finger1
    ]
];

if($finger1 == null && $finger2 == null){
    $result['status'] = 'failed';
}

if($result['status'] == 'success'){
    
}

echo json_encode($result);
