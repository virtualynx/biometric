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
    'actor_external_user_create',
    $actor,
    10,
    60,
    $actor
);

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    keycloak_json_error(400, 'invalid_request', 'Data pengguna tidak valid.');
}
$scopes = is_array($input['scopes'] ?? null) ? $input['scopes'] : [];
if ($scopes === []) {
    keycloak_json_error(422, 'scope_required', 'Pilih minimal satu lokasi atau SK.');
}

$client = null;
$createdUserId = '';
try {
    $client = keycloak_admin_client();
    $created = $client->createExternalUser($input);
    $createdUserId = (string) ($created['user']['id'] ?? '');
    $normalizedScopes = (new ResourceScopeAuthorizer())->replaceActiveScopes(
        $createdUserId,
        $scopes,
        'Dikelola melalui aplikasi',
        $actor
    );
    $created['user']['scopes'] = $normalizedScopes;

    keycloak_security_store()->recordEvent(
        'external_user_created',
        'info',
        $actor,
        keycloak_client_ip(),
        [
            'target_hash' => substr(hash('sha256', $createdUserId), 0, 16),
            'scope_count' => count($normalizedScopes),
        ]
    );
    header('Cache-Control: no-store');
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Pengguna eksternal berhasil dibuat.',
        'data' => $created,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (KeycloakAdminException $exception) {
    keycloak_json_error(
        $exception->httpStatus(),
        'external_user_create_failed',
        $exception->getMessage()
    );
} catch (InvalidArgumentException $exception) {
    if ($client !== null && $createdUserId !== '') {
        $client->deleteUser($createdUserId);
    }
    keycloak_json_error(422, 'invalid_scope', $exception->getMessage());
} catch (Throwable $exception) {
    if ($client !== null && $createdUserId !== '') {
        $client->deleteUser($createdUserId);
    }
    error_log('[biometric-security] external user creation failed');
    keycloak_json_error(500, 'external_user_create_failed', 'Pengguna eksternal belum berhasil dibuat.');
}
