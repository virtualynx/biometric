<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__) . "/../Database.php");

class FaceModel extends Database
{
    const ID_TYPE_NIP = 'NIP';
    const ID_TYPE_NIK = 'NIK';
    const ID_TYPE_PHONE = 'PHONE';
    const ID_TYPE_EMAIL = 'EMAIL';

    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
    }

    public function get(string $person_id): array
    {
        $faces = $this->query("
            select * 
            from face 
            where 
                person_id = '$person_id'
        ");

        return !empty($faces) ? $faces[0] : null;
    }

    public function list(array $person_ids): array
    {
        $where_clause = '';

        if (!empty($person_ids)) {
            $where_in = implode("', '", $person_ids);
            $where_clause = "where person_id in ('$where_in')";
        }

        $faces = $this->query("
            select * 
            from face 
            $where_clause
        ");

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

    public function enroll(
        string $person_id,
        string $id_type,
        string $encoding
    ) {
        $existings = $this->query("
            select * 
            from face 
            where
                person_id = '$person_id'
                and id_type = '$id_type'
        ");

        $res = false;
        if (empty($existings)) {
            $res = $this->execute("
                insert into face(
                    person_id,
                    id_type,
                    encoding
                )
                values(
                    '$person_id',
                    '$id_type',
                    '$encoding'
                )
            ");
        } else {
            $existing_id = $existings[0]->face_id;
            $res = $this->execute("update face set encoding = '$encoding' where face_id = $existing_id");
        }

        return $res;
    }

    public function delete(string $face_id)
    {
        $res = $this->execute("delete from face where face_id = '$face_id'");

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
