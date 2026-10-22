ALTER TABLE notifications
    MODIFY notification_type ENUM(
        'event_discussion',
        'mentorship_request',
        'mentorship_accepted',
        'mentorship_declined',
        'photo_approved',
        'photo_rejected',
        'alumni_verified',
        'alumni_rejected'
    ) NOT NULL,
    MODIFY resource_type ENUM('event', 'mentorship', 'alumni') NOT NULL;
