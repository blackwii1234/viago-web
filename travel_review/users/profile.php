<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

/* 유저 정보 */

$sql = "
    SELECT *
    FROM users
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_id]);

$user = $stmt->fetch();

/* 리뷰 목록 */

$sql = "
    SELECT
        reviews.*,
        review_media.file_path AS media,
        review_media.file_type AS media_type

    FROM reviews

    LEFT JOIN review_media
    ON review_media.id = (
        SELECT id
        FROM review_media
        WHERE review_id = reviews.id
        ORDER BY
            is_thumbnail DESC,

            CASE
                WHEN file_type = 'image' THEN 0
                ELSE 1
            END,
            id ASC
        LIMIT 1
    )

    WHERE reviews.user_id = ?

    ORDER BY reviews.id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_id]);

$reviews = $stmt->fetchAll();

?>

<?php include "../includes/header.php"; ?>

<section class="profile-header">


<div class="profile-left">

    <img
        src="<?= htmlspecialchars($user["profile_image"] ? media_url($user["profile_image"]) : viago_default_profile_image()) ?>"
        class="profile-avatar"
    >

</div>

<div class="profile-right">

    <h1>
        <?= htmlspecialchars($user["username"]) ?>
    </h1>

    <p class="profile-bio">

        <?= $user["bio"]
            ? nl2br(htmlspecialchars($user["bio"]))
            : "여행 소개글이 없습니다." ?>

    </p>

    <a
        href="edit_profile.php"
        class="btn btn-primary profile-edit-btn"
    >
        ✏ 프로필 수정
    </a>

    <div class="profile-stats">

    <div class="profile-stats">

        <div class="stat-box">

            <strong>
                <?= count($reviews) ?>
            </strong>

            <span>
                리뷰
            </span>

        </div>

    </div>

</div>


</section>

<h2 class="section-title">
    ✈ 내 여행 기록
</h2>

<div class="row">

<?php foreach($reviews as $review): ?>


<div class="col-md-4 mb-4">

    <div class="review-card h-100">

        <?php if(!empty($review["media"])): ?>

            <?php if($review["media_type"] == "image"): ?>

                <img
                    src="<?= htmlspecialchars(media_url($review["media"])) ?>"
                    class="review-image"
                >

            <?php elseif($review["media_type"] == "video"): ?>

                <video
                    class="review-image"
                    muted
                    autoplay
                    loop
                    playsinline
                >

                    <source
                        src="<?= htmlspecialchars(media_url($review["media"])) ?>"
                    >

                </video>

            <?php endif; ?>

        <?php endif; ?>

        <div class="card-body">

            <h5>
                <?= htmlspecialchars($review["title"]) ?>
            </h5>

            <p>
                📍 <?= htmlspecialchars($review["location"]) ?>
            </p>

            <p>
                👁 <?= $review["views"] ?>
            </p>

            <a
                href="../reviews/detail.php?id=<?= $review["id"] ?>"
                class="btn btn-primary"
            >
                상세보기
            </a>

        </div>

    </div>

</div>


<?php endforeach; ?>

</div>

<?php include "../includes/footer.php"; ?>
