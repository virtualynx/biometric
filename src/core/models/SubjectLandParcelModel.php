<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__) . "/../Database.php");

class SubjectLandParcelModel extends Database
{
    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS subject_land_parcel (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nik VARCHAR(32) NOT NULL,
                sk_number VARCHAR(128) NULL,
                parcel_label VARCHAR(255) NOT NULL,
                parcel_number VARCHAR(255) NULL,
                village VARCHAR(255) NULL,
                area_declared DECIMAL(14,2) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_subject_land_parcel_nik (nik),
                INDEX idx_subject_land_parcel_nik_sk (nik, sk_number),
                INDEX idx_subject_land_parcel_deleted (deleted_at)
            )
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS subject_land_parcel_file (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parcel_id INT UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                extension VARCHAR(32) NULL,
                file_path VARCHAR(255) NOT NULL,
                file_size BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_subject_land_parcel_file_parcel (parcel_id),
                INDEX idx_subject_land_parcel_file_deleted (deleted_at)
            )
        ");
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }

        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return json_decode(json_encode($rows));
    }

    private function fetchOne(string $sql, array $params = []): ?stdClass
    {
        $rows = $this->fetchAll($sql, $params);
        if (empty($rows)) {
            return null;
        }

        return $rows[0];
    }

    public function listByNik(string $nik): array
    {
        return $this->fetchAll("
            SELECT
                slp.*,
                slpf.id AS shp_file_id,
                slpf.filename AS shp_filename,
                slpf.extension AS shp_extension,
                slpf.file_path AS shp_file_path,
                slpf.file_size AS shp_file_size,
                slpf.created_at AS shp_uploaded_at,
                CASE WHEN slpf.id IS NULL THEN 0 ELSE 1 END AS has_shp
            FROM subject_land_parcel slp
            LEFT JOIN (
                SELECT f1.*
                FROM subject_land_parcel_file f1
                INNER JOIN (
                    SELECT parcel_id, MAX(id) AS latest_id
                    FROM subject_land_parcel_file
                    WHERE deleted_at IS NULL
                    GROUP BY parcel_id
                ) latest ON latest.latest_id = f1.id
            ) slpf ON slpf.parcel_id = slp.id
            WHERE slp.nik = ? AND slp.deleted_at IS NULL
            ORDER BY slp.updated_at DESC, slp.id DESC
        ", [$nik]);
    }

    public function getById(int $id, ?string $nik = null): ?stdClass
    {
        $params = [(string) $id];
        $whereNik = "";
        if (!empty($nik)) {
            $whereNik = " AND slp.nik = ?";
            $params[] = $nik;
        }

        return $this->fetchOne("
            SELECT
                slp.*,
                slpf.id AS shp_file_id,
                slpf.filename AS shp_filename,
                slpf.extension AS shp_extension,
                slpf.file_path AS shp_file_path,
                slpf.file_size AS shp_file_size,
                slpf.created_at AS shp_uploaded_at,
                CASE WHEN slpf.id IS NULL THEN 0 ELSE 1 END AS has_shp
            FROM subject_land_parcel slp
            LEFT JOIN (
                SELECT f1.*
                FROM subject_land_parcel_file f1
                INNER JOIN (
                    SELECT parcel_id, MAX(id) AS latest_id
                    FROM subject_land_parcel_file
                    WHERE deleted_at IS NULL
                    GROUP BY parcel_id
                ) latest ON latest.latest_id = f1.id
            ) slpf ON slpf.parcel_id = slp.id
            WHERE slp.id = ? AND slp.deleted_at IS NULL $whereNik
            LIMIT 1
        ", $params);
    }

    public function save(stdClass $payload): stdClass
    {
        $areaDeclared = isset($payload->area_declared) && $payload->area_declared !== ''
            ? (string) $payload->area_declared
            : null;

        if (!empty($payload->id)) {
            $stmt = $this->db->prepare("
                UPDATE subject_land_parcel
                SET
                    sk_number = ?,
                    parcel_label = ?,
                    parcel_number = ?,
                    village = ?,
                    area_declared = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND nik = ? AND deleted_at IS NULL
            ");
            if (!$stmt) {
                throw new \Exception("Prepare failed: " . $this->db->error);
            }

            $id = (string) $payload->id;
            $stmt->bind_param(
                "ssssssss",
                $payload->sk_number,
                $payload->parcel_label,
                $payload->parcel_number,
                $payload->village,
                $areaDeclared,
                $payload->notes,
                $id,
                $payload->nik
            );
            $stmt->execute();
            $stmt->close();

            $parcelId = (int) $payload->id;
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO subject_land_parcel (
                    nik,
                    sk_number,
                    parcel_label,
                    parcel_number,
                    village,
                    area_declared,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                throw new \Exception("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param(
                "sssssss",
                $payload->nik,
                $payload->sk_number,
                $payload->parcel_label,
                $payload->parcel_number,
                $payload->village,
                $areaDeclared,
                $payload->notes
            );
            $stmt->execute();
            $parcelId = (int) $stmt->insert_id;
            $stmt->close();
        }

        $parcel = $this->getById($parcelId, $payload->nik);
        if (empty($parcel)) {
            throw new \Exception("Bidang tanah gagal disimpan.");
        }

        return $parcel;
    }

    public function softDelete(int $id, string $nik): bool
    {
        $stmt = $this->db->prepare("
            UPDATE subject_land_parcel
            SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND nik = ? AND deleted_at IS NULL
        ");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }

        $idString = (string) $id;
        $stmt->bind_param("ss", $idString, $nik);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        $stmtFiles = $this->db->prepare("
            UPDATE subject_land_parcel_file
            SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE parcel_id = ? AND deleted_at IS NULL
        ");
        if ($stmtFiles) {
            $stmtFiles->bind_param("s", $idString);
            $stmtFiles->execute();
            $stmtFiles->close();
        }

        return $affectedRows > 0;
    }

    public function replaceShpFile(
        int $parcelId,
        string $filename,
        string $filePath,
        ?string $extension = null,
        ?int $fileSize = null
    ): stdClass {
        $parcelIdString = (string) $parcelId;

        $stmtDelete = $this->db->prepare("
            UPDATE subject_land_parcel_file
            SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE parcel_id = ? AND deleted_at IS NULL
        ");
        if ($stmtDelete) {
            $stmtDelete->bind_param("s", $parcelIdString);
            $stmtDelete->execute();
            $stmtDelete->close();
        }

        $stmtInsert = $this->db->prepare("
            INSERT INTO subject_land_parcel_file (
                parcel_id,
                filename,
                extension,
                file_path,
                file_size
            ) VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmtInsert) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }

        $fileSizeValue = $fileSize !== null ? (string) $fileSize : null;
        $stmtInsert->bind_param(
            "sssss",
            $parcelIdString,
            $filename,
            $extension,
            $filePath,
            $fileSizeValue
        );
        $stmtInsert->execute();
        $stmtInsert->close();

        $file = $this->getActiveShpFile($parcelId);
        if (empty($file)) {
            throw new \Exception("File SHP gagal disimpan.");
        }

        return $file;
    }

    public function getActiveShpFile(int $parcelId): ?stdClass
    {
        return $this->fetchOne("
            SELECT *
            FROM subject_land_parcel_file
            WHERE parcel_id = ? AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ", [(string) $parcelId]);
    }

    public function getShpSummaryByNikList(array $nikList): array
    {
        $nikList = array_values(array_unique(array_filter(array_map(
            fn($nik) => trim((string) $nik),
            $nikList
        ))));

        if (empty($nikList)) {
            return [];
        }

        $rows = [];
        foreach (array_chunk($nikList, 500) as $nikChunk) {
            $placeholders = implode(',', array_fill(0, count($nikChunk), '?'));
            $chunkRows = $this->fetchAll("
                SELECT
                    slp.nik,
                    COUNT(*) AS total_parcels,
                    SUM(CASE WHEN slpf.parcel_id IS NOT NULL THEN 1 ELSE 0 END) AS total_parcels_with_shp,
                    COALESCE(SUM(slp.area_declared), 0) AS total_area_declared
                FROM subject_land_parcel slp
                LEFT JOIN (
                    SELECT DISTINCT parcel_id
                    FROM subject_land_parcel_file
                    WHERE deleted_at IS NULL
                ) slpf ON slpf.parcel_id = slp.id
                WHERE slp.deleted_at IS NULL
                  AND slp.nik IN ($placeholders)
                GROUP BY slp.nik
            ", $nikChunk);
            $rows = array_merge($rows, $chunkRows);
        }

        return $rows;
    }
}
