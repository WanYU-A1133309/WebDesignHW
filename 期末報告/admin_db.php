<?php
// admin_db.php
session_start();
require_once 'db.php';

// 安全檢查：未登入，或者角色不是管理員，直接踢回首頁
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 撈取目前使用者的角色
$check_stmt = $pdo->prepare("SELECT role, username, nickname FROM users WHERE id = ?");
$check_stmt->execute([$_SESSION['user_id']]);
$current_admin = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_admin || $current_admin['role'] !== 'admin') {
    die("<h1 style='text-align:center;margin-top:100px;color:#d6557a;'>您的帳號沒有存取管理後台的權限！</h1>");
}

$admin_display_name = !empty($current_admin['nickname']) ? $current_admin['nickname'] : $current_admin['username'];