<?php

session_start();
require_once "../config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {

        $message = "이메일과 비밀번호를 입력하세요.";

    } else {

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if ($user) {

            if (password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                header("Location: ../index.php");
                exit;

            } else {

                $message = "비밀번호가 틀렸습니다.";
            }

        } else {

            $message = "존재하지 않는 이메일입니다.";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="auth-wrap">

    <div class="auth-card">

        <div class="auth-card-top">
            <div class="auth-card-logo">ViaGo</div>
            <div class="auth-card-subtitle">EXPLORE THE WORLD</div>
        </div>

        <div class="auth-card-body">

            <h3 class="auth-card-title">로그인</h3>

            <?php if($message): ?>
                <div class="alert alert-danger" style="border-radius: 12px; font-size: 0.9rem;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="auth-label">이메일</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control auth-input"
                        placeholder="이메일을 입력하세요"
                    >
                </div>

                <div class="mb-4">
                    <label class="auth-label">비밀번호</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control auth-input"
                        placeholder="비밀번호를 입력하세요"
                    >
                </div>

                <button type="submit" class="btn-auth-submit">
                    로그인
                </button>

            </form>

        </div>

        <div class="auth-card-footer">
            아직 계정이 없으신가요?
            <a href="register.php">회원가입</a>
        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>
