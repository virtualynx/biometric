<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/PersonModel.php");

use biometric\src\core\models\PersonModel;

$pm = new PersonModel();

$persons = $pm->list();

echo json_encode($persons);
