USE ViaGoDb;

CREATE TABLE IF NOT EXISTS moderation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('review', 'comment', 'media', 'user', 'report') NOT NULL,
    target_id INT NOT NULL,
    action ENUM('approved', 'deleted', 'blocked', 'dismissed', 'role_changed') NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    ai_score INT DEFAULT NULL,
    ai_service VARCHAR(50) DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moderation_logs_target (target_type, target_id),
    INDEX idx_moderation_logs_action (action),
    INDEX idx_moderation_logs_created_at (created_at),
    FOREIGN KEY (admin_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
