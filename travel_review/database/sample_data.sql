USE ViaGoDb;

INSERT INTO users (username, email, password, bio, role)
VALUES
('admin', 'admin@viago.com', '$2y$10$5o33Jd9yUyy3M91/7S4UheSg04Kv3U0uJHOZME0wEJj5q6D4kN3O2', '관리자 테스트 계정입니다.', 'admin'),
('tester', 'tester@viago.com', '$2y$10$5o33Jd9yUyy3M91/7S4UheSg04Kv3U0uJHOZME0wEJj5q6D4kN3O2', '테스트 사용자입니다.', 'user')
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO reviews (user_id, title, content, location, country, rating, travel_date)
SELECT u.id, '도쿄 여행 후기', 'CloudFront 이미지 표시 테스트용 리뷰입니다.', 'Tokyo', '일본', 5, '2026-05-21'
FROM users u
WHERE u.email = 'admin@viago.com'
ON DUPLICATE KEY UPDATE title = title;
