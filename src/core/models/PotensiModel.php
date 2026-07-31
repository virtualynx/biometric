<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\FaceModel;

use stdClass;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/PhotoModel.php");
require_once(dirname(__FILE__) . "/DocumentModel.php");
require_once(dirname(__FILE__) . "/FaceModel.php");


class PotensiModel extends Database
{
    private static $schemaChecked = false;
    private static $beneficiarySchemaChecked = false;

    /** @var \mysqli */
    private $db;
    private $photoModel;
    private $documentModel;
    private $faceModel;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();

        $this->photoModel = new PhotoModel();
        $this->documentModel = new DocumentModel();
        $this->faceModel = new FaceModel();
        $this->ensureActiveNikUniqueness();
        $this->ensureBeneficiaryColumns();
    }

    private function ensureActiveNikUniqueness(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $columns = $this->query("SHOW COLUMNS FROM potensi");
            $columnNames = array_map(
                fn($column) => $column->Field ?? null,
                $columns
            );

            if (!in_array('active_nik', $columnNames, true)) {
                $this->db->query("
                    ALTER TABLE potensi
                    ADD COLUMN active_nik VARCHAR(20)
                    GENERATED ALWAYS AS (
                        CASE WHEN deleted_at IS NULL THEN nik ELSE NULL END
                    ) STORED
                ");
            }

            $duplicateResult = $this->db->query("
                SELECT nik
                FROM potensi
                WHERE deleted_at IS NULL
                GROUP BY nik
                HAVING COUNT(*) > 1
                LIMIT 1
            ");

            if ($duplicateResult && $duplicateResult->num_rows > 0) {
                error_log("Unable to enforce potensi NIK uniqueness: active duplicate NIK still exists");
                return;
            }

            $indexResult = $this->db->query("
                SHOW INDEX FROM potensi
                WHERE Key_name = 'uniq_potensi_active_nik'
                  AND Non_unique = 0
            ");
            $hasActiveNikIndex = $indexResult && $indexResult->num_rows > 0;

            if (!$hasActiveNikIndex) {
                $this->db->query("
                    ALTER TABLE potensi
                    ADD UNIQUE INDEX uniq_potensi_active_nik (active_nik)
                ");
            }
        } catch (\Throwable $e) {
            error_log("Unable to enforce potensi NIK uniqueness: " . $e->getMessage());
        }
    }

    private function ensureBeneficiaryColumns(): void
    {
        if (self::$beneficiarySchemaChecked) {
            return;
        }

        self::$beneficiarySchemaChecked = true;

        try {
            $columns = $this->query("SHOW COLUMNS FROM potensi");
            $existingColumns = array_map(
                fn($column) => $column->Field ?? null,
                $columns
            );

            if (!in_array('beneficiary_nik', $existingColumns, true)) {
                $this->db->query("ALTER TABLE potensi ADD COLUMN beneficiary_nik VARCHAR(32) NULL AFTER luas_bangunan");
            }

            if (!in_array('beneficiary_familycard_no', $existingColumns, true)) {
                $this->db->query("ALTER TABLE potensi ADD COLUMN beneficiary_familycard_no VARCHAR(32) NULL AFTER beneficiary_nik");
            }

            if (!in_array('beneficiary_name', $existingColumns, true)) {
                $this->db->query("ALTER TABLE potensi ADD COLUMN beneficiary_name VARCHAR(255) NULL AFTER beneficiary_familycard_no");
            }

            if (!in_array('beneficiary_address', $existingColumns, true)) {
                $this->db->query("ALTER TABLE potensi ADD COLUMN beneficiary_address TEXT NULL AFTER beneficiary_name");
            }
        } catch (\Throwable $e) {
            error_log("Unable to ensure potensi beneficiary columns: " . $e->getMessage());
        }
    }

    public function list(?string $sk_number = null): array
    {
        if (!empty($sk_number)) {
            $stmt = $this->db->prepare("
            SELECT *
            FROM potensi
            WHERE deleted_at IS NULL
              AND status = 'POTENSI'
              AND sk_number = ?
            ORDER BY created_at DESC
        ");
            $stmt->bind_param("s", $sk_number);
        } else {
            $stmt = $this->db->prepare("
            SELECT *
            FROM potensi
            WHERE deleted_at IS NULL
              AND status = 'POTENSI'
            ORDER BY created_at DESC
        ");
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$row) {
            $row['biometric_status'] = $this->getBiometricStatus($row['nik']);
            $row['status'] = $this->getOverallStatus($row['nik']);
        }
        unset($row);

        return json_decode(json_encode($rows));
    }

    public function getById(int $id): ?stdClass
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM potensi
        WHERE id = ?
          AND status = 'POTENSI'
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        return $row ? json_decode(json_encode($row)) : null;
    }


    public function markAsApproved(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE potensi
        SET status = 'DITERIMA',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE potensi
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    public function getBiometricStatus(string $nik): stdClass
    {
        $result = [
            'photo' => 'unregistered',
            'fingerprint' => 'unregistered',
            'face' => 'unregistered'
        ];

        $photos = $this->photoModel->get($nik);
        foreach ($photos as $row) {
            if ($row->type === 'biometric') {
                $result['photo'] = 'completed';
            }
        }

        $faces = $this->faceModel->list([$nik]);
        if (count($faces) > 0) {
            $result['face'] = 'completed';
        }

        return json_decode(json_encode($result));
    }


    public function getOverallStatus(string $nik): string
    {
        $docs = $this->documentModel->get($nik);

        $hasKtp = false;
        $hasKk  = false;

        foreach ($docs as $row) {
            if ($row->type_id === 'KTP') $hasKtp = true;
            if ($row->type_id === 'KK')  $hasKk  = true;
        }

        if (!$hasKtp) return "Dokumen KTP belum lengkap";
        if (!$hasKk)  return "Dokumen KK belum lengkap";

        $bio = $this->getBiometricStatus($nik);
        if ($bio->photo !== 'completed') {
            return "Belum melakukan foto wajah";
        }

        return "Siap didaftarkan ke SK";
    }

    public function exists(string $nik, ?string $sk_number = null): bool
    {
        if (!empty($sk_number)) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM potensi
                WHERE nik = ?
                  AND sk_number = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->bind_param("ss", $nik, $sk_number);
        } else {
            $stmt = $this->db->prepare("
                SELECT 1 FROM potensi
                WHERE nik = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->bind_param("s", $nik);
        }

        $stmt->execute();
        $stmt->store_result();

        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function add(stdClass $data): bool
    {
        $nullableNumber = static function ($value): ?float {
            if ($value === null || $value === '') {
                return null;
            }

            return (float) $value;
        };

        $luasTanah = $nullableNumber($data->luas_tanah ?? null);
        $luasBangunan = $nullableNumber($data->luas_bangunan ?? null);
        $beneficiaryNik = $data->beneficiary_nik ?? null;
        $beneficiaryFamilycardNo = $data->beneficiary_familycard_no ?? null;
        $beneficiaryName = $data->beneficiary_name ?? null;
        $beneficiaryAddress = $data->beneficiary_address ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO potensi (
                nik, name, address, familycard_no,
                village, phone, sk_number,
                luas_tanah, luas_bangunan,
                beneficiary_nik, beneficiary_familycard_no,
                beneficiary_name, beneficiary_address,
                status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'POTENSI', CURRENT_TIMESTAMP
            )
        ");

        $stmt->bind_param(
            "sssssssddssss",
            $data->nik,
            $data->name,
            $data->address,
            $data->familycard_no,
            $data->village,
            $data->phone,
            $data->sk_number,
            $luasTanah,
            $luasBangunan,
            $beneficiaryNik,
            $beneficiaryFamilycardNo,
            $beneficiaryName,
            $beneficiaryAddress
        );

        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    public function update(stdClass $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE potensi SET
                name = ?,
                address = ?,
                familycard_no = ?,
                village = ?,
                phone = ?,
                luas_tanah = ?,
                luas_bangunan = ?,
                beneficiary_nik = ?,
                beneficiary_familycard_no = ?,
                beneficiary_name = ?,
                beneficiary_address = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE nik = ?
              AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "sssssddsssss",
            $data->name,
            $data->address,
            $data->familycard_no,
            $data->village,
            $data->phone,
            $data->luas_tanah,
            $data->luas_bangunan,
            $data->beneficiary_nik,
            $data->beneficiary_familycard_no,
            $data->beneficiary_name,
            $data->beneficiary_address,
            $data->nik
        );

        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    public function updateById(stdClass $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE potensi SET
                nik = ?,
                name = ?,
                address = ?,
                familycard_no = ?,
                village = ?,
                phone = ?,
                luas_tanah = ?,
                luas_bangunan = ?,
                beneficiary_nik = ?,
                beneficiary_familycard_no = ?,
                beneficiary_name = ?,
                beneficiary_address = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND deleted_at IS NULL
        ");

        $stmt->bind_param(
            "ssssssddssssi",
            $data->nik,
            $data->name,
            $data->address,
            $data->familycard_no,
            $data->village,
            $data->phone,
            $data->luas_tanah,
            $data->luas_bangunan,
            $data->beneficiary_nik,
            $data->beneficiary_familycard_no,
            $data->beneficiary_name,
            $data->beneficiary_address,
            $data->id
        );

        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }
}
