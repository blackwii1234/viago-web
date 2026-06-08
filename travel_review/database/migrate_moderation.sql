-- =============================================
-- ViaGo 콘텐츠 모더레이션 마이그레이션
-- 대상: 이미 생성된 로컬 DB (travel_review)
-- 실행: phpMyAdmin SQL 탭에 붙여넣고 실행
-- =============================================

-- reviews 테이블에 status 컬럼 추가
ALTER TABLE reviews
    ADD COLUMN status ENUM('normal', 'flagged', 'reported') DEFAULT 'normal'
    AFTER views;

-- comments 테이블에 status 컬럼 추가
ALTER TABLE comments
    ADD COLUMN status ENUM('normal', 'flagged', 'reported') DEFAULT 'normal'
    AFTER content;

-- 신고 테이블 생성
CREATE TABLE IF NOT EXISTS reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('review', 'comment') NOT NULL,
    target_id   INT NOT NULL,
    user_id     INT NOT NULL,
    reason      VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 관리자 계정 추가 (이미 있으면 스킵)
INSERT IGNORE INTO users (username, email, password, role)
VALUES (
    'admin',
    'admin@viago.com',
    '$2y$10$DPoIrfiBn5rFIJIihbyL8uifBGx/mRcW/2mvVt4IyMto6IHy4Xpya',
    'admin'
);


-- Amazon Comprehend 광고글 필터링 결과 저장 컬럼
ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS ad_score INT DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS moderation_reason VARCHAR(255) DEFAULT NULL AFTER ad_score,
    ADD COLUMN IF NOT EXISTS comprehend_result JSON DEFAULT NULL AFTER moderation_reason;
