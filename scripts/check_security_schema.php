<?php

declare(strict_types=1);

use biometric\src\core\Database;

require_once dirname(__DIR__) . '/src/core/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$requiredTables = [
    'api_auth_cache',
    'api_rate_limit',
    'api_security_event',
    'api_actor_scope',
    'api_scope_observation',
    'api_subject_reference',
];

try {
    $database = (new Database())->getConnection();
    $statement = $database->prepare(
        'SELECT COUNT(*) AS table_count
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );

    $missingTables = [];
    foreach ($requiredTables as $tableName) {
        $statement->bind_param('s', $tableName);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ((int) ($row['table_count'] ?? 0) !== 1) {
            $missingTables[] = $tableName;
        }
    }
    $statement->close();

    if ($missingTables !== []) {
        fwrite(
            STDERR,
            'Missing security tables: ' . implode(', ', $missingTables) . PHP_EOL
        );
        exit(1);
    }

    echo "Security schema is ready. No database changes were made.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Security schema check failed: {$exception->getMessage()}\n");
    exit(1);
}
