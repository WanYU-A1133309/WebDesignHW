<?php
// 咬一口故事 - 書籍盲盒專區
session_start();
ob_start(); // 開啟輸出緩衝，確保這之後不輸出任何東西
require_once 'db.php'; 

// 如果有任何錯誤，隱藏錯誤訊息，防止它們混入 JSON
error_reporting(0);
ini_set('display_errors', 0);

ob_end_clean(); // 把引入檔案時產生的所有輸出（包含註解）全部消滅

date_default_timezone_set('Asia/Taipei');
// 不需要再 require_once 'db.php' 了，上面已經引入過

// ==========================================
// 🎯 核心後端邏輯：處理盲盒抽取 AJAX 請求
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'draw') {
    header('Content-Type: application/json');
    $mode = $_GET['mode'] ?? 'random';
    $book = null;
    $is_logged_in = isset($_SESSION['user_id']);

    try {
        if ($mode === 'history' && $is_logged_in) {
            // 【猜你想看】邏輯：撈出使用者近期最常觀看的前3個標籤(tag)，並從中隨機推薦
            $stmt_tags = $pdo->prepare("
                SELECT b.tag 
                FROM user_reading_history h 
                JOIN books b ON h.book_id = b.id 
                WHERE h.user_id = ? 
                GROUP BY b.tag 
                ORDER BY COUNT(*) DESC 
                LIMIT 3
            ");
            $stmt_tags->execute([$_SESSION['user_id']]);
            $favorite_tags = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($favorite_tags)) {
                $in_clause = implode(',', array_fill(0, count($favorite_tags), '?'));
                $stmt_book = $pdo->prepare("SELECT * FROM stories WHERE tag IN ($in_clause) ORDER BY RAND() LIMIT 1");
                $stmt_book->execute($favorite_tags);
                $book = $stmt_book->fetch(PDO::FETCH_ASSOC);
            }
        }

        // 降級防禦：若選隨機、未登入、或猜你想看無歷史紀錄，則走【全站隨機抽】
        if (!$book) {
            $stmt_book = $pdo->query("SELECT * FROM stories ORDER BY RAND() LIMIT 1");
            $book = $stmt_book->fetch(PDO::FETCH_ASSOC);
        }

        if ($book) {
            echo json_encode([
                'success' => true,
                'title' => $book['title'],
                'author' => $book['author'],
                'cover_type'  => $book['cover_type'],
                'cover_image' => ($book['cover_type'] === 'upload' && !empty($book['cover_image'])) ? $book['cover_image'] : null,
                'tag' => $book['tag'],
                'id' => $book['id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '目前庫存沒有書籍可供抽取資料。']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '系統忙碌中，請稍後再試。' . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 🎯 一般頁面初始化：同步主頁 index.php 邏輯
// ==========================================
$is_logged_in = isset($_SESSION['user_id']);
$user = null;
$show_signin_alert = false; 

if ($is_logged_in) {
    try {
        // 1. 優先撈取使用者核心錢包與個人化設定
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_user) {
            // 先初始化好核心基礎資料（確保不論如何，頭貼、暱稱、甜甜圈、麥穗都能載入成功）
            $user = [
                'name'   => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
                'avatar' => (!empty($db_user['avatar'])) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
                'donuts' => $db_user['donuts'] ?? 0,
                'wheat'  => $db_user['wheat'] ?? 0,
                'reading_progress' => 0 
            ];

            if ($db_user['last_signin_date'] !== date('Y-m-d')) {
                $show_signin_alert = true;
            }

            // 2. 獨立進行挑戰進度撈取（精準對齊資料庫真實欄位：c.target_count）
            try {
                $stmt_challenge = $pdo->prepare("
                    SELECT uc.progress_count as user_current, c.target_count as challenge_target 
                    FROM user_challenges uc
                    JOIN challenges c ON uc.challenge_id = c.id
                    WHERE uc.user_id = ? AND uc.status = 'progress'
                    LIMIT 1
                ");
                $stmt_challenge->execute([$_SESSION['user_id']]);
                $challenge_res = $stmt_challenge->fetch(PDO::FETCH_ASSOC);
                
                if ($challenge_res && $challenge_res['challenge_target'] > 0) {
                    $raw_prog = ($challenge_res['user_current'] / $challenge_res['challenge_target']) * 100;
                    $user['reading_progress'] = min(100, max(0, round($raw_prog))); 
                }
            } catch (PDOException $e_child) {
                // 即使挑戰進度模組發生未預期資料庫錯誤，也絕不阻塞與影響上方已載入的錢包與頭貼
                $user['reading_progress'] = 0;
            }
        } else {
            $is_logged_in = false;
        }
    } catch (PDOException $e) {
        $is_logged_in = false;
    }
}

function loginClass($is_logged_in) {
    return $is_logged_in ? '' : 'need-login';
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>書籍盲盒 ‧ 咬一口故事</title>

<style>
/* ================== 100% 同步主頁核心變數與背景網格 ================== */
:root {
  --bg:#FBF5EC;
  --bg-2:#F4E7D3;
  --surface:#FFFFFF;
  --surface-glass:rgba(255,255,255,.72);
  --ink:#2B1A0F;
  --muted:#9A8675;
  --line:#EDDFC8;
  --primary:#E36A4B;
  --primary-2:#F6A26B;
  --accent:#7BAE7F;
  --donut:#F5B7C4;
  --wheat:#D9A441;
  --radius:18px;
  --shadow-sm:0 4px 12px rgba(80,40,10,.06);
  --shadow:0 14px 40px rgba(80,40,10,.10);
  --shadow-lg:0 30px 80px rgba(80,40,10,.18);
  font-family:"Noto Sans TC","PingFang TC","Microsoft JhengHei",system-ui,sans-serif;
}
body[data-theme="dark"] {
  --bg:#1A1612;
  --bg-2:#13100E;
  --surface:#251F1A;
  --surface-glass:rgba(37,31,26,.8);
  --ink:#ECE4DC;
  --muted:#857365;
  --line:#3A322B;
  --shadow-sm:0 4px 12px rgba(0,0,0,.3);
  --shadow:0 14px 40px rgba(0,0,0,.4);
  --shadow-lg:0 30px 80px rgba(0,0,0,.5);
}
body[data-theme="cool"] {
  --bg:#EFF5F6;
  --bg-2:#DEE9EB;
  --surface:#FFFFFF;
  --surface-glass:rgba(255,255,255,.72);
  --ink:#1C2627;
  --muted:#7A8B8D;
  --line:#D1E1E3;
  --primary:#2D82B7;
  --primary-2:#42A5F5;
  --shadow-sm:0 4px 12px rgba(20,40,50,.05);
  --shadow:0 14px 40px rgba(20,40,50,.08);
}

*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
  color:var(--ink);min-height:100vh;display:flex;position:relative;overflow-x:hidden;
  background:
    radial-gradient(900px 600px at -10% -10%, #FFD9C2 0%, transparent 60%),
    radial-gradient(800px 600px at 110% 10%, #F8E6BE 0%, transparent 55%),
    radial-gradient(700px 500px at 50% 110%, #E9F1DE 0%, transparent 60%),
    linear-gradient(180deg, var(--bg), var(--bg-2));
}
body[data-theme="dark"] {
  background: linear-gradient(180deg, var(--bg), var(--bg-2));
}
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:radial-gradient(rgba(120,80,40,.05) 1px, transparent 1px);
  background-size:22px 22px;
}
a{color:inherit;text-decoration:none}

/* ================== 自製插畫型 Emojis 圖示 ================== */
.ic {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; flex-shrink: 0; font-style: normal;
  background: none !important; filter: drop-shadow(0 1px 1px rgba(0,0,0,.08));
  vertical-align: middle;
}
.ic::before { display: inline-block; text-indent: 0 !important; visibility: visible !important; }
.ic-home::before { content: '🏠'; }
.ic-search::before { content: '🔍'; }
.ic-bottle::before { content: '🍾'; }
.ic-user::before { content: '👤'; }
.ic-books::before { content: '📚'; }
.ic-cart::before { content: '🛒'; }
.ic-pen::before { content: '✍️'; }
.ic-openbook::before { content: '📖'; }
.ic-draft::before { content: '📁'; }
.ic-chart::before { content: '📊'; }
.ic-exit::before { content: '🚪'; }
.ic-gift::before { content: '🎁'; }
.ic-sparkle::before { content: '✨'; }
.ic-refresh::before { content: '🔄'; }
.ic-box::before { content: '🎁'; }
.ic-sm{width:20px;height:20px;font-size:14px}

/* ================== 100% 同步主頁桌機版側邊欄 ================== */
.sidebar{
  width:248px;flex-shrink:0;height:100vh;position:sticky;top:0;z-index:100;
  background:linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%);
  border-right:1px solid var(--line); padding:22px 16px;display:flex;flex-direction:column;gap:6px;
  transition:width .35s cubic-bezier(.4,.0,.2,1); box-shadow:6px 0 30px rgba(80,40,10,.04);
}
body[data-theme="dark"] .sidebar {
  background: linear-gradient(180deg,#2A231D 0%,#201A16 100%);
}
.sidebar.collapsed{width:78px;padding:22px 12px}
.logo{
  display:flex;align-items:center;gap:12px;padding:6px 10px;margin-bottom:14px;
  font-weight:800;font-size:19px;letter-spacing:.5px;color:var(--ink); overflow:hidden;white-space:nowrap;
}
.logo-mark {
  width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
  background: #fff url('img/logo.png') center/cover no-repeat !important; 
  box-shadow: var(--shadow); border: 1px solid var(--line); display: grid; place-items: center;
}
.sidebar.collapsed .logo-text{opacity:0;transform:translateX(-8px);pointer-events:none}
.logo-text{transition:.3s}

.toggle{
  position:absolute;top:30px;right:-14px;width:28px;height:28px;border-radius:50%;
  background:#fff;border:1px solid var(--line);color:var(--primary);
  display:grid;place-items:center;cursor:pointer;font-size:14px;font-weight:900;
  box-shadow:var(--shadow);transition:.3s;z-index:6;
}
body[data-theme="dark"] .toggle { background:#251F1A; }
.toggle:hover{transform:scale(1.1);background:var(--primary);color:#fff}
.sidebar.collapsed .toggle{transform:rotate(180deg)}

.nav{display:flex;flex-direction:column;gap:4px}
.nav a{
  display:flex;align-items:center;gap:14px;padding:11px 12px;border-radius:14px;
  color:var(--ink);font-size:15px;transition:.25s;position:relative; white-space:nowrap;overflow:hidden;
}
.nav a .label{transition:.3s}
.sidebar.collapsed .nav a{justify-content:center;padding:11px 8px}
.sidebar.collapsed .nav a .label{opacity:0;width:0;margin-left:-14px}
.nav a:hover{background:#FFEFDD;color:var(--primary)}
body[data-theme="dark"] .nav a:hover { background: #3A3026; }
.nav a.active{
  background:linear-gradient(135deg,#FFE4D2,#FFD3B6); color:var(--primary);font-weight:700;
  box-shadow:inset 0 0 0 1px rgba(227,106,75,.15);
}
body[data-theme="dark"] .nav a.active { background: linear-gradient(135deg,#4A3220,#3A2210); }
.nav a.active::before{
  content:"";position:absolute;left:0;top:18%;bottom:18%;width:3px;border-radius:3px;
  background:linear-gradient(180deg,var(--primary),var(--primary-2));
}
.sidebar.collapsed .nav a.active::before{display:none}
.divider{height:1px;background:linear-gradient(90deg,transparent,var(--line),transparent);margin:10px 4px}

.sidebar.collapsed .nav a:hover::after,
.sidebar.collapsed .publish-btn:hover::after{
  content:attr(data-label);position:absolute;left:calc(100% + 12px);top:50%;transform:translateY(-50%);
  background:var(--ink);color:var(--bg);padding:6px 12px;border-radius:8px;font-size:13px;white-space:nowrap;
  box-shadow:var(--shadow);z-index:99;
}

.publish{position:relative;margin-top:10px}
.publish-btn{
  width:100%;border:0;cursor:pointer; background:linear-gradient(135deg,var(--primary),var(--primary-2));
  color:#fff;font-weight:700;padding:13px;border-radius:16px;font-size:15px;
  box-shadow:0 10px 24px rgba(227,106,75,.35);transition:.25s;
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.publish-btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(227,106,75,.45)}
.sidebar.collapsed .publish-btn .label{display:none}
.publish-menu{
  position:absolute;left:0;right:0;top:calc(100% + 10px); background:var(--surface);border:1px solid var(--line);border-radius:16px;
  box-shadow:var(--shadow-lg);padding:8px;display:none;min-width:200px;z-index:110;
}
.sidebar.collapsed .publish-menu{left:calc(100% + 10px);right:auto;top:0}
.publish.open .publish-menu{display:block;animation:pop .25s ease}
@keyframes pop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.publish-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;white-space:nowrap}
.publish-menu a:hover{background:#FFEFDD;color:var(--primary)}

.logout{
  margin-top:auto;color:var(--muted);font-size:14px;padding:10px 12px;
  display:flex;align-items:center;gap:14px;border-radius:12px;transition:.2s; white-space:nowrap;overflow:hidden;
}
.logout:hover{background:#FAF1E6;color:var(--primary)}
.sidebar.collapsed .logout .label{display:none}

/* ================== 100% 同步主頁頂部功能列 ================== */
.main{flex:1;min-width:0;padding:24px 36px 80px;position:relative;z-index:1}
.topbar{display:flex;align-items:center;gap:14px;margin-bottom:28px;flex-wrap:wrap}
.search{
  flex:1;min-width:260px;display:flex;align-items:center;gap:10px;
  background:var(--surface-glass);backdrop-filter:blur(14px); border:1px solid rgba(255,255,255,.7);border-radius:999px;
  padding:11px 20px;box-shadow:var(--shadow);
}
body[data-theme="dark"] .search { border-color: var(--line); }
.search input{flex:1;border:0;outline:0;font-size:15px;background:transparent;color:var(--ink)}
.search input::placeholder{color:var(--muted)}

.challenge{
  display:flex;align-items:center;gap:10px;background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:9px 16px;
  box-shadow:var(--shadow);min-width:240px;font-size:14px; transition:.2s;
}
body[data-theme="dark"] .challenge { border-color: var(--line); }
.challenge:hover { transform: translateY(-2px); background: var(--surface); }
.challenge .bar{flex:1;height:8px;background:#F2E2C8;border-radius:99px;overflow:hidden;position:relative}
body[data-theme="dark"] .challenge .bar { background: #3A322B; }
.challenge .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-2));border-radius:999px;}

.top-btn {
  display: inline-flex; align-items: center; gap: 8px; background: var(--surface-glass); backdrop-filter: blur(14px);
  border: 1px solid rgba(255,255,255,.7); border-radius: 999px; padding: 9px 16px; box-shadow: var(--shadow); font-size: 14px;
  color: var(--ink); transition: .25s; cursor: pointer;
}
body[data-theme="dark"] .top-btn { border-color: var(--line); }
.top-btn:hover { background: linear-gradient(135deg,var(--primary),var(--primary-2)); color: #fff; border-color: transparent; transform: translateY(-2px); }

.points{display:flex;gap:10px}
.point{
  display:flex;align-items:center;gap:8px;background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:5px 14px 5px 6px; font-weight:800;box-shadow:var(--shadow);font-size:14px;
}
body[data-theme="dark"] .point { border-color: var(--line); }
.point img { width: 26px; height: 26px; object-fit: contain; }
.point.donut{color:#D6557A}
.point.wheat{color:#A87A1F}

.avatar-wrap{position:relative; cursor: pointer; display: inline-block;}
.avatar-wrap::after{
  content:"";position:absolute;inset:-3px;border-radius:50%;
  background:conic-gradient(from 180deg,var(--primary),var(--primary-2),var(--donut),var(--primary)); z-index:-1;
}
.avatar{ width:46px;height:46px;border-radius:50%;object-fit:cover; border:3px solid #fff;box-shadow:var(--shadow);display:block; }
body[data-theme="dark"] .avatar { border-color: var(--surface); }

.avatar-popover {
  position: absolute; top: calc(100% + 12px); right: 0; width: 280px;
  background: linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%); border: 1px solid var(--line); border-radius: 20px;
  padding: 20px; box-shadow: var(--shadow-lg); opacity: 0; visibility: hidden; transform: translateY(10px); transition: .3s; z-index: 150; text-align: center;
}
body[data-theme="dark"] .avatar-popover { background: linear-gradient(180deg,#2A231D 0%,#201A16 100%); }
.avatar-wrap:hover .avatar-popover { opacity: 1; visibility: visible; transform: translateY(0); }
.popover-btn {
  display: block; width: 100%; padding: 10px; background: linear-gradient(135deg,var(--primary),var(--primary-2));
  color: #fff; font-weight: 700; border-radius: 12px; box-shadow: 0 4px 10px rgba(227,106,75,0.2); text-align: center; font-size: 14px; margin-top: 12px;
}

.signin-bubble-top {
    position: absolute; top: -14px; right: -25px; background: #FF6B6B; color: white; font-size: 11px; font-weight: bold; padding: 3px 9px; border-radius: 20px; white-space: nowrap; box-shadow: 0 4px 10px rgba(255, 107, 107, 0.4); animation: bounceTop 2s infinite ease-in-out; z-index: 10;
}
@keyframes bounceTop { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }

/* ================== 🧱 盲盒專區專屬內頁排版 ================== */
.section{margin-bottom:42px;position:relative}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.section-head .h{display:flex;align-items:center;gap:12px}
.section-head .h .deco{ width:6px;height:26px;border-radius:999px; background:linear-gradient(180deg,var(--primary),var(--primary-2)); }
.section-head h3{font-size:21px;letter-spacing:.5px;display:flex;align-items:center;gap:10px}

.blindbox-container {
  background: var(--surface); padding: 40px; border-radius: var(--radius); box-shadow: var(--shadow-sm); border: 1px solid var(--line); text-align: center; max-width: 700px; margin: 0 auto;
}
.blindbox-desc { color: var(--muted); font-size: 14px; margin-top: 8px; margin-bottom: 24px; }

/* 盲盒玩法切換按鈕組 */
.mode-tabs { display: inline-flex; background: var(--bg-2); padding: 4px; border-radius: 99px; margin-bottom: 30px; border: 1px solid var(--line); }
.mode-tab-btn { border: 0; background: transparent; padding: 8px 20px; font-size: 14px; font-weight: 700; border-radius: 99px; cursor: pointer; color: var(--muted); transition: .3s; }
.mode-tab-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: #fff; box-shadow: var(--shadow-sm); }

/* 盲盒互動大舞台 */
.stage-area { position: relative; height: 260px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }

/* 盲盒外觀設計 */
.gift-box {
  width: 140px; height: 140px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); border-radius: 20px;
  box-shadow: 0 12px 28px rgba(227,106,75,0.3), inset 0 -6px 0 rgba(0,0,0,0.12);
  display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; transition: transform 0.2s;
}
.gift-box::after { content: "？"; font-size: 3.5rem; color: #fff; font-weight: 900; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
.gift-box:hover { transform: scale(1.06) rotate(2deg); }

/* 精緻開箱抖動動畫 */
@keyframes boxShake {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  15% { transform: translate(-6px, -3px) rotate(-4deg); }
  30% { transform: translate(6px, 3px) rotate(4deg); }
  45% { transform: translate(-6px, 3px) rotate(-4deg); }
  60% { transform: translate(6px, -3px) rotate(4deg); }
  75% { transform: scale(0.5); opacity: 0.5; }
  100% { transform: scale(0); opacity: 0; }
}
.gift-box.shaking { animation: boxShake 1.1s forwards ease-in-out; pointer-events: none; }

/* 抽中卡片呈現 */
.result-card {
  display: none; opacity: 0; transform: scale(0.85); max-width: 220px; text-align: left; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.2);
}
.result-card.reveal { display: block; opacity: 1; transform: scale(1); }
.result-card .cover { aspect-ratio: 3/4; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--line); margin-bottom: 12px; }
.result-card .cover img { width: 100%; height: 100%; object-fit: cover; }
.result-card .title { font-weight: 700; font-size: 15px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--ink); }
.result-card .author { font-size: 12px; color: var(--muted); margin-bottom: 8px; }
.result-card .tag-pill { display: inline-block; font-size: 11px; padding: 2px 8px; background: #FFEFDD; color: var(--primary); font-weight: 700; border-radius: 99px; }
body[data-theme="dark"] .result-card .tag-pill { background: #3A322B; }

/* 功能主控大按鈕 */
.action-draw-btn {
  border: 0; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: #fff; font-weight: 700;
  padding: 12px 36px; border-radius: 99px; font-size: 15px; cursor: pointer; box-shadow: 0 8px 20px rgba(227,106,75,0.3); transition: .25s;
}
.action-draw-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(227,106,75,0.4); }

/* 觀看紀錄鎖定遮罩 */
.lock-overlay { display: none; position: absolute; inset: 0; background: rgba(255,252,246,0.94); border-radius: var(--radius); flex-direction: column; align-items: center; justify-content: center; gap: 12px; z-index: 10; }
body[data-theme="dark"] .lock-overlay { background: rgba(37,31,26,0.96); }

/* ================== 100% 同步主頁手機/彈窗/燈箱基礎 ================== */
.mobile-nav{
  display:none;position:fixed;bottom:0;left:0;right:0;height:66px;
  background:rgba(255,252,246,0.92);backdrop-filter:blur(20px); border-top:1px solid var(--line);z-index:100; justify-content:space-around;align-items:center;padding:0 10px;
}
body[data-theme="dark"] .mobile-nav { background: rgba(26,22,18,0.92); }
.mobile-nav a, .mobile-nav .mob-pub-trigger{ display:flex;flex-direction:column;align-items:center;gap:4px; color:var(--muted);font-size:11px;font-weight:500; background: transparent; border: 0; cursor: pointer; }
.mobile-nav a.active{color:var(--primary);font-weight:700}
.mobile-nav .center-pub-btn {
  position: relative; top: -14px; width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-2));
  box-shadow: 0 6px 16px rgba(227,106,75,0.4); display: grid; place-items: center; color: #fff; font-size: 24px;
}
.mobile-publish-sheet {
  position: fixed; bottom: -100%; left: 0; right: 0; background: var(--surface); border-top-left-radius: 24px; border-top-right-radius: 24px; box-shadow: 0 -10px 30px rgba(0,0,0,0.15); z-index: 200; padding: 24px; transition: bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.mobile-publish-sheet.open { bottom: 0; }
.mobile-publish-sheet .sheet-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.mobile-publish-sheet .sheet-item { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 16px 8px; border-radius: 16px; background: var(--bg-2); font-size: 14px; }
.mobile-publish-sheet .sheet-close { margin-top: 18px; width: 100%; padding: 12px; border-radius: 99px; border: 1px solid var(--line); background: var(--surface); font-weight: 700; cursor: pointer; color: var(--ink); }

.modal-overlay { position: fixed; inset: 0; background: rgba(43,26,15,0.4); backdrop-filter: blur(4px); z-index: 300; display: none; place-items: center; }
.user-card-modal {
  background: linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%); width: 90%; max-width: 360px; border-radius: 24px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--line); position: relative; text-align: center;
}
body[data-theme="dark"] .user-card-modal { background: linear-gradient(180deg,#2A231D 0%,#201A16 100%); }
.user-card-modal .close-btn { position: absolute; top: 16px; right: 16px; font-size: 20px; cursor: pointer; color: var(--muted); background:none; border:0; }
.modal-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid #fff; box-shadow: var(--shadow); margin-bottom: 12px; }
.modal-username { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.modal-stat-box { background: var(--surface); padding: 10px 16px; border-radius: 14px; flex: 1; box-shadow: var(--shadow-sm); border: 1px solid var(--line); }
.modal-btn { display: block; width: 100%; padding: 12px; background: linear-gradient(135deg,var(--primary),var(--primary-2)); color: #fff; font-weight: 700; border-radius: 14px; text-align: center; margin-top: 16px; }

@media (max-width: 768px) {
  .sidebar { display: none; } .mobile-nav { display: flex; } .main { padding: 16px 16px 100px; } .topbar { gap: 12px; }
  .search { order: 1; min-width: 100%; } .challenge { order: 2; flex: 1; min-width: auto; } .top-btn { order: 3; } .points { order: 4; } .avatar-wrap { order: 5; } .avatar-popover { display: none !important; }
}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="收合 / 展開">‹</button>

  <div class="logo">
    <div class="logo-mark">📖</div>
    <span class="logo-text">咬一口故事</span>
  </div>

  <nav class="nav">
    <a href="index.php" data-label="首頁"><span class="ic ic-home"></span><span class="label">首頁</span></a>
    <a href="explore.php" data-label="探索"><span class="ic ic-search"></span><span class="label">探索</span></a>
    <a href="bottle.php" class="<?= loginClass($is_logged_in) ?>" data-label="漂流瓶"><span class="ic ic-bottle"></span><span class="label">漂流瓶</span></a>
    <div class="divider"></div>
    <a href="profile.php" class="<?= loginClass($is_logged_in) ?>" data-label="個人頁"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
    <a href="bookshelf.php" class="<?= loginClass($is_logged_in) ?>" data-label="我的書架"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
    <a href="cart.php" class="<?= loginClass($is_logged_in) ?>" data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
  </nav>

  <div class="publish <?= loginClass($is_logged_in) ?>" id="publish">
    <button class="publish-btn" data-label="發布新作" onclick="if(document.body.classList.contains('not-logged-in')) return; event.stopPropagation(); document.getElementById('publish').classList.toggle('open')">
      <span class="ic ic-pen"></span><span class="label">發布新作</span>
    </button>
    <div class="publish-menu">
      <a href="create.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800; margin-bottom: 4px;">➕ 開始寫故事 / 投稿</a>
      <div class="divider" style="margin: 4px 0;"></div>
      <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
      <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
      
    </div>
  </div>

  <?php if($is_logged_in): ?>
    <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
  <?php else: ?>
    <a class="logout" href="login.php"><span class="ic ic-sm ic-user"></span><span class="label">登入</span></a>
  <?php endif; ?>
</aside>

<nav class="mobile-nav">
  <a href="index.php"><span class="ic ic-home"></span>首頁</a>
  <a href="explore.php"><span class="ic ic-search"></span>探索</a>
  <button class="mob-pub-trigger <?= loginClass($is_logged_in) ?>" onclick="if(!document.body.classList.contains('not-logged-in')) toggleMobileSheet(true)">
    <div class="center-pub-btn">＋</div>
  </button>
  <a href="bookshelf.php" class="<?= loginClass($is_logged_in) ?>"><span class="ic ic-books"></span>書架</a>
  <a href="javascript:void(0)" onclick="openUserModal()"><span class="ic ic-user"></span>我的</a>
</nav>

<div class="mobile-publish-sheet" id="mobileSheet">
  <h4>選擇創作動作</h4>
  <div class="sheet-grid">
    <a href="create.php" class="sheet-item"><span>✍️</span><strong>開始寫故事</strong></a>
    <a href="works.php" class="sheet-item"><span>📖</span>我的作品</a>
    <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
    
  </div>
  <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<div class="modal-overlay" id="userModal" onclick="closeUserModal(event)">
  <div class="user-card-modal" onclick="event.stopPropagation()">
    <button class="close-btn" onclick="closeUserModal(null)">×</button>
    <?php if($is_logged_in && $user !== null): ?>
      <img class="modal-avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
      <div class="modal-username"><?= htmlspecialchars($user['name']) ?></div>
      <div style="display: flex; gap: 12px; justify-content: center; margin: 16px 0;">
        <div class="modal-stat-box">🍩 <?= number_format($user['donuts']) ?></div>
        <div class="modal-stat-box">🌾 <?= number_format($user['wheat']) ?></div>
      </div>
      <a href="profile.php" class="modal-btn">進入個人中心</a>
    <?php else: ?>
      <div style="font-size: 50px; margin-bottom: 12px;">🦊</div>
      <div class="modal-username" style="margin-bottom: 10px;">歡迎來到故事盒子</div>
      <a href="login.php" class="modal-btn">立即登入 / 註冊</a>
    <?php endif; ?>
  </div>
</div>

<main class="main <?= !$is_logged_in ? 'not-logged-in' : '' ?>">

  <div class="topbar">
    <form class="search" action="explore.php" method="get">
      <span class="ic ic-sm ic-search"></span>
      <input name="q" placeholder="搜尋書名、作者、標籤…">
    </form>

    <a href="challenge.php" class="challenge <?= loginClass($is_logged_in) ?>">
      <span class="ic ic-sm ic-openbook"></span>
      <span>閱讀挑戰</span>
      <div class="bar"><i style="width:<?= ($is_logged_in && $user !== null) ? (int)$user['reading_progress'] : 0 ?>%"></i></div>
      <strong><?= ($is_logged_in && $user !== null) ? (int)$user['reading_progress'] : 0 ?>%</strong>
    </a>

    <a class="top-btn" href="tasks.php"><span class="ic ic-sm ic-sparkle"></span> 每日任務</a>
    <a class="top-btn" href="signin.php" style="position: relative;">
        <span class="ic ic-sm ic-gift"></span> 每日簽到
        <?php if ($show_signin_alert): ?>
            <span class="signin-bubble-top">記得簽到 🍩</span>
        <?php endif; ?>
    </a>

    <div class="points">
      <div class="point donut" title="甜甜圈點數">
        <img src="img/coin-donut.png" alt=""> <?= ($is_logged_in && $user !== null) ? number_format($user['donuts']) : 0 ?>
      </div>
      <div class="point wheat" title="麥穗點數">
        <img src="img/coin-wheat.png" alt=""> <?= ($is_logged_in && $user !== null) ? number_format($user['wheat']) : 0 ?>
      </div>
    </div>

    <div class="avatar-wrap" onclick="<?= $is_logged_in ? "location.href='profile.php'" : "openUserModal()" ?>">
      <img class="avatar" src="<?= ($is_logged_in && $user !== null) ? htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?img=99' ?>" alt="User">
      <div class="avatar-popover" onclick="event.stopPropagation()">
        <?php if($is_logged_in && $user !== null): ?>
          <div style="font-weight:700; color:var(--ink);">您好，<?= htmlspecialchars($user['name']) ?></div>
          <a href="profile.php" class="popover-btn">進入個人中心</a>
        <?php else: ?>
          <div style="font-weight:700; color:var(--ink);">訪客您好，尚未登入</div>
          <a href="login.php" class="popover-btn">立即登入</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <section class="section">
    <div class="section-head">
      <div class="h"><span class="deco"></span><h3>書海尋寶 ‧ 書籍盲盒</h3></div>
    </div>
    
    <div class="blindbox-container">
      <p class="blindbox-desc">不知道今天想閱讀什麼內容嗎？讓命運的幸運魔盒為你挑選一本精彩故事吧！</p>
      
      <div class="mode-tabs">
        <button class="mode-tab-btn active" onclick="switchMode('random', this)">全站隨機抽</button>
        <button class="mode-tab-btn" onclick="switchMode('history', this, <?= $is_logged_in ? 'true' : 'false' ?>)">猜你想看 (依觀看紀錄)</button>
      </div>

      <div class="stage-area">
        <div class="gift-box" id="magicBox" onclick="startDraw()"></div>

        <div class="result-card" id="resultCard">
          <div class="cover"><img src="" alt="" id="resCover"></div>
          <div class="title" id="resTitle">加載中...</div>
          <div class="author" id="resAuthor">作者</div>
          <div style="margin-bottom: 12px;"><span class="tag-pill" id="resTag">標籤</span></div>
          <a href="#" class="action-draw-btn" id="resLink" style="display:block; text-align:center; padding: 8px;">立即閱讀 📖</a>
        </div>

        <div class="lock-overlay" id="lockOverlay">
          <span style="font-size: 28px;">🔒</span>
          <p style="font-size: 14px; font-weight:700;">「猜你想看」模式需要登入後才能分析數據喔！</p>
          <button class="action-draw-btn" style="padding: 6px 16px; font-size:12px;" onclick="openUserModal()">前往登入</button>
        </div>
      </div>

      <button class="action-draw-btn" id="drawBtn" onclick="startDraw()">開啟幸運盲盒</button>
    </div>
  </section>
</main>

<script>
// 1. 宣告全域變數
let isDrawing = false;
let currentMode = 'random';
const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;

// 2. 切換模式
function switchMode(mode, btn) {
    if (isDrawing) return;
    currentMode = mode;
    document.querySelectorAll('.mode-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const lock = document.getElementById('lockOverlay');
    const drawBtn = document.getElementById('drawBtn');
    
    if (mode === 'history' && !isLoggedIn) {
        lock.style.display = 'flex';
        drawBtn.style.display = 'none';
    } else {
        lock.style.display = 'none';
        drawBtn.style.display = 'inline-block';
    }
    resetBox();
}

// 3. 重設開箱狀態
function resetBox() {
    const box = document.getElementById('magicBox');
    const card = document.getElementById('resultCard');
    box.style.display = 'flex';
    box.classList.remove('shaking');
    card.classList.remove('reveal');
    document.getElementById('drawBtn').innerText = '開啟幸運盲盒';
}

// 4. 開始抽獎邏輯 (最重要：這裡的括號務必對齊)
function startDraw() {
    if (isDrawing) return;
    isDrawing = true;

    const box = document.getElementById('magicBox');
    const card = document.getElementById('resultCard');
    const drawBtn = document.getElementById('drawBtn');

    box.classList.add('shaking');

    fetch('blindbox.php?action=draw&mode=' + currentMode)
        .then(response => response.text())
        .then(text => {
            const cleanText = text.trim();
            try {
                const data = JSON.parse(cleanText);
                setTimeout(() => {
                    box.classList.remove('shaking');
                    if (data.success) {
                        const coverWrap = document.querySelector('.result-card .cover');
                        if (data.cover_type === 'upload' && data.cover_image) {
                            coverWrap.innerHTML = `<img src="${data.cover_image}" alt="">`;
                        } else {
                            // 和 explore.php 一樣，用書名文字代替封面
                            coverWrap.innerHTML = `<div style="width:100%;height:100%;background:linear-gradient(135deg,var(--primary),var(--primary-2));display:flex;align-items:center;justify-content:center;padding:16px;text-align:center;color:#fff;font-weight:900;font-size:16px;line-height:1.5;">${data.title}</div>`;
                        }
                        document.getElementById('resTitle').innerText = data.title;
                        document.getElementById('resAuthor').innerText = '作者：' + data.author;
                        document.getElementById('resTag').innerText = data.tag;
                        document.getElementById('resLink').href = 'story.php?id=' + data.id;
                        box.style.display = 'none';
                        card.classList.add('reveal');
                        drawBtn.innerText = '再抽一次 🔄';
                    } else {
                        alert(data.message);
                    }
                    isDrawing = false;
                }, 1100);
            } catch (e) {
                alert("JSON解析錯誤，請確認 PHP 是否仍有輸出");
                isDrawing = false;
            }
        })
        .catch(error => {
            console.error('連線錯誤:', error);
            isDrawing = false;
        });
}
</script>
</body>
</html>