<?php
require_once(dirname(__FILE__)."/../../src/utils/Helper.php");
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

if(empty($_POST['nik']) || empty($_POST['sk_number'])){
    http_response_code(400);
    echo 'Missing NIK or sk_number';
    exit;
}

$pm = new PersonModel();

try{
    $person = $pm->delete($_POST['nik'], $_POST['sk_number']);
}catch(\Exception $e){
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus subjek.']);
}

echo json_encode(['status' => 'success']);
