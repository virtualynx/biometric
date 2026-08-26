<?php

declare(strict_types=1);

namespace biometric\src\core\security;

require_once dirname(__DIR__) . '/Database.php';

use biometric\src\core\Database;
use mysqli;
use RuntimeException;

final class SubjectReferenceStore
{
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    private mysqli $db;

    public function __construct(?mysqli $connection = null)
    {
        $this->db = $connection ?? (new Database())->getConnection();
    }

    public function issue(string $actor, string $nik, int $ttlSeconds = 1800): array
    {
        $actor = trim($actor);
        $nik = trim($nik);
        if ($actor === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1) {
            throw new \InvalidArgumentException('Invalid subject reference input');
        }

        $subject = $this->db->prepare(
            'SELECT 1 FROM person WHERE nik = ? AND deleted_at IS NULL LIMIT 1'
        );
        $subject->bind_param('s', $nik);
        $subject->execute();
        $exists = $subject->get_result()->num_rows === 1;
        $subject->close();
        if (!$exists) {
            throw new \OutOfBoundsException('Subject not found');
        }

        $ttlSeconds = max(300, min(3600, $ttlSeconds));
        $expiresAt = time() + $ttlSeconds;
        $actorHash = hash('sha256', $actor);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $referenceHash = hash('sha256', $token);

            try {
                $statement = $this->db->prepare(
                    'INSERT INTO api_subject_reference
                        (reference_hash, actor_hash, nik, expires_at)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->bind_param('sssi', $referenceHash, $actorHash, $nik, $expiresAt);
                $statement->execute();
                $statement->close();

                $this->pruneExpiredReferences();

                return [
                    'reference' => $token,
                    'expires_at' => gmdate(DATE_ATOM, $expiresAt),
                ];
            } catch (\mysqli_sql_exception $exception) {
                if ((int) $exception->getCode() !== 1062) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to issue a unique subject reference');
    }

    public function resolve(string $actor, string $token): ?string
    {
        $actor = trim($actor);
        $token = trim($token);
        if ($actor === '' || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $referenceHash = hash('sha256', $token);
        $actorHash = hash('sha256', $actor);
        $now = time();
        $statement = $this->db->prepare(
            'SELECT reference_hash, nik
             FROM api_subject_reference
             WHERE reference_hash = ?
               AND actor_hash = ?
               AND expires_at > ?
             LIMIT 1'
        );
        $statement->bind_param('ssi', $referenceHash, $actorHash, $now);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        if (!$row || !is_string($row['nik'] ?? null)) {
            return null;
        }

        $touch = $this->db->prepare(
            'UPDATE api_subject_reference
             SET last_used_at = CURRENT_TIMESTAMP(6)
             WHERE reference_hash = ? AND actor_hash = ?'
        );
        $touch->bind_param('ss', $referenceHash, $actorHash);
        $touch->execute();
        $touch->close();

        return trim($row['nik']);
    }

    private function pruneExpiredReferences(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }

        $cutoff = time();
        $statement = $this->db->prepare(
            'DELETE FROM api_subject_reference WHERE expires_at <= ? LIMIT 1000'
        );
        $statement->bind_param('i', $cutoff);
        $statement->execute();
        $statement->close();
    }
}
