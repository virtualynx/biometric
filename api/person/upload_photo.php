<?php
require_once(dirname(__FILE__) . "/../../src/utils/Helper.php");
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");

use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\utils\Helper;

// Ambil data JSON dari request body
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

// Fallback jika user kirim pakai form-urlencoded
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

if (empty($input['nik'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing NIK"]);
    exit;
}

if (empty($input['photo'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing photo"]);
    exit;
}

$nik         = $input['nik'];
$desc        = $input['description'] ?? null;
$filename    = $input['filename'] ?? ("Dokumentasi-RA-" . date("d-m-Y-His") . rand(100, 999) . ".jpeg");
$is_base64   = filter_var($input['is_base64'] ?? true, FILTER_VALIDATE_BOOLEAN);

$latitude  = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

$latlong = null;
if (!empty($latitude) && !empty($longitude)) {
    $latlong = "{$latitude},{$longitude}";
}

// Selalu pakai documentation type
$photoType   = PhotoModel::PHOTO_TYPE_DOCUMENTATION;

// Target path
$targetPath  = "person/$nik/photos/";

// Dapatkan data foto
$photoData = $input['photo'];

// Upload file base64
try {
    $fu = new FileUploadModel();
    $filedata = $fu->upload($photoData, $filename, $targetPath, true, $is_base64);

    $phm = new PhotoModel();
    $phm->add(
        $nik,
        $filedata->filename,
        $filedata->path,
        $photoType,
        $desc,
        $filedata->extension,
        $latlong
    );

    echo json_encode([
        "status" => "success",
        "message" => "Photo uploaded successfully",
        "data" => [
            "nik" => $nik,
            "filename" => $filedata->filename,
            "path" => $filedata->path,
            "type" => $photoType,
            "description" => $desc,
            "latlong" => $latlong
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
