<?php

session_start();
require_once "../config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    if (
        empty($username) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = "모든 항목을 입력하세요.";

    } elseif ($password !== $confirm_password) {

        $message = "비밀번호가 일치하지 않습니다.";

    } else {

        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {

            $message = "이미 사용중인 이메일입니다.";

        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "
                INSERT INTO users
                (username, email, password)
                VALUES (?, ?, ?)
            ";

            $stmt = $pdo->prepare($sql);

            $result = $stmt->execute([
                $username,
                $email,
                $hashed_password
            ]);

            if ($result) {

                header("Location: login.php");
                exit;

            } else {

                $message = "회원가입 실패";
            }
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

            <h3 class="auth-card-title">회원가입</h3>

            <?php if($message): ?>
                <div class="alert alert-danger" style="border-radius: 12px; font-size: 0.9rem;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="auth-label">사용자 이름</label>
                    <input
                        type="text"
                        name="username"
                        class="form-control auth-input"
                        placeholder="닉네임을 입력하세요"
                    >
                </div>

                <div class="mb-3">
                    <label class="auth-label">이메일</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control auth-input"
                        placeholder="이메일을 입력하세요"
                    >
                </div>

                <div class="mb-3">
                    <label class="auth-label">비밀번호</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control auth-input"
                        placeholder="비밀번호를 입력하세요"
                    >
                </div>

                <div class="mb-4">
                    <label class="auth-label">비밀번호 확인</label>
                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control auth-input"
                        placeholder="비밀번호를 다시 입력하세요"
                    >
                </div>

                <button type="submit" class="btn-auth-submit">
                    회원가입
                </button>

            </form>

        </div>

        <div class="auth-card-footer">
            이미 계정이 있으신가요?
            <a href="login.php">로그인</a>
        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>
