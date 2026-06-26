<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php'; // 請確保你的 PDO 連線變數名稱叫 $pdo

// 💡 點數不同步修復核心：如果使用者已登入，直接從資料庫撈取最新點數
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    try {
        $stmtUser = $pdo->prepare("SELECT donuts, wheat FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // 同步更新至 Session 保持全站最新狀態
            $_SESSION['donuts'] = intval($user_data['donuts']);
            $_SESSION['wheat'] = intval($user_data['wheat']);
        }
    } catch (Exception $e) {
        // 發生錯誤時降級使用 Session 舊值防止網站掛掉
    }
}

// 💡 初始化導覽列變數
$user_donuts = $_SESSION['donuts'] ?? 0;
$user_wheat = $_SESSION['wheat'] ?? 0;

// 🎯 調整 3：初始化頭貼變數。如果 Session 裡有且不為空就用它；否則用你原本設計的預設圖
$user_avatar = (!empty($_SESSION['avatar'])) ? $_SESSION['avatar'] : 'https://i.pravatar.cc/100?img=47';

// 💡 動態撈取站內現有的所有故事作品，用來渲染搜尋下拉選單
$all_stories = [];
try {
    $stmtStories = $pdo->query("SELECT id, title FROM stories ORDER BY id DESC");
    $all_stories = $stmtStories->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 發生錯誤時保持空陣列
}

// 處理「丟瓶子」表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'throw') {
    $story_id = intval($_POST['story_id'] ?? 0);
    $content = trim($_POST['notes_text'] ?? '');
    
    if (!empty($content) && $story_id > 0) {
        $nickname = $_SESSION['nickname'] ?? ($_SESSION['username'] ?? '神秘讀者');

        try {
            $stmt = $pdo->prepare("INSERT INTO drifting_bottles (user_id, nickname, story_id, content) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $nickname, $story_id, $content])) {
                echo "<script>alert('🍾 你的真心話已被封入玻璃瓶，拋向遠方故事海...'); location.href='bottle.php';</script>";
                exit;
            }
        } catch (Exception $e) {
            echo "<script>alert('大海風浪太大，瓶子被沖回岸邊...');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>漂流瓶 - 咬一口故事</title>
    <style>
        /* ==================== 🎨 完美移植與大海氛圍調色盤 ==================== */
        :root {
            --bg-card-rgba: rgba(255, 255, 255, 0.85); 
            --ink: #33444d;    
            --brand: #2b6e8c;  
            --brand-light: rgba(230, 242, 247, 0.7); 
            --muted: #7fa1b0;  
            --border: #cadde6; 
            --nav-text: #415a66;
            --nav-text-hover: #e06a55; 
            --active-color: #e06a55;
            --sub-menu-bg: #f0f7fa;
            --shadow: 0 8px 30px rgba(43, 110, 140, 0.15); 
        }

        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { 
            background-image: url('img/ocean-bg.png'); 
            background-attachment: fixed;          
            background-size: cover;                
            background-position: center bottom;    
            background-repeat: no-repeat;
            color: var(--ink); margin: 0; padding: 0; line-height: 1.6;
            min-height: 100vh;
        }
 
        /* ── 導覽列樣式完全同步 ── */
        .navbar {
            background: #fff8ee; min-height: 75px; display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1000; gap: 20px;
        }
        .nav-left { display: flex; align-items: center; gap: 30px; flex: 1; }
        .logo { font-size: 24px; font-weight: 900; color: var(--brand); text-decoration: none; letter-spacing: 1px; white-space: nowrap; }
        .nav-links { display: flex; gap: 24px; align-items: center; flex-wrap: nowrap; }
        .nav-links a, .btn-create-trigger {
            text-decoration: none; color: var(--nav-text); font-size: 15px; font-weight: bold;
            display: flex; align-items: center; position: relative; transition: color 0.2s ease; gap: 4px; white-space: nowrap;
        }
        .nav-links a:hover, .btn-create-trigger:hover { color: var(--nav-text-hover); }
        .nav-links a.active { color: var(--active-color); }
 
        .creator-menu-wrapper { position: relative; display: flex; align-items: center; padding-bottom: 10px; margin-bottom: -10px; }
        .btn-create-trigger { background: transparent; border: none; cursor: pointer; padding: 0; }
        .creator-dropdown {
            position: absolute; top: 35px; left: 0; background: #fff8ee; border: 1px solid var(--border);
            border-radius: 8px; box-shadow: 0 10px 30px rgba(140,98,57,0.1); width: 180px;
            display: none; flex-direction: column; padding: 8px 0; z-index: 2000;
        }
        .creator-menu-wrapper:hover .creator-dropdown { display: flex; }
        .creator-dropdown a { padding: 12px 20px; text-decoration: none; color: var(--nav-text); font-size: 15px; font-weight: 500; display: block; }
        .creator-dropdown a:hover { background: var(--sub-menu-bg); color: var(--active-color); }
 
        .nav-right { display: flex; align-items: center; flex-shrink: 0; gap: 20px; }
        .user-challenge { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: bold; }
        .challenge-bar-bg { width: 70px; height: 8px; background: #eaddc5; border-radius: 4px; overflow: hidden; }
        .challenge-bar-fill { height: 100%; background: var(--active-color); }
        .user-wallet { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: bold; background: #f3ede2; padding: 6px 12px; border-radius: 20px; }
        .wallet-item { display: flex; align-items: center; gap: 2px; }
 
        .avatar-wrap { position: relative; cursor: pointer; display: inline-block; flex-shrink: 0; }
        .avatar-wrap::after {
            content: ""; position: absolute; inset: -3px; border-radius: 50%;
            background: conic-gradient(from 180deg, var(--brand), #e36a4b, #eab365, var(--brand)); z-index: -1;
        }
        .avatar { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: block; }
        .rwd-avatar-item { display: none; margin-left: -5px; }
 
        .avatar-popover {
            position: absolute; top: calc(100% + 14px); right: 0; width: 260px;
            background: linear-gradient(180deg, #FFFCF6 0%, #FFF6E8 100%); border: 1px solid var(--border);
            border-radius: 20px; padding: 20px; box-shadow: 0 10px 25px rgba(74,60,49,0.08);
            opacity: 0; visibility: hidden; transform: translateY(10px);
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s; z-index: 2000; text-align: center;
        }
        .avatar-wrap:hover .avatar-popover { opacity: 1; visibility: visible; transform: translateY(0); }
        .avatar-popover::before {
            content: ''; position: absolute; top: -6px; right: 18px; width: 10px; height: 10px;
            background: #FFFCF6; border-left: 1px solid var(--border); border-top: 1px solid var(--border); transform: rotate(45deg);
        }
        .avatar-wrap .popover-btn {
            display: block; width: 100%; padding: 11px; font-weight: 700; border-radius: 14px;
            text-align: center; font-size: 14px; margin-top: 10px; transition: .2s ease;
            border: 0 !important; cursor: pointer; text-decoration: none;
        }
        .avatar-wrap .avatar-popover a.popover-btn.primary { background: linear-gradient(135deg, #e36a4b, #e06a55) !important; color: #fff !important; }
        .avatar-wrap .avatar-popover a.popover-btn.primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .avatar-wrap .avatar-popover a.popover-btn.secondary { background: #8C7C72 !important; color: #fff !important; }
        .avatar-wrap .avatar-popover a.popover-btn.secondary:hover { background: #76685F !important; transform: translateY(-1px); }

        /* ==================== 🍾 漂流瓶核心排版與故事海特效 ==================== */
        .bottle-container {
            max-width: 1100px; margin: 40px auto; padding: 0 20px;
            display: grid; grid-template-columns: 1fr 380px; gap: 30px;
            position: relative; 
            z-index: 10;
        }
        
        .panel { 
            background-color: var(--bg-card-rgba); 
            backdrop-filter: blur(12px);          
            -webkit-backdrop-filter: blur(12px);  
            border: 1px solid rgba(202, 221, 230, 0.5); 
            border-radius: 20px; padding: 28px; box-shadow: var(--shadow); 
        }
        
        .panel-title { font-size: 20px; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 1px dashed var(--border); padding-bottom: 12px; color: var(--brand); font-weight: 800; }

        .sea-stage { display: flex; flex-direction: column; gap: 24px; }
        
        .catch-trigger-box { 
            text-align: center; padding: 48px 20px; 
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(242, 248, 250, 0.7) 100%); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 2px dashed rgba(43, 110, 140, 0.6); 
            border-radius: 24px;
            position: relative; overflow: hidden;
        }
        
        .catch-btn { 
            background: linear-gradient(135deg, var(--active-color), #f78472); 
            color: #fff; border: none; padding: 16px 36px; font-size: 18px; font-weight: bold; 
            border-radius: 50px; cursor: pointer; 
            box-shadow: 0 6px 20px rgba(224, 106, 85, 0.4); 
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .catch-btn:hover { 
            transform: translateY(-3px) scale(1.03); 
            box-shadow: 0 10px 25px rgba(224, 106, 85, 0.5); 
        }
        .catch-btn:active { transform: translateY(1px) scale(0.98); }
        .sea-hint { color: var(--ink); font-size: 14px; margin-top: 14px; font-weight: 700; text-shadow: 0 1px 1px rgba(255,255,255,0.8); }

        .bottle-card { 
            position: relative; 
            border-left: 6px solid var(--brand); 
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(251, 253, 254, 0.6) 100%);
            animation: floatWave 6s ease-in-out infinite, fadeIn 0.6s ease; 
        }
        
        @keyframes floatWave {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(0.5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        
        .bottle-meta { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--muted); margin-bottom: 14px; }
        .sender-info { display: flex; align-items: center; gap: 8px; }
        .sender-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--brand-light); display: grid; place-items: center; font-size: 12px; color: var(--brand); font-weight: bold; }
        .bottle-content { font-size: 16px; line-height: 1.9; margin-bottom: 24px; white-space: pre-wrap; color: var(--ink); font-weight: 700; letter-spacing: 0.5px; }
        
        .ref-book-card {
            display: flex; align-items: center; background-color: rgba(230, 242, 247, 0.6); 
            padding: 14px 18px;
            border-radius: 14px; text-decoration: none; color: inherit; border: 1px solid transparent; 
            transition: all 0.3s ease;
        }
        .ref-book-card:hover { 
            border-color: rgba(43, 110, 140, 0.8); 
            background-color: #fff; 
            transform: translateX(4px); 
            box-shadow: 0 4px 12px rgba(43, 110, 140, 0.1);
        }
        .ref-book-icon { font-size: 26px; margin-right: 14px; }
        .ref-book-title { font-weight: 800; font-size: 15px; margin-bottom: 3px; color: var(--brand); }
        .ref-book-author { font-size: 12px; color: var(--muted); font-weight: 700; }

        .write-stage { height: fit-content; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: bold; margin-bottom: 8px; color: var(--nav-text); text-shadow: 0 1px 1px rgba(255,255,255,0.6); }
        .form-select, .form-textarea { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px; background-color: rgba(250, 253, 254, 0.6); color: var(--ink); font-size: 14px; box-sizing: border-box; outline: none; transition: all 0.25s; }
        .form-textarea { height: 160px; resize: vertical; }
        .form-select:focus, .form-textarea:focus { border-color: var(--brand); background-color: #fff; box-shadow: 0 0 0 3px rgba(43, 110, 140, 0.15); }
        
        .throw-btn { 
            width: 100%; background: linear-gradient(135deg, var(--brand), #3d8ba6); 
            color: #fff; border: none; padding: 14px; font-size: 15px; font-weight: bold; 
            border-radius: 10px; cursor: pointer; transition: all 0.25s; 
            box-shadow: 0 4px 12px rgba(43, 110, 140, 0.2);
        }
        .throw-btn:hover { 
            background: linear-gradient(135deg, #20546b, var(--brand)); 
            transform: translateY(-1px); 
            box-shadow: 0 6px 18px rgba(43, 110, 140, 0.3);
        }

        @media (max-width: 1200px) { .bottle-container { max-width: 700px; display: flex; flex-direction: column; gap: 24px; padding: 0 20px; } .sea-stage { order: 1; } .write-stage { order: 2; } }
        @media (max-width: 768px) {
            .navbar { position: static !important; display: flex; flex-direction: column; align-items: flex-start; padding: 12px 16px; gap: 14px; width: 100%; }
            .nav-left { width: 100%; display: flex; flex-direction: column; align-items: flex-start; gap: 12px; }
            .nav-links { width: 100%; display: flex; flex-direction: row; flex-wrap: wrap; gap: 10px; }
            .nav-links a { font-size: 13px !important; padding: 4px; }
            .nav-right { display: none !important; }
            .rwd-avatar-item { display: inline-block !important; position: relative; flex-shrink: 0; margin-left: auto; transform: scale(0.85); }
            .bottle-container { padding: 0 12px; margin: 15px auto; }
            .panel { padding: 22px; }
        }
    </style>
</head>
<body>

<header class="navbar">
    <div class="nav-left">
        <a href="explore.php" class="logo">咬一口故事</a>
        <nav class="nav-links">
            <a href="index.php">🏠 首頁</a>
            <a href="explore.php">🔍 探索</a>
            <a href="bottle.php" class="active">🍾 漂流瓶</a>
            <div class="divider" style="width:1px; height:16px; background:var(--border); margin:0 4px;"></div>
            <a href="profile.php">👤 個人頁</a>
            <a href="bookshelf.php">📚 我的書架</a>
            <a href="cart.php">🛒 購物車</a>
 
            <div class="creator-menu-wrapper">
                <button class="btn-create-trigger">✍️ 發布新作 ▾</button>
                <div class="creator-dropdown">
                    <a href="create.php" style="background:var(--sub-menu-bg); color:var(--active-color); font-weight:800; border-bottom:1px dashed var(--border);">➕ 開始寫故事</a>
                    <a href="works.php">📖 我的作品</a>
                    <a href="drafts.php">📁 草稿箱</a>
                    
                </div>
            </div>
 
            <div class="avatar-wrap rwd-avatar-item">
                <img class="avatar" src="https://i.pravatar.cc/100?img=47" alt="User">
                <div class="avatar-popover" onclick="event.stopPropagation()">
                    <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">您好</div>
                    <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                    <a href="logout.php" class="popover-btn secondary">登出帳號</a>
                </div>
            </div>
        </nav>
    </div>
 
    <div class="nav-right">
        <div class="user-challenge">
            <span>📖 閱讀挑戰</span>
            <div class="bar"><i style="width:<?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%"></i></div>
            <strong><?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%</strong>
        </div>
        <div class="user-wallet">
            <div class="wallet-item">🍩 <span id="walletDonuts"><?= number_format($user_donuts) ?></span></div>
            <div class="wallet-item">🌾 <span><?= number_format($user_wheat) ?></span></div>
        </div>
        <div class="avatar-wrap">
            <a href="profile.php">
                <img class="avatar" src="<?= htmlspecialchars($user_avatar) ?>" alt="User" style="cursor: pointer; width: 46px; height: 46px; border-radius: 50%; object-fit: cover;">
            </a>
            <div class="avatar-popover" onclick="event.stopPropagation()">
                <div class="popover-title">您好，<?= htmlspecialchars($user['name']) ?></div>
                <div class="popover-desc">今天想讀點什麼呢？</div>
                <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                <a href="logout.php" class="popover-btn secondary">登出帳號</a>
            </div>
        </div>
    </div>
</header>

<div class="bottle-container">
    
    <div class="sea-stage">
        <div class="panel catch-trigger-box">
            <button class="catch-btn" id="btnCatch">🌊 從故事海中撈一個瓶子</button>
            <div class="sea-hint">隨機遇見某位讀者留下的真心推薦與心得筆記</div>
        </div>

        <div class="panel bottle-card" id="bottleDisplay">
            <div class="panel-title">✨ 剛撈上岸的漂流瓶</div>
            <div class="bottle-meta">
                <div class="sender-info">
                    <div class="sender-avatar">👤</div>
                    <span>神秘讀者 · <strong>測試帳戶01</strong></span>
                </div>
                <span>🕒 剛剛撈起</span>
            </div>
            <div class="bottle-content">今天熬夜把這篇故事看完了！文字溫柔得像在說每個人心底最深的小祕密。看到最後章節真的忍不住掉眼淚，但又是被治癒的那種淚水 🕯️。</div>
            
            <a href="story.php?id=6" class="ref-book-card">
                <div class="ref-book-icon">📚</div>
                <div>
                    <div class="ref-book-title">深夜限時的時空照照相館</div>
                    <div class="ref-book-author">分類標籤：奇幻,治癒,反轉,深夜,時空</div>
                </div>
            </a>
        </div>
    </div>

    <div class="panel write-stage">
        <div class="panel-title">✉️ 封裝你的漂流瓶</div>
        
        <form action="bottle.php" method="POST" id="throwBottleForm">
            <input type="hidden" name="action" value="throw">
            
            <input type="hidden" name="story_id" id="selectedStoryId" value="">
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="bookSearchInput" style="margin-bottom: 0;">📌 輸入你想推薦的作品書名</label>
                </div>   
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <input type="text" id="bookSearchInput" class="form-select" placeholder="🔍 打字搜尋書名" list="stationStoriesData" autocomplete="off" required style="width: 100%;">
                
                <datalist id="stationStoriesData">
                    <?php foreach ($all_stories as $b): ?>
                        <option value="<?= htmlspecialchars($b['title']) ?>" data-id="<?= $b['id'] ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label for="notesText">💬 寫下你當下的閱讀心得或感悟</label>
                <textarea id="notesText" name="notes_text" class="form-textarea" placeholder="寫點什麼吧... 你的真心話會隨機出現在某位讀者的海灘上。" required></textarea>
            </div>

            <button type="submit" class="throw-btn">🍾 拋向故事海</button>
        </form>
    </div>

</div>

<script>
    // 🔍 搜尋書名與 Datalist 數據聯動監聽
    const bookSearchInput = document.getElementById('bookSearchInput');
    const selectedStoryId = document.getElementById('selectedStoryId');
    const stationOptions = document.querySelectorAll('#stationStoriesData option');

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

    // 異步打撈邏輯
    document.getElementById('btnCatch').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerText = "⚡ 正在海中打撈瓶子...";

        fetch('get_random_bottle.php')
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = "🌊 從故事海中撈一個瓶子";

                if (data.error) {
                    alert(data.error);
                    return;
                }

                const display = document.getElementById('bottleDisplay');
                
                display.style.display = 'none';
                display.style.animation = 'none';
                setTimeout(() => { 
                    display.style.animation = 'floatWave 6s ease-in-out infinite, fadeIn 0.6s ease'; 
                    display.style.display = 'block';
                }, 10);

                display.innerHTML = `
                    <div class="panel-title">✨ 剛撈上岸的漂流瓶</div>
                    <div class="bottle-meta">
                        <div class="sender-info">
                            <div class="sender-avatar">👤</div>
                            <span>神秘讀者 · <strong>${escapeHtml(data.nickname)}</strong></span>
                        </div>
                        <span>🕒 剛剛撈起</span>
                    </div>
                    <div class="bottle-content">${escapeHtml(data.content)}</div>
                    
                    <a href="story.php?id=${data.story_id}" class="ref-book-card">
                        <div class="ref-book-icon">📚</div>
                        <div>
                            <div class="ref-book-title">${escapeHtml(data.story_title)}</div>
                            <div class="ref-book-author">分類標籤：${escapeHtml(data.story_tags || '未分類')}</div>
                        </div>
                    </a>
                `;
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = "🌊 從故事海中撈一個瓶子";
                alert('哎呀，風浪太大沒撈到，再撈一次！');
            });
    });

    function escapeHtml(text) {
        if(!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>
</body>
</html>