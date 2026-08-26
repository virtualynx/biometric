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
$reference = is_array($input) ? trim((string) ($input['reference'] ?? '')) : '';
$identity = keycloak_authenticated_identity();
$actor = trim((string) ($identity['sub'] ?? ''));
if ($actor === '') {
    keycloak_json_error(401, 'unauthorized', 'Autentikasi diperlukan.');
}

keycloak_enforce_rate_limit(
    keycloak_security_store(),
    'actor_subject_reference_resolve',
    $actor,
    240,
    60,
    $actor
);

try {
    $nik = (new SubjectReferenceStore())->resolve($actor, $reference);
    if ($nik === null) {
        keycloak_json_error(
            404,
            'reference_not_found',
            'Tautan verifikasi tidak valid atau sudah kedaluwarsa.'
        );
    }

    echo json_encode([
        'status' => 'success',
        'data' => ['nik' => $nik],
    ]);
} catch (Throwable $exception) {
    error_log('[biometric-security] subject reference resolve failed: ' . $exception->getMessage());
    keycloak_json_error(500, 'reference_resolve_failed', 'Tautan verifikasi belum dapat dibuka.');
}
