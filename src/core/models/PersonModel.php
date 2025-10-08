<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\Fingerprint;
use biometric\src\core\utils\Helper;
use stdClass;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/PhotoModel.php");
require_once(dirname(__FILE__) . "/DocumentModel.php");
require_once(dirname(__FILE__) . "/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../Fingerprint.php");
require_once(dirname(__FILE__) . "/FaceModel.php");

class PersonModel extends Database
{
    private $photoModel;
    private $documentModel;
    private $fileUploadModel;
    private $faceModel;

    public static $STATUS = [];

    public function __construct()
    {
        parent::__construct();
        $this->photoModel = new PhotoModel();
        $this->documentModel = new DocumentModel();
        $this->fileUploadModel = new FileUploadModel();
        $this->faceModel = new FaceModel();
    }

    public function list(): array
    {
        $persons = $this->query("
            select * 
            from person 
            where deleted_at is NULL
            order by created_at desc");

        $persons = json_decode(json_encode($persons), true);
        foreach ($persons as &$row) {
            $row['biometric_status'] = $this->getBiometricStatus($row['nik']);
            $row['status'] = $this->getOverallStatus($row['nik']);

            unset($row);
        }

        return json_decode(json_encode($persons));
    }

    public function get(string $nik, string $sk_number = null): stdClass
    {
        $where_sk = "";
        if (!empty($sk_number)) {
            $where_sk = " and sk_number = '$sk_number'";
        }
        $persons = $this->query("select * from person where nik = '$nik' $where_sk");

        if (count($persons) == 0) {
            throw new \Exception('Data not found', 901);
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

        return json_decode(json_encode($person));
    }

    /**
     * Mengecek apakah person dengan NIK (dan opsional SK number) sudah ada.
     *
     * @param string $nik
     * @param string|null $sk_number
     * @return bool
     */
    public function exists(string $nik, ?string $sk_number = null): bool
    {
        $where_sk = "";
        if (!empty($sk_number)) {
            $where_sk = " AND sk_number = '$sk_number'";
        }

        $rows = $this->query("SELECT COUNT(*) AS total FROM person WHERE nik = '$nik' $where_sk AND deleted_at IS NULL");
        return isset($rows[0]) && intval($rows[0]->total) > 0;
    }


    public function getByFmd(string $fmd): stdClass
    {
        $persons = $this->list();
        $fp = new Fingerprint();

        $found_nik = null;
        foreach ($persons as $row) {
            $fingerprints = $this->getFingerprints($row->nik);

            $fmdArr = [];
            foreach ($fingerprints as $row_fp) {
                $fmdArr[] = $row_fp->hash;
            }

            if (count($fmdArr) > 0) {
                $res = $fp->verify($fmd, $fmdArr);
                if ($res === 'match') {
                    $found_nik = $row->nik;
                    break;
                }
            }
        }

        $result = null;
        if (!empty($found_nik)) {
            $result = $this->get($found_nik);
        }

        return json_decode(json_encode(['person' => $result]));
    }

    public function add(stdClass $person): bool
    {
        $persons = $this->query("select * from person where nik = '$person->nik'");

        if (count($persons) > 0) {
            throw new \Exception('Data exists');
        }

        $sk_number = empty($person->sk_number) ? "NULL" : "'$person->sk_number'";
        $luas_tanah = empty($person->luas_tanah) ? "NULL" : "$person->luas_tanah";
        $luas_bangunan = empty($person->luas_bangunan) ? "NULL" : "$person->luas_bangunan";
        $beneficiary_nik = empty($person->beneficiary_nik) ? "NULL" : "'$person->beneficiary_nik'";
        $beneficiary_familycard_no = empty($person->beneficiary_familycard_no) ? "NULL" : "'$person->beneficiary_familycard_no'";
        $beneficiary_name = empty($person->beneficiary_name) ? "NULL" : "'$person->beneficiary_name'";
        $beneficiary_address = empty($person->beneficiary_address) ? "NULL" : "'$person->beneficiary_address'";

        $res = $this->execute("
            insert into person(
                nik,
                name,
                address,
                familycard_no,
                village,
                phone,
                sk_number,
                luas_tanah,
                luas_bangunan,
                beneficiary_nik,
                beneficiary_familycard_no,
                beneficiary_name,
                beneficiary_address
            )
            values(
                '$person->nik',
                '$person->name',
                '$person->address',
                '$person->familycard_no',
                '$person->village',
                '$person->phone',
                $sk_number,
                $luas_tanah,
                $luas_bangunan,
                $beneficiary_nik,
                $beneficiary_familycard_no,
                $beneficiary_name,
                $beneficiary_address
            )
        ");

        return $res;
    }

    public function update(stdClass $person): bool
    {
        $res = $this->execute("
            update person
            set
                name = '$person->name',
                address = '$person->address',
                familycard_no = '$person->familycard_no',
                village = '$person->village',
                phone = '$person->phone'
                " . (!empty($person->luas_tanah) ? ", luas_tanah = $person->luas_tanah" : '') . "
                " . (!empty($person->luas_bangunan) ? ", luas_bangunan = $person->luas_bangunan" : '') . "
                " . (!empty($person->beneficiary_nik) ? ", beneficiary_nik = '$person->beneficiary_nik'" : '') . "
                " . (!empty($person->beneficiary_familycard_no) ? ", beneficiary_familycard_no = '$person->beneficiary_familycard_no'" : '') . "
                " . (!empty($person->beneficiary_name) ? ", beneficiary_name = '$person->beneficiary_name'" : '') . "
                " . (!empty($person->beneficiary_address) ? ", beneficiary_address = '$person->beneficiary_address'" : '') . "
                , updated_at = current_timestamp()
            where
                nik = '$person->nik'
        ");

        return $res;
    }

    public function update_mobile(stdClass $person, string $sk_number): bool
    {
        $res = $this->execute("
            update person
            set
                name = '$person->name',
                address = '$person->address',
                familycard_no = '$person->familycard_no',
                village = '$person->village',
                phone = '$person->phone'
                " . (!empty($person->luas_tanah) ? ", luas_tanah = $person->luas_tanah" : '') . "
                " . (!empty($person->luas_bangunan) ? ", luas_bangunan = $person->luas_bangunan" : '') . "
                " . (!empty($person->beneficiary_nik) ? ", beneficiary_nik = '$person->beneficiary_nik'" : '') . "
                " . (!empty($person->beneficiary_familycard_no) ? ", beneficiary_familycard_no = '$person->beneficiary_familycard_no'" : '') . "
                " . (!empty($person->beneficiary_name) ? ", beneficiary_name = '$person->beneficiary_name'" : '') . "
                " . (!empty($person->beneficiary_address) ? ", beneficiary_address = '$person->beneficiary_address'" : '') . "
                , updated_at = current_timestamp()
            where
                nik = '$person->nik'
                and sk_number = '$sk_number'
        ");

        return $res;
    }

    public function delete(string $nik, string $sk_number)
    {
        $res = $this->execute("
            update person
            set deleted_at = current_timestamp()
            where 
                nik = '$nik'
                and sk_number = '$sk_number'
        ");

        return $res;
    }

    public function getFingerprints(string $nik): array
    {
        $fps = $this->query("select * from fingerprint where nik = '$nik'");

        return $fps;
    }

    public function getBiometricStatus(string $nik): stdClass
    {
        $result = [
            'photo' => 'unregistered',
            'fingerprint' => 'unregistered',
            'face' => 'unregistered'
        ];

        $photos = $this->photoModel->get($nik);
        foreach ($photos as $row) {
            if ($row->type == 'biometric') {
                $result['photo'] = 'completed';
            }
        }

        $fps = $this->getFingerprints($nik);
        $hasIndex = false;
        $hasThumb = false;
        foreach ($fps as $row) {
            if ($row->hand_side == 'RIGHT' && $row->finger_type == 'INDEX') {
                $hasIndex = true;
            }
            if ($row->hand_side == 'RIGHT' && $row->finger_type == 'THUMB') {
                $hasThumb = true;
            }
        }

        if ($hasIndex && $hasThumb) {
            $result['fingerprint'] = 'completed';
        } else if (!$hasIndex) {
            $result['fingerprint'] = 'index finger not registered';
        } else if (!$hasThumb) {
            $result['fingerprint'] = 'thumb finger not registered';
        }

        $faces = $this->faceModel->list([$nik]);
        if (count($faces) > 0) {
            $result['face'] = 'completed';
        }

        return json_decode(json_encode($result));
    }

    public function getOverallStatus($nik)
    {
        $hasKtp = false;
        $hasKk = false;
        $docs = $this->documentModel->get($nik);
        foreach ($docs as $row) {
            if ($row->type_id == 'KTP' || $row->type == 'SIM') {
                $hasKtp = true;
            }
            if ($row->type_id == 'KK') {
                $hasKk = true;
            }
        }

        if (!$hasKtp) {
            return 'Dokumen KTP belum lengkap';
        }
        if (!$hasKk) {
            return 'Dokumen KK belum lengkap';
        }

        $biometricStatus = $this->getBiometricStatus($nik);
        if ($biometricStatus->photo != 'completed') {
            return 'Belum melakukan foto wajah';
        }
        // if ($biometricStatus->fingerprint != 'completed' && $biometricStatus->face != 'completed') {
        //     return 'Belum melakukan rekam fingerprint maupun rekam wajah';
        // }

        //auto-generate REG, DOC-VERIFY for already existing KTP and KK
        try {
            $res = $this->execute("
                insert into trx_subject_status(
                    nik,
                    status_id,
                    is_done
                )
                select 
                    '$nik',
                    id,
                    1
                from 
                    master_status ms
                where
                    disabled = 0
                    and id in ('REG', 'DOC-VERIFY')
                order by
                    `order`
            ");
        } catch (\Exception $e) {
            if (!Helper::startsWith($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
        }
        // auto-generate AGR-DISC
        try {
            $res = $this->execute("
                insert into trx_subject_status(
                    nik,
                    status_id,
                    is_done
                )
                select 
                    '$nik',
                    'AGR-DISC',
                    0
            ");
        } catch (\Exception $e) {
            if (!Helper::startsWith($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
        }

        $trxSubjectStatus = $this->query("
            select 
                tss.*,
                ms.name,
                ms.`order`
            from 
                trx_subject_status tss
                join master_status ms on tss.status_id = ms.id
            where
                ms.disabled = 0
                and tss.nik = '$nik'
            order by
                ms.`order` desc
        ");

        $latestStatus = null;
        if (!empty($trxSubjectStatus)) {
            foreach ($trxSubjectStatus as $row) {
                if ($row->is_done == 1) {
                    break;
                }
                $latestStatus = $row;
            }
        }

        //auto-generated status log
        if (!empty($latestStatus)) {
            try {
                $res = $this->execute("
                    insert into trx_subject_status(
                        nik,
                        status_id,
                        is_done
                    )
                    select 
                        '$nik',
                        id,
                        1
                    from 
                        master_status ms
                    where
                        `order` < $latestStatus->order
                    order by
                        `order`
                ");
            } catch (\Exception $e) {
                if (!Helper::startsWith($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
            }
        }

        if (empty($latestStatus)) {
            $latestStatus = 'Done';
            return 'Done';
        }

        return $latestStatus->name;
    }

    public function getStatusList($nik)
    {
        $res_masters = $this->query("
            select 
                ms.id,
                ms.name,
                0 as is_done
            from 
                master_status ms
            where
                ms.disabled = 0
            order by
                ms.`order` asc
        ");

        $results = json_decode(json_encode($res_masters), true);

        $trx_status = $this->query("
            select 
                tss.status_id,
                ms.name,
                tss.is_done
            from 
                trx_subject_status tss
                join master_status ms on tss.status_id = ms.id
            where
                ms.disabled = 0
                and tss.nik = '$nik'
            order by
                ms.`order` asc
        ");

        foreach ($results as &$row) {
            foreach ($trx_status as $status) {
                if ($row['id'] == $status->status_id) {
                    $row['is_done'] = $status->is_done;
                }
            }

            $row['is_done'] = intval($row['is_done']);
        }
        unset($row);

        return $results;
    }
}
