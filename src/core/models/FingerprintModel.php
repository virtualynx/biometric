<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\Fingerprint;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");
require_once(dirname(__FILE__)."/PersonModel.php");
require_once(dirname(__FILE__)."/PhotoModel.php");
require_once(dirname(__FILE__)."/DocumentModel.php");
require_once(dirname(__FILE__)."/FileUploadModel.php");

class FingerprintModel {
    private $db;
    private $photoModel;
    private $documentModel;
    private $fileUploadModel;

    public function __construct(){
        $this->db = new Database();
        $this->photoModel = new PhotoModel();
        $this->documentModel = new DocumentModel();
        $this->fileUploadModel = new FileUploadModel();
    }

    public function list(): array{
        $rs = $this->db->query("select * from fingerprint");

        return $rs;
    }

    public function getByFmd(string $fmd): stdClass{
        $pm = new PersonModel();
        $persons = $pm->list();
        $fp = new Fingerprint();

        foreach($persons as $row){
            $fmdArr = $pm->getFingerprints($row->nik);

            if(count($fmdArr)>0){
                $res = $fp->verify($fmd, $fmdArr);
            }

            $a = 1;
        }

        return json_decode(json_encode(['test' => 1]));
    }

    public function add(stdClass $person): bool{
        $persons = $this->db->query("select * from person where nik = '$person->nik'");

        if(count($persons) > 0){
            throw new \Exception('Data exists');
        }

        $res = $this->db->execute("
            insert into person(
                nik,
                name,
                address,
                familycard_no,
                village,
                phone
            )
            values(
                '$person->nik',
                '$person->name',
                '$person->address',
                '$person->familycard_no',
                '$person->village',
                '$person->phone'
            )
        ");

        return $res;
    }

    public function update(stdClass $person): bool{
        $res = $this->db->execute("
            update person
            set
                name = '$person->name',
                address = '$person->address',
                familycard_no = '$person->familycard_no',
                village = '$person->village',
                phone = '$person->phone',
                updated_at = current_timestamp()
            where
                nik = '$person->nik'
        ");

        return $res;
    }

    public function getFingerprints(string $nik): array{
        $fps = $this->db->query("select * from fingerprint where nik = '$nik'");

        return $fps;
    }

    public function getBiometricStatus(string $nik): stdClass{
        $result = [
            'photo' => 'unregistered',
            'fingerprint' => 'unregistered'
        ];

        $photos = $this->photoModel->get($nik);
        foreach($photos as $row){
            if($row->type == 'biometric'){
                $result['photo'] = 'completed';
            }
        }

        $fps = $this->getFingerprints($nik);
        foreach($fps as $row){
            $hasIndex = false;
            $hasThumb = false;
            if($row->hand_side == 'RIGHT' && $row->finger_type == 'INDEX'){
                $hasIndex = true;
            }
            if($row->hand_side == 'RIGHT' && $row->finger_type == 'THUMB'){
                $hasThumb = true;
            }
            if($hasIndex && $hasThumb){
                $result['fingerprint'] = 'completed';
            }else if(!$hasIndex){
                $result['fingerprint'] = 'index finger not registered';
            }else if(!$hasThumb){
                $result['fingerprint'] = 'thumb finger not registered';
            }
        }

        return json_decode(json_encode($result));
    }
}