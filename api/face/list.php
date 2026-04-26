<?php

use biometric\src\core\models\FaceModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");

$requestBody = file_get_contents('php://input');
$json = json_decode($requestBody);

$fm = new FaceModel();
$person_ids = [];
$scope = 'global';
$sk_number = null;

if (!empty($json->scope) && in_array($json->scope, ['global', 'sk'], true)) {
    $scope = $json->scope;
}

if(!empty($json->person_ids) && is_array($json->person_ids)){
    $person_ids = $json->person_ids;
    $scope = 'person_ids';
}else if(!empty($json->sk_number)){
    $sk_number = $json->sk_number;
    if ($scope === 'sk') {
        $scope = 'sk';
    }
}

$faces = $fm->listActiveDescriptors(
    !empty($person_ids) ? $person_ids : null,
    $scope === 'sk' ? $sk_number : null
);

echo json_encode([
    'status' => 'success',
    'meta' => [
        'scope' => $scope,
        'count' => count($faces),
    ],
    'data' => $faces
]);
