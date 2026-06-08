<?php

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

$id = $_GET["id"];
$review_id = $_GET["review_id"];

// 이미지 조회
$sql = "
    SELECT *
    FROM review_media
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$image = $stmt->fetch();

if (!$image) {
    die("이미지가 존재하지 않습니다.");
}

// 리뷰 작성자 확인
$sql = "
    SELECT *
    FROM reviews
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$review_id]);

$review = $stmt->fetch();

if ($_SESSION["user_id"] != $review["user_id"]) {
    die("권한이 없습니다.");
}

// 실제 파일 삭제
// CloudFront URL이면 S3 객체를 삭제하고, 기존 로컬 경로면 로컬 파일을 삭제합니다.
viago_delete_media_file($image["file_path"]);

// DB 삭제
$sql = "
    DELETE FROM review_media
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

header("Location: edit.php?id=" . $review_id);
exit;