CREATE TABLE IF NOT EXISTS api_subject_reference (
    reference_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nik VARCHAR(64) NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    last_used_at DATETIME(6) NULL,
    PRIMARY KEY (reference_hash),
    KEY idx_api_subject_reference_actor (actor_hash, expires_at),
    KEY idx_api_subject_reference_nik (nik, expires_at),
    KEY idx_api_subject_reference_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
