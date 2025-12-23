<?php
require_once(dirname(__FILE__) . "/../../src/core/models/PotensiModel.php");
require_once(dirname(__FILE__) . "/../_api_header.php");

use biometric\src\core\models\PotensiModel;

$sk_number = $_POST['sk_number'] ?? null;

$model = new PotensiModel();
$data  = $model->list($sk_number);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'data'   => $data
]);
exit;
