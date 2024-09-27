<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../Database.php");

class PhotoModel {
    private $db;

    public function __construct(){
        $this->db = new Database();
    }
    
    public function get(string $nik): array{
        $photos = $this->db->query("select * from photo where nik = '$nik'");

        return $photos;
    }
    
    public function add(string $nik, string $filename, string $savepath, string $description = null){
        $res = $this->db->execute("
            insert into photo(
                nik,
                filename,
                photo_path,
                type,
                description
            )
            values(
                '$nik',
                '$filename',
                '$savepath',
                'biometric',
                '$description'
            )
        ");

        return $res;
    }
}