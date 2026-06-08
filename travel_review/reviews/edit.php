<?php

session_start();

require_once "../config/db.php";
require_once "../config/rekognition_filter.php";
require_once "../includes/auth.php";

$id = $_GET["id"];

// 리뷰 조회
$sql = "
    SELECT *
    FROM reviews
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$review = $stmt->fetch();

if (!$review) {
    die("리뷰가 존재하지 않습니다.");
}

// 기존 이미지 출력
$sql = "
    SELECT *
    FROM review_media
    WHERE review_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$mediaFiles = $stmt->fetchAll();

// 본인 글인지 체크
if ($_SESSION["user_id"] != $review["user_id"]) {
    die("수정 권한이 없습니다.");
}

$message = "";

// 수정 처리
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = trim($_POST["title"]);
    $location = trim($_POST["location"]);
    $rating = $_POST["rating"];
    $travel_date = $_POST["travel_date"];
    $content = trim($_POST["content"]);

    if (
        empty($title) ||
        empty($location) ||
        empty($content)
    ) {

        $message = "모든 항목을 입력하세요.";

    } else {

        $sql = "
            UPDATE reviews
            SET
                title = ?,
                location = ?,
                rating = ?,
                travel_date = ?,
                content = ?
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute([
            $title,
            $location,
            $rating,
            $travel_date,
            $content,
            $id
        ]);

        if ($result) {

            // 새 이미지/영상 업로드: EC2 로컬 디스크가 아니라 S3에 저장하고, DB에는 CloudFront URL을 저장합니다.
            if (!empty($_FILES["images"]["name"][0])) {

                foreach ($_FILES["images"]["tmp_name"] as $key => $tmp_name) {

                    $file = viago_uploaded_file_array($_FILES["images"], $key);

                    try {
                        $uploaded = viago_upload_to_s3($file, "reviews");
                    } catch (Exception $e) {
                        $message = $e->getMessage();
                        continue;
                    }

                    if ($uploaded) {
                        $imageModeration = [
                            "status" => "normal",
                            "score" => 0,
                            "reason" => "",
                            "raw_result" => null,
                        ];

                        if (($uploaded["file_type"] ?? "") === "image") {
                            $imageModeration = viago_analyze_s3_image_moderation(
                                $uploaded["bucket"] ?? viago_s3_bucket(),
                                $uploaded["s3_key"] ?? "",
                                $uploaded["file_type"]
                            );

                            if (($imageModeration["status"] ?? "normal") === "blocked") {
                                viago_delete_media_file($uploaded["file_path"]);
                                $pdo->prepare("UPDATE reviews SET status='flagged', moderation_reason=? WHERE id=?")
                                    ->execute([$imageModeration["reason"] ?: "부적절한 이미지 업로드가 차단되었습니다.", $id]);
                                continue;
                            }

                            if (($imageModeration["status"] ?? "normal") === "flagged") {
                                $pdo->prepare("UPDATE reviews SET status='flagged', ad_score=GREATEST(ad_score, ?), moderation_reason=? WHERE id=?")
                                    ->execute([(int)($imageModeration["score"] ?? 0), $imageModeration["reason"] ?? "Rekognition 이미지 검토 필요", $id]);
                            }
                        }

                        $sql = "
                            INSERT INTO review_media
                            (review_id, file_path, file_type, moderation_status, moderation_score, moderation_reason, rekognition_result)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $id,
                            $uploaded["file_path"],
                            $uploaded["file_type"],
                            $imageModeration["status"] ?? "normal",
                            (int)($imageModeration["score"] ?? 0),
                            $imageModeration["reason"] ?? null,
                            json_encode($imageModeration["raw_result"] ?? null, JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                }
            }

            header("Location: detail.php?id=" . $id);
            exit;
        } else {

            $message = "수정 실패";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow">

            <div class="card-body">

                <h2 class="mb-4">
                    리뷰 수정
                </h2>

                <?php if($message): ?>

                    <div class="alert alert-danger">
                        <?= $message ?>
                    </div>

                <?php endif; ?>

                <!-- 수정페이지 이미지 불러오기 -->
                 <?php if($mediaFiles): ?>

                    <div class="mb-4">

                        <h5 class="mb-3">
                            현재 미디어
                        </h5>

                        <div class="row">

                            <?php foreach($mediaFiles as $media): ?>

                                <div
                                    class="col-md-3 mb-3 image-box-<?= $media["id"] ?>"
                                >

                                    <div class="card h-100">

                                        <?php if($media["file_type"] == "image"): ?>

                                            <img
                                                src="<?= htmlspecialchars(media_url($media["file_path"])) ?>"
                                                class="review-edit-media"
                                            >

                                        <?php elseif($media["file_type"] == "video"): ?>

                                            <video
                                                class="review-edit-media"
                                                controls
                                                muted
                                                playsinline
                                            >

                                                <source
                                                    src="<?= htmlspecialchars(media_url($media["file_path"])) ?>"
                                                >

                                            </video>

                                        <?php endif; ?>

                                        <div class="card-body text-center">

                                            <?php if($media["is_thumbnail"]): ?>

                                                <div class="badge bg-primary mb-2">
                                                    대표 미디어
                                                </div>

                                            <?php endif; ?>

                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm set-thumbnail mb-2"
                                                data-id="<?= $media["id"] ?>"
                                            >
                                                대표로 설정
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm delete-media"
                                                data-id="<?= $media["id"] ?>"
                                            >
                                                삭제
                                            </button>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <div class="mb-3">

                        <label class="form-label">
                            제목
                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="<?= htmlspecialchars($review["title"]) ?>"
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            여행 장소
                        </label>

                        <input
                            type="text"
                            name="location"
                            class="form-control"
                            value="<?= htmlspecialchars($review["location"]) ?>"
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            별점
                        </label>

                        <select
                            name="rating"
                            class="form-select"
                        >

                            <?php for($i=1; $i<=5; $i++): ?>

                                <option
                                    value="<?= $i ?>"
                                    <?= $review["rating"] == $i ? "selected" : "" ?>
                                >
                                    <?= $i ?>점
                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            여행 날짜
                        </label>

                        <input
                            type="date"
                            name="travel_date"
                            class="form-control"
                            value="<?= $review["travel_date"] ?>"
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            리뷰 내용
                        </label>

                        <textarea
                            name="content"
                            rows="6"
                            class="form-control"
                        ><?= htmlspecialchars($review["content"]) ?></textarea>

                        <div class="mb-3">

                            <label class="form-label">
                                새 사진/영상 추가
                            </label>

                            <input
                                type="file"
                                name="images[]"
                                class="form-control"
                                multiple
                                accept=".jpg,.jpeg,.png,video/mp4,video/webm,video/quicktime"
                            >

                        </div>
                        
                    </div>

                    <button
                        type="submit"
                        class="btn btn-warning"
                    >
                        수정 완료
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>
<!-- 사진 삭제 스크립트 ajax_delete_image -->
 <script>

document.querySelectorAll(".delete-media").forEach(button => {

    button.addEventListener("click", function() {

        if (!confirm("이미지를 삭제하시겠습니까?")) {
            return;
        }

        const imageId = this.dataset.id;

        fetch("ajax_delete_image.php", {

            method: "POST",

            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },

            body: "id=" + imageId

        })

        .then(response => response.json())

        .then(data => {

            if (data.success) {

                document.querySelector(
                    ".image-box-" + imageId
                ).remove();

            } else {

                alert("삭제 실패");
            }
        });

    });

});

document.querySelectorAll(".set-thumbnail").forEach(button => {

    button.addEventListener("click", function() {

        const mediaId = this.dataset.id;

        fetch("ajax_set_thumbnail.php", {

            method: "POST",

            headers: {
                "Content-Type":
                    "application/x-www-form-urlencoded"
            },

            body: "id=" + mediaId

        })

        .then(response => response.json())

        .then(data => {

            if (data.success) {

                location.reload();

            } else {

                alert("대표 미디어 설정 실패");
            }
        });

    });

});

</script>
<?php include "../includes/footer.php"; ?>