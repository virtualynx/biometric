<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

if(empty($_POST['nik']) || empty($_POST['doc_checklist_id']) || empty($_POST['value'])){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$nik = $_POST['nik'];
$doc_checklist_id = $_POST['doc_checklist_id'];
$value = filter_var($_POST['value'], FILTER_VALIDATE_BOOLEAN);

$db = new Database();
try{
    $existings = $db->queryPrepared(
        'SELECT * FROM trx_subject_doc_checklist
         WHERE nik = ? AND doc_checklist_id = ?',
        [$nik, $doc_checklist_id]
    );
    $existing = null;
    if(count($existings) > 0){
        $existing = $existings[0];
    }
    if($value == true){
        if(empty($existing)){
            $res = $db->executePrepared(
                'INSERT INTO trx_subject_doc_checklist (nik, doc_checklist_id) VALUES (?, ?)',
                [$nik, $doc_checklist_id]
            );
        }
    }else{
        if(!empty($existing)){
            $res = $db->executePrepared(
                'DELETE FROM trx_subject_doc_checklist
                 WHERE nik = ? AND doc_checklist_id = ?',
                [$nik, $doc_checklist_id]
            );
        }
    }
}catch(\Exception $e){
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui checklist dokumen.']);
}

echo 'success';
