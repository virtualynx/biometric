<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$nik = isset($input['nik']) ? trim((string) $input['nik']) : '';
$note = isset($input['verification_note']) ? trim((string) $input['verification_note']) : '';
$byName = isset($input['verification_note_by_name']) ? trim((string) $input['verification_note_by_name']) : null;
$byEmail = isset($input['verification_note_by_email']) ? trim((string) $input['verification_note_by_email']) : null;
$clearNote = !empty($input['clear_note']) && filter_var($input['clear_note'], FILTER_VALIDATE_BOOLEAN);

if ($nik === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'NIK wajib diisi',
    ]);
    exit;
}

if ($note === '' && !$clearNote) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Catatan khusus tidak boleh kosong',
    ]);
    exit;
}

$personModel = new PersonModel();

try {
    if (!$personModel->exists($nik)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Subjek tidak ditemukan',
        ]);
        exit;
    }

    $personModel->updateVerificationNote(
        $nik,
        $clearNote ? null : $note,
        $clearNote ? null : $byName,
        $clearNote ? null : $byEmail
    );

    echo json_encode([
        'status' => 'success',
        'message' => $clearNote
            ? 'Catatan khusus berhasil dihapus'
            : 'Catatan khusus berhasil disimpan',
        'data' => [
            'nik' => $nik,
            'verification_note' => $clearNote ? null : $note,
            'verification_note_by_name' => $clearNote ? null : $byName,
            'verification_note_by_email' => $clearNote ? null : $byEmail,
        ],
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal memperbarui catatan khusus.'),
    ]);
}
