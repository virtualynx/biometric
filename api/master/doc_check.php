<?php

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

$db = new Database();

try{
    $res = $db->query("select * from master_doc_checklist where disabled = 0 order by `order`");
    echo json_encode($res);
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}
