<?php
// 咬一口故事 - 我的作品頁面 works.php (智慧時間判定發布版)
date_default_timezone_set('Asia/Taipei'); // 💡 強制設定台灣時區，防止比對時間差 8 小時
session_start();
require_once 'db.php'; 

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?back_url=works.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // 1. 先撈使用者基本資料（供側邊欄與大頭貼判斷）
    $stmt_user = $pdo->prepare("SELECT id, username, nickname,avatar , role, is_contracted, donuts, wheat FROM users WHERE id = ?");
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
    // 2. 撈出當前使用者所有的故事作品
    $stmt_stories = $pdo->prepare("SELECT id, title, type, intro, tags, status, created_at FROM `stories` WHERE user_id = ? ORDER BY created_at DESC");
    $stmt_stories->execute([$current_user_id]);
    $stories = $stmt_stories->fetchAll(PDO::FETCH_ASSOC);

    // 3. 撈出這些故事下，所有「已發布」或「排程中」的章節
    $my_works = [];
    if (!empty($stories)) {
        foreach ($stories as $story) {
            // 💡 確保撈出 publish_at 欄位，供後續前端精準比對當前時間
            $stmt_chapters = $pdo->prepare("
                SELECT id, chapter_number, title, word_count, status, price_donuts, egg_content, publish_at, created_at 
                FROM `chapters` 
                WHERE story_id = ? AND status IN ('published', 'scheduled')
                ORDER BY chapter_number ASC
            ");
            $stmt_chapters->execute([$story['id']]);
            $chapters = $stmt_chapters->fetchAll(PDO::FETCH_ASSOC);
            
            // 包裝成方便前端渲染的巢狀陣列
            $my_works[] = [
                'story' => $story,
                'chapters' => $chapters
            ];
        }
    }

} catch (PDOException $e) {
    die("讀取作品失敗：" . $e->getMessage());
}
?>

<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>我的作品 ‧ 咬一口故事</title>
    <link rel="stylesheet" href="css/create.css"> 
    <style>
        .story-group {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .story-header {
            border-bottom: 2px dashed var(--line);
            padding-bottom: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
        }
        .story-meta h3 {
            margin: 0 0 8px 0;
            font-size: 22px;
            color: var(--ink);
        }
        .story-tags {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .tag-badge {
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
        }
        .chapter-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chapter-row {
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .chapter-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-badge {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            cursor: help;
        }
        .status-published { background: #e6f4ea; color: #137333; }
        .status-scheduled { background: #fef7e0; color: #b06000; }
        
        .egg-badge {
            background: #fff5f7;
            color: #D6557A;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn-action {
            background: #fff;
            border: 1px solid var(--line);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-action:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .empty-works {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            background: var(--bg-1);
            border-radius: 12px;
            border: 2px dashed var(--line);
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
            <a href="works.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800;"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
            <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
            
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
        <a href="works.php" class="sheet-item" style="background: linear-gradient(135deg, #FFEEDD, #FFE4D2); border: 1px solid var(--primary-2);"><span>📖</span>我的作品</a>
        <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
        
    </div>
    <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<main class="main">
    <div class="page-header">
        <h2 class="page-title">📖 我的作品專區</h2>
        
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

    <div style="margin-top: 20px;">
        <?php if (empty($my_works)): ?>
            <div class="empty-works">
                <p style="font-size: 48px; margin-bottom: 10px;">✨</p>
                <p>目前還沒有發布過的作品喔！快點擊左側「開始寫故事」記錄你的奇思妙想吧！</p>
                <br>
                <a href="create.php" class="btn-action" style="background: var(--primary); color:#fff; padding: 10px 20px;">創立第一個故事 ✍️</a>
            </div>
        <?php else: ?>
            <?php foreach ($my_works as $item): 
                $story = $item['story'];
                $chapters = $item['chapters'];
            ?>
                <div class="story-group">
                    <div class="story-header">
                        <div class="story-meta">
                            <h3>
                                <span style="font-size: 14px; padding: 3px 8px; border-radius: 20px; background: #ebdcd0; color: #5a483c; font-weight: bold; margin-right: 8px; vertical-align: middle;">
                                    <?= $story['type'] === 'single' ? '短篇一發完' : '長篇連載' ?>
                                </span>
                                <?= htmlspecialchars($story['title']) ?>
                            </h3>
                            <p style="color: var(--muted); margin: 5px 0 0 0; font-size: 14px; font-style: italic;">
                                💡 引文：<?= htmlspecialchars($story['intro']) ?>
                            </p>
                            <div class="story-tags">
                                <?php 
                                if (!empty($story['tags'])) {
                                    foreach (explode(',', $story['tags']) as $tag) {
                                        echo '<span class="tag-badge">#' . htmlspecialchars(trim($tag)) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div>
                            <?php if($story['type'] === 'serial'): ?>
                                <a href="create.php?story_id=<?= $story['id'] ?>&post_type=chapter" class="btn-action" style="background: #FFEEDD; color: #A0522D; border-color: #FFE4D2;">➕ 續寫下一章</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="chapter-list">
                        <?php if (empty($chapters)): ?>
                            <p style="color: var(--muted); font-size: 13px; padding-left: 10px;">⚠️ 本故事目前只有草稿，暫無公開發布的章節。</p>
                        <?php else: ?>
                            <?php foreach ($chapters as $ch): ?>
                                <div class="chapter-row">
                                    <div class="chapter-info">
                                        <?php 
                                            // 💡 精準比對伺服器現在的 Linux Timestamp
                                            $now = time();
                                            $publish_timestamp = !empty($ch['publish_at']) ? strtotime($ch['publish_at']) : 0;
                                            $is_time_up = ($publish_timestamp > 0 && $publish_timestamp <= $now);
                                            
                                            if ($ch['status'] === 'published' || ($ch['status'] === 'scheduled' && $is_time_up)): 
                                        ?>
                                            <span class="status-badge status-published">已發布</span>
                                        <?php else: ?>
                                            <span class="status-badge status-scheduled" title="預約發布：<?= htmlspecialchars($ch['publish_at']) ?> &#10;現在時間：<?= date('Y-m-d H:i:s', $now) ?>">⏳ 排程中</span>
                                        <?php endif; ?>

                                        <strong>
                                            <?= $story['type'] === 'serial' ? '第 ' . $ch['chapter_number'] . ' 章：' : '' ?>
                                            <?= htmlspecialchars($ch['title']) ?>
                                        </strong>
                                        
                                        <span style="font-size: 13px; color: var(--muted);"><?= $ch['word_count'] ?> 字</span>

                                        <?php if (!empty($ch['egg_content']) || $ch['price_donuts'] > 0): ?>
                                            <span class="egg-badge">🍩 彩蛋 (<?= $ch['price_donuts'] ?> 點)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span style="font-size: 12px; color: var(--muted); margin-right: 10px;">
                                            📅 <?= date('Y-m-d', strtotime($ch['publish_at'] ?? $ch['created_at'])) ?>
                                        </span>
                                        <a href="story.php?chapter_id=<?= $ch['id'] ?>" target="_blank" class="btn-action">線上閱讀 🔍</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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