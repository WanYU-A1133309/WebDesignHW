<?php
// admin.php - 咬一口故事 後台管理中心 (升級版：納入精品書籍與章節管理)
require_once 'admin_db.php';

$page = $_GET['page'] ?? 'dashboard';
$alert_success = '';
$alert_error = '';

// ── 🛠️ 後台動作處理 (POST 請求) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. 手動調帳（修改使用者的甜甜圈與小麥）
    if (isset($_POST['action']) && $_POST['action'] === 'adjust_wallet') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $donuts = intval($_POST['donuts'] ?? 0);
        $wheat = intval($_POST['wheat'] ?? 0);
        
        try {
            $up_stmt = $pdo->prepare("UPDATE users SET donuts = ?, wheat = ? WHERE id = ?");
            $up_stmt->execute([$donuts, $wheat, $target_user_id]);
            $alert_success = "🎉 成功更新用戶 ID: {$target_user_id} 的資產設定！";
        } catch (PDOException $e) { $alert_error = "❌ 變更失敗：" . $e->getMessage(); }
    }

    // B：變更使用者角色權限 (管理員 / 一般用戶)
    if (isset($_POST['action']) && $_POST['action'] === 'change_role') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $new_role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        
        if ($target_user_id === intval($_SESSION['user_id'])) {
            $alert_error = "您不能在後台拔除您自己的管理員權限！";
        } else {
            try {
                $up_stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $up_stmt->execute([$new_role, $target_user_id]);
                $alert_success = "成功將用戶 ID: {$target_user_id} 的權限變更為 [{$new_role}]！";
            } catch (PDOException $e) { $alert_error = "權限更新失敗：" . $e->getMessage(); }
        }
    }

    // C：切換用戶禁言狀態 (禁言 / 解除)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_mute') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $current_mute = intval($_POST['current_mute'] ?? 0);
        $new_mute = $current_mute === 1 ? 0 : 1;
        
        try {
            $up_stmt = $pdo->prepare("UPDATE users SET is_muted = ? WHERE id = ?");
            $up_stmt->execute([$new_mute, $target_user_id]);
            $status_text = $new_mute === 1 ? "⚠️ 帳號已被禁言！" : "禁言已解除。";
            $alert_success = "👤 用戶 ID: {$target_user_id} " . $status_text;
        } catch (PDOException $e) { $alert_error = "禁言操作失敗：" . $e->getMessage(); }
    }

    // ✨ 新增：從資料庫永久刪除用戶
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        
        if ($target_user_id === intval($_SESSION['user_id'])) {
            $alert_error = "❌ 您不能在後台刪除您自己目前的管理員帳號！";
        } else {
            try {
                $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->execute([$target_user_id]);
                $alert_success = "🗑️ 用戶 ID: {$target_user_id} 及其關聯資料已被系統完全註銷永久刪除！";
            } catch (PDOException $e) { $alert_error = "刪除用戶失敗：" . $e->getMessage(); }
        }
    }

    // D：刪除違規留言
    if (isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        try {
            $del_stmt = $pdo->prepare("DELETE FROM chapter_annotations WHERE id = ?");
            $del_stmt->execute([$comment_id]);
            $alert_success = "違規留言 ID: {$comment_id} 已從資料庫中永久移除！";
        } catch (PDOException $e) { $alert_error = "刪除留言失敗：" . $e->getMessage(); }
    }

    // E：強制下架文章故事
    if (isset($_POST['action']) && $_POST['action'] === 'take_down_story') {
        $story_id = intval($_POST['story_id'] ?? 0);
        try {
            $up_stmt = $pdo->prepare("UPDATE stories SET status = 'draft' WHERE id = ?");
            $up_stmt->execute([$story_id]);
            $alert_success = "🛑 故事 ID: {$story_id} 已成功被行政強制下架（狀態已變更為草稿隱藏）！";
        } catch (PDOException $e) { $alert_error = "故事下架操作失敗：" . $e->getMessage(); }
    }
    
    // 重新上架文章故事
    if (isset($_POST['action']) && $_POST['action'] === 'put_up_story') {
        $story_id = intval($_POST['story_id'] ?? 0);
        try {
            $up_stmt = $pdo->prepare("UPDATE stories SET status = 'published' WHERE id = ?");
            $up_stmt->execute([$story_id]);
            $alert_success = "🚀 故事 ID: {$story_id} 已成功重新上架發布！";
        } catch (PDOException $e) { $alert_error = "故事上架操作失敗：" . $e->getMessage(); }
    }

    // F：從資料庫完全刪除整部故事作品
    if (isset($_POST['action']) && $_POST['action'] === 'delete_story') {
        $story_id = intval($_POST['story_id'] ?? 0);
        try {
            $del_stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
            $del_stmt->execute([$story_id]);
            $alert_success = "違規故事作品 ID: {$story_id} 及其所屬章節內容已被管理員永久移除！";
        } catch (PDOException $e) { $alert_error = "刪除故事失敗：" . $e->getMessage(); }
    }

    // H：完全刪除違規漂流瓶
    if (isset($_POST['action']) && $_POST['action'] === 'delete_bottle') {
        $bottle_id = intval($_POST['bottle_id'] ?? 0);
        try {
            $del_stmt = $pdo->prepare("DELETE FROM drifting_bottles WHERE id = ?");
            $del_stmt->execute([$bottle_id]);
            $alert_success = "違規漂流瓶 ID: {$bottle_id} 已被管理員永久移除！";
        } catch (PDOException $e) { $alert_error = "移除漂流瓶失敗：" . $e->getMessage(); }
    }

    // ── ✨ 新增：精品書城與外部故事相關 POST 控制 ──────────────────
    
    // 1. 新增官方精品書籍
    if (isset($_POST['action']) && $_POST['action'] === 'add_shop_book') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $cover = trim($_POST['cover'] ?? 'img/default_cover.png');
        $tag = trim($_POST['tag'] ?? '精品');
        $price = max(0, intval($_POST['price'] ?? 100));
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $author === '') {
            $alert_error = "❌ 書名與作者為必填項目！";
        } else {
            try {
                $ins_stmt = $pdo->prepare("INSERT INTO books (title, author, cover, tag, price, description, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $ins_stmt->execute([$title, $author, $cover, $tag, $price, $description]);
                $alert_success = "🎉 成功上架全新精品書：《{$title}》！";
            } catch (PDOException $e) { $alert_error = "書籍上架失敗：" . $e->getMessage(); }
        }
    }

    // 2. 切換精品書架狀態 (上架/下架)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_shop_book') {
        $book_id = intval($_POST['book_id'] ?? 0);
        $current_active = intval($_POST['current_active'] ?? 0);
        $new_active = $current_active === 1 ? 0 : 1;
        try {
            $up_stmt = $pdo->prepare("UPDATE books SET is_active = ? WHERE id = ?");
            $up_stmt->execute([$new_active, $book_id]);
            $alert_success = "📚 精品書 ID: {$book_id} 狀態已更新！";
        } catch (PDOException $e) { $alert_error = "修改狀態失敗：" . $e->getMessage(); }
    }

    // 3. 刪除精品書
    if (isset($_POST['action']) && $_POST['action'] === 'delete_shop_book') {
        $book_id = intval($_POST['book_id'] ?? 0);
        try {
            $del_stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
            $del_stmt->execute([$book_id]);
            $alert_success = "🗑️ 精品書籍 ID: {$book_id} 及其章節內容已全面移除！";
        } catch (PDOException $e) { $alert_error = "刪除書籍失敗：" . $e->getMessage(); }
    }

    // 4. 新增精品書內文章節
    if (isset($_POST['action']) && $_POST['action'] === 'add_shop_chapter') {
        $shop_book_id = intval($_POST['shop_book_id'] ?? 0);
        $chapter_number = intval($_POST['chapter_number'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '' || $content === '') {
            $alert_error = "❌ 章節標題與內文不可留空！";
        } else {
            try {
                $ins_stmt = $pdo->prepare("INSERT INTO shop_book_chapters (shop_book_id, chapter_number, title, content) VALUES (?, ?, ?, ?)");
                $ins_stmt->execute([$shop_book_id, $chapter_number, $title, $content]);
                $alert_success = "📖 成功建立第 {$chapter_number} 章：{$title}！";
            } catch (PDOException $e) { $alert_error = "章節建立失敗：" . $e->getMessage(); }
        }
    }

    // 5. 刪除精品書內文章節
    if (isset($_POST['action']) && $_POST['action'] === 'delete_shop_chapter') {
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        try {
            $del_stmt = $pdo->prepare("DELETE FROM shop_book_chapters WHERE id = ?");
            $del_stmt->execute([$chapter_id]);
            $alert_success = "🗑️ 該精選章節內文已成功移除。";
        } catch (PDOException $e) { $alert_error = "刪除章節失敗：" . $e->getMessage(); }
    }
}

// ── 📊 根據分頁讀取對應的資料庫數據 ──────────────────────────
$stats = [];
$users_list = [];
$stories_list = [];
$comments_list = [];
$bottles_list = [];
$shop_books_list = [];

if ($page === 'dashboard') {
    $stats['total_users']   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_stories'] = $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
    $stats['total_donuts']  = $pdo->query("SELECT SUM(donuts) FROM users")->fetchColumn() ?? 0;
    $stats['total_mutes']   = $pdo->query("SELECT COUNT(*) FROM users WHERE is_muted = 1")->fetchColumn();
    $stats['total_comments'] = $pdo->query("SELECT COUNT(*) FROM chapter_annotations")->fetchColumn();
    $stats['total_bottles'] = $pdo->query("SELECT COUNT(*) FROM drifting_bottles")->fetchColumn(); 
    
    // ✨ 新增精品統計
    $stats['total_shop_books'] = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $stats['total_shop_chapters'] = $pdo->query("SELECT COUNT(*) FROM shop_book_chapters")->fetchColumn();
} elseif ($page === 'users') {
    $users_list = $pdo->query("SELECT id, username, nickname, email, role, is_muted, donuts, wheat FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'stories') {
    $stories_list = $pdo->query("SELECT s.*, u.username, u.nickname FROM stories s JOIN users u ON s.user_id = u.id ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'comments') {
    $comments_list = $pdo->query("
        SELECT a.id, a.content, a.paragraph_index, a.selected_text, a.created_at,
               u.username, u.nickname, u.is_muted,
               c.title AS chapter_title, s.title AS story_title
        FROM chapter_annotations a
        JOIN users u ON a.user_id = u.id
        JOIN chapters c ON a.chapter_id = c.id
        JOIN stories s ON c.story_id = s.id
        ORDER BY a.id DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'bottles') {
    $bottles_list = $pdo->query("
        SELECT db.id, db.nickname AS author, db.content, db.created_at, s.title AS story_title 
        FROM drifting_bottles db
        LEFT JOIN stories s ON db.story_id = s.id 
        ORDER BY db.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'shop_books') {
    // ✨ 撈取精品書清單與對應的章節數
    $shop_books_list = $pdo->query("
        SELECT b.*, COUNT(c.id) as chapter_count 
        FROM books b 
        LEFT JOIN shop_book_chapters c ON b.id = c.shop_book_id 
        GROUP BY b.id 
        ORDER BY b.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ 咬一口故事 - 系統管理中心</title>
    <style>
        :root {
            --admin-primary: #5A483C;
            --admin-bg: #f8f9fa;
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 70px;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
            --border-color: #e2e8f0;
            --danger: #D6557A;
            --success: #2b6cb0;
        }
        * { box-sizing: border-box; font-family: system-ui, sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--admin-bg); display: flex; height: 100vh; overflow: hidden; }

        /* 左側邊欄 */
        .admin-sidebar { 
            width: var(--sidebar-width); 
            background-color: #2D2520; 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 10;
        }
        .sidebar-brand { padding: 24px; font-size: 18px; font-weight: bold; border-bottom: 1px solid #463830; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-menu { list-style: none; padding: 15px 0; overflow-x: hidden; }
        .sidebar-menu a { display: flex; align-items: center; padding: 14px 24px; color: #b3a7a0; text-decoration: none; font-size: 14px; transition: all 0.2s; white-space: nowrap; }
        .sidebar-menu a span.menu-text { margin-left: 10px; transition: opacity 0.2s; }
        .sidebar-menu a:hover, .sidebar-menu li.active a { color: #fff; background-color: var(--admin-primary); }

        /* 右側主內容區 */
        .admin-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .admin-header { background-color: #fff; height: 70px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 30px; }
        
        /* 折疊按鈕與頭部元件 */
        .toggle-sidebar-btn { background: #f1f3f5; border: none; width: 38px; height: 38px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 15px; color: var(--admin-primary); transition: background 0.2s; }
        .toggle-sidebar-btn:hover { background: #e2e8f0; }
        .admin-container { padding: 30px; flex: 1; overflow-y: auto; }

        /* 當側邊欄收束後的 CSS 覆蓋狀態 */
        body.sidebar-collapsed .admin-sidebar { width: var(--sidebar-collapsed-width); }
        body.sidebar-collapsed .sidebar-brand { font-size: 14px; padding: 24px 5px; }
        body.sidebar-collapsed .sidebar-brand span { display: none; }
        body.sidebar-collapsed .sidebar-brand::after { content: "⚙️"; }
        body.sidebar-collapsed .sidebar-menu a { padding: 14px 0; justify-content: center; }
        body.sidebar-collapsed .sidebar-menu a span.menu-text { display: none; }
        body.sidebar-collapsed .sidebar-footer { padding: 15px 5px !important; }
        body.sidebar-collapsed .sidebar-footer div, body.sidebar-collapsed .sidebar-footer a span { display: none; }

        /* 萬用提示框 */
        .alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background-color: #e6fffa; color: #234e52; border: 1px solid #319795; }
        .alert-error { background-color: #fff5f5; color: #9b2c2c; border: 1px solid #e53e3e; }

        /* 數據卡片 */
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow); border: 1px solid var(--border-color); }
        .stat-card h3 { font-size: 13px; color: #718096; margin-bottom: 10px; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #2d3748; }

        /* 資料表格 */
        .table-responsive { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); border: 1px solid var(--border-color); overflow: hidden; margin-top: 15px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .admin-table th { background-color: #f7fafc; color: #4a5568; padding: 14px 16px; font-weight: 600; border-bottom: 2px solid var(--border-color); }
        .admin-table td { padding: 14px 16px; color: #2d3748; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .admin-table tr:hover { background-color: #f8fafc; }

        /* 按鈕與小組件 */
        .btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; font-weight: bold; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: var(--admin-primary); color: #fff; }
        .btn-danger { background-color: #fff5f5; color: var(--danger); border: 1px solid #fed7e2; }
        .btn-danger:hover { background-color: #fff0f3; }
        .btn-mute { background-color: #feebc8; color: #c05621; border: 1px solid #fbd38d; }
        .btn-unmute { background-color: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; }
        .btn-warn { color: #b7791f; border: 1px solid #fbd38d; background-color: #fefcbf; }
        .btn-warn:hover { background-color: #fff9db; }
        
        .input-inline { padding: 5px 8px; width: 60px; border: 1px solid #cbd5e0; border-radius: 4px; margin-right: 4px; }
        .select-inline { padding: 5px; border-radius: 4px; border: 1px solid #cbd5e0; margin-right: 4px; }

        /* ✨ 表單排版元件 */
        .admin-form-group { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 24px; box-shadow: var(--card-shadow); }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e0; font-size: 14px; margin-top: 5px; }
        .chapter-box { background: #f7fafc; padding: 15px; border-radius: 8px; margin-top: 10px; border: 1px solid #e2e8f0; display: none; }
    </style>
</head>
<body>

    <aside class="admin-sidebar" id="sidebar">
        <div>
            <div class="sidebar-brand"><span>⚙️ 咬一口故事 後台</span></div>
            <ul class="sidebar-menu">
                <li class="<?= $page === 'dashboard' ? 'active' : '' ?>"><a href="admin.php?page=dashboard">📊<span class="menu-text">營運數據總覽</span></a></li>
                <li class="<?= $page === 'users' ? 'active' : '' ?>"><a href="admin.php?page=users">👥<span class="menu-text">全站用戶管理</span></a></li>
                <li class="<?= $page === 'stories' ? 'active' : '' ?>"><a href="admin.php?page=stories">📚<span class="menu-text">故事作品審查</span></a></li>
                <li class="<?= $page === 'shop_books' ? 'active' : '' ?>"><a href="admin.php?page=shop_books">💎<span class="menu-text">精品書城上架</span></a></li>
                <li class="<?= $page === 'comments' ? 'active' : '' ?>"><a href="admin.php?page=comments">💬<span class="menu-text">讀者留言審查</span></a></li>
                <li class="<?= $page === 'bottles' ? 'active' : '' ?>"><a href="admin.php?page=bottles">🍾<span class="menu-text">漂流瓶內容審查</span></a></li>
            </ul>
        </div>
        <div class="sidebar-footer" style="padding: 20px; border-top: 1px solid #463830; font-size: 13px; color: #b3a7a0; background-color: #241d1a; text-align: center;">
            <div style="margin-bottom: 12px; white-space: nowrap; overflow: hidden;">官：<strong><?= htmlspecialchars($admin_display_name ?? '管理員') ?></strong></div>
            <a href="index.php" style="display: block; background-color: var(--admin-primary); color: #fff; text-decoration: none; padding: 8px; border-radius: 6px; margin-bottom: 8px; font-weight: bold; font-size: 12px;">🚀<span>返回首頁</span></a>
            <a href="logout.php" style="display: block; background-color: #4a3728; color: #d6557a; text-decoration: none; padding: 8px; border-radius: 6px; font-weight: bold; font-size: 11px;" onclick="return confirm('確定要安全登出嗎？')">🔒<span>安全登出</span></a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div style="display: flex; align-items: center;">
                <button class="toggle-sidebar-btn" id="toggleSidebar" title="展開/折疊側邊欄">☰</button>
                <h2 style="color: var(--admin-primary); font-size: 19px;">
                    <?php 
                        if($page === 'dashboard') echo "📊 營運數據與狀態總覽";
                        if($page === 'users') echo "👥 全站用戶身分與安全管理";
                        if($page === 'stories') echo "📚 故事作品審查管理";
                        if($page === 'shop_books') echo "💎 精品書城書籍與內文章節架設";
                        if($page === 'comments') echo "💬 讀者段落留言審查管控";
                        if($page === 'bottles') echo "🍾 漂流瓶內容審查管控";
                    ?>
                </h2>
            </div>
            <span style="font-size: 13px; color: #718096;">系統核心運作正常</span>
        </header>

        <div class="admin-container">
            <?php if(!empty($alert_success)): ?><div class="alert alert-success"><?= $alert_success ?></div><?php endif; ?>
            <?php if(!empty($alert_error)): ?><div class="alert alert-error"><?= $alert_error ?></div><?php endif; ?>

            <?php if($page === 'dashboard'): ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">

                    <div class="stat-card" style="border-left: 4px solid #4a5568;">
                        <h3 style="color: #4a5568;">👥 註冊會員總數</h3>
                        <div class="value" style="color: #2d3748;"><?= $stats['total_users'] ?? 0 ?> 人</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #4c51bf;">
                        <h3 style="color: #4c51bf;">📚 全站連載故事</h3>
                        <div class="value" style="color: #4c51bf;"><?= $stats['total_stories'] ?? 0 ?> 部</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #b7791f;">
                        <h3 style="color: #b7791f;">💎 精品書城總數</h3>
                        <div class="value" style="color: #b7791f;"><?= $stats['total_shop_books'] ?? 0 ?> 本</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #319795;">
                        <h3 style="color: #319795;">📖 精品總章節數</h3>
                        <div class="value" style="color: #319795;"><?= $stats['total_shop_chapters'] ?? 0 ?> 章</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #2b6cb0;">
                        <h3 style="color: #2b6cb0;">🍾 流通漂流瓶數</h3>
                        <div class="value" style="color: #2b6cb0;"><?= $stats['total_bottles'] ?? 0 ?> 個</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #dd6b20;">
                        <h3 style="color: #dd6b20;">🍩 讀者甜甜圈總量</h3>
                        <div class="value" style="color: #2d3748;"><?= number_format($stats['total_donuts'] ?? 0) ?> 個</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #38a169;">
                        <h3 style="color: #38a169;">💬 全站讀者留言</h3>
                        <div class="value" style="color: #2d3748;"><?= $stats['total_comments'] ?? 0 ?> 則</div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #e53e3e;">
                        <h3 style="color: #e53e3e;">⚠️ 遭禁言讀者</h3>
                        <div class="value" style="color: #e53e3e;"><?= $stats['total_mutes'] ?? 0 ?> 人</div>
                    </div>

                </div>

            <?php elseif($page === 'users'): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>用戶名 / 暱稱</th>
                                <th>管理權限</th>
                                <th>狀態</th>
                                <th>資產 (🍩/🌾)</th>
                                <th>手動調帳</th>
                                <th style="width: 200px; text-align: center;">互動安全與帳號管理</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users_list as $u): ?>
                            <tr>
                                <td><strong><?= $u['id'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($u['username']) ?>
                                    <div style="font-size:11px; color:#a0aec0;">暱稱: <?= htmlspecialchars($u['nickname'] ?? '未設定') ?></div>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="role" class="select-inline" onchange="this.form.submit()">
                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>一般讀者</option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>管理員</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= ($u['is_muted'] == 1) ? '<span style="color:var(--danger); font-weight:bold;">已禁言</span>' : '<span style="color:green;">🟢 正常</span>' ?></td>
                                <td>🍩<?= $u['donuts'] ?> / 🌾<?= $u['wheat'] ?></td>
                                <td>
                                    <form method="POST" style="display:inline-flex; align-items:center;">
                                        <input type="hidden" name="action" value="adjust_wallet">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="number" name="donuts" class="input-inline" value="<?= $u['donuts'] ?>">
                                        <input type="number" name="wheat" class="input-inline" value="<?= $u['wheat'] ?>">
                                        <button type="submit" class="btn btn-primary">更新</button>
                                    </form>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display:inline-block; margin-right: 4px;">
                                        <input type="hidden" name="action" value="toggle_mute">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="current_mute" value="<?= $u['is_muted'] ?>">
                                        <button type="submit" class="btn <?= $u['is_muted'] == 1 ? 'btn-unmute' : 'btn-mute' ?>" onclick="return confirm('確定要變更此用戶的發言禁言狀態嗎？')"><?= $u['is_muted'] == 1 ? '解禁' : '禁言' ?></button>
                                    </form>
                                    
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ 警告！刪除用戶將一併抹除其所有關聯數據且無法回復。確定要永久註銷刪除此用戶嗎？');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="background-color: #fff5f5; color: #e53e3e; border: 1px solid #fed7e2;">❌ 刪除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page === 'stories'): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>作品名稱</th>
                                <th>作者</th>
                                <th>類型</th>
                                <th>發布狀態</th>
                                <th style="width: 140px;">建立時間</th>
                                <th style="width: 160px;">安全管理操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stories_list as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                                <td><?= htmlspecialchars(!empty($s['nickname']) ? $s['nickname'] : $s['username']) ?></td>
                                <td><?= $s['type'] === 'single' ? '🧩 短篇' : '📑 連載' ?></td>
                                <td>
                                    <?php if($s['status'] === 'published'): ?>
                                        <span style="color:green; font-weight:bold;">🟢 上架</span>
                                    <?php else: ?>
                                        <span style="color:#e53e3e; background:#fff5f5; padding:2px 6px; border-radius:4px; font-weight:500;">🛑 已下架</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:#718096;"><?= substr($s['created_at'], 0, 16) ?></td>
                                <td>
                                    <?php if($s['status'] === 'published' || $s['status'] === 'ongoing'): ?>
                                        <form method="POST" style="display:inline-block; margin-right: 4px;">
                                            <input type="hidden" name="action" value="take_down_story">
                                            <input type="hidden" name="story_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-warn" onclick="return confirm('確定要行政下架這部作品嗎？')">下架</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline-block; margin-right: 4px;">
                                            <input type="hidden" name="action" value="put_up_story">
                                            <input type="hidden" name="story_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-unmute" style="background-color: #e6fffa; color: #234e52; border: 1px solid #b2f5ea;" onclick="return confirm('確定要將這部作品重新上架嗎？')">上架</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('☠️ 警告：此動作將從資料庫永久刪除這部故事且無法復原，繼續嗎？');">
                                        <input type="hidden" name="action" value="delete_story">
                                        <input type="hidden" name="story_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-danger">刪除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page === 'shop_books'): ?>
                <div class="admin-form-group">
                    <h3 style="color: var(--admin-primary); margin-bottom: 12px;">💎 上架全新官方精品電子書</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_shop_book">
                        <div class="form-grid">
                            <div>
                                <label style="font-size: 13px; font-weight: bold; color: #4a5568;">📚 書籍名稱 *</label>
                                <input type="text" name="title" class="form-control" placeholder="例如：寂寞烘焙坊的午後微光" required>
                            </div>
                            <div>
                                <label style="font-size: 13px; font-weight: bold; color: #4a5568;">✍️ 作者名稱 *</label>
                                <input type="text" name="author" class="form-control" placeholder="例如：林海微瀾" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div>
                                <label style="font-size: 13px; font-weight: bold; color: #4a5568;">🖼️ 封面路徑/網址</label>
                                <input type="text" name="cover" class="form-control" value="img/default_cover.png">
                            </div>
                            <div>
                                <label style="font-size: 13px; font-weight: bold; color: #4a5568;">🏷️ 分類標籤</label>
                                <input type="text" name="tag" class="form-control" value="精品">
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="font-size: 13px; font-weight: bold; color: #4a5568;">🌾 販售價格 (解鎖所需小麥數量)</label>
                            <input type="number" name="price" class="form-control" value="100" min="0">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="font-size: 13px; font-weight: bold; color: #4a5568;">📝 書籍簡介描述</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="請輸入對這本精品電子書的簡介介紹..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">🚀 確立上架新書</button>
                    </form>
                </div>

                <h3 style="color: var(--admin-primary); margin-bottom: 10px;">📋 目前上架之精品書籍清單（共 <?= count($shop_books_list) ?> 本）</h3>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">BID</th>
                                <th>書名</th>
                                <th>作者</th>
                                <th>分類</th>
                                <th>定價</th>
                                <th>目前章節</th>
                                <th>書城狀態</th>
                                <th style="width: 240px; text-align: center;">操作選項</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($shop_books_list as $b): ?>
                            <tr>
                                <td><strong><?= $b['id'] ?></strong></td>
                                <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                                <td><?= htmlspecialchars($b['author']) ?></td>
                                <td><span style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:11px;"><?= htmlspecialchars($b['tag']) ?></span></td>
                                <td>🌾 <?= $b['price'] ?></td>
                                <td><span style="color:#2b6cb0; font-weight:bold;">📖 <?= $b['chapter_count'] ?> 個章節</span></td>
                                <td><?= ($b['is_active'] == 1) ? '<span style="color:green; font-weight:bold;">🟢 上架中</span>' : '<span style="color:var(--danger); font-weight:bold;">🛑 已下架</span>' ?></td>
                                <td style="text-align: center;">
                                    <button class="btn btn-warn" onclick="toggleChapterBox(<?= $b['id'] ?>)">⚙️ 章節維護</button>
                                    
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="toggle_shop_book">
                                        <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="current_active" value="<?= $b['is_active'] ?>">
                                        <button type="submit" class="btn btn-unmute"><?= $b['is_active'] == 1 ? '下架' : '上架' ?></button>
                                    </form>

                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ 確定要徹底刪除此精品書嗎？此動作將連同所有內文章節一併抹除！');">
                                        <input type="hidden" name="action" value="delete_shop_book">
                                        <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-danger">❌ 刪除</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="chapter-tr-<?= $b['id'] ?>" style="display:none; background: #fcfbf9;">
                                <td colspan="8" style="padding: 20px;">
                                    <div style="border-left: 4px solid var(--admin-primary); padding-left: 15px;">
                                        <h4 style="color: var(--admin-primary); margin-bottom: 12px;">📖 《<?= htmlspecialchars($b['title']) ?>》章節內容清單與管理</h4>
                                        
                                        <?php 
                                            $ch_stmt = $pdo->prepare("SELECT id, chapter_number, title FROM shop_book_chapters WHERE shop_book_id = ? ORDER BY chapter_number ASC");
                                            $ch_stmt->execute([$b['id']]);
                                            $b_chapters = $ch_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        
                                        <?php if(empty($b_chapters)): ?>
                                            <p style="color:#718096; font-size:13px; margin-bottom: 15px;">🍃 目前這本書還沒有任何故事內文，請於下方新增。</p>
                                        <?php else: ?>
                                            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                                                <?php foreach($b_chapters as $ch_item): ?>
                                                    <div style="background:#fff; border:1px solid #cbd5e0; padding:6px 12px; border-radius:20px; font-size:12px; display:inline-flex; align-items:center; gap:8px;">
                                                        <strong>第 <?= $ch_item['chapter_number'] ?> 章：<?= htmlspecialchars($ch_item['title']) ?></strong>
                                                        <form method="POST" style="margin:0;" onsubmit="return confirm('確定要永久刪除此章節內文嗎？');">
                                                            <input type="hidden" name="action" value="delete_shop_chapter">
                                                            <input type="hidden" name="chapter_id" value="<?= $ch_item['id'] ?>">
                                                            <button type="submit" style="background:none; border:none; color:var(--danger); cursor:pointer; font-weight:bold;">✕</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <form method="POST" style="background: #fff; padding: 15px; border-radius: 8px; border:1px solid #e2e8f0;">
                                            <input type="hidden" name="action" value="add_shop_chapter">
                                            <input type="hidden" name="shop_book_id" value="<?= $b['id'] ?>">
                                            <h5 style="margin-bottom:8px; color:#4a5568;">➕ 為此書撰寫/插入新章節故事</h5>
                                            <div style="display:flex; gap:10px; margin-bottom:10px;">
                                                <input type="number" name="chapter_number" class="form-control" style="width:100px; margin:0;" value="<?= count($b_chapters)+1 ?>" placeholder="章號" required>
                                                <input type="text" name="title" class="form-control" style="margin:0;" placeholder="輸入章節標題名稱（如：第一章：意外的相遇）" required>
                                            </div>
                                            <textarea name="content" class="form-control" rows="4" placeholder="請將整章精彩的故事內文貼於此處（支援換行顯示）..." required></textarea>
                                            <button type="submit" class="btn btn-primary" style="margin-top:10px;">💾 發布章節故事</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                    function toggleChapterBox(bookId) {
                        var tr = document.getElementById('chapter-tr-' + bookId);
                        if(tr.style.display === 'none') {
                            tr.style.display = 'table-row';
                        } else {
                            tr.style.display = 'none';
                        }
                    }
                </script>

            <?php elseif($page === 'comments'): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th style="width: 130px;">發表讀者</th>
                                <th>所屬故事 / 章節</th>
                                <th style="width: 400px;">段落劃線與留言內容</th>
                                <th style="width: 120px;">發表時間</th>
                                <th style="width: 80px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($comments_list)): ?>
                                <tr><td colspan="6" style="text-align:center; color:#718096; padding: 30px;">🍃 全站目前還沒有任何讀者留言喔！</td></tr>
                            <?php else: ?>
                                <?php foreach($comments_list as $c): ?>
                                <tr>
                                    <td><?= $c['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($c['nickname'] ?? $c['username']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($c['story_title']) ?></strong><div style="font-size:11px; color:#718096;">📌 <?= htmlspecialchars($c['chapter_title']) ?></div></td>
                                    <td>
                                        <?php if(!empty($c['selected_text'])): ?><div style="background:#fffaf0; border-left:3px solid #dd6b20; padding:4px 8px; font-size:12px; margin-bottom:6px;">📖 劃線：「<?= htmlspecialchars($c['selected_text']) ?>」</div><?php endif; ?>
                                        <div style="font-size:14px; background:#f7fafc; padding:8px; border-radius:6px; border:1px solid #edf2f7;">💬 <?= htmlspecialchars($c['content']) ?></div>
                                    </td>
                                    <td style="font-size:12px; color:#718096;"><?= substr($c['created_at'], 5, 11) ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('確定要刪除這條留言嗎？');">
                                            <input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-danger">刪除</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page === 'bottles'): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 140px;">投放者</th>
                                <th style="width: 45%;">投放內容</th>
                                <th>對哪個故事投放</th>
                                <th style="width: 160px;">投放時間</th>
                                <th style="width: 100px;">海洋安全操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($bottles_list)): ?>
                                <tr><td colspan="5" style="text-align:center; color:#718096; padding: 40px;">🌊 目前海域非常平靜乾淨，沒有任何漂流瓶存在。</td></tr>
                            <?php else: ?>
                                <?php foreach($bottles_list as $b): ?>
                                <tr>
                                    <td><strong style="color: #2D2520;">👤 <?= htmlspecialchars($b['author']) ?></strong></td>
                                    <td><div style="font-size:14px; background:#f7fafc; padding:10px; border-radius:6px; border:1px solid #edf2f7; word-break: break-all; line-height: 1.5;">✉️ <?= htmlspecialchars($b['content']) ?></div></td>
                                    <td><span style="color:#b7791f; font-weight:600;">📖 <?= htmlspecialchars($b['story_title'] ?? '未知故事') ?></span></td>
                                    <td style="font-size:12px; color:#718096;"><?= $b['created_at'] ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('確定要打撈並完全摧毀這只違規漂流瓶嗎？');">
                                            <input type="hidden" name="action" value="delete_bottle">
                                            <input type="hidden" name="bottle_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-danger">🗑️ 摧毀</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const toggleSidebar = document.getElementById('toggleSidebar');
        const body = document.body;

        // 檢查本地儲存設定側邊欄狀態
        if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
            body.classList.add('sidebar-collapsed');
        }

        toggleSidebar.addEventListener('click', () => {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('admin_sidebar_collapsed', body.classList.contains('sidebar-collapsed'));
        });
    </script>
</body>
</html>