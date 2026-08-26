<?php

declare(strict_types=1);

use biometric\src\core\Database;

require_once dirname(__DIR__) . '/src/core/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$migrationPaths = [
    dirname(__DIR__) . '/database/migrations/20260819_api_security_foundation.sql',
    dirname(__DIR__) . '/database/migrations/20260824_resource_scope_authorization.sql',
    dirname(__DIR__) . '/database/migrations/20260825_subject_access_reference.sql',
];

try {
    $database = (new Database())->getConnection();
    foreach ($migrationPaths as $migrationPath) {
        $sql = file_get_contents($migrationPath);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Security migration is missing or empty: ' . basename($migrationPath));
        }

        $database->multi_query($sql);
        do {
            if ($result = $database->store_result()) {
                $result->free();
            }
        } while ($database->more_results() && $database->next_result());

        if ($database->errno !== 0) {
            throw new RuntimeException($database->error, $database->errno);
        }
    }

    echo "Security database migrations completed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Security migration failed: {$exception->getMessage()}\n");
    exit(1);
}
