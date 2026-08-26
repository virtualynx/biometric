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
        $uploadRoot = realpath(dirname(__FILE__) . '/../../../uploads');
        $absolutePath = realpath(dirname(__FILE__) . '/../../../' . ltrim($relativePath, '/'));

        return is_string($uploadRoot)
            && is_string($absolutePath)
            && strpos($absolutePath, $uploadRoot . DIRECTORY_SEPARATOR) === 0
            && is_file($absolutePath);
    }

    public function get(string $nik, ?string $filename = null): array
    {
        $sql = 'SELECT * FROM photo WHERE nik = ?';
        $params = [$nik];
        if (!empty($filename)) {
            $sql .= ' AND filename = ?';
            $params[] = $filename;
        }
        $photos = $this->query($sql, $params);

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
            $rs = $this->query(
                'SELECT * FROM photo WHERE nik = ? AND type = ?',
                [$nik, $photoType]
            );

            if (!empty($rs)) {
                $existingBiometric = $rs[0];
            }
        }

        $res = false;
        if ($photoType == self::PHOTO_TYPE_BIOMETRIC && !empty($existingBiometric)) {
            $sets = ['filename = ?', 'photo_path = ?', 'description = ?'];
            $params = [$filename, $savepath, $description];
            if (!empty($extension)) {
                $sets[] = 'extension = ?';
                $params[] = $extension;
            }
            if (!empty($latlong)) {
                $sets[] = 'latlong = ?';
                $params[] = $latlong;
            }
            $params[] = $nik;
            $params[] = $photoType;
            $res = $this->execQuery(
                'UPDATE photo SET ' . implode(', ', $sets) . ' WHERE nik = ? AND type = ?',
                $params
            );
        } else {
            $columns = ['nik', 'filename', 'photo_path', 'type', 'description'];
            $params = [$nik, $filename, $savepath, $photoType, $description];

            if (!empty($extension)) {
                $columns[] = 'extension';
                $params[] = $extension;
            }

            if (!empty($latlong)) {
                $columns[] = 'latlong';
                $params[] = $latlong;
            }

            $placeholders = implode(', ', array_fill(0, count($params), '?'));
            $res = $this->execQuery(
                'INSERT INTO photo (' . implode(', ', $columns) . ") VALUES ({$placeholders})",
                $params
            );
        }

        return $res;
    }


    public function delete(string $nik, string $filename)
    {
        $res = $this->execQuery(
            'DELETE FROM photo WHERE nik = ? AND filename = ?',
            [$nik, $filename]
        );

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
