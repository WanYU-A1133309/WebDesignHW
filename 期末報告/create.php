<?php
// 咬一口故事 - 投稿頁面核心大腦 create.php (全功能整合完美修復版 - 支援草稿編輯完全體 & 10種風格自訂封面版)
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?back_url=create.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$alert_status = null; 
$error_detail = '';

// 初始化所有預填變數（用於編輯草稿時回填表單）
$edit_chapter_id = !empty($_GET['edit_chapter_id']) ? intval($_GET['edit_chapter_id']) : null;
$draft_data = null;

// ===================================================
// 🔥 【核心攔截】如果使用者按下提交表單 (POST) (修復版)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 0. 檢查這是不是一筆既有草稿的更新
        $form_edit_chapter_id = !empty($_POST['edit_chapter_id']) ? intval($_POST['edit_chapter_id']) : null;

        // 1. 接收並清洗前端丟過來的資料
        $post_type = $_POST['post_type'] ?? 'new'; // new 創新作, chapter 續寫
        $novel_type = $_POST['novel_type'] ?? 'serial'; // serial 連載, single 短篇
        $story_id = !empty($_POST['story_id']) ? intval($_POST['story_id']) : null;
        
        $title = trim($_POST['title'] ?? '');
        $chapter_title = trim($_POST['chapter_title'] ?? '');
        $summary = trim($_POST['intro'] ?? ''); // 對應前端的引文
        $tags = trim($_POST['tags'] ?? ''); // 標籤字串 (逗號隔開)
        $content = $_POST['content'] ?? ''; // 主要正文 HTML
        $egg_content = $_POST['egg_content'] ?? ''; // 彩蛋文 HTML
        
        // 🎨 封面相關變數接收修正（HTML 的 name 屬性是 cover_type）
        $cover_type = $_POST['cover_type'] ?? 'none'; 
        $cover_image_path = null;

        // 狀態與特權欄位
        $status = $_POST['story_status'] ?? 'published'; 
        $price_donuts = !empty($_POST['price_donuts']) ? intval($_POST['price_donuts']) : 0;
        $publish_at = !empty($_POST['publish_at']) ? $_POST['publish_at'] : null;
        
        // 接收是否勾選全書完結
        $is_final_chapter = !empty($_POST['is_final_chapter']) ? true : false;

        // 如果是續寫，放寬引文限制
        if ($post_type === 'chapter') {
            $summary = "（續寫章節）";
        }

        // 簡單驗證（儲存草稿時放寬限制，但發布時嚴格）
        if ($status !== 'draft') {
            if ($post_type === 'new' && empty($title)) {
                throw new Exception("請輸入故事總標題！");
            }
            if (empty($content) || $content === '<p><br></p>') {
                throw new Exception("小說正文不能為空！");
            }
        }

        // 計算真實字數
        $clean_text = strip_tags($content);
        $clean_text = preg_replace('/\s+/', '', $clean_text); 
        $word_count = mb_strlen($clean_text, 'UTF-8'); 

        // 💡 獨立彩蛋安全機制
        if (empty($_POST['has_extra'])) {
            $egg_content = null;
        }

        // 🖼️ 處理自訂圖片上傳邏輯
        if ($post_type === 'new' && $cover_type === 'upload' && isset($_FILES['custom_cover']) && $_FILES['custom_cover']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['custom_cover']['tmp_name'];
            $file_name = $_FILES['custom_cover']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 允許的圖片格式
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception("封面圖片格式不符（僅支援 JPG, PNG, WEBP, GIF）");
            }

            // 建立上傳資料夾
            $upload_dir = 'uploads/covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // 重新命名防重複
            $new_file_name = 'cover_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $cover_image_path = $dest_path;
            } else {
                throw new Exception("封面圖片上傳失敗，請稍後再試。");
            }
        }

        // 開啟資料庫交易流程 (Transaction)
        $pdo->beginTransaction();

        if ($form_edit_chapter_id) {
            // 📝 【路徑 A】：更新現有草稿模式 (UPDATE)
            $chk_stmt = $pdo->prepare("SELECT story_id FROM `chapters` WHERE `id` = ?");
            $chk_stmt->execute([$form_edit_chapter_id]);
            $found_story_id = $chk_stmt->fetchColumn();

            if ($found_story_id) {
                $story_id = $found_story_id;

                // 如果原本是建立全新作品，同步更新故事總表（包含封面欄位）
                if ($post_type === 'new') {
                    $story_initial_status = ($novel_type === 'single') ? 'completed' : 'ongoing';
                    
                    // 修正：如果這次選的是自訂上傳，且沒有上傳新圖，則保留本來的舊圖路徑
                    if ($cover_type === 'upload' && empty($cover_image_path)) {
                        $old_cover_stmt = $pdo->prepare("SELECT cover_image FROM `stories` WHERE `id` = ?");
                        $old_cover_stmt->execute([$story_id]);
                        $cover_image_path = $old_cover_stmt->fetchColumn();
                    } elseif ($cover_type !== 'upload') {
                        // 如果切換成了內建風格（如 style3）或無封面，清空自訂上傳欄位防止衝突
                        $cover_image_path = null;
                    }

                    $up_story = $pdo->prepare("UPDATE `stories` SET `type` = ?, `title` = ?, `intro` = ?, `tags` = ?, `status` = ?, `cover_type` = ?, `cover_image` = ? WHERE `id` = ? AND `user_id` = ?");
                    $up_story->execute([$novel_type, $title, $summary, $tags, $story_initial_status, $cover_type, $cover_image_path, $story_id, $current_user_id]);

                    if ($novel_type === 'single') {
                        $chapter_title = $title;
                    }
                }

                // 更新該章節內容
                $up_ch = $pdo->prepare("UPDATE `chapters` SET `title` = ?, `content` = ?, `egg_content` = ?, `word_count` = ?, `status` = ?, `price_donuts` = ?, `publish_at` = ? WHERE `id` = ?");
                $up_ch->execute([$chapter_title, $content, $egg_content, $word_count, $status, $price_donuts, $publish_at, $form_edit_chapter_id]);
            } else {
                throw new Exception("找不到該草稿章節資料！");
            }

        } else {
            // 🌱 【路徑 B】：全新建立模式 (INSERT)
            if ($post_type === 'new') {
                $story_initial_status = ($novel_type === 'single') ? 'completed' : 'ongoing';
                $stmt = $pdo->prepare("INSERT INTO `stories` (`user_id`, `type`, `title`, `intro`, `tags`, `status`, `cover_type`, `cover_image`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user_id, $novel_type, $title, $summary, $tags, $story_initial_status, $cover_type, $cover_image_path]);
                $story_id = $pdo->lastInsertId();

                if ($novel_type === 'single') {
                    $chapter_title = $title;
                }
            }

            // 計算這是第幾章
            $chapter_number = 1;
            if ($post_type === 'chapter') {
                $num_stmt = $pdo->prepare("SELECT MAX(chapter_number) FROM `chapters` WHERE `story_id` = ?");
                $num_stmt->execute([$story_id]);
                $max_num = $num_stmt->fetchColumn();
                $chapter_number = $max_num ? intval($max_num) + 1 : 1;
            }

            // 塞入 chapters 表
            $ch_stmt = $pdo->prepare("INSERT INTO `chapters` (`story_id`, `chapter_number`, `title`, `content`, `egg_content`, `word_count`, `status`, `price_donuts`, `publish_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ch_stmt->execute([$story_id, $chapter_number, $chapter_title, $content, $egg_content, $word_count, $status, $price_donuts, $publish_at]);
            
            $form_edit_chapter_id = $pdo->lastInsertId();
        }

        if ($post_type === 'chapter' && $is_final_chapter && $status !== 'draft') {
            $update_story_stmt = $pdo->prepare("UPDATE `stories` SET `status` = 'completed' WHERE `id` = ? AND `user_id` = ?");
            $update_story_stmt->execute([$story_id, $current_user_id]);
        }

        $pdo->commit();

        if ($status === 'draft') {
            header("Location: drafts.php?alert=draft_success");
        } else {
            header("Location: drafts.php?alert=publish_success");
        }
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); 
        }
        $redir_id = !empty($form_edit_chapter_id) ? "&edit_chapter_id=" . $form_edit_chapter_id : "";
        header("Location: create.php?alert=error" . $redir_id . "&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// ===================================================
// 📥 撈取用戶資料與既有故事 (GET 流程)
// ===================================================
try {
    $stmt = $pdo->prepare("SELECT id, username, nickname, role, is_contracted, donuts, wheat,avatar FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

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
        'donuts'        => $db_user['donuts'],
        'wheat'         => $db_user['wheat']
    ];

    $_SESSION['user_role'] = $user['role'];
    $_SESSION['is_contracted'] = $user['is_contracted'];
    $_SESSION['avatar'] = $user['avatar'];

    // 💡 智慧回填加上了封面資訊撈取
    if ($edit_chapter_id) {
        $draft_stmt = $pdo->prepare("
            SELECT c.*, s.title AS story_title, s.type AS story_type, s.intro AS story_intro, s.tags AS story_tags, s.cover_type, s.cover_image
            FROM `chapters` c
            JOIN `stories` s ON c.story_id = s.id
            WHERE c.id = ? AND s.user_id = ?
        ");
        $draft_stmt->execute([$edit_chapter_id, $current_user_id]);
        $draft_data = $draft_stmt->fetch(PDO::FETCH_ASSOC);
    }

    $my_stories = []; 
    $tableExists = $pdo->query("SHOW TABLES LIKE 'stories'")->rowCount() > 0;
    if ($tableExists) {
        $story_stmt = $pdo->prepare("SELECT id, title FROM stories WHERE user_id = ? AND type = 'serial' AND status = 'ongoing' ORDER BY created_at DESC");
        $story_stmt->execute([$current_user_id]);
        $my_stories = $story_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("資料庫連線或查詢失敗：" . $e->getMessage());
}

$alert_status = $_GET['alert'] ?? null;
$error_detail = $_GET['msg'] ?? '';
?>

<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $draft_data ? '編輯草稿 ‧ ' : '發布新作品 ‧ ' ?>咬一口故事</title>
    <link rel="stylesheet" href="css/create.css">
</head>
<body>
<script>
// 主題套用 — 必須在頁面渲染前執行，避免閃爍
(function(){
  var t = localStorage.getItem('bite_theme');
  if (t && t !== 'warm') document.body.setAttribute('data-theme', t);
})();
</script>

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
            <a href="create.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800; margin-bottom: 4px;">➕ 開始寫故事 / 投稿</a>
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
        <a href="create.php" class="sheet-item" style="background: linear-gradient(135deg, #FFEEDD, #FFE4D2); border: 1px solid var(--primary-2);">
            <span>✍️</span><strong>開始寫故事</strong>
        </a>
        <a href="works.php" class="sheet-item"><span>📖</span>我的作品</a>
        <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
        
    </div>
    <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<main class="main">
    <div class="page-header">
        <h2 class="page-title"><?= $draft_data ? '編輯歷史草稿' : '發布新作品' ?></h2>
        <div class="avatar-wrap">
            <a href="profile.php">
                <img class="avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="User" style="cursor: pointer; width: 46px; height: 46px; border-radius: 50%; object-fit: cover;">
            </a>

            <div class="avatar-popover" onclick="event.stopPropagation()">
                <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">
                    您好，<?= htmlspecialchars($user['name']) ?>
                </div>
                <?php if($user['role'] === 'admin'): ?>
                    <a href="admin/index.php" class="popover-btn" style="background: #5A483C; margin-bottom: 5px;">進入管理員後台</a>
                <?php endif; ?>
                <a href="profile.php" class="popover-btn primary">進入個人中心</a>
                <a href="logout.php" class="popover-btn secondary" style="background: #8C7C72; margin-bottom: 5px;">登出帳號</a>
                
            </div>
        </div>
    </div>

    <form id="postForm" method="POST" action="create.php" enctype="multipart/form-data">
        <input type="hidden" name="edit_chapter_id" value="<?= $edit_chapter_id ?>">
        <input type="hidden" name="story_status" id="storyStatus" value="<?= $draft_data ? htmlspecialchars($draft_data['status']) : 'published' ?>">
        <input type="hidden" name="content" id="hiddenContent">
        <input type="hidden" name="egg_content" id="hiddenEggContent">

        <div class="form-section">
            <!-- <div class="identity-badge"> -->
                <!-- 身份狀態：<?= $user['is_contracted'] ? '🌟 平台簽約作者（享有 20% 低抽成特權）' : '📝 一般創作者（享有創作變現，平台抽成 40%）' ?> -->
            <!-- </div> -->

            <div class="form-group" style="<?= $draft_data ? 'display:none;' : '' ?>">
              <label>投稿類型</label>
              <div class="story-select-grid">
                <div class="radio-label active" data-type="new">
                  <input type="radio" name="post_type" value="new" checked style="display:none;">
                  <span>🌱 創立全新作品</span>
                </div>
                <?php if (empty($my_stories)): ?>
                  <div class="radio-label" style="opacity: 0.5; cursor: not-allowed; background: #f5f5f5;" title="您目前尚未建立任何作品。">
                    <input type="radio" name="post_type" value="chapter" disabled style="display:none;">
                    <span style="color: var(--muted);">🔒 續寫已有作品 (尚無作品)</span>
                  </div>
                <?php else: ?>
                  <div class="radio-label" data-type="chapter">
                    <input type="radio" name="post_type" value="chapter" style="display:none;">
                    <span>📚 續寫已有作品</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php if($draft_data): ?>
                <input type="hidden" name="post_type" value="<?= ($draft_data['chapter_number'] == 1 && $draft_data['story_intro'] !== '（續寫章節）') ? 'new' : 'chapter' ?>">
                <input type="hidden" name="novel_type" value="<?= htmlspecialchars($draft_data['story_type']) ?>">
                <input type="hidden" name="story_id" value="<?= htmlspecialchars($draft_data['story_id']) ?>">
            <?php endif; ?>

            <div class="form-group" id="novelTypeBlock" style="<?= $draft_data ? 'display:none;' : '' ?>">
              <label>作品型態</label>
              <div class="story-select-grid">
                <div class="radio-label active" data-novel-type="serial">
                  <input type="radio" name="novel_type" value="serial" checked style="display:none;">
                  <span>📚 長篇連載 (有多章節)</span>
                </div>
                <div class="radio-label" data-novel-type="single">
                  <input type="radio" name="novel_type" value="single" style="display:none;">
                  <span>📄 短篇小說 (一發完)</span>
                </div>
              </div>
            </div>

            <div class="form-group" id="existingStoryBlock" style="display: none;">
              <label for="story_id">選擇要續寫的作品</label>
              <select name="story_id" id="story_id" class="input-control">
                <option value="">-- 請選擇您的作品 --</option>
                <?php foreach ($my_stories as $story): ?>
                  <option value="<?= $story['id'] ?>"><?= htmlspecialchars($story['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" id="finalChapterBlock" style="display: none; background: #fdf6ec; padding: 15px; border-radius: 8px; border: 1px solid #faecd8; margin-bottom: 20px;">
              <label class="checkbox-label" style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: bold; color: #e6a23c;">
                <input type="checkbox" name="is_final_chapter" value="1" style="transform: scale(1.2);"> 
                <span>🏁 這是本故事的「最終完結章章節」</span>
              </label>
              <div style="font-size: 13px; color: #909399; margin-top: 5px; padding-left: 24px;">
                勾選後，當此章節成功發表，整部作品的狀態將會自動變更為 <span style="color: #67c23a; font-weight: bold;">全面完結</span>！
              </div>
            </div>

            <?php 
               $show_cover_picker = true;
               if ($draft_data && $draft_data['chapter_number'] > 1) {
                   $show_cover_picker = false;
               }
               $current_cover_type = $draft_data['cover_type'] ?? 'none';
               $current_cover_image = $draft_data['cover_image'] ?? '';
            ?>
            <div class="form-group" id="storyCoverBlock" style="<?= $show_cover_picker ? '' : 'display:none;' ?>">
                <label>故事封面風格</label>
                <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                    <div id="coverPreview" class="cover-preview-box style-<?= $current_cover_type ?>" style="<?= ($current_cover_type === 'upload' && $current_cover_image) ? "background-image:url('{$current_cover_image}'); background-size:cover; background-position:center;" : '' ?>">
                        <span id="previewTitleText" class="preview-title"><?= $draft_data ? htmlspecialchars($draft_data['story_title']) : '故事標題預覽' ?></span>
                    </div>
                    
                    <div style="flex: 1; min-width: 280px;">
                        <div class="cover-options-grid">
                            <?php 
                                // 從資料庫撈出的現有風格，如果沒設定則預設為 none
                                $current_cover = $draft_data['cover_type'] ?? 'none'; 
                            ?>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="none" <?= $current_cover === 'none' ? 'checked' : '' ?> onclick="updateCoverPreview('none')">
                                <span>無封面</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="upload" <?= $current_cover === 'upload' ? 'checked' : '' ?> onclick="updateCoverPreview('upload')">
                                <span>自訂上傳</span>
                            </label>

                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style1" <?= $current_cover === 'style1' ? 'checked' : '' ?> onclick="updateCoverPreview('style1')">
                                <span>溫暖閱讀</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style2" <?= $current_cover === 'style2' ? 'checked' : '' ?> onclick="updateCoverPreview('style2')">
                                <span>靜謐森林</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style3" <?= $current_cover === 'style3' ? 'checked' : '' ?> onclick="updateCoverPreview('style3')">
                                <span>筆下故事</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style4" <?= $current_cover === 'style4' ? 'checked' : '' ?> onclick="updateCoverPreview('style4')">
                                <span>都市風格</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style5" <?= $current_cover === 'style5' ? 'checked' : '' ?> onclick="updateCoverPreview('style5')">
                                <span>撞色風格</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style6" <?= $current_cover === 'style6' ? 'checked' : '' ?> onclick="updateCoverPreview('style6')">
                                <span>日常風格</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style7" <?= $current_cover === 'style7' ? 'checked' : '' ?> onclick="updateCoverPreview('style7')">
                                <span>莫蘭迪抽象</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style8" <?= $current_cover === 'style8' ? 'checked' : '' ?> onclick="updateCoverPreview('style8')">
                                <span>靈感海洋</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style9" <?= $current_cover === 'style9' ? 'checked' : '' ?> onclick="updateCoverPreview('style9')">
                                <span>唯美星空</span>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="cover_type" value="style10" <?= $current_cover === 'style10' ? 'checked' : '' ?> onclick="updateCoverPreview('style10')">
                                <span>青春夕陽</span>
                            </label>
                        </div>
                        
                        <div id="uploadInputArea" style="margin-top: 15px; display: <?= $current_cover_type === 'upload' ? 'block' : 'none' ?>;">
                            <input type="file" name="custom_cover" id="customCoverInput" accept="image/*" class="input-control">
                            <?php if($current_cover_type === 'upload' && $current_cover_image): ?>
                                <div style="font-size:12px; color:var(--muted); margin-top:5px;">📋 目前已有上傳封面，若不修改免重新選取</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" id="storyTitleBlock" style="<?= ($draft_data && $draft_data['story_intro'] === '（續寫章節）') ? 'display:none;' : '' ?>">
              <label for="title">故事總標題</label>
              <input type="text" id="title" name="title" class="input-control" placeholder="請輸入故事的總名字，例如：深夜限時的時空照相館" value="<?= $draft_data ? htmlspecialchars($draft_data['story_title']) : '' ?>">
            </div>

            <div class="form-group" id="chapterTitleBlock" style="<?= ($draft_data && $draft_data['story_type'] === 'single') ? 'display:none;' : '' ?>">
              <label for="chapter_title">本章標題</label>
              <input type="text" id="chapter_title" name="chapter_title" class="input-control" placeholder="例如：第一話：最後一張全家福" value="<?= $draft_data ? htmlspecialchars($draft_data['title']) : '' ?>">
            </div>

            <div class="form-group" id="introGroup" style="<?= ($draft_data && $draft_data['story_intro'] === '（續寫章節）') ? 'display:none;' : '' ?>">
                <label>引文（一句話簡介）</label>
                <textarea class="input-control" id="summaryInput" name="intro" maxlength="100" placeholder="請輸入作品引文，用最美的一句話勾引讀者..." style="height: 75px; padding-right: 60px;"><?= $draft_data ? htmlspecialchars($draft_data['story_intro']) : '' ?></textarea>
                <div class="input-counter"><span id="introCount">0</span>/100</div>
            </div>

            <div class="form-group" id="tagGroup" style="<?= ($draft_data && $draft_data['story_intro'] === '（續寫章節）') ? 'display:none;' : '' ?>">
                <label>標籤（最多 5 個，輸入完按 Enter 新增）</label>
                <div class="tag-container" id="tagContainer">
                    <input type="text" id="tagInput" placeholder="自訂標籤">
                </div>
                <input type="hidden" name="tags" id="hiddenTags" value="<?= $draft_data ? htmlspecialchars($draft_data['story_tags']) : '' ?>">
                <div class="input-counter"><span id="tagCount">0</span>/5</div>
            </div>

            <div class="form-group">
                <label>故事內文</label>
                <div class="editor-wrapper">
                    <div class="editor-toolbar">
                        <button type="button" class="toolbar-btn" onclick="execMainCmd('bold')"><b>B</b></button>
                        <button type="button" class="toolbar-btn" onclick="execMainCmd('italic')"><i>I</i></button>
                        <button type="button" class="toolbar-btn" onclick="execMainCmd('underline')"><u>U</u></button>
                        <button type="button" class="toolbar-btn" onclick="execMainCmd('strikeThrough')"><s>S</s></button>
                    </div>
                    <div class="editor-content" id="editorContent" contenteditable="true" placeholder="在此咬一口故事，展開你的靈感世界..."><?= $draft_data ? $draft_data['content'] : '' ?></div>
                </div>
                <div class="input-counter" style="bottom: -24px;">
                    真實字數：<span id="contentWordCount" style="color:var(--primary); font-weight:bold;">0</span> 字
                    <span id="cheatWarning" style="color:red; margin-left:10px; display:none;">⚠️ 偵測到大量無效空白</span>
                </div>
            </div>

            <br><br>

            <div class="extra-panel">
                <?php $is_scheduled = ($draft_data && !empty($draft_data['publish_at'])) ? true : false; ?>
                <label class="checkbox-label">
                    <input type="checkbox" id="scheduleToggle" name="is_scheduled" style="transform: scale(1.1);" <?= $is_scheduled ? 'checked' : '' ?>>
                    <div style="margin-left: 6px;">
                        <strong>開啟預約自動發布時間</strong>
                        <div style="font-size: 13px; color: var(--muted); margin-top: 2px;">選定未來的時間，系統到時會自動對外公開</div>
                    </div>
                </label>
                <div class="extra-expanded" id="scheduleTimeArea" style="margin-bottom:15px; display: <?= $is_scheduled ? 'block' : 'none' ?>;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span>📅 選擇自動發布時間：</span>
                        <input type="datetime-local" class="input-control" name="publish_at" id="scheduledAtInput" style="width: 250px; padding: 8px 12px;" value="<?= $is_scheduled ? date('Y-m-d\TH:i', strtotime($draft_data['publish_at'])) : '' ?>">
                    </div>
                </div>

                <?php $has_egg = ($draft_data && !empty($draft_data['egg_content'])) ? true : false; ?>
                <label class="checkbox-label" style="border-top: 1px dashed var(--line); padding-top: 15px; margin-top: 15px;">
                    <input type="checkbox" id="extraToggle" name="has_extra" style="transform: scale(1.1);" <?= $has_egg ? 'checked' : '' ?>> 
                    <div style="margin-left: 6px;">
                        <strong>是否將此章節設置為故事彩蛋（收費機制）</strong>
                        <div style="font-size: 13px; color: var(--muted); margin-top: 2px;">
                            讀者需消耗甜甜圈點數解鎖。
                            <span style="color:#e67e22; font-weight:bold;">
                                <?= $user['is_contracted'] ? '💡 您是簽約作者，此彩蛋享有 20% 平台低抽成優惠！' : '💡 您目前是一般創作者，此彩蛋平台抽成 40% (簽約後降至 20%)' ?>
                            </span>
                        </div>
                    </div>
                </label>
                <div class="extra-expanded" id="extraPriceArea" style="display: <?= $has_egg ? 'block' : 'none' ?>;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                        <span>🔑 解鎖所需點數：</span>
                        <input type="number" class="input-control" id="unlockPointsInput" name="price_donuts" value="<?= $draft_data ? intval($draft_data['price_donuts']) : '0' ?>" min="0" style="width: 100px; padding: 8px 12px;">
                        <span style="font-weight: bold; color: #D6557A;">🍩 甜甜圈</span>
                        <span id="recommendTip" style="font-size:13px; color:var(--accent); font-weight:bold;"></span>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label style="color: #D6557A; font-weight: bold;">🔒 專屬彩蛋內文 / 獨家後記（付費解鎖後讀者才看得到）</label>
                        <div class="editor-wrapper" style="border-color: #D6557A;">
                            <div class="editor-toolbar" style="background: #fff5f7;">
                                <button type="button" class="toolbar-btn" onclick="execEggCmd('bold')"><b>B</b></button>
                                <button type="button" class="toolbar-btn" onclick="execEggCmd('italic')"><i>I</i></button>
                                <button type="button" class="toolbar-btn" onclick="execEggCmd('underline')"><u>U</u></button>
                            </div>
                            <div class="editor-content" id="eggEditorContent" contenteditable="true" placeholder="在此寫入隱藏結局、番外或給讀者的悄悄話..." style="min-height: 120px; outline:none; background:#fff;"><?= $draft_data ? $draft_data['egg_content'] : '' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="button" class="btn-draft" id="saveDraftBtn" onclick="submitForm('draft')">💾 儲存至草稿箱</button>
            <button type="button" class="btn-submit" id="submitPublishBtn" onclick="submitForm('published')">🚀 確認發布投稿</button>
        </div>
    </form>
</main>

<div class="modal-overlay" id="userModal" onclick="closeUserModal(event)"></div>

<div id="alertToast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100px); color: white; padding: 15px 30px; border-radius: 50px; font-weight: bold; font-size: 16px; z-index: 9999; display: flex; align-items: center; gap: 10px; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); opacity: 0; box-shadow: 0 5px 20px rgba(0,0,0,0.15);">
    <span id="toastIcon"></span><span id="toastText"></span>
</div>

<script>
function submitForm(status) {
    // 1. 動態將狀態（draft 或 published）寫入你的隱藏欄位 <input id="storyStatus">
    const statusInput = document.getElementById('storyStatus');
    if (statusInput) {
        statusInput.value = status;
    }
    
    // 2. 獲取你的 contenteditable 編輯器內容，並同步塞入隱藏欄位，後端才收得到文內！
    const mainEditor = document.getElementById('editorContent');
    const eggEditor = document.getElementById('eggEditorContent');
    
    if (mainEditor) {
        document.getElementById('hiddenContent').value = mainEditor.innerHTML;
    }
    if (eggEditor) {
        document.getElementById('hiddenEggContent').value = eggEditor.innerHTML;
    }

    // 3. 基本防空驗證（只在正式發布時嚴格限制）
    if (status === 'published') {
        const textContent = mainEditor ? mainEditor.textContent.trim() : '';
        if (textContent === '') {
            alert('寫點什麼吧！小說正文不能為空喔 ✍️');
            return;
        }
    }
    
    // 4. 通過驗證，強制提交表單
    const form = document.getElementById('postForm');
    if (form) {
        form.submit();
    } else {
        alert('找不到表單物件，請確認 <form id="postForm"> 的 ID 是否正確！');
    }
}
</script>

<div class="modal-overlay" id="userModal" onclick="closeUserModal(event)"></div>

<div id="alertToast" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100px); color: white; padding: 15px 30px; border-radius: 50px; font-weight: bold; font-size: 16px; z-index: 9999; display: flex; align-items: center; gap: 10px; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); opacity: 0; box-shadow: 0 5px 20px rgba(0,0,0,0.15);">
    <span id="toastIcon"></span><span id="toastText"></span>
</div>

<script>
// 🎨 全域 JS 預覽邏輯（放在 DOM 載入前，確保 HTML 上的 onclick 能正常觸發）
function updateCoverPreview(type) {
    const preview = document.getElementById('coverPreview');
    const uploadArea = document.getElementById('uploadInputArea');
    if(!preview) return;
    
    // 1. 徹底重置：移除現有所有風格樣式 class 
    preview.className = 'cover-preview-box';
    // 2. 清除之前可能殘留的行內背景屬性，交給 CSS 決定
    preview.style.backgroundImage = '';
    preview.style.backgroundColor = '';
    
    if (type === 'none') {
        preview.classList.add('style-none');
        if(uploadArea) uploadArea.style.display = 'none';
    } else if (type === 'upload') {
        preview.classList.add('style-upload');
        if(uploadArea) uploadArea.style.display = 'block';
        
        // 如果原本已經有自訂上傳圖，就保留預覽，否則給預設灰色底
        const customCoverInput = document.getElementById('customCoverInput');
        if(!preview.style.backgroundImage || preview.style.backgroundImage === 'none') {
            preview.style.backgroundColor = '#f2f2f2';
        }
    } else {
        // 自動校正：防止傳入 'style-style3' 或 'style3' 造成錯誤
        let styleClass = type;
        if (!styleClass.startsWith('style-')) {
            if (styleClass.startsWith('style')) {
                styleClass = 'style-' + styleClass; // 將 style3 轉為 style-style3
            } else {
                styleClass = 'style-style' + styleClass; // 將 3 轉為 style-style3
            }
        }
        
        preview.classList.add(styleClass);
        if(uploadArea) uploadArea.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const postTypeRadioLabels = document.querySelectorAll('[data-type]');
    const novelTypeRadioLabels = document.querySelectorAll('[data-novel-type]');
    const novelTypeBlock = document.getElementById('novelTypeBlock');
    const existingStoryBlock = document.getElementById('existingStoryBlock');
    const finalChapterBlock = document.getElementById('finalChapterBlock'); 
    const storyTitleBlock = document.getElementById('storyTitleBlock');
    const chapterTitleBlock = document.getElementById('chapterTitleBlock');
    const storyCoverBlock = document.getElementById('storyCoverBlock');
    const editor = document.getElementById('editorContent');
    const wordCountSpan = document.getElementById('contentWordCount');
    const recommendTip = document.getElementById('recommendTip');
    const unlockPointsInput = document.getElementById('unlockPointsInput');
    const postForm = document.getElementById('postForm');
    const storyStatusInput = document.getElementById('storyStatus');
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    
    // 監聽標題輸入，動態同步到封面預覽上
    const titleInput = document.getElementById('title');
    const previewTitleText = document.getElementById('previewTitleText');
    if(titleInput && previewTitleText) {
        titleInput.addEventListener('input', function() {
            previewTitleText.textContent = this.value.trim() || '故事標題預覽';
        });
    }

    // 監聽本地圖片上傳，即時預覽
    const customCoverInput = document.getElementById('customCoverInput');
    if(customCoverInput) {
        customCoverInput.addEventListener('change', function() {
            const file = this.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('coverPreview');
                    if(preview) {
                        preview.style.backgroundImage = `url('${e.target.result}')`;
                        preview.style.backgroundSize = 'cover';
                        preview.style.backgroundPosition = 'center';
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    }

    postTypeRadioLabels.forEach(label => {
        label.addEventListener('click', function() {
            postTypeRadioLabels.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;

            const introGroup = document.getElementById('introGroup'); 
            const tagGroup = document.getElementById('tagGroup');     

            if (radio.value === 'new') {
                if(novelTypeBlock) novelTypeBlock.style.display = 'block';
                if(storyTitleBlock) storyTitleBlock.style.display = 'block';
                if(storyCoverBlock) storyCoverBlock.style.display = 'block'; 
                if(introGroup) introGroup.style.display = 'block';
                if(tagGroup) tagGroup.style.display = 'block';
                if(finalChapterBlock) finalChapterBlock.style.display = 'none'; 
                checkNovelType(); 
            } else {
                if(novelTypeBlock) novelTypeBlock.style.display = 'none';
                if(storyTitleBlock) storyTitleBlock.style.display = 'none';
                if(storyCoverBlock) storyCoverBlock.style.display = 'none'; 
                if(introGroup) introGroup.style.display = 'none';
                if(tagGroup) tagGroup.style.display = 'none';
                if(existingStoryBlock) existingStoryBlock.style.display = 'block';
                if(chapterTitleBlock) chapterTitleBlock.style.display = 'block';
                if(finalChapterBlock) finalChapterBlock.style.display = 'block'; 
            }
        });
    });

    novelTypeRadioLabels.forEach(label => {
        label.addEventListener('click', function() {
            novelTypeRadioLabels.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            checkNovelType();
        });
    });

    function checkNovelType() {
        const currentPostTypeOpt = document.querySelector('input[name="post_type"]:checked');
        if (!currentPostTypeOpt || currentPostTypeOpt.value !== 'new') return;
        if(existingStoryBlock) existingStoryBlock.style.display = 'none';
        
        const currentNovelTypeOpt = document.querySelector('input[name="novel_type"]:checked');
        if (currentNovelTypeOpt) {
            if (currentNovelTypeOpt.value === 'single') {
                if(chapterTitleBlock) chapterTitleBlock.style.display = 'none';
            } else {
                if(chapterTitleBlock) chapterTitleBlock.style.display = 'block';
            }
        }
    }

    function getCleanWordCount(htmlContent, textContent) {
        let cleanText = textContent || htmlContent.replace(/<[^>]*>/g, '');
        const cheatWarning = document.getElementById('cheatWarning');
        if (/ {6,}/.test(cleanText) || /\n{5,}/.test(cleanText)) {
            if(cheatWarning) cheatWarning.style.display = 'inline';
        } else {
            if(cheatWarning) cheatWarning.style.display = 'none';
        }
        let cleaned = cleanText.trim().replace(/\s+/g, ' ');
        if (cleaned === '') return 0;
        const chineseMatches = cleaned.match(/[\u4e00-\u9fa5]/g) || [];
        const englishMatches = cleaned.match(/\b[a-zA-Z0-9-']+\b/g) || [];
        return chineseMatches.length + englishMatches.length;
    }

    if (editor && wordCountSpan) {
        editor.addEventListener('input', () => {
            const realCount = getCleanWordCount(editor.innerHTML, editor.innerText);
            wordCountSpan.textContent = realCount;

            const extraToggle = document.getElementById('extraToggle');

            if (recommendTip && extraToggle && extraToggle.checked) {
                let recommendedPoints = 0;
                if (realCount > 0 && realCount < 1000) recommendedPoints = 10;
                else if (realCount >= 1000 && realCount <= 2500) recommendedPoints = 20;
                else if (realCount > 2500) recommendedPoints = 30;

                if (recommendedPoints > 0) {
                    recommendTip.textContent = `（💡 系統依據您目前的 ${realCount} 字，推薦設置為 ${recommendedPoints} 點）`;
                    if (unlockPointsInput && (unlockPointsInput.value == "0" || unlockPointsInput.value == "")) {
                        unlockPointsInput.value = recommendedPoints;
                    }
                } else {
                    recommendTip.textContent = '';
                }
            } else {
                if (recommendTip) recommendTip.textContent = '';
            }
        });
        editor.dispatchEvent(new Event('input'));
    }

    function setupCounter(inputId, countId) {
        const el = document.getElementById(inputId);
        const cnt = document.getElementById(countId);
        if(el && cnt) {
            el.addEventListener('input', () => cnt.textContent = el.value.length);
            cnt.textContent = el.value.length; 
        }
    }
    setupCounter('title', 'titleCount'); 
    setupCounter('summaryInput', 'introCount'); 

    const scheduleToggle = document.getElementById('scheduleToggle');
    const scheduleTimeArea = document.getElementById('scheduleTimeArea');
    if(scheduleToggle && scheduleTimeArea) {
        scheduleToggle.addEventListener('change', function() {
            scheduleTimeArea.style.display = this.checked ? 'block' : 'none';
            if(storyStatusInput && this.checked) {
                storyStatusInput.value = 'scheduled';
            } else if (storyStatusInput && !this.checked) {
                storyStatusInput.value = 'published';
            }
        });
    }

    const extraToggle = document.getElementById('extraToggle');
    const extraPriceArea = document.getElementById('extraPriceArea');
    if(extraToggle && extraPriceArea) {
        extraToggle.addEventListener('change', function() {
            extraPriceArea.style.display = this.checked ? 'block' : 'none';
            if (editor) editor.dispatchEvent(new Event('input'));
        });
    }

    // 處理標籤邏輯
    const tagInput = document.getElementById('tagInput');
    const tagContainer = document.getElementById('tagContainer');
    const hiddenTags = document.getElementById('hiddenTags');
    const tagCount = document.getElementById('tagCount');
    let tagsArr = [];

    if (hiddenTags && hiddenTags.value.trim() !== '') {
        tagsArr = hiddenTags.value.split(',').map(t => t.trim()).filter(t => t !== '');
    }

    function renderTags() {
        if(!tagContainer || !tagInput) return;
        document.querySelectorAll('.tag-badge').forEach(el => el.remove());
        tagsArr.forEach((tag, idx) => {
            const badge = document.createElement('span');
            badge.className = 'tag-badge';
            badge.innerHTML = `${escapeHtml(tag)} <span class="tag-remove" onclick="removeTag(${idx})">&times;</span>`;
            tagContainer.insertBefore(badge, tagInput);
        });
        if(hiddenTags) hiddenTags.value = tagsArr.join(',');
        if(tagCount) tagCount.textContent = tagsArr.length;
    }

    window.removeTag = function(idx) {
        tagsArr.splice(idx, 1);
        renderTags();
    }

    if(tagInput) {
        tagInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim().replace(/,/g, '');
                if (val !== '' && !tagsArr.includes(val) && tagsArr.length < 5) {
                    tagsArr.push(val);
                    renderTags();
                    this.value = '';
                }
            }
        });
    }
    renderTags();

    // 處理草稿與提交按鈕
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function() {
            if(storyStatusInput) storyStatusInput.value = 'draft';
            submitFormAction();
        });
    }

    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const currentStatus = storyStatusInput ? storyStatusInput.value : 'published';
            if (currentStatus !== 'draft') {
                if(scheduleToggle && scheduleToggle.checked) {
                    if(storyStatusInput) storyStatusInput.value = 'scheduled';
                } else {
                    if(storyStatusInput) storyStatusInput.value = 'published';
                }
            }
            submitFormAction();
        });
    }

    function submitFormAction() {
        const hiddenContent = document.getElementById('hiddenContent');
        const mainEditor = document.getElementById('editorContent');
        if(hiddenContent && mainEditor) {
            hiddenContent.value = mainEditor.innerHTML;
        }

        const hiddenEgg = document.getElementById('hiddenEggContent');
        const eggEditor = document.getElementById('eggEditorContent');
        if(hiddenEgg && eggEditor) {
            hiddenEgg.value = eggEditor.innerHTML;
        }

        postForm.submit();
    }
});

// 工具類編輯器指令
function execMainCmd(cmd) { document.execCommand(cmd, false, null); }
function execEggCmd(cmd) { document.execCommand(cmd, false, null); }
function toggleMobileSheet(show) {
    const sheet = document.getElementById('mobileSheet');
    if(sheet) sheet.classList.toggle('open', show);
}
function openUserModal() {
    const overlay = document.getElementById('userModal');
    if(overlay) overlay.style.display = 'block';
}
function closeUserModal(e) {
    const overlay = document.getElementById('userModal');
    if(overlay) overlay.style.display = 'none';
}
function escapeHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

</body>
</html>