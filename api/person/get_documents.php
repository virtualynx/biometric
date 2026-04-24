<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/FileUploadModel.php");

use biometric\src\core\models\FileUploadModel;

if (empty($_POST['nik'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing NIK']);
    exit;
}

try {
    $fum = new FileUploadModel();

    $documents = $fum->listDocuments($_POST['nik']);
    $photos = $fum->listPhotos($_POST['nik']);

    $filteredDocuments = [];
    foreach ($documents as $doc) {
        if (empty($doc['file_path']) || !$fum->fileExists($doc['file_path'])) {
            continue;
        }

        $filteredDocuments[] = [
            'nik' => $doc['nik'],
            'filename' => $doc['filename'],
            'type' => $doc['type']
        ];
    }

    $filteredPhotos = [];
    foreach ($photos as $photo) {
        if (
            $photo['type'] === 'biometric' &&
            !empty($photo['photo_path']) &&
            $fum->fileExists($photo['photo_path'])
        ) {
            $filteredPhotos[] = [
                'nik' => $photo['nik'],
                'filename' => $photo['filename'],
                'type' => $photo['type']
            ];
        }
    }

    $types = array_column($filteredDocuments, 'type');
    $hasKTP = in_array('KTP', $types);
    $hasKK = in_array('KK', $types);
    $hasBio = !empty($filteredPhotos);

    $statusMessages = [
        'KTP' => $hasKTP ? 'Sudah diupload' : 'Belum diupload',
        'KK' => $hasKK ? 'Sudah diupload' : 'Belum diupload',
        'Photo Profile' => $hasBio ? 'Sudah diupload' : 'Belum diupload'
    ];

    echo json_encode([
        'documents' => $filteredDocuments,
        'photos' => $filteredPhotos,
        'status' => $statusMessages
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
