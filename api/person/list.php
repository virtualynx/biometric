<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

$pm = new PersonModel();

$persons = $pm->list();
$persons_filtered = [];

if(!empty($_POST['sk_number'])){
    foreach($persons as $row){
        if($row->sk_number == $_POST['sk_number']){
            $persons_filtered []= $row;
        }
    }
}else{
    $persons_filtered = $persons;
}

echo json_encode($persons_filtered);
