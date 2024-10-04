<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\Fingerprint;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");
require_once(dirname(__FILE__)."/PhotoModel.php");
require_once(dirname(__FILE__)."/DocumentModel.php");
require_once(dirname(__FILE__)."/FileUploadModel.php");
require_once(dirname(__FILE__)."/../Fingerprint.php");

class PersonModel extends Database {
    private $photoModel;
    private $documentModel;
    private $fileUploadModel;

    public function __construct(){
        parent::__construct();
        $this->photoModel = new PhotoModel();
        $this->documentModel = new DocumentModel();
        $this->fileUploadModel = new FileUploadModel();
    }

    public function list(): array{
        $persons = $this->query("select * from person");

        $persons = json_decode(json_encode($persons), true);
        foreach($persons as &$row){
            $row['biometric_status'] = $this->getBiometricStatus($row['nik']);

            unset($row);
        }

        return json_decode(json_encode($persons));
    }

    public function get(string $nik): stdClass{
        $persons = $this->query("select * from person where nik = '$nik'");

        if(count($persons) == 0){
            throw new \Exception('Data not found');
        }

        $person = json_decode(json_encode($persons[0]), true);
        $person['biometric_status'] = $this->getBiometricStatus($nik);
        $person['documents'] = $this->documentModel->get($nik);
        $photos = $this->photoModel->get($nik);
        $person['photos'] = $photos;

        /**
         * DO NOT LOAD PHOTO BY DEFAULT
         */
        // $bioPhoto = null;
        // foreach($photos as $row){
        //     if($row->type == 'biometric'){
        //         $bioPhoto = $row;
        //         break;
        //     }
        // }
        // if(!empty($bioPhoto)){
        //     $bioPhoto = $this->fileUploadModel->getBase64String($bioPhoto->filename, $bioPhoto->photo_path);
        // }
        // $person['photo'] = $bioPhoto;

        /**
         * GET STATUS
         */
        $acts = $this->query("
            select 
                id,
                name,
                (
                    case when (
                        exists (select 1 from trx_subject_act where nik = '$nik' and act_id = mact.id)
                    ) then
                        1
                    else
                        0
                    end
                ) as status
            from 
                master_act mact
            order by
                `order`
        ");
        $latestStatus = null;
        foreach($acts as $row){
            if(intval($row->status) == 0){
                $latestStatus = $row;
                break;
            }
        }
        if(empty($latestStatus)){
            $latestStatus = 'Done';
        }
        $person['status'] = $latestStatus->name;

        /**
         * DOCUMENT VERIFICATION STATUS
         */
        $docs = $this->query("
            select 
                id,
                name,
                (
                    case when (
                        exists (select 1 from trx_subject_act where nik = '$nik' and act_id = mdt.id)
                    ) then
                        1
                    else
                        0
                    end
                ) as status
            from 
                master_doc_type mdt
            order by
                `order`
        ");
        $person['doc_verification'] = [

        ];

        return json_decode(json_encode($person));
    }

    public function getByFmd(string $fmd): stdClass{
        $persons = $this->list();
        $fp = new Fingerprint();

        $found_nik = null;
        foreach($persons as $row){
            $fingerprints = $this->getFingerprints($row->nik);

            $fmdArr = [];
            foreach($fingerprints as $row_fp){
                $fmdArr []= $row_fp->hash;
            }

            if(count($fmdArr)>0){
                $res = $fp->verify($fmd, $fmdArr);
                if($res === 'match'){
                    $found_nik = $row->nik;
                    break;
                }
            }
        }

        $result = null;
        if(!empty($found_nik)){
            $result = $this->get($found_nik);
        }

        return json_decode(json_encode(['person' => $result]));
    }

    public function add(stdClass $person): bool{
        $persons = $this->query("select * from person where nik = '$person->nik'");

        if(count($persons) > 0){
            throw new \Exception('Data exists');
        }

        $res = $this->execute("
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
        $res = $this->execute("
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
        $fps = $this->query("select * from fingerprint where nik = '$nik'");

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
        $hasIndex = false;
        $hasThumb = false;
        foreach($fps as $row){
            if($row->hand_side == 'RIGHT' && $row->finger_type == 'INDEX'){
                $hasIndex = true;
            }
            if($row->hand_side == 'RIGHT' && $row->finger_type == 'THUMB'){
                $hasThumb = true;
            }
        }
        
        if($hasIndex && $hasThumb){
            $result['fingerprint'] = 'completed';
        }else if(!$hasIndex){
            $result['fingerprint'] = 'index finger not registered';
        }else if(!$hasThumb){
            $result['fingerprint'] = 'thumb finger not registered';
        }

        return json_decode(json_encode($result));
    }
}