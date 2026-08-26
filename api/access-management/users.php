<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_api_header.php';
require_once dirname(__DIR__, 2) . '/src/core/security/ResourceScopeAuthorizer.php';

use biometric\src\core\security\KeycloakAdminException;
use biometric\src\core\security\ResourceScopeAuthorizer;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Allow: GET');
    keycloak_json_error(405, 'method_not_allowed', 'Metode permintaan tidak diizinkan.');
}

$identity = keycloak_authenticated_identity();
$actor = trim((string) ($identity['sub'] ?? ''));
keycloak_enforce_rate_limit(
    keycloak_security_store(),
    'actor_access_management_read',
    $actor,
    60,
    60,
    $actor
);

$search = substr(trim((string) ($_GET['search'] ?? '')), 0, 100);

try {
    $users = keycloak_admin_client()->listExternalUsers($search, 200);
    $userIds = array_values(array_filter(array_column($users, 'id')));
    $scopeMap = (new ResourceScopeAuthorizer())->listActiveForActors($userIds);
    foreach ($users as &$user) {
        $user['scopes'] = $scopeMap[$user['id']] ?? [];
    }
    unset($user);

    header('Cache-Control: no-store');
    echo json_encode([
        'status' => 'success',
        'count' => count($users),
        'data' => $users,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (KeycloakAdminException $exception) {
    keycloak_json_error(
        $exception->httpStatus(),
        'user_directory_unavailable',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log('[biometric-security] external user directory failed');
    keycloak_json_error(500, 'user_directory_failed', 'Daftar pengguna eksternal belum dapat dimuat.');
}
