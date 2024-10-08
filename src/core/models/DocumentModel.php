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
                doc.nik,
                doc.filename,
                doc.extension,
                doc.`type` as `type_id`,
                mdt.name as `type`,
                doc.description,
                doc.file_path,
                doc.file_blob,
                doc.created_at,
                doc.updated_at 
            from 
                document doc
                left join master_doc_type mdt on doc.`type` = mdt.id
            where 
                nik = '$nik'
        ");
        // $docs = $this->query("
        //     select 
        //         doc.*
        //     from 
        //         document doc
        //     where 
        //         nik = '$nik'
        // ");

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