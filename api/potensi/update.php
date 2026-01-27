<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");

use biometric\src\core\models\PotensiModel;

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

try {
    if (!$pm->exists($input['nik'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Potensi tidak ditemukan']);
        exit;
    }

    $data = (object)[
        'nik'           => $input['nik'],
        'name'          => $input['name'] ?? null,
        'address'       => $input['address'] ?? null,
        'familycard_no' => $input['familycard_no'] ?? null,
        'village'       => $input['village'] ?? null,
        'phone'         => $input['phone'] ?? null,
        'luas_tanah'    => $input['luas_tanah'] ?? null,
        'luas_bangunan' => $input['luas_bangunan'] ?? null,
    ];

    $result = $pm->update($data);

    if ($result) {
        echo json_encode([
            'status'  => 'SUCCESS',
            'message' => 'Data potensi berhasil diupdate'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal mengupdate data']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
