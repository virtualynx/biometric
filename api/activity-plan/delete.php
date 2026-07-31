<?php

require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/ActivityPlanModel.php");

use biometric\src\core\models\ActivityPlanModel;

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$planId = !empty($input['plan_id']) ? (int) $input['plan_id'] : 0;
$siteDesc = isset($input['site_desc']) ? trim((string) $input['site_desc']) : '';
$skNumber = isset($input['sk_number']) ? trim((string) $input['sk_number']) : '';

if ($planId <= 0 || $siteDesc === '' || $skNumber === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'plan_id, site_desc, dan sk_number wajib diisi.',
    ]);
    exit;
}

try {
    $model = new ActivityPlanModel();
    $deletedPlan = $model->softDelete($planId, $siteDesc, $skNumber);

    if (empty($deletedPlan)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Rencana kegiatan tidak ditemukan atau sudah dihapus.',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Rencana kegiatan berhasil dihapus.',
        'data' => $deletedPlan,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
