<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");

use biometric\src\core\models\PotensiModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\PhotoModel;

if (empty($_POST['nik']) && empty($_POST['potensi_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing NIK or potensi_id']);
    exit;
}

$nik = $_POST['nik'] ?? null;
$potensiId = isset($_POST['potensi_id']) ? intval($_POST['potensi_id']) : 0;
$with_photo = true;
if (!empty($_POST['without_photo']) && filter_var($_POST['without_photo'], FILTER_VALIDATE_BOOLEAN) == true) {
    $with_photo = false;
}

$pm = new PotensiModel();
$photoModel = new PhotoModel();

try {
    if ($potensiId > 0) {
        $potensi = $pm->getById($potensiId);
        $potensi = $potensi ? json_decode(json_encode($potensi), true) : null;
    } else {
        $db = $pm->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM potensi
            WHERE nik = ?
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $nik);
        $stmt->execute();
        $result = $stmt->get_result();
        $potensi = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$potensi) {
        http_response_code(404);
        echo json_encode(['error' => 'Potensi not found']);
        exit;
    }

    $nik = $potensi['nik'];

    $photos = $photoModel->get($nik);
    $potensi['photos'] = [];
    
    $bioPhoto = null;
    foreach ($photos as $row) {
        if ($row->type == 'biometric') {
            if ($with_photo) {
                $fum = new FileUploadModel();
                try {
                    $bioPhoto = $fum->getBase64String($row->filename, $row->photo_path);
                } catch (\Exception $photoException) {
                    if (
                        str_starts_with($photoException->getMessage(), 'File ') &&
                        str_ends_with($photoException->getMessage(), ' does not exists')
                    ) {
                        error_log($photoException->getMessage());
                        $bioPhoto = null;
                        $potensi['photo_warning'] = 'Biometric photo file is missing';
                    } else {
                        throw $photoException;
                    }
                }
            }
        } else {
            $potensi['photos'][] = $row;
        }
    }
    if ($with_photo) {
        $potensi['photo'] = $bioPhoto;
    }

    $potensi['biometric_status'] = $pm->getBiometricStatus($nik);
    $potensi['status'] = $pm->getOverallStatus($nik);

    echo json_encode($potensi);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => biometricPublicExceptionMessage($e, 'Gagal memuat data potensi.')]);
}
