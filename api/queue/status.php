<?php
require_once(dirname(__FILE__)."/../../src/utils/Helper.php");
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/models/QueueModel.php");

use biometric\src\core\models\PersonModel;
use biometric\src\core\models\FileUploadModel;
use biometric\src\core\models\DocumentModel;
use biometric\src\core\models\PhotoModel;
use biometric\src\core\models\QueueModel;
use biometric\src\core\utils\Helper;

if(empty($_POST['queue_id'])){
    http_response_code(400);
    echo 'Missing required parameter';
    exit; 
}

$qm = new QueueModel();

$queue = $qm->find($_POST['queue_id']);

// echo !empty($queue)? $queue->status: '';

echo json_encode([
    'status' => !empty($queue)? $queue->status: '',
    'queue' => $queue
]);