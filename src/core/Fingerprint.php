<?php
namespace biometric\src\core;

use biometric\src\core\models\EnvFileModel;

require_once(dirname(__FILE__)."/helpers/helpers.php");
require_once(dirname(__FILE__)."/models/EnvFileModel.php");

date_default_timezone_set("Asia/Jakarta");

class Fingerprint {
    private const MAX_FMD_BYTES = 256 * 1024;
    private $fp_service_host;

    function __construct(){
        // $this->fp_service_host = getenv('FP_CLIENT_SERVICE_HOST');
        $env = new EnvFileModel();
        $configuredHost = rtrim(trim((string) $env->get('FP_CLIENT_SERVICE_HOST')), '/');
        $parts = parse_url($configuredHost);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (
            $configuredHost === '' ||
            !is_array($parts) ||
            !in_array($scheme, ['http', 'https'], true) ||
            empty($parts['host']) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['query']) ||
            isset($parts['fragment'])
        ) {
            throw new \RuntimeException('Fingerprint service is not configured safely');
        }
        $this->fp_service_host = $configuredHost;
    }

    public static function normalizeFmd($value): ?string
    {
        if (is_string($value)) {
            $encoded = trim($value);
        } elseif (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
        } else {
            return null;
        }

        if (
            !is_string($encoded) ||
            $encoded === '' ||
            strlen($encoded) > self::MAX_FMD_BYTES
        ) {
            return null;
        }

        return $encoded;
    }

    /**
     * 
     */
    function enroll($fmdArr, $fmdArr2 = []){
        $res = $this->post_service(
            "/enroll.php", 
            [
                "index_finger" => $fmdArr,
                "middle_finger" => !empty($fmdArr2)? $fmdArr2: ''
            ]
        );

        return json_decode(json_encode([
            'finger1' => $res->enrolled_index_finger,
            'finger2' => $res->enrolled_middle_finger
        ]));

        // $pre_reg_fmd_array = [
        //     "index_finger" => $fmdArr,
        //     "middle_finger" => $fmdArr2
        // ];
        // $json_response = enroll_fingerprint($pre_reg_fmd_array);

        // return json_decode($json_response);
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
        $res = $this->post_service(
            "verify.php",
            [
                "pre_enrolled_finger_data" => $fmdToCheck,
                "enrolled_index_finger_data" => $fmdArr[0],
                "enrolled_middle_finger_data" => count($fmdArr)>1? $fmdArr[1]: ''
            ]
        );

        return $res;
    }

    private function post_service($endpoint, $data){
        $endpoint = ltrim((string) $endpoint, '/');
        if (!in_array($endpoint, ['enroll.php', 'verify.php', 'is_duplicate.php'], true)) {
            throw new \RuntimeException('Fingerprint operation is not allowed');
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || strlen($payload) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Fingerprint request is invalid');
        }

        $jsonStr = make_request(
            "$this->fp_service_host/coreComponents/$endpoint",
            ['data' => $payload]
        );
        if (!is_string($jsonStr) || strlen($jsonStr) > 1024 * 1024) {
            throw new \RuntimeException('Fingerprint service returned an invalid response');
        }

        $decoded = json_decode($jsonStr);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Fingerprint service returned an invalid response');
        }

        return $decoded;
    }
}
