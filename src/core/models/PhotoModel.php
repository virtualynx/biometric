<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/FileUploadModel.php");

class PhotoModel extends Database
{
    const PHOTO_TYPE_BIOMETRIC = 'biometric';
    const PHOTO_TYPE_DOCUMENTATION = 'documentation';

    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
    }

    private function fileExists(string $relativePath): bool
    {
        $absolutePath = dirname(__FILE__) . '/../../../' . ltrim($relativePath, '/');

        return is_file($absolutePath);
    }

    public function get(string $nik, ?string $filename = null): array
    {
        $where_filename = '';

        if (!empty($filename)) {
            $where_filename = " and filename = '$filename'";
        }

        $photos = $this->query("
            select * 
            from photo 
            where 
                nik = '$nik'
                $where_filename
        ");

        return $photos;
    }

    public function add(
        string $nik,
        string $filename,
        string $savepath,
        string $photoType = self::PHOTO_TYPE_BIOMETRIC,
        ?string $description = null,
        ?string $extension = null,
        ?string $latlong = null
    ) {
        $existingBiometric = null;

        if ($photoType == self::PHOTO_TYPE_BIOMETRIC) {
            $rs = $this->query("
            SELECT * 
            FROM photo 
            WHERE nik = '$nik'
              AND type = '$photoType'
        ");

            if (!empty($rs)) {
                $existingBiometric = $rs[0];
            }
        }

        $res = false;
        if ($photoType == self::PHOTO_TYPE_BIOMETRIC && !empty($existingBiometric)) {
            $res = $this->execute("
            UPDATE photo
            SET
                filename = '$filename',
                photo_path = '$savepath',
                description = '$description'
                " . (!empty($extension) ? ", extension = '$extension'" : "") . "
                " . (!empty($latlong) ? ", latlong = '$latlong'" : "") . "
            WHERE
                nik = '$nik'
                AND type = '$photoType'
        ");
        } else {
            $columns = ['nik', 'filename', 'photo_path', 'type', 'description'];
            $values = ["'$nik'", "'$filename'", "'$savepath'", "'$photoType'", "'$description'"];

            if (!empty($extension)) {
                $columns[] = 'extension';
                $values[] = "'$extension'";
            }

            if (!empty($latlong)) {
                $columns[] = 'latlong';
                $values[] = "'$latlong'";
            }

            $sql = sprintf(
                "INSERT INTO photo (%s) VALUES (%s)",
                implode(',', $columns),
                implode(',', $values)
            );

            $res = $this->execute($sql);
        }

        return $res;
    }


    public function delete(string $nik, string $filename)
    {
        $res = $this->execute("delete from photo where nik = '$nik' and filename = '$filename'");

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

    public function getBiometricPresenceByNikList(array $nikList): array
    {
        if (empty($nikList)) return [];

        if (count($nikList) > 500) {
            $nikList = array_slice($nikList, 0, 500);
        }

        $placeholders = implode(',', array_fill(0, count($nikList), '?'));

        $rows = $this->query("
            SELECT
                nik,
                type,
                photo_path
            FROM photo
            WHERE nik IN ($placeholders)
        ", $nikList);

        $presenceMap = [];

        foreach ($rows as $row) {
            if (
                empty($row->nik) ||
                $row->type !== 'biometric' ||
                empty($row->photo_path) ||
                !$this->fileExists($row->photo_path)
            ) {
                continue;
            }

            $presenceMap[$row->nik] = [
                'nik' => $row->nik,
                'has_biometric_photo' => 1,
            ];
        }

        return json_decode(json_encode(array_values($presenceMap)));
    }
}
