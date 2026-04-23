<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__) . "/../Database.php");

class DocumentModel extends Database
{
    const DOCUMENT_TYPE_DOCUMENT = 'document';

    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
    }

    public function get(string $nik): array
    {
        $docs = $this->query("
            select 
                doc.nik,
                doc.filename,
                doc.extension,
                doc.`type` as `type_id`,
                mdt.name as `type`,
                doc.description,
                doc.file_path,
                doc.file_blob,
                doc.created_at,
                doc.updated_at 
            from 
                document doc
                left join master_doc_type mdt on doc.`type` = mdt.id
            where 
                nik = '$nik'
        ");
        // $docs = $this->query("
        //     select 
        //         doc.*
        //     from 
        //         document doc
        //     where 
        //         nik = '$nik'
        // ");

        return $docs;
    }

    public function add(
        string $nik,
        string $filename,
        string $savepath,
        string $documentType = self::DOCUMENT_TYPE_DOCUMENT,
        ?string $description = null,
        ?string $extension = null
    ) {
        $res = $this->execute("
            insert into document(
                nik,
                filename,
                type,
                description,
                file_path
                " . (!empty($extension) ? ",extension" : "") . "
            )
            values(
                '$nik',
                '$filename',
                '$documentType',
                '$description',
                '$savepath'
                " . (!empty($extension) ? ",'$extension'" : "") . "
            )
        ");

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

    public function deleteByType(string $nik, string $type): bool
    {
        $sql = "DELETE FROM document WHERE nik = ? AND type = ?";
        return $this->execQuery($sql, [$nik, $type]);
    }

    public function getByNikList(array $nikList): array
    {
        if (empty($nikList)) return [];

        if (count($nikList) > 50) {
            $nikList = array_slice($nikList, 0, 50);
        }

        $placeholders = implode(',', array_fill(0, count($nikList), '?'));
        $types = str_repeat('s', count($nikList));

        $sql = "SELECT nik, type, file_path FROM document WHERE nik IN ($placeholders)";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            error_log("MYSQL PREPARE FAILED: " . $this->db->error);
            return [];
        }

        if (!$stmt->bind_param($types, ...$nikList)) {
            error_log("MYSQL BIND PARAM FAILED: " . $stmt->error);
            return [];
        }

        if (!$stmt->execute()) {
            error_log("MYSQL EXECUTE FAILED: " . $stmt->error);
            return [];
        }

        $result = $stmt->get_result();

        if (!$result) {
            error_log("MYSQL GET_RESULT FAILED: " . $stmt->error);
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function getPresenceByNikList(array $nikList): array
    {
        if (empty($nikList)) return [];

        if (count($nikList) > 500) {
            $nikList = array_slice($nikList, 0, 500);
        }

        $placeholders = implode(',', array_fill(0, count($nikList), '?'));

        $sql = "
            SELECT
                nik,
                MAX(CASE WHEN type IN ('KTP', 'SIM') THEN 1 ELSE 0 END) AS has_ktp,
                MAX(CASE WHEN type = 'KK' THEN 1 ELSE 0 END) AS has_kk
            FROM document
            WHERE nik IN ($placeholders)
            GROUP BY nik
        ";

        return $this->query($sql, $nikList);
    }
}
