<?php
require_once(dirname(__FILE__) . "/../../src/core/Database.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PersonModel.php");
require_once(dirname(__FILE__) . "/../_api_header.php");

use biometric\src\core\Database;
use biometric\src\core\models\PotensiModel;
use biometric\src\core\models\PersonModel;

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['potensi_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'potensi_id wajib']);
    exit;
}

$db = (new Database())->getConnection();
$potensiModel = new PotensiModel();
$personModel  = new PersonModel();

try {
    $db->begin_transaction();

    $potensi = $potensiModel->getById((int)$input['potensi_id']);
    if (!$potensi) {
        throw new Exception("Potensi tidak ditemukan atau sudah diproses");
    }

    $personModel->add((object)[
        'nik'           => $potensi->nik,
        'name'          => $potensi->name,
        'address'       => $potensi->address,
        'familycard_no' => $potensi->familycard_no,
        'village'       => $potensi->village,
        'phone'         => $potensi->phone,
        'sk_number'     => $potensi->sk_number,
        'luas_tanah'    => $potensi->luas_tanah,
        'luas_bangunan' => $potensi->luas_bangunan,
    ]);

    $potensiModel->markAsApproved($potensi->id);

    $db->commit();

    echo json_encode([
        'status'  => 'SUCCESS',
        'nik'     => $potensi->nik,
        'name'    => $potensi->name,
        'message' => 'Berhasil terdaftar ke SK'
    ]);
    exit;

} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
