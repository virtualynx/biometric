<?php

/**
 * Lightweight API security telemetry.
 *
 * This intentionally records request metadata only. Query strings, request
 * bodies, authorization credentials, response bodies, and uploaded files are
 * never included in the log entry.
 */

function biometricSecurityRequestHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';

    return is_string($value) ? trim($value) : '';
}

function biometricSecurityCreateRequestId(): string
{
    $providedRequestId = biometricSecurityRequestHeader('X-Request-ID');
    if (
        $providedRequestId !== '' &&
        preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/D', $providedRequestId) === 1
    ) {
        return $providedRequestId;
    }

    try {
        return bin2hex(random_bytes(16));
    } catch (\Throwable $exception) {
        return hash('sha256', uniqid('', true) . mt_rand());
    }
}

function biometricSecurityRequestPath(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }

    $path = preg_replace('/[\x00-\x1F\x7F]/', '', $path);

    return substr((string) $path, 0, 512);
}

function biometricPublicExceptionMessage(
    Throwable $exception,
    string $fallback = 'Permintaan tidak dapat diproses.'
): string {
    if (
        $exception instanceof InvalidArgumentException ||
        $exception instanceof LengthException ||
        $exception instanceof DomainException
    ) {
        return $exception->getMessage();
    }

    $code = (int) $exception->getCode();
    if ($code >= 400 && $code <= 499) {
        return $exception->getMessage();
    }

    return $fallback;
}

function biometricPublicExceptionStatus(Throwable $exception): int
{
    if ($exception instanceof LengthException) {
        return 413;
    }
    if ($exception instanceof InvalidArgumentException || $exception instanceof DomainException) {
        return 400;
    }

    $code = (int) $exception->getCode();
    return $code >= 400 && $code <= 499 ? $code : 500;
}

function biometricSecurityAuthenticationState(): string
{
    $authorization = biometricSecurityRequestHeader('Authorization');
    if ($authorization === '') {
        $authorization = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
            ? trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
            : '';
    }

    if ($authorization === '') {
        return 'missing';
    }

    return stripos($authorization, 'Bearer ') === 0
        ? 'bearer_present'
        : 'unsupported_scheme';
}

function biometricSecurityRecordCorsDecision(
    string $requestOrigin,
    string $allowedOrigin,
    bool $usesWildcard
): void {
    if (!isset($GLOBALS['biometric_security_context'])) {
        return;
    }

    $originHost = $requestOrigin !== ''
        ? parse_url($requestOrigin, PHP_URL_HOST)
        : null;

    $GLOBALS['biometric_security_context']['cors'] = [
        'origin_host' => is_string($originHost) ? substr($originHost, 0, 255) : null,
        'allowed' => $allowedOrigin !== '',
        'wildcard' => $usesWildcard,
    ];
}

function biometricSecurityBootstrap(): void
{
    if (!empty($GLOBALS['biometric_security_context'])) {
        return;
    }

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');

    set_exception_handler(static function (Throwable $exception): void {
        $context = $GLOBALS['biometric_security_context'] ?? [];
        error_log(sprintf(
            '[biometric-security] unhandled exception request_id=%s type=%s message=%s',
            (string) ($context['request_id'] ?? 'unknown'),
            get_class($exception),
            $exception->getMessage()
        ));

        if (!headers_sent()) {
            http_response_code(biometricPublicExceptionStatus($exception));
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode([
            'status' => 'error',
            'message' => biometricPublicExceptionMessage($exception, 'Terjadi kesalahan pada server.'),
            'request_id' => $context['request_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    });

    $requestId = biometricSecurityCreateRequestId();
    $remoteAddress = isset($_SERVER['REMOTE_ADDR'])
        ? trim((string) $_SERVER['REMOTE_ADDR'])
        : '';

    $GLOBALS['biometric_security_context'] = [
        'started_at' => microtime(true),
        'request_id' => $requestId,
        'client_ip_hash' => $remoteAddress !== ''
            ? substr(hash('sha256', $remoteAddress), 0, 16)
            : null,
        'authentication' => biometricSecurityAuthenticationState(),
        'cors' => [
            'origin_host' => null,
            'allowed' => false,
            'wildcard' => false,
        ],
    ];

    header_remove('X-Powered-By');
    header('X-Request-ID: ' . $requestId);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');

    register_shutdown_function(static function (): void {
        $context = $GLOBALS['biometric_security_context'] ?? [];
        $startedAt = isset($context['started_at'])
            ? (float) $context['started_at']
            : microtime(true);
        $responseStatus = http_response_code();
        if (!is_int($responseStatus) || $responseStatus < 100) {
            $responseStatus = 200;
        }

        $lastError = error_get_last();
        $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        $fatalErrorType = null;
        if (
            is_array($lastError) &&
            isset($lastError['type']) &&
            in_array((int) $lastError['type'], $fatalErrorTypes, true)
        ) {
            $fatalErrorType = (int) $lastError['type'];
        }

        $contentLength = isset($_SERVER['CONTENT_LENGTH'])
            ? max(0, (int) $_SERVER['CONTENT_LENGTH'])
            : null;

        $entry = [
            'event' => 'api_request_completed',
            'timestamp' => gmdate('c'),
            'request_id' => $context['request_id'] ?? null,
            'method' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'), 0, 16),
            'path' => biometricSecurityRequestPath(),
            'status' => $responseStatus,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'content_length' => $contentLength,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'client_ip_hash' => $context['client_ip_hash'] ?? null,
            'authentication' => $context['authentication'] ?? 'unknown',
            'actor_hash' => $context['actor_hash'] ?? null,
            'resource_scope' => $context['resource_scope'] ?? 'not_evaluated',
            'cors' => $context['cors'] ?? null,
            'fatal_error_type' => $fatalErrorType,
        ];

        $encodedEntry = json_encode(
            $entry,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (is_string($encodedEntry)) {
            error_log('[biometric-security] ' . $encodedEntry);
        }
    });
}
