<?php

session_start();

require_once "../config/db.php";
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

        $sql = "
            INSERT INTO reviews
            (user_id, title, content, location, country, rating, travel_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute([
            $_SESSION["user_id"],
            $title,
            $content,
            $location,
            $country,
            $rating,
            $travel_date
        ]);

        if ($result) {

            // 새 리뷰 ID
            $review_id = $pdo->lastInsertId();

            // 이미지/영상 업로드 처리: EC2 로컬 디스크가 아니라 S3에 저장하고, DB에는 CloudFront URL을 저장합니다.
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
                        $sql = "
                            INSERT INTO review_media
                            (review_id, file_path, file_type, is_thumbnail)
                            VALUES (?, ?, ?, ?)
                        ";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $review_id,
                            $uploaded["file_path"],
                            $uploaded["file_type"],
                            $is_thumbnail
                        ]);
                    }
                }
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

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow">

            <div class="card-body">

                <h2 class="mb-4">
                    리뷰 작성
                </h2>

                <?php if($message): ?>
                    <div class="alert alert-danger">
                        <?= $message ?>
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
                        >
                        <div class="mb-3">

                            <label class="form-label">
                                나라
                            </label>

                            <select
                                name="country"
                                class="form-select"
                            >

                                <option value="">
                                    나라 선택
                                </option>

                                <option value="한국">
                                    🇰🇷 한국
                                </option>

                                <option value="일본">
                                    🇯🇵 일본
                                </option>

                                <option value="미국">
                                    🇺🇸 미국
                                </option>

                                <option value="프랑스">
                                    🇫🇷 프랑스
                                </option>

                                <option value="영국">
                                    🇬🇧 영국
                                </option>

                                <option value="태국">
                                    🇹🇭 태국
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            별점
                        </label>

                        <select
                            name="rating"
                            class="form-select"
                        >
                            <option value="5">5점</option>
                            <option value="4">4점</option>
                            <option value="3">3점</option>
                            <option value="2">2점</option>
                            <option value="1">1점</option>
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
                        ></textarea>
                        <div class="mb-3">

                            <label class="form-label">
                                여행 사진 / 영상
                            </label>

                            <input
                                type="file"
                                name="media[]"
                                class="form-control"
                                multiple
                                accept="image/*,video/*"
                            >

                        </div>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        리뷰 등록
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>