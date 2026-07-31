<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__) . "/../Database.php");

class ActivityPlanModel extends Database
{
    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();
        $this->ensureTable();
        $this->ensureSubjectStatusVerifiedAtColumn();
    }

    private function ensureSubjectStatusVerifiedAtColumn(): void
    {
        try {
            $columns = $this->db->query("SHOW COLUMNS FROM trx_subject_status");
            if (!$columns) {
                return;
            }

            $existingColumns = [];
            while ($column = $columns->fetch_assoc()) {
                $existingColumns[] = $column["Field"];
            }

            if (!in_array("verified_at", $existingColumns, true)) {
                $this->db->query("ALTER TABLE trx_subject_status ADD COLUMN verified_at DATETIME NULL DEFAULT NULL AFTER updated_at");
            }
        } catch (\Throwable $e) {
            error_log("Failed to ensure trx_subject_status.verified_at: " . $e->getMessage());
        }
    }

    private function ensureTable(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS activity_plan (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_name VARCHAR(255) NOT NULL,
                batch_no INT UNSIGNED NOT NULL DEFAULT 1,
                site_desc VARCHAR(255) NOT NULL,
                sk_number VARCHAR(128) NOT NULL,
                date_start DATE NOT NULL,
                date_end DATE NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_activity_plan_location (site_desc, sk_number),
                INDEX idx_activity_plan_dates (date_start, date_end),
                INDEX idx_activity_plan_deleted (deleted_at)
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
        return empty($rows) ? null : $rows[0];
    }

    public function list(?string $siteDesc = null, ?string $skNumber = null): array
    {
        $where = ["ap.deleted_at IS NULL"];
        $params = [];

        if (!empty($siteDesc)) {
            $where[] = "ap.site_desc = ?";
            $params[] = $siteDesc;
        }

        if (!empty($skNumber)) {
            $where[] = "ap.sk_number = ?";
            $params[] = $skNumber;
        }

        return $this->fetchAll("
            SELECT
                ap.*,
                COUNT(DISTINCT CASE WHEN ms.id IS NOT NULL THEN p.nik END) AS matched_subject_count
            FROM activity_plan ap
            LEFT JOIN person p
                ON p.sk_number COLLATE utf8mb4_general_ci = ap.sk_number COLLATE utf8mb4_general_ci
                AND p.deleted_at IS NULL
            LEFT JOIN trx_subject_status tss
                ON tss.nik COLLATE utf8mb4_general_ci = p.nik COLLATE utf8mb4_general_ci
                AND tss.is_done = 1
                AND COALESCE(tss.verified_at, tss.updated_at) IS NOT NULL
                AND DATE(COALESCE(tss.verified_at, tss.updated_at)) BETWEEN ap.date_start AND ap.date_end
            LEFT JOIN master_status ms
                ON ms.id COLLATE utf8mb4_general_ci = tss.status_id COLLATE utf8mb4_general_ci
                AND ms.disabled = 0
            WHERE " . implode(" AND ", $where) . "
            GROUP BY
                ap.id,
                ap.plan_name,
                ap.batch_no,
                ap.site_desc,
                ap.sk_number,
                ap.date_start,
                ap.date_end,
                ap.notes,
                ap.created_at,
                ap.updated_at,
                ap.deleted_at
            ORDER BY ap.date_start DESC, ap.batch_no DESC, ap.id DESC
        ", $params);
    }

    public function getById(int $id): ?stdClass
    {
        return $this->fetchOne("
            SELECT *
            FROM activity_plan
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1
        ", [(string) $id]);
    }

    public function create(stdClass $payload): stdClass
    {
        $batchNo = !empty($payload->batch_no)
            ? (int) $payload->batch_no
            : $this->getNextBatchNo($payload->site_desc, $payload->sk_number);

        $stmt = $this->db->prepare("
            INSERT INTO activity_plan (
                plan_name,
                batch_no,
                site_desc,
                sk_number,
                date_start,
                date_end,
                notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }

        $batchNoValue = (string) $batchNo;
        $stmt->bind_param(
            "sssssss",
            $payload->plan_name,
            $batchNoValue,
            $payload->site_desc,
            $payload->sk_number,
            $payload->date_start,
            $payload->date_end,
            $payload->notes
        );
        $stmt->execute();
        $planId = (int) $stmt->insert_id;
        $stmt->close();

        $plan = $this->getById($planId);
        if (empty($plan)) {
            throw new \Exception("Rencana kegiatan gagal disimpan.");
        }

        return $plan;
    }

    public function softDelete(int $id, string $siteDesc, string $skNumber): ?stdClass
    {
        $plan = $this->fetchOne("
            SELECT *
            FROM activity_plan
            WHERE id = ?
              AND site_desc = ?
              AND sk_number = ?
              AND deleted_at IS NULL
            LIMIT 1
        ", [(string) $id, $siteDesc, $skNumber]);

        if (empty($plan)) {
            return null;
        }

        $stmt = $this->db->prepare("
            UPDATE activity_plan
            SET deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND site_desc = ?
              AND sk_number = ?
              AND deleted_at IS NULL
        ");
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }

        $planId = (string) $id;
        $stmt->bind_param("sss", $planId, $siteDesc, $skNumber);
        $stmt->execute();
        $deleted = $stmt->affected_rows === 1;
        $stmt->close();

        return $deleted ? $plan : null;
    }

    private function getNextBatchNo(string $siteDesc, string $skNumber): int
    {
        $row = $this->fetchOne("
            SELECT COALESCE(MAX(batch_no), 0) + 1 AS next_batch_no
            FROM activity_plan
            WHERE site_desc = ?
              AND sk_number = ?
              AND deleted_at IS NULL
        ", [$siteDesc, $skNumber]);

        return max(1, (int) ($row->next_batch_no ?? 1));
    }

    public function listSubjectMatches(int $planId): array
    {
        $plan = $this->getById($planId);
        if (empty($plan)) {
            throw new \Exception("Rencana kegiatan tidak ditemukan.");
        }

        return $this->fetchAll("
            SELECT
                p.nik,
                p.name,
                p.familycard_no,
                p.village,
                p.address,
                p.sk_number,
                MAX(COALESCE(tss.verified_at, tss.updated_at)) AS matched_at,
                GROUP_CONCAT(DISTINCT ms.name ORDER BY ms.`order` SEPARATOR ', ') AS matched_statuses
            FROM person p
            INNER JOIN trx_subject_status tss
                ON tss.nik COLLATE utf8mb4_general_ci = p.nik COLLATE utf8mb4_general_ci
                AND tss.is_done = 1
                AND COALESCE(tss.verified_at, tss.updated_at) IS NOT NULL
                AND DATE(COALESCE(tss.verified_at, tss.updated_at)) BETWEEN ? AND ?
            INNER JOIN master_status ms
                ON ms.id COLLATE utf8mb4_general_ci = tss.status_id COLLATE utf8mb4_general_ci
                AND ms.disabled = 0
            WHERE p.deleted_at IS NULL
              AND p.sk_number COLLATE utf8mb4_general_ci = ? COLLATE utf8mb4_general_ci
            GROUP BY
                p.nik,
                p.name,
                p.familycard_no,
                p.village,
                p.address,
                p.sk_number
            ORDER BY matched_at DESC, p.name ASC
        ", [$plan->date_start, $plan->date_end, $plan->sk_number]);
    }
}
