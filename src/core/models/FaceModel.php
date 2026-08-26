<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\biometrics\FaceDescriptorMatcher;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/../biometrics/FaceDescriptorMatcher.php");

class FaceModel extends Database
{
    const ID_TYPE_NIP = 'NIP';
    const ID_TYPE_NIK = 'NIK';
    const ID_TYPE_PHONE = 'PHONE';
    const ID_TYPE_EMAIL = 'EMAIL';
    private const MATCH_CACHE_REVISION_KEY = 'biometric.face.descriptor.revision';
    private const MATCH_CACHE_TTL_SECONDS = 15;

    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
    }

    public function get(string $person_id): array
    {
        $faces = $this->query('SELECT * FROM face WHERE person_id = ?', [$person_id]);

        return !empty($faces) ? $faces[0] : null;
    }

    public function list(array $person_ids): array
    {
        $sql = 'SELECT * FROM face';
        $params = [];
        if (!empty($person_ids)) {
            $params = array_values($person_ids);
            $sql .= ' WHERE person_id IN ('
                . implode(',', array_fill(0, count($params), '?')) . ')';
        }
        $faces = $this->query($sql, $params);

        $result = [];
        if (!empty($faces)) {
            foreach ($faces as $row) {
                $result[] = [
                    'person_id' => $row->person_id,
                    'id_type' => $row->id_type,
                    'encoding' => json_decode($row->encoding)
                ];
            }
        }

        return $result;
    }

    public function listActiveDescriptors(?array $person_ids = null, ?string $sk_number = null): array
    {
        $person_ids = array_values(array_unique(array_filter(
            $person_ids ?? [],
            fn($person_id) => !empty($person_id)
        )));

        $sql = "
            SELECT
                f.person_id,
                f.id_type,
                f.encoding,
                p.sk_number,
                p.name
            FROM face f
            INNER JOIN person p
                ON p.nik = f.person_id
               AND p.deleted_at IS NULL
            WHERE f.id_type = ?
        ";

        $params = [self::ID_TYPE_NIK];

        if (!empty($sk_number)) {
            $sql .= " AND p.sk_number = ?";
            $params[] = $sk_number;
        }

        if (!empty($person_ids)) {
            $placeholders = implode(',', array_fill(0, count($person_ids), '?'));
            $sql .= " AND f.person_id IN ($placeholders)";
            $params = array_merge($params, $person_ids);
        }

        $sql .= " ORDER BY COALESCE(f.updated_at, f.created_at) DESC";

        $faces = $this->query($sql, $params);

        $result = [];
        if (!empty($faces)) {
            foreach ($faces as $row) {
                $result[] = [
                    'person_id' => $row->person_id,
                    'id_type' => $row->id_type,
                    'sk_number' => $row->sk_number,
                    'name' => $row->name,
                    'encoding' => json_decode($row->encoding),
                ];
            }
        }

        return $result;
    }

    public function matchActiveDescriptor(array $probe, ?string $sk_number = null): array
    {
        $faces = $this->matchingCandidates($sk_number);

        return FaceDescriptorMatcher::match($probe, $faces);
    }

    private function matchingCandidates(?string $sk_number): array
    {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return $this->listActiveDescriptors(null, $sk_number);
        }

        $revision = apcu_fetch(self::MATCH_CACHE_REVISION_KEY, $revisionFound);
        if (!$revisionFound || !is_int($revision)) {
            $revision = 1;
            apcu_store(self::MATCH_CACHE_REVISION_KEY, $revision);
        }

        $scopeKey = $sk_number === null ? 'global' : hash('sha256', $sk_number);
        $cacheKey = 'biometric.face.descriptor.' . $revision . '.' . $scopeKey;
        $cached = apcu_fetch($cacheKey, $cacheFound);
        if ($cacheFound && is_array($cached)) {
            return $cached;
        }

        $faces = $this->listActiveDescriptors(null, $sk_number);
        apcu_store($cacheKey, $faces, self::MATCH_CACHE_TTL_SECONDS);

        return $faces;
    }

    public static function invalidateDescriptorCache(): void
    {
        if (!function_exists('apcu_inc') || !function_exists('apcu_store')) {
            return;
        }

        $incremented = apcu_inc(self::MATCH_CACHE_REVISION_KEY, 1, $success);
        if (!$success || !is_int($incremented)) {
            apcu_store(self::MATCH_CACHE_REVISION_KEY, 2);
        }
    }

    public function enroll(
        string $person_id,
        string $id_type,
        string $encoding
    ) {
        $existings = $this->query(
            'SELECT * FROM face WHERE person_id = ? AND id_type = ?',
            [$person_id, $id_type]
        );

        $res = false;
        if (empty($existings)) {
            $res = $this->execQuery(
                'INSERT INTO face (person_id, id_type, encoding) VALUES (?, ?, ?)',
                [$person_id, $id_type, $encoding]
            );
        } else {
            $existing_id = $existings[0]->face_id;
            $res = $this->execQuery(
                'UPDATE face SET encoding = ? WHERE face_id = ?',
                [$encoding, $existing_id]
            );
        }

        if ($res) {
            self::invalidateDescriptorCache();
        }

        return $res;
    }

    public function delete(string $face_id)
    {
        $res = $this->execQuery('DELETE FROM face WHERE face_id = ?', [$face_id]);

        if ($res) {
            self::invalidateDescriptorCache();
        }

        return $res;
    }

    public function query($sql, $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        if ($params && count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return json_decode(json_encode($rows));
    }

    public function execQuery($sql, $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        if ($params && count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
