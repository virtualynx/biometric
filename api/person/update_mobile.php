<?php
require_once(dirname(__FILE__) . "/../../src/utils/Helper.php");
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/DocumentModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/QueueModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FaceModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\utils\Helper;
use biometric\src\core\models\FaceModel;

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (empty($input['nik']) || empty($input['sk_number'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing NIK or sk_number']);
    exit;
}

$pm  = new PersonModel();
$fu  = new FileUploadModel();
$dcm = new DocumentModel();
$phm = new PhotoModel();
$fm  = new FaceModel();


try {
    $person = $pm->get($input['nik'], $input['sk_number']);
    $is_update = true;
} catch (\Exception $e) {
    if ($e->getMessage() !== 'Data not found') {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    $person = new \stdClass();
    $person->nik       = $input['nik'];
    $person->sk_number = $input['sk_number'];
    $is_update = false;
}


$fields = ['name', 'address', 'familycard_no', 'village', 'phone', 'luas_tanah', 'luas_bangunan'];

foreach ($fields as $field) {
    if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== null) {
        $person->$field = $input[$field];
    }
}


function replacePhoto($fu, $model, $nik, $base64, $filename, $path, $type)
{
    if (empty($base64)) return;

    $filedata = $fu->upload($base64, $filename, $path, true, true);

    if (method_exists($model, 'deleteByType')) {
        $model->deleteByType($nik, $type);
    }

    $model->add($nik, $filedata->filename, $filedata->path, $type, null, $filedata->extension);
}

if (!empty($input['photo_profile'])) {
    replacePhoto(
        $fu,
        $phm,
        $input['nik'],
        $input['photo_profile'],
        $input['nik'] . '.jpeg',
        'person/' . $input['nik'] . '/',
        PhotoModel::PHOTO_TYPE_BIOMETRIC
    );
}

if (!empty($input['photo_ktp'])) {
    replacePhoto(
        $fu,
        $dcm,
        $input['nik'],
        $input['photo_ktp'],
        'KTP_' . $input['nik'] . '.jpeg',
        'person/' . $input['nik'] . '/documents/',
        'KTP'
    );
}

if (!empty($input['photo_kk'])) {
    replacePhoto(
        $fu,
        $dcm,
        $input['nik'],
        $input['photo_kk'],
        'KK_' . $input['nik'] . '.jpeg',
        'person/' . $input['nik'] . '/documents/',
        'KK'
    );
}

if (!empty($input['photos']) && is_array($input['photos'])) {
    foreach ($input['photos'] as $index => $base64) {
        if (!empty($base64)) {
            $filedata = $fu->upload(
                $base64,
                $input['nik'] . "_documentation_" . time() . "_{$index}.jpeg",
                'person/' . $input['nik'] . '/photos/',
                true,
                true
            );
            $phm->add(
                $input['nik'],
                $filedata->filename,
                $filedata->path,
                'documentation',
                null,
                $filedata->extension
            );
        }
    }
}

// ==========================
// 5️⃣ Replace face_encoding (timpa lama)
// ==========================
if (!empty($input['face_encoding']) && is_array($input['face_encoding'])) {
    $encoding_json = json_encode($input['face_encoding'], JSON_UNESCAPED_SLASHES);

    try {
        // Hapus lama
        $fm->delete($input['nik'], FaceModel::ID_TYPE_NIK);
    } catch (\Exception $e) {
        // abaikan jika belum ada
    }

    try {
        // Tambah baru
        $fm->enroll($input['nik'], FaceModel::ID_TYPE_NIK, $encoding_json);
    } catch (\Exception $e) {
        error_log("Face enroll failed for {$input['nik']}: " . $e->getMessage());
    }
}

// ==========================
// 6️⃣ Simpan person data
// ==========================
try {
    if ($is_update) {
        // Jika sudah ada, update data saja
        $pm->update_mobile($person, $input['sk_number']);
        $msg = 'Person updated successfully';
    } else {
        // Jika belum ada, kita juga update saja (tidak create baru)
        $pm->update_mobile($person, $input['sk_number']);
        $msg = 'Person updated (created implicitly)';
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}


// ==========================
// ✅ Selesai
// ==========================
echo json_encode(['status' => 'success', 'message' => $msg]);
