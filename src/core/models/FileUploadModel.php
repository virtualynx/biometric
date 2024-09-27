<?php
namespace biometric\src\core\models;

use stdClass;

class FileUploadModel {
    private $env;
    private $basePath;
    private $uploadDir;

    public function __construct(){
        $this->env = new EnvFileModel();

        $this->basePath = dirname(__FILE__).'/../../../';
        $this->uploadDir = $this->env->get('BIOMETRIC_UPLOAD_DIR');
    }

    public function upload_array(array $filesArray, string $path = null): array{
        $result = [];



        return $result;
    }

    public function upload(array $files, string $filename = null, string $path = null): stdClass{
        $savePath = $this->uploadDir;
        if(substr_compare($savePath, '/', -strlen('/')) === 0){
            $savePath = substr($savePath, 0, strlen($savePath)-1);
        }

        if(!empty($path)){
            $savePath = $savePath.'/'.$path;
            if(substr_compare($savePath, '/', -strlen('/')) === 0){
                $savePath = substr($savePath, 0, strlen($savePath)-1);
            }
        }

        // echo "<b>File to be uploaded: </b>" . $files["name"] . "<br>";
        // echo "<b>Type: </b>" . $files["type"] . "<br>";
        // echo "<b>File Size: </b>" . $files["size"]/1024 . "<br>";
        // echo "<b>Store in: </b>" . $files["tmp_name"] . "<br>";

        $targetPath = $this->basePath.$savePath;
        if(!file_exists($targetPath)){
            mkdir($targetPath, 0777, true);
        }

        if(empty($filename)){
            $filename = $files["name"];
        }else{
            $exploded = explode(".", $files["name"]);
            $extension = end($exploded);
            $filename = $filename.'.'.$extension;
        }

        $filePath = $targetPath.'/'.$filename;

        $status = 'success';

        if(file_exists($filePath)){
            // echo "<h3>The file already exists</h3>";
            // throw new \Exception("File already exists");
            $status = 'File already exists';
        }

        move_uploaded_file($files["tmp_name"], $filePath);
        // echo "<h3>File Successfully Uploaded</h3>";

        return json_decode(json_encode([
            'status' => $status,
            'filename' => $filename,
            'path' => $savePath.'/'.$filename
        ]));
    }
}
