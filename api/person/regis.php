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

use biometric\src\core\Database;
use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\models\FaceModel;
use biometric\src\core\utils\Helper;

// --- INPUT HANDLING --- //
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

// If JSON decode fails or input isn't an array, fallback to $_POST (form-data)
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

$pm = new PersonModel();

try {
    $person = $pm->get($input['nik']);
} catch (\Exception $e) {
    if ($e->getMessage() != 'Data not found') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

if (empty($person)) {
    $person = json_decode(json_encode([
        'nik' => $input['nik'],
        'name' => $input['name'] ?? null,
        'address' => $input['address'] ?? null,
        'familycard_no' => $input['familycard_no'] ?? null,
        'village' => $input['village'] ?? null,
        'sk_number' => $input['sk_number'] ?? null,
        'phone' => $input['phone'] ?? null,
        'luas_tanah' => !empty($input['luas_tanah']) ? floatval($input['luas_tanah']) : null,
        'luas_bangunan' => !empty($input['luas_bangunan']) ? floatval($input['luas_bangunan']) : null,
        'beneficiary_nik' => $input['beneficiary_nik'] ?? null,
        'beneficiary_familycard_no' => $input['beneficiary_familycard_no'] ?? null,
        'beneficiary_name' => $input['beneficiary_name'] ?? null,
        'beneficiary_address' => $input['beneficiary_address'] ?? null
    ]));
    $pm->add($person);
} else {
    $person->name = $input['name'] ?? $person->name;
    $person->address = $input['address'] ?? $person->address;
    $person->familycard_no = $input['familycard_no'] ?? $person->familycard_no;
    $person->village = $input['village'] ?? $person->village;
    $person->phone = $input['phone'] ?? $person->phone;
    $person->sk_number = $input['sk_number'] ?? $person->sk_number;
    $person->luas_tanah = !empty($input['luas_tanah']) ? floatval($input['luas_tanah']) : null;
    $person->luas_bangunan = !empty($input['luas_bangunan']) ? floatval($input['luas_bangunan']) : null;
    $person->beneficiary_nik = $input['beneficiary_nik'] ?? null;
    $person->beneficiary_familycard_no = $input['beneficiary_familycard_no'] ?? null;
    $person->beneficiary_name = $input['beneficiary_name'] ?? null;
    $person->beneficiary_address = $input['beneficiary_address'] ?? null;

    $pm->update($person);
}

$fu = new FileUploadModel();
$phm = new PhotoModel();
$fm = new FaceModel();

function saveBase64PhotoToTable(string $base64, string $nik, string $type, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        $parts = explode(';base64,', $base64);
        $meta = $parts[0];
        $data = $parts[1];
    } else {
        $data = $base64;
        $meta = 'data:image/png';
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:image\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = $m[1];
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    $filename = $type . "_" . uniqid() . "." . $ext;
    $folder = "uploads/person/{$nik}/photos/";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $file_path = $folder . $filename;
    file_put_contents($file_path, $image_bin);

    $stmt = $db->prepare("INSERT INTO photo (nik, filename, extension, type, description, photo_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $desc = ucfirst($type) . " photo";
    $stmt->bind_param("ssssss", $nik, $filename, $ext, $type, $desc, $file_path);
    $stmt->execute();
    $stmt->close();

    return $file_path;
}

function saveBase64DocumentToTable(string $base64, string $nik, string $doctype, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        $parts = explode(';base64,', $base64);
        $meta = $parts[0];
        $data = $parts[1];
    } else {
        $data = $base64;
        $meta = 'data:image/png';
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:image\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = $m[1];
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    $filename = $doctype . "_" . uniqid() . "." . $ext;
    $folder = "uploads/person/{$nik}/documents/";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $file_path = $folder . $filename;
    file_put_contents($file_path, $image_bin);

    $stmt = $db->prepare("INSERT INTO document (nik, filename, extension, type, description, file_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $desc = strtoupper($doctype) . " document";
    $stmt->bind_param("ssssss", $nik, $filename, $ext, $doctype, $desc, $file_path);
    $stmt->execute();
    $stmt->close();

    return $file_path;
}

function saveBase64PhotoToTableWithDesc(string $base64, string $nik, string $type, string $desc, $db)
{
    if (empty($base64)) return null;

    if (strpos($base64, 'base64,') !== false) {
        $parts = explode(';base64,', $base64);
        $meta = $parts[0];
        $data = $parts[1];
    } else {
        $data = $base64;
        $meta = 'data:image/png';
    }

    $image_bin = base64_decode($data);
    if ($image_bin === false) return null;

    $ext = 'png';
    if (preg_match('/data:image\/([a-zA-Z0-9]+)/', $meta, $m)) {
        $ext = $m[1];
        if ($ext === 'jpeg') $ext = 'jpg';
    }

    $filename = $type . "_" . uniqid() . "." . $ext;
    $folder = "uploads/person/{$nik}/photos/";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $file_path = $folder . $filename;
    file_put_contents($file_path, $image_bin);

    $stmt = $db->prepare("INSERT INTO photo (nik, filename, extension, type, description, photo_path, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $nik, $filename, $ext, $type, $desc, $file_path);
    $stmt->execute();
    $stmt->close();

    return $file_path;
}


if (!empty($input['photo_profile'])) {
    saveBase64PhotoToTable($input['photo_profile'], $input['nik'], "biometric", $db);
}

if (!empty($input['photo_ktp'])) {
    saveBase64DocumentToTable($input['photo_ktp'], $input['nik'], "KTP", $db);
}

if (!empty($input['photo_kk'])) {
    saveBase64DocumentToTable($input['photo_kk'], $input['nik'], "KK", $db);
}
if (!empty($input['photos']) && is_array($input['photos'])) {
    foreach ($input['photos'] as $photoItem) {
        if (is_array($photoItem) && !empty($photoItem['base64'])) {
            $base64 = $photoItem['base64'];
            $desc = $photoItem['description'] ?? 'Documentation photo';
            saveBase64PhotoToTableWithDesc($base64, $input['nik'], "documentation", $desc, $db);
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

$person_arr = json_decode(json_encode($person), true);
$qm = new QueueModel();
$current_queue = $qm->findByNik($person->nik, [QueueModel::STATUS_PENDING, QueueModel::STATUS_PULLED]);

if (empty($current_queue)) {
    $current_queue = $qm->add('BMT', $person->nik);
}

$person_arr['queue'] = $current_queue;
$person = json_decode(json_encode($person_arr));

// --- RESPONSE --- //
header('Content-Type: application/json');
echo json_encode($person);
