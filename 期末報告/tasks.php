<?php
// 咬一口故事 - 每日任務系統 tasks.php (資料庫真正連線動態版)
date_default_timezone_set('Asia/Taipei'); // 強制設定台灣時區
session_start();
require_once 'db.php'; // 🎯 引入你的資料庫連線大腦

// 🔒 守門員：未登入者引導回登入頁面
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?back_url=tasks.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$today = date('Y-m-d'); // 取得今天的日期 (如 2026-06-23)
$alert_msg = $_GET['success'] ?? ""; // 接收領取獎勵成功的提示

// ===================================================
// 1. 🥇【搬移到最前】同步撈取使用者基本資料 (包含最後簽到日期)
// ===================================================
try {
    // 🔥 關鍵修正：SQL 必須把 last_signin_date 撈出來！
    $stmt_user = $pdo->prepare("SELECT id, username, nickname, donuts, wheat, last_signin_date FROM users WHERE id = ?");
    $stmt_user->execute([$current_user_id]);
    $db_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$db_user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    // 建立變數與格式化符合前端邏輯的 $user 陣列
    $last_signin = $db_user['last_signin_date']; // 🔑 現在老天爺終於知道最後簽到日期了！
    
    $user = [
        'name'   => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
        'avatar' => 'https://i.pravatar.cc/100?img=47', // 預設大頭貼
        'donuts' => $db_user['donuts'],
        'wheat'  => $db_user['wheat']
    ];
} catch (PDOException $e) {
    die("使用者資料讀取失敗: " . $e->getMessage());
}


// ===================================================
// 2. ⚡ 核心自動化：檢查並自動建立「今日份」的任務與寶箱進度
// ===================================================
try {
    // A. 檢查今天有沒有建立過任務進度紀錄
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_task_progress WHERE user_id = ? AND log_date = ?");
    $stmt_check->execute([$current_user_id, $today]);
    
    if ($stmt_check->fetchColumn() == 0) {
        // 如果今天完全沒紀錄，抓取目前所有可用的任務主設定
        $stmt_active_tasks = $pdo->prepare("SELECT id FROM daily_tasks WHERE is_active = 1");
        $stmt_active_tasks->execute();
        $active_tasks = $stmt_active_tasks->fetchAll(PDO::FETCH_ASSOC);
        
        // 幫使用者批次塞入今天份的空白任務紀錄 (進度為 0)
        $stmt_init = $pdo->prepare("INSERT IGNORE INTO user_task_progress (user_id, task_id, current_value, is_done, log_date) VALUES (?, ?, 0, 0, ?)");
        foreach ($active_tasks as $at) {
            $stmt_init->execute([$current_user_id, $at['id'], $today]);
        }
    }

    // B. 檢查今天有沒有建立過活躍度寶箱紀錄
    $stmt_chest_check = $pdo->prepare("SELECT COUNT(*) FROM user_daily_chests WHERE user_id = ? AND log_date = ?");
    $stmt_chest_check->execute([$current_user_id, $today]);
    
    if ($stmt_chest_check->fetchColumn() == 0) {
        $stmt_chest_init = $pdo->prepare("INSERT IGNORE INTO user_daily_chests (user_id, log_date) VALUES (?, ?)");
        $stmt_chest_init->execute([$current_user_id, $today]);
    }
} catch (PDOException $e) {
    die("系統初始化任務失敗: " . $e->getMessage());
}


// ===================================================
// 3. 🔄【移到初始化後】利用 $last_signin 動態同步「每日簽到」任務狀態
// ===================================================
// 當上面「今日空白任務」創好後，此時如果發現使用者最後簽到日是今天，就立刻去把它改成「已完成」！
if (isset($last_signin) && $last_signin === $today) {
    try {
        $stmt_sync_signin = $pdo->prepare("
            UPDATE user_task_progress 
            SET current_value = 1, is_done = 1 
            WHERE user_id = ? 
              AND log_date = ? 
              AND task_id = (SELECT id FROM daily_tasks WHERE task_key = 'signin' LIMIT 1)
        ");
        $stmt_sync_signin->execute([$current_user_id, $today]);
    } catch (PDOException $e) {
        // 防止干擾
    }
}


// ===================================================
// 4. 📊 從資料庫撈取今日最新任務狀況、計算總活躍度
// ===================================================
try {
    // 聯表查詢 (JOIN) 抓出主設定與使用者的今日進度（因為上方剛剛更新過，這裡撈出來的一定是最新正確狀態！）
    $stmt_load = $pdo->prepare("
        SELECT t.title as n, t.description as d, t.reward_text as r, t.pts_reward as pts, t.target_value,
               p.current_value, p.is_done, t.task_key
        FROM user_task_progress p
        JOIN daily_tasks t ON p.task_id = t.id
        WHERE p.user_id = ? AND p.log_date = ?
    ");
    $stmt_load->execute([$current_user_id, $today]);
    $tasks = $stmt_load->fetchAll(PDO::FETCH_ASSOC);

    // 動態加總今日已完成的任務分數，算出總活躍度
    $total_pts_earned = 0;
    foreach ($tasks as $t) {
        if ($t['is_done'] == 1) {
            $total_pts_earned += $t['pts'];
        }
    }

    // 撈取今日寶箱領取狀態
    $stmt_chests = $pdo->prepare("SELECT chest_20, chest_50, chest_80, chest_100 FROM user_daily_chests WHERE user_id = ? AND log_date = ?");
    $stmt_chests->execute([$current_user_id, $today]);
    $chest_status = $stmt_chests->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("讀取今日任務資料失敗: " . $e->getMessage());
}

// ===================================================
// 5. 🎁 核心互動：處理「點擊寶箱領取獎勵」請求 (POST)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_chest') {
    $target = intval($_POST['target'] ?? 0);
    $chest_field = "chest_" . $target;
    
    if (in_array($target, [20, 50, 80, 100]) && $total_pts_earned >= $target && isset($chest_status[$chest_field]) && $chest_status[$chest_field] == 0) {
        try {
            $pdo->beginTransaction();
            
            $stmt_up_chest = $pdo->prepare("UPDATE user_daily_chests SET {$chest_field} = 1 WHERE user_id = ? AND log_date = ?");
            $stmt_up_chest->execute([$current_user_id, $today]);
            
            $reward_msg = "";
            if ($target == 20) {
                $pdo->prepare("UPDATE users SET wheat = wheat + 5 WHERE id = ?")->execute([$current_user_id]);
                $reward_msg = "成功解鎖 20 點寶箱！獲得 🌾 5 麥穗！";
            } elseif ($target == 50) {
                $pdo->prepare("UPDATE users SET wheat = wheat + 10 WHERE id = ?")->execute([$current_user_id]);
                $reward_msg = "成功解鎖 50 點寶箱！獲得 🌾 10 麥穗！";
            } elseif ($target == 80) {
                $pdo->prepare("UPDATE users SET wheat = wheat + 15, donuts = donuts + 1 WHERE id = ?")->execute([$current_user_id]);
                $reward_msg = "成功解鎖 80 點寶箱！獲得 🌾 15 麥穗 + 🍩 1 甜甜圈！";
            } elseif ($target == 100) {
                $pdo->prepare("UPDATE users SET donuts = donuts + 10 WHERE id = ?")->execute([$current_user_id]);
                $reward_msg = "太厲害了！解鎖 100 點終極寶箱！獲得 🍩 10 甜甜圈！";
            }
            
            $pdo->commit();
            header("Location: tasks.php?success=" . urlencode($reward_msg));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("領取獎勵失敗: " . $e->getMessage());
        }
    }
}

// 定義前端里程碑寶箱結構
$milestones = [
    ['target' => 20,  'reward' => '5 麥穗', 'claimed' => $chest_status['chest_20'] ?? 0],
    ['target' => 50,  'reward' => '10 麥穗', 'claimed' => $chest_status['chest_50'] ?? 0],
    ['target' => 80,  'reward' => '15 麥穗 + 1 甜甜圈', 'claimed' => $chest_status['chest_80'] ?? 0],
    ['target' => 100, 'reward' => '🔥 豪華大禮包 (10 甜甜圈)', 'claimed' => $chest_status['chest_100'] ?? 0]
];

$progress_percent = min(100, ($total_pts_earned / 100) * 100);
$active = 'tasks'; // 側邊欄亮燈標籤
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ 每日任務中心</title>
<link rel="stylesheet" href="theme.css">
<style>
/* 核心版面樣式維持原有的完美磨砂暖色調不變 */
.page-head{display:flex;align-items:center;justify-content:between;gap:14px;margin-bottom:24px}
.page-head h1{font-size:28px;display:flex;align-items:center;gap:12px;color:var(--ink)}
.page-head h1 img{width:48px;height:48px;object-fit:contain}

.active-panel {
  background: var(--surface-glass); backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px;
  padding: 24px; box-shadow: var(--shadow); margin-bottom: 30px;
  display: flex; flex-direction: column; gap: 20px;
}
.active-info { display: flex; justify-content: space-between; align-items: center; }
.active-title { font-size: 18px; font-weight: 800; color: var(--ink); display: flex; align-items: center; gap: 8px; }
.active-title span.num { color: var(--primary); font-size: 24px; font-family: 'Impact', sans-serif; }
.active-desc { font-size: 13px; color: var(--muted); }

.milestone-container { position: relative; height: 45px; margin: 10px 10px 0 10px; }
.milestone-track { position: absolute; top: 12px; left: 0; right: 0; height: 12px; background: #EDDFC8; border-radius: 99px; }
.milestone-fill { height: 100%; background: linear-gradient(90deg, var(--primary-2), var(--primary)); border-radius: 99px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }

.chest-node { position: absolute; top: 0; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; z-index: 2; }
.chest-icon { width: 36px; height: 36px; background: #FFF; border: 3px solid #EDDFC8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }

/* 狀態一：解鎖但尚未點擊領取 -> 觸發滑鼠指針與跳動 */
.chest-node.unlocked { cursor: pointer; }
.chest-node.unlocked .chest-icon { border-color: var(--primary-2); background: #FFFBF5; animation: pulse 1.5s infinite alternate; }
/* 狀態二：已點擊領取完畢 -> 綠色沉靜狀態 */
.chest-node.claimed .chest-icon { border-color: var(--accent); background: #E9F1DE; animation: none; }

.chest-pts { font-size: 12px; font-weight: 700; margin-top: 6px; background: #FAF3E8; padding: 2px 8px; border-radius: 10px; color: var(--ink); }
.chest-node.unlocked .chest-pts { background: var(--primary-2); color: #FFF; }
.chest-node.claimed .chest-pts { background: var(--accent); color: #FFF; }

.chest-pop { position: absolute; bottom: 50px; background: var(--ink); color: #FFF; padding: 6px 12px; border-radius: 8px; font-size: 12px; white-space: nowrap; opacity: 0; visibility: hidden; transition: 0.2s; box-shadow: var(--shadow); }
.chest-pop::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border: 6px solid transparent; border-top-color: var(--ink); }
.chest-node:hover .chest-pop { opacity: 1; visibility: visible; bottom: 55px; }

.task-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px}
.task { background:var(--surface-glass); backdrop-filter:blur(14px); border:1px solid rgba(255,255,255,.7); border-radius:24px; padding:22px; box-shadow:var(--shadow); display:flex; flex-direction:column; gap:14px; transition:.25s; }
.task:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.task.done{background:linear-gradient(135deg,#E9F1DE,#FFFCF6); border-color:rgba(123,174,127,0.4)}
.task .row1{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.task .n{font-size:18px;font-weight:800;color:var(--ink)}
.task .d{font-size:13px;color:var(--muted);line-height:1.5;margin-top:4px}
.task .meta-box { display: flex; align-items: center; gap: 8px; }
.task .r{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:#FFEFDD;color:var(--primary);font-weight:700;font-size:12px;}
.task .pts-badge {display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;background:#EBF3FF;color:#3A86FF;font-weight:700;font-size:12px;}
.task.done .r{background:#DCEFD4;color:var(--accent)}
.task.done .pts-badge {background:#E2ECDE;color:var(--accent)}

.task .bar-wrapper { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; color: var(--muted); }
.task .bar{flex: 1; height:8px;background:#EDDFC8;border-radius:99px;overflow:hidden}
.task .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-2)); transition: width 0.4s;}
.task.done .bar i{background:linear-gradient(90deg,var(--accent),#A8C49C)}

.task .act{padding:11px;border-radius:14px;text-align:center;font-weight:700;font-size:14px;cursor:pointer;border:0;transition:.2s}
.task .act.go{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff}
.task .act.ok{background:#DCEFD4;color:var(--accent);cursor:default}
.task .act.go:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(227,106,75,.3)}

/* 彈出成功提示視窗優化 */
.alert-toast-success { background: linear-gradient(135deg, #7BAE7F, #5f9663); color:#fff; padding:12px 24px; border-radius:14px; margin-bottom:20px; font-weight:700; box-shadow:var(--shadow-sm); }

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(246, 162, 107, 0.5); transform: scale(1); }
  100% { box-shadow: 0 0 0 8px rgba(246, 162, 107, 0); transform: scale(1.05); }
}
</style>
</head>
<body>
<?php include '_sidebar.php'; ?>
<main class="main">
  <?php include '_topbar.php'; ?>

  <div class="page-head">
    <h1><span class="ic ic-sparkle ic-lg"></span> 每日任務中心 <img src="deco-badge.png" alt=""></h1>
  </div>

  <?php if(!empty($alert_msg)): ?>
    <div class="alert-toast-success">🎉 <?= htmlspecialchars($alert_msg) ?></div>
  <?php endif; ?>

  <div class="active-panel">
    <div class="active-info">
      <div class="active-title">
        今日總活躍度：<span class="num"><?= $total_pts_earned ?></span> / 100
      </div>
      <div class="active-desc">把游標移到寶箱上可以偷看獎勵！點擊跳動的寶箱即可兌換虛寶。</div>
    </div>
    
    <div class="milestone-container">
      <div class="milestone-track">
        <div class="milestone-fill" style="width: <?= $progress_percent ?>%"></div>
      </div>
      
      <?php foreach($milestones as $ms): 
        $is_unlocked = ($total_pts_earned >= $ms['target']);
        $is_claimed = $ms['claimed'];
        
        $status_class = '';
        $chest_emoji = '🔒'; // 預設上鎖
        
        if ($is_unlocked) {
            if ($is_claimed) {
                $status_class = 'claimed';
                $chest_emoji = '🎉'; // 已領完
            } else {
                $status_class = 'unlocked';
                $chest_emoji = '🎁'; // 達標跳動中
            }
        }
      ?>
        <div class="chest-node <?= $status_class ?>" 
             style="left: <?= $ms['target'] ?>%;" 
             onclick="submitClaim(<?= $ms['target'] ?>, <?= $is_unlocked ? 'true':'false' ?>, <?= $is_claimed ? 'true':'false' ?>)">
          
          <div class="chest-pop">
            <?= $ms['target'] ?>點：<?= $ms['reward'] ?> 
            (<?= $is_unlocked ? ($is_claimed ? '已領取過' : '可點擊領取✨') : '未達成' ?>)
          </div>
          <div class="chest-icon"><?= $chest_emoji ?></div>
          <div class="chest-pts"><?= $ms['target'] ?> Pts</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="task-list">
    <?php foreach($tasks as $t):
      // 計算目前進度百分比
      $raw_prog = ($t['current_value'] / $t['target_value']) * 100;
      $prog = $t['is_done'] ? 100 : min(100, max(0, round($raw_prog))); 
    ?>
    <div class="task <?= $t['is_done'] ? 'done' : '' ?>">
      <div class="row1">
        <div>
          <div class="n"><?= htmlspecialchars($t['n']) ?></div>
          <div class="d"><?= htmlspecialchars($t['d']) ?></div>
        </div>
        <div class="meta-box">
          <span class="pts-badge">⚡ +<?= $t['pts'] ?> 活躍</span>
          <span class="r"><?= htmlspecialchars($t['r']) ?></span>
        </div>
      </div>
      
      <div class="bar-wrapper">
        <div class="bar"><i style="width:<?= $prog ?>%"></i></div>
        <span><?= $t['is_done'] ? '100%' : $t['current_value'] . '/' . $t['target_value'] ?></span>
      </div>

      <?php if($t['is_done']): ?>
        <button class="act ok">✓ 已完成</button>
      <?php else: ?>
        <button class="act go" onclick="location.href='index.php'">前往完成 →</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<form id="claimChestForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="claim_chest">
    <input type="hidden" name="target" id="chestTargetField">
</form>

<script>
function submitClaim(target, unlocked, claimed) {
    if (unlocked && !claimed) {
        document.getElementById('chestTargetField').value = target;
        document.getElementById('claimChestForm').submit();
    } else if (claimed) {
        alert("這個寶箱你今天已經領過囉！明天請早點來咬一口故事。");
    } else {
        alert("你的活躍度還不夠喔，快去完成下方的每日任務吧！");
    }
}
</script>
</body>
</html>