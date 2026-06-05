<?php

session_start();

header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../includes/auth.php";

$id = $_POST["id"] ?? 0;

// 이미지 조회
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

if (!$review) {

    echo json_encode([
        "success" => false
    ]);

    exit;
}

// 권한 확인
if ($_SESSION["user_id"] != $review["user_id"]) {

    echo json_encode([
        "success" => false
    ]);

    exit;
}

// 실제 파일 삭제
// CloudFront URL이면 S3 객체를 삭제하고, 기존 로컬 경로면 로컬 파일을 삭제합니다.
viago_delete_media_file($media["file_path"]);

// DB 삭제
$sql = "
    DELETE FROM review_media
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

echo json_encode([
    "success" => true
]);