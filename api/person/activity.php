<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

if(empty($_POST['nik']) || empty($_POST['acts'])){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$nik = $_POST['nik'];

$db = new Database();
try{
    $db->beginTransaction();

    $acts = $_POST['acts'];

    foreach($acts as $act){
        $act_id = $act['act_id'];
        $value = intval(filter_var($act['value'], FILTER_VALIDATE_BOOLEAN));
    
        $existings = $db->query("
            select *
            from trx_subject_status
            where
                nik = '$nik'
                and status_id = '$act_id'
        ");
        $existing = null;
        if(count($existings) > 0){
            $existing = $existings[0];
        }

        if(empty($existing)){
            $res = $db->execute("
                insert into trx_subject_status(nik, status_id, is_done)
                values('$nik', '$act_id', $value)
            ");
        }else{
            $res = $db->execute("
                update trx_subject_status set
                    is_done = $value
                where
                    nik = '$nik'
                    and status_id = '$act_id'
            ");
        }
    }
    
    $db->endTransaction();
}catch(\Exception $e){
    $db->rollbackTransaction();
    echo $e->getMessage();
    exit;
}

echo 'success';
