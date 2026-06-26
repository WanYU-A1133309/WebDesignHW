<?php
// 咬一口故事 - 我的書架 bookshelf.php (完美同步草稿箱側邊欄與視覺風格版 - 整合官方書城版)
session_start();
require_once 'db.php'; 

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?back_url=bookshelf.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$alert_message = '';

// 接收當前分頁分類狀態 (預設看原本的收藏故事 fav)
$current_tab = $_GET['tab'] ?? 'fav';

// 🔥 處理「取消收藏」的 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $story_id = intval($_POST['story_id'] ?? 0);
    try {
        $del_stmt = $pdo->prepare("DELETE FROM bookshelves WHERE user_id = ? AND story_id = ?");
        $del_stmt->execute([$current_user_id, $story_id]);
        
        header("Location: bookshelf.php?tab=fav&alert=remove_success");
        exit;
    } catch (PDOException $e) {
        $alert_message = "❌ 移除失敗：" . $e->getMessage();
    }
}

// 接收提示
$alert_status = $_GET['alert'] ?? null;
if ($alert_status === 'remove_success') {
    $alert_message = "🗑️ 故事已成功從書架中移出。";
}

try {
    // 1. 撈取使用者基本資料（供大頭貼與彈出視窗使用）
    $stmt_user = $pdo->prepare("SELECT id, username, nickname, avatar, role FROM users WHERE id = ?");
    $stmt_user->execute([$current_user_id]);
    $db_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$db_user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $user = [
        'id'     => $db_user['id'],
        'role'   => $db_user['role'],
        'name'   => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
        'avatar'  => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47', 
    ];

    // 2. 撈取使用者收藏的書架清單（完全維持你原本的邏輯與欄位）
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS story_id,
            s.title AS story_title,
            s.status AS story_status,
            s.type AS novel_type
        FROM `bookshelves` b
        JOIN `stories` s ON b.story_id = s.id
        WHERE b.user_id = ?
        ORDER BY b.id DESC
    ");
    $stmt->execute([$current_user_id]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✨ 3. 新增：撈取此用戶已在精品書城購買的官方電子書
    $shop_stmt = $pdo->prepare("
        SELECT 
            s.shop_book_id,
            s.purchased_at,
            b.title AS book_title,
            b.author AS book_author,
            b.tag AS book_tag
        FROM user_shop_shelf s
        JOIN books b ON s.shop_book_id = b.id
        WHERE s.user_id = ?
        ORDER BY s.purchased_at DESC
    ");
    $shop_stmt->execute([$current_user_id]);
    $purchased_books = $shop_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("書架資料撈取失敗：" . $e->getMessage());
}
?>

<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>我的書架 ‧ 咬一口故事</title>
    <link rel="stylesheet" href="css/create.css"> 
    <style>
        /* 完美承襲草稿箱卡片架構的書架卡片清單排版 */
        .book-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .book-card {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .book-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .book-info h3 {
            margin: 0 0 6px 0;
            color: var(--ink, #2d3748);
            font-size: 18px;
        }
        .book-meta {
            font-size: 13px;
            color: var(--muted, #718096);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .book-badge {
            background: #edf2f7;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .book-badge.status-ongoing {
            background: #e6fffa;
            color: #234e52;
        }
        .book-badge.status-completed {
            background: #ebf8ff;
            color: #2b6cb0;
        }
        .book-badge.status-official {
            background: #FFF9E6;
            color: #B37D14;
            border: 1px solid #FFEAA8;
        }
        .book-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-read-book {
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
        .btn-read-book:hover {
            opacity: 0.9;
        }
        .btn-remove-book {
            background: #fff5f7;
            color: #D6557A;
            border: 1px solid #fed7e2;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-remove-book:hover {
            background: #fff0f3;
            color: #b83253;
        }
        .empty-shelf {
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

        /* ✨ 新增：上方分類切換頁籤 Tabs CSS */
        .shelf-tabs {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            border-bottom: 2px solid var(--line, #e2e8f0);
            padding-bottom: 6px;
        }
        .shelf-tab-btn {
            font-size: 15px;
            font-weight: bold;
            color: var(--muted, #718096);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        .shelf-tab-btn:hover {
            color: var(--ink, #2d3748);
            background: #f7fafc;
        }
        .shelf-tab-btn.active {
            color: var(--primary, #5A483C);
            border-bottom: 3px solid var(--primary, #5A483C);
            background: rgba(90, 72, 60, 0.05);
        }

        /* ==================== ✨ 同步設計：頭像懸浮視窗專屬 CSS ==================== */
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
        .avatar-wrap .avatar-popover a.popover-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-2)) !important; 
            color: #fff !important; 
            box-shadow: 0 4px 12px rgba(227,106,75,0.2) !important;
        }
        .avatar-wrap .avatar-popover a.popover-btn.primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
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
        <a href="bookshelf.php" data-label="我的書架" style="background: var(--bg-2); color: var(--primary); font-weight: 800;"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
        <a href="cart.php" data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
    </nav>
    
    <div class="publish" id="publish"> 
        <button class="publish-btn" data-label="發布新作" onclick="event.stopPropagation(); document.getElementById('publish').classList.toggle('open')">
            <span class="ic ic-pen"></span><span class="label">發布新作</span>
        </button>
        <div class="publish-menu">
            <a href="create.php" style="margin-bottom: 4px;">➕ 開始寫故事 / 投稿</a>
            <div class="divider" style="margin: 4px 0;"></div>
            <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
            <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
            
        </div>
    </div>
    <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
</aside>

<nav class="mobile-nav">
    <a href="index.php"><span class="ic ic-home"></span>首頁</a>
    <a href="explore.php"><span class="ic ic-search"></span>探索</a>
    <button class="mob-pub-trigger" onclick="toggleMobileSheet(true)"><div class="center-pub-btn">＋</div></button>
    <a href="bookshelf.php" style="color: var(--primary); font-weight: bold;"><span class="ic ic-books"></span>書架</a>
    <a href="javascript:void(0)" onclick="openUserModal()"><span class="ic ic-user"></span>我的</a>
</nav>

<div class="mobile-publish-sheet" id="mobileSheet">
    <h4>選擇創作動作</h4>
    <div class="sheet-grid">
        <a href="create.php" class="sheet-item"><span>✍️</span><strong>開始寫故事</strong></a>
        <a href="works.php" class="sheet-item"><span>📖</span>我的作品</a>
        <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
        
    </div>
    <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<main class="main">
    <div class="page-header">
        <h2 class="page-title">📚 我的專屬書櫃</h2>
        
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

    <div class="shelf-tabs">
        <a href="bookshelf.php?tab=fav" class="shelf-tab-btn <?= $current_tab === 'fav' ? 'active' : '' ?>">❤️ 收藏故事 (<?= count($books) ?>)</a>
        <a href="bookshelf.php?tab=shop" class="shelf-tab-btn <?= $current_tab === 'shop' ? 'active' : '' ?>">🏛️ 購買書籍 (<?= count($purchased_books) ?>)</a>
    </div>

    <?php if (!empty($alert_message)): ?>
        <div class="alert-banner"><?= htmlspecialchars($alert_message) ?></div>
    <?php endif; ?>

    <div class="book-list">
        
        <?php if ($current_tab === 'fav'): ?>
            <?php if (empty($books)): ?>
                <div class="empty-shelf">
                    <p style="font-size: 48px; margin-bottom: 10px;">🍃</p>
                    <p>書架空空如也，快去探索好故事放進來吧！</p>
                    <br>
                    <a href="index.php" class="btn-read-book" style="display:inline-block;">去首頁逛逛 🚀</a>
                </div>
            <?php else: ?>
                <?php foreach ($books as $b): ?>
                    <div class="book-card">
                        <div class="book-info">
                            <h3>
                                [<?= $b['novel_type'] === 'single' ? '短篇' : '連載' ?>] 
                                <?= htmlspecialchars($b['story_title']) ?>
                            </h3>
                            <div class="book-meta">
                                <?php if ($b['story_status'] === 'completed'): ?>
                                    <span class="book-badge status-completed">🎉 已完結</span>
                                <?php else: ?>
                                    <span class="book-badge status-ongoing">✨ 連載中</span>
                                <?php endif; ?>
                                <span>📖 故事類別：<?= $b['novel_type'] === 'single' ? '短篇小說' : '長篇連載' ?></span>
                            </div>
                        </div>
                        <div class="book-actions">
                            <a href="story.php?id=<?= $b['story_id'] ?>" class="btn-read-book">開始閱讀 📖</a>
                            
                            <form method="POST" style="margin:0;" onsubmit="return confirm('確定要將這本故事移出書架嗎？');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="story_id" value="<?= $b['story_id'] ?>">
                                <button type="submit" class="btn-remove-book">取消收藏 🗑️</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($current_tab === 'shop'): ?>
            <?php if (empty($purchased_books)): ?>
                <div class="empty-shelf">
                    <p style="font-size: 48px; margin-bottom: 10px;">🏛️</p>
                    <p>您目前尚未在精品書城購買任何官方電子書喔！</p>
                    <br>
                    <a href="shop.php" class="btn-read-book" style="display:inline-block; background:#D9A441;">去精品書城逛逛 🛒</a>
                </div>
            <?php else: ?>
                <?php foreach ($purchased_books as $sb): ?>
                    <div class="book-card">
                        <div class="book-info">
                            <h3>
                                <?= htmlspecialchars($sb['book_title']) ?>
                            </h3>
                            <div class="book-meta">
                                <span class="book-badge status-official">📜 官方精品正版</span>
                                <span>✍️ 作者：<?= htmlspecialchars($sb['book_author']) ?> 著</span>
                                <span>🏷️ 標籤：<?= htmlspecialchars($sb['book_tag']) ?></span>
                            </div>
                        </div>
                        <div class="book-actions">
                            <a href="shop_read.php?book_id=<?= $sb['shop_book_id'] ?>&ch=1" class="btn-read-book" style="background:#2B1A0F;">線上開卷 📖</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
