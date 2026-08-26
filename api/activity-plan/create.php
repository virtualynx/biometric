<?php

require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/ActivityPlanModel.php");

use biometric\src\core\models\ActivityPlanModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

$required = ['plan_name', 'site_desc', 'sk_number', 'date_start', 'date_end'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "Missing $field",
        ]);
        exit;
    }
}

$dateStart = date_create((string) $input['date_start']);
$dateEnd = date_create((string) $input['date_end']);

if (!$dateStart || !$dateEnd) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Format tanggal rencana kegiatan tidak valid.',
    ]);
    exit;
}

if ($dateStart > $dateEnd) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.',
    ]);
    exit;
}

$payload = (object) [
    'plan_name' => trim((string) $input['plan_name']),
    'batch_no' => !empty($input['batch_no']) ? (int) $input['batch_no'] : null,
    'site_desc' => trim((string) $input['site_desc']),
    'sk_number' => trim((string) $input['sk_number']),
    'date_start' => $dateStart->format('Y-m-d'),
    'date_end' => $dateEnd->format('Y-m-d'),
    'notes' => !empty($input['notes']) ? trim((string) $input['notes']) : null,
];

try {
    $model = new ActivityPlanModel();
    $plan = $model->create($payload);

    echo json_encode([
        'status' => 'success',
        'message' => 'Rencana kegiatan berhasil dibuat.',
        'data' => $plan,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal membuat rencana kegiatan.'),
    ]);
}
