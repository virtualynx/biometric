<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use mysqli_sql_exception;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/PotensiModel.php");

class PotensiImportModel extends Database
{
    public const MAX_ROWS = 1000;

    /** @var \mysqli */
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();

        // PotensiModel also keeps the database-level active NIK guard in place.
        new PotensiModel();
    }

    private function normalizeText($value, int $maxLength): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $text = trim((string) ($value ?? ''));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return function_exists('mb_substr')
            ? mb_substr($text, 0, $maxLength)
            : substr($text, 0, $maxLength);
    }

    private function buildAddress(array $row): string
    {
        $parts = [];
        $addressDomisili = $this->normalizeText($row['address_domisili'] ?? '', 1000);
        $addressKtp = $this->normalizeText($row['address_ktp'] ?? '', 1000);
        $rtRw = $this->normalizeText($row['rt_rw'] ?? '', 30);

        if ($addressDomisili !== '') {
            $parts[] = 'Alamat Domisili: ' . $addressDomisili;
        }
        if ($addressKtp !== '') {
            $parts[] = 'Alamat KTP: ' . $addressKtp;
        }
        if ($rtRw !== '') {
            $parts[] = 'RT/RW: ' . $rtRw;
        }

        return implode('; ', $parts);
    }

    private function findExistingNiks(array $niks): array
    {
        $niks = array_values(array_unique(array_filter($niks)));
        if (empty($niks)) {
            return [];
        }

        $existing = [];
        foreach (array_chunk($niks, 300) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare("
                SELECT DISTINCT nik
                FROM potensi
                WHERE deleted_at IS NULL
                  AND nik IN ($placeholders)
            ");

            $types = str_repeat('s', count($chunk));
            $params = array_merge([$types], $chunk);
            $references = [];
            foreach ($params as $index => &$value) {
                $references[$index] = &$value;
            }
            call_user_func_array([$stmt, 'bind_param'], $references);

            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $existing[$row['nik']] = true;
            }
            $stmt->close();
        }

        return $existing;
    }

    public function validateRows(array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw new \InvalidArgumentException(
                'Maksimal ' . self::MAX_ROWS . ' baris dalam satu kali impor.'
            );
        }

        $normalizedRows = [];
        $candidateNiks = [];
        $seenNiks = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $row = [];
            }

            $sourceRow = isset($row['source_row']) ? (int) $row['source_row'] : $index + 2;
            $nik = preg_replace('/\s+/u', '', $this->normalizeText($row['nik'] ?? '', 32)) ?? '';
            $name = $this->normalizeText($row['name'] ?? '', 255);
            $familycardNo = preg_replace(
                '/\s+/u',
                '',
                $this->normalizeText($row['familycard_no'] ?? '', 30)
            ) ?? '';
            $village = $this->normalizeText($row['village'] ?? '', 100);
            $address = $this->buildAddress($row);
            $errors = [];

            if ($nik === '') {
                $errors[] = 'NIK wajib diisi.';
            } elseif (!preg_match('/^\d{16}$/', $nik)) {
                $errors[] = 'NIK harus tepat 16 digit.';
            } elseif (!empty($row['nik_is_numeric'])) {
                $errors[] = 'Format sel NIK harus Text agar digit tidak berubah.';
            }

            if ($name === '') {
                $errors[] = 'Nama wajib diisi.';
            }

            if ($familycardNo !== '' && !preg_match('/^\d{1,30}$/', $familycardNo)) {
                $errors[] = 'No. KK hanya boleh berisi angka.';
            } elseif ($familycardNo !== '' && !empty($row['familycard_no_is_numeric'])) {
                $errors[] = 'Format sel No. KK harus Text agar digit tidak berubah.';
            }

            $status = 'ready';
            if (!empty($errors)) {
                $status = 'invalid';
            } elseif (isset($seenNiks[$nik])) {
                $status = 'duplicate_file';
                $errors[] = 'NIK duplikat di file; hanya kemunculan pertama yang diproses.';
            } else {
                $seenNiks[$nik] = true;
                $candidateNiks[] = $nik;
            }

            $normalizedRows[] = [
                'source_row' => $sourceRow,
                'nik' => $nik,
                'name' => $name,
                'familycard_no' => $familycardNo,
                'village' => $village,
                'address' => $address,
                'status' => $status,
                'message' => implode(' ', $errors),
            ];
        }

        $existingNiks = $this->findExistingNiks($candidateNiks);
        foreach ($normalizedRows as &$row) {
            if ($row['status'] === 'ready' && isset($existingNiks[$row['nik']])) {
                $row['status'] = 'duplicate_database';
                $row['message'] = 'NIK sudah aktif di tabel Potensi.';
            }
        }
        unset($row);

        return $normalizedRows;
    }

    public function import(array $rows, string $skNumber): array
    {
        $validatedRows = $this->validateRows($rows);
        $inserted = 0;

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO potensi (
                    nik, name, address, familycard_no,
                    village, phone, sk_number,
                    luas_tanah, luas_bangunan,
                    beneficiary_nik, beneficiary_familycard_no,
                    beneficiary_name, beneficiary_address,
                    status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, '', ?, NULL, NULL,
                    NULL, NULL, NULL, NULL,
                    'POTENSI', CURRENT_TIMESTAMP
                )
            ");

            foreach ($validatedRows as &$row) {
                if ($row['status'] !== 'ready') {
                    continue;
                }

                $stmt->bind_param(
                    'ssssss',
                    $row['nik'],
                    $row['name'],
                    $row['address'],
                    $row['familycard_no'],
                    $row['village'],
                    $skNumber
                );

                try {
                    $stmt->execute();
                    $row['status'] = 'inserted';
                    $row['message'] = 'Berhasil ditambahkan.';
                    $inserted++;
                } catch (mysqli_sql_exception $exception) {
                    if ((int) $exception->getCode() !== 1062) {
                        throw $exception;
                    }

                    $row['status'] = 'duplicate_database';
                    $row['message'] = 'NIK sudah aktif di tabel Potensi.';
                }
            }
            unset($row);
            $stmt->close();

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return [
            'inserted' => $inserted,
            'rows' => $validatedRows,
            'summary' => $this->summarize($validatedRows),
        ];
    }

    public function summarize(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ready' => 0,
            'inserted' => 0,
            'duplicate_file' => 0,
            'duplicate_database' => 0,
            'invalid' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'] ?? 'invalid';
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        return $summary;
    }
}
