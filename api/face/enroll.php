<?php

use biometric\src\core\models\FaceModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");
// require_once(dirname(__FILE__)."/../../src/core/helpers/helpers.php");

$requestBody = file_get_contents('php://input');
$json = json_decode($requestBody, true);

if (!is_array($json)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload JSON tidak valid.']);
    exit;
}

$personId = is_scalar($json['person_id'] ?? null) ? trim((string) $json['person_id']) : '';
$idType = is_scalar($json['id_type'] ?? null) ? strtoupper(trim((string) $json['id_type'])) : '';
$encoding = $json['encoding'] ?? null;

if (
    preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $personId) !== 1 ||
    !in_array($idType, [FaceModel::ID_TYPE_NIP, FaceModel::ID_TYPE_NIK, FaceModel::ID_TYPE_PHONE, FaceModel::ID_TYPE_EMAIL], true) ||
    !is_array($encoding) ||
    count($encoding) !== 128
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data descriptor wajah tidak valid.']);
    exit;
}

$normalizedEncoding = [];
foreach ($encoding as $value) {
    if (!is_numeric($value) || !is_finite((float) $value) || abs((float) $value) > 10) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data descriptor wajah tidak valid.']);
        exit;
    }
    $normalizedEncoding[] = (float) $value;
}

$fm = new FaceModel();
$encodedDescriptor = json_encode($normalizedEncoding, JSON_PRESERVE_ZERO_FRACTION);
if (!is_string($encodedDescriptor)) {
    throw new \RuntimeException('Unable to encode face descriptor');
}
$fm->enroll($personId, $idType, $encodedDescriptor);

echo json_encode(['status' => 'success']);
