<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../Database.php");

class DocumentModel {
    const DOCUMENT_TYPE_DOCUMENT = 'document';

    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    public function get(string $nik): array{
        $docs = $this->db->query("select * from document where nik = '$nik'");

        return $docs;
    }
    
    public function add(
        string $nik, 
        string $filename, 
        string $savepath, 
        string $documentType = self::DOCUMENT_TYPE_DOCUMENT, 
        string $description = null
    ){
        $res = $this->db->execute("
            insert into document(
                nik,
                filename,
                type,
                description,
                file_path
            )
            values(
                '$nik',
                '$filename',
                '$documentType',
                '$description',
                '$savepath'
            )
        ");

        return $res;
    }
}