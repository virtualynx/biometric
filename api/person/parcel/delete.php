<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");

use biometric\src\core\models\SubjectLandParcelModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$parcelId = !empty($input['parcel_id']) ? (int) $input['parcel_id'] : 0;
$nik = $input['nik'] ?? null;

if ($parcelId <= 0 || empty($nik)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing parcel_id or NIK',
    ]);
    exit;
}

try {
    $model = new SubjectLandParcelModel();
    $deleted = $model->softDelete($parcelId, trim((string) $nik));

    if (!$deleted) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Bidang tanah tidak ditemukan.',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Bidang tanah berhasil dihapus.',
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
