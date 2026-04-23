<?php

require_once(dirname(__FILE__) . "/../../_api_header.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/SubjectLandParcelModel.php");
require_once(dirname(__FILE__) . "/../../../src/core/models/FileUploadModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\SubjectLandParcelModel;

$nik = $_GET['nik'] ?? null;
$parcelId = !empty($_GET['parcel_id']) ? (int) $_GET['parcel_id'] : 0;

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
    echo $e->getMessage();
}
