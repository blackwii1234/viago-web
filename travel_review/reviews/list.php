<?php

require_once "../config/db.php";

$search = $_GET["search"] ?? "";
$country = $_GET["country"] ?? "";

$popularSql = "
    SELECT
        reviews.*,
        users.username,
        review_media.file_path AS media,
        review_media.file_type AS media_type

    FROM reviews

    JOIN users
    ON reviews.user_id = users.id

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
";

$popularWhere = [];
$popularParams = [];

// 나라 필터
if ($country) {

    $popularWhere[] = "
        reviews.country = ?
    ";

    $popularParams[] = $country;
}

// WHERE 조합
if ($popularWhere) {

    $popularSql .= "
        WHERE
        " . implode(" AND ", $popularWhere);
}

$popularSql .= "
    ORDER BY reviews.views DESC
    LIMIT 5
";

// 실행
$popularStmt =
    $pdo->prepare($popularSql);

$popularStmt->execute($popularParams);

$popularReviews =
    $popularStmt->fetchAll();

$sql = "
    SELECT
        reviews.*,
        users.username,
        review_media.file_path AS media,
        review_media.file_type AS media_type

    FROM reviews

    JOIN users
    ON reviews.user_id = users.id

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
";

$where = [];
$params = [];

// 검색 기능
if ($search) {

    $where[] = "
        (
            reviews.title LIKE ?
            OR reviews.location LIKE ?
        )
    ";

    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
}

// 나라 필터
if ($country) {

    $where[] = "
        reviews.country = ?
    ";

    $params[] = $country;
}

// WHERE 조합
if ($where) {

    $sql .= "
        WHERE
        " . implode(" AND ", $where);
}

// 최신순 정렬
$sql .= "
    ORDER BY reviews.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$reviews = $stmt->fetchAll();

$countryImages = [

    "한국" =>
    "https://images.unsplash.com/photo-1517154421773-0529f29ea451",

    "일본" =>
    "https://images.unsplash.com/photo-1492571350019-22de08371fd3",

    "미국" =>
    "https://images.unsplash.com/photo-1496588152823-86ff7695e68f",

    "프랑스" =>
    "https://images.unsplash.com/photo-1502602898657-3e91760cbb34",

    "영국" =>
    "https://images.unsplash.com/photo-1513635269975-59663e0ac1ad",

    "태국" =>
    "https://images.unsplash.com/photo-1508009603885-50cf7c579365"
];

$heroImage =
    $countryImages[$country]
    ?? $countryImages["일본"];

?>

<?php include "../includes/header.php"; ?>


<section
    class="country-hero"
    style="
        background-image:
        url('<?= $heroImage ?>');
    "
>

    <div class="overlay">

        <h1>

            🌍
            <?= htmlspecialchars($country ?: "ViaGo") ?>

        </h1>

        <p>
            Explore amazing travel reviews
        </p>

    </div>

</section>

<?php if($popularReviews): ?>

<section class="mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="section-title mb-0">
            🔥 인기 리뷰
        </h2>

    </div>

    <div class="swiper popularSwiper">

        <div class="swiper-wrapper">

            <?php foreach($popularReviews as $popular): ?>

                <div class="swiper-slide">

                    <a
                        href="detail.php?id=<?= $popular["id"] ?>"
                        class="text-decoration-none"
                    >

                        <div class="popular-card">

                            <?php if(!empty($popular["media"])): ?>

                                <?php if($popular["media_type"] == "image"): ?>

                                    <img
                                        src="<?= htmlspecialchars(media_url($popular["media"])) ?>"
                                        class="popular-card-image"
                                    >

                                <?php elseif($popular["media_type"] == "video"): ?>

                                    <video
                                        class="popular-card-image"
                                        muted
                                        autoplay
                                        loop
                                        playsinline
                                        preload="metadata"
                                    >

                                        <source
                                            src="<?= htmlspecialchars(media_url($popular["media"])) ?>"
                                        >

                                    </video>

                                <?php endif; ?>

                            <?php endif; ?>

                            <div class="popular-card-overlay">

                                <span class="badge bg-danger mb-2">
                                    🔥 HOT
                                </span>

                                <h3>
                                    <?= htmlspecialchars($popular["title"]) ?>
                                </h3>

                                <p>

                                    🌍
                                    <?= htmlspecialchars($popular["country"]) ?>

                                    ·

                                    👁 <?= $popular["views"] ?>

                                </p>

                            </div>

                        </div>

                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<?php endif; ?>

<section class="search-section mb-5">

    <div class="search-box">

        <div class="search-title">

            🔎 여행지 탐색

        </div>

        <form method="GET">

            <?php if($country): ?>

                <input
                    type="hidden"
                    name="country"
                    value="<?= htmlspecialchars($country) ?>"
                >

            <?php endif; ?>

            <div class="search-input-wrap">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="어디로 떠나고 싶나요?"
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button
                    class="search-btn"
                    type="submit"
                >
                    검색
                </button>

            </div>

        </form>

    </div>

</section>

<div class="row">

<?php foreach($reviews as $review): ?>

    <div class="col-md-4 mb-4">

        <div class="review-card h-100">

            <?php if(!empty($review["media"])): ?>

                <div class="media-preview">

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
                            preload="metadata"
                        >

                            <source
                                src="<?= htmlspecialchars(media_url($review["media"])) ?>"
                            >

                        </video>

                        <div class="video-badge">

                            ▶ VIDEO

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <div class="card-body">

                <h5>
                    <?= htmlspecialchars($review["title"]) ?>
                </h5>

                <p>
                    📍 <?= htmlspecialchars($review["location"]) ?>
                </p>

                <p class="mb-2">
                    🌍 <?= htmlspecialchars($review["country"]) ?>
                </p>

                <p>
                    ⭐ <?= $review["rating"] ?>/5
                </p>

                <p>
                    👁 <?= $review["views"] ?>
                </p>

                <p>
                    작성자:
                    <?= htmlspecialchars($review["username"]) ?>
                </p>

                <a
                    href="detail.php?id=<?= $review["id"] ?>"
                    class="btn btn-primary"
                >
                    상세보기
                </a>

            </div>

        </div>

    </div>

<?php endforeach; ?>

</div>
<script>

document.addEventListener("DOMContentLoaded", function() {

    new Swiper(".popularSwiper", {

        loop: true,

        spaceBetween: 25,

        autoplay: {

            delay: 3000,

            disableOnInteraction: false
        },

        breakpoints: {

            0: {

                slidesPerView: 1
            },

            768: {

                slidesPerView: 2
            },

            1200: {

                slidesPerView: 3
            }
        }
    });

});

</script>

<?php include "../includes/footer.php"; ?>