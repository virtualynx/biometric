<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

if(empty($_POST['nik']) || empty($_POST['act_id']) || empty($_POST['value'])){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$nik = $_POST['nik'];
$act_id = $_POST['act_id'];
$value = filter_var($_POST['value'], FILTER_VALIDATE_BOOLEAN);

$db = new Database();
try{
    $existings = $db->query("
        select *
        from trx_subject_act
        where
            nik = '$nik'
            and act_id = '$act_id'
    ");
    $existing = null;
    if(count($existings) > 0){
        $existing = $existings[0];
    }
    if($value == true){
        if(empty($existing)){
            $res = $db->execute("
                insert into trx_subject_act(nik, act_id)
                values('$nik', '$act_id')
            ");
        }
    }else{
        if(!empty($existing)){
            $res = $db->execute("
                delete from trx_subject_act
                where
                    nik = '$nik'
                    and act_id = '$act_id'
            ");
        }
    }
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}

echo 'success';
