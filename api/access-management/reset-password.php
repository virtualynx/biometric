<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_api_header.php';

use biometric\src\core\security\KeycloakAdminException;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    keycloak_json_error(405, 'method_not_allowed', 'Metode permintaan tidak diizinkan.');
}

$identity = keycloak_authenticated_identity();
$actor = trim((string) ($identity['sub'] ?? ''));
keycloak_enforce_rate_limit(
    keycloak_security_store(),
    'actor_external_password_reset',
    $actor,
    10,
    60,
    $actor
);

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    keycloak_json_error(400, 'invalid_request', 'Data pengguna tidak valid.');
}
$userId = trim((string) ($input['user_id'] ?? ''));

try {
    $credential = keycloak_admin_client()->resetInitialPassword($userId);
    keycloak_security_store()->recordEvent(
        'external_user_initial_password_reset',
        'warning',
        $actor,
        keycloak_client_ip(),
        ['target_hash' => substr(hash('sha256', $userId), 0, 16)]
    );

    header('Cache-Control: no-store');
    echo json_encode([
        'status' => 'success',
        'message' => 'Password awal baru berhasil dibuat.',
        'data' => $credential,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (KeycloakAdminException $exception) {
    keycloak_json_error(
        $exception->httpStatus(),
        'external_password_reset_failed',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log('[biometric-security] external user initial password reset failed');
    keycloak_json_error(
        500,
        'external_password_reset_failed',
        'Password awal baru belum berhasil dibuat.'
    );
}
