<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../Database.php");

class DocumentModel extends Database {
    const DOCUMENT_TYPE_DOCUMENT = 'document';

    public function __construct(){
        parent::__construct();
    }

    public function get(string $nik): array{
        $docs = $this->query("
            select 
                * 
            from 
                document 
                left join 
            where 
                nik = '$nik'
        ");

        return $docs;
    }
    
    public function add(
        string $nik, 
        string $filename, 
        string $savepath, 
        string $documentType = self::DOCUMENT_TYPE_DOCUMENT, 
        string $description = null,
        string $extension = null
    ){
        $res = $this->execute("
            insert into document(
                nik,
                filename,
                type,
                description,
                file_path
                ".(!empty($extension)? ",extension": "")."
            )
            values(
                '$nik',
                '$filename',
                '$documentType',
                '$description',
                '$savepath'
                ".(!empty($extension)? ",'$extension'": "")."
            )
        ");

        return $res;
    }
}