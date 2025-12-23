<?php
require_once(dirname(__FILE__) . "/../../src/utils/Helper.php");
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/DocumentModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PhotoModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/QueueModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FaceModel.php");
require_once(dirname(__FILE__) . "/../../src/core/Database.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");

use biometric\src\core\Database;
use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\models\FaceModel;
use biometric\src\core\utils\Helper;
use biometric\src\core\models\PotensiModel;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    $input = $_POST;
}

if (empty($input)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No input data received', 'raw' => $raw]);
    exit;
}

if (empty($input['nik']) || empty($input['sk_number'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Missing NIK or sk_number',
        'received_keys' => array_keys($input)
    ]);
    exit;
}

$dbInstance = new Database();
$db = $dbInstance->getConnection();

$potensiModel = new PotensiModel();

$exists = $potensiModel->exists($input['nik']);

if (!$exists) {
    $potensiModel->add((object)[
        'nik'           => $input['nik'],
        'name'          => $input['name'] ?? null,
        'address'       => $input['address'] ?? null,
        'familycard_no' => $input['familycard_no'] ?? null,
        'village'       => $input['village'] ?? null,
        'phone'         => $input['phone'] ?? null,
        'sk_number'     => $input['sk_number'] ?? null,
        'luas_tanah'    => !empty($input['luas_tanah']) ? floatval($input['luas_tanah']) : null,
        'luas_bangunan' => !empty($input['luas_bangunan']) ? floatval($input['luas_bangunan']) : null,
    ]);
} else {
    $potensiModel->update((object)[
        'nik'           => $input['nik'],
        'name'          => $input['name'] ?? null,
        'address'       => $input['address'] ?? null,
        'familycard_no' => $input['familycard_no'] ?? null,
        'village'       => $input['village'] ?? null,
        'phone'         => $input['phone'] ?? null,
        'luas_tanah'    => !empty($input['luas_tanah']) ? floatval($input['luas_tanah']) : null,
        'luas_bangunan' => !empty($input['luas_bangunan']) ? floatval($input['luas_bangunan']) : null,
    ]);
}


$fu = new FileUploadModel();
$phm = new PhotoModel();
$fm = new FaceModel();

function saveBase64PhotoToTable(string $base64, string $nik, string $type, $fu, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        [$meta, $data] = explode(';base64,', $base64);
    } else {
        $meta = 'data:image/png';
        $data = $base64;
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:image\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    if ($type === 'biometric') {
        $filename = "{$nik}.{$ext}";
        $path = "person/{$nik}";
    } else {
        $filename = "{$type}_" . uniqid() . ".{$ext}";
        $path = "person/{$nik}/photos";
    }

    $result = $fu->upload($base64, $filename, $path, true, true);

    $desc = ucfirst($type) . " photo";

    $stmt = $db->prepare("
        INSERT INTO photo (nik, filename, extension, type, description, photo_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if (method_exists($stmt, 'bind_param')) {
        $stmt->bind_param("ssssss", $nik, $filename, $ext, $type, $desc, $result->path);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt->execute([$nik, $filename, $ext, $type, $desc, $result->path]);
    }

    return $result->path;
}


function saveBase64DocumentToTable(string $base64, string $nik, string $doctype, $fu, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        [$meta, $data] = explode(';base64,', $base64);
    } else {
        $meta = 'data:application/pdf';
        $data = $base64;
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:(image|application)\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = strtolower($m[2]);
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    $filename = "{$doctype}_" . uniqid() . ".{$ext}";
    $path = "person/{$nik}/documents";

    $result = $fu->upload($base64, $filename, $path, true, true);

    $desc = strtoupper($doctype) . " document";
    $stmt = $db->prepare("INSERT INTO document (nik, filename, extension, type, description, file_path, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $nik, $filename, $ext, $doctype, $desc, $result->path);
    $stmt->execute();
    $stmt->close();

    return $result->path;
}


function saveBase64PhotoToTableWithDesc(string $base64, string $nik, string $type, string $desc, $fu, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        [$meta, $data] = explode(';base64,', $base64);
    } else {
        $meta = 'data:image/png';
        $data = $base64;
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:image\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    $filename = "{$type}_" . uniqid() . ".{$ext}";
    $path = "person/{$nik}/photos";

    $result = $fu->upload($base64, $filename, $path, true, true);

    $stmt = $db->prepare("INSERT INTO photo (nik, filename, extension, type, description, photo_path, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $nik, $filename, $ext, $type, $desc, $result->path);
    $stmt->execute();
    $stmt->close();

    return $result->path;
}


if (!empty($input['photo_profile'])) {
    saveBase64PhotoToTable($input['photo_profile'], $input['nik'], "biometric", $fu, $db);
}

if (!empty($input['photo_ktp'])) {
    saveBase64DocumentToTable($input['photo_ktp'], $input['nik'], "KTP", $fu, $db);
}

if (!empty($input['photo_kk'])) {
    saveBase64DocumentToTable($input['photo_kk'], $input['nik'], "KK", $fu, $db);
}
if (!empty($input['photos']) && is_array($input['photos'])) {
    foreach ($input['photos'] as $photoItem) {
        if (is_array($photoItem) && !empty($photoItem['base64'])) {
            $base64 = $photoItem['base64'];
            $desc = $photoItem['description'] ?? 'Documentation photo';
            saveBase64PhotoToTableWithDesc($base64, $input['nik'], "documentation", $desc, $fu, $db);
        }
    }
}

if (!empty($input['face_encoding']) && is_array($input['face_encoding'])) {
    $encoding_json = json_encode($input['face_encoding'], JSON_UNESCAPED_SLASHES);
    try {
        $fm->enroll($input['nik'], FaceModel::ID_TYPE_NIK, $encoding_json);
    } catch (\Exception $e) {
        error_log("Face enroll failed for {$input['nik']}: " . $e->getMessage());
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'POTENSI',
    'nik' => $input['nik'],
    'name'    => $input['name'] ?? null,
    'message' => 'Data berhasil disimpan sebagai Potensi'
]);
exit;
