SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    label VARCHAR(64) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (slug, label)
VALUES
    ('alumni', 'Alumni'),
    ('student', 'Student'),
    ('organizer', 'Event organizer'),
    ('admin', 'Administrator')
ON DUPLICATE KEY UPDATE label = VALUES(label);

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY ix_users_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    KEY ix_user_roles_role_user (role_id, user_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS graduation_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id BIGINT UNSIGNED NOT NULL,
    graduation_year SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_batches_department_year (department_id, graduation_year),
    CONSTRAINT fk_batches_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alumni_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    batch_id BIGINT UNSIGNED NULL,
    headline VARCHAR(160) NULL,
    company VARCHAR(160) NULL,
    job_title VARCHAR(160) NULL,
    biography TEXT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    profile_visibility ENUM('private', 'members', 'public') NOT NULL DEFAULT 'members',
    contact_sharing TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY ix_alumni_discovery (department_id, batch_id, profile_visibility, verification_status),
    CONSTRAINT fk_alumni_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_alumni_profile_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_alumni_profile_batch FOREIGN KEY (batch_id) REFERENCES graduation_batches (id) ON DELETE SET NULL,
    CONSTRAINT fk_alumni_profile_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS auth_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action ENUM('login', 'registration') NOT NULL,
    identifier_hash CHAR(64) NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_auth_attempts_created (created_at),
    KEY ix_auth_attempts_action_time (action, created_at),
    KEY ix_auth_attempts_ip_time (ip_hash, action, created_at),
    KEY ix_auth_attempts_identifier_time (identifier_hash, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_audit_target_time (target_type, target_id, created_at),
    KEY ix_audit_actor_time (actor_user_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizer_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(140) NOT NULL,
    description TEXT NOT NULL,
    venue VARCHAR(200) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    capacity INT UNSIGNED NULL,
    status ENUM('published', 'cancelled') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_events_status_start (status, starts_at),
    KEY ix_events_organizer_start (organizer_id, starts_at),
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0),
    CONSTRAINT chk_events_times CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_rsvps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    state ENUM('attending', 'interested', 'waitlisted', 'declined') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_rsvps_event_user (event_id, user_id),
    KEY ix_event_rsvps_event_state (event_id, state),
    CONSTRAINT fk_event_rsvps_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_rsvps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_waitlist (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('waiting', 'promoted', 'cancelled') NOT NULL DEFAULT 'waiting',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_waitlist_event_user (event_id, user_id),
    KEY ix_event_waitlist_event_status_order (event_id, status, created_at, id),
    CONSTRAINT fk_event_waitlist_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_waitlist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_passes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_passes_event_user (event_id, user_id),
    UNIQUE KEY uq_event_passes_token_hash (token_hash),
    CONSTRAINT fk_event_passes_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_passes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_checkins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    pass_id BIGINT UNSIGNED NOT NULL,
    checked_in_by BIGINT UNSIGNED NOT NULL,
    method ENUM('qr', 'manual') NOT NULL,
    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_checkins_event_user (event_id, user_id),
    KEY ix_event_checkins_event_time (event_id, checked_in_at),
    CONSTRAINT fk_event_checkins_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_checkins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_checkins_pass FOREIGN KEY (pass_id) REFERENCES event_passes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_event_checkins_operator FOREIGN KEY (checked_in_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS event_discussions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_event_discussions_event_time (event_id, created_at, id),
    CONSTRAINT fk_event_discussions_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_discussions_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_albums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_photo_albums_event (event_id),
    CONSTRAINT fk_photo_albums_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_photo_albums_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    album_id BIGINT UNSIGNED NOT NULL,
    uploader_user_id BIGINT UNSIGNED NOT NULL,
    storage_key CHAR(68) NOT NULL,
    mime_type ENUM('image/jpeg', 'image/png') NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    caption VARCHAR(300) NOT NULL DEFAULT '',
    moderation_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_photos_storage_key (storage_key),
    KEY ix_event_photos_album_status (album_id, moderation_status, created_at),
    CONSTRAINT fk_event_photos_album FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_photos_uploader FOREIGN KEY (uploader_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_event_photos_dimensions CHECK (width > 0 AND height > 0 AND byte_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    notification_type ENUM(
        'event_discussion',
        'mentorship_request',
        'mentorship_accepted',
        'mentorship_declined',
        'photo_approved',
        'photo_rejected',
        'alumni_verified',
        'alumni_rejected'
    ) NOT NULL,
    resource_type ENUM('event', 'mentorship', 'alumni') NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_notifications_recipient_read_time (recipient_user_id, read_at, created_at, id),
    CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
