<?php
//報錯
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 咬一口故事 - 個人頁 profile.php (完整功能 + 頭貼裁切置中優化 + 主題代幣/簽到解鎖版)
session_start();
require_once 'db.php';

// 🔒 守門員第一線：未登入者一律引導到登入頁面
if (!isset($_SESSION['user_id'])) {
    $back = 'profile.php' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
    header('Location: login.php?back_url=' . urlencode($back) . '&msg=' . urlencode('請先登入才能進入個人頁'));
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "success"; // 用來區分成功綠色或失敗紅色訊息

// ===================================================
// 🎯 核心攔截一：處理「主題解鎖」請求 (POST)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_theme') {
    $theme_to_unlock = trim($_POST['theme_name'] ?? '');
    
    // 先抓取使用者目前的點數與簽到資料
    $stmt = $pdo->prepare("SELECT donuts, total_signins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 檢查是否已經解鎖過
    $stmt_check = $pdo->prepare("SELECT 1 FROM user_themes WHERE user_id = ? AND theme_name = ?");
    $stmt_check->execute([$user_id, $theme_to_unlock]);
    $already_unlocked = $stmt_check->fetchColumn();

    if ($already_unlocked) {
        $msg = "您已經解鎖過這個主題囉！";
    } else {
        if ($theme_to_unlock === 'night' || $theme_to_unlock === 'forest') {
            $cost = ($theme_to_unlock === 'night') ? 3000 : 5000;
            
            if ($u_data['donuts'] >= $cost) {
                // 扣除甜甜圈並寫入解鎖紀錄 (用 Transaction 確保安全)
                $pdo->beginTransaction();
                try {
                    $stmt_deduct = $pdo->prepare("UPDATE users SET donuts = donuts - ? WHERE id = ?");
                    $stmt_deduct->execute([$cost, $user_id]);
                    
                    $stmt_insert = $pdo->prepare("INSERT INTO user_themes (user_id, theme_name) VALUES (?, ?)");
                    $stmt_insert->execute([$user_id, $theme_to_unlock]);
                    
                    $pdo->commit();
                    $msg = "🎉 成功消耗 " . number_format($cost) . " 甜甜圈，解鎖了全新主題！";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "解鎖失敗，系統忙碌中。";
                    $msg_type = "error";
                }
            } else {
                $msg = "❌ 您的甜甜圈不足！還差 " . number_format($cost - $u_data['donuts']) . " 個甜甜圈。";
                $msg_type = "error";
            }
        } elseif ($theme_to_unlock === 'sakura') {
            // 🌸 櫻花粉：判定累積簽到是否達到 30 天
            if (($u_data['total_signins'] ?? 0) >= 30) {
                $stmt_insert = $pdo->prepare("INSERT INTO user_themes (user_id, theme_name) VALUES (?, ?)");
                $stmt_insert->execute([$user_id, $theme_to_unlock]);
                $msg = "🌸 太棒了！達成累積簽到 30 天成就，成功解鎖【櫻花粉】主題！";
            } else {
                $msg = "❌ 鎖定中！此主題需要累積簽到 30 天（您目前累計：" . ($u_data['total_signins'] ?? 0) . " 天）。";
                $msg_type = "error";
            }
        }
    }
    // 為了讓解鎖後的畫面刷新呈現最新點數，留在編輯/換主題分頁
    $_GET['tab'] = 'shelf'; 
}

// ===================================================
// 🎯 核心攔截二：處理資料修改與頭貼裁切 (POST)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $nickname = trim($_POST['name'] ?? '');
    $bio      = trim($_POST['bio'] ?? '');
    $gender   = $_POST['gender'] ?? '不公開';
    $genre    = trim($_POST['genre'] ?? '');

    // 🔒【新增】讀取隱私開關（有勾選才是 1，沒勾選是 0）
    $show_gender = isset($_POST['show_gender']) ? 1 : 0;
    $show_genre  = isset($_POST['show_genre']) ? 1 : 0;
    $show_stats  = isset($_POST['show_stats']) ? 1 : 0;
    
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_avatar = $stmt->fetchColumn();
    $avatar_path = $current_avatar;

    if (!empty($_POST['avatar_base64'])) {
        $base64_string = $_POST['avatar_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = substr($base64_string, strpos($base64_string, ',') + 1);
            $type = strtolower($type[1]);
            
            if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $data = base64_decode($data);
                $upload_dir = 'uploads/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $type;
                $target_path = $upload_dir . $new_file_name;
                
                if (file_put_contents($target_path, $data)) {
                    $avatar_path = $target_path;
                    if ($current_avatar && file_exists($current_avatar) && strpos($current_avatar, 'uploads/') === 0) {
                        unlink($current_avatar);
                    }
                }
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET nickname = ?, bio = ?, gender = ?, genre = ?, avatar = ?, show_gender = ?, show_genre = ?, show_stats = ? WHERE id = ?");
    $stmt->execute([$nickname, $bio, $gender, $genre, $avatar_path, $show_gender, $show_genre, $show_stats, $user_id]);
    $_SESSION['nickname'] = $nickname;
    $msg = "個人資料與頭貼已成功更新！";
}

// 🎯 查詢目前使用者的最新資料
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$db_user = $stmt->fetch(PDO::FETCH_ASSOC);

// ===================================================
// 👥 🔥【新增】動態計算追蹤/粉絲數，並撈取彈跳卡片要用的名單
// ===================================================
// A. 計算總追蹤人數 & 撈取追蹤中的人（頭貼、暱稱、ID）
$stmt_following_count = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
$stmt_following_count->execute([$user_id]);
$following_count = $stmt_following_count->fetchColumn();

$stmt_following_list = $pdo->prepare("
    SELECT u.id, u.nickname, u.username, u.avatar 
    FROM user_follows f
    JOIN users u ON f.following_id = u.id
    WHERE f.follower_id = ?
    ORDER BY f.created_at DESC
");
$stmt_following_list->execute([$user_id]);
$following_list = $stmt_following_list->fetchAll(PDO::FETCH_ASSOC);

// B. 計算總粉絲人數 & 撈取粉絲們（頭貼、暱稱、ID）
$stmt_followers_count = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
$stmt_followers_count->execute([$user_id]);
$followers_count = $stmt_followers_count->fetchColumn();

$stmt_followers_list = $pdo->prepare("
    SELECT u.id, u.nickname, u.username, u.avatar 
    FROM user_follows f
    JOIN users u ON f.follower_id = u.id
    WHERE f.following_id = ?
    ORDER BY f.created_at DESC
");
$stmt_followers_list->execute([$user_id]);
$followers_list = $stmt_followers_list->fetchAll(PDO::FETCH_ASSOC);

// 🔍 查詢該使用者目前已經解鎖的所有主題
$stmt_themes = $pdo->prepare("SELECT theme_name FROM user_themes WHERE user_id = ?");
$stmt_themes->execute([$user_id]);
$unlocked_themes = $stmt_themes->fetchAll(PDO::FETCH_COLUMN);
// 預設「暖陽米(warm)」是免解鎖自帶的
$unlocked_themes[] = 'warm'; 

// 動態配置使用者前台常數
$user = [
    'name'   => $db_user['nickname'] ?? '小狐',
    'avatar' => (!empty($db_user['avatar']) && file_exists($db_user['avatar'])) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
    'donuts' => $db_user['donuts'] ?? 0,
    'wheat'  => $db_user['wheat'] ?? 0,
    'level'  => $db_user['level'] ?? 1,
    'exp'    => $db_user['exp'] ?? 0,
    'exp_max'=> 1000 * ($db_user['level'] ?? 1), 
    'bio'    => $db_user['bio'] ?? '這個人很懶，還沒有寫下任何簡介。',
    'gender' => $db_user['gender'] ?? '不公開',
    'genre'  => $db_user['genre'] ?? '',
    'total_signins' => $db_user['total_signins'] ?? 0, // 帶出總簽到天數
    'works'  => 0, 
    'favs' => 0, 
    'following' => $following_count, // ⚡ 換成真實追蹤數
    'followers' => $followers_count  // ⚡ 換成真實粉絲數
];

$badges = $badges ?? [
    ['n' => '新進狐狸', 'd' => '註冊會員成功'],
    ['n' => '故事愛好者', 'd' => '收藏超過 5 本書'],
    ['n' => '夜貓子', 'd' => '深夜閱讀挑戰達成']
];

$orders = $orders ?? [
    ['date' => date('Y-m-d'), 'item' => '系統初始贈送甜甜圈', 'amt' => '+1,000']
];
// ── 消費 / 獲得紀錄：整合四個真實資料來源 ──
$orders = [];
try {
    // 1. 購買書籍（麥穗消費）— orders + order_items
    $stmt_o = $pdo->prepare("
        SELECT DATE(o.created_at) AS date,
               CONCAT('購買書籍：', oi.book_title) AS item,
               CONCAT('-', oi.wheat_price, ' 🌾') AS amt,
               o.created_at AS sort_time
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = ? AND o.status = 'paid'
        ORDER BY o.created_at DESC
    ");
    $stmt_o->execute([$user_id]);
    $orders = array_merge($orders, $stmt_o->fetchAll(PDO::FETCH_ASSOC));

    // 2. 解鎖付費章節（甜甜圈消費）— user_unlocks + chapters + stories
    $stmt_u = $pdo->prepare("
        SELECT DATE(uu.unlocked_at) AS date,
               CONCAT('解鎖章節：', COALESCE(c.title, '未知章節'),
                      '（', COALESCE(s.title, '未知故事'), '）') AS item,
               CONCAT('-', COALESCE(c.price_donuts, 0), ' 🍩') AS amt,
               uu.unlocked_at AS sort_time
        FROM user_unlocks uu
        LEFT JOIN chapters c ON c.id = uu.chapter_id
        LEFT JOIN stories s ON s.id = c.story_id
        WHERE uu.user_id = ?
        ORDER BY uu.unlocked_at DESC
    ");
    $stmt_u->execute([$user_id]);
    $orders = array_merge($orders, $stmt_u->fetchAll(PDO::FETCH_ASSOC));

    // 3. 購買主題（甜甜圈消費）— user_themes
    $theme_names = ['night' => '夜幕藍主題', 'forest' => '森林綠主題', 'sakura' => '櫻花粉主題'];
    $theme_costs = ['night' => '-3000 🍩', 'forest' => '-5000 🍩', 'sakura' => '免費（簽到解鎖）'];
    $stmt_t = $pdo->prepare("
        SELECT theme_name, unlocked_at
        FROM user_themes
        WHERE user_id = ?
        ORDER BY unlocked_at DESC
    ");
    $stmt_t->execute([$user_id]);
    foreach ($stmt_t->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $orders[] = [
            'date'      => date('Y-m-d', strtotime($t['unlocked_at'])),
            'item'      => '解鎖主題：' . ($theme_names[$t['theme_name']] ?? $t['theme_name']),
            'amt'       => $theme_costs[$t['theme_name']] ?? '—',
            'sort_time' => $t['unlocked_at'],
        ];
    }

    // 4. 每日簽到獎勵（甜甜圈收入）— signin_logs（最近 30 筆）
    $stmt_s = $pdo->prepare("
        SELECT signin_date AS date,
               '每日簽到獎勵' AS item,
               '+10 🍩' AS amt,
               created_at AS sort_time
        FROM signin_logs
        WHERE user_id = ?
        ORDER BY signin_date DESC
        LIMIT 30
    ");
    $stmt_s->execute([$user_id]);
    $orders = array_merge($orders, $stmt_s->fetchAll(PDO::FETCH_ASSOC));

    // 統一依時間新→舊排序
    usort($orders, fn($a, $b) => strtotime($b['sort_time']) - strtotime($a['sort_time']));

} catch (PDOException $e) {
    $orders = [];
}

$initTab = isset($_GET['tab']) ? $_GET['tab'] : 'shelf';

function fetchUserBooksMock($pdo, $userId, $type) {
    return [];
}
$reading = [];

// 從 bookshelves 撈真實收藏
try {
    $stmt_favs = $pdo->prepare("
        SELECT s.id, s.title, s.cover_image AS cover, u.nickname AS author
        FROM bookshelves bs
        JOIN stories s ON bs.story_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE bs.user_id = ?
        ORDER BY bs.created_at DESC
    ");
    $stmt_favs->execute([$user_id]);
    $favs = $stmt_favs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $favs = [];
}
$user['favs'] = count($favs);  // ← 加這行
// 🎯 【修改核心】：從資料庫中撈取真實的閱讀紀錄（關聯書籍資料表 books）
try {
    $stmt_history = $pdo->prepare("
        SELECT b.title, b.cover_image AS cover, h.progress, h.book_id
        FROM user_reading_history h
        JOIN stories b ON h.book_id = b.id
        WHERE h.user_id = ?
        ORDER BY h.updated_at DESC
        LIMIT 12
    ");
    $stmt_history->execute([$user_id]);
    $history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 降級處理：如果資料表尚未建立，則回傳空陣列，防止網頁壞掉
    $history = [];
}
// 本月閱讀本數
try {
    $stmt_month = $pdo->prepare("
        SELECT COUNT(*) FROM user_reading_history
        WHERE user_id = ? AND MONTH(updated_at) = MONTH(NOW()) AND YEAR(updated_at) = YEAR(NOW())
    ");
    $stmt_month->execute([$user_id]);
    $monthly_read = $stmt_month->fetchColumn();
} catch (Exception $e) {
    $monthly_read = 0;
}

?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ 個人頁</title>
<link rel="stylesheet" href="theme.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

<link rel="stylesheet" href="css/profile.css">

</head>
<body>

<input type="file" id="avatarFileInput" accept="image/*" style="display: none;">

<div id="cropModal" class="crop-modal">
  <div class="crop-modal-content">
    <h3>裁切你的故事頭貼</h3>
    <div class="crop-container">
      <img id="cropImg" src="" alt="待裁切圖片">
    </div>
    <div class="crop-actions">
      <button type="button" class="btn-ghost" onclick="closeCropModal()">取消</button>
      <button type="button" class="btn-fill" id="confirmCropBtn">確定裁切</button>
    </div>
  </div>
</div>

<form id="unlockThemeForm" method="post" action="profile.php" style="display:none;">
    <input type="hidden" name="action" value="unlock_theme">
    <input type="hidden" name="theme_name" id="unlockThemeName">
</form>

<aside class="sidebar" id="sidebar">
  <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="收合 / 展開">‹</button>
  <a href="index.php" class="logo-link" data-label="咬一口故事">
    <div class="logo-mark">📖</div><span class="logo-text">咬一口故事</span>
  </a>
  <nav class="nav">
    <a href="index.php" data-label="首頁"><span class="ic ic-home"></span><span class="label">首頁</span></a>
    <a href="explore.php" data-label="探索"><span class="ic ic-search"></span><span class="label">探索</span></a>
    <a href="bottle.php" data-label="漂流瓶"><span class="ic ic-bottle"></span><span class="label">漂流瓶</span></a>
    <div class="divider"></div>
    <a href="profile.php" class="active" data-label="個人頁"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
    <a href="bookshelf.php" data-label="我的書架"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
    <a href="cart.php" data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
  </nav>
  <div class="publish" id="publish">
    <button class="publish-btn" data-label="發布新作" onclick="event.stopPropagation(); document.getElementById('publish').classList.toggle('open')">
      <span class="ic ic-pen"></span><span class="label">發布新作</span>
    </button>
    <div class="publish-menu">
      <a href="create.php"><span class="ic ic-sm ic-pen"></span> 開始寫故事 / 投稿</a>
      <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
      <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
      
    </div>
  </div>
  <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
</aside>

<main class="main">

  <div class="topbar">
    <form class="search" action="explore.php" method="get" role="search">
      <span class="ic ic-sm ic-search"></span>
      <input name="q" placeholder="搜尋書名、作者、標籤…">
    </form>
    <a class="top-btn" href="tasks.php"><span class="ic ic-sm ic-sparkle"></span> 每日任務</a>
    <a class="top-btn" href="signin.php"><span class="ic ic-sm ic-gift"></span> 每日簽到</a>
    <div class="avatar-wrap" onclick="location.href='profile.php'">
      <img class="avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="User">
    </div>
  </div>

  <?php if (!empty($msg)): ?>
    <div style="background: <?= $msg_type === 'success' ? '#E8F5E9' : '#FFEBEE' ?>; color: <?= $msg_type === 'success' ? '#2E7D32' : '#C62828' ?>; padding: 14px 20px; border-radius: 14px; margin-bottom: 24px; font-weight: 600; font-size: 14px; box-shadow: var(--shadow-sm);">
      ✨ <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <section class="profile-card">
    <div class="pc-left">
      <div class="big-avatar-wrap" onclick="document.getElementById('avatarFileInput').click()">
        <div class="avatar-edit-overlay">更換頭貼</div>
        <img class="big-avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
      </div>
      <div class="pc-info" style="flex:1;min-width:0">
        <h2><?= htmlspecialchars($user['name']) ?></h2>
        <div class="lv">
          <span class="lvtag">Lv.<?= $user['level'] ?></span>
          <div class="bar"><i style="width:<?= round($user['exp']/$user['exp_max']*100) ?>%"></i></div>
          <span><?= number_format($user['exp']) ?>/<?= number_format($user['exp_max']) ?></span>
        </div>
        <p class="bio"><?= htmlspecialchars($user['bio']) ?></p>
        <div class="stats">
          <a class="stat" href="create.php"><div class="n"><?= $user['works'] ?></div><div class="l">發布作品</div></a>
          <button class="stat" type="button" onclick="goTab('favs')"><div class="n"><?= $user['favs'] ?></div><div class="l">收藏</div></button>
          <button class="stat" type="button" onclick="openFollowsModal('following')"><div class="n"><?= $user['following'] ?></div><div class="l">追蹤中</div></button>
          <button class="stat" type="button" onclick="openFollowsModal('followers')"><div class="n"><?= $user['followers'] ?></div><div class="l">粉絲</div></button>
        </div>
      </div>
    </div>

    <div class="pc-right">
      <h3><span class="ic ic-sm ic-gift"></span> 我的點數</h3>
      <div class="coins">
        <div class="coin donut"><div class="coin-ico">🍩</div><div><div class="name">甜甜圈</div><div class="val"><?= number_format($user['donuts']) ?></div></div></div>
        <div class="sep"></div>
        <div class="coin wheat"><div class="coin-ico">🌾</div><div><div class="name">麥穗</div><div class="val"><?= number_format($user['wheat']) ?></div></div></div>
      </div>
      <div class="coin-acts">
        <button class="btn-ghost" type="button" onclick="goTab('orders')">消費紀錄 ›</button>
        <a class="btn-fill" href="topup.php">儲值點數</a>
      </div>
    </div>
  </section>

  <div class="tabs" role="tablist">
    <button class="tab" data-tab="shelf">我的書架</button>
    <button class="tab" data-tab="favs">收藏</button>
    <button class="tab" data-tab="history">閱讀紀錄</button>
    <button class="tab" data-tab="orders">消費紀錄</button>
    <button class="tab" data-tab="edit">編輯個人主頁</button>
  </div>

  <section class="panel shelf-row" data-panel="shelf">
    <div class="section-head"><div class="h"><span class="deco"></span><h3>我正在閱讀</h3></div><a class="more" href="bookshelf.php">查看更多 ›</a></div>
    <div class="grid">
      <?php foreach($reading as $b): ?>
      <a class="card" href="book.php?title=<?= urlencode($b['title']) ?>">
        <div class="cover">
            <img src="<?= htmlspecialchars($b['cover'] ?? 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=200') ?>" alt="封面">
        </div>
        <div class="body"><div class="title"><?= htmlspecialchars($b['title']) ?></div><div class="meta"><span><?= htmlspecialchars($b['author']) ?></span><span class="tag">閱讀中</span></div></div>
      </a>
      <?php endforeach; ?>
      <a class="card add" href="explore.php"><span class="plus">+</span><span>前往探索<br>更多故事</span></a>
    </div>

    <div class="duo" style="margin-top:30px">
      <div class="panel-card">
        <h4><span class="ic ic-sm ic-chart"></span> 閱讀統計 / 閱讀挑戰</h4>
        <div class="stat-row"><span>累積簽到</span><b><?= $user['total_signins'] ?> 天</b></div>
        <div class="stat-row"><span>本月閱讀</span><b><?= $monthly_read ?> 本</b></div>
        
      </div>

      <div class="panel-card">
        <h4><span class="ic ic-sm ic-sparkle"></span> 換換主題</h4>
        <p style="color:var(--muted);font-size:13px;margin-bottom:14px">挑一個你今天的心情，整個網站的背景與配色都會跟著變！</p>
        <div class="theme-switcher" id="themeSwitcher">
          <div class="theme-opt" data-theme="warm" data-unlocked="true">
            <div class="theme-swatch warm"></div>暖陽米
          </div>
          
          <?php $is_night_unlocked = in_array('night', $unlocked_themes); ?>
          <div class="theme-opt" data-theme="night" data-unlocked="<?= $is_night_unlocked ? 'true' : 'false' ?>" data-cost="3000">
            <div class="theme-swatch night"></div>夜幕藍 
            <?php if(!$is_night_unlocked): ?><span class="theme-lock-badge">🔒 3K 🍩</span><?php endif; ?>
          </div>
          
          <?php $is_forest_unlocked = in_array('forest', $unlocked_themes); ?>
          <div class="theme-opt" data-theme="forest" data-unlocked="<?= $is_forest_unlocked ? 'true' : 'false' ?>" data-cost="5000">
            <div class="theme-swatch forest"></div>森林綠
            <?php if(!$is_forest_unlocked): ?><span class="theme-lock-badge">🔒 5K 🍩</span><?php endif; ?>
          </div>
          
          <?php $is_sakura_unlocked = in_array('sakura', $unlocked_themes); ?>
          <div class="theme-opt" data-theme="sakura" data-unlocked="<?= $is_sakura_unlocked ? 'true' : 'false' ?>" data-type="signin" data-days="<?= $user['total_signins'] ?>">
            <div class="theme-swatch sakura"></div>櫻花粉
            <?php if(!$is_sakura_unlocked): ?><span class="theme-lock-badge">🔒 簽到30天</span><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="section-head" style="margin-top:30px"><div class="h"><span class="deco"></span><h3>我的徽章 🏅</h3></div></div>
    <div class="panel-card">
      <div class="badge-grid">
        <?php $emojis=['🦊','🌙','📚','🖋️','⭐','🍾']; foreach($badges as $i=>$bd): ?>
        <div class="badge-item"><div class="badge-emoji"><?= $emojis[$i % count($emojis)] ?></div><div class="n"><?= htmlspecialchars($bd['n']) ?></div><div class="d"><?= htmlspecialchars($bd['d']) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="panel" data-panel="favs">
    <div class="section-head"><div class="h"><span class="deco"></span><h3>我的收藏</h3></div><span class="more">共 <?= $user['favs'] ?> 本</span></div>
    <div class="grid">
      <?php foreach($favs as $b): ?>
      <a class="card" href="story.php?id=<?= $b['id'] ?>">
        <div class="cover">
            <img src="<?= !empty($b['cover']) ? htmlspecialchars($b['cover']) : 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=200' ?>" alt="封面">
        </div>
        <div class="body">
            <div class="title"><?= htmlspecialchars($b['title']) ?></div>
            <div class="meta">
            <span><?= htmlspecialchars($b['author']) ?></span>
            <span class="tag">♥ 收藏</span>
            </div>
        </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" data-panel="history">
  <div class="section-head"><div class="h"><span class="deco"></span><h3>閱讀紀錄</h3></div></div>
  <div class="grid">
    <?php if (empty($history)): ?>
      <p style="color: var(--muted); padding: 40px 0; text-align: center; grid-column: 1 / -1; font-size: 14px;">目前還沒有任何閱讀紀錄喔 📖</p>
    <?php else: ?>
      <?php foreach($history as $b): ?>
        <a class="card" href="story.php?id=<?= intval($b['book_id']) ?>">
          
          <div class="cover">
            <img src="<?= htmlspecialchars($b['cover'] ?? 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=200') ?>" alt="封面">
          </div>
          
          <div class="body">
            <div class="title"><?= htmlspecialchars($b['title']) ?></div>
            <div class="meta">
              <span>最近閱讀</span>
              <span class="tag"><?= intval($b['progress']) ?>%</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

  <section class="panel" data-panel="orders">
    <div class="panel-card">
      <h4><span class="ic ic-sm ic-chart"></span> 消費 / 儲值紀錄</h4>
      <table class="tbl">
        <thead><tr><th>日期</th><th>項目</th><th style="text-align:right">金額</th></tr></thead>
        <tbody>
        <?php foreach($orders as $o):
            $cls = strpos($o['amt'],'+')===0 ? 'amt-pos' : 'amt-neg'; ?>
          <tr><td><?= $o['date'] ?></td><td><?= htmlspecialchars($o['item']) ?></td><td class="<?= $cls ?>" style="text-align:right"><?= htmlspecialchars($o['amt']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel" data-panel="edit">
    <form class="panel-card" method="post" action="profile.php">
      <input type="hidden" name="avatar_base64" id="avatarBase64">
      
      <h4><span class="ic ic-sm ic-pen"></span> 編輯個人主頁</h4>
      <div class="form-grid">
        <div class="field"><label>顯示名稱</label><input name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
        <div class="field">
          <label>個人頭貼</label>
          <button type="button" class="btn-ghost" onclick="document.getElementById('avatarFileInput').click()" style="padding:11px 14px; text-align:left; border-radius:12px;">選擇照片並裁切...</button>
        </div>
        <div class="field full"><label>個人簡介</label><textarea name="bio"><?= htmlspecialchars($user['bio']) ?></textarea></div>
        <div class="field"><label>性別</label>
          <select name="gender">
            <option value="不公開" <?= $user['gender']=='不公開'?'selected':'' ?>>不公開</option>
            <option value="女" <?= $user['gender']=='女'?'selected':'' ?>>女</option>
            <option value="男" <?= $user['gender']=='男'?'selected':'' ?>>男</option>
            <option value="其他" <?= $user['gender']=='其他'?'selected':'' ?>>其他</option>
          </select>
        </div>
        <div class="field"><label>喜歡的類型</label><input name="genre" value="<?= htmlspecialchars($user['genre']) ?>" placeholder="例如：奇幻、治癒、懸疑"></div>


        <div class="field full" style="background: #f9f9f9; padding: 15px; border-radius: 12px; margin-top: 10px;">
        <label style="font-weight:700; margin-bottom:8px; display:block; color:var(--primary);">🔒 他人視角隱私設定（勾選代表公開顯示）</label>
        <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px;">
            <label style="cursor:pointer"><input type="checkbox" name="show_gender" value="1" <?= ($db_user['show_gender'] ?? 1) ? 'checked' : '' ?>> 公開我的性別</label>
            <label style="cursor:pointer"><input type="checkbox" name="show_genre" value="1" <?= ($db_user['show_genre'] ?? 1) ? 'checked' : '' ?>> 公開我喜歡的故事類型</label>
            <label style="cursor:pointer"><input type="checkbox" name="show_stats" value="1" <?= ($db_user['show_stats'] ?? 1) ? 'checked' : '' ?>> 公開我的閱讀統計（簽到天數等數據）</label>
            </div>
        </div>

        <a href="user.php?id=<?= $user_id ?>&preview=1" target="_blank" style="display: inline-block; padding: 6px 12px; background: #666; color: #fff; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600;">
            👁️ 預覽我的公開主頁（別人看我的視角）
        </a>

      </div>
      <div class="save-row">
        <button class="btn-ghost" type="reset">取消</button>
        <button class="btn-fill" type="submit">儲存變更</button>
      </div>
    </form>
  </section>

</main>



<div id="followsModal" class="follows-modal" style="display: none;">
  <div class="follows-modal-content">
    <div class="follows-modal-header">
      <h3 id="followsModalTitle">社群清單</h3>
      <button type="button" class="follows-close-btn" onclick="closeFollowsModal()">&times;</button>
    </div>
    <div class="follows-modal-body">
      
      <div id="followingSection" class="follows-section" style="display: none;">
        <?php if (empty($following_list)): ?>
          <p class="follows-empty-msg">目前還沒有追蹤任何人喔 🦊</p>
        <?php else: ?>
          <?php foreach ($following_list as $f_user): 
            $f_avatar = (!empty($f_user['avatar']) && file_exists($f_user['avatar'])) ? $f_user['avatar'] : 'https://i.pravatar.cc/100?img=47';
            $f_name = !empty($f_user['nickname']) ? $f_user['nickname'] : $f_user['username'];
          ?>
            <div class="follow-item-row" onclick="location.href='profile.php?uid=<?= $f_user['id'] ?>'">
              <img src="<?= htmlspecialchars($f_avatar) ?>" alt="avatar">
              <span class="follow-item-name"><?= htmlspecialchars($f_name) ?></span>
              <span class="follow-arrow">›</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div id="followersSection" class="follows-section" style="display: none;">
        <?php if (empty($followers_list)): ?>
          <p class="follows-empty-msg">目前還沒有粉絲，繼續發布好故事吧！🌾</p>
        <?php else: ?>
          <?php foreach ($followers_list as $f_user): 
            $f_avatar = (!empty($f_user['avatar']) && file_exists($f_user['avatar'])) ? $f_user['avatar'] : 'https://i.pravatar.cc/100?img=47';
            $f_name = !empty($f_user['nickname']) ? $f_user['nickname'] : $f_user['username'];
          ?>
            <div class="follow-item-row" onclick="location.href='profile.php?uid=<?= $f_user['id'] ?>'">
              <img src="<?= htmlspecialchars($f_avatar) ?>" alt="avatar">
              <span class="follow-item-name"><?= htmlspecialchars($f_name) ?></span>
              <span class="follow-arrow">›</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<style>
.follows-modal {
  position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.25s ease;
}
.follows-modal-content {
  background: #ffffff; border-radius: 20px; width: 90%; max-width: 420px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; flex-direction: column;
  max-height: 75vh; overflow: hidden; animation: slideUp 0.3s cubic-bezier(0.1, 0.8, 0.2, 1);
}
.follows-modal-header {
  padding: 18px 24px; border-bottom: 1px solid #f0f0f0;
  display: flex; justify-content: space-between; align-items: center;
}
.follows-modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #333; }
.follows-close-btn { background: none; border: none; font-size: 28px; cursor: pointer; color: #aaa; line-height: 1; }
.follows-close-btn:hover { color: #555; }
.follows-modal-body { padding: 12px 16px; overflow-y: auto; flex: 1; }

/* 橫排使用者清單 */
.follow-item-row {
  display: flex; align-items: center; padding: 12px 14px; border-radius: 14px;
  cursor: pointer; transition: all 0.2s ease; margin-bottom: 4px;
}
.follow-item-row:hover { background: #f5f5f5; transform: translateX(2px); }
.follow-item-row img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; margin-right: 14px; border: 1px solid #eee; }
.follow-item-name { font-weight: 600; color: #444; font-size: 14.5px; flex: 1; }
.follow-arrow { color: #ccc; font-size: 18px; font-weight: 300; }

.follows-empty-msg { text-align: center; color: #999; padding: 40px 0; font-size: 14px; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>





<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
// ====== Tabs 分頁切換邏輯 ======
const tabs = document.querySelectorAll('.tab');
const panels = document.querySelectorAll('.panel');
function goTab(name){
  tabs.forEach(t => t.classList.toggle('on', t.dataset.tab === name));
  panels.forEach(p => p.classList.toggle('on', p.dataset.panel === name));
  history.replaceState(null,'','?tab='+name);
}
tabs.forEach(t => t.addEventListener('click', () => goTab(t.dataset.tab)));
goTab(<?= json_encode($initTab) ?>);

// ====== 主題動態切換邏輯 ======
const THEME_KEY = 'bite_theme';
function applyTheme(name){
  if(name && name !== 'warm') document.body.setAttribute('data-theme', name);
  else document.body.removeAttribute('data-theme');
  document.querySelectorAll('.theme-opt').forEach(o=>{
    o.classList.toggle('active', o.dataset.theme === (name||'warm'));
  });
  localStorage.setItem(THEME_KEY, name||'warm');
}
applyTheme(localStorage.getItem(THEME_KEY) || 'warm');

// ====== 👑 換主題與點數/簽到判定控制項 ======
document.querySelectorAll('.theme-opt').forEach(opt => {
  opt.addEventListener('click', () => {
    const isUnlocked = opt.dataset.unlocked === 'true';
    const themeName = opt.dataset.theme;

    if (isUnlocked) {
      // 已經解鎖，直接套用主題
      applyTheme(themeName);
    } else {
      // 尚未解鎖，啟動解鎖判定機制
      if (opt.dataset.type === 'signin') {
        // 櫻花粉邏輯
        const myDays = parseInt(opt.dataset.days) || 0;
        if (myDays >= 30) {
          if (confirm(`🌸 恭喜！您已滿足累積簽到 30 天的條件（目前：${myDays} 天），是否立即解鎖【櫻花粉】主題？`)) {
            submitUnlock(themeName);
          }
        } else {
          alert(`🔒 此主題為限定成就獎勵！\n需要累積簽到 30 天才能解鎖。\n您目前已累積：${myDays} 天，繼續加油！🌾`);
        }
      } else {
        // 金額購買邏輯 (夜幕藍、森林綠)
        const cost = opt.dataset.cost;
        if (confirm(`🔒 解鎖此主題需要消耗 ${cost} 個甜甜圈 🍩\n確定要扣點並解鎖嗎？`)) {
          submitUnlock(themeName);
        }
      }
    }
  });
});

function submitUnlock(themeName) {
  document.getElementById('unlockThemeName').value = themeName;
  document.getElementById('unlockThemeForm').submit();
}




// ====== 追蹤與粉絲彈跳卡片控制邏輯 ======
function openFollowsModal(type) {
  const modal = document.getElementById('followsModal');
  const title = document.getElementById('followsModalTitle');
  const followingSec = document.getElementById('followingSection');
  const followersSec = document.getElementById('followersSection');
  
  // 顯示背景遮罩彈窗
  modal.style.display = 'flex'; 
  
  // 根據點擊的是追蹤還是粉絲，動態更換標題與顯示對應的名單區塊
  if (type === 'following') {
    title.innerText = '追蹤中的創作者';
    followingSec.style.display = 'block';
    followersSec.style.display = 'none';
  } else if (type === 'followers') {
    title.innerText = '我的狐狸粉絲';
    followingSec.style.display = 'block';
    followingSec.style.display = 'none';
    followersSec.style.display = 'block';
  }
}

function closeFollowsModal() {
  document.getElementById('followsModal').style.display = 'none';
}

// 當使用者點選彈窗外面的半透明灰色背景時，也能貼心自動關閉彈窗
window.addEventListener('click', function(event) {
  const modal = document.getElementById('followsModal');
  if (event.target === modal) {
    closeFollowsModal();
  }
});


// ====== 📸 實體頭貼裁切運算模組 ======
let cropper = null;
const fileInput = document.getElementById('avatarFileInput');
const cropModal = document.getElementById('cropModal');
const cropImg = document.getElementById('cropImg');

fileInput.addEventListener('change', function(e) {
  const files = e.target.files;
  if (files && files.length > 0) {
    const file = files[0];
    const reader = new FileReader();
    reader.onload = function(event) {
      cropImg.src = event.target.result;
      cropModal.style.display = 'grid'; 
      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImg, {
        aspectRatio: 1,
        viewMode: 1,
        background: false,
        autoCropArea: 1
      });
    };
    reader.readAsDataURL(file);
  }
});

function closeCropModal() {
  cropModal.style.display = 'none';
  fileInput.value = ''; 
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
}

document.getElementById('confirmCropBtn').addEventListener('click', function() {
  if (!cropper) return;
  const canvas = cropper.getCroppedCanvas({ width: 300, height: 300, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
  const base64Data = canvas.toDataURL('image/jpeg');
  document.getElementById('avatarBase64').value = base64Data;
  document.querySelectorAll('.big-avatar, .avatar').forEach(img => { img.src = base64Data; });
  closeCropModal();
  goTab('edit'); 
});
</script>
</body>
</html>