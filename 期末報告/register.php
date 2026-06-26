<!-- 註冊頁 -->
<?php
session_start();
require_once 'db.php'; // 引入剛才寫好的資料庫連線

// 安全檢查：如果已經登入，直接帶去首頁
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$msg = ""; // 儲存後端錯誤口信

// 當使用者在下方按下註冊按鈕時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']?? '');
    $nickname = trim($_POST['nickname']?? ''); // 接收創作者暱稱
    $email = trim($_POST['email']?? '');
    $password = $_POST['password']?? '';
    $confirm_password = $_POST['confirm_password']?? '';

    // 後端加固檢查（防止有心人繞過 JS）
    if (empty($username) || empty($nickname) || empty($email) || empty($password)) {
        $msg = "所有欄位皆為必填！";
    } elseif ($password !== $confirm_password) {
        $msg = "兩次輸入的密碼不一致！";
    } else {
        try {
            // 同時檢查 帳號(username)、Email、以及「暱稱(nickname)」有沒有跟別人重複
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR nickname = ?");
            $stmt->execute([$username, $email, $nickname]);
            
            if ($stmt->rowCount() > 0) {
                $msg = "您輸入的 帳號(ID)、電子信箱 或 創作者暱稱 已經被別人使用了！";
            } else {
                // 密碼安全雜湊加密
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 寫入資料庫
                $stmt = $pdo->prepare("INSERT INTO users (username, nickname, email, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $nickname, $email, $hashed_password]);

                // 帶著口信導向登入頁
                header("Location: login.php?msg=" . urlencode("註冊成功！請登入探索新世界"));
                exit;
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
    <title>會員註冊 - 咬一口故事</title>
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
                <div class="error-msg"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form id="register-form" class="form-content" action="" method="POST">
                    <div class="input-group">
                        <label for="reg-username">設定帳號 (ID) <span style="color:#db4455; font-size:12px;">*不能重複</span></label>
                        <input type="text" id="reg-username" name="username" value="<?php echo isset($username)?htmlspecialchars($username):''; ?>" required placeholder="英文字母、數字 6-12 個字元">
                    </div>
                    
                    <div class="input-group">
                        <label for="reg-nickname">創作者筆名 / 暱稱 <span style="color:#db4455; font-size:12px;">*不能重複</span></label>
                        <input type="text" id="reg-nickname" name="nickname" value="<?php echo isset($nickname)?htmlspecialchars($nickname):''; ?>" required placeholder="想要在社區展示的獨特名字">
                    </div>

                    <div class="input-group">
                        <label for="reg-email">電子信箱</label>
                        <input type="email" id="reg-email" name="email" value="<?php echo isset($email)?htmlspecialchars($email):''; ?>" required placeholder="請輸入包含@的電子郵件">
                    </div>
                    <div class="input-group">
                        <label for="reg-password">設定密碼</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-password" name="password" required placeholder="請輸入至少 6 位數密碼">
                            <span class="toggle-password slash" onclick="togglePasswordVisibility('reg-password', this)">
                                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="confirm-password">確認密碼</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm-password" name="confirm_password" required placeholder="請再次輸入密碼">
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">註冊</button>
                    
                    <div class="form-links">
                        <a href="login.php">已有帳號？立即登入</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/auth-validation.js"></script>
</body>
</html>