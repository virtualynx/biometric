<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");

use biometric\src\core\models\SubjectLandParcelModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

if (empty($input['nik']) || empty($input['parcel_label'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing NIK or parcel_label',
    ]);
    exit;
}

$payload = (object) [
    'id' => !empty($input['id']) ? (int) $input['id'] : null,
    'nik' => trim((string) $input['nik']),
    'sk_number' => !empty($input['sk_number']) ? trim((string) $input['sk_number']) : null,
    'parcel_label' => trim((string) $input['parcel_label']),
    'parcel_number' => !empty($input['parcel_number']) ? trim((string) $input['parcel_number']) : null,
    'village' => !empty($input['village']) ? trim((string) $input['village']) : null,
    'area_declared' => isset($input['area_declared']) && $input['area_declared'] !== ''
        ? (float) $input['area_declared']
        : null,
    'notes' => !empty($input['notes']) ? trim((string) $input['notes']) : null,
];

try {
    $model = new SubjectLandParcelModel();
    $parcel = $model->save($payload);

    echo json_encode([
        'status' => 'success',
        'message' => empty($payload->id)
            ? 'Bidang tanah berhasil ditambahkan.'
            : 'Bidang tanah berhasil diperbarui.',
        'data' => $parcel,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal menyimpan bidang tanah.'),
    ]);
}
