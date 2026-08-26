<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/FileUploadModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\SubjectLandParcelModel;

if (empty($_POST['nik']) || empty($_POST['parcel_id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing NIK or parcel_id',
    ]);
    exit;
}

if (empty($_FILES['document'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing SHP file',
    ]);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ZipArchive extension is not available on the server.',
    ]);
    exit;
}

$nik = trim((string) $_POST['nik']);
$parcelId = (int) $_POST['parcel_id'];
$file = $_FILES['document'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (
    (int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK ||
    !is_uploaded_file((string) ($file['tmp_name'] ?? ''))
) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Transfer file SHP tidak valid.',
    ]);
    exit;
}

if ($parcelId <= 0 || preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid parcel_id',
    ]);
    exit;
}

if ($extension !== 'zip') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'File SHP wajib berupa ZIP shapefile.',
    ]);
    exit;
}

if (!empty($file['size']) && (int) $file['size'] > 25 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Ukuran file SHP maksimal 25 MB.',
    ]);
    exit;
}

$zip = new ZipArchive();
$opened = $zip->open($file['tmp_name']);
if ($opened !== true) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ZIP SHP tidak dapat dibuka.',
    ]);
    exit;
}

$requiredExtensions = ['shp', 'shx', 'dbf'];
$allowedExtensions = ['shp', 'shx', 'dbf', 'prj', 'cpg', 'qpj', 'sbn', 'sbx', 'xml'];
$componentSets = [];
$totalCompressedBytes = 0;
$totalUncompressedBytes = 0;
$zipError = null;

if ($zip->numFiles <= 0 || $zip->numFiles > 64) {
    $zipError = 'ZIP SHP berisi terlalu banyak file atau tidak memiliki isi.';
}

for ($i = 0; $i < $zip->numFiles; $i++) {
    if ($zipError !== null) {
        break;
    }

    $entry = $zip->getNameIndex($i);
    $stat = $zip->statIndex($i);
    if (!is_string($entry) || !is_array($stat)) {
        $zipError = 'ZIP SHP memiliki struktur yang tidak valid.';
        break;
    }

    if (
        strlen($entry) > 240 ||
        strpos($entry, "\0") !== false ||
        strpos($entry, '\\') !== false ||
        preg_match('#(^/|(^|/)\.\.(/|$))#', $entry) === 1
    ) {
        $zipError = 'ZIP SHP memiliki nama file yang tidak aman.';
        break;
    }

    if (substr($entry, -1) === '/') {
        continue;
    }

    $basename = basename($entry);
    if ($basename === '.DS_Store' || strpos($entry, '__MACOSX/') === 0) {
        continue;
    }

    $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (!in_array($entryExt, $allowedExtensions, true)) {
        $zipError = 'ZIP SHP hanya boleh berisi komponen shapefile.';
        break;
    }

    $compressedBytes = max(0, (int) ($stat['comp_size'] ?? 0));
    $uncompressedBytes = max(0, (int) ($stat['size'] ?? 0));
    $totalCompressedBytes += $compressedBytes;
    $totalUncompressedBytes += $uncompressedBytes;

    if (
        $uncompressedBytes > 64 * 1024 * 1024 ||
        $totalUncompressedBytes > 100 * 1024 * 1024 ||
        ($compressedBytes > 0 && $uncompressedBytes > ($compressedBytes * 100) + (10 * 1024 * 1024))
    ) {
        $zipError = 'ZIP SHP melewati batas keamanan kompresi.';
        break;
    }

    $stem = strtolower(pathinfo($basename, PATHINFO_FILENAME));
    $componentSets[$stem][$entryExt] = true;
}
$zip->close();

$completeSets = array_filter($componentSets, static function (array $extensions) use ($requiredExtensions) {
    foreach ($requiredExtensions as $requiredExtension) {
        if (empty($extensions[$requiredExtension])) {
            return false;
        }
    }
    return true;
});

if ($zipError !== null || count($completeSets) !== 1) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $zipError ?? 'ZIP SHP harus memuat tepat satu set .shp, .shx, dan .dbf.',
    ]);
    exit;
}

try {
    $model = new SubjectLandParcelModel();
    $parcel = $model->getById($parcelId, $nik);

    if (empty($parcel)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Bidang tanah tidak ditemukan untuk subjek ini.',
        ]);
        exit;
    }

    $upload = new FileUploadModel();
    $savedFile = $upload->upload(
        $file,
        null,
        "person/" . $nik . "/parcels/" . $parcelId,
        true,
        false,
        FileUploadModel::PURPOSE_SHAPEFILE
    );

    $model->replaceShpFile(
        $parcelId,
        $savedFile->filename,
        $savedFile->path,
        $savedFile->extension,
        !empty($file['size']) ? (int) $file['size'] : null
    );

    $updatedParcel = $model->getById($parcelId, $nik);

    echo json_encode([
        'status' => 'success',
        'message' => 'SHP bidang berhasil diunggah.',
        'data' => $updatedParcel,
    ]);
} catch (\Throwable $e) {
    http_response_code(biometricPublicExceptionStatus($e));
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal mengunggah SHP.'),
    ]);
}
