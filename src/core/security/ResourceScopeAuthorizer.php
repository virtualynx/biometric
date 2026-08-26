<?php

declare(strict_types=1);

namespace biometric\src\core\security;

require_once dirname(__DIR__) . '/Database.php';

use biometric\src\core\Database;
use mysqli;
use Throwable;

final class ResourceScopeAuthorizer
{
    public const SCOPE_SITE = 'site';
    public const SCOPE_SK = 'sk';

    private mysqli $db;

    public function __construct(?mysqli $connection = null)
    {
        $this->db = $connection ?? (new Database())->getConnection();
    }

    public function evaluate(string $actor, array $resource, array $claimScopes = []): array
    {
        try {
            $targets = $this->resolveTargets($resource);
            if ($targets['decision'] !== 'resolved') {
                return $targets;
            }

            $grants = $this->effectiveScopes($actor, $claimScopes);

            if ($grants[self::SCOPE_SITE] === [] && $grants[self::SCOPE_SK] === []) {
                return $this->decision('unassigned', $targets);
            }

            foreach ($targets['sk_numbers'] as $skNumber) {
                if (in_array($skNumber, $grants[self::SCOPE_SK], true)) {
                    continue;
                }

                $site = $targets['sites_by_sk'][$skNumber] ?? null;
                if ($site !== null && in_array($site, $grants[self::SCOPE_SITE], true)) {
                    continue;
                }

                return $this->decision('denied', $targets);
            }

            if ($targets['sk_numbers'] === []) {
                foreach ($targets['sites'] as $site) {
                    if (!in_array($site, $grants[self::SCOPE_SITE], true)) {
                        return $this->decision('denied', $targets);
                    }
                }
            }

            return $this->decision('allowed', $targets);
        } catch (Throwable $exception) {
            error_log('[biometric-security] resource scope evaluation failed: ' . $exception->getMessage());

            return [
                'decision' => 'unavailable',
                'target_count' => 0,
                'target_hashes' => [],
            ];
        }
    }

    public function effectiveScopes(string $actor, array $claimScopes = []): array
    {
        $storedScopes = $this->loadActorScopes($actor);

        return [
            self::SCOPE_SITE => $this->normalizeValues(array_merge(
                $storedScopes[self::SCOPE_SITE],
                is_array($claimScopes[self::SCOPE_SITE] ?? null)
                    ? $claimScopes[self::SCOPE_SITE]
                    : []
            )),
            self::SCOPE_SK => $this->normalizeValues(array_merge(
                $storedScopes[self::SCOPE_SK],
                is_array($claimScopes[self::SCOPE_SK] ?? null)
                    ? $claimScopes[self::SCOPE_SK]
                    : []
            )),
        ];
    }

    public static function normalizeScopeValue(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        $value = function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);

        return substr($value, 0, 255);
    }

    public function grant(
        string $actor,
        string $scopeType,
        string $scopeValue,
        ?string $note = null,
        ?string $grantedBy = null
    ): void {
        $scopeType = $this->validateScopeType($scopeType);
        $scopeValue = $this->normalizeValue($scopeValue);
        if ($scopeValue === '') {
            throw new \InvalidArgumentException('Nilai scope tidak boleh kosong.');
        }

        $actorHash = hash('sha256', $actor);
        $grantorHash = $grantedBy !== null && trim($grantedBy) !== ''
            ? hash('sha256', trim($grantedBy))
            : null;
        $safeNote = $note !== null ? substr(trim($note), 0, 500) : null;
        $statement = $this->db->prepare(
            'INSERT INTO api_actor_scope
                (actor_hash, scope_type, scope_value, is_active, note, granted_by_hash)
             VALUES (?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_active = 1,
                note = VALUES(note),
                granted_by_hash = VALUES(granted_by_hash),
                updated_at = CURRENT_TIMESTAMP(6)'
        );
        $statement->bind_param('sssss', $actorHash, $scopeType, $scopeValue, $safeNote, $grantorHash);
        $statement->execute();
        $statement->close();
    }

    public function revoke(string $actor, string $scopeType, string $scopeValue): bool
    {
        $scopeType = $this->validateScopeType($scopeType);
        $scopeValue = $this->normalizeValue($scopeValue);
        $actorHash = hash('sha256', $actor);
        $statement = $this->db->prepare(
            'UPDATE api_actor_scope
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP(6)
             WHERE actor_hash = ? AND scope_type = ? AND scope_value = ?'
        );
        $statement->bind_param('sss', $actorHash, $scopeType, $scopeValue);
        $statement->execute();
        $changed = $statement->affected_rows > 0;
        $statement->close();

        return $changed;
    }

    public function listForActor(string $actor): array
    {
        $actorHash = hash('sha256', $actor);
        $statement = $this->db->prepare(
            'SELECT scope_type, scope_value, is_active, note, created_at, updated_at
             FROM api_actor_scope
             WHERE actor_hash = ?
             ORDER BY scope_type, scope_value'
        );
        $statement->bind_param('s', $actorHash);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $rows;
    }

    public function listActiveForActors(array $actors): array
    {
        $actorMap = [];
        foreach (array_slice($actors, 0, 200) as $actor) {
            $actor = is_scalar($actor) ? trim((string) $actor) : '';
            if ($actor !== '') {
                $actorMap[hash('sha256', $actor)] = $actor;
            }
        }
        $result = [];
        foreach ($actorMap as $actor) {
            $result[$actor] = [];
        }
        if ($actorMap === []) {
            return $result;
        }

        $hashes = array_keys($actorMap);
        $placeholders = implode(',', array_fill(0, count($hashes), '?'));
        $statement = $this->db->prepare(
            "SELECT actor_hash, scope_type, scope_value
             FROM api_actor_scope
             WHERE actor_hash IN ({$placeholders})
               AND is_active = 1
               AND (valid_from IS NULL OR valid_from <= CURRENT_TIMESTAMP)
               AND (valid_until IS NULL OR valid_until > CURRENT_TIMESTAMP)
             ORDER BY scope_type, scope_value"
        );
        $types = str_repeat('s', count($hashes));
        $statement->bind_param($types, ...$hashes);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        foreach ($rows as $row) {
            $actor = $actorMap[(string) ($row['actor_hash'] ?? '')] ?? null;
            if ($actor === null) {
                continue;
            }
            $result[$actor][] = [
                'type' => (string) ($row['scope_type'] ?? ''),
                'value' => (string) ($row['scope_value'] ?? ''),
            ];
        }

        return $result;
    }

    public function replaceActiveScopes(
        string $actor,
        array $assignments,
        ?string $note = null,
        ?string $grantedBy = null
    ): array {
        $actor = trim($actor);
        if (preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $actor) !== 1) {
            throw new \InvalidArgumentException('Identitas pengguna tidak valid.');
        }
        if (count($assignments) > 100) {
            throw new \InvalidArgumentException('Maksimal 100 cakupan akses per pengguna.');
        }

        $availableRows = $this->query(
            'SELECT site_desc, sk_number FROM master_sk',
            []
        );
        $available = [self::SCOPE_SITE => [], self::SCOPE_SK => []];
        foreach ($availableRows as $row) {
            $site = $this->normalizeValue((string) ($row['site_desc'] ?? ''));
            $skNumber = $this->normalizeValue((string) ($row['sk_number'] ?? ''));
            if ($site !== '') {
                $available[self::SCOPE_SITE][$site] = true;
            }
            if ($skNumber !== '') {
                $available[self::SCOPE_SK][$skNumber] = true;
            }
        }

        $normalized = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $type = $this->validateScopeType((string) ($assignment['type'] ?? ''));
            $value = $this->normalizeValue((string) ($assignment['value'] ?? ''));
            if ($value === '' || !isset($available[$type][$value])) {
                throw new \InvalidArgumentException('Lokasi atau SK yang dipilih tidak valid.');
            }
            $normalized[$type . ':' . $value] = ['type' => $type, 'value' => $value];
        }
        $normalized = array_values($normalized);

        $actorHash = hash('sha256', $actor);
        $grantorHash = $grantedBy !== null && trim($grantedBy) !== ''
            ? hash('sha256', trim($grantedBy))
            : null;
        $safeNote = $note !== null ? substr(trim($note), 0, 500) : null;

        $this->db->begin_transaction();
        try {
            $deactivate = $this->db->prepare(
                'UPDATE api_actor_scope
                 SET is_active = 0, updated_at = CURRENT_TIMESTAMP(6)
                 WHERE actor_hash = ? AND is_active = 1'
            );
            $deactivate->bind_param('s', $actorHash);
            $deactivate->execute();
            $deactivate->close();

            if ($normalized !== []) {
                $upsert = $this->db->prepare(
                    'INSERT INTO api_actor_scope
                        (actor_hash, scope_type, scope_value, is_active, note, granted_by_hash)
                     VALUES (?, ?, ?, 1, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        is_active = 1,
                        valid_from = NULL,
                        valid_until = NULL,
                        note = VALUES(note),
                        granted_by_hash = VALUES(granted_by_hash),
                        updated_at = CURRENT_TIMESTAMP(6)'
                );
                foreach ($normalized as $scope) {
                    $scopeType = $scope['type'];
                    $scopeValue = $scope['value'];
                    $upsert->bind_param(
                        'sssss',
                        $actorHash,
                        $scopeType,
                        $scopeValue,
                        $safeNote,
                        $grantorHash
                    );
                    $upsert->execute();
                }
                $upsert->close();
            }

            $this->db->commit();
            return $normalized;
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function resolveTargets(array $resource): array
    {
        $directSkNumbers = $this->normalizeValues($this->arrayValue($resource['sk_number'] ?? []));
        $directSites = $this->normalizeValues($this->arrayValue($resource['site_desc'] ?? []));
        $niks = $this->normalizeIdentifiers($this->arrayValue($resource['nik'] ?? []));
        $resolvedSkNumbers = [];

        if ($niks !== []) {
            $placeholders = implode(',', array_fill(0, count($niks), '?'));
            $rows = $this->query(
                "SELECT DISTINCT sk_number FROM person
                 WHERE deleted_at IS NULL AND nik IN ({$placeholders})
                 UNION
                 SELECT DISTINCT sk_number FROM potensi
                 WHERE deleted_at IS NULL AND nik IN ({$placeholders})",
                array_merge($niks, $niks)
            );
            $resolvedSkNumbers = $this->normalizeValues(array_column($rows, 'sk_number'));
        }

        $planIds = $this->positiveIntegers($this->arrayValue($resource['plan_id'] ?? []));
        if ($planIds !== []) {
            $rows = $this->queryByIds(
                'SELECT site_desc, sk_number FROM activity_plan WHERE id IN (%s) AND deleted_at IS NULL',
                $planIds
            );
            $directSites = $this->normalizeValues(array_merge($directSites, array_column($rows, 'site_desc')));
            $resolvedSkNumbers = $this->normalizeValues(array_merge(
                $resolvedSkNumbers,
                array_column($rows, 'sk_number')
            ));
        }

        $parcelIds = $this->positiveIntegers($this->arrayValue($resource['parcel_id'] ?? []));
        if ($parcelIds !== []) {
            $rows = $this->queryByIds(
                'SELECT DISTINCT p.sk_number
                 FROM subject_land_parcel slp
                 JOIN person p ON BINARY p.nik = BINARY slp.nik AND p.deleted_at IS NULL
                 WHERE slp.id IN (%s) AND slp.deleted_at IS NULL',
                $parcelIds
            );
            $resolvedSkNumbers = $this->normalizeValues(array_merge(
                $resolvedSkNumbers,
                array_column($rows, 'sk_number')
            ));
        }

        $potensiIds = $this->positiveIntegers($this->arrayValue($resource['potensi_id'] ?? []));
        if ($potensiIds !== []) {
            $rows = $this->queryByIds(
                'SELECT sk_number FROM potensi WHERE id IN (%s) AND deleted_at IS NULL',
                $potensiIds
            );
            $resolvedSkNumbers = $this->normalizeValues(array_merge(
                $resolvedSkNumbers,
                array_column($rows, 'sk_number')
            ));
        }

        if (
            $directSkNumbers !== [] &&
            $resolvedSkNumbers !== [] &&
            array_diff($resolvedSkNumbers, $directSkNumbers) !== []
        ) {
            return $this->decision('target_mismatch', [
                'sk_numbers' => array_values(array_unique(array_merge($directSkNumbers, $resolvedSkNumbers))),
                'sites' => $directSites,
                'sites_by_sk' => [],
            ]);
        }

        $skNumbers = $this->normalizeValues(array_merge($directSkNumbers, $resolvedSkNumbers));
        $sitesBySk = [];
        if ($skNumbers !== []) {
            $placeholders = implode(',', array_fill(0, count($skNumbers), '?'));
            $rows = $this->query(
                "SELECT sk_number, site_desc FROM master_sk WHERE sk_number IN ({$placeholders})",
                $skNumbers
            );
            foreach ($rows as $row) {
                $sk = $this->normalizeValue((string) ($row['sk_number'] ?? ''));
                $site = $this->normalizeValue((string) ($row['site_desc'] ?? ''));
                if ($sk !== '' && $site !== '') {
                    $sitesBySk[$sk] = $site;
                }
            }
        }

        $mappedSites = array_values(array_unique(array_values($sitesBySk)));
        if (
            $directSites !== [] &&
            $mappedSites !== [] &&
            array_diff($mappedSites, $directSites) !== []
        ) {
            return $this->decision('target_mismatch', [
                'sk_numbers' => $skNumbers,
                'sites' => array_values(array_unique(array_merge($directSites, $mappedSites))),
                'sites_by_sk' => $sitesBySk,
            ]);
        }

        $sites = $this->normalizeValues(array_merge($directSites, $mappedSites));
        if ($skNumbers === [] && $sites === []) {
            return $this->decision('unresolved', [
                'sk_numbers' => [],
                'sites' => [],
                'sites_by_sk' => [],
            ]);
        }

        return [
            'decision' => 'resolved',
            'sk_numbers' => $skNumbers,
            'sites' => $sites,
            'sites_by_sk' => $sitesBySk,
        ];
    }

    private function loadActorScopes(string $actor): array
    {
        $actorHash = hash('sha256', $actor);
        $statement = $this->db->prepare(
            'SELECT scope_type, scope_value
             FROM api_actor_scope
             WHERE actor_hash = ?
               AND is_active = 1
               AND (valid_from IS NULL OR valid_from <= CURRENT_TIMESTAMP)
               AND (valid_until IS NULL OR valid_until > CURRENT_TIMESTAMP)'
        );
        $statement->bind_param('s', $actorHash);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        $scopes = [self::SCOPE_SITE => [], self::SCOPE_SK => []];
        foreach ($rows as $row) {
            $type = (string) ($row['scope_type'] ?? '');
            if (!array_key_exists($type, $scopes)) {
                continue;
            }
            $value = $this->normalizeValue((string) ($row['scope_value'] ?? ''));
            if ($value !== '') {
                $scopes[$type][] = $value;
            }
        }

        return $scopes;
    }

    private function queryByIds(string $sqlTemplate, array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->query(sprintf($sqlTemplate, $placeholders), $ids, 'i');
    }

    private function query(string $sql, array $params, string $type = 's'): array
    {
        $statement = $this->db->prepare($sql);
        if ($params !== []) {
            $types = str_repeat($type, count($params));
            $statement->bind_param($types, ...$params);
        }
        $statement->execute();
        $result = $statement->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $statement->close();

        return $rows;
    }

    private function decision(string $decision, array $targets): array
    {
        $scopeKeys = [];
        foreach ($targets['sk_numbers'] ?? [] as $skNumber) {
            $scopeKeys[] = self::SCOPE_SK . ':' . $skNumber;
        }
        foreach ($targets['sites'] ?? [] as $site) {
            $scopeKeys[] = self::SCOPE_SITE . ':' . $site;
        }

        return [
            'decision' => $decision,
            'target_count' => count($scopeKeys),
            'target_hashes' => array_map(
                static fn(string $scope): string => substr(hash('sha256', $scope), 0, 16),
                array_values(array_unique($scopeKeys))
            ),
        ];
    }

    private function normalizeValue(string $value): string
    {
        return self::normalizeScopeValue($value);
    }

    private function normalizeValues(array $values): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map(
            fn($value): string => is_scalar($value) ? $this->normalizeValue((string) $value) : '',
            $values
        )))), 0, 500);
    }

    private function normalizeIdentifiers(array $values): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map(
            static function ($value): string {
                if (!is_scalar($value)) {
                    return '';
                }
                $value = trim((string) $value);
                return preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value) === 1 ? $value : '';
            },
            $values
        )))), 0, 500);
    }

    private function positiveIntegers(array $values): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map(
            static fn($value): int => is_numeric($value) ? max(0, (int) $value) : 0,
            $values
        )))), 0, 500);
    }

    private function arrayValue($value): array
    {
        return is_array($value) ? $value : [$value];
    }

    private function validateScopeType(string $scopeType): string
    {
        $scopeType = strtolower(trim($scopeType));
        if (!in_array($scopeType, [self::SCOPE_SITE, self::SCOPE_SK], true)) {
            throw new \InvalidArgumentException('Tipe scope harus site atau sk.');
        }

        return $scopeType;
    }
}
