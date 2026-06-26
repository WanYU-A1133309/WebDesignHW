<?php

// 🔴 頂部強迫噴出所有錯誤訊息（除錯專用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// 咬一口故事 - 草稿箱 drafts.php (完美同步側邊欄版 + 快捷發布功能)
session_start();
require_once 'db.php'; 

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?back_url=drafts.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$alert_message = '';

// 🔥 【核心快捷發布功能】處理直接發布請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_publish') {
    $pub_chapter_id = intval($_POST['chapter_id'] ?? 0);
    try {
        // 驗證這筆草稿是否真的屬於目前使用者
        $check_stmt = $pdo->prepare("
            SELECT c.id, s.type FROM `chapters` c 
            JOIN `stories` s ON c.story_id = s.id 
            WHERE c.id = ? AND s.user_id = ?
        ");
        $check_stmt->execute([$pub_chapter_id, $current_user_id]);
        $chapter_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($chapter_data) {
            $pdo->beginTransaction();
            
            // 1. 將章節狀態改為發布
            $update_ch = $pdo->prepare("UPDATE `chapters` SET `status` = 'published' WHERE `id` = ?");
            $update_ch->execute([$pub_chapter_id]);
            
            // 2. 自動更新對應故事的狀態
            $story_status = ($chapter_data['type'] === 'single') ? 'completed' : 'ongoing';
            $update_story = $pdo->prepare("UPDATE `stories` SET `status` = ? WHERE `id` = (SELECT story_id FROM `chapters` WHERE id = ?)");
            $update_story->execute([$story_status, $pub_chapter_id]);
            
            $pdo->commit();
            header("Location: drafts.php?alert=publish_success");
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alert_message = "❌ 發布失敗：" . $e->getMessage();
    }
}

// 接收來自 create.php 的提示
$alert_status = $_GET['alert'] ?? null;
if ($alert_status === 'draft_success') {
    $alert_message = "💾 草稿已成功儲存！";
} elseif ($alert_status === 'publish_success') {
    $alert_message = "🚀 文章已成功正式發布！";
}

try {
    // 1. 先撈使用者基本資料以供大頭貼與側邊欄判斷（同步 create.php）
    $stmt_user = $pdo->prepare("SELECT id, username, nickname, role, is_contracted, donuts, wheat, avatar FROM users WHERE id = ?");
    $stmt_user->execute([$current_user_id]);
    $db_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$db_user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $user = [
        'id'            => $db_user['id'],
        'role'          => $db_user['role'],
        'is_contracted' => $db_user['is_contracted'],
        'name'          => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
        'avatar' => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
    ];
    $_SESSION['avatar'] = $user['avatar'];

    // 2. 撈出當前使用者所有狀態為 'draft' (草稿) 的章節
    $stmt = $pdo->prepare("
        SELECT 
            c.id AS chapter_id,
            c.title AS chapter_title,
            c.word_count,
            c.created_at,
            c.price_donuts,
            c.egg_content,
            s.id AS story_id,
            s.title AS story_title,
            s.type AS novel_type
        FROM `chapters` c
        JOIN `stories` s ON c.story_id = s.id
        WHERE s.user_id = ? AND c.status = 'draft'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("草稿箱撈取失敗：" . $e->getMessage());
}
?>

<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>草稿箱 ‧ 咬一口故事</title>
    <link rel="stylesheet" href="css/create.css"> 
    <style>
        .draft-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .draft-card {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .draft-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .draft-info h3 {
            margin: 0 0 6px 0;
            color: var(--ink, #2d3748);
            font-size: 18px;
        }
        .draft-meta {
            font-size: 13px;
            color: var(--muted, #718096);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .draft-badge {
            background: #edf2f7;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .draft-badge.egg {
            background: #fff5f7;
            color: #D6557A;
        }
        .draft-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-edit-draft {
            background: var(--primary, #5A483C);
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.2s;
        }
        .btn-publish-draft {
            background: #2b6cb0;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-publish-draft:hover {
            background: #2c5282;
        }
        .btn-edit-draft:hover {
            opacity: 0.9;
        }
        .empty-draft {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            background: var(--bg-1);
            border-radius: 12px;
            border: 2px dashed var(--line);
        }
        .alert-banner {
            background: #e6fffa;
            border: 1px solid #319795;
            color: #234e52;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        /* ==================== ✨ 同步設計：頭像懸浮視窗專屬 CSS ==================== */
        .avatar-wrap {
            position: relative; 
            cursor: pointer; 
            display: inline-block;
        }

        /* 漸層外圈裝飾 */
        .avatar-wrap::after { 
            content: "";
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: conic-gradient(from 180deg, var(--primary), var(--primary-2), var(--donut), var(--primary));
            z-index: -1; 
        }

        .avatar { 
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: var(--shadow);
            display: block; 
        }

        /* 彈出視窗主體 */
        .avatar-popover { 
            position: absolute; 
            top: calc(100% + 14px); 
            right: 0; 
            width: 260px; 
            background: linear-gradient(180deg, #FFFCF6 0%, #FFF6E8 100%); 
            border: 1px solid var(--line); 
            border-radius: 20px; 
            padding: 20px; 
            box-shadow: var(--shadow-lg); 
            opacity: 0; 
            visibility: hidden; 
            transform: translateY(10px); 
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s; 
            z-index: 150; 
            text-align: center; 
        }

        /* 懸浮觸發顯示 */
        .avatar-wrap:hover .avatar-popover { 
            opacity: 1; 
            visibility: visible; 
            transform: translateY(0); 
        }

        /* 視窗裝飾引導小三角 */
        .avatar-popover::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 18px;
            width: 10px;
            height: 10px;
            background: #FFFCF6;
            border-left: 1px solid var(--line);
            border-top: 1px solid var(--line);
            transform: rotate(45deg);
        }

        /* 視窗內所有按鈕的共用基本結構 */
        .avatar-wrap .popover-btn { 
            display: block; 
            width: 100%; 
            padding: 11px; 
            font-weight: 700; 
            border-radius: 14px; 
            text-align: center; 
            font-size: 14px; 
            margin-top: 10px; 
            transition: .2s ease;
            border: 0 !important;
            cursor: pointer;
            text-decoration: none;
        }

        /* 進入個人中心 (強力指定：維持暖橘漸層) */
        .avatar-wrap .avatar-popover a.popover-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-2)) !important; 
            color: #fff !important; 
            box-shadow: 0 4px 12px rgba(227,106,75,0.2) !important;
        }
        .avatar-wrap .avatar-popover a.popover-btn.primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* 登出帳號 (強力指定：徹底清除可能被通用 CSS 塞入的漸層圖片，強制換上沉穩灰褐色) */
        .avatar-wrap .avatar-popover a.popover-btn.secondary {
            background: #8C7C72 !important; 
            background-image: none !important; 
            color: #fff !important;
            box-shadow: none !important;
        }
        .avatar-wrap .avatar-popover a.popover-btn.secondary:hover {
            background: #76685F !important;
            background-image: none !important;
            transform: translateY(-1px);
        }

        /* 管理員後台按鈕 (深咖色) */
        .avatar-wrap .avatar-popover a.popover-btn.admin-btn {
            background: #5A483C !important; 
            background-image: none !important;
            color: #fff !important;
        }
        .avatar-wrap .avatar-popover a.popover-btn.admin-btn:hover {
            background: #46382E !important;
            background-image: none !important;
        }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="收合 / 展開">‹</button>
    <div class="logo">
        <div class="logo-mark">📖</div>
        <span class="logo-text">咬一口故事</span>
    </div>
    <nav class="nav">
        <a href="index.php" data-label="首頁"><span class="ic ic-home"></span><span class="label">首頁</span></a>
        <a href="explore.php" data-label="探索"><span class="ic ic-search"></span><span class="label">探索</span></a>
        <a href="bottle.php" data-label="漂流瓶"><span class="ic ic-bottle"></span><span class="label">漂流瓶</span></a>
        <div class="divider"></div>
        <a href="profile.php" data-label="個人頁"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
        <a href="bookshelf.php" data-label="我的書架"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
        <a href="cart.php" data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
    </nav>
    
    <div class="publish open" id="publish"> 
        <button class="publish-btn" data-label="發布新作" onclick="event.stopPropagation(); document.getElementById('publish').classList.toggle('open')">
            <span class="ic ic-pen"></span><span class="label">發布新作</span>
        </button>
        <div class="publish-menu">
            <a href="create.php" style="margin-bottom: 4px;">➕ 開始寫故事 / 投稿</a>
            <div class="divider" style="margin: 4px 0;"></div>
            <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
            <a href="drafts.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800;"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
            
        </div>
    </div>
    <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
</aside>

<nav class="mobile-nav">
    <a href="index.php"><span class="ic ic-home"></span>首頁</a>
    <a href="explore.php"><span class="ic ic-search"></span>探索</a>
    <button class="mob-pub-trigger" onclick="toggleMobileSheet(true)"><div class="center-pub-btn">＋</div></button>
    <a href="bookshelf.php"><span class="ic ic-books"></span>書架</a>
    <a href="javascript:void(0)" onclick="openUserModal()"><span class="ic ic-user"></span>我的</a>
</nav>

<div class="mobile-publish-sheet" id="mobileSheet">
    <h4>選擇創作動作</h4>
    <div class="sheet-grid">
        <a href="create.php" class="sheet-item"><span>✍️</span><strong>開始寫故事</strong></a>
        <a href="works.php" class="sheet-item"><span>📖</span>我的作品</a>
        <a href="drafts.php" class="sheet-item" style="background: linear-gradient(135deg, #FFEEDD, #FFE4D2); border: 1px solid var(--primary-2);"><span>📁</span>草稿箱</a>
        
    </div>
    <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<main class="main">
    <div class="page-header">
        <h2 class="page-title">📁 我的草稿箱</h2>
        
        <div class="avatar-wrap">
            <a href="profile.php">
                <img class="avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="User" style="cursor: pointer; width: 46px; height: 46px; border-radius: 50%; object-fit: cover;">
            </a>
            <div class="avatar-popover" onclick="event.stopPropagation()">
                <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">您好，<?= htmlspecialchars($user['name']) ?></div>
                
                <?php if($user['role'] === 'admin'): ?>
                    <a href="admin/index.php" class="popover-btn admin-btn">進入管理員後台</a>
                <?php endif; ?>
                
                <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                <a href="logout.php" class="popover-btn secondary">登出帳號</a>
            </div>
        </div>
    </div>

    <?php if (!empty($alert_message)): ?>
        <div class="alert-banner"><?= htmlspecialchars($alert_message) ?></div>
    <?php endif; ?>

    <div class="draft-list">
        <?php if (empty($drafts)): ?>
            <div class="empty-draft">
                <p style="font-size: 48px; margin-bottom: 10px;">🍃</p>
                <p>草稿箱空空如也，看來你的靈感都已經完美發布囉！</p>
                <br>
                <a href="create.php" class="btn-edit-draft" style="display:inline-block;">去捕捉新靈感 ✍️</a>
            </div>
        <?php else: ?>
            <?php foreach ($drafts as $draft): ?>
                <div class="draft-card">
                    <div class="draft-info">
                        <h3>
                            [<?= $draft['novel_type'] === 'single' ? '短篇' : '連載' ?>] 
                            <?= htmlspecialchars($draft['story_title']) ?> 
                            <?= $draft['novel_type'] === 'serial' ? ' ‧ ' . htmlspecialchars($draft['chapter_title']) : '' ?>
                        </h3>
                        <div class="draft-meta">
                            <span>⏱️ 儲存時間：<?= $draft['created_at'] ?></span>
                            <span>📝 字數：<?= $draft['word_count'] ?> 字</span>
                            <?php if (!empty($draft['egg_content']) || $draft['price_donuts'] > 0): ?>
                                <span class="draft-badge egg">🍩 包含彩蛋 (<?= $draft['price_donuts'] ?>點)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="draft-actions">
                        <a href="create.php?edit_chapter_id=<?= $draft['chapter_id'] ?>" class="btn-edit-draft">繼續編輯 ✍️</a>
                        
                        <form method="POST" style="margin:0;" onsubmit="return confirm('確定要直接發布此章節嗎？');">
                            <input type="hidden" name="action" value="quick_publish">
                            <input type="hidden" name="chapter_id" value="<?= $draft['chapter_id'] ?>">
                            <button type="submit" class="btn-publish-draft">直接發布 🚀</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div class="modal-overlay" id="userModal" onclick="closeUserModal(event)"></div>

<script>
function toggleMobileSheet(open) { 
    const sheet = document.getElementById('mobileSheet');
    if(sheet) sheet.classList.toggle('open', open); 
}
function openUserModal() { 
    const modal = document.getElementById('userModal');
    if(modal) modal.style.display = 'grid'; 
}
function closeUserModal() { 
    const modal = document.getElementById('userModal');
    if(modal) modal.style.display = 'none'; 
}
</script>
</body>
</html>