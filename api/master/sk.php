<?php

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

$db = new Database();

try{
    $connection = $db->getConnection();
    if (!empty($_GET['site_desc'])) {
        $siteDesc = trim((string) $_GET['site_desc']);
        $statement = $connection->prepare(
            'SELECT * FROM master_sk WHERE site_desc = ? ORDER BY site_desc, sk_number'
        );
        $statement->bind_param('s', $siteDesc);
    } else {
        $statement = $connection->prepare(
            'SELECT * FROM master_sk ORDER BY site_desc, sk_number'
        );
    }
    $statement->execute();
    $res = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    echo json_encode(keycloak_filter_master_sk_rows(
        $res,
        keycloak_authenticated_identity()
    ));
}catch(\Throwable $e){
    error_log('[biometric-security] SK scope filter failed: ' . $e->getMessage());
    keycloak_json_error(
        503,
        'authorization_unavailable',
        'Daftar SK sementara tidak tersedia.'
    );
}
