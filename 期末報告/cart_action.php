<?php
// cart_action.php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<script>alert('請先登入會員！'); location.href='login.php';</script>";
    exit;
}

$action = $_POST['action'] ?? '';

// ==================== 1. 加入購物車 ====================
if ($action === 'add') {
    $book_id = intval($_POST['shop_book_id'] ?? 0);

    // 檢查該電子書是否存在且上架 (注意：配合你的 shop.php，這裡改回 'active' 字串比較安全)
    $stmt = $pdo->prepare("SELECT id FROM books WHERE id = ? AND (is_active = 'active' OR is_active = 1)");
    $stmt->execute([$book_id]);
    if (!$stmt->fetch()) {
        echo "<script>alert('該書籍不存在或已下架！'); history.back();</script>";
        exit;
    }

    // 檢查是否已經購買過這本書，若買過則不需要再加購物車
    $stmtCheckBought = $pdo->prepare("SELECT 1 FROM user_shop_shelf WHERE user_id = ? AND shop_book_id = ?");
    $stmtCheckBought->execute([$user_id, $book_id]);
    if ($stmtCheckBought->fetch()) {
        echo "<script>alert('您已經擁有此書籍，無需重複購買！'); location.href='shop.php';</script>";
        exit;
    }

    // 純電子書模式，數量固定為 1
    $stmtCart = $pdo->prepare("INSERT INTO cart (user_id, shop_book_id, quantity) VALUES (?, ?, 1) 
                               ON DUPLICATE KEY UPDATE quantity = 1");
    if ($stmtCart->execute([$user_id, $book_id])) {
        echo "<script>alert('已成功加入購物車！'); location.href='shop.php';</script>";
    } else {
        echo "<script>alert('加入失敗，請稍後再試'); history.back();</script>";
    }
    exit;
}

// ==================== 2. 從購物車刪除 ====================
if ($action === 'delete') {
    $cart_id = intval($_POST['cart_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $user_id]);
    header("Location: cart.php");
    exit;
}

// ==================== ✨ 3. 購物車結帳（扣麥穗並寫入已購書架） ====================
if ($action === 'checkout') {
    try {
        // A. 啟動資料庫交易模式 (Transaction)，確保扣款與寫入書架同時成功或失敗
        $pdo->beginTransaction();

        // B. 撈取目前購物車內的所有商品與價格
        $stmt_cart = $pdo->prepare("
            SELECT c.shop_book_id, b.price, b.title 
            FROM cart c
            JOIN books b ON c.shop_book_id = b.id
            WHERE c.user_id = ?
        ");
        $stmt_cart->execute([$user_id]);
        $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cart_items)) {
            echo "<script>alert('您的購物車是空的！'); location.href='cart.php';</script>";
            exit;
        }

        // C. 計算總金額
        $total_price = 0;
        foreach ($cart_items as $item) {
            $total_price += intval($item['price']);
        }

        // D. 檢查使用者的麥穗餘額是否足夠
        $stmt_user = $pdo->prepare("SELECT wheat FROM users WHERE id = ? FOR UPDATE");
        $stmt_user->execute([$user_id]);
        $user_wheat = intval($stmt_user->fetchColumn());

        if ($user_wheat < $total_price) {
            echo "<script>alert('您的麥穗不足！總共需要 🌾 {$total_price}，您目前只有 🌾 {$user_wheat}'); location.href='cart.php';</script>";
            $pdo->rollBack();
            exit;
        }

        // E. 扣除使用者麥穗
        $stmt_deduct = $pdo->prepare("UPDATE users SET wheat = wheat - ? WHERE id = ?");
        $stmt_deduct->execute([$total_price, $user_id]);

        // F. 🔥 重點：將購買的書籍逐一寫入 user_shop_shelf 資料表
        $stmt_shelf = $pdo->prepare("
            INSERT INTO user_shop_shelf (user_id, shop_book_id, created_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        
        foreach ($cart_items as $item) {
            $stmt_shelf->execute([$user_id, $item['shop_book_id']]);
        }

        // G. 清空該使用者的購物車
        $stmt_clear_cart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt_clear_cart->execute([$user_id]);

        // H. 提交交易
        $pdo->commit();

        echo "<script>alert('結帳成功！已扣除 🌾 {$total_price} 麥穗，書籍已加入您的書架！'); location.href='bookshelf.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack(); // 發生錯誤時全部回滾
        echo "<script>alert('結帳失敗，系統錯誤： " . addslashes($e->getMessage()) . "'); location.href='cart.php';</script>";
    }
    exit;
}