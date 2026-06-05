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

// flagged 리뷰는 관리자만 접근 가능
if ($review["status"] === "flagged") {
    $isAdmin = isset($_SESSION["user_id"]) && ($_SESSION["role"] ?? "") === "admin";
    if (!$isAdmin) {
        header("Location: /travel_review/index.php");
        exit;
    }
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["user_id"])) {

    $isAjax  = isset($_POST["_ajax"]);
    $content = trim($_POST["content"]);

    if (!empty($content)) {

        $sql = "INSERT INTO comments (review_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $_SESSION["user_id"], $content]);
        $newId = $pdo->lastInsertId();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'    => true,
                'id'         => (int)$newId,
                'username'   => $_SESSION["username"],
                'user_id'    => (int)$_SESSION["user_id"],
                'content'    => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            exit;
        }

        header("Location: detail.php?id=" . $id);
        exit;
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '내용을 입력하세요']);
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
                                loading="lazy"
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

            <i class="bi bi-person"></i>
            <?= htmlspecialchars($review["username"]) ?>

            ·

            <i class="bi bi-globe2"></i>
            <?= htmlspecialchars($review["country"]) ?>

            ·

            <i class="bi bi-star-fill" style="color:#ffc107;"></i>
            <?= $review["rating"] ?>/5

            <i class="bi bi-eye"></i>
            <?= $review["views"] ?>

        </div>

        <div class="detail-location">

            <i class="bi bi-geo-alt"></i>
            <?= htmlspecialchars($review["location"]) ?>

        </div>

        <div class="detail-date">

            <i class="bi bi-calendar3"></i>
            <?= $review["travel_date"] ?>

        </div>

        <!-- 좋아요 -->

        <div class="my-4">

            <?php if(isset($_SESSION["user_id"])): ?>

                <button
                    type="button"
                    class="btn <?= $liked ? 'btn-danger' : 'btn-outline-danger' ?> like-btn"
                    data-id="<?= $review["id"] ?>"
                >
                    <i class="bi <?= $liked ? 'bi-heart-fill' : 'bi-heart' ?> like-icon"></i>
                    <span class="like-text"><?= $liked ? "좋아요 취소" : "좋아요" ?></span>
                    <span class="like-count"><?= $like_count ?></span>
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

            <form id="commentForm" class="mb-4">

                <textarea
                    name="content"
                    id="commentContent"
                    class="form-control mb-3"
                    rows="3"
                    placeholder="댓글을 입력하세요"
                ></textarea>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-chat-dots"></i> 댓글 작성
                </button>

            </form>

        <?php endif; ?>

        <!-- 댓글 목록 -->

        <div id="commentList">

        <?php foreach($comments as $comment): ?>

            <div class="comment-box" data-comment-id="<?= $comment["id"] ?>">

                <div class="comment-top">

                    <strong>
                        <i class="bi bi-person-circle" style="color:var(--primary);"></i>
                        <?= htmlspecialchars($comment["username"]) ?>
                    </strong>

                    <small><?= $comment["created_at"] ?></small>

                </div>

                <p><?= nl2br(htmlspecialchars($comment["content"])) ?></p>

                <?php if(isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $comment["user_id"]): ?>
                    <button
                        class="btn btn-sm btn-outline-danger delete-comment-btn"
                        data-id="<?= $comment["id"] ?>"
                        data-review="<?= $id ?>"
                    >
                        <i class="bi bi-trash3"></i> 삭제
                    </button>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>

        </div>

    </div>

</div>
<script>

/* 미디어 전환 */
function changeMedia(type, src) {
    const container  = document.querySelector(".gallery-section");
    const oldThumbs  = document.querySelector(".thumbnail-list");
    const mediaHtml  = type === "image"
        ? `<img src="${src}" class="main-image" id="mainMedia" loading="lazy">`
        : `<video controls autoplay playsinline class="main-image" id="mainMedia"><source src="${src}"></video>`;
    container.innerHTML = mediaHtml + oldThumbs.outerHTML;
}

/* 좋아요 */
const likeBtn = document.querySelector(".like-btn");
if (likeBtn) {
    likeBtn.addEventListener("click", function() {
        fetch("toggle_like.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "review_id=" + this.dataset.id
        })
        .then(r => r.json())
        .then(data => {
            document.querySelector(".like-count").innerText = data.count;
            const icon = likeBtn.querySelector(".like-icon");
            const text = likeBtn.querySelector(".like-text");
            if (data.liked) {
                icon.className       = "bi bi-heart-fill like-icon";
                text.innerText       = "좋아요 취소";
                likeBtn.className    = "btn btn-danger like-btn";
                showToast("좋아요를 눌렀어요 ❤️", "danger");
            } else {
                icon.className       = "bi bi-heart like-icon";
                text.innerText       = "좋아요";
                likeBtn.className    = "btn btn-outline-danger like-btn";
                showToast("좋아요를 취소했어요", "secondary");
            }
        });
    });
}

/* 댓글 유틸 */
function escHtml(str) {
    var d = document.createElement("div");
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function bindDeleteBtn(btn) {
    if (!btn) return;
    btn.addEventListener("click", async function() {
        if (!confirm("댓글을 삭제하시겠습니까?")) return;
        const res  = await fetch("delete_comment.php?id=" + this.dataset.id + "&review_id=" + this.dataset.review + "&ajax=1");
        const data = await res.json();
        if (data.success) {
            this.closest(".comment-box").remove();
            showToast("댓글이 삭제됐어요", "success");
        } else {
            showToast("삭제에 실패했어요", "danger");
        }
    });
}

/* 새 댓글 DOM 삽입 */
function prependComment(data) {
    const myUserId = <?= isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0 ?>;
    const isOwner  = myUserId === data.user_id;
    const div      = document.createElement("div");
    div.className  = "comment-box";
    div.dataset.commentId = data.id;
    div.innerHTML  = `
        <div class="comment-top">
            <strong><i class="bi bi-person-circle" style="color:var(--primary);"></i> ${escHtml(data.username)}</strong>
            <small>${data.created_at}</small>
        </div>
        <p>${escHtml(data.content).replace(/\n/g, "<br>")}</p>
        ${isOwner ? `<button class="btn btn-sm btn-outline-danger delete-comment-btn" data-id="${data.id}" data-review="<?= $id ?>"><i class="bi bi-trash3"></i> 삭제</button>` : ""}
    `;
    const list = document.getElementById("commentList");
    list.insertBefore(div, list.firstChild);
    bindDeleteBtn(div.querySelector(".delete-comment-btn"));
}

/* AJAX 댓글 작성 */
const commentForm = document.getElementById("commentForm");
if (commentForm) {
    commentForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        const textarea = document.getElementById("commentContent");
        const content  = textarea.value.trim();
        if (!content) { showToast("댓글 내용을 입력하세요", "warning"); return; }

        const fd = new FormData();
        fd.append("content", content);
        fd.append("_ajax",   "1");

        try {
            const res  = await fetch("", { method: "POST", body: fd });
            const data = await res.json();
            if (data.success) {
                textarea.value = "";
                prependComment(data);
                showToast("댓글이 작성됐어요", "success");
            } else {
                showToast(data.message || "오류가 발생했어요", "danger");
            }
        } catch(err) {
            showToast("오류가 발생했어요", "danger");
        }
    });
}

/* 기존 삭제 버튼 바인딩 */
document.querySelectorAll(".delete-comment-btn").forEach(bindDeleteBtn);

</script>

<?php include "../includes/footer.php"; ?>