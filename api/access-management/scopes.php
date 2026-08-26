<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_api_header.php';
require_once dirname(__DIR__, 2) . '/src/core/security/ResourceScopeAuthorizer.php';

use biometric\src\core\security\KeycloakAdminException;
use biometric\src\core\security\ResourceScopeAuthorizer;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    keycloak_json_error(405, 'method_not_allowed', 'Metode permintaan tidak diizinkan.');
}

$identity = keycloak_authenticated_identity();
$actor = trim((string) ($identity['sub'] ?? ''));
keycloak_enforce_rate_limit(
    keycloak_security_store(),
    'actor_external_scope_write',
    $actor,
    60,
    60,
    $actor
);

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    keycloak_json_error(400, 'invalid_request', 'Data akses tidak valid.');
}
$userId = trim((string) ($input['user_id'] ?? ''));
$scopes = is_array($input['scopes'] ?? null) ? $input['scopes'] : [];

try {
    $user = keycloak_admin_client()->getExternalUser($userId);
    $normalizedScopes = (new ResourceScopeAuthorizer())->replaceActiveScopes(
        $userId,
        $scopes,
        'Dikelola melalui aplikasi',
        $actor
    );
    keycloak_security_store()->recordEvent(
        'external_user_scope_replaced',
        'info',
        $actor,
        keycloak_client_ip(),
        [
            'target_hash' => substr(hash('sha256', $userId), 0, 16),
            'scope_count' => count($normalizedScopes),
        ]
    );

    header('Cache-Control: no-store');
    echo json_encode([
        'status' => 'success',
        'message' => 'Akses pengguna berhasil diperbarui.',
        'data' => [
            'user' => $user,
            'scopes' => $normalizedScopes,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (KeycloakAdminException $exception) {
    keycloak_json_error(
        $exception->httpStatus(),
        'external_scope_update_failed',
        $exception->getMessage()
    );
} catch (InvalidArgumentException $exception) {
    keycloak_json_error(422, 'invalid_scope', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('[biometric-security] external scope update failed');
    keycloak_json_error(500, 'external_scope_update_failed', 'Akses pengguna belum berhasil diperbarui.');
}
