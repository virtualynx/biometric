<?php

use biometric\src\core\Fingerprint;
use biometric\src\core\models\FingerprintModel;
use biometric\src\core\models\PersonModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FingerprintModel.php");
require_once(dirname(__FILE__)."/../../src/core/Fingerprint.php");
// require_once(dirname(__FILE__)."/../../src/core/helpers/helpers.php");

$nik = is_scalar($_POST['nik'] ?? null) ? trim((string) $_POST['nik']) : '';
$submittedFmds = $_POST['fmds'] ?? null;

if(
    preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1 ||
    !is_array($submittedFmds) ||
    count($submittedFmds) < 2 ||
    count($submittedFmds) > 12
){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$encodedFmds = json_encode($submittedFmds);
if (!is_string($encodedFmds) || strlen($encodedFmds) > 2 * 1024 * 1024) {
    http_response_code(400);
    echo 'Fingerprint payload is invalid';
    exit;
}
$fmds = json_decode($encodedFmds);

$indexFmds = [];
$thumbFmds = [];
foreach($fmds as $row){
    if (!is_object($row) || !isset($row->fingerType, $row->fmd)) {
        continue;
    }
    $normalizedFmd = Fingerprint::normalizeFmd($row->fmd);
    if ($normalizedFmd === null) {
        continue;
    }
    if($row->fingerType == 'index'){
        $indexFmds []= $normalizedFmd;
    }
    if($row->fingerType == 'thumb'){
        $thumbFmds []= $normalizedFmd;
    }
}

if (empty($indexFmds) || empty($thumbFmds)) {
    http_response_code(400);
    echo 'Fingerprint samples are incomplete';
    exit;
}

keycloak_enforce_biometric_search_policy('fingerprint', true);
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
$res = $fpm->clearFingerprintsForNik($nik);
$fpm->add($nik, FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_INDEX, $finger1);
$fpm->add($nik, FingerprintModel::HAND_SIDE_RIGHT, FingerprintModel::FINGER_TYPE_THUMB, $finger2);

echo json_encode(['status' => 'success']);
