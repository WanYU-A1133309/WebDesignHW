<?php //為盲盒多加12,13行
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    // 清除任何已輸出的內容（例如 db.php 的 HTML 注釋），確保回傳乾淨的 JSON
    if (ob_get_level()) ob_end_clean();
    ob_start();

    if (!isset($_SESSION['user_id'])) {
        //$stmt = $pdo->prepare("INSERT INTO user_reading_history (user_id, book_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP");
        //$stmt->execute([$_SESSION['user_id'], $current_book_id]);

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '請先登入帳號喔！']);
        exit;
    }
 
    $user_id = $_SESSION['user_id'];
 
    
    if ($_GET['action'] === 'add') {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        $chapter_id    = intval($_POST['chapter_id'] ?? 0);
        $content       = trim($_POST['content'] ?? '');
        $parent_id     = intval($_POST['parent_id'] ?? 0);
        $para_idx      = intval($_POST['paragraph_index'] ?? -1);
        $selected_text = $_POST['selected_text'] ?? '';
 
        if (empty($content)) {
            echo json_encode(['status' => 'error', 'message' => '留言內容不能為空！']);
            exit;
        }
 
        try {
            $sql = "INSERT INTO chapter_annotations (chapter_id, user_id, paragraph_index, selected_text, content, parent_id, like_count) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$chapter_id, $user_id, $para_idx, $selected_text, $content, $parent_id]);
 
            // 取回新留言資料（含使用者名稱），方便前端即時渲染
            $new_id = $pdo->lastInsertId();
            $fetch = $pdo->prepare("SELECT a.*, u.username, u.nickname, u.id AS user_id, u.avatar 
                                    FROM chapter_annotations a 
                                    JOIN users u ON a.user_id = u.id 
                                    WHERE a.id = ?");
            $new_comment = $fetch->fetch(PDO::FETCH_ASSOC);
 
            echo json_encode(['status' => 'success', 'comment' => $new_comment]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => '資料庫寫入失敗：' . $e->getMessage()]);
            exit;
        }
    }
 
    // 點讚（防重複：用 session 記錄）
    if ($_GET['action'] === 'like') {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $liked_key  = "liked_{$comment_id}";
 
        if (!empty($_SESSION[$liked_key])) {
            // 取消讚
            unset($_SESSION[$liked_key]);
            $up_stmt = $pdo->prepare("UPDATE chapter_annotations SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?");
            $up_stmt->execute([$comment_id]);
            $action = 'unliked';
        } else {
            // 加讚
            $_SESSION[$liked_key] = true;
            $up_stmt = $pdo->prepare("UPDATE chapter_annotations SET like_count = like_count + 1 WHERE id = ?");
            $up_stmt->execute([$comment_id]);
            $action = 'liked';
        }
 
        try {
            $q_stmt = $pdo->prepare("SELECT like_count FROM chapter_annotations WHERE id = ?");
            $q_stmt->execute([$comment_id]);
            $res = $q_stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'like_count' => $res['like_count'] ?? 0, 'action' => $action]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => '按讚失敗：' . $e->getMessage()]);
            exit;
        }
    }
    // 新的啊啊啊啊啊啊啊啊啊啊啊啊啊啊啊啊彩蛋
    elseif ($_GET['action'] === 'unlock_egg') {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
            
        $chapter_id = intval($_POST['chapter_id'] ?? 0);
        $egg_price  = intval($_POST['egg_price'] ?? 0);
            
        if ($chapter_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => '無效的章節編號']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. 檢查使用者目前的甜甜圈是否足夠
            $user_stmt = $pdo->prepare("SELECT donuts FROM users WHERE id = ? FOR UPDATE");
            $user_stmt->execute([$user_id]);
            $user_donuts = $user_stmt->fetchColumn();

            if ($user_donuts < $egg_price) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => '您的甜甜圈不夠囉！快去每日任務賺取或充值吧 🍩']);
                exit;
            }

            // 2. 檢查是否早已解鎖過（防禦性重複點擊扣款）
            $check_stmt = $pdo->prepare("SELECT 1 FROM user_unlocks WHERE user_id = ? AND chapter_id = ?");
            $check_stmt->execute([$user_id, $chapter_id]);
            if ($check_stmt->fetch()) {
                $pdo->rollBack();
                echo json_encode(['status' => 'success', 'message' => '已解鎖過此彩蛋']);
                exit;
            }

            // 3. 扣除使用者的甜甜圈
            $deduct_stmt = $pdo->prepare("UPDATE users SET donuts = donuts - ? WHERE id = ?");
            $deduct_stmt->execute([$egg_price, $user_id]);

            // 4. 寫入解鎖紀錄表
            $unlock_stmt = $pdo->prepare("INSERT INTO user_unlocks (user_id, chapter_id) VALUES (?, ?)");
            $unlock_stmt->execute([$user_id, $chapter_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => '解鎖成功！快來看高甜番外吧 🎉']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => '系統錯誤：' . $e->getMessage()]);
            exit;
        }
    }
}
 
// ── 讀取故事資料 ──────────────────────────────────────────────
 
$story_id         = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_chap_idx = isset($_GET['ch']) ? intval($_GET['ch']) : 0;
 
$story = null; $chapters = []; $current_chapter = null;
$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : 0;
$user_donuts  = 0; $user_wheat = 0;
 
if ($is_logged_in) {
    $u_stmt = $pdo->prepare("SELECT donuts, wheat, avatar FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $u_data = $u_stmt->fetch(PDO::FETCH_ASSOC);
    if ($u_data) { 
        $user_donuts = $u_data['donuts']; 
        $user_wheat  = $u_data['wheat']; 
        // 確保不論資料庫有沒有頭貼，都不會是空變數
        $user_avatar = !empty($u_data['avatar']) ? $u_data['avatar'] : 'https://i.pravatar.cc/100?img=47';
    } else {
        $user_avatar = 'https://i.pravatar.cc/100?img=47';
    }

    // 閱讀挑戰進度（同 index.php 邏輯）
    try {
        $ch_stmt = $pdo->prepare("
            SELECT uc.progress_count as user_current, c.progress_count as challenge_target
            FROM user_challenges uc
            JOIN challenges c ON uc.challenge_id = c.id
            WHERE uc.user_id = ? AND uc.status = 'progress'
            LIMIT 1
        ");
        $ch_stmt->execute([$user_id]);
        $ch_res = $ch_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ch_res && $ch_res['challenge_target'] > 0) {
            $user_progress = min(100, max(0, round(($ch_res['user_current'] / $ch_res['challenge_target']) * 100)));
            $user_progress_current = intval($ch_res['user_current']);
            $user_progress_target  = intval($ch_res['challenge_target']);
        } else {
            $user_progress = 0;
            $user_progress_current = 0;
            $user_progress_target  = 0;
        }
    } catch (PDOException $e) {
        $user_progress = 0;
        $user_progress_current = 0;
        $user_progress_target  = 0;
    }
} else {
    $user_avatar = 'https://i.pravatar.cc/100?img=47';
    $user_progress = 0;
    $user_progress_current = 0;
    $user_progress_target  = 0;
}
 
if ($story_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.username, u.nickname, u.id AS user_id, u.avatar FROM stories s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->execute([$story_id]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if ($story) {
            $chap_stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? AND (status = 'published' OR (status = 'scheduled' AND publish_at <= NOW())) ORDER BY chapter_number ASC, id ASC");
            $chap_stmt->execute([$story_id]);
            $chapters = $chap_stmt->fetchAll(PDO::FETCH_ASSOC);
            if (isset($chapters[$current_chap_idx])) $current_chapter = $chapters[$current_chap_idx];
        }
    } catch (PDOException $e) { die("資料庫連線錯誤：" . $e->getMessage()); }
}
if (!$story) die("📚 抱歉，找不到該篇故事。");
// ===================================================
// 📖 真正正確的【真實閱讀紀錄寫入區塊】放在這裡！
// ===================================================

// ===================================================
// ===================================================
// ⭐ 讀完一本書（進度100%）→ 加經驗值 + 判斷升等
// ===================================================
if (isset($_SESSION['user_id'])) {
    $record_user_id = $_SESSION['user_id'];
    $record_book_id = $story_id;
    
    $total_chaps = count($chapters);
    $record_progress = 100;
    if ($total_chaps > 0) {
        $record_progress = min(100, max(1, round((($current_chap_idx + 1) / $total_chaps) * 100)));
    }

    try {
        // ⭐ 先查舊進度（寫入前）
        $check_done = $pdo->prepare("
            SELECT progress FROM user_reading_history 
            WHERE user_id = ? AND book_id = ?
        ");
        $check_done->execute([$record_user_id, $record_book_id]);
        $old_progress = (int)($check_done->fetchColumn() ?: 0);

        // 寫入新進度
        $stmt_history = $pdo->prepare("
            INSERT INTO user_reading_history (user_id, book_id, progress) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE progress = VALUES(progress), updated_at = NOW()
        ");
        $stmt_history->execute([$record_user_id, $record_book_id, $record_progress]);

        // ⭐ 讀完且之前未完成 → 加經驗值
        if ($record_progress >= 100 && $old_progress < 100) {
            $exp_stmt = $pdo->prepare("SELECT exp, level FROM users WHERE id = ?");
            $exp_stmt->execute([$record_user_id]);
            $exp_data = $exp_stmt->fetch(PDO::FETCH_ASSOC);

            $new_exp   = $exp_data['exp'] + 100;
            $new_level = $exp_data['level'];
            $exp_max   = 1000 * $new_level;

            while ($new_exp >= $exp_max) {
                $new_exp   -= $exp_max;
                $new_level += 1;
                $exp_max    = 1000 * $new_level;
            }

            $up_exp = $pdo->prepare("UPDATE users SET exp = ?, level = ? WHERE id = ?");
            $up_exp->execute([$new_exp, $new_level, $record_user_id]);
        }
    } catch (Exception $e) {
        error_log('EXP ERROR: ' . $e->getMessage());
    }
}
 
$author_name = !empty($story['nickname']) ? $story['nickname'] : $story['username'];
 
// 彩蛋解鎖
$egg_unlocked = false; $egg_price = 0;
if ($current_chapter) {
    $egg_price = intval($current_chapter['price_donuts'] ?? 0);
    if ($egg_price == 0) {
        $egg_unlocked = true;
    } elseif ($is_logged_in) {
        $un_stmt = $pdo->prepare("SELECT 1 FROM user_unlocks WHERE user_id = ? AND chapter_id = ?");
        $un_stmt->execute([$user_id, $current_chapter['id']]);
        if ($un_stmt->fetch()) $egg_unlocked = true;
    }
}
 
// 作者其他作品
$author_stmt = $pdo->prepare("SELECT * FROM stories WHERE user_id = ? AND id != ? ORDER BY id DESC LIMIT 3");
$author_stmt->execute([$story['user_id'], $story_id]);
$author_other_stories = $author_stmt->fetchAll(PDO::FETCH_ASSOC);
 
// 推薦書單
$recommend_books = [];
if (!empty($story['tags'])) {
    $main_tag = trim(explode(',', $story['tags'])[0]);
    $rec_stmt = $pdo->prepare("SELECT * FROM stories WHERE id != ? AND tags LIKE ? ORDER BY id DESC LIMIT 4");
    $rec_stmt->execute([$story_id, "%$main_tag%"]);
    $recommend_books = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (count($recommend_books) < 4) {
    $needed      = 4 - count($recommend_books);
    $exclude_ids = array_merge([$story_id], array_column($recommend_books, 'id'));
    $in_clause   = implode(',', array_fill(0, count($exclude_ids), '?'));
    $fallback    = $pdo->prepare("SELECT * FROM stories WHERE id NOT IN ($in_clause) ORDER BY id DESC LIMIT $needed");
    $fallback->execute($exclude_ids);
    $recommend_books = array_merge($recommend_books, $fallback->fetchAll(PDO::FETCH_ASSOC));
}
if ($current_chapter && !empty($current_chapter['content'])) {
    $current_chapter['content'] = str_replace('', '', $current_chapter['content']);
}
 
// 留言撈取
$annotations = []; $general_comments = []; $reply_comments = [];
// 記錄當前使用者已按過讚的留言
$user_liked_set = [];
$text_comment_counts = [];
if ($current_chapter) {
    try {
        $comm_stmt = $pdo->prepare("SELECT a.*, u.username, u.nickname, u.id AS user_id, u.avatar FROM chapter_annotations a JOIN users u ON a.user_id = u.id WHERE a.chapter_id = ? ORDER BY a.like_count DESC, a.created_at DESC");
        $comm_stmt->execute([$current_chapter['id']]);
        $all_comments = $comm_stmt->fetchAll(PDO::FETCH_ASSOC);
 
        foreach ($all_comments as $c) {
            // 檢查 session 中是否已按讚
            if (!empty($_SESSION["liked_{$c['id']}"])) $user_liked_set[] = $c['id'];
 
            if ($c['parent_id'] > 0) {
                $reply_comments[$c['parent_id']][] = $c;
            } else {
                if (isset($c['paragraph_index']) && $c['paragraph_index'] >= 0) {
                    $annotations[] = $c;

                    if (!empty($c['selected_text'])) {
                        $text_key = trim($c['selected_text']);
                        $text_comment_counts[$text_key] = ($text_comment_counts[$text_key] ?? 0) + 1;
                    }
                } else {
                    $general_comments[] = $c;
                }
            }
        }
    } catch (PDOException $e) { }
}
// ===================================================
// 處理閱讀頁「丟漂流瓶」表單提交 新的ㄚㄚㄚㄚㄚㄚㄚㄚ
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'read_page_throw') {
    // 從 URL 或隱藏欄位獲取當前故事 ID
    $story_id = intval($_POST['story_id'] ?? 0);
    $content = trim($_POST['notes_text'] ?? '');
    
    if (!empty($content) && $story_id > 0) {
        $user_id = $_SESSION['user_id'] ?? null;
        // 抓取登入者暱稱，沒登入就叫神秘讀者
        $nickname = $_SESSION['nickname'] ?? ($_SESSION['username'] ?? '神秘讀者');

        try {
            $stmtBottle = $pdo->prepare("INSERT INTO drifting_bottles (user_id, nickname, story_id, content) VALUES (?, ?, ?, ?)");
            if ($stmtBottle->execute([$user_id, $nickname, $story_id, $content])) {
                // 發送成功後，彈窗提示並重定向回原閱讀頁（避免重新整理網頁時重複提交）
                $current_url = $_SERVER['REQUEST_URI'];
                echo "<script>alert('🍾 你的真心話已被封入玻璃瓶，拋向遠方故事海...'); location.href='{$current_url}';</script>";
                exit;
            }
        } catch (Exception $e) {
            echo "<script>alert('大海風浪太大，瓶子被沖回岸邊...');</script>";
        }
    } else {
        echo "<script>alert('⚠️ 瓶子裡空空的，寫點真心話再丟吧！');</script>";
    }
}
 
// PHP helper：渲染一則留言卡片（含回覆巢狀）
function renderComment($c, $reply_comments, $user_liked_set, $indent = false) {
    $comment_user_id = intval($c['user_id'] ?? 0);
    $comment_avatar  = !empty($c['avatar']) ? $c['avatar'] : 'https://i.pravatar.cc/100?img=47';
    $display_name = !empty($c['nickname']) ? $c['nickname'] : ($c['username'] ?? '匿名讀者');
    $liked_class  = in_array($c['id'], $user_liked_set) ? 'liked' : '';
    $heart_color  = $liked_class ? 'var(--active-color)' : 'var(--muted)';
    $para_attr    = ($c['paragraph_index'] >= 0) ? "data-para=\"{$c['paragraph_index']}\"" : '';
    $indent_style = $indent ? 'margin-left:0;' : '';
    
    // 💡 計算當前留言有幾條回覆
    $replies = $reply_comments[$c['id']] ?? [];
    $reply_count = count($replies);

    ob_start(); ?>
    <div class="comment-item" id="cmt-<?= $c['id'] ?>" <?= $para_attr ?> style="<?= $indent_style ?>">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="user.php?id=<?= $comment_user_id ?>">
                    <img src="<?= htmlspecialchars($comment_avatar) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover; cursor:pointer;">
                </a>
                <a href="user.php?id=<?= $comment_user_id ?>" style="text-decoration:none;">
                    <span style="font-weight:bold; color:var(--brand); font-size:13px; cursor:pointer;"> <?= htmlspecialchars($display_name) ?></span>
                </a>
            </div>
            <button class="like-btn <?= $liked_class ?>" data-id="<?= $c['id'] ?>" style="background:none; border:none; cursor:pointer; font-size:12px; white-space:nowrap; flex-shrink:0;">
                <span class="heart-icon">❤️</span><span class="lk-cnt" style="font-weight: bold; color: var(--ink);"><?= $c['like_count'] ?></span>
            </button>
        </div>

        <?php if (!empty($c['selected_text'])): ?>
            <div style="font-size:11px; background:var(--highlight); border-left:3px solid #e6b800; padding:4px 8px; margin:6px 0; border-radius:4px; color:#665500;">
                「<?= htmlspecialchars($c['selected_text']) ?>」
            </div>
        <?php endif; ?>

        <div style="margin:6px 0; color:var(--ink); font-size:14px; white-space:pre-line; line-height:1.6;"><?= htmlspecialchars($c['content']) ?></div>

        <div style="font-size:11px; color:var(--muted); display:flex; justify-content:space-between; align-items:center;">
            <span>🕒 <?= date('Y-m-d', strtotime($c['created_at'])) ?></span>
            <div style="display:flex; gap:12px; align-items:center;">
                <?php if ($reply_count > 0): ?>
                    <span class="toggle-replies-btn" data-id="<?= $c['id'] ?>" style="color:var(--brand); cursor:pointer; font-weight:bold; font-size:12px;">
                        💬 <?= $reply_count ?> 條回覆 ▾
                    </span>
                <?php endif; ?>
                <span class="reply-trigger" data-id="<?= $c['id'] ?>" data-name="<?= $display_name ?>" style="color:var(--active-color); cursor:pointer; font-weight:bold; font-size:12px;">↩ 回覆</span>
            </div>
        </div>

        <?php if ($reply_count > 0): ?>
            <div class="replies-container" id="replies-<?= $c['id'] ?>" style="display:none; margin-top:8px; padding:8px 12px; border-radius:8px; background:#faf6ee; border-left:3px solid var(--border); display:flex; flex-direction:column; gap:6px;">
                <?php foreach ($replies as $reply): ?>
                    <?= renderComment($reply, [], $user_liked_set, true) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <link rel="stylesheet" href="story_style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($story['title']) ?> - 咬一口故事</title>
    
</head>
<body>
 
<!-- 反白工具列 -->
<div id="selectionToolbar">
    <button onclick="doInlineComment()">✍️ 畫重點留言</button>
    <div class="sep"></div>
    <button onclick="doSidebarAnnotation()">📌 加入側欄</button>
</div>
 
<!-- 反白內嵌輸入框 -->
<div id="inlineCommentBox">
    <div class="quote-preview" id="inlineQuotePreview"></div>
    <textarea id="inlineCommentText" placeholder="對這段有什麼感想...（Enter 換行，雙按 Enter 送出）"></textarea>
    <div class="hint">⌨️ 連按兩下 Enter 快速送出</div>
    <div class="btn-row">
        <button class="btn-cancel" onclick="closeInlineBox()">取消</button>
        <button class="btn-send" onclick="submitInlineComment()">發布 🚀</button>
    </div>
</div>
 
<header class="navbar">
    <div class="nav-left">
        <a href="explore.php" class="logo">咬一口故事</a>
        <nav class="nav-links">
            <a href="index.php">🏠 首頁</a>
            <a href="explore.php" class="active">🔍 探索</a>
            <a href="bottle.php">🍾 漂流瓶</a>
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
            
        </nav>
    </div>
 
    <div class="nav-right">
        <div class="user-challenge" onclick="location.href='challenge.php'" style="cursor: pointer;">
            <span>📖 閱讀挑戰</span>
            <div class="challenge-bar-bg">
                <div class="challenge-bar-fill" style="width:<?= $user_progress ?>%;"></div>
            </div>
            <span><?php
                if ($user_progress_target > 0) {
                    echo $user_progress_current . '/' . $user_progress_target;
                } else {
                    echo $user_progress . '%';
                }
            ?></span>
        </div>
        <div class="user-wallet">
            <div class="wallet-item">🍩 <span id="walletDonuts"><?= number_format($user_donuts) ?></span></div>
            <div class="wallet-item">🌾 <span><?= number_format($user_wheat) ?></span></div>
        </div>
        <div class="avatar-wrap">
            <a href="profile.php">
                <img class="avatar" src="<?= !empty($user_avatar) ? htmlspecialchars($user_avatar) : 'https://i.pravatar.cc/100?img=47' ?>" alt="User" style="cursor: pointer;">
            </a>
            <div class="avatar-popover" onclick="event.stopPropagation()">
                <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">您好</div>
                <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                <a href="logout.php" class="popover-btn secondary">登出帳號</a>
            </div>
        </div>
    </div>
</header>
 
<main class="read-layout">
 
    <div class="author-info-column">
        <?php
        $is_fav = false;
        if ($is_logged_in && isset($story['id'])) {
            $fav_stmt = $pdo->prepare("SELECT 1 FROM bookshelves WHERE user_id = ? AND story_id = ?");
            $fav_stmt->execute([$user_id, $story['id']]);
            $is_fav = (bool)$fav_stmt->fetch();
        }
        ?>
        <div class="panel author-card" style="text-align: center;">
            <a href="user.php?id=<?= intval($story['user_id'] ?? 0) ?>" style="text-decoration: none; color: inherit; display: block; text-align: center;">
                <?php
                $author_avatar_src = !empty($story['avatar']) ? $story['avatar'] : '';
                if ($author_avatar_src): ?>
                    <img src="<?= htmlspecialchars($author_avatar_src) ?>"
                         style="width:64px; height:64px; border-radius:50%; object-fit:cover; margin: 0 auto 8px auto; display:block; border:3px solid var(--brand-light); cursor:pointer;"
                         alt="<?= htmlspecialchars($author_name) ?>">
                <?php else: ?>
                    <div class="author-avatar" style="cursor: pointer; margin: 0 auto 8px auto;">✍️</div>
                <?php endif; ?>
                <strong style="display:block; margin-bottom:4px; font-size:16px; color: var(--brand);"><?= htmlspecialchars($author_name) ?></strong>
            </a>
            <span style="font-size:12px; color:var(--muted);">@<?= htmlspecialchars($story['username']) ?></span>
            <div class="bookshelf-action-wrap" style="width: 100%; margin-top: 10px;">
                <button type="button" 
                        id="storyBookshelfBtn"
                        class="story-fav-btn <?= $is_fav ? 'active' : '' ?>" 
                        onclick="toggleStoryBookshelf(<?= (int)$story['id'] ?>, this)" 
                        title="<?= $is_fav ? '移出書架' : '加入書架' ?>">
                    <span class="star-icon"><?= $is_fav ? '★' : '☆' ?></span> 
                    <span class="text-label"><?= $is_fav ? '已在書架' : '收藏至書架' ?></span>
                </button>
            </div>
        </div>

        <div class="sidebar-bottle-card">
            <div class="sidebar-bottle-title"><span>🍾</span> 封裝你的漂流瓶</div>
            <div class="sidebar-bottle-desc">讀完這章有什麼觸動嗎？寫下你當下的閱讀感悟，拋向故事海吧！</div>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="read_page_throw">
                <input type="hidden" name="story_id" value="<?= intval($story_id) ?>">
                
                <textarea 
                    name="notes_text" 
                    class="sidebar-bottle-textarea" 
                    placeholder="寫下讀後感... 投遞後將自動關聯本部作品喔！" 
                    required></textarea>
                    
                <button type="submit" class="sidebar-bottle-btn">
                    <span>🚀</span> 拋向故事海
                </button>
            </form>
        </div>
        
        <div class="author-works-box">
            <h4 style="margin:0 0 12px 0; font-size:14px; color:var(--brand); border-bottom:2px solid var(--border); padding-bottom:6px;">✍️ 作者的其他作品</h4>
            <?php if (!empty($author_other_stories)): ?>
                <?php foreach ($author_other_stories as $aos): ?>
                    <a href="story.php?id=<?= $aos['id'] ?>" class="author-work-item">
                        <span style="font-size:14px;">📚</span>
                        <div class="work-title"><?= htmlspecialchars($aos['title']) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="font-size:12px; color:var(--muted); text-align:center; padding:10px 0;">這是該作者的第一部作品 ✨</div>
            <?php endif; ?>
        </div>
    </div>
 
    <section class="panel center-content">
        <div class="article-container">
            <header class="article-header">
                <?php if ($story['type'] === 'serial' && $current_chapter): ?>
                    <div class="chapter-badge">連載中 ❖ 第 <?= htmlspecialchars($current_chapter['chapter_number']) ?> 章</div>
                <?php elseif ($story['type'] === 'single'): ?>
                    <div class="chapter-badge" style="background:#eef7ed; color:#438a4d;">短篇全一冊</div>
                <?php endif; ?>
                <h1 class="article-title"><?= htmlspecialchars($current_chapter['title'] ?? $story['title']) ?></h1>
                <div class="article-meta">
                    作品系列：《<a href="story_index.php?id=<?= $story_id ?>" style="color:var(--brand); text-decoration:none; font-weight:bold;"><?= htmlspecialchars($story['title']) ?></a>》
                    <span style="margin-left:15px;">標籤：#<?= htmlspecialchars(!empty($story['tags']) ? explode(',', $story['tags'])[0] : '綜合') ?></span>
                </div>
            </header>
 
            <div class="story-intro-block">
                <p class="story-intro-text"><?= htmlspecialchars($current_chapter['intro'] ?? '翻開這一頁，走進那座在深夜才會亮起微光的神祕故事館……') ?></p>
            </div>
 
            <article class="article-text" id="storyArticleText">
                <?php
                if ($current_chapter && !empty($current_chapter['content'])) {
                    $content = $current_chapter['content'];
                    if (strpos($content, '<p') !== false || strpos($content, '<div') !== false || strpos($content, '<blockquote') !== false) {
                        $dom = new DOMDocument();
                        libxml_use_internal_errors(true);
                        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $xpath = new DOMXPath($dom);
                        $elements = $xpath->query('//p | //div | //blockquote | //span');
                        if ($elements->length > 0) {
                            foreach ($elements as $index => $el) {
                                if (!$el->hasAttribute('data-index')) $el->setAttribute('data-index', $index);
                            }
                            echo $dom->saveHTML();
                        } else { echo $content; }
                        libxml_clear_errors();
                    } else {
                        foreach (explode("\n", $content) as $index => $para) {
                            $para = trim($para);
                            if (empty($para)) continue;
                            echo "<p data-index='{$index}'>" . htmlspecialchars($para) . "</p>";
                        }
                    }
                } else {
                    echo "<p data-index='0'>一段溫暖人心的故事正準備在此展開...</p>";
                }
                ?>
            </article>
 
            <?php if ($current_chapter && !empty($current_chapter['egg_content'])): ?>
                <div class="egg-bottom-box">
                    <?php if ($egg_unlocked): ?>
                        <div class="panel" style="background:linear-gradient(180deg,#fffcf7,#fdf6ec); border:1px solid #e2cfb6; padding:24px;">
                            <h3 style="margin:0 0 12px 0; color:var(--brand);">🎉 經由甜甜圈投餵解鎖的隱藏番外</h3>
                            <div style="font-size:16px; line-height:2.0; color:#2b1a0f; white-space:pre-wrap;"><?= $current_chapter['egg_content'] ?></div>
                        </div>
                    <?php else: ?>
                        <div class="egg-lock-screen">
                            <span style="font-size:36px;">🔒</span>
                            <h3 style="margin:12px 0 6px 0; color:var(--brand);">解鎖查看本章高甜彩蛋番外</h3>
                            <p style="font-size:14px; color:var(--muted); margin:0 0 20px 0;">作者精心準備了後續幕後彩蛋，需要花費 <b style="color:var(--active-color);"><?= $egg_price ?></b> 個甜甜圈解鎖</p>
                            <button class="btn-brand" style="width:auto; padding:12px 40px;" onclick="unlockEasterEgg(<?= $current_chapter['id'] ?>, <?= $egg_price ?>)">使用 🍩 解鎖故事彩蛋</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
 
        <footer class="article-footer">
            <?php if (isset($chapters[$current_chap_idx + 1])): ?>
                <a href="story.php?id=<?= $story_id ?>&ch=<?= $current_chap_idx + 1 ?>" class="btn-next">下一章 ➔</a>
            <?php else: ?>
                <a href="explore.php" class="btn-next" style="background:#fff; color:var(--muted); border-color:var(--border);">筆墨至此 返回探索 ↩</a>
            <?php endif; ?>
        </footer>
    </section>
 
    <!-- ── 右側評論區 ── -->
    <section class="panel right-panel">
        <div class="comment-box-title">評論區</div>
 
        <div class="comment-tabs">
            <div class="comment-tab active" id="tabGeneralBtn" onclick="switchCommentTab('general')">
                一般留言 <span id="generalCount">(<?= count($general_comments) ?>)</span>
            </div>
            <div class="comment-tab" id="tabAnnotBtn" onclick="switchCommentTab('annot')">
                劃線重點 <span id="annotCount">(<?= count($annotations) ?>)</span>
            </div>
        </div>
 
        <!-- 輸入區 -->
        <div class="comment-input-area">
            <div class="annotation-quote" id="annotQuoteBox">
                <span>引用劃線：</span><span id="annotQuoteText"></span>
            </div>
            <div class="reply-label" id="replyLabel"></div>
            <input type="hidden" id="submitParentId" value="0">
            <input type="hidden" id="annotParaIndex" value="-1">
            <input type="hidden" id="annotSelectedText" value="">
 
            <textarea id="commentTextarea" placeholder="在此留下你對故事或角色的感想...（連按兩下 Enter 可快速送出）"></textarea>
            <button class="btn-brand" style="width:100%; margin-top:8px; padding:10px; border-radius:8px;" onclick="submitMyComment(<?= $current_chapter['id'] ?? 0 ?>)">
                留下想法
            </button>
            <div style="font-size:11px; color:var(--muted); text-align:right; margin-top:4px;">⌨️ 連按兩下 Enter 也可送出</div>
        </div>
 
        <div style="margin-top:16px;"></div>
 
        <!-- 一般留言列表 -->
        <div id="generalCommentList" class="comment-list-scroll">
            <?php if (!empty($general_comments)): ?>
                <?php foreach ($general_comments as $gc): ?>
                    <?= renderComment($gc, $reply_comments, $user_liked_set) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; color:var(--muted); font-size:13px; padding:30px 0;">沙發還空著，快來留下第一個足跡吧！✨</div>
            <?php endif; ?>
        </div>
 
        <!-- 劃線留言列表 -->
        <div id="annotCommentList" class="comment-list-scroll" style="display:none;">
            <?php if (!empty($annotations)): ?>
                <?php foreach ($annotations as $ann): ?>
                    <?= renderComment($ann, $reply_comments, $user_liked_set) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; color:var(--muted); font-size:13px; padding:30px 0;">反白故事文字後可留下專屬劃線留言 ✍️</div>
            <?php endif; ?>
        </div>
    </section>
 
    <!-- 推薦 -->
    <div class="bottom-rec-section">
        <div class="rec-title">💡你可能也喜歡：</div>
        <div class="rec-grid">
            <?php foreach ($recommend_books as $rb): ?>
                <a href="story.php?id=<?= $rb['id'] ?>" class="rec-card">
                    <div class="rec-img">📚</div>
                    <div>
                        <div style="font-weight:bold; font-size:15px; margin-bottom:4px;"><?= htmlspecialchars($rb['title']) ?></div>
                        <div style="font-size:12px; color:var(--muted);">#<?= htmlspecialchars(!empty($rb['tags']) ? explode(',', $rb['tags'])[0] : '精選') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
 
</main>
 
<script>
// 只保留這幾個 PHP 輸出的變數，JS 檔案需要用到
const CHAPTER_ID = <?= $current_chapter['id'] ?? 0 ?>;
const activeAnnotations = <?= json_encode($annotations) ?>;
const textCommentCounts = <?= json_encode($text_comment_counts ?? []) ?>;
const likedSet = new Set(<?= json_encode($user_liked_set) ?>);
</script>
<script src="story_script.js"></script>
</body>
</html>