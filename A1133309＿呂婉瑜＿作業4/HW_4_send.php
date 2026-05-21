<!-- 新增Email、寄信 --> 
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer/src/Exception.php';
require 'PHPMailer/PHPMailer/src/PHPMailer.php';
require 'PHPMailer/PHPMailer/src/SMTP.php';

// 設定資料庫連線
$host = 'localhost';
$db   = 'spam_system';
$user = 'root'; 
$pass = '';     
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
     $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (\PDOException $e) {
     die("資料庫連線失敗: " . $e->getMessage());
}

// A. 新增 Email 
if (isset($_POST['action']) && $_POST['action'] == 'add_email') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO emails (email) VALUES (?)");
            $stmt->execute([$email]);
            
            echo "Email 新增成功！<br><a href='HW_4_index.php'>返回</a>";
        } catch (\PDOException $e) {
            echo "該 Email 已存在或新增失敗！<br><a href='HW_4_index.php'>返回</a>";
        }
    } else {
        echo "不合法的 Email 格式！<br><a href='HW_4_index.php'>返回</a>";
    }
    exit;
} 

// 處理刪除 Email 
if (isset($_POST['action']) && $_POST['action'] == 'delete_email') {
    $id = intval($_POST['id']);

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM emails WHERE id = ?");
            $stmt->execute([$id]);
            
            echo "Email 已成功刪除！<br><a href='HW_4_index.php'>返回</a>";
        } catch (\PDOException $e) {
            echo "刪除失敗！<br><a href='HW_4_index.php'>返回</a>";
        }
    }
    exit;
}

// 寄件 (PHP 緩衝區輸出)
if (isset($_POST['action']) && $_POST['action'] == 'send_mail') {
    ignore_user_abort(true);
    set_time_limit(0);
    
    // 關閉 PHP 輸出緩衝，讓內容能即時呈現在畫面上
    if (ob_get_level()) ob_end_clean();
    ob_implicit_flush(true);
    
    header('Content-Type: text/html; charset=utf-8');

    $mode = $_POST['mode'];
    $interval_min = intval($_POST['interval_min']);
    $interval_max = intval($_POST['interval_max']);
    $subject = $_POST['subject'];
    $content = $_POST['content'];

    // 撈取名單資料
    if ($mode == 'random') {
        $limit = intval($_POST['random_limit']);
        $stmt = $pdo->prepare("SELECT email FROM emails ORDER BY RAND() LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query("SELECT email FROM emails");
    }
    $targets = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $total = count($targets);

    if ($total === 0) {
        echo "<h3>找不到任何可寄送的 Email 名單！</h3><a href='HW_4_index.php'>返回</a>";
        exit;
    }

    echo "<h2>===== 開始執行郵件寄送任務 =====</h2>";
    echo "預計寄送總筆數: $total 筆<br><br>";
    echo str_repeat(' ', 4096); // 填滿瀏覽器暫存區以利即時輸出
    flush();

    // 迴圈開始(一筆一筆寄送)
    foreach ($targets as $index => $email) {
        $current = $index + 1;
        $percent = round(($current / $total) * 100);

        echo "
        <div style='position: fixed; top: 100px; left: 20px; right: 20px; background: white; padding: 20px; border: 1px solid #ccc; font-family: sans-serif;'>
            <progress value='{$current}' max='{$total}' style='width: 100%; height: 20px;'></progress>
            
            <div style='margin-top: 10px; font-size: 15px;'>
                ({$current}/{$total}) 正在寄送至: {$email} ... 
        ";
        flush();

        // 隨機產生這次的間隔秒數
        $current_sleep = rand($interval_min, $interval_max);

        $mail = new PHPMailer(true);
        try {
            // --- 伺服器設定 ---
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'cherry94121111@gmail.com';
            $mail->Password   = 'vhfi jimc cwkv bxcl'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // --- 收件人與內容設定 ---
            $mail->setFrom('cherry94121111@gmail.com', '系統名稱'); 
            $mail->addAddress($email); 

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $content;

            // 執行真正的發信
            $mail->send();
            echo "<span style='color: green; font-weight: bold;'>[成功]</span>";
            
        } catch (Exception $e) {
            echo "<span style='color: red; font-weight: bold;'>[失敗: " . $mail->ErrorInfo . "]</span>";
        }

        // 顯示等待秒數，或是顯示發送完畢即將跳轉的提示
        if ($current < $total) {
            echo "<br><small style='color: #666;'> (等待 {$current_sleep} 秒後寄送下一封...)</small>";
        } else {
            echo "<br><br><strong style='color: blue;'>===== 全部郵件發送完畢！ =====</strong>";
            echo "<br><span style='color: #e74c3c; font-size: 14px;'>任務已結束，系統將在 3 秒後自動返回首頁控制台...</span>";
            echo "<br><small style='font-size: 12px;'><a href='HW_4_index.php' style='color: #3498db;'>若瀏覽器沒有自動跳轉，請點擊此處返回</a></small>";
        }

        echo "</div>"; // 結束文字區
        echo "</div>"; // 結束整個區塊
        flush(); 

        // 進行間隔等待
        if ($current < $total) {
            sleep($current_sleep);
        }
    }

    echo "<meta http-equiv='refresh' content='3;url=HW_4_index.php'>";
    exit;
}
?>