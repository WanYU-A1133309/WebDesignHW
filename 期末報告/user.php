<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$target_id = (int)$_GET['id'];
$current_user_id = $_SESSION['user_id'] ?? null;
$is_preview_mode = isset($_GET['preview']) && $_GET['preview'] == 1 && ($current_user_id === $target_id);

// 🎯 如果看的是自己，且「沒有帶預覽參數」，就導回個人後台
if ($current_user_id === $target_id && !$is_preview_mode) {
    header("Location: profile.php");
    exit;
}

// 🎯 處理「追蹤 / 取消追蹤」動態切換 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_follow') {
    if (!$current_user_id) {
        header("Location: login.php");
        exit;
    }
    
    // 不能追蹤自己
    if ($current_user_id !== $target_id) {
        $stmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$current_user_id, $target_id]);
        $is_following = $stmt->fetchColumn();

        if ($is_following) {
            $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
            $stmt->execute([$current_user_id, $target_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
            $stmt->execute([$current_user_id, $target_id]);
        }
    }
    header("Location: user.php?id=" . $target_id . ($is_preview_mode ? "&preview=1" : ""));
    exit;
}

// 🔍 撈取目標用戶公開資料
$stmt = $pdo->prepare("SELECT id, nickname, username, avatar, level, bio, gender, genre, total_signins, show_gender, show_genre, show_stats FROM users WHERE id = ?");
$stmt->execute([$target_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    echo "<h3>🍂 找不到該使用者資料。</h3><a href='index.php'>回首頁</a>";
    exit;
}

// 👥 撈取粉絲與追蹤數
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
$stmt->execute([$target_id]);
$t_following = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
$stmt->execute([$target_id]);
$t_followers = $stmt->fetchColumn();

// 🔍 檢查當前用戶是否正追蹤他
$is_following_now = false;
if ($current_user_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$current_user_id, $target_id]);
    $is_following_now = (bool)$stmt->fetchColumn();
}

// 📖 撈取此作者發表的公開故事作品
$published_stories = [];
try {
    $stmt_stories = $pdo->prepare("SELECT id, title, intro, cover_type, cover_image FROM stories WHERE user_id = ? ORDER BY id DESC");
    $stmt_stories->execute([$target_id]);
    $published_stories = $stmt_stories->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 防止尚未建好 stories 資料表時報錯
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($target_user['nickname'] ?: $target_user['username']) ?> 的故事主頁</title>
<link rel="stylesheet" href="theme.css">
<link rel="stylesheet" href="css/user.css">
</head>
<body>

<?php if ($is_preview_mode): ?>
  <div class="preview-banner">✨ 您目前處於「公開主頁預覽模式」，此畫面為其他讀者看您的樣子。</div>
<?php endif; ?>

<div class="app-container" style="display: flex; min-height: 100vh; width: 100%;">

    <aside class="sidebar" id="sidebar">
      <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">‹</button>
      <a href="index.php" class="logo-link">
        <div class="logo-mark">📖</div><span class="logo-text">咬一口故事</span>
      </a>
      <nav class="nav">
        <a href="index.php"><span class="ic ic-home"></span><span class="label">首頁</span></a>
        <a href="explore.php"><span class="ic ic-search"></span><span class="label">探索</span></a>
        <div class="divider"></div>
        <a href="profile.php"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
      </nav>
    </aside>

    <main class="main" style="flex: 1; min-width: 0;">
      <div class="topbar">
        <div class="back-track" onclick="history.back()" style="cursor:pointer; font-weight:600; color:var(--brand, #df6e4b);">‹ 返回</div>
      </div>

      <section class="user-profile-card">
        <div class="upc-left">
          <div class="upc-avatar-wrap">
            <img class="upc-avatar" src="<?= (!empty($target_user['avatar']) && file_exists($target_user['avatar'])) ? htmlspecialchars($target_user['avatar']) : 'https://i.pravatar.cc/100?img=47' ?>" alt="Avatar">
          </div>
          <div class="upc-info">
            <h2><?= htmlspecialchars($target_user['nickname'] ?: $target_user['username']) ?></h2>
            <div class="upc-lv"><span class="lv-badge">Lv.<?= $target_user['level'] ?? 1 ?></span></div>
            <p class="upc-bio"><?= htmlspecialchars($target_user['bio'] ?: '這個人很神祕，還沒有寫下任何簡介。') ?></p>
            <div class="upc-stats">
              <div class="stat-item"><span class="num"><?= $t_following ?></span><span class="lbl">追蹤中</span></div>
              <div class="stat-item"><span class="num"><?= $t_followers ?></span><span class="lbl">粉絲</span></div>
            </div>
          </div>
        </div>

        <div class="upc-right">
          <?php if ($is_preview_mode): ?>
            <button class="btn-unfollow" disabled style="cursor: not-allowed; opacity: 0.7;">這是您自己</button>
          <?php elseif ($current_user_id): ?>
            <form method="post">
              <input type="hidden" name="action" value="toggle_follow">
              <?php if ($is_following_now): ?>
                <button type="submit" class="btn-unfollow">✓ 已追蹤</button>
              <?php else: ?>
                <button type="submit" class="btn-follow">＋ 追蹤創作者</button>
              <?php endif; ?>
            </form>
          <?php else: ?>
            <a href="login.php" class="btn-follow" style="text-decoration:none; display:inline-block;">登入以追蹤</a>
          <?php endif; ?>
        </div>
      </section>

      <div class="user-layout-grid">
        <div class="user-main-content">
          <div class="content-headline">✍️ 創作展覽櫃</div>
          <div class="books-showcase-grid">
            <?php if (!empty($published_stories)): ?>
              <?php foreach ($published_stories as $st): ?>
                <div class="book-mini-card" onclick="location.href='story.php?id=<?= $st['id'] ?>'">
                  <div class="bk-cover">
                    <?php if (($st['cover_type'] ?? '') === 'upload' && !empty($st['cover_image'])): ?>
                      <img src="<?= htmlspecialchars($st['cover_image']) ?>" alt="">
                    <?php else: ?>
                      <span class="bk-title-text"><?= htmlspecialchars($st['title']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="bk-body">
                    <div class="bk-title"><?= htmlspecialchars($st['title']) ?></div>
                    <div class="bk-intro"><?= mb_substr(htmlspecialchars($st['intro'] ?? ''), 0, 32, 'utf-8') ?>...</div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-books-state">🍂 該創作者目前尚未公開發表過故事作品。</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="user-sidebar-content">
          <div class="info-widget-card">
            <h4>🔍 創作者特徵</h4>
            <div class="widget-row"><span>性別：</span><strong><?= ($target_user['show_gender'] == 1) ? htmlspecialchars($target_user['gender'] ?: '未填') : '🔒 不公開' ?></strong></div>
            <div class="widget-row"><span>擅長類型：</span><strong><?= ($target_user['show_genre'] == 1) ? htmlspecialchars($target_user['genre'] ?: '未填') : '🔒 不公開' ?></strong></div>
          </div>

          <div class="info-widget-card">
            <h4>📊 社群統計數據</h4>
            <?php if ($target_user['show_stats'] == 1): ?>
              <div class="widget-row"><span>累積簽到天數</span><b><?= $target_user['total_signins'] ?? 0 ?> 天</b></div>
              <div class="widget-row"><span>總創作作品量</span><b><?= count($published_stories) ?> 本</b></div>
            <?php else: ?>
              <div class="empty-books-state" style="padding:10px 0; border:none;">🔒 創作者將統計設為私密。</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>

</div> <script>
if(localStorage.getItem('bite_theme') && localStorage.getItem('bite_theme') !== 'warm') {
    document.body.setAttribute('data-theme', localStorage.getItem('bite_theme'));
}
</script>
</body>
</html>