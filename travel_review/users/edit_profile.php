<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$message = "";

/* 현재 유저 */

$sql = "
    SELECT *
    FROM users
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_id]);

$user = $stmt->fetch();

/* 수정 */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $bio = trim($_POST["bio"]);

    $profile_image =
        $user["profile_image"];

    /* 프로필 이미지 업로드: EC2 로컬 디스크가 아니라 S3에 저장합니다. */

    if (!empty($_FILES["profile"]["name"])) {

        try {
            $uploaded = viago_upload_to_s3($_FILES["profile"], "profiles");
            if ($uploaded) {
                $profile_image = $uploaded["file_path"];
            }
        } catch (Exception $e) {
            // 프로필 이미지 업로드 실패 시 기존 이미지는 유지합니다.
        }
    }

    /* 저장 */

    $sql = "
        UPDATE users
        SET
            bio = ?,
            profile_image = ?
        WHERE id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $bio,
        $profile_image,
        $user_id
    ]);

    header("Location: profile.php");
    exit;
}

?>

<?php include "../includes/header.php"; ?>

<div class="profile-edit-card">


<h2 class="mb-4">
    ✏ 프로필 수정
</h2>

<form
    method="POST"
    enctype="multipart/form-data"
>

    <div class="mb-4 text-center">

        <img
            src="<?= htmlspecialchars($user["profile_image"] ? media_url($user["profile_image"]) : viago_default_profile_image()) ?>"
            class="profile-avatar mb-3"
        >

        <input
            type="file"
            name="profile"
            class="form-control"
        >

    </div>

    <div class="mb-4">

        <label class="form-label">
            소개글
        </label>

        <textarea
            name="bio"
            rows="5"
            class="form-control"
        ><?= htmlspecialchars($user["bio"] ?? "") ?></textarea>

    </div>

    <button
        class="btn btn-primary"
        type="submit"
    >
        저장하기
    </button>

</form>


</div>

<?php include "../includes/footer.php"; ?>
