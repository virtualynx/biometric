<?php
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

if(empty($_POST['nik'])){
    //reject
}

$db = new Database();

$persons = $db->query("select * from person where nik = '$'");

echo json_encode($persons);
