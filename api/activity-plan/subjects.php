<?php

require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/ActivityPlanModel.php");

use biometric\src\core\models\ActivityPlanModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$planId = !empty($input['plan_id'])
    ? (int) $input['plan_id']
    : (!empty($_GET['plan_id']) ? (int) $_GET['plan_id'] : 0);

if ($planId <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing plan_id',
    ]);
    exit;
}

try {
    $model = new ActivityPlanModel();
    $data = $model->listSubjectMatches($planId);

    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data,
        'nik_list' => array_values(array_map(fn($item) => $item->nik, $data)),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
