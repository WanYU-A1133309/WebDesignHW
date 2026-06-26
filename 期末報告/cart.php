<?php
// cart.php - 我的購物車 (完美同步全站側邊欄與大頭貼懸浮視窗版)
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo "<script>alert('請先登入會員！'); location.href='login.php';</script>";
    exit;
}

try {
    // 1. 撈取使用者基本資料（供左側大頭貼、權限與頂部彈出視窗使用）
    $stmt_user = $pdo->prepare("SELECT id, username, nickname, role, wheat, avatar FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
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
        'avatar'  => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47', // 同步你的專屬頭像
    ];

    // 安全兼容版：撈取即時的使用者擁有麥穗數 (解決 fetch_column 噴錯問題)
    $user_wheat = intval($db_user['wheat']);

    // 2. 撈取購物車中的電子書
    $stmt = $pdo->prepare("
        SELECT c.id AS cart_id, b.id AS book_id, b.title, b.author, b.price AS wheat_price, b.tag 
        FROM cart c 
        JOIN books b ON c.shop_book_id = b.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$total_wheat_needed = 0;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>我的購物車 ‧ 咬一口故事</title>
    <link rel="stylesheet" href="css/create.css"> 
    <style>
        /* 🎨 為購物車主內文區塊量身打造的精緻與一致性樣式 */
        .cart-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .cart-card {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .cart-info h3 {
            margin: 0 0 6px 0;
            color: var(--ink, #2d3748);
            font-size: 18px;
        }
        .cart-meta {
            font-size: 13px;
            color: var(--muted, #718096);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .cart-badge-official {
            background: #FFF9E6;
            color: #B37D14;
            border: 1px solid #FFEAA8;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .wheat-price-tag {
            color: #D9A441; /* 麥穗黃 */
            font-weight: bold;
            font-size: 15px;
        }
        .cart-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* 結帳底條與總計區塊 */
        .checkout-summary-bar {
            margin-top: 25px;
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-text {
            font-size: 16px;
            color: var(--ink, #2d3748);
            font-weight: 500;
        }
        .total-wheat-highlight {
            font-size: 22px;
            font-weight: 800;
            color: #D9A441;
        }
        
        .btn-checkout-action {
            background: var(--primary, #5A483C);
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-size: 15px;
            transition: opacity 0.2s;
        }
        .btn-checkout-action:hover {
            opacity: 0.9;
        }
        .btn-delete-item {
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
        .btn-delete-item:hover {
            background: #fff0f3;
            color: #b83253;
        }
        .empty-cart-state {
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
        <a href="bookshelf.php" data-label="我的書架"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
        <a href="cart.php" data-label="購物車" style="background: var(--bg-2); color: var(--primary); font-weight: 800;"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
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
    <a href="bookshelf.php"><span class="ic ic-books"></span>書架</a>
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
        <h2 class="page-title">🛒 我的購物車</h2>
        
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="font-size:14px; font-weight:bold; background:#FFF9E6; color:#B37D14; border:1px solid #FFEAA8; padding:6px 14px; border-radius:20px;">
                擁有麥穗：🌾 <?= $user_wheat ?>
            </div>
            
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
    </div>

    <div class="cart-list">
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart-state">
                <p style="font-size: 48px; margin-bottom: 10px;">🛒</p>
                <p>購物車空空如也，快去精品書城挑幾本好書吧！</p>
                <br>
                <a href="shop.php" class="btn-checkout-action" style="display:inline-block; background:#D9A441;">去精品書城逛逛 🏛️</a>
            </div>
        <?php else: ?>
            <?php foreach ($cart_items as $item): 
                $total_wheat_needed += $item['wheat_price'];
            ?>
                <div class="cart-card">
                    <div class="cart-info">
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <div class="cart-meta">
                            <span class="cart-badge-official">🏛️ 官方精品電子書</span>
                            <span>✍️ 作者：<?= htmlspecialchars($item['author']) ?> 著</span>
                            <span>🏷️ 標籤：<?= htmlspecialchars($item['tag']) ?></span>
                            <span class="wheat-price-tag">單價：🌾 <?= $item['wheat_price'] ?> 麥穗</span>
                        </div>
                    </div>
                    <div class="cart-actions">
                        <form action="cart_action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <button type="submit" class="btn-delete-item">移除商品 🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="checkout-summary-bar">
                <div class="total-text">
                    總計預計消耗：<span class="total-wheat-highlight">🌾 <?= $total_wheat_needed ?></span> 粒麥穗
                </div>
                <form action="checkout_process.php" method="POST" style="margin:0;">
                    <button type="submit" class="btn-checkout-action" onclick="return confirm('確定要消耗 <?= $total_wheat_needed ?> 麥穗兌換這些電子書嗎？')">確認扣除麥穗，結帳下載 🚀</button>
                </form>
            </div>
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