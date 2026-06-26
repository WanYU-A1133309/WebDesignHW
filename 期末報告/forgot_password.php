<?php
// forgot_password.php - 忘記密碼處理頁面（PHPMailer 實戰版）
session_start();
require_once 'db.php'; // 引入資料庫連線橋樑

// 1. 引入 PHPMailer 的核心檔案（請確保你的專案目錄下有 PHPMailer 資料夾與這三個檔案）
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = "";       
$msg_type = "";  

// 當使用者送出 Email 表單時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $msg = "請輸入您的電子信箱！";
        $msg_type = "error";
    } else {
        try {
            // 至資料庫查詢該 Email 是否存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                // 🎯 找到使用者了！開始執行重設密碼與發信邏輯
                
                // (1) 生成隨機且安全的 Token (防偽造的密碼重設金鑰)
                $token = bin2hex(random_bytes(16)); 
                
                // (2) 實務補充：這裡通常會把 Token 寫入資料庫的 users 資料表，以便 reset_password.php 比對
                // 假設你的 users 資料表有 reset_token 欄位，可以取消下方兩行註解來儲存：
                $update_stmt = $pdo->prepare("UPDATE users SET reset_token = ? WHERE email = ?");
                $update_stmt->execute([$token, $email]);

                // (3) 組合重設密碼的完整連結 (請根據你的專案資料夾名稱修改網址路徑)
                $reset_link = "http://localhost/Project/reset_password.php?token=" . $token;

                // (4) 啟動 PHPMailer 機制
                $mail = new PHPMailer(true);

                // 伺服器設定 (Gmail SMTP 固定配置)
                $mail->isSMTP();                                            
                $mail->Host       = 'smtp.gmail.com';                       
                $mail->SMTPAuth   = true;                                   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
                $mail->Port       = 587;                                    
                $mail->CharSet    = 'UTF-8'; // 防止中文信件標題或內文亂碼

                // 🔑 憑證設定 (請填寫你自己的資訊)
                $mail->Username   = 'cherry94121111@gmail.com';                // 你的 Gmail 信箱
                $mail->Password   = 'vhfi jimc cwkv bxcl';                     // 剛剛去 Google 申請的 16 位元應用程式密碼

                // 收發件人設定
                $mail->setFrom('cherry94121111@gmail.com', '咬一口故事');       
                $mail->addAddress($email);                                  // 寄給填寫表單的使用者

                // 信件內容 HTML 樣式排版
                $mail->isHTML(true);                                        
                $mail->Subject = '【咬一口故事】請確認您的密碼找回請求';
                $mail->Body    = "
                    <div style='max-width:500px; background:#FBF5EC; padding:30px; border-radius:18px; font-family:sans-serif; color:#2B1A0F;'>
                        <h3 style='color:#E36A4B; margin-bottom:16px;'>親愛的書友您好：</h3>
                        <p style='font-size:15px; line-height:1.6;'>我們收到了您找回密碼的請求。請點擊下方的按鈕來重新設定您的密碼。</p>
                        <div style='text-align:center; margin:26px 0;'>
                            <a href='{$reset_link}' style='background:linear-gradient(135deg,#E36A4B,#F6A26B); color:#fff; text-decoration:none; padding:12px 28px; border-radius:999px; font-weight:bold; display:inline-block; box-shadow:0 6px 14px rgba(227,106,75,0.3);'>立即重設密碼</a>
                        </div>
                        <p style='font-size:12px; color:#9A8675;'>如果按鈕無法點擊，您也可以複製此網址到瀏覽器開啟：<br><a href='{$reset_link}' style='color:#E36A4B;'>{$reset_link}</a></p>
                        <hr style='border:0; border-top:1px solid #EDDFC8; margin:20px 0;'>
                        <p style='font-size:12px; color:#9A8675; text-align:center;'>咬一口故事團隊 敬上</p>
                    </div>
                ";

                // 執行發送
                $mail->send();

                // 發送成功後顯示綠色成功提示
                $msg = "重設驗證郵件已發送！請檢查您的電子信箱。";
                $msg_type = "success"; 

            } else {
                $msg = "找不到此電子信箱，請確認是否輸入正確或尚未註冊。";
                $msg_type = "error";
            }
        } catch (Exception $e) {
            // 捕捉 PHPMailer 的發信錯誤
            $msg = "郵件發送失敗，請稍後再試。錯誤原因: " . $mail->ErrorInfo;
            $msg_type = "error";
        } catch (PDOException $e) {
            // 捕捉資料庫錯誤
            $msg = "系統錯誤，請稍後再試";
            $msg_type = "error";
        }
    }
}

if (empty($msg) && isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    $msg_type = "error"; 
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="utf-8">
    <title>忘記密碼 - 咬一口故事</title>
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
                <div class="form-title">找回密碼</div>
                <div class="form-subtitle">請輸入您註冊時填寫的電子郵件，<br>我們將向您發送重設密碼的說明郵件。</div>

                <form action="" method="POST" class="form-content">
                    <div class="input-group">
                        <label for="forgot-email">電子信箱</label>
                        <input type="email" id="forgot-email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required placeholder="請輸入包含@的電子郵件">
                    </div>
                    <button type="submit" class="submit-btn">發送重設郵件</button>
                    
                    <div class="form-links">
                        <a href="login.php">想起來了？返回登入</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</body>
</html>