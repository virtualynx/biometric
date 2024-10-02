<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../Database.php");
require_once(dirname(__FILE__)."/FileUploadModel.php");

class PhotoModel extends Database {
    const PHOTO_TYPE_BIOMETRIC = 'biometric';
    const PHOTO_TYPE_DOCUMENTATION = 'documentation';

    public function __construct(){
        parent::__construct();
    }
    
    public function get(string $nik, string $filename = null): array{
        $where_filename = '';

        if(!empty($filename)){
            $where_filename = " and filename = '$filename'";
        }

        $photos = $this->query("
            select * 
            from photo 
            where 
                nik = '$nik'
                $where_filename
        ");

        return $photos;
    }
    
    public function add(
        string $nik, 
        string $filename, 
        string $savepath, 
        string $photoType = self::PHOTO_TYPE_BIOMETRIC, 
        string $description = null,
        string $extension = null
    ){
        $res = $this->execute("
            insert into photo(
                nik,
                filename,
                photo_path,
                type,
                description
                ".(!empty($extension)? ",extension": "")."
            )
            values(
                '$nik',
                '$filename',
                '$savepath',
                '$photoType',
                '$description'
                ".(!empty($extension)? ",'$extension'": "")."
            )
        ");

        return $res;
    }

    public function delete(string $nik, string $filename){
        $res = $this->execute("delete from photo where nik = '$nik' and filename = '$filename'");

        return $res;
    }
}