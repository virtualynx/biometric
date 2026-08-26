<?php

use biometric\src\core\Database;

require_once(dirname(__FILE__)."/../_api_header.php");
require_once(dirname(__FILE__)."/../../src/core/Database.php");

$db = new Database();

try{
    $connection = $db->getConnection();
    $res = $connection->query(
        'SELECT site_desc, sk_number FROM master_sk ORDER BY site_desc, sk_number'
    )->fetch_all(MYSQLI_ASSOC);
    $allowedRows = keycloak_filter_master_sk_rows(
        $res,
        keycloak_authenticated_identity()
    );
    $result = array_values(array_unique(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['site_desc'] ?? '')),
        $allowedRows
    ))));

    echo json_encode($result);
}catch(\Throwable $e){
    error_log('[biometric-security] location scope filter failed: ' . $e->getMessage());
    keycloak_json_error(
        503,
        'authorization_unavailable',
        'Daftar lokasi sementara tidak tersedia.'
    );
}
