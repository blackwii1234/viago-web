<?php

session_start();

require_once "../config/db.php";

$id = $_GET["id"];

$sql = "
    UPDATE reviews
    SET views = views + 1
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$sql = "
    SELECT
        reviews.*,
        users.username
    FROM reviews
    JOIN users
    ON reviews.user_id = users.id
    WHERE reviews.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$review = $stmt->fetch();

if (!$review) {
    die("리뷰가 존재하지 않습니다.");
}
// 이미지 조회
$sql = "
    SELECT *
    FROM review_media
    WHERE review_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$mediaFiles = $stmt->fetchAll();

$liked = false;
$like_count = 0;

// 좋아요 수 조회
$sql = "
    SELECT COUNT(*) as cnt
    FROM likes
    WHERE review_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$like_count = $stmt->fetch()["cnt"];

// 로그인 상태면 좋아요 여부 확인
if (isset($_SESSION["user_id"])) {

    $sql = "
        SELECT *
        FROM likes
        WHERE review_id = ?
        AND user_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $id,
        $_SESSION["user_id"]
    ]);

    $liked = $stmt->fetch();
}

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_SESSION["user_id"])
) {

    $content = trim($_POST["content"]);

    if (!empty($content)) {

        $sql = "
            INSERT INTO comments
            (review_id, user_id, content)
            VALUES (?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $id,
            $_SESSION["user_id"],
            $content
        ]);

        // 새로고침 방지
        header("Location: detail.php?id=" . $id);
        exit;
    }
}

$sql = "
    SELECT
        comments.*,
        users.username
    FROM comments
    JOIN users
    ON comments.user_id = users.id
    WHERE review_id = ?
    ORDER BY comments.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$comments = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="detail-layout">

    <!-- 왼쪽 이미지 영역 -->

    <div class="gallery-section">

        <!-- 메인 이미지 -->

            <?php if($mediaFiles): ?>

                <?php $first = $mediaFiles[0]; ?>

                <?php if($first["file_type"] == "image"): ?>

                    <img
                        src="<?= htmlspecialchars(media_url($first["file_path"])) ?>"
                        class="main-image"
                        id="mainMedia"
                    >

                <?php else: ?>

                    <video
                        controls
                        class="main-image"
                        id="mainMedia"
                    >
                        <source
                            src="<?= htmlspecialchars(media_url($first["file_path"])) ?>"
                        >
                    </video>

                <?php endif; ?>

            <?php else: ?>

                <img
                    src="/travel_review/assets/no-image.jpg"
                    class="main-image"
                >

            <?php endif; ?>

            <div class="thumbnail-list">

                <?php foreach($mediaFiles as $media): ?>

                    <div class="thumbnail-wrap">

                        <?php if($media["is_thumbnail"]): ?>

                            <span class="thumbnail-badge">
                                ⭐
                            </span>

                        <?php endif; ?>

                        <?php if($media["file_type"] == "image"): ?>

                            <img
                                src="<?= htmlspecialchars(media_url($media["file_path"])) ?>"
                                class="thumbnail-image"
                                onclick='changeMedia("image", <?= json_encode(media_url($media["file_path"])) ?>)'
                            >

                        <?php elseif($media["file_type"] == "video"): ?>

                            <video
                                class="thumbnail-image"
                                muted
                                playsinline
                                onclick='changeMedia("video", <?= json_encode(media_url($media["file_path"])) ?>)'
                            >

                                <source
                                    src="<?= htmlspecialchars(media_url($media['file_path'])) ?>"
                                >

                            </video>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

    </div>

    
    <!-- 오른쪽 정보 영역 -->

    <div class="info-section">

        <h1 class="detail-title">

            <?= htmlspecialchars($review["title"]) ?>

        </h1>

        <div class="detail-meta">

            👤
            <?= htmlspecialchars($review["username"]) ?>

            ·

            🌍
            <?= htmlspecialchars($review["country"]) ?>

            ·

            ⭐
            <?= $review["rating"] ?>/5

            👁
            <?= $review["views"] ?>

        </div>

        <div class="detail-location">

            📍
            <?= htmlspecialchars($review["location"]) ?>

        </div>

        <div class="detail-date">

            ✈️
            <?= $review["travel_date"] ?>

        </div>

        <!-- 좋아요 -->

        <div class="my-4">

            <?php if(isset($_SESSION["user_id"])): ?>

                <button
                    type="button"
                    class="btn btn-danger like-btn"
                    data-id="<?= $review["id"] ?>"
                >

                    <span class="like-text">

                        <?= $liked
                            ? "❤️ 좋아요 취소"
                            : "🤍 좋아요" ?>

                    </span>

                    (
                        <span class="like-count">
                            <?= $like_count ?>
                        </span>
                    )

                </button>

            <?php endif; ?>

        </div>

        <!-- 본문 -->

        <div class="detail-content">

            <?= nl2br(htmlspecialchars($review["content"])) ?>

        </div>

        <!-- 수정 삭제 -->

        <?php if(
            isset($_SESSION["user_id"]) &&
            $_SESSION["user_id"] == $review["user_id"]
        ): ?>

            <div class="mt-4">

                <a
                    href="edit.php?id=<?= $review["id"] ?>"
                    class="btn btn-warning"
                >
                    수정
                </a>

                <a
                    href="delete.php?id=<?= $review["id"] ?>"
                    class="btn btn-danger"
                    onclick="return confirm('삭제하시겠습니까?')"
                >
                    삭제
                </a>

            </div>

        <?php endif; ?>

        <hr class="my-5">

        <!-- 댓글 -->

        <h3 class="mb-4">
            댓글
        </h3>

        <?php if(isset($_SESSION["user_id"])): ?>

            <form method="POST" class="mb-4">

                <textarea
                    name="content"
                    class="form-control mb-3"
                    rows="3"
                    placeholder="댓글을 입력하세요"
                ></textarea>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    댓글 작성
                </button>

            </form>

        <?php endif; ?>

        <!-- 댓글 목록 -->

        <?php if($comments): ?>
            <?php foreach($comments as $comment): ?>

                <div class="comment-box">

                    <div class="comment-top">

                        <strong>
                            <?= htmlspecialchars($comment["username"]) ?>
                        </strong>

                        <small>
                            <?= $comment["created_at"] ?>
                        </small>

                    </div>

                    <p>

                        <?= nl2br(htmlspecialchars($comment["content"])) ?>

                    </p>

                    <?php if(
                        isset($_SESSION["user_id"]) &&
                        $_SESSION["user_id"] == $comment["user_id"]
                    ): ?>

                        <a
                            href="delete_comment.php?id=<?= $comment["id"] ?>&review_id=<?= $id ?>"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('댓글 삭제하시겠습니까?')"
                        >
                            삭제
                        </a>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>
        <?php endif; ?>

    </div>

</div>
<script>

function changeMedia(type, src) {

    const container =
        document.querySelector(".gallery-section");

    let html = "";

    if (type === "image") {

        html = `
            <img
                src="${src}"
                class="main-image"
                id="mainMedia"
            >
        `;

    } else {

        html = `
            <video
                controls
                autoplay
                playsinline
                class="main-image"
                id="mainMedia"
            >
                <source src="${src}">
            </video>
        `;
    }

    const oldThumbs =
        document.querySelector(".thumbnail-list");

    container.innerHTML =
        html + oldThumbs.outerHTML;
}


const likeBtn = document.querySelector(".like-btn");

if (likeBtn) {

    likeBtn.addEventListener("click", function() {

        const reviewId = this.dataset.id;

        fetch("toggle_like.php", {

            method: "POST",

            headers: {
                "Content-Type":
                    "application/x-www-form-urlencoded"
            },

            body: "review_id=" + reviewId

        })

        .then(response => response.json())

        .then(data => {

            document.querySelector(".like-count")
                .innerText = data.count;

            document.querySelector(".like-text")
                .innerText = data.liked
                    ? "❤️ 좋아요 취소"
                    : "🤍 좋아요";
        });

    });
}

</script>

<?php include "../includes/footer.php"; ?>