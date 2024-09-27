<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

if(empty($_POST['nik'])){
    //reject
}

$db = new Database();

$persons = $db->query("");

echo json_encode($persons);
