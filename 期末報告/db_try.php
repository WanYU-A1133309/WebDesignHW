<!-- 資料庫連線 -->
<?php
$host    = 'sql211.infinityfree.com';
$db      = 'if0_42272767_bite_story';
$user    = 'if0_42272767';
$pass    = 'Qft7Rd9jzy';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
?>