<!DOCTYPE html>
<html lang="ko">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>ViaGo</title>

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
</head>

<body>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar">

    <div class="container">

        <a
            class="navbar-brand fw-bold"
            href="/travel_review/index.php"
        >
            ViaGo
        </a>

        <div class="menu-wrapper">

            <div class="d-flex align-items-center gap-3">

    <?php if(isset($_SESSION["user_id"])): ?>

        <div class="nav-username">

            👋
            <?= htmlspecialchars($_SESSION["username"]) ?>님

        </div>

    <?php endif; ?>

    <div class="menu-wrapper">

            <button
                id="menu-toggle"
                class="menu-toggle"
            >
                ☰
            </button>

            <div
                id="menu-dropdown"
                class="menu-dropdown"
            >

                <?php if(isset($_SESSION["user_id"])): ?>

                    <a href="/travel_review/reviews/write.php">
                        ✈️ 리뷰 작성
                    </a>

                    <a href="/travel_review/users/profile.php"
                       class="dropdown-item"
                    >
                        👤 내 프로필
                    </a>

                    <a href="#">
                        📝 내 리뷰
                    </a>

                    <a href="/travel_review/auth/logout.php">
                        🚪 로그아웃
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

</nav>

<div class="container mt-4">

<script>

const toggle =
    document.getElementById("menu-toggle");

const dropdown =
    document.getElementById("menu-dropdown");

if (toggle) {

    toggle.addEventListener("click", () => {

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

</script>