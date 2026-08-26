<?php

declare(strict_types=1);

namespace biometric\src\core\security;

use RuntimeException;

final class KeycloakAdminException extends RuntimeException
{
    private int $httpStatus;
    private string $providerReason;
    private string $operation;

    public function __construct(
        string $message,
        int $httpStatus = 500,
        string $providerReason = '',
        string $operation = ''
    ) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->providerReason = $providerReason;
        $this->operation = $operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function providerReason(): string
    {
        return $this->providerReason;
    }

    public function operation(): string
    {
        return $this->operation;
    }
}

final class KeycloakAdminClient
{
    private string $baseUrl;
    private string $realm;
    private string $clientId;
    private string $clientSecret;
    private string $externalRole;
    private string $externalAccessClientId;
    private string $externalAccessClientRole;
    private ?string $caBundlePath;
    private ?string $accessToken = null;

    public function __construct(
        string $baseUrl,
        string $realm,
        string $clientId,
        string $clientSecret,
        string $externalRole = 'eksternal',
        string $externalAccessClientId = 'super-sso-bbt',
        string $externalAccessClientRole = 'access',
        ?string $caBundlePath = null
    ) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->realm = trim($realm);
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->externalRole = strtolower(trim($externalRole));
        $this->externalAccessClientId = trim($externalAccessClientId);
        $this->externalAccessClientRole = strtolower(trim($externalAccessClientRole));
        $this->caBundlePath = $caBundlePath;

        $parts = parse_url($this->baseUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if (
            $this->baseUrl === ''
            || $this->realm === ''
            || $this->clientId === ''
            || $this->clientSecret === ''
            || $this->externalRole === ''
            || !is_array($parts)
            || ($scheme !== 'https' && !$isLocal)
        ) {
            throw new KeycloakAdminException(
                'Layanan pengelolaan pengguna belum dikonfigurasi.',
                503
            );
        }
    }

    public function listExternalUsers(string $search = '', int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $response = $this->request(
            'GET',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/roles/' . rawurlencode($this->externalRole)
                . '/users',
            null,
            ['first' => 0, 'max' => $limit, 'briefRepresentation' => 'true']
        );
        $users = is_array($response['data']) ? $response['data'] : [];
        $needle = function_exists('mb_strtolower')
            ? mb_strtolower(trim($search), 'UTF-8')
            : strtolower(trim($search));

        return array_values(array_filter(array_map(
            fn(array $user): array => $this->sanitizeUser($user),
            array_filter($users, 'is_array')
        ), static function (array $user) use ($needle): bool {
            if ($needle === '') {
                return true;
            }
            $haystack = implode(' ', [
                $user['username'],
                $user['email'],
                $user['first_name'],
                $user['last_name'],
            ]);
            $haystack = function_exists('mb_strtolower')
                ? mb_strtolower($haystack, 'UTF-8')
                : strtolower($haystack);
            return strpos($haystack, $needle) !== false;
        }));
    }

    public function createExternalUser(array $input): array
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        if (preg_match('/^[a-z0-9._-]{3,80}$/D', $username) !== 1) {
            throw new KeycloakAdminException('Username tidak valid.', 422);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new KeycloakAdminException('Alamat email tidak valid.', 422);
        }
        if ($firstName === '') {
            throw new KeycloakAdminException('Nama depan wajib diisi.', 422);
        }
        if (
            strlen($firstName) > 100
            || strlen($lastName) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $firstName . $lastName) === 1
        ) {
            throw new KeycloakAdminException('Nama pengguna tidak valid.', 422);
        }

        $initialPassword = $this->generateInitialPassword();
        $payload = [
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'enabled' => true,
            'emailVerified' => false,
        ];
        $response = $this->request(
            'POST',
            '/admin/realms/' . rawurlencode($this->realm) . '/users',
            $payload
        );
        $userId = $this->userIdFromLocation((string) ($response['location'] ?? ''));
        if ($userId === '') {
            $matches = $this->request(
                'GET',
                '/admin/realms/' . rawurlencode($this->realm) . '/users',
                null,
                ['username' => $username, 'exact' => 'true', 'max' => 1]
            );
            $userId = trim((string) ($matches['data'][0]['id'] ?? ''));
        }
        if ($userId === '') {
            throw new KeycloakAdminException('Akun dibuat tetapi identitasnya belum dapat dibaca.', 502);
        }

        try {
            $this->request(
                'PUT',
                '/admin/realms/' . rawurlencode($this->realm)
                    . '/users/' . rawurlencode($userId)
                    . '/reset-password',
                [
                    'type' => 'password',
                    'value' => $initialPassword,
                    'temporary' => false,
                ]
            );
            $role = $this->request(
                'GET',
                '/admin/realms/' . rawurlencode($this->realm)
                    . '/roles/' . rawurlencode($this->externalRole)
            );
            $roleData = is_array($role['data']) ? $role['data'] : [];
            $roleId = trim((string) ($roleData['id'] ?? ''));
            $roleName = trim((string) ($roleData['name'] ?? ''));
            if ($roleId === '' || $roleName === '') {
                throw new KeycloakAdminException(
                    'Representasi role eksternal Keycloak tidak valid.',
                    502
                );
            }
            $this->request(
                'POST',
                '/admin/realms/' . rawurlencode($this->realm)
                    . '/users/' . rawurlencode($userId)
                    . '/role-mappings/realm',
                [[
                    'id' => $roleId,
                    'name' => $roleName,
                ]]
            );
            $this->assignApplicationAccessRole($userId);
        } catch (\Throwable $exception) {
            $this->deleteUser($userId);
            throw $exception;
        }

        return [
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'enabled' => true,
            ],
            'initial_password' => $initialPassword,
        ];
    }

    public function resetInitialPassword(string $userId): array
    {
        $user = $this->getExternalUser($userId);
        $this->assignApplicationAccessRole($userId);
        $initialPassword = $this->generateInitialPassword();
        $this->request(
            'PUT',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/users/' . rawurlencode($userId)
                . '/reset-password',
            [
                'type' => 'password',
                'value' => $initialPassword,
                'temporary' => false,
            ]
        );

        return [
            'user' => $user,
            'initial_password' => $initialPassword,
        ];
    }

    public function ensureExternalApplicationAccess(string $userId): array
    {
        $user = $this->getExternalUser($userId);
        $this->assignApplicationAccessRole($userId);

        return $user;
    }

    public function getExternalUser(string $userId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $userId) !== 1) {
            throw new KeycloakAdminException('Identitas pengguna tidak valid.', 422);
        }
        $userResponse = $this->request(
            'GET',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/users/' . rawurlencode($userId)
        );
        $roleResponse = $this->request(
            'GET',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/users/' . rawurlencode($userId)
                . '/role-mappings/realm'
        );
        $hasExternalRole = false;
        foreach (is_array($roleResponse['data']) ? $roleResponse['data'] : [] as $role) {
            if (
                is_array($role)
                && strtolower(trim((string) ($role['name'] ?? ''))) === $this->externalRole
            ) {
                $hasExternalRole = true;
                break;
            }
        }
        if (!$hasExternalRole) {
            throw new KeycloakAdminException('Pengguna eksternal tidak ditemukan.', 404);
        }

        return $this->sanitizeUser(
            is_array($userResponse['data']) ? $userResponse['data'] : []
        );
    }

    public function deleteUser(string $userId): void
    {
        if (preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $userId) !== 1) {
            return;
        }
        try {
            $this->request(
                'DELETE',
                '/admin/realms/' . rawurlencode($this->realm)
                    . '/users/' . rawurlencode($userId)
            );
        } catch (\Throwable $exception) {
            error_log('[biometric-security] unable to roll back newly created Keycloak user');
        }
    }

    private function sanitizeUser(array $user): array
    {
        return [
            'id' => substr(trim((string) ($user['id'] ?? '')), 0, 80),
            'username' => substr(trim((string) ($user['username'] ?? '')), 0, 100),
            'email' => substr(trim((string) ($user['email'] ?? '')), 0, 255),
            'first_name' => substr(trim((string) ($user['firstName'] ?? '')), 0, 100),
            'last_name' => substr(trim((string) ($user['lastName'] ?? '')), 0, 100),
            'enabled' => !empty($user['enabled']),
        ];
    }

    private function assignApplicationAccessRole(string $userId): void
    {
        if ($this->externalAccessClientId === '' || $this->externalAccessClientRole === '') {
            throw new KeycloakAdminException(
                'Client akses login pengguna eksternal belum dikonfigurasi.',
                503
            );
        }

        $clients = $this->request(
            'GET',
            '/admin/realms/' . rawurlencode($this->realm) . '/clients',
            null,
            [
                'clientId' => $this->externalAccessClientId,
                'max' => 2,
            ]
        );
        $matches = array_values(array_filter(
            is_array($clients['data']) ? $clients['data'] : [],
            fn($client): bool => is_array($client)
                && trim((string) ($client['clientId'] ?? '')) === $this->externalAccessClientId
        ));
        $clientUuid = trim((string) ($matches[0]['id'] ?? ''));
        if (count($matches) !== 1 || $clientUuid === '') {
            throw new KeycloakAdminException(
                'Client akses aplikasi belum dapat dibaca oleh service account Keycloak.',
                503
            );
        }

        $role = $this->request(
            'GET',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/clients/' . rawurlencode($clientUuid)
                . '/roles/' . rawurlencode($this->externalAccessClientRole)
        );
        $roleData = is_array($role['data']) ? $role['data'] : [];
        $roleId = trim((string) ($roleData['id'] ?? ''));
        $roleName = trim((string) ($roleData['name'] ?? ''));
        if ($roleId === '' || $roleName === '') {
            throw new KeycloakAdminException(
                'Client role akses aplikasi belum tersedia di Keycloak.',
                503
            );
        }

        $this->request(
            'POST',
            '/admin/realms/' . rawurlencode($this->realm)
                . '/users/' . rawurlencode($userId)
                . '/role-mappings/clients/' . rawurlencode($clientUuid),
            [[
                'id' => $roleId,
                'name' => $roleName,
            ]]
        );
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = []
    ): array {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $location = '';
        $curl = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->adminToken(),
            'Accept: application/json',
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));
                }
                return strlen($header);
            },
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }
        if ($this->caBundlePath !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundlePath;
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($curl);
        if ($body === false || $curlError !== 0) {
            error_log('[biometric-security] Keycloak Admin API transport failure');
            throw new KeycloakAdminException('Keycloak sementara tidak dapat dihubungi.', 503);
        }
        $decoded = $body !== '' ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300) {
            $keycloakReason = $this->keycloakErrorReason($decoded);
            $publicStatus = $status === 400 || $status === 422
                ? 422
                : ($status === 409 ? 409 : ($status === 403 ? 503 : 502));
            $message = $this->publicErrorMessage($status, $path, $keycloakReason);
            error_log(sprintf(
                '[biometric-security] Keycloak Admin API rejected request (status=%d, operation=%s, reason_hash=%s)',
                $status,
                $this->operationForPath($method, $path),
                substr(hash('sha256', $keycloakReason), 0, 12)
            ));
            throw new KeycloakAdminException(
                $message,
                $publicStatus,
                $keycloakReason,
                $this->operationForPath($method, $path)
            );
        }

        return [
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : [],
            'location' => $location,
        ];
    }

    private function keycloakErrorReason($decoded): string
    {
        if (!is_array($decoded)) {
            return '';
        }
        foreach (['errorMessage', 'error_description', 'error'] as $key) {
            $value = trim((string) ($decoded[$key] ?? ''));
            if ($value !== '') {
                return substr($value, 0, 500);
            }
        }
        return '';
    }

    private function publicErrorMessage(int $status, string $path, string $reason): string
    {
        if ($status === 409) {
            return 'Username atau email sudah digunakan.';
        }
        if ($status === 403) {
            return 'Service account Keycloak belum memiliki izin yang diperlukan.';
        }
        if ($status === 404 && strpos($path, '/roles/') !== false) {
            return 'Role eksternal belum tersedia di Keycloak.';
        }

        $normalizedReason = function_exists('mb_strtolower')
            ? mb_strtolower($reason, 'UTF-8')
            : strtolower($reason);
        if (strpos($normalizedReason, 'password') !== false) {
            return 'Password sementara tidak memenuhi kebijakan Keycloak.';
        }
        if (strpos($normalizedReason, 'email') !== false) {
            return 'Email tidak dapat digunakan atau sudah terdaftar.';
        }
        if (
            strpos($normalizedReason, 'username') !== false
            || strpos($normalizedReason, 'user name') !== false
        ) {
            return 'Username tidak dapat digunakan.';
        }
        if (
            strpos($normalizedReason, 'first name') !== false
            || strpos($normalizedReason, 'last name') !== false
            || strpos($normalizedReason, 'user profile') !== false
        ) {
            return 'Data pengguna belum memenuhi konfigurasi User Profile Keycloak.';
        }
        if ($status === 400 || $status === 422) {
            $operation = $this->operationForPath('', $path);
            if ($operation === 'reset_password') {
                return 'Keycloak menolak pemasangan password sementara untuk pengguna baru.';
            }
            if ($operation === 'create_user') {
                return 'Keycloak menolak pembuatan profil pengguna baru.';
            }
            return 'Data pengguna ditolak oleh kebijakan Keycloak.';
        }

        return 'Keycloak menolak operasi pengelolaan pengguna.';
    }

    private function operationForPath(string $method, string $path): string
    {
        if (substr($path, -15) === '/reset-password') {
            return 'reset_password';
        }
        if (strpos($path, '/role-mappings/') !== false) {
            return 'assign_role';
        }
        if (strpos($path, '/roles/') !== false) {
            return 'read_role';
        }
        if (substr($path, -6) === '/users' && strtoupper($method) === 'POST') {
            return 'create_user';
        }
        if (substr($path, -6) === '/users') {
            return 'create_user';
        }
        return 'user_admin';
    }

    private function adminToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $url = $this->baseUrl
            . '/realms/' . rawurlencode($this->realm)
            . '/protocol/openid-connect/token';
        $curl = curl_init($url);
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($this->caBundlePath !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundlePath;
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($body === false || $status !== 200) {
            error_log(sprintf('[biometric-security] Keycloak admin token failed (status=%d)', $status));
            throw new KeycloakAdminException('Otorisasi pengelolaan pengguna belum tersedia.', 503);
        }
        $decoded = json_decode($body, true);
        $token = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';
        if ($token === '') {
            throw new KeycloakAdminException('Token pengelolaan pengguna tidak valid.', 503);
        }
        $this->accessToken = $token;
        return $token;
    }

    private function userIdFromLocation(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);
        $id = is_string($path) ? basename($path) : '';
        return preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $id) === 1 ? $id : '';
    }

    private function generateInitialPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = 'A7!';
        $max = strlen($alphabet) - 1;
        for ($index = 0; $index < 17; $index++) {
            $password .= $alphabet[random_int(0, $max)];
        }
        return $password . 'a';
    }
}
