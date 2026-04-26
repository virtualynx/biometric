<?php
require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

use biometric\src\core\Database;

function ensureVerifierColumns(Database $db): void
{
    $columns = $db->query("SHOW COLUMNS FROM trx_subject_status");
    $existingColumns = array_map(
        fn($column) => $column->Field ?? null,
        $columns
    );

    if (!in_array('verifier_name', $existingColumns, true)) {
        $db->execute("ALTER TABLE trx_subject_status ADD COLUMN verifier_name VARCHAR(255) NULL AFTER is_done");
    }

    if (!in_array('verifier_email', $existingColumns, true)) {
        $db->execute("ALTER TABLE trx_subject_status ADD COLUMN verifier_email VARCHAR(255) NULL AFTER verifier_name");
    }

    if (!in_array('verified_at', $existingColumns, true)) {
        $db->execute("ALTER TABLE trx_subject_status ADD COLUMN verified_at DATETIME NULL AFTER verifier_email");
    }
}

if(empty($_POST['nik']) || empty($_POST['acts'])){
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$nik = $_POST['nik'];

$db = new Database();
try{
    $db->beginTransaction();
    ensureVerifierColumns($db);

    $acts = $_POST['acts'];
    $verifierName = !empty($_POST['verifier_name']) ? addslashes(trim($_POST['verifier_name'])) : null;
    $verifierEmail = !empty($_POST['verifier_email']) ? addslashes(trim($_POST['verifier_email'])) : null;

    foreach($acts as $act){
        $act_id = $act['act_id'];
        $value = intval(filter_var($act['value'], FILTER_VALIDATE_BOOLEAN));
        $verifierNameSql = $value === 1 && !empty($verifierName)
            ? "'" . $verifierName . "'"
            : "NULL";
        $verifierEmailSql = $value === 1 && !empty($verifierEmail)
            ? "'" . $verifierEmail . "'"
            : "NULL";
        $verifiedAtSql = $value === 1 ? "NOW()" : "NULL";
    
        $existings = $db->query("
            select *
            from trx_subject_status
            where
                nik = '$nik'
                and status_id = '$act_id'
        ");
        $existing = null;
        if(count($existings) > 0){
            $existing = $existings[0];
        }

        if(empty($existing)){
            $res = $db->execute("
                insert into trx_subject_status(nik, status_id, is_done, verifier_name, verifier_email, verified_at)
                values('$nik', '$act_id', $value, $verifierNameSql, $verifierEmailSql, $verifiedAtSql)
            ");
        }else{
            $res = $db->execute("
                update trx_subject_status set
                    is_done = $value,
                    verifier_name = $verifierNameSql,
                    verifier_email = $verifierEmailSql,
                    verified_at = $verifiedAtSql
                where
                    nik = '$nik'
                    and status_id = '$act_id'
            ");
        }
    }
    
    $db->endTransaction();
}catch(\Exception $e){
    $db->rollbackTransaction();
    echo $e->getMessage();
    exit;
}

echo 'success';
