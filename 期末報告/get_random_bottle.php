<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'db.php';
ob_clean();

try {
    // 💡 根據最新資料表結構修正：
    // 1. 漂流瓶內確實有 story_id，必須與 stories 表進行 JOIN 來取得故事標題 (title) 與標籤 (tags)。
    // 2. 如果 users 表的 nickname 為空，則採用 drifting_bottles 內建的 nickname。
    $query = "SELECT 
            b.story_id,
            IFNULL(NULLIF(u.nickname,''), b.nickname) AS nickname, 
            b.content, 
            s.title AS story_title,
            s.tags AS story_tags
          FROM drifting_bottles b
          INNER JOIN stories s ON b.story_id = s.id
          LEFT JOIN users u ON b.user_id = u.id
          ORDER BY RAND() 
          LIMIT 1";
              
    $stmt = $pdo->query($query);
    $bottle = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bottle) {
        echo json_encode($bottle, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => '故事海裡目前空空如也，快去寫下第一個瓶子吧！'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    // 除錯用：若是測試環境，可以把 $e->getMessage() 印出來看具體錯誤
    echo json_encode(['error' => '打撈失敗：資料庫連線或欄位設定錯誤。'], JSON_UNESCAPED_UNICODE);
}