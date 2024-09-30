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

if(empty($_POST['prefix']) && empty($_POST['nik'])){
    http_response_code(400);
    echo 'Missing required parameter';
    exit; 
}

$qm = new QueueModel();

if(!empty($_POST['prefix'])){
    $queue = $qm->pullQueue($_POST['prefix']);
}else if(!empty($_POST['nik'])){
    $queue = $qm->findByNik($_POST['nik']);
    if(!empty($queue)){
        $qm->updateStatus($queue->queue_id, QueueModel::STATUS_PULLED);
    }
}

echo !empty($queue)? json_encode($queue): '';