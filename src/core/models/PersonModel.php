<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");

class PersonModel {
    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    public function list(): array{
        $persons = $this->db->query("select * from person");

        $persons = json_decode(json_encode($persons), true);
        foreach($persons as &$row){
            $row['biometric_status'] = $this->getBiometricStatus($row['nik']);

            unset($row);
        }

        return $persons;
    }

    public function getInfo(string $nik): stdClass{
        $persons = $this->db->query("select * from person where nik = '$nik'");

        if(count($persons) == 0){
            throw new \Exception('Data not found');
        }

        $person = json_decode(json_encode($persons[0]), true);
        $person['biometric_status'] = $this->getBiometricStatus($nik);
        $person['documents'] = $this->getDocuments($nik);
        $person['photos'] = $this->getPhotos($nik);

        return json_decode(json_encode($person));;
    }

    public function getDocuments(string $nik): array{
        $docs = $this->db->query("select * from document where nik = '$nik'");

        return $docs;
    }

    public function getPhotos(string $nik): array{
        $photos = $this->db->query("select * from photo where nik = '$nik'");

        return $photos;
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

        $photos = $this->getPhotos($nik);
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