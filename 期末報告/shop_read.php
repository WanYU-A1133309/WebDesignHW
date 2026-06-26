<?php
// shop_read.php - 官方精品電子書 線上閱讀頁
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo "<script>alert('請先登入會員！'); location.href='login.php';</script>";
    exit;
}

$book_id = intval($_GET['book_id'] ?? 0);
$ch      = max(1, intval($_GET['ch'] ?? 1)); // 第幾章，預設第 1 章

if ($book_id <= 0) {
    die("📚 無效的書籍編號。");
}

// 🔒 核心防護：確認這個使用者真的買過這本書，沒買過就不准看
$own_stmt = $pdo->prepare("SELECT 1 FROM user_shop_shelf WHERE user_id = ? AND shop_book_id = ?");
$own_stmt->execute([$user_id, $book_id]);
if (!$own_stmt->fetch()) {
    echo "<script>alert('⚠️ 您尚未購買此書籍，無法閱讀！'); location.href='shop.php';</script>";
    exit;
}

// 撈取書籍基本資料
$book_stmt = $pdo->prepare("SELECT id, title, author, cover, tag FROM books WHERE id = ?");
$book_stmt->execute([$book_id]);
$book = $book_stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    die("📚 抱歉，找不到該本電子書。");
}

// 撈取這本書全部章節（依章節順序）
$chap_stmt = $pdo->prepare("SELECT * FROM shop_book_chapters WHERE shop_book_id = ? ORDER BY chapter_number ASC, id ASC");
$chap_stmt->execute([$book_id]);
$chapters = $chap_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_chapters  = count($chapters);
$current_index   = $ch - 1; // 陣列索引從 0 開始
$current_chapter = $chapters[$current_index] ?? null;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($book['title']) ?> ‧ 線上閱讀</title>
    <link rel="stylesheet" href="css/create.css">
    <style>
        .read-wrap {
            max-width: 760px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .read-panel {
            background: var(--bg-1, #ffffff);
            border: 1px solid var(--line, #e2e8f0);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(74,60,49,0.04);
        }
        .book-header {
            border-bottom: 1px solid var(--line, #e2e8f0);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .book-header h1 {
            margin: 0 0 6px 0;
            font-size: 24px;
            color: var(--primary, #5A483C);
        }
        .book-header .meta {
            font-size: 13px;
            color: var(--muted, #718096);
        }
        .chapter-title {
            font-size: 19px;
            font-weight: 800;
            color: var(--primary, #5A483C);
            margin-bottom: 18px;
        }
        .chapter-content {
            font-size: 17px;
            line-height: 2.1;
            color: #2d2014;
            white-space: pre-wrap;
        }
        .empty-content {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted, #718096);
        }
        .chapter-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--line, #e2e8f0);
        }
        .chapter-nav a, .chapter-nav span {
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }
        .chapter-nav a {
            background: var(--primary, #5A483C);
            color: #fff;
        }
        .chapter-nav .disabled {
            background: #f0f0f0;
            color: #bbb;
        }
        .chapter-select {
            text-align: center;
            margin-bottom: 20px;
        }
        .chapter-select select {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--line, #e2e8f0);
            font-size: 14px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--primary, #5A483C);
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body style="background:#FBF7EF;">

<div class="read-wrap">
    <a href="bookshelf.php?tab=shop" class="back-link">← 返回我的書架</a>

    <div class="read-panel">
        <div class="book-header">
            <h1><?= htmlspecialchars($book['title']) ?></h1>
            <div class="meta">
                ✍️ 作者：<?= htmlspecialchars($book['author']) ?>　|
                🏷️ <?= htmlspecialchars($book['tag']) ?>　|
                📖 共 <?= $total_chapters ?> 章
            </div>
        </div>

        <?php if ($total_chapters > 0): ?>
            <div class="chapter-select">
                <select onchange="location.href='shop_read.php?book_id=<?= $book_id ?>&ch=' + this.value">
                    <?php foreach ($chapters as $idx => $c): ?>
                        <option value="<?= $idx + 1 ?>" <?= ($idx === $current_index) ? 'selected' : '' ?>>
                            第 <?= htmlspecialchars($c['chapter_number']) ?> 章：<?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($current_chapter): ?>
            <div class="chapter-title">
                第 <?= htmlspecialchars($current_chapter['chapter_number']) ?> 章：<?= htmlspecialchars($current_chapter['title']) ?>
            </div>
            <div class="chapter-content"><?= htmlspecialchars($current_chapter['content']) ?></div>

            <div class="chapter-nav">
                <?php if ($current_index > 0): ?>
                    <a href="shop_read.php?book_id=<?= $book_id ?>&ch=<?= $ch - 1 ?>">← 上一章</a>
                <?php else: ?>
                    <span class="disabled">← 上一章</span>
                <?php endif; ?>

                <?php if ($current_index < $total_chapters - 1): ?>
                    <a href="shop_read.php?book_id=<?= $book_id ?>&ch=<?= $ch + 1 ?>">下一章 →</a>
                <?php else: ?>
                    <span class="disabled">下一章 →</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-content">
                <p style="font-size:48px;">📖</p>
                <p>這本書目前還沒有上架任何章節內容，敬請期待！</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>