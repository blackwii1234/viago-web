USE ViaGoDb;

ALTER TABLE review_media
ADD COLUMN IF NOT EXISTS moderation_status ENUM('normal', 'flagged', 'blocked') DEFAULT 'normal',
ADD COLUMN IF NOT EXISTS moderation_score INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS moderation_reason VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS rekognition_result JSON DEFAULT NULL;
