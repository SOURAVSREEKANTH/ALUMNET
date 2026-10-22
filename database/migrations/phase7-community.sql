CREATE TABLE IF NOT EXISTS mentorship_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    topics VARCHAR(500) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY ix_mentorship_profiles_available (is_available, updated_at),
    CONSTRAINT fk_mentorship_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mentorship_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_user_id BIGINT UNSIGNED NOT NULL,
    mentor_user_id BIGINT UNSIGNED NOT NULL,
    goals VARCHAR(2000) NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'withdrawn') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mentorship_request_pair (requester_user_id, mentor_user_id),
    KEY ix_mentorship_requests_mentor_status (mentor_user_id, status, updated_at),
    KEY ix_mentorship_requests_requester_status (requester_user_id, status, updated_at),
    CONSTRAINT fk_mentorship_requester FOREIGN KEY (requester_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_mentorship_mentor FOREIGN KEY (mentor_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_mentorship_distinct_users CHECK (requester_user_id <> mentor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    opportunity_type ENUM('job', 'internship', 'scholarship', 'volunteer', 'other') NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(160) NULL,
    expires_at DATETIME NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_opportunities_status_expiry (status, expires_at, created_at),
    KEY ix_opportunities_author (author_user_id, created_at),
    CONSTRAINT fk_opportunity_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    preferred_location VARCHAR(120) NULL,
    available_from DATE NULL,
    available_until DATE NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_event_preference_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_event_preference_dates CHECK (available_until IS NULL OR available_from IS NULL OR available_until >= available_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
