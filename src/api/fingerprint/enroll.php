<?php
/*
 * Author: Dahir Muhammad Dahir
 * Date: 26-April-2020 5:44 PM
 * About: identification and verification
 * will be carried out in this file
 */

namespace fingerprint;

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../../core/helpers/helpers.php");
require_once(dirname(__FILE__)."/../../core/Database.php");
require_once(dirname(__FILE__)."/../../core/Fingerprint.php");

if(!empty($_POST['nik']) && !empty($_POST['fmds'])) {
    // $user_data = json_decode($_POST["data"]);
    // $user_id = $user_data->id;
    // //this is not necessarily index_finger it could be
    // //any finger we wish to identify
    // $pre_reg_fmd_string = $user_data->index_finger[0];

    $db = new Database();
    $persons = $db->query("select * from person where nik='".$_POST['nik']."'");

    $fingerprints = $db->query("select nik, fmd from fingerprint");
    $registeredFmds = [];
    foreach($fingerprints as $row){
        $registeredFmds []= $row->fmd;
    }

    $fp = new Fingerprint();

    if(count($registeredFmds) > 0 && $fp->isDuplicate($_POST['fmds'][0], $registeredFmds)) {
        echo "Duplicate not allowed!";
    }

    $fmds = json_decode(json_encode($_POST['fmds']));

    $indexFmds = [];
    $thumbFmds = [];
    foreach($fmds as $row){
        if($row->fingerType == 'index'){
            $indexFmds []= $row->fmd;
        }
        if($row->fingerType == 'thumb'){
            $thumbFmds []= $row->fmd;
        }
    }

    $response_index = $fp->enroll($indexFmds);
    if($response_index === "enrollment failed"){
        $response_index = null;
    }else{
        $response_index = $response_index->enrolled_index_finger;
    }
    $response_thumb = $fp->enroll($thumbFmds);
    if($response_thumb === "enrollment failed"){
        $response_thumb = null;
    }else{
        $response_thumb = $response_thumb->enrolled_index_finger;
    }
    // $response3 = $fp->enroll($indexFmds, $thumbFmds);

    $pre_reg_fmd_array = [
        "index_finger" => $indexFmds,
        "middle_finger" => $thumbFmds
    ];
    $json_response = enroll_fingerprint($pre_reg_fmd_array);
    $response4 = json_decode($json_response);

    $result = [
        'status' => 'success',
        'data' => [
            'index' => $response_index,
            'thumb' => $response_thumb
        ]
    ];

    if($response_index == null && $response_thumb == null){
        $result['status'] = 'failed';
    }

    echo json_encode($result);
}else{
    http_response_code(400);
    echo 'Missing required field';
}
