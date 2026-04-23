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

if ($parcelId <= 0) {
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
$foundExtensions = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (!empty($entryExt)) {
        $foundExtensions[$entryExt] = true;
    }
}
$zip->close();

$missingExtensions = array_values(array_filter($requiredExtensions, function ($ext) use ($foundExtensions) {
    return empty($foundExtensions[$ext]);
}));

if (!empty($missingExtensions)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ZIP SHP belum lengkap. File wajib: .shp, .shx, .dbf',
        'missing' => $missingExtensions,
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
        false
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
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
