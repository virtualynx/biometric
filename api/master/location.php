<?php

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

$db = new Database();

try{
    $res = $db->query("select site_desc from master_sk order by `site_desc`");

    $result = [];
    foreach($res as $row){
        $result []= $row->site_desc;
    }

    echo json_encode($result);
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}
