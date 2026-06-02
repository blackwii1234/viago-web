<?php

session_start();

require_once "../config/db.php";

$id = $_POST["id"] ?? 0;

// 미디어 조회
$sql = "
    SELECT *
    FROM review_media
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$media = $stmt->fetch();

if (!$media) {

    echo json_encode([
        "success" => false
    ]);

    exit;
}

// 리뷰 조회
$sql = "
    SELECT *
    FROM reviews
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$media["review_id"]]);

$review = $stmt->fetch();

// 권한 확인
if ($_SESSION["user_id"] != $review["user_id"]) {

    echo json_encode([
        "success" => false
    ]);

    exit;
}

// 기존 대표 해제
$sql = "
    UPDATE review_media
    SET is_thumbnail = 0
    WHERE review_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$media["review_id"]]);

// 새 대표 지정
$sql = "
    UPDATE review_media
    SET is_thumbnail = 1
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

echo json_encode([
    "success" => true
]);