<?php

declare(strict_types=1);

namespace biometric\src\core\models;

require_once dirname(__DIR__) . '/Database.php';

use biometric\src\core\Database;
use mysqli;

final class SubjectHistoryModel
{
    private mysqli $db;

    public function __construct(?mysqli $connection = null)
    {
        $this->db = $connection ?? (new Database())->getConnection();
    }

    public function findRelatedRecords(string $nik): array
    {
        $current = $this->db->prepare(
            'SELECT nik, familycard_no
             FROM person
             WHERE nik = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $current->bind_param('s', $nik);
        $current->execute();
        $subject = $current->get_result()->fetch_assoc();
        $current->close();
        if (!$subject) {
            return [];
        }

        $familyCardNo = trim((string) ($subject['familycard_no'] ?? ''));
        $statement = $this->db->prepare(
            'SELECT
                nik, name, address, familycard_no, village, phone, sk_number,
                created_at, updated_at, beneficiary_nik,
                beneficiary_familycard_no, beneficiary_name,
                beneficiary_address, luas_tanah, luas_bangunan
             FROM person
             WHERE deleted_at IS NULL
               AND (
                    nik = ?
                    OR beneficiary_nik = ?
                    OR (? <> \'\' AND familycard_no = ?)
                    OR (? <> \'\' AND beneficiary_familycard_no = ?)
               )
             ORDER BY updated_at DESC, created_at DESC, nik ASC'
        );
        $statement->bind_param(
            'ssssss',
            $nik,
            $nik,
            $familyCardNo,
            $familyCardNo,
            $familyCardNo,
            $familyCardNo
        );
        $statement->execute();
        $records = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $records;
    }

    public function listSkLabels(array $records): array
    {
        $skNumbers = array_values(array_unique(array_filter(array_map(
            static fn(array $record): string => trim((string) ($record['sk_number'] ?? '')),
            $records
        ))));
        if ($skNumbers === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($skNumbers), '?'));
        $statement = $this->db->prepare(
            "SELECT sk_number, site_desc
             FROM master_sk
             WHERE sk_number IN ({$placeholders})"
        );
        $types = str_repeat('s', count($skNumbers));
        $statement->bind_param($types, ...$skNumbers);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $rows;
    }
}
