<?php

require_once "../config/db.php";

$search  = $_GET["search"] ?? "";
$country = $_GET["country"] ?? "";
$page    = max(1, (int)($_GET["page"] ?? 1));
$perPage = 9;

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

$popularWhere  = ["reviews.status != 'flagged'"];
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

$where  = ["reviews.status != 'flagged'"];
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

// 전체 개수 (페이지네이션용)
$countSql = "SELECT COUNT(*) FROM reviews JOIN users ON reviews.user_id = users.id";
if ($where) {
    $countSql .= " WHERE " . implode(" AND ", $where);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// 최신순 정렬 + 페이지네이션
$sql .= "
    ORDER BY reviews.id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$reviews = $stmt->fetchAll();

// 페이지 URL 빌더
$baseParams = array_filter(['search' => $search, 'country' => $country]);
function pageUrl(array $base, int $p): string {
    $base['page'] = $p;
    return '?' . http_build_query($base);
}

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
            <i class="bi bi-fire" style="color:#ff6b35;font-size:1.3rem;"></i> 인기 리뷰
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

<div class="row">

<?php foreach($reviews as $review): ?>

    <div class="col-md-4 mb-4">

        <a
            href="detail.php?id=<?= $review["id"] ?>"
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

                <div class="review-card-top">
                    <span class="review-card-country">
                        🌍 <?= htmlspecialchars($review["country"]) ?>
                    </span>
                    <span class="review-card-stars">
                        <?= str_repeat('★', $review["rating"]) ?><span class="review-card-empty-stars"><?= str_repeat('★', 5 - $review["rating"]) ?></span>
                    </span>
                </div>

                <h5 class="review-card-title">
                    <?= htmlspecialchars($review["title"]) ?>
                </h5>

                <div class="review-card-meta">
                    <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($review["location"]) ?></span>
                    <span><i class="bi bi-person"></i> <?= htmlspecialchars($review["username"]) ?></span>
                    <span><i class="bi bi-eye"></i> <?= $review["views"] ?></span>
                </div>

            </div>

        </a>

    </div>

<?php endforeach; ?>

</div>

<?php if (empty($reviews)): ?>

<div class="empty-state">

    <div class="empty-icon">
        <i class="bi bi-<?= $search ? 'search' : 'map' ?>"></i>
    </div>

    <h3 class="empty-title">
        <?php if ($search): ?>
            검색 결과가 없어요
        <?php elseif ($country): ?>
            <?= htmlspecialchars($country) ?>의 리뷰가 아직 없어요
        <?php else: ?>
            아직 리뷰가 없어요
        <?php endif; ?>
    </h3>

    <p class="empty-desc">
        <?= $search
            ? '"' . htmlspecialchars($search) . '"에 대한 결과를 찾지 못했어요'
            : '첫 번째 여행 리뷰를 남겨보세요!' ?>
    </p>

    <?php if (!$search && isset($_SESSION["user_id"])): ?>
        <a href="/travel_review/reviews/write.php" class="btn btn-primary btn-lg">
            <i class="bi bi-pencil-square"></i> 리뷰 작성하기
        </a>
    <?php endif; ?>

</div>

<?php endif; ?>

<?php if ($totalPages > 1): ?>

<div class="pagination-wrap">

    <?php if ($page > 1): ?>
        <a href="<?= pageUrl($baseParams, $page - 1) ?>" class="page-btn">
            <i class="bi bi-chevron-left"></i>
        </a>
    <?php endif; ?>

    <?php
        $pStart = max(1, $page - 2);
        $pEnd   = min($totalPages, $page + 2);
    ?>

    <?php if ($pStart > 1): ?>
        <a href="<?= pageUrl($baseParams, 1) ?>" class="page-btn">1</a>
        <?php if ($pStart > 2): ?>
            <span class="page-dots">…</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $pStart; $i <= $pEnd; $i++): ?>
        <a href="<?= pageUrl($baseParams, $i) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($pEnd < $totalPages): ?>
        <?php if ($pEnd < $totalPages - 1): ?>
            <span class="page-dots">…</span>
        <?php endif; ?>
        <a href="<?= pageUrl($baseParams, $totalPages) ?>" class="page-btn"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= pageUrl($baseParams, $page + 1) ?>" class="page-btn">
            <i class="bi bi-chevron-right"></i>
        </a>
    <?php endif; ?>

</div>

<?php endif; ?>

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