<?php

session_start();
require_once "../config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // 빈 값 체크
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

        // 이메일 중복 체크
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {

            $message = "이미 사용중인 이메일입니다.";

        } else {

            // 비밀번호 암호화
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // 회원 저장
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

<div class="row justify-content-center">
    <div class="col-md-6">

        <div class="card shadow">
            <div class="card-body">

                <h2 class="mb-4 text-center">
                    회원가입
                </h2>

                <?php if($message): ?>
                    <div class="alert alert-danger">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="mb-3">
                        <label class="form-label">
                            사용자 이름
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            이메일
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            비밀번호
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            비밀번호 확인
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                        >
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        회원가입
                    </button>

                </form>

            </div>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>