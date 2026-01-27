<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");

use biometric\src\core\models\PotensiModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\PhotoModel;

if (empty($_POST['nik'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing NIK']);
    exit;
}

$nik = $_POST['nik'];

$pm = new PotensiModel();
$photoModel = new PhotoModel();

try {
    $db = $pm->getConnection();
    $stmt = $db->prepare("
        SELECT *
        FROM potensi
        WHERE nik = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("s", $nik);
    $stmt->execute();
    $result = $stmt->get_result();
    $potensi = $result->fetch_assoc();
    $stmt->close();

    if (!$potensi) {
        http_response_code(404);
        echo json_encode(['error' => 'Potensi not found']);
        exit;
    }

    $photos = $photoModel->get($nik);
    $potensi['photos'] = [];
    
    $bioPhoto = null;
    foreach ($photos as $row) {
        if ($row->type == 'biometric') {
            $fum = new FileUploadModel();
            $bioPhoto = $fum->getBase64String($row->filename, $row->photo_path);
        } else {
            $potensi['photos'][] = $row;
        }
    }
    $potensi['photo'] = $bioPhoto;

    $potensi['biometric_status'] = $pm->getBiometricStatus($nik);
    $potensi['status'] = $pm->getOverallStatus($nik);

    echo json_encode($potensi);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
