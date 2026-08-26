<?php

declare(strict_types=1);

namespace biometric\src\core\security;

require_once dirname(__DIR__) . '/Database.php';

use biometric\src\core\Database;
use mysqli;
use Throwable;

final class ApiSecurityStore
{
    private mysqli $db;

    public function __construct(?mysqli $connection = null)
    {
        $this->db = $connection ?? (new Database())->getConnection();
    }

    public function findCachedIdentity(string $tokenHash, int $now): ?array
    {
        try {
            $statement = $this->db->prepare(
                'SELECT identity_json
                 FROM api_auth_cache
                 WHERE token_hash = ?
                   AND validated_until >= ?
                   AND token_expires_at > ?
                 LIMIT 1'
            );
            $statement->bind_param('sii', $tokenHash, $now, $now);
            $statement->execute();
            $row = $statement->get_result()->fetch_assoc();

            if (!$row || !is_string($row['identity_json'] ?? null)) {
                return null;
            }

            $identity = json_decode($row['identity_json'], true);
            if (!is_array($identity)) {
                return null;
            }

            return $identity;
        } catch (Throwable $exception) {
            error_log('[biometric-security] auth cache read failed: ' . $exception->getMessage());
            return null;
        }
    }

    public function cacheIdentity(
        string $tokenHash,
        array $identity,
        int $tokenExpiresAt,
        int $validatedUntil
    ): void {
        try {
            $identityJson = json_encode(
                $identity,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if (!is_string($identityJson)) {
                return;
            }

            $statement = $this->db->prepare(
                'INSERT INTO api_auth_cache
                    (token_hash, identity_json, token_expires_at, validated_until)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    identity_json = VALUES(identity_json),
                    token_expires_at = VALUES(token_expires_at),
                    validated_until = VALUES(validated_until),
                    last_seen_at = CURRENT_TIMESTAMP(6)'
            );
            $statement->bind_param(
                'ssii',
                $tokenHash,
                $identityJson,
                $tokenExpiresAt,
                $validatedUntil
            );
            $statement->execute();
        } catch (Throwable $exception) {
            error_log('[biometric-security] auth cache write failed: ' . $exception->getMessage());
        }
    }

    public function consumeRateLimit(
        string $scope,
        string $identifier,
        int $limit,
        int $windowSeconds
    ): array {
        $now = time();
        $windowStartedAt = intdiv($now, $windowSeconds) * $windowSeconds;
        $expiresAt = $windowStartedAt + $windowSeconds + 60;
        $bucketKey = hash(
            'sha256',
            $scope . "\0" . $identifier . "\0" . (string) $windowStartedAt
        );

        try {
            $statement = $this->db->prepare(
                'INSERT INTO api_rate_limit
                    (bucket_key, scope, window_started_at, expires_at, request_count)
                 VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                    request_count = LAST_INSERT_ID(request_count + 1),
                    expires_at = VALUES(expires_at)'
            );
            $statement->bind_param(
                'ssii',
                $bucketKey,
                $scope,
                $windowStartedAt,
                $expiresAt
            );
            $statement->execute();
            $count = max(1, (int) $statement->insert_id);

            return [
                'allowed' => $count <= $limit,
                'count' => $count,
                'limit' => $limit,
                'retry_after' => max(1, $windowStartedAt + $windowSeconds - $now),
            ];
        } catch (Throwable $exception) {
            error_log('[biometric-security] rate limit failed: ' . $exception->getMessage());

            return [
                'allowed' => true,
                'count' => 0,
                'limit' => $limit,
                'retry_after' => 1,
            ];
        }
    }

    public function recordEvent(
        string $eventType,
        string $severity,
        ?string $actorIdentifier,
        ?string $clientIpIdentifier,
        array $metadata = []
    ): void {
        try {
            $context = $GLOBALS['biometric_security_context'] ?? [];
            $requestId = isset($context['request_id'])
                ? substr((string) $context['request_id'], 0, 128)
                : null;
            $method = substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'), 0, 16);
            $path = function_exists('biometricSecurityRequestPath')
                ? biometricSecurityRequestPath()
                : '/';
            $actorHash = $actorIdentifier !== null
                ? hash('sha256', $actorIdentifier)
                : null;
            $clientIpHash = $clientIpIdentifier !== null
                ? hash('sha256', $clientIpIdentifier)
                : null;
            $metadataJson = $metadata !== []
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                )
                : null;

            $statement = $this->db->prepare(
                'INSERT INTO api_security_event
                    (request_id, event_type, severity, actor_hash, client_ip_hash,
                     request_method, request_path, metadata_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'ssssssss',
                $requestId,
                $eventType,
                $severity,
                $actorHash,
                $clientIpHash,
                $method,
                $path,
                $metadataJson
            );
            $statement->execute();
        } catch (Throwable $exception) {
            error_log('[biometric-security] audit event write failed: ' . $exception->getMessage());
        }
    }

    public function recordScopeObservation(
        string $actorIdentifier,
        string $requestPath,
        string $decision,
        int $targetCount
    ): void {
        try {
            $actorHash = hash('sha256', $actorIdentifier);
            $requestPath = substr($requestPath, 0, 512);
            $decision = substr($decision, 0, 32);
            $targetCount = max(0, min(65535, $targetCount));
            $statement = $this->db->prepare(
                'INSERT INTO api_scope_observation
                    (actor_hash, request_path, decision, target_count)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    target_count = VALUES(target_count),
                    observation_count = observation_count + 1,
                    last_seen_at = CURRENT_TIMESTAMP(6)'
            );
            $statement->bind_param(
                'sssi',
                $actorHash,
                $requestPath,
                $decision,
                $targetCount
            );
            $statement->execute();
            $statement->close();
        } catch (Throwable $exception) {
            error_log('[biometric-security] scope observation write failed: ' . $exception->getMessage());
        }
    }

    public function pruneExpiredData(): void
    {
        try {
            $now = time();
            $staleValidation = $now - 3600;
            $this->db->query(
                "DELETE FROM api_auth_cache
                 WHERE token_expires_at < {$now} OR validated_until < {$staleValidation}
                 LIMIT 500"
            );
            $this->db->query(
                "DELETE FROM api_rate_limit WHERE expires_at < {$now} LIMIT 1000"
            );
            $this->db->query(
                'DELETE FROM api_security_event
                 WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL 90 DAY)
                 LIMIT 1000'
            );
        } catch (Throwable $exception) {
            error_log('[biometric-security] cleanup failed: ' . $exception->getMessage());
        }
    }
}
