<?php

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

$review_id = $_POST["review_id"];
$user_id = $_SESSION["user_id"];

// 기존 좋아요 확인
$sql = "
    SELECT *
    FROM likes
    WHERE review_id = ?
    AND user_id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $review_id,
    $user_id
]);

$like = $stmt->fetch();

// 이미 좋아요 상태
if ($like) {

    $sql = "
        DELETE FROM likes
        WHERE review_id = ?
        AND user_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $review_id,
        $user_id
    ]);

    $liked = false;

} else {

    $sql = "
        INSERT INTO likes
        (review_id, user_id)
        VALUES (?, ?)
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $review_id,
        $user_id
    ]);

    $liked = true;
}

// 좋아요 수 다시 조회
$sql = "
    SELECT COUNT(*) as cnt
    FROM likes
    WHERE review_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$review_id]);

$count = $stmt->fetch()["cnt"];

// JSON 응답
echo json_encode([
    "liked" => $liked,
    "count" => $count
]);