<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/Database.php");

use biometric\src\core\Database;

$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) {
    $input = $_POST;
}

$nik = isset($input['nik']) ? trim((string) $input['nik']) : '';
$skNumber = isset($input['sk_number']) ? trim((string) $input['sk_number']) : '';
$name = isset($input['name']) ? trim((string) $input['name']) : '';
$address = isset($input['address']) ? trim((string) $input['address']) : '';
$familycardNo = isset($input['familycard_no']) ? trim((string) $input['familycard_no']) : '';
$village = isset($input['village']) ? trim((string) $input['village']) : '';

if ($nik === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'NIK wajib diisi']);
    exit;
}

function collectRows(mysqli $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function executeStatement(mysqli $db, string $sql, array $params = []): void
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $stmt->close();
}

function deleteDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
            continue;
        }

        @unlink($path);
    }

    @rmdir($dir);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $activeNikRows = collectRows(
        $db,
        "SELECT nik, sk_number, name, address, familycard_no, village
         FROM person
         WHERE nik = ? AND deleted_at IS NULL",
        [$nik]
    );

    if (count($activeNikRows) > 1) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Ditemukan lebih dari satu subjek aktif dengan NIK ini. Penghapusan dibatalkan demi keamanan.',
        ]);
        exit;
    }

    $personRows = collectRows(
        $db,
        "SELECT nik, sk_number, name, address, familycard_no, village
         FROM person
         WHERE nik = ?
           AND deleted_at IS NULL
           AND (? = '' OR sk_number = ?)
           AND (? = '' OR name = ?)
           AND COALESCE(address, '') = ?
           AND COALESCE(familycard_no, '') = ?
           AND COALESCE(village, '') = ?
         LIMIT 2",
        [$nik, $skNumber, $skNumber, $name, $name, $address, $familycardNo, $village]
    );

    if (empty($personRows)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Subjek tidak cocok dengan data yang dipilih. Penghapusan dibatalkan demi keamanan.',
        ]);
        exit;
    }

    if (count($personRows) > 1) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Ditemukan lebih dari satu subjek dengan identitas yang sama. Penghapusan dibatalkan demi keamanan.',
        ]);
        exit;
    }

    $person = $personRows[0];
    $effectiveSkNumber = $person['sk_number'] ?? $skNumber;

    $documentRows = collectRows(
        $db,
        "SELECT file_path FROM document WHERE nik = ?",
        [$nik]
    );
    $photoRows = collectRows(
        $db,
        "SELECT photo_path FROM photo WHERE nik = ?",
        [$nik]
    );
    $parcelRows = collectRows(
        $db,
        "SELECT id FROM subject_land_parcel WHERE nik = ?",
        [$nik]
    );

    $parcelIds = array_values(array_filter(array_map(
        fn($row) => isset($row['id']) ? (string) $row['id'] : null,
        $parcelRows
    )));

    $parcelFileRows = [];
    if (!empty($parcelIds)) {
        $placeholders = implode(',', array_fill(0, count($parcelIds), '?'));
        $parcelFileRows = collectRows(
            $db,
            "SELECT file_path FROM subject_land_parcel_file WHERE parcel_id IN ($placeholders)",
            $parcelIds
        );
    }

    $filePaths = array_values(array_unique(array_filter(array_merge(
        array_map(fn($row) => $row['file_path'] ?? null, $documentRows),
        array_map(fn($row) => $row['photo_path'] ?? null, $photoRows),
        array_map(fn($row) => $row['file_path'] ?? null, $parcelFileRows)
    ))));

    $matchingPotensiRows = collectRows(
        $db,
        "SELECT id
         FROM potensi
         WHERE nik = ?
           AND (? = '' OR sk_number = ?)
           AND (? = '' OR name = ?)
           AND COALESCE(address, '') = ?
           AND COALESCE(familycard_no, '') = ?
           AND COALESCE(village, '') = ?",
        [$nik, $effectiveSkNumber, $effectiveSkNumber, $name, $name, $address, $familycardNo, $village]
    );

    if (count($matchingPotensiRows) > 1) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Ditemukan lebih dari satu data Potensi yang cocok persis. Penghapusan dibatalkan demi keamanan.',
        ]);
        exit;
    }

    $db->begin_transaction();

    executeStatement($db, "DELETE FROM trx_subject_status WHERE nik = ?", [$nik]);
    executeStatement($db, "DELETE FROM queue WHERE nik = ?", [$nik]);
    executeStatement($db, "DELETE FROM fingerprint WHERE nik = ?", [$nik]);
    executeStatement($db, "DELETE FROM face WHERE person_id = ?", [$nik]);

    if (!empty($parcelIds)) {
        $placeholders = implode(',', array_fill(0, count($parcelIds), '?'));
        executeStatement(
            $db,
            "DELETE FROM subject_land_parcel_file WHERE parcel_id IN ($placeholders)",
            $parcelIds
        );
    }

    executeStatement($db, "DELETE FROM subject_land_parcel WHERE nik = ?", [$nik]);
    executeStatement($db, "DELETE FROM document WHERE nik = ?", [$nik]);
    executeStatement($db, "DELETE FROM photo WHERE nik = ?", [$nik]);
    if (!empty($matchingPotensiRows)) {
        executeStatement(
            $db,
            "DELETE FROM potensi WHERE id = ?",
            [(string) $matchingPotensiRows[0]['id']]
        );
    }
    executeStatement(
        $db,
        "DELETE FROM person
         WHERE nik = ?
           AND (? = '' OR sk_number = ?)
           AND (? = '' OR name = ?)
           AND COALESCE(address, '') = ?
           AND COALESCE(familycard_no, '') = ?
           AND COALESCE(village, '') = ?",
        [$nik, $effectiveSkNumber, $effectiveSkNumber, $name, $name, $address, $familycardNo, $village]
    );

    $db->commit();

    $backendRoot = dirname(__FILE__, 3);
    foreach ($filePaths as $relativePath) {
        $absolutePath = $backendRoot . '/' . ltrim($relativePath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    deleteDirectoryRecursive($backendRoot . "/uploads/person/" . $nik);

    echo json_encode([
        'status' => 'success',
        'message' => 'Subjek telah berhasil dihapus',
        'data' => [
            'nik' => $nik,
            'sk_number' => $effectiveSkNumber,
            'name' => $person['name'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
