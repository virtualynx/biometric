<?php

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

$filters = '';

if(!empty($_GET['site_desc'])){
    $filters = " where site_desc = '".$_GET['site_desc']."'";
}

$db = new Database();

try{
    $res = $db->query("select * from master_sk $filters order by `site_desc`, `sk_number`");
    echo json_encode($res);
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}
