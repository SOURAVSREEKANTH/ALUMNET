CREATE TABLE IF NOT EXISTS member_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    batch_id BIGINT UNSIGNED NULL,
    headline VARCHAR(160) NULL,
    company VARCHAR(160) NULL,
    job_title VARCHAR(160) NULL,
    biography TEXT NULL,
    profile_visibility ENUM('private', 'members', 'public') NOT NULL DEFAULT 'members',
    contact_sharing TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY ix_member_profiles_discovery (department_id, batch_id, profile_visibility),
    CONSTRAINT fk_member_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_profile_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_member_profile_batch FOREIGN KEY (batch_id) REFERENCES graduation_batches (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
