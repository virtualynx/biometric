<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");

use biometric\src\core\models\SubjectLandParcelModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$nik = $input['nik'] ?? $_GET['nik'] ?? null;

if (empty($nik)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing NIK',
    ]);
    exit;
}

try {
    $model = new SubjectLandParcelModel();
    $data = $model->listByNik($nik);

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal memuat bidang tanah.'),
    ]);
}
