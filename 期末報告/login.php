<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 取得登入錯誤或註冊成功的提示訊息
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
$back_url = isset($_REQUEST['back_url']) ? $_REQUEST['back_url'] : "index.php";

// 當表單送出登入請求時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $msg = "請輸入帳號與密碼！";
    } else {
        try {
            // 用使用者填寫的帳號去追查資料庫
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✨ 修正：有這個帳號，且安全解密密碼正確
            if ($user && password_verify($password, $user['password'])) {
                
                // 登入成功，將使用者資訊存入 Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nickname'] = $user['nickname'] ?? $user['username'];
                $_SESSION['wheat'] = $user['wheat'] ?? 0; // 同步你剛做好的麥穗功能

                if ($user['role'] === 'admin') {
                    // 👑 狀況 A：如果登入的人是管理員，直接送進後台管理系統辦公
                    header("Location: admin.php");
                    exit;
                } else {
                    // 👤 狀況 B：如果是一般使用者，回到當前想看的頁面（或首頁）
                    $back_url = $_GET['back_url'] ?? $_POST['back_url'] ?? 'index.php';
                    
                    // 防禦型安全檢查：避免 back_url 被串改導向惡意外部網站
                    if (strpos($back_url, 'http') === 0) {
                        $back_url = 'index.php';
                    }
                    
                    header("Location: " . $back_url);
                    exit;
                }
            } else {
                // 💡 括號對齊修正：這裡現在能正確對應上面的 if 了
                $msg = "帳號或密碼錯誤！";
            }
        } catch (PDOException $e) {
            $msg = "系統錯誤，請稍後再試";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="utf-8">
    <title>登入 - 咬一口故事</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style-auth.css">
</head>
<body>
    <div class="custom-hero">
        <div class="hero-content">
            <h1>
                咬一口<span class="bite-text">故事</span>
                <br>
                遇見靈魂的共鳴
            </h1>

            <?php if (!empty($msg)): ?>
                <div class="error-msg" style="background-color: rgba(74, 60, 49, 0.08); border-color: #4a3c31; color: #4a3c31;">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <div class="form-title">即刻登入探索萬卷文字世界</div>

                <form action="" method="POST" class="form-content">
                    
                    <input type="hidden" name="back_url" value="<?php echo htmlspecialchars($back_url); ?>">

                    <div class="input-group">
                        <label for="login-username">帳號 (ID)</label>
                        <input type="text" id="login-username" name="username" required placeholder="請輸入您的帳號 (ID)">
                    </div>
                    <div class="input-group">
                        <label for="login-password">密碼</label>
                        <div class="password-wrapper">
                            <input type="password" id="login-password" name="password" required placeholder="請輸入密碼">
                            <span class="toggle-password slash" onclick="togglePasswordVisibility('login-password', this)">
                                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>

                    <div class="options-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="keep_logged_in" value="1"> 保持登入狀態
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="save_id" value="1"> 儲存ID
                        </label>
                    </div>
                    <button type="submit" class="submit-btn">登入</button>
                    
                    <div class="form-links">
                        <a href="forgot_password.php">忘記密碼</a>
                        |
                        <a href="register.php">會員註冊</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="js/auth-validation.js"></script>
</body>
</html>