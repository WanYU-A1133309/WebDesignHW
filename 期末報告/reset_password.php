<?php
// reset_password.php - 重設新密碼頁面
session_start();
require_once 'db.php'; // 引入資料庫連線

$msg = "";
$msg_type = "";
$show_form = false; // 是否顯示重設密碼的表單
$token = "";

// 🎯 階段一：檢查網址上有沒有帶 Token
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        // 去資料庫搜尋有沒有使用者的 reset_token 符合這個 token
        // 💡 實務提醒：請確保你的 users 資料表確實有 `reset_token` 這個欄位
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token IS NOT NULL");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $show_form = true; // 找到了！允許顯示修改密碼表單
        } else {
            $msg = "此重設連結已失效或不正確，請重新申請。";
            $msg_type = "error";
        }
    } catch (PDOException $e) {
        $msg = "系統錯誤，請稍後再試";
        $msg_type = "error";
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 如果既沒有 GET token，也不是 POST 送出表單，就是非法直接闖入
    $msg = "無效的訪問請求。";
    $msg_type = "error";
}

// 🎯 階段二：當使用者填完新密碼，按下「確認修改」時 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($password) || empty($password_confirm)) {
        $msg = "請完整填寫新密碼與確認密碼！";
        $msg_type = "error";
        $show_form = true; // 繼續顯示表單讓使用者填寫
    } else if ($password !== $password_confirm) {
        $msg = "兩次輸入的密碼不相同，請重新確認。";
        $msg_type = "error";
        $show_form = true;
    } else if (strlen($password) < 6) { // 簡單的安全檢查
        $msg = "為了密碼安全，新密碼長度請至少輸入 6 個字元。";
        $msg_type = "error";
        $show_form = true;
    } else {
        try {
            // 再次安全確認該 Token 的合法性
            $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                // 🔒 密碼加密：使用 PHP 官方最高規格安全加密
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 更新資料庫：寫入新密碼，並將 reset_token 清空 (設為 NULL) 以防連結被重複使用
                $update_stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user['id']]);

                // 修改成功！直接用 JavaScript 跳轉或給予成功提示
                echo "<script>alert('密碼重設成功！請使用新密碼重新登入。'); window.location.href='login.php';</script>";
                exit;

            } else {
                $msg = "驗證逾時或無效，請重新申請。";
                $msg_type = "error";
            }
        } catch (PDOException $e) {
            $msg = "系統變更失敗，請稍後再試。";
            $msg_type = "error";
            $show_form = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="utf-8">
    <title>重設密碼 - 咬一口故事</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style-auth.css">
</head>
<body>
    <div class="custom-hero">
        <div class="hero-content">
            <h1>
                咬一口<span class="bite-text">故事</span>
                <br>
                尋回靈魂的密鑰
            </h1>

            <?php if (!empty($msg)): ?>
                <?php if ($msg_type === "success"): ?>
                    <div class="success-msg"><?php echo $msg; ?></div>
                <?php else: ?>
                    <div class="error-msg"><?php echo $msg; ?></div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="form-container">
                <div class="form-title">設定新密碼</div>
                
                <?php if ($show_form): ?>
                    <div class="form-subtitle">請為您的帳號設定一組全新且安全的密碼。</div>

                    <form action="reset_password.php" method="POST" class="form-content">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="input-group">
                            <label for="new-password">新密碼</label>
                            <input type="password" id="new-password" name="password" required placeholder="請輸入至少 6 位數新密碼">
                        </div>

                        <div class="input-group">
                            <label for="confirm-password">確認新密碼</label>
                            <input type="password" id="confirm-password" name="password_confirm" required placeholder="請再次輸入新密碼">
                        </div>

                        <button type="submit" class="submit-btn">儲存新密碼並登入</button>
                    </form>

                <?php else: ?>
                    <div class="form-subtitle" style="color: #9A8675; margin-bottom: 24px;">
                        很抱歉，此連結可能已經被使用過，或是您的驗證網址不完整。
                    </div>
                    <div class="form-links" style="text-align: center;">
                        <a href="forgot_password.php" class="submit-btn" style="display: block; text-align: center; line-height: 44px; text-decoration: none; color: #fff;">重新申請重設郵件</a>
                    </div>
                <?php endif; ?>

                <div class="form-links" style="margin-top: 20px;">
                    <a href="login.php">返回登入頁面</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>