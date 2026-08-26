<?php

namespace biometric\src\core\models;

require_once(dirname(__FILE__) . "/EnvFileModel.php");
require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/../../utils/Helper.php");


use biometric\src\core\Database;
use biometric\src\core\utils\Helper;
use SplFileInfo;
use stdClass;

class FileUploadModel
{
    public const PURPOSE_IMAGE = 'image';
    public const PURPOSE_DOCUMENT = 'document';
    public const PURPOSE_SHAPEFILE = 'shapefile';

    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;
    private const MAX_DOCUMENT_BYTES = 20 * 1024 * 1024;
    private const MAX_SHAPEFILE_BYTES = 25 * 1024 * 1024;
    private const MAX_IMAGE_PIXELS = 60000000;

    private $env;
    private $basePath;
    private $uploadDir;
    private $db;

    public function __construct()
    {
        $this->env = new EnvFileModel();

        $this->basePath = dirname(__FILE__) . '/../../../';
        $this->uploadDir = Helper::removesTrailingSlash($this->env->get('BIOMETRIC_UPLOAD_DIR'));

        try {
            $database = new Database();
            $this->db = $database->getConnection();

            if (!$this->db || !($this->db instanceof \mysqli)) {
                throw new \Exception("Database connection invalid or not instance of mysqli.");
            }

            // mysqli::ping() is deprecated in PHP 8.4
            // Connection is validated by the checks above
        } catch (\Exception $e) {
            error_log('[biometric] FileUploadModel database connection failed');
            throw new \RuntimeException('File service is unavailable');
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                throw new \InvalidArgumentException('Invalid storage path');
            }
            $segments[] = $segment;
        }
        return implode('/', $segments);
    }

    private function normalizeFilename(string $filename, string $extension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new \InvalidArgumentException('Invalid filename');
        }

        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9_-]+/', '-', $stem);
        $stem = trim((string) $stem, '-_');
        if ($stem === '') {
            try {
                $stem = 'upload-' . bin2hex(random_bytes(12));
            } catch (\Throwable $exception) {
                $stem = 'upload-' . str_replace('.', '', uniqid('', true));
            }
        }

        return substr($stem, 0, 140) . '.' . $extension;
    }

    private function uploadProfile(string $purpose): array
    {
        $profiles = [
            self::PURPOSE_IMAGE => [
                'max_bytes' => self::MAX_IMAGE_BYTES,
                'mime_extensions' => [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                ],
            ],
            self::PURPOSE_DOCUMENT => [
                'max_bytes' => self::MAX_DOCUMENT_BYTES,
                'mime_extensions' => [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                ],
            ],
            self::PURPOSE_SHAPEFILE => [
                'max_bytes' => self::MAX_SHAPEFILE_BYTES,
                'mime_extensions' => [
                    'application/zip' => 'zip',
                ],
            ],
        ];

        if (!isset($profiles[$purpose])) {
            throw new \InvalidArgumentException('Unsupported upload purpose');
        }

        return $profiles[$purpose];
    }

    private function normalizeDetectedMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'application/x-pdf' => 'application/pdf',
            'application/x-zip' => 'application/zip',
            'application/x-zip-compressed' => 'application/zip',
        ];

        return $aliases[$mime] ?? $mime;
    }

    private function detectMime(?string $contents, ?string $temporaryPath): string
    {
        if (!class_exists('finfo')) {
            throw new \RuntimeException('File type validation is unavailable');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $contents !== null
            ? $finfo->buffer($contents)
            : $finfo->file((string) $temporaryPath);

        if (!is_string($detected) || $detected === '') {
            throw new \InvalidArgumentException('Unable to determine uploaded file type');
        }

        return $this->normalizeDetectedMime($detected);
    }

    private function validateFileSignature(
        string $mime,
        ?string $contents,
        ?string $temporaryPath
    ): void {
        if (strpos($mime, 'image/') === 0) {
            $imageInfo = $contents !== null
                ? @getimagesizefromstring($contents)
                : @getimagesize((string) $temporaryPath);
            if (
                !is_array($imageInfo) ||
                empty($imageInfo[0]) ||
                empty($imageInfo[1]) ||
                ((int) $imageInfo[0] * (int) $imageInfo[1]) > self::MAX_IMAGE_PIXELS
            ) {
                throw new \InvalidArgumentException('Invalid or unsafe image file');
            }
            return;
        }

        $prefix = $contents !== null
            ? substr($contents, 0, 8)
            : (string) @file_get_contents((string) $temporaryPath, false, null, 0, 8);

        if ($mime === 'application/pdf' && strncmp($prefix, '%PDF-', 5) !== 0) {
            throw new \InvalidArgumentException('Invalid PDF file signature');
        }

        if (
            $mime === 'application/zip' &&
            !in_array(substr($prefix, 0, 4), ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)
        ) {
            throw new \InvalidArgumentException('Invalid ZIP file signature');
        }
    }

    private function validateUpload(
        string $purpose,
        int $size,
        ?string $contents,
        ?string $temporaryPath,
        ?string $declaredMime = null
    ): array {
        $profile = $this->uploadProfile($purpose);
        if ($size <= 0) {
            throw new \InvalidArgumentException('Uploaded file is empty');
        }
        if ($size > (int) $profile['max_bytes']) {
            throw new \LengthException('Uploaded file exceeds the allowed size');
        }

        $mime = $this->detectMime($contents, $temporaryPath);
        $mimeExtensions = $profile['mime_extensions'];
        if (!isset($mimeExtensions[$mime])) {
            throw new \InvalidArgumentException('Unsupported file type');
        }

        if ($declaredMime !== null) {
            $declaredMime = $this->normalizeDetectedMime($declaredMime);
            if ($declaredMime !== $mime) {
                throw new \InvalidArgumentException('Declared file type does not match file content');
            }
        }

        $this->validateFileSignature($mime, $contents, $temporaryPath);

        return ['mime' => $mime, 'extension' => $mimeExtensions[$mime]];
    }

    private function storeAtomically(
        string $targetDirectory,
        string $targetFile,
        ?string $contents,
        ?string $uploadedTemporaryPath
    ): void {
        $temporaryFile = tempnam($targetDirectory, '.upload-');
        if (!is_string($temporaryFile)) {
            throw new \RuntimeException('Unable to prepare temporary storage');
        }

        try {
            if ($contents !== null) {
                if (file_put_contents($temporaryFile, $contents, LOCK_EX) === false) {
                    throw new \RuntimeException('Unable to store uploaded file');
                }
            } elseif (!move_uploaded_file((string) $uploadedTemporaryPath, $temporaryFile)) {
                throw new \RuntimeException('Unable to store uploaded file');
            }

            @chmod($temporaryFile, 0640);
            if (!rename($temporaryFile, $targetFile)) {
                throw new \RuntimeException('Unable to finalize uploaded file');
            }
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function absoluteStoragePath(string $relativePath): string
    {
        $normalized = $this->normalizeRelativePath($relativePath);
        $uploadRoot = $this->normalizeRelativePath($this->uploadDir);
        if (
            $normalized !== $uploadRoot &&
            strpos($normalized, $uploadRoot . '/') !== 0
        ) {
            throw new \InvalidArgumentException('Storage path is outside upload directory');
        }
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    }

    public function upload(
        $files,
        ?string $filename = null,
        ?string $path = null,
        bool $overwrite = false,
        bool $is_base64 = false,
        string $purpose = self::PURPOSE_DOCUMENT
    ): stdClass {
        $savePath = $this->normalizeRelativePath($this->uploadDir);

        if (!empty($path)) {
            $savePath = $this->normalizeRelativePath($savePath . '/' . $path);
        }

        // echo "<b>File to be uploaded: </b>" . $files["name"] . "<br>";
        // echo "<b>Type: </b>" . $files["type"] . "<br>";
        // echo "<b>File Size: </b>" . $files["size"]/1024 . "<br>";
        // echo "<b>Store in: </b>" . $files["tmp_name"] . "<br>";

        $targetPath = $this->absoluteStoragePath($savePath);
        if (!file_exists($targetPath)) {
            if (!mkdir($targetPath, 0750, true) && !is_dir($targetPath)) {
                throw new \RuntimeException('Unable to prepare storage directory');
            }
        }

        $base64decoded = null;
        $temporaryPath = null;
        $declaredMime = null;
        if ($is_base64) {
            if (empty($filename)) {
                throw new \Exception('Filename cannot be empty for base64-based file transfer');
            }

            if (!is_string($files) || preg_match('/^data:([^;,]+);base64,(.+)$/s', $files, $matches) !== 1) {
                throw new \InvalidArgumentException('Invalid base64 file payload');
            }
            $declaredMime = strtolower(trim($matches[1]));
            $base64decoded = base64_decode($matches[2], true);
            if (!is_string($base64decoded)) {
                throw new \InvalidArgumentException('Invalid base64 encoding');
            }
            $validation = $this->validateUpload(
                $purpose,
                strlen($base64decoded),
                $base64decoded,
                null,
                $declaredMime
            );
        } else {
            if (empty($files)) {
                throw new \Exception('Blob file cannot be empty for base64-based file transfer');
            }
            if (empty($filename)) {
                $filename = $files["name"];
            }
            if (!is_uploaded_file($files['tmp_name'] ?? '')) {
                throw new \InvalidArgumentException('Invalid uploaded file');
            }
            if ((int) ($files['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Upload transfer failed');
            }
            $temporaryPath = (string) $files['tmp_name'];
            $validation = $this->validateUpload(
                $purpose,
                (int) ($files['size'] ?? 0),
                null,
                $temporaryPath
            );
        }

        $extension = (string) $validation['extension'];
        $filename = $this->normalizeFilename((string) $filename, $extension);
        $filePath = $targetPath . DIRECTORY_SEPARATOR . $filename;

        $status = 'success';

        if (file_exists($filePath)) {
            if (!$overwrite) {
                $status = 'File already exists';
                return json_decode(json_encode([
                    'status' => $status,
                    'filename' => $filename,
                    'extension' => $extension,
                    'path' => $savePath . '/' . $filename
                ]));
            }
        }

        $this->storeAtomically($targetPath, $filePath, $base64decoded, $temporaryPath);

        return json_decode(json_encode([
            'status' => $status,
            'filename' => $filename,
            'extension' => $extension,
            'path' => $savePath . '/' . $filename
        ]));
    }

    public function deleteFile(string $filepath)
    {
        $targetPath = $this->absoluteStoragePath($filepath);
        if (is_file($targetPath) && !unlink($targetPath)) {
            throw new \RuntimeException('Unable to delete stored file');
        }
    }

    public function fileExists(string $filepath): bool
    {
        $loadPath = $this->absoluteStoragePath($filepath);

        return is_file($loadPath);
    }

    public function downloadFile(string $filename, string $filepath)
    {
        $loadPath = $this->absoluteStoragePath($filepath);

        if (!is_file($loadPath)) {
            throw new \Exception("File $filepath does not exists");
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filename = $this->normalizeFilename($filename, $extension !== '' ? $extension : 'bin');
        $types = [
            'zip' => 'application/zip',
            'txt' => 'text/plain; charset=utf-8',
            'pdf' => 'application/pdf',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $type = $types[$extension] ?? 'application/octet-stream';

        header("Content-Description: File Transfer");
        header("Content-Type: $type");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
        header("Content-Length: " . filesize($loadPath));

        readfile($loadPath);
    }

    public function getBase64String(string $filename, string $filepath)
    {
        $loadPath = $this->absoluteStoragePath($filepath);

        if (!is_file($loadPath)) {
            throw new \Exception("File $filepath does not exists");
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $types = [
            'pdf' => 'application/pdf',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $type = $types[$extension] ?? 'application/octet-stream';
        $data = file_get_contents($loadPath);
        $base64 = 'data:' . $type . ';base64,' . base64_encode($data);

        return $base64;
    }

   public function listDocuments($nik)
{
    if (!$this->db || !($this->db instanceof \mysqli)) {
        throw new \Exception("Database connection not available in listDocuments()");
    }

    $statement = $this->db->prepare(
        'SELECT nik, filename, type, file_path FROM document WHERE nik = ?'
    );
    $statement->bind_param('s', $nik);
    $statement->execute();
    $result = $statement->get_result();

    if (!$result) {
        throw new \Exception("Query failed: " . $this->db->error);
    }

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    return $documents;
}

    public function listPhotos($nik)
    {

        if (!$this->db || !($this->db instanceof \mysqli)) {
        throw new \Exception("Database connection not available in listDocuments()");
    }
        $statement = $this->db->prepare(
            'SELECT nik, filename, type, photo_path FROM photo WHERE nik = ?'
        );
        $statement->bind_param('s', $nik);
        $statement->execute();
        $result = $statement->get_result();

          if (!$result) {
        throw new \Exception("Query failed: " . $this->db->error);
    }
        $photos = [];
        while ($row = $result->fetch_assoc()) {
            $photos[] = $row;
        }
        return $photos;
    }
}
