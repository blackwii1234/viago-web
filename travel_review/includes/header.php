<!DOCTYPE html>
<html lang="ko">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>ViaGo</title>

    <!-- Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700;900&family=Poppins:wght@700;900&display=swap" rel="stylesheet">

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- jsVectorMap -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css"
    />

    <link
        rel="stylesheet"
        href="/travel_review/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    />

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    />
</head>

<body style="padding-top: 130px;">

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="main-header">

    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">

        <div class="container d-flex justify-content-between align-items-center">

            <!-- 왼쪽 -->
            <div class="d-flex align-items-center gap-4 nav-left-group">

                <a
                    class="navbar-brand fw-bold mb-0"
                    href="/travel_review/index.php"
                >
                    ViaGo
                </a>

                <?php if(isset($_SESSION["user_id"])): ?>

                    <div class="country-wrapper">

                        <button
                            id="country-toggle"
                            class="country-toggle"
                        >
                            <i class="bi bi-globe2"></i> 나라별
                        </button>

                        <div
                            id="country-dropdown"
                            class="country-dropdown"
                        >

                            <a href="/travel_review/reviews/list.php?country=한국">
                                🇰🇷 한국
                            </a>

                            <a href="/travel_review/reviews/list.php?country=일본">
                                🇯🇵 일본
                            </a>

                            <a href="/travel_review/reviews/list.php?country=미국">
                                🇺🇸 미국
                            </a>

                            <a href="/travel_review/reviews/list.php?country=프랑스">
                                🇫🇷 프랑스
                            </a>

                            <a href="/travel_review/reviews/list.php?country=영국">
                                🇬🇧 영국
                            </a>

                            <a href="/travel_review/reviews/list.php?country=태국">
                                🇹🇭 태국
                            </a>

                        </div>

                    </div>

                    <!-- 리뷰 작성 -->
                    <a
                        href="/travel_review/reviews/write.php"
                        class="nav-link-custom"
                    >
                        <i class="bi bi-pencil-square"></i> 리뷰작성
                    </a>

                    <!-- 내 프로필 -->
                    <a
                        href="/travel_review/users/profile.php"
                        class="nav-link-custom"
                    >
                        <i class="bi bi-person"></i> 내프로필
                    </a>

                <?php endif; ?>

            </div>

            <!-- 오른쪽 -->
            <div class="d-flex align-items-center gap-3">

                <?php if(isset($_SESSION["user_id"])): ?>

                    <div class="nav-username">

                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($_SESSION["username"]) ?>님

                    </div>

                    <!-- 로그아웃 -->
                    <a
                        href="/travel_review/auth/logout.php"
                        class="logout-link"
                    >
                        <i class="bi bi-box-arrow-right"></i> 로그아웃
                    </a>

                <?php endif; ?>

                <div class="menu-wrapper">

                    <button
                        id="menu-toggle"
                        class="menu-toggle"
                    >
                        <i class="bi bi-list"></i>
                    </button>

                    <div
                        id="menu-dropdown"
                        class="menu-dropdown"
                    >

                        <?php if(isset($_SESSION["user_id"])): ?>

                            <?php if(($_SESSION["role"] ?? "") === "admin"): ?>
                                <a href="/travel_review/admin/index.php">
                                    <i class="bi bi-shield-lock"></i> 관리자 페이지
                                </a>
                            <?php endif; ?>

                            <a href="#">
                                <i class="bi bi-gear"></i> 설정
                            </a>

                        <?php else: ?>

                            <a href="/travel_review/auth/login.php">
                                로그인
                            </a>

                            <a href="/travel_review/auth/register.php">
                                회원가입
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </nav>

    <!-- 검색창 -->

    <section class="top-search-section">

        <form
            action="/travel_review/reviews/list.php"
            method="GET"
            class="top-search-form"
        >

            <input
                type="text"
                name="search"
                class="top-search-input"
                placeholder="어디로 떠나고 싶나요?"
            >

            <button
                type="submit"
                class="top-search-btn"
            >
                <i class="bi bi-search"></i>
            </button>

        </form>

    </section>

</header>

<div class="container mt-4">
<script>

const toggle =
    document.getElementById("menu-toggle");

const dropdown =
    document.getElementById("menu-dropdown");

if (toggle) {

    toggle.addEventListener("click", (e) => {

        e.stopPropagation();

        dropdown.classList.toggle("show");
    });

    // 바깥 클릭 시 닫기

    document.addEventListener("click", (e) => {

        if (
            !toggle.contains(e.target) &&
            !dropdown.contains(e.target)
        ) {

            dropdown.classList.remove("show");
        }
    });
}

const countryToggle =
    document.getElementById("country-toggle");

const countryDropdown =
    document.getElementById("country-dropdown");

if (countryToggle) {

    countryToggle.addEventListener("click", (e) => {

        e.stopPropagation();

        countryDropdown.classList.toggle("show");
    });

    document.addEventListener("click", (e) => {

        if (
            !countryToggle.contains(e.target) &&
            !countryDropdown.contains(e.target)
        ) {

            countryDropdown.classList.remove("show");
        }
    });
}
</script>