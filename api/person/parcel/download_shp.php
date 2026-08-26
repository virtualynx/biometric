<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/FileUploadModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\SubjectLandParcelModel;

$jsonInput = json_decode((string) file_get_contents('php://input'), true);
$input = array_replace(
    is_array($_GET) ? $_GET : [],
    is_array($_POST) ? $_POST : [],
    is_array($jsonInput) ? $jsonInput : []
);
$nik = $input['nik'] ?? null;
$parcelId = !empty($input['parcel_id']) ? (int) $input['parcel_id'] : 0;

if (empty($nik) || $parcelId <= 0) {
    http_response_code(400);
    echo 'Parameter nik & parcel_id is required';
    exit;
}

try {
    $model = new SubjectLandParcelModel();
    $parcel = $model->getById($parcelId, trim((string) $nik));

    if (empty($parcel)) {
        echo 'Bidang tanah tidak ditemukan';
        exit;
    }

    $file = $model->getActiveShpFile($parcelId);
    if (empty($file)) {
        echo 'File SHP tidak ditemukan';
        exit;
    }

    $upload = new FileUploadModel();
    $upload->downloadFile($file->filename, $file->file_path);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengunduh SHP.']);
}
