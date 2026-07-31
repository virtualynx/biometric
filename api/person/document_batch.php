<?php
require_once(dirname(__FILE__) . "/../_api_header.php");
require_once(dirname(__FILE__) . "/../../src/core/models/DocumentModel.php");

use biometric\src\core\models\DocumentModel;

function documentBatchMemoryLimitBytes(): ?int
{
    $memoryLimit = trim((string) ini_get("memory_limit"));
    if ($memoryLimit === "" || $memoryLimit === "-1") {
        return null;
    }

    $unit = strtolower(substr($memoryLimit, -1));
    $value = (float) $memoryLimit;
    $multipliers = [
        "g" => 1024 ** 3,
        "m" => 1024 ** 2,
        "k" => 1024
    ];

    return (int) round($value * ($multipliers[$unit] ?? 1));
}

function documentBatchHasImageMemory(
    int $width,
    int $height,
    bool $requiresRotation
): bool {
    $memoryLimit = documentBatchMemoryLimitBytes();
    if ($memoryLimit === null) {
        return true;
    }

    $sourceEstimate = $width * $height * 6;
    $previewEstimate = 1600 * 1200 * 6 * ($requiresRotation ? 2 : 1);
    $safetyBuffer = 8 * 1024 * 1024;
    $estimatedPeak = memory_get_usage(true)
        + $sourceEstimate
        + $previewEstimate
        + $safetyBuffer;

    return $estimatedPeak < ($memoryLimit * 0.9);
}

$requestBody = file_get_contents("php://input");
$json = json_decode($requestBody, true);

$nikList = is_array($json) ? ($json["nik_list"] ?? []) : [];

if (is_array($nikList)) {
    $nikList = array_values(array_unique(array_filter(
        array_map(
            static fn($nik) => is_scalar($nik) ? trim((string) $nik) : "",
            $nikList
        ),
        static fn($nik) => $nik !== ""
    )));
}

if (!is_array($nikList) || empty($nikList)) {
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

        if (!is_file($path)) {
            error_log("FILE NOT FOUND: $path");
            continue;
        }

        if (filesize($path) > 12 * 1024 * 1024) {
            error_log("FILE TOO LARGE: $path");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "status" => "too_large"
            ];
            continue;
        }

        $mime = mime_content_type($path);
        if ($mime === "application/pdf") {
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "pdf"
            ];
            continue;
        }

        if (!in_array($mime, ["image/jpeg", "image/png"], true)) {
            error_log("INVALID MIME: $mime ($path)");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "unsupported"
            ];
            continue;
        }

        $imageData = @file_get_contents($path);
        if ($imageData === false) {
            error_log("FAILED TO READ IMAGE: $path");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        $imageInfo = @getimagesizefromstring($imageData);

        if (
            !$imageInfo ||
            empty($imageInfo[0]) ||
            empty($imageInfo[1]) ||
            ($imageInfo[0] * $imageInfo[1]) > 60000000
        ) {
            error_log("INVALID OR UNSAFE IMAGE DIMENSIONS: $path");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        $orientation = 1;
        if ($mime === "image/jpeg" && function_exists("exif_read_data")) {
            $exifData = @exif_read_data($path);
            $orientation = is_array($exifData)
                ? (int) ($exifData["Orientation"] ?? 1)
                : 1;
        }

        $rotation = [
            3 => 180,
            6 => -90,
            8 => 90
        ][$orientation] ?? 0;
        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];

        if (!documentBatchHasImageMemory(
            $sourceWidth,
            $sourceHeight,
            $rotation !== 0
        )) {
            error_log("INSUFFICIENT MEMORY FOR IMAGE PREVIEW: $path");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "too_large"
            ];
            continue;
        }

        $imageSrc = @imagecreatefromstring($imageData);
        unset($imageData);

        if (!$imageSrc) {
            error_log("CORRUPTED IMAGE: $path");
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        $maxWidth = 1600;
        $maxHeight = 1200;
        $width = imagesx($imageSrc);
        $height = imagesy($imageSrc);
        $isQuarterTurn = in_array($orientation, [6, 8], true);
        $displayWidth = $isQuarterTurn ? $height : $width;
        $displayHeight = $isQuarterTurn ? $width : $height;
        $scale = min($maxWidth / $displayWidth, $maxHeight / $displayHeight, 1);
        $targetDisplayWidth = max(1, (int) round($displayWidth * $scale));
        $targetDisplayHeight = max(1, (int) round($displayHeight * $scale));
        $resampleWidth = $isQuarterTurn
            ? $targetDisplayHeight
            : $targetDisplayWidth;
        $resampleHeight = $isQuarterTurn
            ? $targetDisplayWidth
            : $targetDisplayHeight;

        $previewImage = imagecreatetruecolor($resampleWidth, $resampleHeight);
        if ($previewImage === false) {
            imagedestroy($imageSrc);
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        $white = imagecolorallocate($previewImage, 255, 255, 255);
        imagefilledrectangle(
            $previewImage,
            0,
            0,
            $resampleWidth,
            $resampleHeight,
            $white
        );
        imagealphablending($previewImage, true);

        $resampled = imagecopyresampled(
            $previewImage,
            $imageSrc,
            0,
            0,
            0,
            0,
            $resampleWidth,
            $resampleHeight,
            $width,
            $height
        );
        imagedestroy($imageSrc);

        if (!$resampled) {
            imagedestroy($previewImage);
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        if ($rotation !== 0) {
            $rotatedImage = imagerotate($previewImage, $rotation, $white);
            imagedestroy($previewImage);

            if ($rotatedImage === false) {
                $result[$nik][$doc["type"]] = [
                    "file_path" => $doc["file_path"],
                    "mime_type" => $mime,
                    "status" => "invalid"
                ];
                continue;
            }

            $previewImage = $rotatedImage;
        }

        ob_start();
        $encoded = imagejpeg($previewImage, null, 90);
        $encodedImage = ob_get_clean();
        imagedestroy($previewImage);

        if (!$encoded || $encodedImage === false || $encodedImage === "") {
            $result[$nik][$doc["type"]] = [
                "file_path" => $doc["file_path"],
                "mime_type" => $mime,
                "status" => "invalid"
            ];
            continue;
        }

        $base64 = base64_encode($encodedImage);
        unset($encodedImage);

        $result[$nik][$doc["type"]] = [
            "file_path" => $doc["file_path"],
            "mime_type" => "image/jpeg",
            "source_mime_type" => $mime,
            "status" => "ready",
            "base64" => "data:image/jpeg;base64," . $base64
        ];
    }


    echo json_encode([
        "status" => "success",
        "data"   => (object) $result
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
