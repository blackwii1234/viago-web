<?php

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

$id = $_GET["id"];

$sql = "SELECT * FROM reviews WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$review = $stmt->fetch();

if (!$review) {
    die("리뷰가 없습니다.");
}

if ($_SESSION["user_id"] != $review["user_id"]) {
    die("권한이 없습니다.");
}

$sql = "SELECT file_path FROM review_media WHERE review_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$mediaFiles = $stmt->fetchAll();

foreach ($mediaFiles as $media) {
    viago_delete_media_file($media["file_path"]);
}

$sql = "DELETE FROM reviews WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

header("Location: list.php");
exit;