<?php
namespace biometric\src\core\models;

require_once(dirname(__FILE__)."/EnvFileModel.php");
require_once(dirname(__FILE__)."/../../utils/Helper.php");

use biometric\src\core\utils\Helper;
use stdClass;

class FileUploadModel {
    private $env;
    private $basePath;
    private $uploadDir;

    public function __construct(){
        $this->env = new EnvFileModel();

        $this->basePath = dirname(__FILE__).'/../../../';
        $this->uploadDir = Helper::removesTrailingSlash($this->env->get('BIOMETRIC_UPLOAD_DIR'));
    }

    public function upload(array $files, string $filename = null, string $path = null): stdClass{
        $savePath = $this->uploadDir;

        if(!empty($path)){
            $savePath = Helper::removesTrailingSlash($savePath.'/'.$path);
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

    public function downloadFile(string $filename, string $filepath){
        $loadPath = $this->basePath.$filepath;

        if(!is_file($loadPath)){
            throw new \Exception("File $filepath does not exists");
        }

        $ext = strrchr($filename, ".");
        $type = '';
        switch($ext){
            case ".zip": $type = "application/zip"; break;
            case ".txt": $type = "text/plain"; break;
            case ".pdf": $type = "application/pdf"; break;
            case('gif') : $type = "image/gif";break;
            case('pnggif') : $type = "image/png";break;
            case('jpg') : $type = "image/jpeg";break;
            default: $type = "application/octet-stream"; break;
        }

        header("Content-Description: File Transfer");
        header("Content-Type: $type");
        header("Content-Transfer-Encoding: binary");
        header("Content-disposition: attachment; filename=$filename");
        header("Content-Length: ".filesize($loadPath));

        echo file_get_contents($loadPath);
    }

    public function getBase64String(string $filename, string $filepath){
        $loadPath = $this->basePath.$filepath;

        if(!is_file($loadPath)){
            throw new \Exception("File $filepath does not exists");
        }

        $ext = strrchr($filename, ".");
        $type = '';
        switch($ext){
            case ".zip": $type = "application/zip"; break;
            case ".txt": $type = "text/plain"; break;
            case ".pdf": $type = "application/pdf"; break;
            case('gif') : $type = "image/gif";break;
            case('pnggif') : $type = "image/png";break;
            case('jpg') : $type = "image/jpeg";break;
            default: $type = "application/octet-stream"; break;
        }
        
        $type = pathinfo($loadPath, PATHINFO_EXTENSION);
        $data = file_get_contents($loadPath);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return $base64;
    }
}
