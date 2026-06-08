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
        <i class="bi bi-pencil"></i> 프로필 수정
    </a>

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
    <i class="bi bi-journal-richtext" style="color:var(--primary);font-size:1.3rem;"></i> 내 여행 기록
</h2>

<div class="row">

<?php foreach($reviews as $review): ?>

<div class="col-md-4 mb-4">

    <a
        href="../reviews/detail.php?id=<?= $review["id"] ?>"
        class="review-card"
    >

        <?php if(!empty($review["media"])): ?>

            <?php if($review["media_type"] == "image"): ?>

                <img
                    src="<?= htmlspecialchars(media_url($review["media"])) ?>"
                    class="review-card-img"
                    alt=""
                    loading="lazy"
                >

            <?php else: ?>

                <video
                    class="review-card-img"
                    muted
                    autoplay
                    loop
                    playsinline
                    preload="metadata"
                >
                    <source src="<?= htmlspecialchars(media_url($review["media"])) ?>">
                </video>

            <?php endif; ?>

        <?php else: ?>

            <div class="review-card-no-img"></div>

        <?php endif; ?>

        <div class="review-card-overlay">

            <h5 class="review-card-title">
                <?= htmlspecialchars($review["title"]) ?>
            </h5>

            <div class="review-card-meta">
                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($review["location"]) ?></span>
                <span><i class="bi bi-eye"></i> <?= $review["views"] ?></span>
            </div>

        </div>

    </a>

</div>

<?php endforeach; ?>

</div>

<?php include "../includes/footer.php"; ?>
