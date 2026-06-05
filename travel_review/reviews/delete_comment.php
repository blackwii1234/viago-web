<?php

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

$id = $_GET["id"];
$review_id = $_GET["review_id"];

$sql = "
    SELECT *
    FROM comments
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$comment = $stmt->fetch();

if (!$comment) {
    die("댓글이 존재하지 않습니다.");
}

if ($_SESSION["user_id"] != $comment["user_id"]) {
    die("권한이 없습니다.");
}

$sql = "
    DELETE FROM comments
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$isAjax = !empty($_GET["ajax"]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header("Location: detail.php?id=" . $review_id);
exit;