<?php
require_once __DIR__ . '/includes/auth_check.php';

// ─── POST 액션 처리 ───────────────────────────────
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $backTab = $_POST['back_tab'] ?? 'dashboard';

    switch ($action) {

        case 'toggle_role':
            $uid     = (int)$_POST['user_id'];
            $newRole = $_POST['new_role'] === 'admin' ? 'admin' : 'user';
            if ($uid !== (int)$_SESSION['user_id']) {
                $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $uid]);
            }
            break;

        case 'delete_user':
            $uid = (int)$_POST['user_id'];
            if ($uid !== (int)$_SESSION['user_id']) {
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            }
            break;

        case 'approve_review':
            $reviewId = (int)$_POST['review_id'];
            $pdo->prepare("UPDATE reviews SET status='normal' WHERE id=?")
                ->execute([$reviewId]);
            $pdo->prepare("UPDATE review_media SET moderation_status='normal' WHERE review_id=?")
                ->execute([$reviewId]);
            break;

        case 'delete_review':
            $pdo->prepare("DELETE FROM reviews WHERE id=?")
                ->execute([(int)$_POST['review_id']]);
            break;

        case 'dismiss_report':
            $pdo->prepare("DELETE FROM reports WHERE id=?")
                ->execute([(int)$_POST['report_id']]);
            break;

        case 'delete_report_target':
            $type     = $_POST['target_type'];
            $targetId = (int)$_POST['target_id'];
            $reportId = (int)$_POST['report_id'];
            if ($type === 'review') {
                $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$targetId]);
            } elseif ($type === 'comment') {
                $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$targetId]);
            }
            $pdo->prepare("DELETE FROM reports WHERE id=?")->execute([$reportId]);
            break;
    }

    header("Location: index.php?tab=$backTab&done=1");
    exit;
}

// ─── 데이터 로드 ──────────────────────────────────

// 대시보드 통계
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$todayUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalReviews   = (int)$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$todayReviews   = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$flaggedReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status!='normal'")->fetchColumn();
$totalReports   = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();

// 최근 회원 5명
$recentUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// 최근 리뷰 5개
$recentReviews = $pdo->query("
    SELECT reviews.id, reviews.title, reviews.country, reviews.status, reviews.created_at, users.username
    FROM reviews JOIN users ON reviews.user_id = users.id
    ORDER BY reviews.created_at DESC LIMIT 5
")->fetchAll();

// 나라별 분포
$countryStats = $pdo->query("
    SELECT country, COUNT(*) as cnt FROM reviews
    GROUP BY country ORDER BY cnt DESC LIMIT 6
")->fetchAll();
$maxCountry = $countryStats ? max(array_column($countryStats, 'cnt')) : 1;

// 회원 전체 (리뷰 수 포함)
$users = $pdo->query("
    SELECT users.*, COUNT(reviews.id) AS review_count
    FROM users
    LEFT JOIN reviews ON reviews.user_id = users.id
    GROUP BY users.id
    ORDER BY users.created_at DESC
")->fetchAll();

// 게시글 전체 (관리 대상)
$reviews = $pdo->query("
    SELECT reviews.*, users.username,
        (SELECT COUNT(*) FROM review_media WHERE review_media.review_id = reviews.id AND review_media.moderation_status = 'flagged') AS media_flagged_count,
        (SELECT COUNT(*) FROM review_media WHERE review_media.review_id = reviews.id AND review_media.moderation_status = 'blocked') AS media_blocked_count,
        (SELECT COALESCE(MAX(review_media.moderation_score), 0) FROM review_media WHERE review_media.review_id = reviews.id) AS image_score,
        (SELECT GROUP_CONCAT(DISTINCT review_media.moderation_reason SEPARATOR ', ') FROM review_media WHERE review_media.review_id = reviews.id AND review_media.moderation_reason IS NOT NULL AND review_media.moderation_reason <> '') AS image_reason
    FROM reviews JOIN users ON reviews.user_id = users.id
    ORDER BY
        CASE status WHEN 'reported' THEN 0 WHEN 'flagged' THEN 1 ELSE 2 END,
        reviews.created_at DESC
")->fetchAll();

$cntAll      = count($reviews);
$cntFlagged  = count(array_filter($reviews, fn($r) => $r['status'] === 'flagged'));
$cntReported = count(array_filter($reviews, fn($r) => $r['status'] === 'reported'));

// 신고 목록
$reports = $pdo->query("
    SELECT reports.*,
        reporter.username AS reporter_name,
        CASE reports.target_type
            WHEN 'review'  THEN (SELECT title   FROM reviews  WHERE id = reports.target_id)
            WHEN 'comment' THEN (SELECT content FROM comments WHERE id = reports.target_id)
        END AS target_preview
    FROM reports
    JOIN users AS reporter ON reporter.id = reports.user_id
    ORDER BY reports.created_at DESC
")->fetchAll();

// 활성 탭
$activeTab = $_GET['tab'] ?? 'dashboard';
$done      = isset($_GET['done']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ViaGo Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700;900&family=Poppins:wght@700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/travel_review/admin/assets/admin.css">
</head>
<body>

<!-- ─── 상단 헤더 ─── -->
<div class="admin-header">
    <div class="admin-brand">
        ViaGo <small>ADMIN</small>
    </div>
    <div class="admin-header-right">
        <span><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="/travel_review/index.php" class="site-link">
            <i class="bi bi-arrow-left-circle"></i> 사이트
        </a>
        <a href="/travel_review/auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> 로그아웃
        </a>
    </div>
</div>

<!-- ─── 탭 네비 ─── -->
<div class="admin-tabs-wrap">
    <ul class="nav admin-nav-tabs" id="adminTabs" role="tablist">

        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button">
                <i class="bi bi-speedometer2"></i> 대시보드
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-users" type="button">
                <i class="bi bi-people"></i> 회원 관리
                <span class="tab-badge blue"><?= count($users) ?></span>
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button">
                <i class="bi bi-file-earmark-text"></i> 게시글 검토
                <?php if ($flaggedReviews > 0): ?>
                    <span class="tab-badge"><?= $flaggedReviews ?></span>
                <?php endif; ?>
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reports" type="button">
                <i class="bi bi-flag"></i> 신고 관리
                <?php if ($totalReports > 0): ?>
                    <span class="tab-badge"><?= $totalReports ?></span>
                <?php endif; ?>
            </button>
        </li>

    </ul>
</div>

<!-- ─── 탭 콘텐츠 ─── -->
<div class="tab-content admin-tab-content">

    <?php if ($done): ?>
        <div class="admin-alert success">
            <i class="bi bi-check-circle-fill"></i> 처리됐어요.
        </div>
    <?php endif; ?>


    <!-- ══════════ 대시보드 ══════════ -->
    <div class="tab-pane fade" id="tab-dashboard" role="tabpanel">

        <!-- 통계 카드 -->
        <div class="stat-grid">

            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info">
                    <strong><?= number_format($totalUsers) ?></strong>
                    <span>전체 회원</span>
                    <?php if ($todayUsers > 0): ?>
                        <span class="stat-sub">+<?= $todayUsers ?> 오늘</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-green"><i class="bi bi-journals"></i></div>
                <div class="stat-info">
                    <strong><?= number_format($totalReviews) ?></strong>
                    <span>전체 리뷰</span>
                    <?php if ($todayReviews > 0): ?>
                        <span class="stat-sub">+<?= $todayReviews ?> 오늘</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="stat-info">
                    <strong><?= $flaggedReviews ?></strong>
                    <span>검토 필요 게시글</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon si-red"><i class="bi bi-flag-fill"></i></div>
                <div class="stat-info">
                    <strong><?= $totalReports ?></strong>
                    <span>처리 대기 신고</span>
                </div>
            </div>

        </div>

        <div class="dash-grid">

            <!-- 최근 가입 회원 -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="bi bi-person-plus" style="color:#0d6efd;"></i> 최근 가입 회원</h2>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>닉네임</th>
                            <th>이메일</th>
                            <th>권한</th>
                            <th>가입일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td class="muted"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge-<?= $u['role'] ?>"><?= $u['role'] === 'admin' ? '관리자' : '회원' ?></span>
                            </td>
                            <td class="muted"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 나라별 리뷰 분포 -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="bi bi-globe2" style="color:#0d6efd;"></i> 나라별 리뷰 분포</h2>
                </div>
                <div class="country-bar-wrap">
                    <?php foreach ($countryStats as $cs): ?>
                    <div class="country-bar-item">
                        <div class="country-bar-label"><?= htmlspecialchars($cs['country']) ?></div>
                        <div class="country-bar-track">
                            <div class="country-bar-fill" style="width:<?= round($cs['cnt'] / $maxCountry * 100) ?>%"></div>
                        </div>
                        <div class="country-bar-count"><?= $cs['cnt'] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($countryStats)): ?>
                        <div class="admin-empty"><i class="bi bi-bar-chart"></i><p>데이터 없음</p></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- 최근 리뷰 -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="bi bi-clock-history" style="color:#0d6efd;"></i> 최근 등록 리뷰</h2>
            </div>
            <table class="admin-table">
                <thead>
                    <tr><th>제목</th><th>작성자</th><th>나라</th><th>상태</th><th>등록일</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td class="muted"><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['country']) ?></td>
                        <td><span class="badge-<?= $r['status'] ?>"><?= ['normal'=>'정상','flagged'=>'검토필요','reported'=>'신고됨'][$r['status']] ?? $r['status'] ?></span></td>
                        <td class="muted"><?= date('m-d H:i', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /tab-dashboard -->


    <!-- ══════════ 회원 관리 ══════════ -->
    <div class="tab-pane fade" id="tab-users" role="tabpanel">

        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="bi bi-people" style="color:#0d6efd;"></i> 회원 목록</h2>
                <span style="font-size:0.82rem;color:#6c8ea4;">총 <?= count($users) ?>명</span>
            </div>

            <div class="admin-search">
                <input type="text" id="userSearch" placeholder="닉네임 또는 이메일 검색...">
            </div>

            <table class="admin-table" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>닉네임</th>
                        <th>이메일</th>
                        <th>권한</th>
                        <th>리뷰 수</th>
                        <th>가입일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr data-name="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                        data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>">
                        <td class="muted"><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td class="muted"><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge-<?= $u['role'] ?>"><?= $u['role'] === 'admin' ? '관리자' : '회원' ?></span></td>
                        <td><?= $u['review_count'] ?></td>
                        <td class="muted"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>

                                <!-- 권한 변경 -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="new_role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                    <input type="hidden" name="back_tab" value="users">
                                    <button type="submit" class="btn-act btn-role">
                                        <i class="bi bi-arrow-left-right"></i>
                                        <?= $u['role'] === 'admin' ? '회원으로' : '관리자로' ?>
                                    </button>
                                </form>

                                <!-- 삭제 -->
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('<?= htmlspecialchars($u['username']) ?> 회원을 삭제하시겠습니까?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="back_tab" value="users">
                                    <button type="submit" class="btn-act btn-del">
                                        <i class="bi bi-trash3"></i> 삭제
                                    </button>
                                </form>

                            <?php else: ?>
                                <span class="muted" style="font-size:0.78rem;">본인</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div><!-- /tab-users -->


    <!-- ══════════ 게시글 검토 ══════════ -->
    <div class="tab-pane fade" id="tab-reviews" role="tabpanel">

        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="bi bi-file-earmark-text" style="color:#0d6efd;"></i> 게시글 검토</h2>
            </div>

            <!-- 필터 -->
            <div class="status-filter">
                <button class="filter-btn active" data-filter="all">
                    전체 <span class="fc"><?= $cntAll ?></span>
                </button>
                <button class="filter-btn" data-filter="flagged">
                    <i class="bi bi-exclamation-triangle"></i> 검토필요 <span class="fc"><?= $cntFlagged ?></span>
                </button>
                <button class="filter-btn" data-filter="reported">
                    <i class="bi bi-flag"></i> 신고됨 <span class="fc"><?= $cntReported ?></span>
                </button>
                <button class="filter-btn" data-filter="normal">
                    <i class="bi bi-check-circle"></i> 정상
                </button>
            </div>

            <table class="admin-table" id="reviewsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>제목</th>
                        <th>작성자</th>
                        <th>나라</th>
                        <th>상태</th>
                        <th>AI 점수</th>
                        <th>이미지</th>
                        <th>등록일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <tr data-status="<?= $r['status'] ?>">
                        <td class="muted"><?= $r['id'] ?></td>
                        <td>
                            <span style="cursor:pointer;color:#0d6efd;font-weight:600;"
                                  onclick="showReviewModal(<?= htmlspecialchars(json_encode([
                                      'id'         => $r['id'],
                                      'title'      => $r['title'],
                                      'content'    => $r['content'],
                                      'username'   => $r['username'],
                                      'country'    => $r['country'],
                                      'location'   => $r['location'],
                                      'status'     => $r['status'],
                                      'ad_score'   => $r['ad_score'] ?? 0,
                                      'reason'     => $r['moderation_reason'] ?? '',
                                      'image_score' => $r['image_score'] ?? 0,
                                      'image_reason' => $r['image_reason'] ?? '',
                                      'media_flagged_count' => $r['media_flagged_count'] ?? 0,
                                      'media_blocked_count' => $r['media_blocked_count'] ?? 0,
                                      'created_at' => $r['created_at'],
                                  ]), ENT_QUOTES) ?>)">
                                <?= htmlspecialchars(mb_strimwidth($r['title'], 0, 30, '…')) ?>
                            </span>
                        </td>
                        <td class="muted"><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['country']) ?></td>
                        <td>
                            <span class="badge-<?= $r['status'] ?>">
                                <?= ['normal'=>'정상','flagged'=>'검토필요','reported'=>'신고됨'][$r['status']] ?? $r['status'] ?>
                            </span>
                        </td>
                        <td class="muted"><?= (int)($r['ad_score'] ?? 0) ?></td>
                        <td class="muted">
                            <?= (int)($r['image_score'] ?? 0) ?>
                            <?php if ((int)($r['media_flagged_count'] ?? 0) > 0): ?>
                                <span class="badge-flagged">이미지 검토</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted"><?= date('m-d H:i', strtotime($r['created_at'])) ?></td>
                        <td>
                            <!-- 원본 보기 -->
                            <a href="/travel_review/reviews/detail.php?id=<?= $r['id'] ?>"
                               target="_blank" class="btn-act btn-view">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>

                            <!-- 승인 (정상으로) -->
                            <?php if ($r['status'] !== 'normal'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve_review">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="back_tab" value="reviews">
                                <button type="submit" class="btn-act btn-approve">
                                    <i class="bi bi-check-circle"></i> 승인
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- 삭제 -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('이 리뷰를 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete_review">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="back_tab" value="reviews">
                                <button type="submit" class="btn-act btn-del">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($reviews)): ?>
                <div class="admin-empty">
                    <i class="bi bi-check2-circle"></i>
                    <p>검토할 게시글이 없어요</p>
                </div>
            <?php endif; ?>

        </div>
    </div><!-- /tab-reviews -->


    <!-- ══════════ 신고 관리 ══════════ -->
    <div class="tab-pane fade" id="tab-reports" role="tabpanel">

        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="bi bi-flag" style="color:#dc2626;"></i> 신고 목록</h2>
                <span style="font-size:0.82rem;color:#6c8ea4;">총 <?= count($reports) ?>건</span>
            </div>

            <?php if (empty($reports)): ?>
                <div class="admin-empty">
                    <i class="bi bi-shield-check"></i>
                    <p>처리 대기 중인 신고가 없어요</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>유형</th>
                        <th>내용 미리보기</th>
                        <th>신고자</th>
                        <th>사유</th>
                        <th>신고일</th>
                        <th>처리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rp): ?>
                    <tr>
                        <td>
                            <span class="badge-<?= $rp['target_type'] === 'review' ? 'flagged' : 'reported' ?>">
                                <?= $rp['target_type'] === 'review' ? '리뷰' : '댓글' ?>
                            </span>
                        </td>
                        <td style="max-width:260px;">
                            <span style="color:#3d5a80;">
                                <?= htmlspecialchars(mb_strimwidth($rp['target_preview'] ?? '(삭제됨)', 0, 50, '…')) ?>
                            </span>
                        </td>
                        <td class="muted"><?= htmlspecialchars($rp['reporter_name']) ?></td>
                        <td class="muted"><?= htmlspecialchars($rp['reason'] ?: '—') ?></td>
                        <td class="muted"><?= date('m-d H:i', strtotime($rp['created_at'])) ?></td>
                        <td>
                            <!-- 원본 보기 -->
                            <?php if ($rp['target_type'] === 'review' && $rp['target_id']): ?>
                            <a href="/travel_review/reviews/detail.php?id=<?= $rp['target_id'] ?>"
                               target="_blank" class="btn-act btn-view">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            <?php endif; ?>

                            <!-- 신고만 무시 -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="dismiss_report">
                                <input type="hidden" name="report_id" value="<?= $rp['id'] ?>">
                                <input type="hidden" name="back_tab" value="reports">
                                <button type="submit" class="btn-act btn-dismiss">
                                    <i class="bi bi-x-circle"></i> 무시
                                </button>
                            </form>

                            <!-- 컨텐츠 삭제 + 신고 처리 -->
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('해당 <?= $rp['target_type'] === 'review' ? '리뷰' : '댓글' ?>를 삭제하시겠습니까?')">
                                <input type="hidden" name="action" value="delete_report_target">
                                <input type="hidden" name="report_id" value="<?= $rp['id'] ?>">
                                <input type="hidden" name="target_type" value="<?= htmlspecialchars($rp['target_type']) ?>">
                                <input type="hidden" name="target_id" value="<?= $rp['target_id'] ?>">
                                <input type="hidden" name="back_tab" value="reports">
                                <button type="submit" class="btn-act btn-del">
                                    <i class="bi bi-trash3"></i> 삭제
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>
    </div><!-- /tab-reports -->

</div><!-- /tab-content -->


<!-- ─── 리뷰 상세 모달 ─── -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="review-modal-meta" id="modalMeta"></div>
                <div class="review-modal-meta" id="modalModeration" style="margin-top:10px;"></div>
                <div class="review-modal-content" id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <span id="modalStatus"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ── 탭 복원 ──
document.addEventListener('DOMContentLoaded', function() {
    const tab = new URLSearchParams(location.search).get('tab') || 'dashboard';
    const el  = document.querySelector(`[data-bs-target="#tab-${tab}"]`);
    if (el) bootstrap.Tab.getOrCreateInstance(el).show();
});

// ── 회원 검색 ──
document.getElementById('userSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#usersTable tbody tr').forEach(tr => {
        const match = tr.dataset.name.includes(q) || tr.dataset.email.includes(q);
        tr.style.display = match ? '' : 'none';
    });
});

// ── 게시글 상태 필터 ──
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('#reviewsTable tbody tr').forEach(tr => {
            tr.style.display = (filter === 'all' || tr.dataset.status === filter) ? '' : 'none';
        });
    });
});

// ── 리뷰 모달 ──
function showReviewModal(data) {
    const statusLabel = { normal: '정상', flagged: '검토필요', reported: '신고됨' };
    const statusClass = { normal: 'badge-normal', flagged: 'badge-flagged', reported: 'badge-reported' };

    document.getElementById('modalTitle').textContent = data.title;
    document.getElementById('modalMeta').innerHTML =
        `<span><i class="bi bi-person"></i> ${data.username}</span>` +
        `<span><i class="bi bi-globe2"></i> ${data.country}</span>` +
        `<span><i class="bi bi-geo-alt"></i> ${data.location}</span>` +
        `<span><i class="bi bi-calendar3"></i> ${data.created_at}</span>`;
    document.getElementById('modalModeration').innerHTML =
        `<span><i class="bi bi-shield-check"></i> AI 광고 의심 점수: ${data.ad_score || 0}</span>` +
        `<span><i class="bi bi-image"></i> 이미지 검열 점수: ${data.image_score || 0}</span>` +
        (data.reason ? `<span><i class="bi bi-info-circle"></i> ${data.reason}</span>` : '') +
        (data.image_reason ? `<span><i class="bi bi-exclamation-triangle"></i> ${data.image_reason}</span>` : '');
    document.getElementById('modalContent').textContent = data.content;
    document.getElementById('modalStatus').innerHTML =
        `<span class="${statusClass[data.status] || ''}">${statusLabel[data.status] || data.status}</span>`;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('reviewModal')).show();
}

</script>
</body>
</html>
