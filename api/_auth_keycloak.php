<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core/models/EnvFileModel.php';
require_once dirname(__DIR__) . '/src/core/security/ApiSecurityStore.php';
require_once dirname(__DIR__) . '/src/core/security/ResourceScopeAuthorizer.php';
require_once dirname(__DIR__) . '/src/core/security/KeycloakAdminClient.php';

use biometric\src\core\models\EnvFileModel;
use biometric\src\core\security\ApiSecurityStore;
use biometric\src\core\security\KeycloakAdminClient;
use biometric\src\core\security\ResourceScopeAuthorizer;

function keycloak_security_store(): ApiSecurityStore
{
    static $store = null;
    if (!$store instanceof ApiSecurityStore) {
        $store = new ApiSecurityStore();
    }
    return $store;
}

function keycloak_env(string $key, ?string $default = null): ?string
{
    static $env = null;
    static $values = [];

    if (array_key_exists($key, $values)) {
        return $values[$key] ?? $default;
    }

    try {
        if (!$env instanceof EnvFileModel) {
            $env = new EnvFileModel();
        }
        $value = $env->get($key);
        $value = is_string($value) ? trim($value) : '';
        $values[$key] = $value !== '' ? $value : null;
    } catch (Throwable $exception) {
        $values[$key] = null;
    }

    return $values[$key] ?? $default;
}

function keycloak_env_list(string $key, array $default = []): array
{
    $fallback = implode(',', $default);
    $value = (string) keycloak_env($key, $fallback);

    return array_values(array_unique(array_filter(array_map(
        static fn(string $item): string => strtolower(trim($item)),
        explode(',', $value)
    ))));
}

function keycloak_ip_matches_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return false;
    }
    if (strpos($cidr, '/') === false) {
        return hash_equals(strtolower($cidr), strtolower($ip));
    }

    [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton((string) $network);
    if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $maxBits = strlen($ipBinary) * 8;
    if (!is_numeric($prefix) || (int) $prefix < 0 || (int) $prefix > $maxBits) {
        return false;
    }

    $prefixBits = (int) $prefix;
    $fullBytes = intdiv($prefixBits, 8);
    $remainingBits = $prefixBits % 8;
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
}

function keycloak_is_trusted_proxy(string $ip): bool
{
    foreach (keycloak_env_list('BIOMETRIC_TRUSTED_PROXY_CIDRS') as $trustedProxy) {
        if (keycloak_ip_matches_cidr($ip, $trustedProxy)) {
            return true;
        }
    }
    return false;
}

function keycloak_client_ip(): string
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddress === '' || filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }
    if (!keycloak_is_trusted_proxy($remoteAddress)) {
        return $remoteAddress;
    }

    $forwardedFor = function_exists('biometricSecurityRequestHeader')
        ? biometricSecurityRequestHeader('X-Forwarded-For')
        : '';
    $chain = array_values(array_filter(array_map('trim', explode(',', $forwardedFor)), static function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }));
    $chain[] = $remoteAddress;

    for ($index = count($chain) - 1; $index >= 0; $index--) {
        if (!keycloak_is_trusted_proxy($chain[$index])) {
            return $chain[$index];
        }
    }

    return $remoteAddress;
}

function keycloak_bearer_token(): ?string
{
    $authorization = function_exists('biometricSecurityRequestHeader')
        ? biometricSecurityRequestHeader('Authorization')
        : '';
    if ($authorization === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authorization = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    if (preg_match('/^Bearer\s+([^\s]+)$/iD', $authorization, $matches) !== 1) {
        return null;
    }
    $token = $matches[1];
    return strlen($token) <= 8192 ? $token : null;
}

function keycloak_decode_token_payload(string $token): array
{
    $segments = explode('.', $token);
    if (count($segments) !== 3) {
        return [];
    }
    $encodedPayload = strtr($segments[1], '-_', '+/');
    $padding = strlen($encodedPayload) % 4;
    if ($padding !== 0) {
        $encodedPayload .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($encodedPayload, true);
    if (!is_string($decoded)) {
        return [];
    }
    $payload = json_decode($decoded, true);
    return is_array($payload) ? $payload : [];
}

function keycloak_token_roles(array $payload, string $clientId): array
{
    $realmRoles = $payload['realm_access']['roles'] ?? [];
    $roles = is_array($realmRoles) ? $realmRoles : [];
    $roleClientIds = keycloak_env_list(
        'KEYCLOAK_ROLE_CLIENT_IDS',
        array_values(array_unique(array_filter([$clientId, 'reforma-bbt'])))
    );
    foreach ($roleClientIds as $roleClientId) {
        $clientRoles = $payload['resource_access'][$roleClientId]['roles'] ?? [];
        if (is_array($clientRoles)) {
            $roles = array_merge($roles, $clientRoles);
        }
    }

    return array_slice(array_values(array_unique(array_filter(array_map(
        static function ($role): string {
            return is_string($role) ? strtolower(substr(trim($role), 0, 80)) : '';
        },
        $roles
    )))), 0, 100);
}

function keycloak_token_scope_values(array $payload, string $claimName): array
{
    $claimName = trim($claimName);
    if ($claimName === '' || !array_key_exists($claimName, $payload)) {
        return [];
    }

    $values = is_array($payload[$claimName])
        ? $payload[$claimName]
        : preg_split('/\s*,\s*/', (string) $payload[$claimName]);

    return array_slice(array_values(array_unique(array_filter(array_map(
        static fn($value): string => is_scalar($value)
            ? substr(trim((string) $value), 0, 255)
            : '',
        is_array($values) ? $values : []
    )))), 0, 200);
}

function keycloak_validate_token_claims(array $payload, int $now): array
{
    $base = rtrim((string) keycloak_env('KEYCLOAK_BASE_URL', ''), '/');
    $realm = (string) keycloak_env('KEYCLOAK_REALM', '');
    $expectedIssuer = $base . '/realms/' . rawurlencode($realm);
    $issuer = rtrim((string) ($payload['iss'] ?? ''), '/');
    $subject = trim((string) ($payload['sub'] ?? ''));
    $expiresAt = isset($payload['exp']) ? (int) $payload['exp'] : 0;
    $issuedAt = isset($payload['iat']) ? (int) $payload['iat'] : 0;
    $authorizedParty = strtolower(trim((string) ($payload['azp'] ?? '')));
    $allowedClients = keycloak_env_list('KEYCLOAK_ALLOWED_CLIENT_IDS', ['super-sso-bbt']);

    if ($expectedIssuer === '/realms/' || !hash_equals($expectedIssuer, $issuer)) {
        throw new \UnexpectedValueException('invalid_issuer');
    }
    if ($subject === '' || strlen($subject) > 255 || $expiresAt <= $now) {
        throw new \UnexpectedValueException('invalid_subject_or_expiry');
    }
    if ($issuedAt > 0 && $issuedAt > $now + 60) {
        throw new \UnexpectedValueException('invalid_issued_at');
    }
    if ($authorizedParty === '' || !in_array($authorizedParty, $allowedClients, true)) {
        throw new \UnexpectedValueException('invalid_authorized_party');
    }

    $enforceAudience = filter_var(
        keycloak_env('KEYCLOAK_ENFORCE_AUDIENCE', 'false'),
        FILTER_VALIDATE_BOOLEAN
    );
    if ($enforceAudience) {
        $audiences = $payload['aud'] ?? [];
        $audiences = is_array($audiences) ? $audiences : [$audiences];
        $audiences = array_map(static fn($audience): string => strtolower((string) $audience), $audiences);
        if (array_intersect($allowedClients, $audiences) === []) {
            throw new \UnexpectedValueException('invalid_audience');
        }
    }

    return [
        'sub' => $subject,
        'authorized_party' => $authorizedParty,
        'roles' => keycloak_token_roles($payload, $authorizedParty),
        'resource_scopes' => [
            ResourceScopeAuthorizer::SCOPE_SITE => keycloak_token_scope_values(
                $payload,
                (string) keycloak_env('BIOMETRIC_SITE_SCOPE_CLAIM', 'biometric_sites')
            ),
            ResourceScopeAuthorizer::SCOPE_SK => keycloak_token_scope_values(
                $payload,
                (string) keycloak_env('BIOMETRIC_SK_SCOPE_CLAIM', 'biometric_sk_numbers')
            ),
        ],
    ];
}

function keycloak_json_error(
    int $status,
    string $code,
    string $message,
    ?int $retryAfter = null
): void {
    http_response_code($status);
    header('Cache-Control: no-store');
    if ($status === 401) {
        header('WWW-Authenticate: Bearer realm="biometric-api", error="invalid_token"');
    }
    if ($retryAfter !== null) {
        header('Retry-After: ' . max(1, $retryAfter));
    }
    $context = $GLOBALS['biometric_security_context'] ?? [];
    echo json_encode([
        'status' => 'error',
        'error' => $code,
        'message' => $message,
        'request_id' => $context['request_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function keycloak_enforce_rate_limit(
    ApiSecurityStore $store,
    string $scope,
    string $identifier,
    int $limit,
    int $windowSeconds,
    ?string $actor = null
): void {
    $result = $store->consumeRateLimit($scope, $identifier, $limit, $windowSeconds);
    if (!empty($result['allowed'])) {
        return;
    }
    $store->recordEvent(
        'rate_limit_exceeded',
        'warning',
        $actor,
        keycloak_client_ip(),
        ['scope' => $scope, 'limit' => $limit, 'window_seconds' => $windowSeconds]
    );
    keycloak_json_error(
        429,
        'rate_limit_exceeded',
        'Terlalu banyak permintaan. Silakan coba kembali beberapa saat lagi.',
        (int) ($result['retry_after'] ?? 1)
    );
}

function keycloak_ca_bundle_path(): ?string
{
    $configuredPath = trim((string) keycloak_env('KEYCLOAK_CA_BUNDLE', ''));
    if ($configuredPath === '') {
        return null;
    }

    $isAbsolute = str_starts_with($configuredPath, '/')
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
    $candidatePath = $isAbsolute
        ? $configuredPath
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($configuredPath, '/\\');
    $resolvedPath = realpath($candidatePath);

    if ($resolvedPath === false || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
        error_log('[biometric-security] configured Keycloak CA bundle is not readable');
        keycloak_json_error(
            503,
            'authentication_unavailable',
            'Layanan autentikasi belum dikonfigurasi dengan aman.'
        );
    }

    return $resolvedPath;
}

function keycloak_validate_remotely(string $token): array
{
    $base = rtrim((string) keycloak_env('KEYCLOAK_BASE_URL', ''), '/');
    $realm = (string) keycloak_env('KEYCLOAK_REALM', '');
    $parts = parse_url($base);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    if (
        $base === '' ||
        $realm === '' ||
        !is_array($parts) ||
        ($scheme !== 'https' && !$isLocal)
    ) {
        keycloak_json_error(
            503,
            'authentication_unavailable',
            'Layanan autentikasi belum dikonfigurasi dengan aman.'
        );
    }
    if (!function_exists('curl_init')) {
        keycloak_json_error(503, 'authentication_unavailable', 'Layanan autentikasi tidak tersedia.');
    }

    $userinfoUrl = $base
        . '/realms/' . rawurlencode($realm)
        . '/protocol/openid-connect/userinfo';
    $curl = curl_init($userinfoUrl);
    $curlOptions = [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ];
    $caBundlePath = keycloak_ca_bundle_path();
    if ($caBundlePath !== null) {
        $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
    }
    curl_setopt_array($curl, $curlOptions);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $transportFailed = $response === false;
    $curlErrorNumber = curl_errno($curl);

    if ($status === 401 || $status === 403) {
        return [];
    }
    if ($transportFailed || $status !== 200 || !is_string($response)) {
        error_log(sprintf(
            '[biometric-security] Keycloak userinfo request failed (http_status=%d, curl_errno=%d)',
            $status,
            $curlErrorNumber
        ));
        keycloak_json_error(
            503,
            'authentication_unavailable',
            'Layanan autentikasi sedang tidak tersedia. Silakan coba kembali.'
        );
    }
    $identity = json_decode($response, true);
    if (!is_array($identity) || !is_string($identity['sub'] ?? null)) {
        return [];
    }
    return [
        'sub' => substr($identity['sub'], 0, 255),
        'preferred_username' => substr((string) ($identity['preferred_username'] ?? ''), 0, 255),
        'name' => substr((string) ($identity['name'] ?? ''), 0, 255),
        'email' => substr((string) ($identity['email'] ?? ''), 0, 255),
    ];
}

function keycloak_request_is_read_only(string $path, string $method): bool
{
    if (in_array($method, ['GET', 'HEAD'], true)) {
        return true;
    }

    if (preg_match('#/api/person/reference/(?:create|resolve)\.php$#i', $path) === 1) {
        return true;
    }

    return preg_match(
        '#/(?:list|list_summary|getinfo|get_documents|document_batch|subjects|status|doc_check|search|history)\.php$#i',
        $path
    ) === 1;
}

function keycloak_required_role_group(string $path, bool $isRead): ?string
{
    if (preg_match('#/api/access-management/#i', $path) === 1) {
        return 'super-admin';
    }
    if ($isRead) {
        return 'read';
    }
    if (preg_match('#/api/person/(?:delete|delete_subject)\.php$#i', $path) === 1) {
        return 'privileged';
    }
    return 'write';
}

function keycloak_identity_roles(array $identity): array
{
    return array_values(array_unique(array_filter(array_map(
        static fn($role): string => is_string($role) ? strtolower(trim($role)) : '',
        is_array($identity['roles'] ?? null) ? $identity['roles'] : []
    ))));
}

function keycloak_identity_has_any_role(array $identity, array $roles): bool
{
    return array_intersect(keycloak_identity_roles($identity), $roles) !== [];
}

function keycloak_super_admin_roles(): array
{
    return array_values(array_diff(array_unique(array_merge(
        keycloak_env_list('BIOMETRIC_SUPER_ADMIN_ROLES', ['admin', 'super-admin']),
        ['super-admin']
    )), ['viewer', 'eksternal']));
}

function keycloak_user_admin_roles(): array
{
    return array_values(array_diff(array_unique(array_merge(
        keycloak_env_list('BIOMETRIC_USER_ADMIN_ROLES', ['super-admin']),
        ['super-admin']
    )), ['admin', 'viewer', 'eksternal', 'access', 'user']));
}

function keycloak_identity_is_viewer(array $identity): bool
{
    if (keycloak_identity_has_any_role($identity, keycloak_super_admin_roles())) {
        return false;
    }

    return keycloak_identity_has_any_role($identity, ['viewer']);
}

function keycloak_identity_is_external(array $identity): bool
{
    if (
        keycloak_identity_has_any_role($identity, keycloak_super_admin_roles())
        || keycloak_identity_is_viewer($identity)
    ) {
        return false;
    }

    return keycloak_identity_has_any_role($identity, ['eksternal']);
}

function keycloak_identity_can_read_all_scopes(array $identity): bool
{
    if (keycloak_identity_is_external($identity)) {
        return false;
    }

    $roles = array_values(array_unique(array_merge(
        keycloak_env_list(
            'BIOMETRIC_SCOPE_READ_ALL_ROLES',
            ['viewer', 'admin', 'super-admin']
        ),
        ['viewer', 'super-admin']
    )));

    return keycloak_identity_has_any_role($identity, $roles);
}

function keycloak_effective_resource_scopes(array $identity): array
{
    $actor = trim((string) ($identity['sub'] ?? ''));
    if ($actor === '') {
        throw new \RuntimeException('Authenticated actor is missing.');
    }

    $claimScopes = is_array($identity['resource_scopes'] ?? null)
        ? $identity['resource_scopes']
        : [];

    return (new ResourceScopeAuthorizer())->effectiveScopes($actor, $claimScopes);
}

function keycloak_filter_master_sk_rows(array $rows, array $identity): array
{
    if (keycloak_identity_can_read_all_scopes($identity)) {
        return array_values($rows);
    }

    $scopes = keycloak_effective_resource_scopes($identity);
    $siteScopes = is_array($scopes[ResourceScopeAuthorizer::SCOPE_SITE] ?? null)
        ? $scopes[ResourceScopeAuthorizer::SCOPE_SITE]
        : [];
    $skScopes = is_array($scopes[ResourceScopeAuthorizer::SCOPE_SK] ?? null)
        ? $scopes[ResourceScopeAuthorizer::SCOPE_SK]
        : [];

    return array_values(array_filter(
        $rows,
        static function (array $row) use ($siteScopes, $skScopes): bool {
            $site = ResourceScopeAuthorizer::normalizeScopeValue(
                (string) ($row['site_desc'] ?? '')
            );
            $skNumber = ResourceScopeAuthorizer::normalizeScopeValue(
                (string) ($row['sk_number'] ?? '')
            );

            return ($site !== '' && in_array($site, $siteScopes, true))
                || ($skNumber !== '' && in_array($skNumber, $skScopes, true));
        }
    ));
}

function keycloak_authorization_decision(array $identity, string $path, bool $isRead): array
{
    $group = keycloak_required_role_group($path, $isRead);
    $requiredRoles = match ($group) {
        'super-admin' => keycloak_user_admin_roles(),
        'read' => array_values(array_unique(array_merge(
            keycloak_env_list(
                'BIOMETRIC_READ_ROLES',
                ['access', 'user', 'viewer', 'eksternal', 'admin', 'super-admin']
            ),
            ['viewer', 'eksternal', 'super-admin']
        ))),
        'privileged' => array_values(array_unique(array_merge(
            keycloak_env_list(
                'BIOMETRIC_PRIVILEGED_ROLES',
                ['admin', 'super-admin', 'eksternal']
            ),
            ['eksternal', 'super-admin']
        ))),
        default => array_values(array_unique(array_merge(
            keycloak_env_list(
                'BIOMETRIC_WRITE_ROLES',
                ['access', 'user', 'eksternal', 'admin', 'super-admin']
            ),
            ['eksternal', 'super-admin']
        ))),
    };
    $userRoles = keycloak_identity_roles($identity);
    $viewerMutationDenied = !$isRead && keycloak_identity_is_viewer($identity);

    return [
        'allowed' => array_intersect($requiredRoles, $userRoles) !== []
            && !$viewerMutationDenied,
        'policy' => $viewerMutationDenied ? 'viewer_read_only' : $group,
        'role_count' => count($userRoles),
    ];
}

function keycloak_admin_client(): KeycloakAdminClient
{
    return new KeycloakAdminClient(
        (string) keycloak_env('KEYCLOAK_BASE_URL', ''),
        (string) keycloak_env('KEYCLOAK_REALM', ''),
        (string) keycloak_env('KEYCLOAK_ADMIN_CLIENT_ID', ''),
        (string) keycloak_env('KEYCLOAK_ADMIN_CLIENT_SECRET', ''),
        (string) keycloak_env('KEYCLOAK_EXTERNAL_REALM_ROLE', 'eksternal'),
        (string) keycloak_env('KEYCLOAK_EXTERNAL_ACCESS_CLIENT_ID', 'super-sso-bbt'),
        (string) keycloak_env('KEYCLOAK_EXTERNAL_ACCESS_CLIENT_ROLE', 'access'),
        keycloak_ca_bundle_path()
    );
}

function keycloak_enforce_authorization(
    ApiSecurityStore $store,
    array $identity,
    string $path,
    bool $isRead,
    string $clientIp
): void {
    $mode = strtolower((string) keycloak_env('BIOMETRIC_AUTHORIZATION_MODE', 'observe'));
    if (!in_array($mode, ['observe', 'required'], true)) {
        return;
    }

    $decision = keycloak_authorization_decision($identity, $path, $isRead);
    if (!empty($decision['allowed'])) {
        return;
    }

    $actor = (string) ($identity['sub'] ?? '');
    $store->recordEvent(
        $mode === 'required' ? 'authorization_denied' : 'authorization_would_deny',
        $mode === 'required' ? 'warning' : 'info',
        $actor !== '' ? $actor : null,
        $clientIp,
        [
            'policy' => $decision['policy'],
            'role_count' => $decision['role_count'],
        ]
    );

    if ($mode === 'required') {
        keycloak_json_error(403, 'forbidden', 'Anda tidak memiliki izin untuk menjalankan tindakan ini.');
    }
}

function keycloak_resource_request_input(): array
{
    $input = [];
    foreach ([is_array($_GET) ? $_GET : [], is_array($_POST) ? $_POST : []] as $source) {
        $input = array_replace($input, $source);
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $decoded = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;
        if (is_array($decoded)) {
            $input = array_replace($input, $decoded);
        }
    }

    return $input;
}

function keycloak_resource_values(array $input, array $keys): array
{
    $values = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = $input[$key];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_scalar($item)) {
                $values[] = $item;
                if (count($values) >= 500) {
                    return $values;
                }
            }
        }
    }

    return $values;
}

function keycloak_resource_reference(string $path): array
{
    $input = keycloak_resource_request_input();
    $resource = [
        'nik' => keycloak_resource_values($input, ['nik', 'old_nik', 'nik_list', 'person_id']),
        'sk_number' => keycloak_resource_values($input, ['sk_number', 'sk_numbers']),
        'site_desc' => keycloak_resource_values($input, ['site_desc']),
        'plan_id' => keycloak_resource_values($input, ['plan_id']),
        'parcel_id' => keycloak_resource_values($input, ['parcel_id']),
        'potensi_id' => keycloak_resource_values($input, ['potensi_id']),
    ];

    if (preg_match('#/api/activity-plan/#i', $path) === 1) {
        $resource['plan_id'] = array_merge(
            $resource['plan_id'],
            keycloak_resource_values($input, ['id'])
        );
    }
    if (preg_match('#/api/person/parcel/#i', $path) === 1) {
        $resource['parcel_id'] = array_merge(
            $resource['parcel_id'],
            keycloak_resource_values($input, ['id'])
        );
    }
    if (preg_match('#/api/potensi/#i', $path) === 1) {
        $resource['potensi_id'] = array_merge(
            $resource['potensi_id'],
            keycloak_resource_values($input, ['id'])
        );
        if (preg_match('#/api/potensi/import\.php$#i', $path) === 1) {
            $resource['site_desc'] = array_merge(
                $resource['site_desc'],
                keycloak_resource_values($input, ['location'])
            );
        }
    }

    return $resource;
}

function keycloak_enforce_resource_authorization(
    ApiSecurityStore $store,
    array $identity,
    string $path,
    string $clientIp
): void {
    $mode = strtolower((string) keycloak_env('BIOMETRIC_SCOPE_AUTHORIZATION_MODE', 'observe'));
    if (!in_array($mode, ['observe', 'required'], true)) {
        return;
    }
    if (preg_match('#/api/(?:person|potensi|activity-plan|face|fingerprint)/#i', $path) !== 1) {
        return;
    }
    if (preg_match('#/api/person/reference/resolve\.php$#i', $path) === 1) {
        if (isset($GLOBALS['biometric_security_context'])) {
            $GLOBALS['biometric_security_context']['resource_scope'] = 'actor_bound_reference';
        }
        return;
    }

    $resourceReference = keycloak_resource_reference($path);
    if (
        preg_match('#/api/(?:face|fingerprint)/#i', $path) === 1
        && array_filter($resourceReference, static fn(array $values): bool => $values !== []) === []
    ) {
        return;
    }

    $roles = keycloak_identity_roles($identity);
    $bypassRoles = array_values(array_diff(array_unique(array_merge(
        keycloak_env_list('BIOMETRIC_SCOPE_BYPASS_ROLES', ['admin', 'super-admin']),
        ['super-admin']
    )), ['viewer', 'eksternal']));
    if (
        !keycloak_identity_is_external($identity)
        && !keycloak_identity_is_viewer($identity)
        && array_intersect($roles, $bypassRoles) !== []
    ) {
        if (isset($GLOBALS['biometric_security_context'])) {
            $GLOBALS['biometric_security_context']['resource_scope'] = 'role_bypass';
        }
        return;
    }


    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (
        keycloak_request_is_read_only($path, $method)
        && keycloak_identity_can_read_all_scopes($identity)
    ) {
        if (isset($GLOBALS['biometric_security_context'])) {
            $GLOBALS['biometric_security_context']['resource_scope'] = 'read_all_role';
        }
        return;
    }

    $actor = trim((string) ($identity['sub'] ?? ''));
    if ($actor === '') {
        return;
    }

    $authorizer = new ResourceScopeAuthorizer();
    $claimScopes = is_array($identity['resource_scopes'] ?? null)
        ? $identity['resource_scopes']
        : [];
    $decision = $authorizer->evaluate(
        $actor,
        $resourceReference,
        $claimScopes
    );
    $result = (string) ($decision['decision'] ?? 'unavailable');
    $targetCount = (int) ($decision['target_count'] ?? 0);

    if (isset($GLOBALS['biometric_security_context'])) {
        $GLOBALS['biometric_security_context']['resource_scope'] = $result;
    }

    if ($mode === 'observe') {
        $store->recordScopeObservation($actor, $path, $result, $targetCount);
        if ($result === 'target_mismatch') {
            $store->recordEvent(
                'resource_scope_target_mismatch',
                'warning',
                $actor,
                $clientIp,
                [
                    'target_count' => $targetCount,
                    'target_hashes' => $decision['target_hashes'] ?? [],
                ]
            );
        }
        return;
    }

    if ($result === 'allowed') {
        return;
    }

    $store->recordEvent(
        $result === 'unavailable' ? 'resource_scope_unavailable' : 'resource_scope_denied',
        $result === 'unavailable' ? 'critical' : 'warning',
        $actor,
        $clientIp,
        [
            'decision' => $result,
            'target_count' => $targetCount,
            'target_hashes' => $decision['target_hashes'] ?? [],
        ]
    );

    if ($result === 'unavailable') {
        keycloak_json_error(
            503,
            'authorization_unavailable',
            'Pemeriksaan cakupan akses sementara tidak tersedia.'
        );
    }

    keycloak_json_error(
        403,
        'resource_forbidden',
        'Anda tidak memiliki izin untuk mengakses lokasi atau SK tersebut.'
    );
}

function keycloak_authenticated_identity(): array
{
    $identity = $GLOBALS['biometric_authenticated_user'] ?? [];
    return is_array($identity) ? $identity : [];
}

function keycloak_enforce_biometric_search_policy(string $operation, bool $isGlobal): void
{
    $identity = keycloak_authenticated_identity();
    $actor = trim((string) ($identity['sub'] ?? ''));
    if ($actor === '') {
        return;
    }

    $store = keycloak_security_store();
    $isFingerprint = strtolower($operation) === 'fingerprint';
    $limitKey = $isFingerprint
        ? 'BIOMETRIC_FINGERPRINT_SEARCH_RATE_LIMIT_PER_MINUTE'
        : 'BIOMETRIC_FACE_SEARCH_RATE_LIMIT_PER_MINUTE';
    $defaultLimit = $isFingerprint ? '12' : '120';
    $limit = max(5, min(300, (int) keycloak_env($limitKey, $defaultLimit)));
    keycloak_enforce_rate_limit(
        $store,
        'actor_biometric_search',
        $actor,
        $limit,
        60,
        $actor
    );
    keycloak_enforce_rate_limit(
        $store,
        'ip_biometric_search',
        keycloak_client_ip(),
        min(400, $limit * 2),
        60,
        $actor
    );

    if (!$isGlobal) {
        return;
    }

    $mode = strtolower((string) keycloak_env('BIOMETRIC_GLOBAL_SEARCH_MODE', 'observe'));
    if (!in_array($mode, ['observe', 'required'], true)) {
        return;
    }

    $roles = is_array($identity['roles'] ?? null) ? $identity['roles'] : [];
    $requiredRoles = keycloak_env_list(
        'BIOMETRIC_GLOBAL_SEARCH_ROLES',
        ['biometric-search', 'admin', 'super-admin']
    );
    if (array_intersect($roles, $requiredRoles) !== []) {
        return;
    }

    $store->recordEvent(
        $mode === 'required' ? 'global_biometric_search_denied' : 'global_biometric_search_would_deny',
        $mode === 'required' ? 'warning' : 'info',
        $actor,
        keycloak_client_ip(),
        ['operation' => substr($operation, 0, 32), 'role_count' => count($roles)]
    );

    if ($mode === 'required') {
        keycloak_json_error(
            403,
            'biometric_search_forbidden',
            'Anda tidak memiliki izin untuk melakukan pencarian biometrik global.'
        );
    }
}

function keycloak_enforce_face_descriptor_export_policy(): void
{
    $identity = keycloak_authenticated_identity();
    $actor = trim((string) ($identity['sub'] ?? ''));
    if ($actor === '') {
        return;
    }

    $store = keycloak_security_store();
    keycloak_enforce_rate_limit(
        $store,
        'actor_descriptor_export',
        $actor,
        10,
        60,
        $actor
    );

    $mode = strtolower((string) keycloak_env('BIOMETRIC_DESCRIPTOR_EXPORT_MODE', 'observe'));
    if (!in_array($mode, ['observe', 'required'], true)) {
        return;
    }

    $roles = is_array($identity['roles'] ?? null) ? $identity['roles'] : [];
    $requiredRoles = keycloak_env_list(
        'BIOMETRIC_DESCRIPTOR_EXPORT_ROLES',
        ['admin', 'super-admin']
    );
    if (array_intersect($roles, $requiredRoles) !== []) {
        return;
    }

    $store->recordEvent(
        $mode === 'required' ? 'face_descriptor_export_denied' : 'face_descriptor_export_would_deny',
        $mode === 'required' ? 'warning' : 'info',
        $actor,
        keycloak_client_ip(),
        ['role_count' => count($roles)]
    );

    if ($mode === 'required') {
        keycloak_json_error(
            403,
            'descriptor_export_forbidden',
            'Ekspor descriptor wajah tidak diizinkan untuk akun ini.'
        );
    }
}

function keycloak_require_auth(): array
{
    $store = keycloak_security_store();
    $clientIp = keycloak_client_ip();
    keycloak_enforce_rate_limit($store, 'authentication_ingress', $clientIp, 240, 60);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, HEAD, POST, PUT, DELETE, OPTIONS');
        keycloak_json_error(405, 'method_not_allowed', 'Metode permintaan tidak diizinkan.');
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH'])
        ? max(0, (int) $_SERVER['CONTENT_LENGTH'])
        : 0;
    if ($contentLength > 32 * 1024 * 1024) {
        $store->recordEvent(
            'request_body_too_large',
            'warning',
            null,
            $clientIp,
            ['content_length' => $contentLength]
        );
        keycloak_json_error(413, 'payload_too_large', 'Ukuran permintaan melebihi batas yang diizinkan.');
    }

    $token = keycloak_bearer_token();
    if ($token === null) {
        $store->recordEvent('authentication_missing', 'warning', null, $clientIp);
        keycloak_json_error(401, 'unauthorized', 'Autentikasi diperlukan untuk mengakses layanan ini.');
    }

    $now = time();
    $payload = keycloak_decode_token_payload($token);
    $expiresAt = isset($payload['exp']) ? (int) $payload['exp'] : 0;
    $notBefore = isset($payload['nbf']) ? (int) $payload['nbf'] : 0;
    $tokenHash = hash('sha256', $token);
    if (($expiresAt > 0 && $expiresAt <= $now) || ($notBefore > $now + 60)) {
        $store->recordEvent('authentication_expired', 'info', null, $clientIp);
        keycloak_json_error(401, 'unauthorized', 'Sesi autentikasi sudah tidak berlaku.');
    }

    try {
        $claimIdentity = keycloak_validate_token_claims($payload, $now);
    } catch (\UnexpectedValueException $exception) {
        $store->recordEvent(
            'authentication_claim_rejected',
            'warning',
            null,
            $clientIp,
            ['reason' => substr($exception->getMessage(), 0, 64)]
        );
        keycloak_json_error(401, 'unauthorized', 'Token autentikasi tidak valid untuk aplikasi ini.');
    }

    $identity = $store->findCachedIdentity($tokenHash, $now);
    if ($identity === null) {
        $identity = keycloak_validate_remotely($token);
        if ($identity === []) {
            $store->recordEvent('authentication_rejected', 'warning', null, $clientIp);
            keycloak_json_error(401, 'unauthorized', 'Token autentikasi tidak valid.');
        }
        if (!hash_equals((string) $claimIdentity['sub'], (string) ($identity['sub'] ?? ''))) {
            $store->recordEvent('authentication_subject_mismatch', 'warning', null, $clientIp);
            keycloak_json_error(401, 'unauthorized', 'Token autentikasi tidak valid.');
        }
        $identity = array_merge($identity, $claimIdentity);
        $effectiveExpiry = $expiresAt;
        $store->cacheIdentity(
            $tokenHash,
            $identity,
            $effectiveExpiry,
            min($effectiveExpiry, $now + 300)
        );
    }

    $identity = array_merge($identity, $claimIdentity);

    $actor = (string) ($identity['sub'] ?? '');
    if ($actor === '') {
        $store->recordEvent('authentication_rejected', 'warning', null, $clientIp);
        keycloak_json_error(401, 'unauthorized', 'Token autentikasi tidak valid.');
    }

    $path = function_exists('biometricSecurityRequestPath') ? biometricSecurityRequestPath() : '/';
    $isUpload = preg_match('/\/(upload|import|enroll|regis)(?:_|\.|\/)/i', $path) === 1;
    $isRead = keycloak_request_is_read_only($path, $method);
    $scope = $isUpload ? 'actor_upload' : ($isRead ? 'actor_read' : 'actor_write');
    $limit = $isUpload ? 60 : ($isRead ? 900 : 300);
    keycloak_enforce_rate_limit($store, $scope, $actor, $limit, 60, $actor);
    keycloak_enforce_authorization($store, $identity, $path, $isRead, $clientIp);
    keycloak_enforce_resource_authorization($store, $identity, $path, $clientIp);

    if (!$isRead) {
        $store->recordEvent(
            'authenticated_mutation',
            'info',
            $actor,
            $clientIp,
            ['category' => $scope]
        );
    }

    $GLOBALS['biometric_authenticated_user'] = $identity;
    if (isset($GLOBALS['biometric_security_context'])) {
        $GLOBALS['biometric_security_context']['authentication'] = 'validated';
        $GLOBALS['biometric_security_context']['actor_hash'] = substr(hash('sha256', $actor), 0, 16);
    }
    try {
        if (random_int(1, 100) === 1) {
            $store->pruneExpiredData();
        }
    } catch (Throwable $exception) {
        // Cleanup is best effort and never blocks a valid field request.
    }
    return $identity;
}

function keycloak_apply_auth_policy(): ?array
{
    $mode = strtolower((string) keycloak_env('BIOMETRIC_API_AUTH_MODE', 'required'));
    if ($mode === 'observe') {
        if (isset($GLOBALS['biometric_security_context'])) {
            $GLOBALS['biometric_security_context']['authentication'] =
                keycloak_bearer_token() !== null ? 'observed_bearer' : 'observed_missing';
        }
        return null;
    }

    return keycloak_require_auth();
}

function unauthorized(): void
{
    keycloak_json_error(401, 'unauthorized', 'Autentikasi diperlukan.');
}
