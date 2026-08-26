<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

$requestBody = file_get_contents("php://input");
$json = json_decode($requestBody, true);

$sk_number = null;

if (!empty($json['sk_number'])) {
    $sk_number = $json['sk_number'];
} elseif (!empty($_POST['sk_number'])) {
    $sk_number = $_POST['sk_number'];
}

try {
    $pm = new PersonModel();
    $persons = $pm->list($sk_number);

    echo json_encode([
        "status" => "success",
        "count"  => count($persons),
        "data"   => $persons
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => biometricPublicExceptionMessage($e, 'Gagal memuat daftar subjek.')
    ]);
}
