<?php
// 咬一口故事 - 每日簽到 signin.php (完全修復時區與實體日曆版)
session_start();
require_once 'db.php'; 

// 🎯 關鍵修正：強制指定為台灣時區，防止 00:00 到了不重置
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?msg=' . urlencode('請先登入才能進行簽到'));
    exit;
}

$user_id = $_SESSION['user_id'];
$today_str = date('Y-m-d');
$yesterday_str = date('Y-m-d', strtotime('-1 day'));

$rewards = ['+10 🍩', '+10 🍩', '+15 🍩', '+15 🍩', '+20 🍩', '+20 🍩', '+50 🍩 大禮包'];
$reward_values = [10, 10, 15, 15, 20, 20, 50]; 

// --- 撈取使用者核心資料 ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$db_user = $stmt->fetch(PDO::FETCH_ASSOC);

$nickname = $db_user['nickname'] ?? '神秘創作者';
$avatar   = (!empty($db_user['avatar']) && file_exists($db_user['avatar'])) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47';
$donuts   = $db_user['donuts'] ?? 0;
$last_signin = $db_user['last_signin_date'];
$streak   = $db_user['signin_streak'] ?? 0;

// --- 💡 實體檢查：今天是否真的簽到過 ---
$check_stmt = $pdo->prepare("SELECT COUNT(*) FROM signin_logs WHERE user_id = ? AND signin_date = ?");
$check_stmt->execute([$user_id, $today_str]);
$has_signed_in_today = ($check_stmt->fetchColumn() > 0);

// 判斷連續簽到是否中斷
if ($last_signin !== $today_str && $last_signin !== $yesterday_str) {
    $streak = 0; 
}

$msg = "";
$msg_type = "";

// 🎯 接收手動簽到請求 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signin') {
    if ($has_signed_in_today) {
        $msg = "今天已經簽到過囉，明天再來吧！";
        $msg_type = "error";
    } else {
        $new_streak = $streak + 1;
        $reward_index = ($new_streak - 1) % 7;
        $bonus_donuts = $reward_values[$reward_index];
        
        try {
            $pdo->beginTransaction();
            
            // 1. 插入實體簽到紀錄紀錄 (UNIQUE KEY 會保護不重複)
            $log_stmt = $pdo->prepare("INSERT IGNORE INTO signin_logs (user_id, signin_date) VALUES (?, ?)");
            $log_stmt->execute([$user_id, $today_str]);
            
            if ($log_stmt->rowCount() > 0) {
                // 2. 確實成功寫入紀錄，才更新使用者點數與連簽天數
                $update_stmt = $pdo->prepare("
                    UPDATE users 
                    SET donuts = donuts + ?, last_signin_date = ?, signin_streak = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$bonus_donuts, $today_str, $new_streak, $user_id]);
                $pdo->commit();
                
                header("Location: signin.php?success_donuts=" . $bonus_donuts);
                exit;
            } else {
                throw new Exception("重複簽到截斷");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "簽到失敗，您今天可能已經簽到過了。";
            $msg_type = "error";
        }
    }
}

if (isset($_GET['success_donuts'])) {
    $msg = "簽到成功！順利獲得 +" . (int)$_GET['success_donuts'] . " 甜甜圈 🍩";
    $msg_type = "success";
    $has_signed_in_today = true;
    
    // 重新取得更新後的連續天數與點數，防止畫面顯示舊資料
    $stmt_refresh = $pdo->prepare("SELECT donuts, signin_streak FROM users WHERE id = ?");
    $stmt_refresh->execute([$user_id]);
    $refreshed = $stmt_refresh->fetch(PDO::FETCH_ASSOC);
    $donuts = $refreshed['donuts'];
    $streak = $refreshed['signin_streak'];
}

// 📅 日曆與週數輔助變數
$today_day_num = (int)date('j');
$days_in_month = (int)date('t');
$current_month_prefix = date('Y-m-'); // 例如 "2026-06-"

// 💡 關鍵修正：直接從資料庫把這使用者「本月有簽到」的所有日子撈出來
$history_days = [];
$hist_stmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(signin_date, '%e') as dnum FROM signin_logs WHERE user_id = ? AND signin_date LIKE ?");
$hist_stmt->execute([$user_id, $current_month_prefix . '%']);
while($row = $hist_stmt->fetch(PDO::FETCH_ASSOC)) {
    $history_days[] = (int)$row['dnum']; // 陣列裡會存像是 [1, 2, 15, 24, 25] 等實體簽到過的日子
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ 每日簽到</title>
<link rel="stylesheet" href="theme.css">
<style>
.page-head{display:flex;align-items:center;gap:14px;margin-bottom:22px}
.page-head h1{font-size:28px;display:flex;align-items:center;gap:12px}
.page-head h1 img{width:54px;height:54px;object-fit:contain}
.signin-wrap{
  background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:24px;padding:28px;box-shadow:var(--shadow);
}
.streak{
  display:flex;justify-content:space-between;align-items:center;
  background:linear-gradient(135deg,#FFE4D2,#FFD3B6);border-radius:18px;padding:18px 22px;margin-bottom:24px;
}
.streak .l{font-size:14px;color:#5A4030}
.streak .n{font-size:34px;font-weight:900;color:var(--primary);line-height:1}
.week{display:grid;grid-template-columns:repeat(7,1fr);gap:12px;margin-bottom:28px}
.day{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 8px;text-align:center;position:relative;transition:.25s;}
.day.done{background:linear-gradient(135deg,#E9F1DE,#FFF6E8);border-color:var(--accent)}
.day.today{border-color:var(--primary);box-shadow:0 0 0 3px rgba(227,106,75,.18)}
.day .l{font-size:12px;color:var(--muted);margin-bottom:4px}
.day .r{font-size:12px;font-weight:700;color:var(--primary);margin-top:6px}
.day.done .r{color:var(--accent)}
.day .icon{font-size:24px}
.day.done .icon{color:var(--accent)}
.big-btn{
  display:block;margin:0 auto;padding:14px 44px;border-radius:99px;border:0;cursor:pointer;
  background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;font-weight:800;font-size:16px;
  box-shadow:0 12px 28px rgba(227,106,75,.45);transition:.25s;
}
.big-btn:disabled{background:#ccc;box-shadow:none;cursor:not-allowed;}
.month-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:18px}
.mday{aspect-ratio:1;display:grid;place-items:center;border-radius:10px;font-size:13px;background:#fff;border:1px solid var(--line);color:var(--muted)}
.mday.done{background:linear-gradient(135deg,#FFE4D2,#FFD3B6);color:var(--primary);font-weight:700;border-color:transparent}
.mday.today{outline:2px solid var(--primary)}
.section-title{font-size:16px;font-weight:700;margin:10px 0 12px;display:flex;align-items:center;gap:8px}
.alert-box { padding: 12px; border-radius: 12px; margin-bottom: 15px; text-align: center; font-weight: bold; }
.alert-success { background: #E9F1DE; color: #5B8C5A; }
.alert-error { background: #FCE8E6; color: #C53929; }
</style>
</head>
<body>
<?php include '_sidebar.php'; ?>
<main class="main">
  <?php include '_topbar.php'; ?>

  <div class="page-head"><h1><span class="ic ic-gift ic-lg"></span> 每日簽到 <img src="deco-calendar.png" alt=""></h1></div>

  <div class="signin-wrap">
    
    <?php if(!empty($msg)): ?>
        <div class="alert-box <?= $msg_type==='success'?'alert-success':'alert-error' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="streak">
      <div><div class="l">目前連續簽到</div><div class="n"><?= $streak ?> <span style="font-size:16px;font-weight:700">天</span></div></div>
      <div style="text-align:right"><div class="l">目前甜甜圈存量</div><div class="n">🍩 <?= $donuts ?></div></div>
    </div>

    <div class="section-title"><span class="ic ic-sm ic-sparkle"></span> 簽到進度（每 7 天一輪大禮包）</div>
    <div class="week">
      <?php 
      // 計算目前這週格子亮起的狀態
      $current_cycle_day = ($streak % 7 == 0 && $streak > 0) ? 7 : ($streak % 7);
      
      for($i=1;$i<=7;$i++):
        if ($has_signed_in_today) {
            $is_done = ($i <= $current_cycle_day);
            $is_today = ($i == $current_cycle_day);
        } else {
            $is_done = ($i < $current_cycle_day + 1);
            $is_today = ($i == $current_cycle_day + 1);
        }
      ?>
      <div class="day <?= $is_done?'done':'' ?> <?= $is_today?'today':'' ?>">
        <div class="l">第 <?= $i ?> 天</div>
        <div class="icon"><?= $is_done?'✓':'🎁' ?></div>
        <div class="r"><?= $rewards[$i-1] ?></div>
      </div>
      <?php endfor; ?>
    </div>

    <form method="POST" action="signin.php">
        <input type="hidden" name="action" value="signin">
        <?php if($has_signed_in_today): ?>
            <button type="button" class="big-btn" disabled>今天已完成簽到 🍩</button>
        <?php else: ?>
            <button type="submit" class="big-btn">立即簽到 ✨</button>
        <?php endif; ?>
    </form>

    <div class="section-title" style="margin-top:30px"><span class="ic ic-sm ic-openbook"></span> 本月簽到日曆</div>
    <div class="month-grid">
      <?php 
      for($d=1;$d<=$days_in_month;$d++):
        $is_m_today = ($d == $today_day_num);
        // 💡 終極修正：直接比對該日期是否存在於歷史簽到陣列中！精準無比！
        $is_m_done = in_array($d, $history_days);
      ?>
      <div class="mday <?= $is_m_done?'done':'' ?> <?= $is_m_today?'today':'' ?>"><?= $d ?></div>
      <?php endfor; ?>
    </div>
  </div>
</main>
</body>
</html>