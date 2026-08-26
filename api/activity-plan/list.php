<?php

require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/ActivityPlanModel.php");

use biometric\src\core\models\ActivityPlanModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$siteDesc = $input['site_desc'] ?? $_GET['site_desc'] ?? null;
$skNumber = $input['sk_number'] ?? $_GET['sk_number'] ?? null;

try {
    $model = new ActivityPlanModel();
    $data = $model->list(
        !empty($siteDesc) ? trim((string) $siteDesc) : null,
        !empty($skNumber) ? trim((string) $skNumber) : null
    );

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal memuat rencana kegiatan.'),
    ]);
}
