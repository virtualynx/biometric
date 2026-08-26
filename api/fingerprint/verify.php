<?php

use biometric\src\core\Fingerprint;
use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__)."/../../src/core/Fingerprint.php");

$fmd = $_POST['fmd'] ?? null;
$encodedFmd = Fingerprint::normalizeFmd($fmd);
if($encodedFmd === null){
    http_response_code(400);
    echo 'Missing required parameter';
    exit;
}

$result = ['person' => null];
$pm = new PersonModel();
if(!empty($_POST['nik'])){
    $nik = is_scalar($_POST['nik']) ? trim((string) $_POST['nik']) : '';
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1) {
        http_response_code(400);
        echo 'Invalid NIK';
        exit;
    }
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
        $fpres = $fp->verify($encodedFmd, $fmdArr);
        if($fpres === 'match'){
            $result['person'] = $pm->get($nik);
        }
    }
}else{
    keycloak_enforce_biometric_search_policy('fingerprint', true);
    $result = $pm->getByFmd($encodedFmd);
}

echo json_encode($result);
