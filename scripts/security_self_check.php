<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/_auth_keycloak.php';
require_once dirname(__DIR__) . '/src/core/models/FileUploadModel.php';
require_once dirname(__DIR__) . '/src/core/security/ResourceScopeAuthorizer.php';

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\Database;
use biometric\src\core\security\ResourceScopeAuthorizer;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$check(keycloak_ip_matches_cidr('10.20.30.40', '10.20.0.0/16'), 'IPv4 CIDR match failed');
$check(!keycloak_ip_matches_cidr('10.21.30.40', '10.20.0.0/16'), 'IPv4 CIDR rejection failed');
$check(keycloak_ip_matches_cidr('2001:db8::10', '2001:db8::/32'), 'IPv6 CIDR match failed');

$viewerIdentity = ['roles' => ['viewer', 'user']];
$externalIdentity = ['roles' => ['eksternal']];
$superAdminIdentity = ['roles' => ['super-admin', 'viewer']];
$check(keycloak_identity_is_viewer($viewerIdentity), 'Viewer precedence was not enforced');
$check(keycloak_identity_is_external($externalIdentity), 'External role was not recognized');
$check(!keycloak_identity_is_viewer($superAdminIdentity), 'Super-admin was reduced to viewer');
$check(keycloak_identity_can_read_all_scopes($viewerIdentity), 'Viewer read-all scope was not accepted');
$check(!keycloak_identity_can_read_all_scopes($externalIdentity), 'External role bypassed location scope');
$check(
    !keycloak_authorization_decision($viewerIdentity, '/api/person/update_mobile.php', false)['allowed'],
    'Viewer mutation was accepted'
);
$check(
    keycloak_authorization_decision($viewerIdentity, '/api/person/list.php', true)['allowed'],
    'Viewer read was rejected'
);
$check(
    keycloak_authorization_decision($externalIdentity, '/api/person/delete_subject.php', false)['allowed'],
    'Scoped external delete role was rejected before resource authorization'
);
$check(
    keycloak_authorization_decision($superAdminIdentity, '/api/person/update_mobile.php', false)['allowed'],
    'Super-admin mutation was rejected'
);
$check(
    !keycloak_authorization_decision(['roles' => ['offline_access']], '/api/person/list.php', true)['allowed'],
    'Unrecognized role was allowed to read business data'
);
$check(
    keycloak_authorization_decision(
        ['roles' => ['super-admin']],
        '/api/access-management/users.php',
        true
    )['allowed'],
    'Super-admin was denied access management'
);
$check(
    !keycloak_authorization_decision(
        ['roles' => ['admin']],
        '/api/access-management/users.php',
        true
    )['allowed'],
    'Legacy admin was allowed to manage Keycloak users'
);
$check(
    !keycloak_authorization_decision(
        ['roles' => ['eksternal']],
        '/api/access-management/scopes.php',
        false
    )['allowed'],
    'External user was allowed to manage user scopes'
);

$base = rtrim((string) keycloak_env('KEYCLOAK_BASE_URL', ''), '/');
$realm = (string) keycloak_env('KEYCLOAK_REALM', '');
$clientId = keycloak_env_list('KEYCLOAK_ALLOWED_CLIENT_IDS', ['super-sso-bbt'])[0] ?? '';
$now = time();
$validClaims = [
    'iss' => $base . '/realms/' . rawurlencode($realm),
    'sub' => 'security-self-check',
    'iat' => $now - 10,
    'exp' => $now + 300,
    'azp' => $clientId,
    'realm_access' => ['roles' => ['access']],
];

try {
    $identity = keycloak_validate_token_claims($validClaims, $now);
    $check(($identity['sub'] ?? null) === 'security-self-check', 'Valid token claims were not accepted');
    $check(in_array('access', $identity['roles'] ?? [], true), 'Token roles were not extracted');
} catch (Throwable $exception) {
    $failures[] = 'Valid token claims threw an exception';
}

foreach ([
    array_merge($validClaims, ['iss' => 'https://invalid.example/realms/other']),
    array_merge($validClaims, ['azp' => 'unexpected-client']),
    array_merge($validClaims, ['exp' => $now - 1]),
] as $invalidClaims) {
    try {
        keycloak_validate_token_claims($invalidClaims, $now);
        $failures[] = 'Invalid token claims were accepted';
    } catch (UnexpectedValueException $exception) {
        // Expected rejection.
    }
}

try {
    $scopeAuthorizer = new ResourceScopeAuthorizer();
    $allowedScope = $scopeAuthorizer->evaluate(
        'security-self-check',
        ['site_desc' => ['SELF CHECK SITE']],
        [ResourceScopeAuthorizer::SCOPE_SITE => ['SELF CHECK SITE']]
    );
    $deniedScope = $scopeAuthorizer->evaluate(
        'security-self-check',
        ['site_desc' => ['SELF CHECK SITE']],
        [ResourceScopeAuthorizer::SCOPE_SITE => ['DIFFERENT SITE']]
    );
    $unresolvedScope = $scopeAuthorizer->evaluate('security-self-check', [], []);
    $check(($allowedScope['decision'] ?? null) === 'allowed', 'Matching site scope was not accepted');
    $check(($deniedScope['decision'] ?? null) === 'denied', 'Mismatched site scope was not rejected');
    $check(($unresolvedScope['decision'] ?? null) === 'unresolved', 'Missing resource target was not rejected');

    $db = (new Database())->getConnection();
    $parcelResult = $db->query(
        "SELECT id, nik, sk_number
         FROM subject_land_parcel
         WHERE deleted_at IS NULL
           AND nik IS NOT NULL AND nik <> ''
           AND sk_number IS NOT NULL AND sk_number <> ''
         ORDER BY id DESC
         LIMIT 1"
    );
    $parcel = $parcelResult ? $parcelResult->fetch_assoc() : null;
    if (is_array($parcel)) {
        $parcelScope = trim((string) ($parcel['sk_number'] ?? ''));
        $parcelDecision = $scopeAuthorizer->evaluate(
            'security-self-check-parcel',
            [
                'parcel_id' => [(int) ($parcel['id'] ?? 0)],
                'nik' => [(string) ($parcel['nik'] ?? '')],
                'sk_number' => [$parcelScope],
            ],
            [ResourceScopeAuthorizer::SCOPE_SK => [$parcelScope]]
        );
        $check(
            ($parcelDecision['decision'] ?? null) === 'allowed',
            'Parcel scope resolution failed across database collations'
        );
    }
} catch (Throwable $exception) {
    $failures[] = 'Resource scope authorization self-check threw an exception';
}

$uploadReflection = new ReflectionClass(FileUploadModel::class);
$uploadModel = $uploadReflection->newInstanceWithoutConstructor();
$validateUpload = $uploadReflection->getMethod('validateUpload');
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    true
);

try {
    $validated = $validateUpload->invoke(
        $uploadModel,
        FileUploadModel::PURPOSE_IMAGE,
        strlen((string) $png),
        $png,
        null,
        'image/png'
    );
    $check(($validated['extension'] ?? null) === 'png', 'Valid PNG was not accepted');
} catch (Throwable $exception) {
    $failures[] = 'Valid PNG threw an exception';
}

try {
    $phpPayload = '<?php echo "unsafe";';
    $validateUpload->invoke(
        $uploadModel,
        FileUploadModel::PURPOSE_DOCUMENT,
        strlen($phpPayload),
        $phpPayload,
        null,
        'application/pdf'
    );
    $failures[] = 'Executable payload was accepted as a document';
} catch (Throwable $exception) {
    // Expected rejection.
}

$apiRoot = dirname(__DIR__) . '/api';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($apiRoot));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace(dirname(__DIR__) . '/', '', $file->getPathname());
    if (strpos($relative, 'api/_') === 0) {
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    $check(
        is_string($contents) && strpos($contents, '_api_header.php') !== false,
        $relative . ' does not load the API security middleware'
    );
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] {$failure}\n");
    }
    exit(1);
}

echo "Security self-check passed.\n";
