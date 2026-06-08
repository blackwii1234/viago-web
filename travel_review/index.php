<?php

require_once "config/db.php";

/* 나라별 TOP 3 */

$topCountriesSql = "
    SELECT
        country,
        COUNT(*) AS review_count
    FROM reviews
    WHERE status != 'flagged'
    GROUP BY country
    ORDER BY review_count DESC
    LIMIT 3
";

$topCountries =
    $pdo->query($topCountriesSql)->fetchAll();

$countryImages = [
    "한국"   => "https://images.unsplash.com/photo-1517154421773-0529f29ea451",
    "일본"   => "https://images.unsplash.com/photo-1492571350019-22de08371fd3",
    "미국"   => "https://images.unsplash.com/photo-1496588152823-86ff7695e68f",
    "프랑스" => "https://images.unsplash.com/photo-1502602898657-3e91760cbb34",
    "영국"   => "https://images.unsplash.com/photo-1513635269975-59663e0ac1ad",
    "태국"   => "https://images.unsplash.com/photo-1508009603885-50cf7c579365",
];

$countryFlags = [
    "한국"   => "🇰🇷",
    "일본"   => "🇯🇵",
    "미국"   => "🇺🇸",
    "프랑스" => "🇫🇷",
    "영국"   => "🇬🇧",
    "태국"   => "🇹🇭",
];


/* 최신 리뷰 */

$latestSql = "

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

    WHERE reviews.status != 'flagged'

    GROUP BY reviews.id

    ORDER BY reviews.id DESC

    LIMIT 6
";

$latestStmt =
    $pdo->query($latestSql);

$latestReviews =
    $latestStmt->fetchAll();
?>

<?php include "includes/header.php"; ?>

<?php if (!empty($_SESSION["moderation_message"])): ?>
    <div class="alert alert-warning" style="max-width:1100px;margin:24px auto 0;border-radius:14px;">
        <?= htmlspecialchars($_SESSION["moderation_message"], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION["moderation_message"]); ?>
<?php elseif (($_GET["moderation"] ?? "") === "flagged"): ?>
    <div class="alert alert-warning" style="max-width:1100px;margin:24px auto 0;border-radius:14px;">
        광고성 게시글로 의심되어 관리자 검토 후 공개됩니다.
    </div>
<?php endif; ?>

<section class="main-screen">

    <!-- 별 배경 -->

    <div class="stars"></div>

    <div class="content">

        <!-- 로고 -->

        <h1 class="logo">
            ViaGo
        </h1>

        <p class="subtitle">
            Explore The World
        </p>

        <!-- 안내 -->

        <p class="guide-text">

            클릭 가능한 국가에서
            여행 리뷰를 탐험해보세요

        </p>

        <!-- 현재 지원 국가 -->
<!-- 
        <div class="supported mb-4">

            🇰🇷 한국
            🇯🇵 일본
            🇺🇸 미국
            🇫🇷 프랑스
            🇬🇧 영국
            🇹🇭 태국

        </div>
 -->
        <?php if(isset($_SESSION["user_id"])): ?>

            <a
                href="reviews/write.php"
                class="write-btn"
            >
                <i class="bi bi-pencil-square"></i> 여행 기록하기
            </a>

        <?php endif; ?>

        <!-- 지구 -->

        <div class="globe-wrapper">

            <div id="world-map"></div>

            <!-- 국기 마커 -->

            <a
                href="reviews/list.php?country=한국"
                class="flag-marker korea"
                title="한국"
            >
                🇰🇷
            </a>

            <a
                href="reviews/list.php?country=일본"
                class="flag-marker japan"
                title="일본"
            >
                🇯🇵
            </a>

            <a
                href="reviews/list.php?country=미국"
                class="flag-marker usa"
                title="미국"
            >
                🇺🇸
            </a>

            <a
                href="reviews/list.php?country=프랑스"
                class="flag-marker france"
                title="프랑스"
            >
                🇫🇷
            </a>

            <a
                href="reviews/list.php?country=영국"
                class="flag-marker uk"
                title="영국"
            >
                🇬🇧
            </a>

            <a
                href="reviews/list.php?country=태국"
                class="flag-marker thailand"
                title="태국"
            >
                🇹🇭
            </a>

            <!-- 준비중 국가 -->

            <div
                class="flag-marker canada disabled"
                title="준비중"
            >
                🇨🇦
            </div>

            <div
                class="flag-marker australia disabled"
                title="준비중"
            >
                🇦🇺
            </div>

        </div>

        <!-- hover 국가명 -->

        <div id="country-name"></div>

    </div>

</section>
<div class="container py-5">

    <!-- TOP 3 나라 -->

    <section class="mb-5">

        <div class="section-title">
            <i class="bi bi-bar-chart-fill" style="color:var(--primary);font-size:1.3rem;"></i> 지금 뜨는 나라 TOP 3
        </div>

        <div class="top3-grid">

            <?php foreach($topCountries as $i => $row):
                $img  = $countryImages[$row["country"]] ?? "";
                $flag = $countryFlags[$row["country"]] ?? "";
            ?>

                <a
                    href="reviews/list.php?country=<?= urlencode($row["country"]) ?>"
                    class="top3-card"
                >

                    <div
                        class="top3-circle"
                        style="background-image: url('<?= $img ?>?w=500&h=500&fit=crop&auto=format')"
                    >

                        <div class="top3-overlay">
                            <div class="top3-rank-badge"><?= $i + 1 ?></div>
                        </div>

                    </div>

                    <div class="top3-info">

                        <div class="top3-flag"><?= $flag ?></div>

                        <div class="top3-name">
                            <?= htmlspecialchars($row["country"]) ?>
                        </div>

                        <div class="top3-count">
                            리뷰 <?= $row["review_count"] ?>개
                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </section>

    <!-- 최신 여행 -->

    <section>

        <div class="section-title">
            <i class="bi bi-images" style="color:var(--primary);font-size:1.3rem;"></i> 최신 여행
        </div>

        <div class="row">

            <?php foreach($latestReviews as $review): ?>

                <div class="col-md-4 mb-4">

                    <a
                        href="reviews/detail.php?id=<?= $review["id"] ?>"
                        class="travel-card"
                    >

                        <?php if($review["media"]): ?>

                            <?php if($review["media_type"] == "image"): ?>

                                <img
                                    src="<?= htmlspecialchars(media_url($review["media"])) ?>"
                                    class="travel-card-img"
                                    alt=""
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <video
                                    class="travel-card-img"
                                    autoplay
                                    muted
                                    loop
                                    playsinline
                                >
                                    <source src="<?= htmlspecialchars(media_url($review["media"])) ?>">
                                </video>

                            <?php endif; ?>

                        <?php else: ?>

                            <div class="travel-card-no-img"></div>

                        <?php endif; ?>

                        <div class="travel-card-overlay">

                            <span class="travel-card-country">
                                <?= htmlspecialchars($review["country"]) ?>
                            </span>

                            <h5 class="travel-card-title">
                                <?= htmlspecialchars($review["title"]) ?>
                            </h5>

                            <div class="travel-card-meta">
                                <span>📍 <?= htmlspecialchars($review["location"]) ?></span>
                                <span>👤 <?= htmlspecialchars($review["username"]) ?></span>
                            </div>

                        </div>

                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

</div>

<script>

document.addEventListener("DOMContentLoaded", function() {

    const enabledCountries = {

        KR: "한국",
        JP: "일본",
        US: "미국",
        FR: "프랑스",
        GB: "영국",
        TH: "태국"
    };

    const countryLabel =
        document.getElementById("country-name");

    // 지도 초기화

    document.getElementById(
        "world-map"
    ).innerHTML = "";

    const map = new jsVectorMap({

        selector: "#world-map",

        map: "world",

        zoomButtons: false,

        backgroundColor: "transparent",

        regionStyle: {

            initial: {

                fill: "#5ba8ff",

                stroke: "#111",

                strokeWidth: 0.5
            },

            hover: {

                fill: "#00d4ff"
            }
        },

        onRegionTooltipShow:
        function(event, tooltip, code) {

            if (enabledCountries[code]) {

                tooltip.text(
                    "🌍 "
                    + enabledCountries[code]
                    + " · 이용 가능"
                );

            } else {

                tooltip.text(
                    "🚧 준비중"
                );
            }
        },

        onRegionOver:
        function(event, code) {

            if (enabledCountries[code]) {

                countryLabel.innerText =
                    "🌍 "
                    + enabledCountries[code];

            } else {

                countryLabel.innerText =
                    "🚧 구현 준비중";
            }
        },

        onRegionOut: function() {

            countryLabel.innerText = "";
        },

        onRegionClick:
        function(event, code) {

            if (enabledCountries[code]) {

                location.href =
                    "reviews/list.php?country="
                    + encodeURIComponent(
                        enabledCountries[code]
                    );
            }
        }
    });

});

</script>

<?php include "includes/footer.php"; ?>