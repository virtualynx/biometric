<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_api_header.php';
require_once dirname(__DIR__, 3) . '/src/core/security/SubjectReferenceStore.php';

use biometric\src\core\security\SubjectReferenceStore;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    keycloak_json_error(405, 'method_not_allowed', 'Metode permintaan tidak diizinkan.');
}

$input = json_decode((string) file_get_contents('php://input'), true);
$nik = is_array($input) ? trim((string) ($input['nik'] ?? '')) : '';
if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1) {
    keycloak_json_error(422, 'invalid_subject', 'Data subjek tidak valid.');
}

$identity = keycloak_authenticated_identity();
$actor = trim((string) ($identity['sub'] ?? ''));
if ($actor === '') {
    keycloak_json_error(401, 'unauthorized', 'Autentikasi diperlukan.');
}

keycloak_enforce_rate_limit(
    keycloak_security_store(),
    'actor_subject_reference_issue',
    $actor,
    60,
    60,
    $actor
);

try {
    $issued = (new SubjectReferenceStore())->issue($actor, $nik);
    echo json_encode([
        'status' => 'success',
        'data' => $issued,
    ]);
} catch (OutOfBoundsException $exception) {
    keycloak_json_error(404, 'subject_not_found', 'Data subjek tidak ditemukan.');
} catch (Throwable $exception) {
    error_log('[biometric-security] subject reference issue failed: ' . $exception->getMessage());
    keycloak_json_error(500, 'reference_issue_failed', 'Tautan verifikasi belum berhasil dibuat.');
}
