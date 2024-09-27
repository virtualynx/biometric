<?php
namespace fingerprint;

require_once(dirname(__FILE__)."/helpers/helpers.php");

date_default_timezone_set("Asia/Jakarta");

class Fingerprint {
    private $fp_service_host;

    function __construct(){
        $this->fp_service_host = getenv('FP_CLIENT_SERVICE_HOST');
    }

    /**
     * 
     */
    function enroll($fmdArr, $fmdArr2 = []){
        return $this->post_service(
            "/enroll.php", 
            [
                "index_finger" => $fmdArr,
                "middle_finger" => []
            ]
        );
    }

    function isDuplicate($fmdToCheck, $fmdArr){
        return $this->post_service(
            "is_duplicate.php", 
            [
                "pre_enrolled_finger_data" => $fmdToCheck,
                "enrolled_hands_list" => $fmdArr
            ]
        );
    }

    function verify($fmdToCheck, $fmdArr){
        return $this->post_service(
            "verify.php",
            [
                "pre_enrolled_finger_data" => $fmdToCheck,
                "enrolled_index_finger_data" => $fmdArr[0],
                "enrolled_middle_finger_data" => count($fmdArr)>1? $fmdArr[1]: ''
            ]
        );
    }

    private function post_service($endpoint, $data){
        $jsonStr = make_request("$this->fp_service_host/coreComponents/$endpoint", ['data' => json_encode($data)]);

        return json_decode($jsonStr);
    }
}
