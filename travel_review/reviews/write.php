<?php

session_start();

require_once "../config/db.php";
require_once "../config/comprehend_filter.php";
require_once "../config/rekognition_filter.php";
require_once "../includes/auth.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = trim($_POST["title"]);
    $content = trim($_POST["content"]);
    $location = trim($_POST["location"]);
    $country = trim($_POST["country"]);
    $rating = $_POST["rating"];
    $travel_date = $_POST["travel_date"];

    if (
        empty($title) ||
        empty($content) ||
        empty($location) ||
        empty($country)
    ) {

        $message = "모든 항목을 입력하세요.";

    } else {

        $moderation = viago_analyze_ad_risk($title, $content);

        $sql = "
            INSERT INTO reviews
            (user_id, title, content, location, country, rating, travel_date, status, ad_score, moderation_reason, comprehend_result)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute([
            $_SESSION["user_id"],
            $title,
            $content,
            $location,
            $country,
            $rating,
            $travel_date,
            $moderation["status"],
            $moderation["score"],
            $moderation["reason"],
            json_encode($moderation["comprehend_result"], JSON_UNESCAPED_UNICODE)
        ]);

        if ($result) {

            $review_id = $pdo->lastInsertId();
            $finalStatus = $moderation["status"] ?? "normal";
            $finalScore = (int)($moderation["score"] ?? 0);
            $finalReasons = [];
            if (!empty($moderation["reason"])) {
                $finalReasons[] = $moderation["reason"];
            }

            if (!empty($_FILES["media"]["name"][0])) {

                foreach ($_FILES["media"]["tmp_name"] as $key => $tmp_name) {

                    $is_thumbnail = ($key == 0) ? 1 : 0;
                    $file = viago_uploaded_file_array($_FILES["media"], $key);

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
                                $finalStatus = "flagged";
                                $finalScore = max($finalScore, (int)($imageModeration["score"] ?? 0));
                                $finalReasons[] = $imageModeration["reason"] ?: "부적절한 이미지 업로드가 차단되었습니다.";
                                continue;
                            }

                            if (($imageModeration["status"] ?? "normal") === "flagged") {
                                $finalStatus = "flagged";
                                $finalScore = max($finalScore, (int)($imageModeration["score"] ?? 0));
                                if (!empty($imageModeration["reason"])) {
                                    $finalReasons[] = $imageModeration["reason"];
                                }
                            }
                        }

                        $sql = "
                            INSERT INTO review_media
                            (review_id, file_path, file_type, is_thumbnail, moderation_status, moderation_score, moderation_reason, rekognition_result)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $review_id,
                            $uploaded["file_path"],
                            $uploaded["file_type"],
                            $is_thumbnail,
                            $imageModeration["status"] ?? "normal",
                            (int)($imageModeration["score"] ?? 0),
                            $imageModeration["reason"] ?? null,
                            json_encode($imageModeration["raw_result"] ?? null, JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                }
            }

            if ($finalStatus !== ($moderation["status"] ?? "normal") || $finalScore !== (int)($moderation["score"] ?? 0) || count($finalReasons) > 1) {
                $stmt = $pdo->prepare("UPDATE reviews SET status = ?, ad_score = ?, moderation_reason = ? WHERE id = ?");
                $stmt->execute([
                    $finalStatus,
                    $finalScore,
                    implode(', ', array_unique(array_filter($finalReasons))),
                    $review_id
                ]);
            }

            if ($finalStatus === "flagged") {
                $_SESSION["moderation_message"] = "광고성 또는 부적절한 이미지가 의심되어 관리자 검토 후 공개됩니다.";
                header("Location: /travel_review/index.php?moderation=flagged");
                exit;
            }

            header("Location: detail.php?id=" . $review_id);
            exit;

        } else {

            $message = "리뷰 작성 실패";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="write-card">

    <div class="write-card-header">
        <h2><i class="bi bi-pencil-square"></i> 여행 기록하기</h2>
        <p>소중한 여행의 기억을 남겨보세요</p>
    </div>

    <?php if($message): ?>
        <div class="alert alert-danger" style="margin: 20px 40px 0; border-radius: 14px;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="write-form">

        <div class="mb-4">
            <label class="write-label">제목</label>
            <input
                type="text"
                name="title"
                class="form-control write-input"
                placeholder="이번 여행의 제목을 지어주세요"
            >
        </div>

        <div class="row mb-4">

            <div class="col-md-6 mb-3 mb-md-0">
                <label class="write-label"><i class="bi bi-globe2"></i> 나라</label>
                <select name="country" class="form-select write-input">
                    <option value="">나라 선택</option>
                    <option value="한국">🇰🇷 한국</option>
                    <option value="일본">🇯🇵 일본</option>
                    <option value="미국">🇺🇸 미국</option>
                    <option value="프랑스">🇫🇷 프랑스</option>
                    <option value="영국">🇬🇧 영국</option>
                    <option value="태국">🇹🇭 태국</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="write-label"><i class="bi bi-geo-alt"></i> 여행 장소</label>
                <input
                    type="text"
                    name="location"
                    class="form-control write-input"
                    placeholder="방문한 도시나 장소"
                >
            </div>

        </div>

        <div class="row mb-4">

            <div class="col-md-6 mb-3 mb-md-0">
                <label class="write-label"><i class="bi bi-star"></i> 별점</label>
                <div class="star-rating-input" id="starRating">
                    <span data-value="1">★</span>
                    <span data-value="2">★</span>
                    <span data-value="3">★</span>
                    <span data-value="4">★</span>
                    <span data-value="5">★</span>
                </div>
                <input type="hidden" name="rating" id="ratingValue" value="5">
            </div>

            <div class="col-md-6">
                <label class="write-label"><i class="bi bi-calendar3"></i> 여행 날짜</label>
                <input
                    type="date"
                    name="travel_date"
                    class="form-control write-input"
                >
            </div>

        </div>

        <div class="mb-4">
            <label class="write-label">여행 이야기</label>
            <textarea
                name="content"
                rows="8"
                class="form-control write-input"
                placeholder="여행에서 경험한 이야기를 자세히 들려주세요..."
            ></textarea>
        </div>

        <div class="mb-5">
            <label class="write-label"><i class="bi bi-images"></i> 여행 사진 / 영상</label>
            <div class="upload-area" id="uploadArea">
                <input
                    type="file"
                    name="media[]"
                    id="mediaInput"
                    class="upload-input"
                    multiple
                    accept=".jpg,.jpeg,.png,video/mp4,video/webm,video/quicktime"
                >
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <div class="upload-icon"><i class="bi bi-cloud-upload" style="font-size:2.5rem;color:#7a9ab8;"></i></div>
                    <div>클릭하거나 파일을 드래그하세요</div>
                    <div class="upload-hint">이미지 JPG/PNG 최대 20MB · 영상 MP4/WEBM/MOV 최대 200MB · 첫 번째 파일이 대표 이미지</div>
                </div>
                <div id="previewContainer" class="preview-container"></div>
            </div>
        </div>

        <button type="submit" class="btn-write-submit">
            <i class="bi bi-send"></i> 여행 등록하기
        </button>

    </form>

</div>

<script>

// 별점
const stars = document.querySelectorAll('#starRating span');
const ratingInput = document.getElementById('ratingValue');
let currentRating = 5;

function updateStars(rating) {
    stars.forEach((s, i) => {
        s.classList.toggle('active', i < rating);
    });
}

stars.forEach((star, index) => {
    star.addEventListener('mouseover', () => updateStars(index + 1));
    star.addEventListener('click', () => {
        currentRating = index + 1;
        ratingInput.value = currentRating;
        updateStars(currentRating);
    });
});

document.getElementById('starRating').addEventListener('mouseleave', () => {
    updateStars(currentRating);
});

updateStars(currentRating);

// 파일 미리보기
const mediaInput = document.getElementById('mediaInput');
const previewContainer = document.getElementById('previewContainer');
const uploadArea = document.getElementById('uploadArea');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');

uploadArea.addEventListener('click', (e) => {
    if (e.target !== mediaInput) mediaInput.click();
});

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    showPreviews(e.dataTransfer.files);
});

mediaInput.addEventListener('change', () => {
    showPreviews(mediaInput.files);
});

function showPreviews(files) {
    previewContainer.innerHTML = '';

    if (files.length === 0) {
        uploadPlaceholder.style.display = 'block';
        return;
    }

    uploadPlaceholder.style.display = 'none';

    Array.from(files).forEach((file, i) => {

        const div = document.createElement('div');
        div.className = 'preview-item';

        if (file.type.startsWith('image/')) {

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            div.appendChild(img);

        } else {

            const video = document.createElement('video');
            video.src = URL.createObjectURL(file);
            video.muted = true;
            div.appendChild(video);

            const badge = document.createElement('span');
            badge.className = 'preview-badge';
            badge.textContent = '▶ VIDEO';
            div.appendChild(badge);
        }

        if (i === 0) {
            const thumbBadge = document.createElement('span');
            thumbBadge.className = 'preview-thumb-badge';
            thumbBadge.textContent = '⭐ 대표';
            div.appendChild(thumbBadge);
        }

        previewContainer.appendChild(div);
    });
}

</script>

<?php include "../includes/footer.php"; ?>
