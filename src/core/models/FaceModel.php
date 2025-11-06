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
