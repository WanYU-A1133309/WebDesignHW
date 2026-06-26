<!-- 資料庫連線 -->
<?php
$host = 'sql213.infinityfree.com';      
$db_user = 'if0_42275181';        
$db_pass = 'reDc5vk2fbx1BW';            
$db_name = 'if0_42275181_db';  

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // 設定錯誤模式為「丟出例外」，寫錯 SQL 時網頁才會主動回報錯誤
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
?>