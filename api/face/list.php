<?php

use biometric\src\core\models\FaceModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");

$requestBody = file_get_contents('php://input');
$json = json_decode($requestBody, true);

if (!is_array($json)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload JSON tidak valid.']);
    exit;
}

header('Cache-Control: no-store, private, max-age=0');
keycloak_enforce_face_descriptor_export_policy();

$fm = new FaceModel();
$person_ids = [];
$scope = 'global';
$sk_number = null;

if (!empty($json['scope']) && in_array($json['scope'], ['global', 'sk'], true)) {
    $scope = $json['scope'];
}

if(!empty($json['person_ids']) && is_array($json['person_ids'])){
    if (count($json['person_ids']) > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Maksimal 100 subjek per permintaan.']);
        exit;
    }
    $person_ids = array_values(array_unique(array_filter(array_map(
        static function ($personId): string {
            $personId = is_scalar($personId) ? trim((string) $personId) : '';
            return preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $personId) === 1 ? $personId : '';
        },
        $json['person_ids']
    ))));
    $scope = 'person_ids';
}else if(!empty($json['sk_number'])){
    $sk_number = is_scalar($json['sk_number']) ? trim((string) $json['sk_number']) : '';
    if ($sk_number === '' || strlen($sk_number) > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nomor SK tidak valid.']);
        exit;
    }
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
