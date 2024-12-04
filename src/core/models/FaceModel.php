<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../Database.php");

class FaceModel extends Database {
    const ID_TYPE_NIP = 'NIP';
    const ID_TYPE_NIK = 'NIK';
    const ID_TYPE_PHONE = 'PHONE';
    const ID_TYPE_EMAIL = 'EMAIL';

    public function __construct(){
        parent::__construct();
    }
    
    public function get(string $person_id): array{
        $faces = $this->query("
            select * 
            from face 
            where 
                person_id = '$person_id'
        ");

        return !empty($faces)? $faces[0]: null;
    }
    
    public function list(array $person_ids): array{
        $where_clause = '';

        if(!empty($person_ids)){
            $where_in = implode("', '", $person_ids);
            $where_clause = "where person_id in ('$where_in')";
        }

        $faces = $this->query("
            select * 
            from face 
            $where_clause
        ");

        return !empty($faces)? $faces[0]: null;
    }
    
    public function add(
        string $person_id, 
        string $id_type, 
        string $encoding
    ){
        
        $res = $this->execute("
            insert into face(
                person_id,
                id_type,
                encoding
            )
            values(
                '$person_id',
                '$id_type',
                '$encoding'
            )
        ");

        return $res;
    }

    public function delete(string $face_id){
        $res = $this->execute("delete from face where face_id = '$face_id'");

        return $res;
    }
}