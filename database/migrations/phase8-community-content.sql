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
