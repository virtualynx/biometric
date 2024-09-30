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
    const FINGER_TYPE_INDEX = 'INDEX';
    const FINGER_TYPE_THUMB = 'THUMB';
    const HAND_SIDE_LEFT = 'LEFT';
    const HAND_SIDE_RIGHT = 'RIGHT';

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

    public function add(string $nik, string $handSide, string $fingerType, string $hash): bool{
        $res = $this->db->execute("
            insert into fingerprint(
                nik,
                finger_type,
                hand_side,
                hash
            )
            values(
                '$nik',
                '$fingerType',
                '$handSide',
                '$hash'
            )
        ");

        return $res;
    }

    public function clearFingerprintsForNik(string $nik): bool{
        $res = $this->db->execute("delete from fingerprint where nik = '$nik'");

        return $res;
    }
}