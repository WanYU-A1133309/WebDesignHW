<?php
// shop.php - 精品書城 (全面修復書籍消失、購物車與引言置中問題)
session_start();
require_once 'db.php';

// 檢查登入狀態
$user_id = $_SESSION['user_id'] ?? 0;

$user = [
    'id'     => 0,
    'role'   => 'user',
    'name'   => '訪客',
    'avatar' => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
    'wheat'  => 0
];
$_SESSION['avatar'] = $user['avatar'];

if ($user_id) {
    try {
        // 撈取使用者基本資料
        $stmt_user = $pdo->prepare("SELECT id, username, nickname, role, wheat, avatar FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $db_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($db_user) {
            $user = [
                'id'     => $db_user['id'],
                'role'   => $db_user['role'],
                'name'   => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
                'avatar' => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
                'wheat'  => intval($db_user['wheat'])
            ];
        }
    } catch (PDOException $e) {
        die("資料庫連線失敗：" . $e->getMessage());
    }
}
$_SESSION['avatar'] = $user['avatar'];

// 接收篩選標籤
$category_filter = $_GET['cat'] ?? 'all';

try {
    // 1. 建立基礎 SQL 語法 (同時支援 is_active 為 1 或 'active' 的情況)
    $sql = "SELECT id, title, author, cover, tag, price, category 
            FROM books 
            WHERE (is_active = 'active' OR is_active = 1)";
    
    $params = [];

    // 2. 條件：如果有特定的分類篩選
    if ($category_filter !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category_filter;
    }

    // 3. 條件：🔥 只有在「使用者已登入 (user_id > 0)」時，才去排除已購買的書籍
    // 這樣可以避免訪客 (ID 為 0) 或是未登入狀態下，因為 user_id 的模糊比對導致不吐資料
    if ($user_id > 0) {
        $sql .= " AND NOT EXISTS (
            SELECT 1 FROM user_shop_shelf 
            WHERE user_shop_shelf.shop_book_id = books.id 
              AND user_shop_shelf.user_id = ?
        )";
        $params[] = $user_id;
    }

    $sql .= " ORDER BY id DESC";

    // 4. 執行查詢
    $stmt_books = $pdo->prepare($sql);
    $stmt_books->execute($params);
    $shop_books = $stmt_books->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("書籍資料撈取失敗：" . $e->getMessage());
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>精品書城 ‧ 咬一口故事</title>
    <link rel="stylesheet" href="css/create.css"> 
    <style>
        /* 🎨 精品書城卡片與網格專屬排版 */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .shop-card {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 16px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .shop-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .book-cover-container {
            width: 100%;
            height: 180px;
            background: #f7fafc;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            overflow: hidden;
            border: 1px solid #edf2f7;
        }
        .book-cover-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .shop-info h3 {
            margin: 0 0 6px 0;
            color: var(--ink, #2d3748);
            font-size: 17px;
            font-weight: 700;
        }
        .shop-meta {
            font-size: 13px;
            color: var(--muted, #718096);
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 15px;
        }
        .shop-badge {
            display: inline-block;
            background: #FFF9E6;
            color: #B37D14;
            border: 1px solid #FFEAA8;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            width: fit-content;
        }
        .price-badge {
            font-size: 16px;
            font-weight: 800;
            color: #D9A441;
            margin-top: 4px;
        }
        .shop-btn-buy {
            background: var(--primary, #5A483C);
            color: #fff;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.2s;
            display: block;
        }
        .shop-btn-buy:hover {
            opacity: 0.9;
        }
        
        .shop-tabs {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            border-bottom: 2px solid var(--line, #e2e8f0);
            padding-bottom: 6px;
        }
        .shop-tab-btn {
            font-size: 15px;
            font-weight: bold;
            color: var(--muted, #718096);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        .shop-tab-btn.active {
            color: var(--primary, #5A483C);
            border-bottom: 3px solid var(--primary, #5A483C);
            background: rgba(90, 72, 60, 0.05);
        }

        /* ==================== ✨ 修正 2：引言彈窗全螢幕絕對水平垂直置中 ==================== */
        .book-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(5px);
            display: none; 
            
            /* 🪐 使用強力 Flex 達成正中央定位 */
            align-items: center;
            justify-content: center;
            
            /* 🪐 超越側邊欄，確保置頂不偏心 */
            z-index: 99999 !important; 
        }
        .book-modal-content {
            background: linear-gradient(180deg, #FFFCF6 0%, #FFF6E8 100%);
            border: 1px solid var(--line);
            border-radius: 24px;
            width: 90%;
            max-width: 440px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            text-align: center;
            box-sizing: border-box;
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-book-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary, #5A483C);
            margin-bottom: 5px;
        }
        .modal-book-author {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 20px;
        }
        .modal-quote-box {
            background: #ffffff;
            border: 1px solid var(--line); 
            border-top: 3px solid var(--primary);
            border-radius: 12px;
            font-style: italic;
            color: var(--ink);
            text-align: center; /* 文字置中 */
            line-height: 1.6;
            padding: 20px;
            margin-bottom: 25px;
            font-size: 15px;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.02);
            white-space: pre-line;
        }
        .modal-close-btn {
            background: #8C7C72;
            color: #fff;
            border: none;
            padding: 11px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: background 0.2s;
        }
        .modal-close-btn:hover {
            background: #76685F;
        }

        /* ==================== ✨ 頭像懸浮視窗專屬 CSS ==================== */
        .avatar-wrap {
            position: relative; 
            cursor: pointer; 
            display: inline-block;
        }
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
        .avatar-wrap:hover .avatar-popover { 
            opacity: 1; 
            visibility: visible; 
            transform: translateY(0); 
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
        <a href="shop.php" data-label="精品書城" style="background: var(--bg-2); color: var(--primary); font-weight: 800;"><span class="ic ic-books"></span><span class="label">精品書城</span></a>
        <a href="cart.php" data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
    </nav>
</aside>

<main class="main">
    <div class="page-header">
        <h2 class="page-title">🏛️ 官方精品書城</h2>
        
        <div style="display:flex; align-items:center; gap:20px;">
            <?php if ($user_id): ?>
                <div style="font-size:14px; font-weight:bold; background:#FFF9E6; color:#B37D14; border:1px solid #FFEAA8; padding:6px 14px; border-radius:20px;">
                    擁有麥穗：🌾 <?= $user['wheat'] ?>
                </div>
            <?php endif; ?>
            
            <div class="avatar-wrap">
            <a href="profile.php">
                <img class="avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="User" style="cursor: pointer; width: 46px; height: 46px; border-radius: 50%; object-fit: cover;">
            </a>
                <div class="avatar-popover" onclick="event.stopPropagation()">
                    <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">您好，<?= htmlspecialchars($user['name']) ?></div>
                    <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                    <?php if ($user_id): ?>
                        <a href="logout.php" class="popover-btn secondary">登出帳號</a>
                    <?php else: ?>
                        <a href="login.php" class="popover-btn primary">立即登入</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="shop-tabs">
        <a href="shop.php?cat=all" class="shop-tab-btn <?= $category_filter === 'all' ? 'active' : '' ?>">✨ 全部圖書</a>
        <a href="shop.php?cat=featured" class="shop-tab-btn <?= $category_filter === 'featured' ? 'active' : '' ?>">⭐ 精選推薦</a>
        <a href="shop.php?cat=short" class="shop-tab-btn <?= $category_filter === 'short' ? 'active' : '' ?>">☕ 獨家短篇</a>
        <a href="shop.php?cat=bottle" class="shop-tab-btn <?= $category_filter === 'bottle' ? 'active' : '' ?>">🍾 漂流特刊</a>
    </div>

    <div class="shop-grid">
        <?php if (empty($shop_books)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--muted);">
                <p style="font-size: 48px;">📚</p>
                <p>精品書城目前還沒有官方上架的精品書喔，敬請期待！</p>
            </div>
        <?php else: ?>
            <?php foreach ($shop_books as $book): 
                $book_intro = "「這是一本關於命運與抉擇的故事...」\n\n【精選引言】\n本篇歸類於 " . htmlspecialchars($book['tag']) . " 特刊，收錄了官方最動人的字句。在未知的冒險中，唯有翻開下一頁，才能找到麥穗市集的真實答案。";
            ?>
                <div class="shop-card" onclick="openQuote('<?= htmlspecialchars($book['title']) ?>', '<?= htmlspecialchars($book['author']) ?>', `<?= htmlspecialchars($book_intro) ?>`)">
                    <div>
                        <div class="book-cover-container">
                            <img class="book-cover-img" src="<?= htmlspecialchars($book['cover'] ?: 'images/default_cover.png') ?>" alt="封面" onerror="this.src='https://placehold.co/150x200?text=Book'">
                        </div>
                        <div class="shop-info">
                            <h3><?= htmlspecialchars($book['title']) ?></h3>
                            <div class="shop-meta">
                                <span class="shop-badge">🏷️ <?= htmlspecialchars($book['tag']) ?></span>
                                <span>✍️ 作者：<?= htmlspecialchars($book['author']) ?> 著</span>
                                <div class="price-badge">價格：🌾 <?= htmlspecialchars($book['price']) ?> 麥穗</div>
                            </div>
                        </div>
                    </div>
                    
                    <form action="cart_action.php" method="POST" style="margin:0;" onclick="event.stopPropagation();">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="shop_book_id" value="<?= $book['id'] ?>">
                        <button type="submit" class="shop-btn-buy">🛒 放入購物車</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div class="book-modal-overlay" id="quoteModal" onclick="closeQuote()">
    <div class="book-modal-content" onclick="event.stopPropagation()">
        <p style="font-size:32px; margin-bottom:10px;">✨ 精彩引言</p>
        <div class="modal-book-title" id="modalTitle">書名</div>
        <div class="modal-book-author" id="modalAuthor">作者</div>
        <div class="modal-quote-box" id="modalQuote">引言內容載入中...</div>
        <button class="modal-close-btn" onclick="closeQuote()">關閉瀏覽 ✖</button>
    </div>
</div>

<script>
function openQuote(title, author, quote) {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalAuthor').innerText = "✍️ " + author + " 著";
    document.getElementById('modalQuote').innerText = quote;
    
    // 💡 修正 3：完美切換成 flex 排版以套用遮罩層的置中設定
    document.getElementById('quoteModal').style.display = 'flex';
}

function closeQuote() {
    document.getElementById('quoteModal').style.display = 'none';
}
</script>

</body>
</html>