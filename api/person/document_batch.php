<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/DocumentModel.php");

use biometric\src\core\models\DocumentModel;

$requestBody = file_get_contents("php://input");
$json = json_decode($requestBody, true);

$nikList = $json["nik_list"] ?? [];

if (empty($nikList)) {
    echo json_encode([
        "status" => "error",
        "message" => "nik_list is required"
    ]);
    exit;
}

try {
    $dm = new DocumentModel();
    $documents = $dm->getByNikList($nikList);

    $result = [];

    foreach ($documents as $doc) {

        $nik = $doc["nik"];
        if (!isset($result[$nik])) {
            $result[$nik] = [];
        }

        $path = __DIR__ . "/../../" . $doc["file_path"];

        if (!file_exists($path)) {
            error_log("FILE NOT FOUND: $path");
            continue;
        }

        if (filesize($path) > 5 * 1024 * 1024) {
            error_log("FILE TOO LARGE: $path");
            continue;
        }

        $mime = mime_content_type($path);
        if (!in_array($mime, ["image/jpeg", "image/png"])) {
            error_log("INVALID MIME: $mime ($path)");
            continue;
        }

        $imageData = file_get_contents($path);
        $imageSrc = @imagecreatefromstring($imageData);

        if (!$imageSrc) {
            error_log("CORRUPTED IMAGE: $path");
            continue;
        }

        $maxWidth = 800;
        $width = imagesx($imageSrc);
        $height = imagesy($imageSrc);

        if ($width > $maxWidth) {
            $ratio = $height / $width;
            $newWidth = $maxWidth;
            $newHeight = $maxWidth * $ratio;

            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled(
                $newImg,
                $imageSrc,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );

            ob_start();
            imagejpeg($newImg, null, 60);
            $base64 = base64_encode(ob_get_clean());

            imagedestroy($newImg);
        } else {
            $base64 = base64_encode($imageData);
        }

        imagedestroy($imageSrc);

        // --- 6. RETURN DATA ---
        $result[$nik][$doc["type"]] = [
            "file_path" => $doc["file_path"],
            "base64" => "data:image/jpeg;base64," . $base64
        ];
    }


    echo json_encode([
        "status" => "success",
        "data"   => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
