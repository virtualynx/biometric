CREATE TABLE IF NOT EXISTS api_actor_scope (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type ENUM('site', 'sk') NOT NULL,
    scope_value VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    note VARCHAR(500) NULL,
    granted_by_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uniq_api_actor_scope (actor_hash, scope_type, scope_value),
    KEY idx_api_actor_scope_active (actor_hash, is_active, valid_until),
    CONSTRAINT chk_api_actor_scope_validity
        CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_scope_observation (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_path VARCHAR(512) NOT NULL,
    decision VARCHAR(32) NOT NULL,
    target_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    observation_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
    first_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    last_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uniq_api_scope_observation (actor_hash, request_path, decision),
    KEY idx_api_scope_observation_decision (decision, last_seen_at),
    KEY idx_api_scope_observation_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
