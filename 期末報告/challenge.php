<?php
// 咬一口故事 - 年度閱讀挑戰 challenge.php (功能整合+完美外觀雙欄連動版)
session_start();
require_once 'db.php';
// 使用台灣時區
date_default_timezone_set('Asia/Taipei');

// 🔒 登入檢查
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?msg=' . urlencode('請先登入才能加入閱讀挑戰'));
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = (int)date('Y');

$msg = "";
$msg_type = "";

// --- 🎯 POST 行動一：報名挑戰 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_challenge') {
    $target_count = (int)$_POST['target_count'];
    $selected_books = $_POST['books'] ?? []; // 陣列，使用者勾選的書本 ID
    
    if ($target_count <= 0) {
        $msg = "目標閱讀本數必須大於 0 哦！";
        $msg_type = "error";
    } elseif (count($selected_books) < $target_count) {
        $msg = "選定的挑戰書單數量，不能少於你的目標本數哦！";
        $msg_type = "error";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. 建立挑戰主表
            $stmt = $pdo->prepare("INSERT INTO user_challenges (user_id, target_year, target_count) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $current_year, $target_count]);
            $challenge_id = $pdo->lastInsertId();
            
            // 2. 綁定書單
            $book_stmt = $pdo->prepare("INSERT IGNORE INTO challenge_books (challenge_id, book_id) VALUES (?, ?)");
            foreach ($selected_books as $b_id) {
                $book_stmt->execute([$challenge_id, (int)$b_id]);
            }
            
            $pdo->commit();
            header("Location: challenge.php?msg=" . urlencode("報名成功！開始今年的閱讀冒險吧 ✨"));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "報名失敗，您今年可能已經報名過挑戰了！";
            $msg_type = "error";
        }
    }
}

// --- 🎯 POST 行動二：領取挑戰獎勵 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_reward') {
    // 重新驗證是否真的達標
    $c_stmt = $pdo->prepare("SELECT * FROM user_challenges WHERE user_id = ? AND target_year = ?");
    $c_stmt->execute([$user_id, $current_year]);
    $challenge = $c_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($challenge && $challenge['is_rewarded'] == 0) {
        // 計算完讀數量 (進度為 100%)
        $comp_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM challenge_books cb
            JOIN stories s ON cb.book_id = s.id
            LEFT JOIN user_reading_history urh ON urh.book_id = s.id AND urh.user_id = ?
            WHERE cb.challenge_id = ? AND urh.progress >= 100
        ");
        $comp_stmt->execute([$user_id, $challenge['id']]);
        $completed_count = (int)$comp_stmt->fetchColumn();
        
        if ($completed_count >= $challenge['target_count']) {
            try {
                $pdo->beginTransaction();
                
                // 1. 標記挑戰為已領獎
                $up_stmt = $pdo->prepare("UPDATE user_challenges SET is_rewarded = 1 WHERE id = ?");
                $up_stmt->execute([$challenge['id']]);
                
                // 2. 注入獎勵 1000 甜甜圈到 users 表
                $user_up = $pdo->prepare("UPDATE users SET donuts = donuts + 1000 WHERE id = ?");
                $user_up->execute([$user_id]);
                
                // 3. 發放榮譽徽章
                $badge_name = $current_year . "年度閱讀達人";
                $badge_icon = "🏆";
                $badge_stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_name, badge_icon) VALUES (?, ?, ?)");
                $badge_stmt->execute([$user_id, $badge_name, $badge_icon]);
                
                $pdo->commit();
                header("Location: challenge.php?claimed=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "領獎程序異常，請稍後再試。";
                $msg_type = "error";
            }
        }
    }
}

// --- 撈取畫面上所需的資料 ---
// 1. 檢查今年是否已經建立挑戰
$challenge_stmt = $pdo->prepare("SELECT * FROM user_challenges WHERE user_id = ? AND target_year = ?");
$challenge_stmt->execute([$user_id, $current_year]);
$my_challenge = $challenge_stmt->fetch(PDO::FETCH_ASSOC);

$has_challenge = (bool)$my_challenge;
$challenge_books = [];
$completed_count = 0;
$progress_percent = 0;

if ($has_challenge) {
    // 2. 如果有挑戰，撈出挑戰的書單以及對應的閱讀進度 (JOIN user_books)
    $books_stmt = $pdo->prepare("
        SELECT s.id, s.title, s.cover_image, s.cover_type, 
            COALESCE(urh.progress, 0) AS progress
        FROM challenge_books cb
        JOIN stories s ON cb.book_id = s.id
        LEFT JOIN user_reading_history urh ON urh.book_id = s.id AND urh.user_id = ?
        WHERE cb.challenge_id = ?
    ");
    $books_stmt->execute([$user_id, $my_challenge['id']]);
    $challenge_books = $books_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 計算有幾本進度是 100% 的
    foreach ($challenge_books as $b) {
        if ($b['progress'] >= 100) {
            $completed_count++;
        }
    }
    
    // 計算進度條百分比 (上限 100%)
    $progress_percent = ($my_challenge['target_count'] > 0) 
        ? min(100, round(($completed_count / $my_challenge['target_count']) * 100)) 
        : 0;
} else {
    // 3. 如果沒有挑戰，撈出全站所有書籍供使用者在彈跳視窗內勾選
    $all_stories_stmt = $pdo->query("SELECT id, title FROM stories ORDER BY id DESC");
    $all_stories = $all_stories_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 接收 Get 彈窗訊息
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = "success";
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ <?= $current_year ?> 年度閱讀挑戰</title>
<link rel="stylesheet" href="theme.css">
<link rel="stylesheet" href="css/challenge.css">
</head>
<body>

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

    <main class="main" style="flex: 1; min-width: 0; padding: 40px 30px; background: var(--bg, #fffbf5);">
      <div class="topbar" style="margin-bottom: 24px;">
        <div class="back-track" onclick="location.href='index.php'" style="cursor:pointer; font-weight:600; color:var(--brand, #df6e4b); display: inline-block; padding: 8px 16px; background: #fff; border: 1px solid #e2cfb6; border-radius: 20px;">‹ 返回首頁</div>
      </div>

      <div class="challenge-container" style="max-width: 1000px; margin: 0 auto;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <h1 style="font-size:28px; color: var(--ink, #2b1a0f);">📖 <?= $current_year ?> 年度閱讀挑戰</h1>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="claim-success-box" style="padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2cfb6; background: #faf6ee; <?= $msg_type==='error'?'background:#FCE8E6;color:#C53929;border-color:#FCE8E6;':'' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['claimed'])): ?>
            <div class="claim-success-box" style="padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2cfb6; background: #faf6ee; color: #df6e4b; font-weight: bold;">
                🎉 恭喜完成年度挑戰！成功咬一口獲得 🍩 1000 甜甜圈 與「<?= $current_year ?>年度閱讀達人」榮譽徽章！
            </div>
        <?php endif; ?>

        <?php if (!$has_challenge): ?>
            <div class="challenge-dashboard" style="display: flex; justify-content: center; text-align: center; flex-direction: column; padding: 60px 30px; background: #fff; border: 1px solid #e2cfb6; border-radius: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.01);">
                <div style="font-size: 60px; margin-bottom: 10px;">🎯</div>
                <h2 style="color: var(--ink, #2b1a0f); margin-bottom: 8px;">你尚未啟動 <?= $current_year ?> 年的新挑戰</h2>
                <p style="margin-bottom: 24px; color: var(--muted, #8a7a6e);">給自己設定一個閱讀目標，挑選心儀的書單，完成即可帶走大獎！</p>
                <div style="text-align: center;">
                    <button class="reward-btn" style="font-size:18px; background: var(--brand, #df6e4b); color:#fff; border:none; padding:12px 30px; border-radius:14px; font-weight:bold; cursor:pointer;" onclick="openChallengeModal()">立即報名挑戰 ✨</button>
                </div>
            </div>

        <?php else: ?>
            <div class="challenge-dashboard" style="background: #fff; border: 1px solid #e2cfb6; border-radius: 24px; padding: 30px; margin-bottom: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center;">
                <div class="dashboard-info">
                    <h2 style="margin:0 0 8px 0; color:var(--ink);">挑戰進度：<?= $completed_count ?> / <?= $my_challenge['target_count'] ?> 本</h2>
                    <p style="color:var(--muted); font-size:14px; margin:4px 0;">年度目標：在 <?= $current_year ?> 年底前讀完指定的 <?= $my_challenge['target_count'] ?> 本故事書。</p>
                    <p style="margin-top: 12px; color:var(--brand, #df6e4b); font-size:13px; font-weight:bold;">🎁 獎勵清單：🍩 1000 甜甜圈 ＋ 🏆 專屬年度榮譽勳章</p>
                </div>

                <div class="progress-section" style="text-align: center; background: #fffbf5; padding: 20px; border-radius: 16px; border: 1px solid #e2cfb6;">
                    <div class="progress-text" style="font-size: 16px; font-weight:bold; margin-bottom: 8px;">已達成 <span style="font-size:28px; color:var(--brand);"><?= $progress_percent ?></span> %</div>
                    <div class="bar-bg" style="background: #eee5d8; height: 12px; border-radius: 6px; overflow:hidden;">
                        <div class="bar-fill" style="width: <?= $progress_percent ?>%; background: var(--brand, #df6e4b); height:100%; transition: width 0.3s;"></div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 16px;">
                        <?php if ($my_challenge['is_rewarded'] == 1): ?>
                            <button class="reward-btn" style="background:#ccc; color:#666; border:none; padding:10px 20px; border-radius:10px; cursor:not-allowed;" disabled>獎勵已領取 🏆</button>
                        <?php elseif ($completed_count >= $my_challenge['target_count']): ?>
                            <form method="POST" action="challenge.php">
                                <input type="hidden" name="action" value="claim_reward">
                                <button type="submit" class="reward-btn" style="background: var(--brand, #df6e4b); color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:bold; cursor:pointer; box-shadow: 0 4px 12px rgba(223,110,75,0.2);">🎁 點我領取 1000 🍩！</button>
                            </form>
                        <?php else: ?>
                            <button class="reward-btn" style="background:#bbb; color:#fff; border:none; padding:10px 20px; border-radius:10px; cursor:not-allowed;" title="還沒達標喔！" disabled>尚未達標 🍩</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h3 style="font-size: 18px; margin-bottom: 15px; color:var(--ink);">🎯 你的挑戰書單明細：</h3>
            <div class="challenge-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px;">
                <?php foreach ($challenge_books as $book): ?>
                    <div class="book-challenge-card" onclick="location.href='story.php?id=<?= $book['id'] ?>'" style="cursor:pointer; background:#fff; border:1px solid #e2cfb6; border-radius:16px; padding:16px; text-align:center; transition: transform 0.2s;">
                        <div style="height: 150px; display:flex; align-items:center; justify-content:center; overflow:hidden; border-radius:8px; background:#faf6ee; margin-bottom:10px; border: 1px solid #eee5d8;">
                            <img src="<?= !empty($book['cover_image']) ? htmlspecialchars($book['cover_image']) : 'https://placehold.co/110x150?text=Bite+Story' ?>" style="max-height:100%; max-width:100%; object-fit:cover;" class="cover" alt="">
                        </div>
                        <div class="title" style="font-weight:bold; font-size:14px; color:var(--ink); margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($book['title']) ?>"><?= htmlspecialchars($book['title']) ?></div>
                        
                        <?php if ($book['progress'] >= 100): ?>
                            <span class="book-status-badge status-completed" style="display:inline-block; font-size:12px; background:#e6f4ea; color:#137333; padding:4px 10px; border-radius:20px; font-weight:bold;">已讀完 ✓</span>
                        <?php else: ?>
                            <span class="book-status-badge status-reading" style="display:inline-block; font-size:12px; background:#fef7e0; color:#b06000; padding:4px 10px; border-radius:20px; font-weight:bold;">閱讀中 <?= (int)$book['progress'] ?>%</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </main>

</div> <?php if (!$has_challenge): ?>
<div class="challenge-modal" id="challengeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:3000; display:none; place-items:center;">
    <div class="modal-card" style="background:#fff; border-radius:24px; padding:30px; width:90%; max-width:500px; box-shadow:0 8px 32px rgba(0,0,0,0.15); box-sizing:border-box;">
        <div class="modal-header" style="display:flex; justify-content:between; align-items:center; margin-bottom:20px; justify-content: space-between;">
            <h3 style="font-size:20px; font-weight:bold; margin:0; color:var(--ink);">✨ 開啟 <?= $current_year ?> 年度閱讀挑戰</h3>
            <button class="close-modal-btn" style="background:none; border:none; font-size:28px; cursor:pointer; color:#999;" onclick="closeChallengeModal()">×</button>
        </div>
        
        <form method="POST" action="challenge.php">
            <input type="hidden" name="action" value="start_challenge">
            
            <div class="form-section" style="margin-bottom: 20px;">
                <label for="target_count" style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; color:var(--ink);">1. 設定今年的目標閱讀本數：</label>
                <input type="number" name="target_count" id="target_count" style="width:100%; padding:10px; border:1px solid #e2cfb6; border-radius:10px; box-sizing:border-box;" min="1" value="3" required>
            </div>

            <div class="form-section" style="margin-bottom: 20px;">
                <label style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; color:var(--ink);">2. 挑選你想加入挑戰的書單（可多選，需大於或等於目標本數）：</label>
                <input type="text" id="bookSearchInput" placeholder="🔍 搜尋書名..." 
                    style="width:100%; padding:10px; border:1px solid #e2cfb6; border-radius:10px; box-sizing:border-box; margin-bottom:8px; font-size:14px;">
                <div class="selectable-book-list" style="max-height:200px; overflow-y:auto; border:1px solid #e2cfb6; border-radius:12px; padding:12px; background:#fffbf5;">
                    <?php if(!empty($all_stories)): ?>
                        <?php foreach($all_stories as $story): ?>
                            <label class="book-checkbox-item" style="display:flex; align-items:center; gap:8px; padding:6px 0; cursor:pointer; font-size:14px; color:var(--ink);">
                                <input type="checkbox" name="books[]" value="<?= $story['id'] ?>">
                                <span><?= htmlspecialchars($story['title']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#999; padding: 10px; margin:0; font-size:13px; text-align:center;">目前站內還沒有書籍可以挑戰喔！</p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="text-align: center; margin-top: 25px;">
                <button type="submit" class="reward-btn" style="width: 100%; padding: 14px; background: var(--brand, #df6e4b); color:#fff; border:none; border-radius:12px; font-weight:bold; font-size:16px; cursor:pointer;">立約報名！全力以赴 🚀</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// 🔍 搜尋書名過濾 checkbox 清單
const bookSearchInput = document.getElementById('bookSearchInput');
if (bookSearchInput) {
    bookSearchInput.addEventListener('input', function () {
        const keyword = this.value.trim().toLowerCase();
        document.querySelectorAll('.book-checkbox-item').forEach(item => {
            const title = item.querySelector('span').textContent.toLowerCase();
            item.style.display = title.includes(keyword) ? 'flex' : 'none';
        });
    });
}

    function updateStoryIdFromInput() {
        let matched = false;
        for (let opt of stationOptions) {
            if (opt.value === bookSearchInput.value) {
                selectedStoryId.value = opt.getAttribute('data-id');
                matched = true;
                break;
            } Matched = true;
        }
        if (!matched) {
            selectedStoryId.value = ""; 
        }
    }

    bookSearchInput.addEventListener('input', updateStoryIdFromInput);

    
    // 表單防呆
    document.getElementById('throwBottleForm').addEventListener('submit', function(e) {
        if (!selectedStoryId.value) {
            alert('⚠️ 請務必輸入或點選「正確的站內故事名稱」才可以發送漂流瓶喔！');
            e.preventDefault();
        }
    });


    
// 控制彈跳卡片視窗
function openChallengeModal() {
    const modal = document.getElementById('challengeModal');
    if(modal) modal.style.display = 'grid';
}

function closeChallengeModal() {
    const modal = document.getElementById('challengeModal');
    if(modal) modal.style.display = 'none';
}

// 點擊視窗外部也可以關閉
window.onclick = function(event) {
    const modal = document.getElementById('challengeModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// 確保一開始它是隱藏的（防止 CSS 沒有加到 display:none 跑版）
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById('challengeModal');
    if(modal) modal.style.display = 'none';
});
</script>
</body>
</html>