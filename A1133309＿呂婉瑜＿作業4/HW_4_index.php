<?php
$host = 'localhost';
$db   = 'spam_system';
$user = 'root'; 
$pass = '';     
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
     $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
     
     // 目前所有的名單
     $stmt = $pdo->query("SELECT id, email FROM emails ORDER BY id DESC");
     $email_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
     die("資料庫連線失敗: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>郵件發送管理系統</title>
    <style>
        body { 
            font-family: "Helvetica Neue", Arial, "Noto Sans TC", sans-serif; 
            background-color: #f4f6f9; 
            color: #333; 
            margin: 0; 
            padding: 40px 20px; 
        }
        .main-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .container { 
            max-width: 650px; 
            background: #ffffff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); 
            margin: 0 auto 30px auto; 
        }
        
        /* 區塊標題 */
        h2 { 
            color: #2c3e50; 
            font-size: 1.5rem;
            margin-top: 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0; 
        }
        .title-a { border-bottom-color: #3498db; }
        .title-b { border-bottom-color: #e67e22; }
        
        /* 表單元素 */
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #4a5568;
        }
        input[type="email"], 
        input[type="text"], 
        input[type="number"], 
        textarea, 
        select { 
            width: 100%; 
            padding: 10px 14px; 
            box-sizing: border-box; 
            border: 1px solid #cbd5e0; 
            border-radius: 5px; 
            font-size: 14px;
            background-color: #fff;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
        
        /* 按鈕 */
        .btn {
            width: 100%; 
            padding: 12px; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            font-weight: bold;
            cursor: pointer; 
            transition: background-color 0.2s;
        }
        .btn-blue { background-color: #3498db; color: white; }
        .btn-blue:hover { background-color: #2980b9; }
        .btn-orange { background-color: #e67e22; color: white; }
        .btn-orange:hover { background-color: #d35400; }
        .btn-red { background-color: #e74c3c; color: white; padding: 6px 12px; font-size: 13px; width: auto; }
        .btn-red:hover { background-color: #c0392b; }
        
        /* 列表表格 */
        .email-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        .email-table th, .email-table td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
        }
        .email-table th {
            background-color: #f7fafc;
            color: #4a5568;
        }
        .email-table tr:hover {
            background-color: #f8fafc;
        }

        /* 其他 */
        .flex-row { 
            display: flex; 
            gap: 15px; 
            align-items: center;
        }
        .flex-row input {
            flex: 1;
        }
        .note {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }
        hr {
            border: 0;
            height: 1px;
            background: #e2e8f0;
            margin: 25px 0;
        }
    </style>
</head>
<body>

    <h1 class="main-title">郵件發送管理系統</h1>

    <div class="container">
        <h2 class="title-a">Email名單管理</h2>
        
        <form action="HW_4_send.php" method="POST">
            <input type="hidden" name="action" value="add_email">
            <div class="form-group">
                <label for="email">新增 Email ：</label>
                <input type="email" id="email" name="email" required placeholder="例如：example@domain.com">
                <div class="note">輸入正確的 Email 格式後點擊按鈕，即可將名單寫入資料庫。</div>
            </div>
            <button type="submit" class="btn btn-blue">加入資料庫</button>
        </form>

        <hr>

        <h3>目前資料庫名單 (總計: <?php echo count($email_list); ?> 筆)</h3>
        <?php if (count($email_list) > 0): ?>
            <table class="email-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">No.</th>
                        <th>Email 位址</th>
                        <th style="width: 100px; text-align: center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_list as $index => $row): ?>
                        <tr>
                            <td><?php echo count($email_list) - $index; ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td style="text-align: center;">
                                <form action="HW_4_send.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_email">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-red">刪除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #718096; font-style: italic;">目前資料庫內尚無資料，請由上方新增。</p>
        <?php endif; ?>
    </div>

    <div class="container">
        <h2 class="title-b">基本郵件及發送設定</h2>
        <form action="HW_4_send.php" method="POST" target="_blank">
            <input type="hidden" name="action" value="send_mail">
            
            <div class="form-group">
                <label for="mode">發送模式：</label>
                <select id="mode" name="mode">
                    <option value="all">全部寄送</option>
                    <option value="random">隨機寄送指定筆數</option>
                </select>
            </div>

            <div class="form-group">
                <label for="random_limit">隨機寄送筆數：</label>
                <input type="number" id="random_limit" name="random_limit" value="5" min="1">
                <div class="note">※ 僅在發送模式選擇「隨機寄送指定筆數」時，此處設定才會生效。</div>
            </div>

            <div class="form-group">
                <label>設定郵件寄送間隔秒數（隨機範圍）：</label>
                <div class="flex-row">
                    <input type="number" name="interval_min" value="2" min="0" required placeholder="最小值"> 
                    <span>至</span>
                    <input type="number" name="interval_max" value="5" min="0" required placeholder="最大值"> 
                    <span>秒</span>
                </div>
                <div class="note">系統會在每寄出一封信後，隨機抽取區間內的秒數進行等待，模擬真人發信。</div>
            </div>

            <hr>

            <div class="form-group">
                <label for="subject">郵件主旨：</label>
                <input type="text" id="subject" name="subject" placeholder="請輸入郵件標題" required>
            </div>

            <div class="form-group">
                <label for="content">郵件內容：</label>
                <textarea id="content" name="content" rows="6" placeholder="請輸入郵件內文" required></textarea>
            </div>

            <button type="submit" class="btn btn-orange">開始執行寄送任務</button>
        </form>
    </div>

</body>
</html>