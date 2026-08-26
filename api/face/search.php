<?php

declare(strict_types=1);

use biometric\src\core\biometrics\FaceDescriptorMatcher;
use biometric\src\core\models\FaceModel;

require_once dirname(__FILE__) . '/../_api_header.php';
require_once dirname(__FILE__) . '/../../src/core/biometrics/FaceDescriptorMatcher.php';
require_once dirname(__FILE__) . '/../../src/core/models/FaceModel.php';

header('Cache-Control: no-store, private, max-age=0');

$requestBody = file_get_contents('php://input');
$json = is_string($requestBody) ? json_decode($requestBody, true) : null;
if (!is_array($json)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload JSON tidak valid.']);
    exit;
}

$scope = strtolower(trim((string) ($json['scope'] ?? 'sk')));
if (!in_array($scope, ['global', 'sk'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cakupan pencarian tidak valid.']);
    exit;
}

$skNumber = null;
if ($scope === 'sk') {
    $skNumber = is_scalar($json['sk_number'] ?? null)
        ? trim((string) $json['sk_number'])
        : '';
    if ($skNumber === '' || strlen($skNumber) > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nomor SK tidak valid.']);
        exit;
    }
}

$descriptor = FaceDescriptorMatcher::normalizeDescriptor($json['encoding'] ?? null);
if ($descriptor === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Descriptor wajah tidak valid.']);
    exit;
}

keycloak_enforce_biometric_search_policy('face', $scope === 'global');

$faceModel = new FaceModel();
$match = $faceModel->matchActiveDescriptor($descriptor, $skNumber);

echo json_encode([
    'status' => 'success',
    'data' => [
        'state' => $match['state'],
        'person_id' => $match['state'] === 'accepted' ? $match['person_id'] : null,
        'best_distance' => $match['best_distance'],
        'confidence' => $match['confidence'],
        'has_candidates' => $match['candidate_count'] > 0,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
