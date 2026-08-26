<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_api_header.php';
require_once dirname(__DIR__, 2) . '/src/core/models/SubjectHistoryModel.php';
require_once dirname(__DIR__, 2) . '/src/core/security/ResourceScopeAuthorizer.php';

use biometric\src\core\models\SubjectHistoryModel;
use biometric\src\core\security\ResourceScopeAuthorizer;

$input = json_decode((string) file_get_contents('php://input'), true);
$nik = is_array($input) ? trim((string) ($input['nik'] ?? '')) : '';
if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $nik) !== 1) {
    keycloak_json_error(422, 'invalid_subject', 'Data subjek tidak valid.');
}

try {
    $model = new SubjectHistoryModel();
    $records = $model->findRelatedRecords($nik);
    if ($records === []) {
        keycloak_json_error(404, 'subject_not_found', 'Data subjek tidak ditemukan.');
    }

    $identity = keycloak_authenticated_identity();
    $actor = trim((string) ($identity['sub'] ?? ''));
    if (!keycloak_identity_can_read_all_scopes($identity)) {
        $claimScopes = is_array($identity['resource_scopes'] ?? null)
            ? $identity['resource_scopes']
            : [];
        $authorizer = new ResourceScopeAuthorizer();
        $records = array_values(array_filter(
            $records,
            static function (array $record) use ($authorizer, $actor, $claimScopes): bool {
                $decision = $authorizer->evaluate(
                    $actor,
                    ['nik' => [(string) ($record['nik'] ?? '')]],
                    $claimScopes
                );
                return ($decision['decision'] ?? null) === 'allowed';
            }
        ));
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'records' => $records,
            'sk_labels' => $model->listSkLabels($records),
        ],
    ]);
} catch (Throwable $exception) {
    error_log('[biometric-security] subject history failed: ' . $exception->getMessage());
    keycloak_json_error(500, 'subject_history_failed', 'Riwayat subjek belum dapat dimuat.');
}
