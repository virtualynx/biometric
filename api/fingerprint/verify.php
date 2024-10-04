<?php

use biometric\src\core\Fingerprint;
use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/Fingerprint.php");

if(empty($_POST["fmd"])){
    http_response_code(400);
    echo 'Missing required parameter';
    exit;
}

$result = ['person' => null];
$pm = new PersonModel();
$fmd = $_POST["fmd"];
if(!empty($_POST['nik'])){
    $nik = $_POST['nik'];
    $fp = new Fingerprint();
    $fingerprints = $pm->getFingerprints($nik);
    if(count($fingerprints) == 0){
        echo 'No enrolled fingerprint data';
        exit;
    }

    $fmdArr = [];
    foreach($fingerprints as $row_fp){
        $fmdArr []= $row_fp->hash;
    }

    if(count($fmdArr)>0){
        $fpres = $fp->verify($fmd, $fmdArr);
        if($fpres === 'match'){
            $result['person'] = $pm->get($nik);
        }
    }
}else{
    $result = $pm->getByFmd($fmd);
}

echo json_encode($result);