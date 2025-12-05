<?php

namespace biometric\src\core\models;

use biometric\src\core\Database;
use biometric\src\core\Fingerprint;
use biometric\src\core\utils\Helper;
use PDO;
use stdClass;

require_once(dirname(__FILE__) . "/../Database.php");
require_once(dirname(__FILE__) . "/PhotoModel.php");
require_once(dirname(__FILE__) . "/DocumentModel.php");
require_once(dirname(__FILE__) . "/FileUploadModel.php");
require_once(dirname(__FILE__) . "/../Fingerprint.php");
require_once(dirname(__FILE__) . "/FaceModel.php");

class PersonModel extends Database
{
    /** @var \mysqli */
    private $db;
    private $photoModel;
    private $documentModel;
    private $fileUploadModel;
    private $faceModel;

    public static $STATUS = [];

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->getConnection();

        $this->photoModel = new PhotoModel();
        $this->documentModel = new DocumentModel();
        $this->fileUploadModel = new FileUploadModel();
        $this->faceModel = new FaceModel();
    }

    public function list(?string $sk_number = null): array
    {
        if (!empty($sk_number)) {
            $stmt = $this->db->prepare("
            SELECT * 
            FROM person 
            WHERE deleted_at IS NULL AND sk_number = ?
            ORDER BY created_at DESC
        ");
            $stmt->bind_param("s", $sk_number);
        } else {
            $stmt = $this->db->prepare("
            SELECT * 
            FROM person 
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ");
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $persons = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($persons as &$row) {
            $row['biometric_status'] = $this->getBiometricStatus($row['nik']);
            $row['status'] = $this->getOverallStatus($row['nik']);
        }
        unset($row);

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

    public function update_mobile(\stdClass $person, string $sk_number): bool
    {
        $sql = "
        UPDATE person SET
        name = ?,
            address = ?,
            familycard_no = ?,
            village = ?,
            phone = ?,
            luas_tanah = ?,
            luas_bangunan = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE
            nik = ?
            AND sk_number = ?
    ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->db->error);
        }
        $person->luas_tanah = $person->luas_tanah ?? 0;
        $person->luas_bangunan = $person->luas_bangunan ?? 0;
        $stmt->bind_param(
            "sssssddss",
            $person->name,
            $person->address,
            $person->familycard_no,
            $person->village,
            $person->phone,
            $person->luas_tanah,
            $person->luas_bangunan,
            $person->nik,
            $sk_number
        );

        $stmt->execute();

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
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
        $hasKk  = false;
        $docs   = $this->documentModel->get($nik);

        foreach ($docs as $row) {
            if ($row->type_id == 'KTP' || $row->type == 'SIM')  $hasKtp = true;
            if ($row->type_id == 'KK')                          $hasKk  = true;
        }

        if (!$hasKtp) return "Dokumen KTP belum lengkap";
        if (!$hasKk)  return "Dokumen KK belum lengkap";

        $bio = $this->getBiometricStatus($nik);
        if ($bio->photo != "completed") {
            return "Belum melakukan foto wajah";
        }

        $this->execute("
    INSERT IGNORE INTO trx_subject_status(nik, status_id, is_done)
    VALUES 
    ('$nik','REG',1),
    ('$nik','DOC-VERIFY',1),
    ('$nik','AGR-DISC',0),
    ('$nik','SIGN-UTL',0),
    ('$nik','CERT-ACQ',0)
");
        $trx = $this->query("
        SELECT tss.status_id, ms.name, ms.`order`, tss.is_done
        FROM trx_subject_status tss
        JOIN master_status ms ON ms.id = tss.status_id
        WHERE ms.disabled = 0 AND tss.nik = '$nik'
        ORDER BY ms.`order` ASC
    ");

        foreach ($trx as $row) {
            if ($row->is_done == 0) {
                return $row->name;
            }
        }

        return "Subjek RA BBT";
    }




    public function getStatusList($nik)
    {
        $res_masters = $this->query("
        SELECT 
            ms.id,
            ms.name,
            ms.`order`,
            0 AS is_done
        FROM 
            master_status ms
        WHERE
            ms.disabled = 0
        ORDER BY
            ms.`order` ASC
    ");

        $results = json_decode(json_encode($res_masters), true);

        $trx_status = $this->query("
        SELECT 
            tss.status_id,
            ms.`order`,
            tss.is_done
        FROM 
            trx_subject_status tss
            JOIN master_status ms ON tss.status_id = ms.id
        WHERE
            ms.disabled = 0
            AND tss.nik = '$nik'
        ORDER BY
            ms.`order` ASC
    ");

        foreach ($results as &$row) {
            foreach ($trx_status as $status) {
                if ($row['id'] == $status->status_id) {
                    $row['is_done'] = intval($status->is_done);
                }
            }
        }
        unset($row);

        return $results;
    }


    public function query($sql, $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        if ($params && count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return json_decode(json_encode($rows));
    }

    public function execQuery($sql, $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        if ($params && count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function beginTransaction()
    {
        $this->db->begin_transaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollback()
    {
        $this->db->rollback();
    }
}
