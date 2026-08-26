<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/DocumentModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FaceModel.php");

use biometric\src\core\models\PotensiModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\FaceModel;

$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) {
    $input = $_POST;
}

if (empty($input['nik'])) {
    http_response_code(400);
    echo json_encode(['error' => 'NIK wajib diisi']);
    exit;
}

$pm = new PotensiModel();
$fu = new FileUploadModel();
$dcm = new DocumentModel();
$phm = new PhotoModel();
$fm = new FaceModel();

$normalizeNullable = static function ($value) {
    if (!isset($value)) {
        return null;
    }

    $trimmed = trim((string) $value);

    return $trimmed === '' ? null : $trimmed;
};

try {
    $potensiId = isset($input['potensi_id']) ? intval($input['potensi_id']) : 0;
    $potensi = $potensiId > 0 ? $pm->getById($potensiId) : null;

    if (!$potensi && !$pm->exists($input['nik'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Potensi tidak ditemukan']);
        exit;
    }

    if (!$potensi) {
        $db = $pm->getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM potensi
            WHERE nik = ?
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $input['nik']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $potensi = $row ? json_decode(json_encode($row)) : null;
    }

    if (!$potensi) {
        http_response_code(404);
        echo json_encode(['error' => 'Potensi tidak ditemukan']);
        exit;
    }

    $oldNik = trim((string) $potensi->nik);
    $submittedNik = isset($input['nik']) ? trim((string) $input['nik']) : $oldNik;
    $newNik = isset($input['new_nik']) && trim((string) $input['new_nik']) !== ''
        ? trim((string) $input['new_nik'])
        : $submittedNik;
    $useTransaction = $newNik !== $oldNik;

    if ($newNik === '') {
        http_response_code(400);
        echo json_encode(['error' => 'NIK tidak boleh kosong']);
        exit;
    }

    if (preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $newNik) !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Format NIK tidak valid']);
        exit;
    }

    if ($useTransaction) {
        $pm->beginTransaction();
    }

    try {
        if ($useTransaction) {
            $updateDocSql = "UPDATE document SET nik = ? WHERE nik = ?";
            $dcm->execQuery($updateDocSql, [$newNik, $oldNik]);

            $updatePhotoSql = "UPDATE photo SET nik = ? WHERE nik = ?";
            $phm->execQuery($updatePhotoSql, [$newNik, $oldNik]);

            try {
                $updateFaceSql = "UPDATE face SET nik = ? WHERE person_id = ?";
                $fm->execQuery($updateFaceSql, [$newNik, $oldNik]);
            } catch (\Exception $e) {
                error_log("⚠️ Tidak ada data face potensi untuk diupdate: " . $e->getMessage());
            }

            $updatePathDoc = "UPDATE document SET file_path = REPLACE(file_path, ?, ?) WHERE nik = ?";
            $dcm->execQuery($updatePathDoc, ["person/$oldNik/", "person/$newNik/", $newNik]);

            $updatePathPhoto = "UPDATE photo SET photo_path = REPLACE(photo_path, ?, ?) WHERE nik = ?";
            $phm->execQuery($updatePathPhoto, ["person/$oldNik/", "person/$newNik/", $newNik]);
        }

        $activeNik = $newNik;

        $replacePhoto = function (
            $model,
            string $nik,
            string $base64,
            string $filename,
            string $path,
            string $type
        ) use ($fu) {
            if (empty($base64)) {
                return;
            }

            $purpose = $model instanceof PhotoModel
                ? FileUploadModel::PURPOSE_IMAGE
                : FileUploadModel::PURPOSE_DOCUMENT;
            $filedata = $fu->upload($base64, $filename, $path, true, true, $purpose);

            if (method_exists($model, 'deleteByType')) {
                $model->deleteByType($nik, $type);
            }

            $model->add(
                $nik,
                $filedata->filename,
                $filedata->path,
                $type,
                null,
                $filedata->extension
            );
        };

        if (!empty($input['photo_profile'])) {
            $replacePhoto(
                $phm,
                $activeNik,
                $input['photo_profile'],
                $activeNik . '.jpeg',
                'person/' . $activeNik . '/',
                PhotoModel::PHOTO_TYPE_BIOMETRIC
            );
        }

        if (!empty($input['photo_ktp'])) {
            $replacePhoto(
                $dcm,
                $activeNik,
                $input['photo_ktp'],
                'KTP_' . $activeNik . '.jpeg',
                'person/' . $activeNik . '/documents/',
                'KTP'
            );
        }

        if (!empty($input['photo_kk'])) {
            $replacePhoto(
                $dcm,
                $activeNik,
                $input['photo_kk'],
                'KK_' . $activeNik . '.jpeg',
                'person/' . $activeNik . '/documents/',
                'KK'
            );
        }

        $data = (object)[
            'id'            => $potensi->id,
            'nik'           => $newNik,
            'name'          => $input['name'] ?? null,
            'address'       => $input['address'] ?? null,
            'familycard_no' => $input['familycard_no'] ?? null,
            'village'       => $input['village'] ?? null,
            'phone'         => $input['phone'] ?? null,
            'luas_tanah'    => $input['luas_tanah'] ?? null,
            'luas_bangunan' => $input['luas_bangunan'] ?? null,
            'beneficiary_nik' => $normalizeNullable($input['beneficiary_nik'] ?? null),
            'beneficiary_familycard_no' => $normalizeNullable($input['beneficiary_familycard_no'] ?? null),
            'beneficiary_name' => $normalizeNullable($input['beneficiary_name'] ?? null),
            'beneficiary_address' => $normalizeNullable($input['beneficiary_address'] ?? null),
        ];

        $result = $pm->updateById($data);
        if (!$result) {
            throw new \Exception('Gagal mengupdate data potensi');
        }

        if ($useTransaction) {
            $pm->endTransaction();
        }

        if ($useTransaction) {
            $oldFolder = dirname(__FILE__, 3) . "/uploads/person/" . $oldNik;
            $newFolder = dirname(__FILE__, 3) . "/uploads/person/" . $newNik;
            if (file_exists($oldFolder)) {
                if (!@rename($oldFolder, $newFolder)) {
                    error_log("⚠️ Gagal rename folder potensi dari {$oldFolder} ke {$newFolder}");
                    @mkdir($newFolder, 0750, true);
                    foreach (glob($oldFolder . '/*') as $file) {
                        @rename($file, $newFolder . '/' . basename($file));
                    }
                    @rmdir($oldFolder);
                }
            }
        }
    } catch (\Exception $e) {
        if ($useTransaction) {
            $pm->rollbackTransaction();
        }
        http_response_code(500);
        echo json_encode(['error' => 'Gagal memperbarui NIK potensi.']);
        exit;
    }
    echo json_encode([
        'status'  => 'SUCCESS',
        'message' => 'Data potensi berhasil diupdate',
        'data' => [
            'nik' => $newNik,
            'potensi_id' => $potensi->id,
        ],
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => biometricPublicExceptionMessage($e, 'Gagal memperbarui data potensi.')]);
}
