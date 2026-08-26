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
        echo json_encode(['status' => 'error', 'message' => 'Gagal memeriksa data subjek.']);
        exit;
    }
    $person = new \stdClass();
    $person->nik       = $input['nik'];
    $person->sk_number = $input['sk_number'];
    $is_update = false;
}

$oldNik = $input['nik'];
$safeNikPattern = '/^[A-Za-z0-9_-]{1,32}$/D';
if (preg_match($safeNikPattern, (string) $oldNik) !== 1) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Format NIK tidak valid.']);
    exit;
}
$newNik = isset($input['new_nik']) && !empty($input['new_nik'])
    ? trim($input['new_nik'])
    : $oldNik;

if ($newNik !== $oldNik) {
    if (!preg_match('/^[0-9]{16}$/', $newNik)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Format NIK baru tidak valid (harus 16 digit angka)']);
        exit;
    }
    $check = $pm->exists($newNik);
    if ($check) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'NIK baru sudah digunakan.']);
        exit;
    }

    $pm->beginTransaction();

    try {
        $pm->migrateNikReferences($oldNik, $newNik);

        $pm->commit();

        $oldFolder = dirname(__FILE__, 3) . "/uploads/person/" . $oldNik;
        $newFolder = dirname(__FILE__, 3) . "/uploads/person/" . $newNik;
        if (file_exists($oldFolder)) {
            if (!@rename($oldFolder, $newFolder)) {
                error_log("⚠️ Gagal rename folder dari {$oldFolder} ke {$newFolder}");
                // fallback: copy dan hapus lama
                @mkdir($newFolder, 0750, true);
                foreach (glob($oldFolder . '/*') as $file) {
                    @rename($file, $newFolder . '/' . basename($file));
                }
                @rmdir($oldFolder);
            }
        }


        $person->nik = $newNik;
    } catch (\Throwable $e) {
        $pm->rollback();
        $status = $e instanceof \DomainException ? 409 : 500;
        http_response_code($status);
        echo json_encode([
            'status' => 'error',
            'message' => biometricPublicExceptionMessage($e, 'Gagal memperbarui NIK dan data terkait.'),
        ]);
        exit;
    }
}

$fields = ['name', 'address', 'familycard_no', 'village', 'phone', 'luas_tanah', 'luas_bangunan'];

foreach ($fields as $field) {
    if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== null) {
        $person->$field = $input[$field];
    }
}

$activeNik = $newNik;

$normalizeNullable = static function ($value) {
    if (!isset($value)) {
        return null;
    }

    $trimmed = trim((string) $value);

    return $trimmed === '' ? null : $trimmed;
};

function replacePhoto($fu, $model, $nik, $base64, $filename, $path, $type)
{
    if (empty($base64)) return;

    $purpose = $model instanceof PhotoModel
        ? FileUploadModel::PURPOSE_IMAGE
        : FileUploadModel::PURPOSE_DOCUMENT;
    $filedata = $fu->upload($base64, $filename, $path, true, true, $purpose);

    if (method_exists($model, 'deleteByType')) {
        $model->deleteByType($nik, $type);
    }

    $model->add($nik, $filedata->filename, $filedata->path, $type, null, $filedata->extension);
}

$person->beneficiary_nik = $normalizeNullable($input['beneficiary_nik'] ?? null);
$person->beneficiary_familycard_no = $normalizeNullable($input['beneficiary_familycard_no'] ?? null);
$person->beneficiary_name = $normalizeNullable($input['beneficiary_name'] ?? null);
$person->beneficiary_address = $normalizeNullable($input['beneficiary_address'] ?? null);

if (!empty($input['photo_profile'])) {
    replacePhoto(
        $fu,
        $phm,
        $activeNik,
        $input['photo_profile'],
        $activeNik . '.jpeg',
        'person/' . $activeNik . '/',
        PhotoModel::PHOTO_TYPE_BIOMETRIC
    );
}

if (!empty($input['photo_ktp'])) {
    replacePhoto(
        $fu,
        $dcm,
        $activeNik,
        $input['photo_ktp'],
        'KTP_' . $activeNik . '.jpeg',
        'person/' . $activeNik . '/documents/',
        'KTP'
    );
}

if (!empty($input['photo_kk'])) {
    replacePhoto(
        $fu,
        $dcm,
        $activeNik,
        $input['photo_kk'],
        'KK_' . $activeNik . '.jpeg',
        'person/' . $activeNik . '/documents/',
        'KK'
    );
}

if (!empty($input['photos']) && is_array($input['photos'])) {
    foreach ($input['photos'] as $index => $base64) {
        if (!empty($base64)) {
            $filedata = $fu->upload(
                $base64,
                $activeNik . "_documentation_" . time() . "_{$index}.jpeg",
                'person/' . $activeNik . '/photos/',
                true,
                true,
                FileUploadModel::PURPOSE_IMAGE
            );
            $phm->add(
                $activeNik,
                $filedata->filename,
                $filedata->path,
                'documentation',
                null,
                $filedata->extension
            );
        }
    }
}

if (!empty($input['face_encoding']) && is_array($input['face_encoding'])) {
    $encoding_json = json_encode($input['face_encoding'], JSON_UNESCAPED_SLASHES);

    try {
        $fm->delete($activeNik, FaceModel::ID_TYPE_NIK);
    } catch (\Exception $e) {
    }

    try {
        $fm->enroll($activeNik, FaceModel::ID_TYPE_NIK, $encoding_json);
    } catch (\Exception $e) {
        error_log("Face enroll failed for {$input['nik']}: " . $e->getMessage());
    }
}

try {
    if ($is_update) {
        $pm->update_mobile($person, $input['sk_number']);
        $msg = 'Person updated successfully';
    } else {
        $pm->update_mobile($person, $input['sk_number']);
        $msg = 'Person updated (created implicitly)';
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => biometricPublicExceptionMessage($e, 'Gagal memperbarui data subjek.')
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => $msg,
    'data' => [
        'nik' => $person->nik,
        'sk_number' => $input['sk_number'],
    ],
]);
