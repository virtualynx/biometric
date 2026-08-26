<?php

require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiImportModel.php");

use biometric\src\core\models\PotensiImportModel;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode tidak diizinkan.']);
    exit;
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 4 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload impor terlalu besar.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON tidak valid.']);
    exit;
}

$action = $input['action'] ?? 'preview';
$rows = $input['rows'] ?? null;
$skNumber = trim((string) ($input['sk_number'] ?? ''));
$location = trim((string) ($input['location'] ?? ''));

if (!in_array($action, ['preview', 'import'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aksi impor tidak valid.']);
    exit;
}

if (!is_array($rows) || empty($rows)) {
    http_response_code(422);
    echo json_encode(['error' => 'File tidak memiliki baris data yang dapat diproses.']);
    exit;
}

if (
    $skNumber === '' ||
    strlen($skNumber) > 50 ||
    preg_match('/[\x00-\x1F\x7F]/', $skNumber)
) {
    http_response_code(422);
    echo json_encode(['error' => 'Nomor SK aktif tidak valid.']);
    exit;
}

if (
    $location === '' ||
    strlen($location) > 255 ||
    preg_match('/[\x00-\x1F\x7F]/', $location)
) {
    http_response_code(422);
    echo json_encode(['error' => 'Lokasi aktif tidak valid.']);
    exit;
}

try {
    $model = new PotensiImportModel();

    if ($action === 'preview') {
        $validatedRows = $model->validateRows($rows);
        echo json_encode([
            'status' => 'OK',
            'action' => 'preview',
            'location' => $location,
            'sk_number' => $skNumber,
            'summary' => $model->summarize($validatedRows),
            'rows' => $validatedRows,
        ]);
        exit;
    }

    $result = $model->import($rows, $skNumber);
    echo json_encode([
        'status' => 'SUCCESS',
        'action' => 'import',
        'location' => $location,
        'sk_number' => $skNumber,
        'inserted' => $result['inserted'],
        'summary' => $result['summary'],
        'rows' => $result['rows'],
        'message' => $result['inserted'] . ' subjek berhasil ditambahkan ke Potensi.',
    ]);
} catch (\InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'error' => biometricPublicExceptionMessage($exception, 'Gagal mengimpor data potensi.')
    ]);
} catch (\Throwable $exception) {
    error_log('Potensi bulk import failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Impor data Potensi gagal diproses. Tidak ada data parsial yang disimpan.',
    ]);
}
