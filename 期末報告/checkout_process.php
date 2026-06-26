<?php
// checkout_process.php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. 撈取購物車中商品
    $stmt = $pdo->prepare("
        SELECT c.id AS cart_id, b.id AS book_id, b.title, b.price AS wheat_price, b.is_active
        FROM cart c 
        JOIN books b ON c.shop_book_id = b.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("你的購物車是空的！");
    }

    $total_wheat_needed = 0;
    foreach ($cart_items as $item) {
        if ($item['is_active'] != 1) {
            throw new Exception("《" . $item['title'] . "》已下架，無法購買。");
        }
        $total_wheat_needed += $item['wheat_price'];
    }

    // 2. 檢查使用者的麥穗是否足夠
    //  完美相容的寫法：
    $user_stmt = $pdo->prepare("SELECT wheat FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // 如果有撈到資料就取 wheat 欄位，沒有的話就給 0
    $user_wheat = $user_data ? intval($user_data['wheat']) : 0;

    if ($user_wheat < $total_wheat_needed) {
        throw new Exception("你的麥穗不足！還差 " . ($total_wheat_needed - $user_wheat) . " 粒麥穗。");
    }

    // 3. 扣除使用者麥穗並同步更新 Session
    $deduct_stmt = $pdo->prepare("UPDATE users SET wheat = wheat - ? WHERE id = ?");
    $deduct_stmt->execute([$total_wheat_needed, $user_id]);
    $_SESSION['wheat'] = $user_wheat - $total_wheat_needed;

    // 4. 建立訂單主檔與明細
    $order_no = 'WHEAT' . date('YmdHis') . rand(1000, 9999);
    $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, order_no, total_wheat, status) VALUES (?, ?, ?, 'paid')");
    $order_stmt->execute([$user_id, $order_no, $total_wheat_needed]);
    $order_id = $pdo->lastInsertId();

    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, shop_book_id, book_title, wheat_price, quantity) VALUES (?, ?, ?, ?, 1)");
    
    // 💡 電子書核心：自動塞入官方專屬書架
    $shelf_stmt = $pdo->prepare("INSERT INTO user_shop_shelf (user_id, shop_book_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE purchased_at = CURRENT_TIMESTAMP()");

    foreach ($cart_items as $item) {
        $item_stmt->execute([$order_id, $item['book_id'], $item['title'], $item['wheat_price']]);
        $shelf_stmt->execute([$user_id, $item['book_id']]);
    }

    // 5. 清空購物車
    $clear_cart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear_cart->execute([$user_id]);

    $pdo->commit();
    echo "<script>alert('🎉 購買成功！已扣除 {$total_wheat_needed} 麥穗，書籍已放入您的書櫃！'); location.href='cart.php';</script>";
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<script>alert('❌ 購買失敗：{$e->getMessage()}'); location.href='cart.php';</script>";
    exit;
}