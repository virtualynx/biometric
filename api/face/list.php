<?php

use biometric\src\core\models\FaceModel;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/FaceModel.php");
// require_once(dirname(__FILE__)."/../../src/core/helpers/helpers.php");

$requestBody = file_get_contents('php://input');
$json = json_decode($requestBody);

$fm = new FaceModel();
$faces = $fm->list($json->person_ids);

echo json_encode([
    'status' => 'success',
    'data' => $faces
]);
