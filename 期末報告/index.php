<?php
// 咬一口故事 - 首頁 index.php (資料庫整合與側邊欄安全防禦完美版)
session_start();
// 🎯 關鍵修正：強制指定為台灣時區，讓午夜 00:00 一到，簽到小紅泡泡立刻亮起！
date_default_timezone_set('Asia/Taipei');
require_once 'db.php'; // 引入你的資料庫連線

// 🎯 1. 核心控制：從 Session 判斷是否真正登入
$is_logged_in = isset($_SESSION['user_id']);
$user = null;
$show_signin_alert = false; // ✨ 新增：預設不顯示提示

if ($is_logged_in) {
    try {
        // 從資料庫即時撈取該使用者的最新動態數據
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_user) {
            // 🎯 【精準撈取挑戰進度】：對齊資料庫真實欄位 progress_count
            $stmt_challenge = $pdo->prepare("
                SELECT uc.progress_count as user_current, c.progress_count as challenge_target 
                FROM user_challenges uc
                JOIN challenges c ON uc.challenge_id = c.id
                WHERE uc.user_id = ? AND uc.status = 'progress'
                LIMIT 1
            ");
            $stmt_challenge->execute([$_SESSION['user_id']]);
            $challenge_res = $stmt_challenge->fetch(PDO::FETCH_ASSOC);
            
            // 安全計算挑戰進度百分比公式：(目前進度 / 目標進度) * 100
            if ($challenge_res && $challenge_res['challenge_target'] > 0) {
                $raw_prog = ($challenge_res['user_current'] / $challenge_res['challenge_target']) * 100;
                $real_progress = min(100, max(0, round($raw_prog))); // 確保在 0% ~ 100% 之間
            } else {
                $real_progress = 0; // 如果目前沒有進行中的挑戰，預設為 0%
            }

            // 💡 欄位名稱完美對應你原本 HTML 畫面的 $user['name']、['avatar'] 等
            $user = [
                'name'   => $db_user['nickname'] ?? $db_user['username'],
                'avatar' => (!empty($db_user['avatar'])) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
                'donuts' => $db_user['donuts'] ?? 0,
                'wheat'  => $db_user['wheat'] ?? 0,
                'reading_progress' => $real_progress // ✨ 完美換成實時進度
            ];

            // ✨ 新增核心邏輯：如果資料庫記的日期不是今天，代表今天「還沒簽到」！
            if ($db_user['last_signin_date'] !== date('Y-m-d')) {
                $show_signin_alert = true;
            }

        } else {
            $is_logged_in = false;
        }
    } catch (PDOException $e) {
        // 💡 為了防止資料庫出錯時把你當作未登入踢掉，改成只將進度設為 0，讓你可以繼續留在頁面
        $real_progress = 0;
        if (isset($db_user)) {
            $user = [
                'name'   => $db_user['nickname'] ?? $db_user['username'],
                'avatar' => (!empty($db_user['avatar'])) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47',
                'donuts' => $db_user['donuts'] ?? 0,
                'wheat'  => $db_user['wheat'] ?? 0,
                'reading_progress' => 0
            ];
        }
    }
}

// 🎯 2. 盲盒推薦基本資料
$blindbox = [
    'title'    => '盲盒推薦 ‧ 猜你想看',
    'subtitle' => '今日為你挑選了一本神秘好故事',
    'cover'    => 'https://picsum.photos/seed/blindbox123/1200/400', 
];

// 🎯 3. 完整保留你原本超厲害的生假資料功能，作為資料庫空無一人時的「安全防護網」
function fakeBooks($seedPrefix, $n = 5) {
    $titles = ['月光下的信','雨夜的咖啡店','少年與星辰','黑貓不說話','第七封情書','風的旅行','機械之心','銀河彼端','深海回聲','紙飛機計畫'];
    $authors = ['林默','江語','陸時','顧昭','沈知','夏螢','秦野','蘇晚','白川','許願'];
    $list = [];
    for ($i = 0; $i < $n; $i++) {
        $list[] = [
            'cover'  => "https://picsum.photos/seed/{$seedPrefix}{$i}/400/560",
            'title'  => $titles[($i + crc32($seedPrefix)) % count($titles)],
            'author' => $authors[($i + crc32($seedPrefix)) % count($authors)],
            'tag'    => ['奇幻','言情','懸疑','治癒','科幻'][$i % 5]
        ];
    }
    return $list;
}

// 🎯 4. 真實故事撈取機制：對齊資料庫真實欄位 type 與資料表 stories
try {
    // 主打精選 (featured) -> 對應資料庫 type = 'featured'
    $stmt = $pdo->prepare("SELECT title, author, cover, tag FROM stories WHERE type = 'featured' LIMIT 5");
    $stmt->execute();
    $featured = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 短篇故事 (short) -> 對應資料庫 type = 'short'
    $stmt = $pdo->prepare("SELECT title, author, cover, tag FROM stories WHERE type = 'short' LIMIT 5");
    $stmt->execute();
    $shorts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 漂流瓶 (bottle) -> 對應資料庫 type = 'bottle'
    $stmt = $pdo->prepare("SELECT title, author, cover, tag FROM stories WHERE type = 'bottle' LIMIT 5");
    $stmt->execute();
    $bottles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Exception $e) {
    // 如果有任何 SQL 語法錯誤，暫時可以將下方取消註解來排查：
    // die("SQL 錯誤: " . $e->getMessage());
    
    $featured = [];
    $shorts   = [];
    $bottles  = [];
}

// 🎯 5. ✨關鍵救援✨：補齊你原本 index5.php 側邊欄與標籤必備的變數與 Helper 函數！
$tags = ['#校園','#奇幻冒險','#治癒系','#黑暗童話','#雙男主','#古風','#科幻','#懸疑推理'];

function loginClass($is_logged_in) {
    return $is_logged_in ? '' : 'need-login';
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&display=swap" rel="stylesheet">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ 首頁</title>
<meta name="description" content="咬一口故事 — 一個溫暖的線上電子書與同人文閱讀平台，每天為你推薦一本好故事。">

<link rel="stylesheet" href="theme.css">

<style>
:root{
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
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:radial-gradient(rgba(120,80,40,.05) 1px, transparent 1px);
  background-size:22px 22px;
}
a{color:inherit;text-decoration:none}

/* ============ 自製插畫圖示 ============ */
.ic {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  flex-shrink: 0;
  font-style: normal;
  background: none !important; 
  filter: drop-shadow(0 1px 1px rgba(0,0,0,.08));
  vertical-align: middle;
}
.ic::before {
  display: inline-block;
  text-indent: 0 !important;
  visibility: visible !important;
}

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
.ic-free::before { content: '🆓'; }
.ic-hash::before { content: '🏷️'; }
.ic-refresh::before { content: '🔄'; }

.ic-lg{width:34px;height:34px;font-size:24px}
.ic-sm{width:20px;height:20px;font-size:14px}

/* ============ 整合：第一版小插圖精緻樣式 ============ */
.section-head h3 .deco-inline {
  width: 36px;
  height: 36px;
  object-fit: contain;
  margin-left: 8px;
  filter: drop-shadow(0 4px 8px rgba(80,40,10,.1));
  vertical-align: middle;
  display: inline-block;
}
.tags-wrap .deco-side {
  width: 130px;
  height: 130px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 10px 24px rgba(80,40,10,.12));
  animation: floaty 6s ease-in-out infinite;
}
@keyframes floaty {
  0%, 100% { transform: translateY(0) rotate(0); }
  50% { transform: translateY(-10px) rotate(-2deg); }
}

/* ============ Sidebar (桌機版) ============ */
.sidebar{
  width:248px;flex-shrink:0;height:100vh;position:sticky;top:0;z-index:100;
  background:linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%);
  border-right:1px solid var(--line);
  padding:22px 16px;display:flex;flex-direction:column;gap:6px;
  transition:width .35s cubic-bezier(.4,.0,.2,1);
  box-shadow:6px 0 30px rgba(80,40,10,.04);
}
.sidebar.collapsed{width:78px;padding:22px 12px}

.logo{
  display:flex;align-items:center;gap:12px;padding:6px 10px;margin-bottom:14px;
  font-weight:800;font-size:19px;letter-spacing:.5px;color:var(--ink);
  overflow:hidden;white-space:nowrap;
}
.logo-mark {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  flex-shrink: 0;
  background: #fff url('img/logo.png') center/cover no-repeat !important; 
  box-shadow: var(--shadow);
  border: 1px solid var(--line);
  display: grid;
  place-items: center;
  font-size: 0 !important;
  line-height: 0;
  color: transparent !important;
}
.sidebar.collapsed .logo-text{opacity:0;transform:translateX(-8px);pointer-events:none}
.logo-text{transition:.3s}

.toggle{
  position:absolute;top:30px;right:-14px;width:28px;height:28px;border-radius:50%;
  background:#fff;border:1px solid var(--line);color:var(--primary);
  display:grid;place-items:center;cursor:pointer;font-size:14px;font-weight:900;
  box-shadow:var(--shadow);transition:.3s;z-index:6;
}
.toggle:hover{transform:scale(1.1);background:var(--primary);color:#fff}
.sidebar.collapsed .toggle{transform:rotate(180deg)}

/* Nav */
.nav{display:flex;flex-direction:column;gap:4px}
.nav a{
  display:flex;align-items:center;gap:14px;padding:11px 12px;border-radius:14px;
  color:var(--ink);font-size:15px;transition:.25s;position:relative;
  white-space:nowrap;overflow:hidden;
}
.nav a .label{transition:.3s}
.sidebar.collapsed .nav a{justify-content:center;padding:11px 8px}
.sidebar.collapsed .nav a .label{opacity:0;width:0;margin-left:-14px}
.nav a:hover{background:#FFEFDD;color:var(--primary)}
.nav a.active{
  background:linear-gradient(135deg,#FFE4D2,#FFD3B6);
  color:var(--primary);font-weight:700;
  box-shadow:inset 0 0 0 1px rgba(227,106,75,.15);
}
.nav a.active::before{
  content:"";position:absolute;left:0;top:18%;bottom:18%;width:3px;border-radius:3px;
  background:linear-gradient(180deg,var(--primary),var(--primary-2));
}
.sidebar.collapsed .nav a.active::before{display:none}
.divider{height:1px;background:linear-gradient(90deg,transparent,var(--line),transparent);margin:10px 4px}

/* Tooltip */
.sidebar.collapsed .nav a:hover::after,
.sidebar.collapsed .publish-btn:hover::after{
  content:attr(data-label);position:absolute;left:calc(100% + 12px);top:50%;transform:translateY(-50%);
  background:var(--ink);color:#fff;padding:6px 12px;border-radius:8px;font-size:13px;white-space:nowrap;
  box-shadow:var(--shadow);z-index:99;
}

/* 發布新作 */
.publish{position:relative;margin-top:10px}
.publish-btn{
  width:100%;border:0;cursor:pointer;
  background:linear-gradient(135deg,var(--primary),var(--primary-2));
  color:#fff;font-weight:700;padding:13px;border-radius:16px;font-size:15px;
  box-shadow:0 10px 24px rgba(227,106,75,.35);transition:.25s;
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.publish-btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(227,106,75,.45)}
.sidebar.collapsed .publish-btn .label{display:none}
.publish-menu{
  position:absolute;left:0;right:0;top:calc(100% + 10px);
  background:#fff;border:1px solid var(--line);border-radius:16px;
  box-shadow:var(--shadow-lg);padding:8px;display:none;min-width:200px;z-index:110;
}
.sidebar.collapsed .publish-menu{left:calc(100% + 10px);right:auto;top:0}
.publish.open .publish-menu{display:block;animation:pop .25s ease}
@keyframes pop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.publish-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;white-space:nowrap}
.publish-menu a:hover{background:#FFEFDD;color:var(--primary)}

.logout{
  margin-top:auto;color:var(--muted);font-size:14px;padding:10px 12px;
  display:flex;align-items:center;gap:14px;border-radius:12px;transition:.2s;
  white-space:nowrap;overflow:hidden;
}
.logout:hover{background:#FAF1E6;color:var(--primary)}
.sidebar.collapsed .logout .label{display:none}

/* ============ 手機版底部導覽列 ============ */
.mobile-nav{
  display:none;position:fixed;bottom:0;left:0;right:0;height:66px;
  background:rgba(255,252,246,0.92);backdrop-filter:blur(20px);
  border-top:1px solid var(--line);z-index:100;
  justify-content:space-around;align-items:center;padding:0 10px;
  box-shadow:0 -4px 20px rgba(80,40,10,.05);
}
.mobile-nav a, .mobile-nav .mob-pub-trigger{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  color:var(--muted);font-size:11px;font-weight:500;transition:.2s;
  cursor: pointer; background: transparent; border: 0;
}
.mobile-nav a.active{color:var(--primary);font-weight:700}

.mobile-nav .center-pub-btn {
  position: relative; top: -14px;
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-2));
  box-shadow: 0 6px 16px rgba(227,106,75,0.4);
  display: grid; place-items: center; color: #fff; font-size: 24px;
}

.mobile-publish-sheet {
  position: fixed; bottom: -100%; left: 0; right: 0;
  background: #fff; border-top-left-radius: 24px; border-top-right-radius: 24px;
  box-shadow: 0 -10px 30px rgba(0,0,0,0.15); z-index: 200;
  padding: 24px; transition: bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.mobile-publish-sheet.open { bottom: 0; }
.mobile-publish-sheet h4 { font-size: 18px; margin-bottom: 16px; text-align: center; }
.mobile-publish-sheet .sheet-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.mobile-publish-sheet .sheet-item {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 16px 8px; border-radius: 16px; background: #FFF9F2; font-size: 14px;
}
.mobile-publish-sheet .sheet-close {
  margin-top: 18px; width: 100%; padding: 12px; border-radius: 99px;
  border: 1px solid var(--line); background: #fdfdfd; font-weight: 700; cursor: pointer;
}

/* ============ Main ============ */
.main{flex:1;min-width:0;padding:24px 36px 80px;position:relative;z-index:1}

/* 頂部列與全新按鈕調校 */
.topbar{display:flex;align-items:center;gap:14px;margin-bottom:28px;flex-wrap:wrap}
.search{
  flex:1;min-width:260px;display:flex;align-items:center;gap:10px;
  background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;
  padding:11px 20px;box-shadow:var(--shadow);
}
.search input{flex:1;border:0;outline:0;font-size:15px;background:transparent;color:var(--ink)}
.search input::placeholder{color:var(--muted)}

.challenge{
  display:flex;align-items:center;gap:10px;background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:9px 16px;
  box-shadow:var(--shadow);min-width:240px;font-size:14px;
  cursor: pointer;
  transition: transform 0.2s ease, background-color 0.2s ease;
}
/* 💡 新增 hover 懸浮效果 */
.challenge:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.9);
}
.challenge .bar{flex:1;height:8px;background:#F2E2C8;border-radius:99px;overflow:hidden;position:relative}
.challenge .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-2));border-radius:999px;box-shadow:0 0 12px rgba(227,106,75,.4)}

/* 整合第一版精美好框線按鈕 */
.top-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--surface-glass); backdrop-filter: blur(14px);
  border: 1px solid rgba(255,255,255,.7); border-radius: 999px;
  padding: 9px 16px; box-shadow: var(--shadow); font-size: 14px;
  color: var(--ink); transition: .25s; cursor: pointer;
}
.top-btn:hover {
  background: linear-gradient(135deg,var(--primary),var(--primary-2)); color: #fff; 
  border-color: transparent; transform: translateY(-2px);
}

.points{display:flex;gap:10px}
.point{
  display:flex;align-items:center;gap:8px;background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:5px 14px 5px 6px;
  font-weight:800;box-shadow:var(--shadow);font-size:14px;
}
.point img { width: 26px; height: 26px; object-fit: contain; }
.point.donut{color:#D6557A}
.point.wheat{color:#A87A1F}

/* 桌機版頭像外框線與 Hover 懸浮卡片設計 */
.avatar-wrap{position:relative; cursor: pointer; display: inline-block;}
.avatar-wrap::after{
  content:"";position:absolute;inset:-3px;border-radius:50%;
  background:conic-gradient(from 180deg,var(--primary),var(--primary-2),var(--donut),var(--primary));
  z-index:-1;
}
.avatar{
  width:46px;height:46px;border-radius:50%;object-fit:cover;
  border:3px solid #fff;box-shadow:var(--shadow);display:block;
}

/* 滑鼠移入頭貼時顯示的懸浮小卡片 */
.avatar-popover {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 280px;
  background: linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%);
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 20px;
  box-shadow: var(--shadow-lg);
  opacity: 0;
  visibility: hidden;
  transform: translateY(10px);
  transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s;
  z-index: 150;
  text-align: center;
}
.avatar-wrap:hover .avatar-popover {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
.popover-btn {
  display: block; width: 100%; padding: 10px; background: linear-gradient(135deg,var(--primary),var(--primary-2));
  color: #fff; font-weight: 700; border-radius: 12px; box-shadow: 0 4px 10px rgba(227,106,75,0.2); text-align: center;
  font-size: 14px; margin-top: 12px;
}

/* 盲盒 hero */
.hero{
  position:relative;border-radius:28px;overflow:hidden;
  height:340px;box-shadow:var(--shadow-lg);margin-bottom:40px;
  background:#000;
}
.hero-bg{
  position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:1;
}
.hero .mask{
  position:absolute;inset:0; z-index:2;
  background:
    radial-gradient(600px 400px at 80% 50%, rgba(255,240,210,.95) 0%, rgba(255,240,210,.4) 35%, transparent 65%),
    linear-gradient(100deg,rgba(43,26,15,.55) 0%,rgba(43,26,15,.15) 45%,transparent 60%);
}
.hero .content{position:absolute;right:48px;top:50%;transform:translateY(-50%);max-width:46%;text-align:right; z-index:3;}
.hero .badge{
  display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.85);
  backdrop-filter:blur(8px);padding:7px 16px;border-radius:999px;font-size:13px;
  margin-bottom:14px;color:var(--primary);font-weight:700;box-shadow:var(--shadow-sm);
}
.hero h2{font-size:34px;margin-bottom:10px;letter-spacing:1px;color:var(--ink);line-height:1.3}
.hero p{color:#5A4030;margin-bottom:22px;font-size:15px}
.hero .btn{
  background:linear-gradient(135deg,var(--primary),var(--primary-2));
  color:#fff;font-weight:700;padding:12px 28px;
  border-radius:999px;display:inline-flex;align-items:center;gap:8px;
  box-shadow:0 12px 28px rgba(227,106,75,.45);transition:.25s;
}
.hero .btn:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(227,106,75,.55)}

/* 區塊與書卡 */
.section{margin-bottom:42px;position:relative}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.section-head .h{display:flex;align-items:center;gap:12px}
.section-head .h .deco{
  width:6px;height:26px;border-radius:999px;
  background:linear-gradient(180deg,var(--primary),var(--primary-2));
}
.section-head h3{font-size:21px;letter-spacing:.5px;display:flex;align-items:center;gap:10px}
.section-head .more{
  color:var(--muted);font-size:13px;padding:6px 14px;border-radius:999px;
  border:1px solid var(--line);background:var(--surface-glass);transition:.2s;
}
.section-head .more:hover{color:var(--primary);border-color:var(--primary);background:#FFEFDD}

.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:20px}
.card{
  background:#fff;border-radius:18px;overflow:hidden;
  box-shadow:var(--shadow-sm);transition:.3s;
  border:1px solid rgba(255,255,255,.8);position:relative;
}
.card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.card .cover{aspect-ratio:3/4;background:#eee;overflow:hidden;position:relative; cursor: zoom-in;}
.card.square .cover{aspect-ratio:1/1}
.card .cover img{width:100%;height:100%;object-fit:cover;transition:.6s}
.card .body{padding:12px 14px 16px}
.card .title{font-weight:700;font-size:14px;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card .meta{display:flex;justify-content:space-between;color:var(--muted);font-size:12px}
.card .tag{color:var(--primary);font-weight:700}

/* 標籤區塊樣式 */
.tags-wrap{position:relative;display:flex;align-items:center;gap:20px}
.tags-wrap .tags{flex:1}
.tags{display:flex;flex-wrap:wrap;gap:10px}
.tag-chip{
  background:var(--surface-glass);backdrop-filter:blur(10px);
  border:1px solid rgba(255,255,255,.8);border-radius:999px;
  padding:9px 18px;font-size:14px;color:var(--ink);transition:.25s;
}
.tag-chip:hover { background: linear-gradient(135deg,var(--primary),var(--primary-2)); color: #fff; border-color:transparent; transform:translateY(-2px); }

/* ============ 會員彈出卡片彈窗 (手機版專用 / 兼未登入提示) ============ */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(43,26,15,0.4);
  backdrop-filter: blur(4px); z-index: 300; display: none; place-items: center;
  animation: fadeIn 0.25s ease;
}
.user-card-modal {
  background: linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%);
  width: 90%; max-width: 360px; border-radius: 24px; padding: 28px;
  box-shadow: var(--shadow-lg); border: 1px solid var(--line);
  position: relative; text-align: center; animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.user-card-modal .close-btn {
  position: absolute; top: 16px; right: 16px; font-size: 20px; 
  cursor: pointer; color: var(--muted); background:none; border:0;
}
.modal-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid #fff; box-shadow: var(--shadow); margin-bottom: 12px; }
.modal-username { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.modal-user-status { font-size: 13px; color: var(--accent); font-weight: 700; margin-bottom: 20px; }
.modal-stats { display: flex; gap: 12px; justify-content: center; margin-bottom: 20px; }
.modal-stat-box { background: #fff; padding: 10px 16px; border-radius: 14px; flex: 1; box-shadow: var(--shadow-sm); border: 1px solid var(--line); }
.modal-stat-title { font-size: 11px; color: var(--muted); margin-bottom: 4px; }
.modal-stat-val { font-size: 15px; font-weight: 800; color: var(--ink); }
.modal-progress-section { text-align: left; background: #fff; padding: 14px; border-radius: 14px; border: 1px solid var(--line); margin-bottom: 20px; }
.modal-progress-lbl { font-size: 12px; font-weight: 700; margin-bottom: 6px; display: flex; justify-content: space-between; }
.modal-btn { 
  display: block; width: 100%; padding: 12px; background: linear-gradient(135deg,var(--primary),var(--primary-2));
  color: #fff; font-weight: 700; border-radius: 14px; box-shadow: 0 6px 14px rgba(227,106,75,0.3); text-align: center;
}

/* ============ 圖片放大燈箱效果 ============ */
.lightbox-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.85);
  z-index: 400; display: none; justify-content: center; align-items: center;
  cursor: zoom-out; animation: fadeIn 0.2s ease;
}
.lightbox-img { max-width: 90%; max-height: 85vh; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); transform: scale(0.95); transition: transform 0.25s ease; }
.lightbox-overlay.open .lightbox-img { transform: scale(1); }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes scaleUp { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* ===================================================
   ============ RWD 響應式斷點優化 ============
   =================================================== */
@media (max-width: 1200px) {
  .grid { grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .main { padding: 24px 24px 80px; }
  .hero h2 { font-size: 28px; }
}
@media (max-width: 960px) {
  .grid { grid-template-columns: repeat(3, 1fr); }
  .hero { height: 280px; }
  .hero .content { max-width: 60%; right: 32px; }
  .hero h2 { font-size: 24px; }
}
@media (max-width: 768px) {
  .sidebar { display: none; }
  .mobile-nav { display: flex; }
  .main { padding: 16px 16px 100px; }
  .topbar { gap: 12px; }
  .search { order: 1; min-width: 100%; } 
  .challenge { order: 2; flex: 1; min-width: auto; }
  .top-btn { order: 3; }
  .points { order: 4; }
  .avatar-wrap { order: 5; }
  .avatar-popover { display: none !important; }
  .hero { height: auto; min-height: 260px; padding: 32px 20px; display: flex; align-items: center; justify-content: center; }
  .hero .mask { background: linear-gradient(180deg, rgba(43,26,15,.4) 0%, rgba(43,26,15,.75) 100%); }
  .hero .content { position: relative; right: auto; top: auto; transform: none; max-width: 100%; text-align: center; z-index: 3; }
  .hero h2 { color: #fff; font-size: 22px; }
  .hero p { color: #EAD6C8; font-size: 14px; }
  .hero .badge { background: rgba(255,255,255,0.2); color: #fff; backdrop-filter: blur(4px); }
  .section-head h3 { font-size: 18px; }
}
@media (max-width: 480px) {
  .grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .challenge span { display: none; } 
  .point { padding: 5px 10px 5px 6px; font-size: 12px; }
  .card .body { padding: 10px; }
  .card .title { font-size: 13px; }
}

/* 適用於主頁上方的簽到提示小泡泡 */
.signin-bubble-top {
    position: absolute;
    top: -14px;        /* 往上飄出按鈕邊界 */
    right: -25px;      /* 往右外側偏移，不擋住按鈕文字 */
    background: #FF6B6B; /* 亮眼活潑的紅橘色 */
    color: white;
    font-size: 11px;
    font-weight: bold;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(255, 107, 107, 0.4);
    
    /* 輕微的上下浮動動畫，讓它像在呼吸一樣 */
    animation: bounceTop 2s infinite ease-in-out; 
    z-index: 10; /* 確保不會被網頁其他元素壓過去 */
}

/* 上方專用的微幅彈跳動畫 */
@keyframes bounceTop {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); } /* 上下飄動 4 像素 */
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
    <a href="index.php" class="active" data-label="首頁"><span class="ic ic-home"></span><span class="label">首頁</span></a>
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
      <a href="create.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800; margin-bottom: 4px;">
        <span class="ic">➕</span> 開始寫故事 / 投稿
      </a>
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
  <a href="index.php" class="active"><span class="ic ic-home"></span>首頁</a>
  <a href="explore.php"><span class="ic ic-search"></span>探索</a>
  <button class="mob-pub-trigger <?= loginClass($is_logged_in) ?>" onclick="if(!document.body.classList.contains('not-logged-in')) toggleMobileSheet(true)">
    <div class="center-pub-btn">＋</div>
  </button>
  <a href="bookshelf.php" class="<?= loginClass($is_logged_in) ?>"><span class="ic ic-books"></span>書架</a>
  <a href="javascript:void(0)" onclick="openUserModal()"><span class="ic ic-user"></span>我的</a>
</nav>

<div class="mobile-publish-sheet" id="mobileSheet">
  <h4>選擇創作動作</h4>
  <div class="sheet-grid" style="grid-template-columns: repeat(2, 1fr); gap: 12px;">
    <a href="create.php" class="sheet-item" style="background: linear-gradient(135deg, #FFEEDD, #FFE4D2); border: 1px solid var(--primary-2);">
      <span style="font-size: 20px;">✍️</span><strong>開始寫故事</strong>
    </a>
    <a href="works.php" class="sheet-item"><span>📖</span>我的作品</a>
    <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
    
  </div>
  <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<div class="modal-overlay" id="userModal" onclick="closeUserModal(event)">
  <div class="user-card-modal" onclick="event.stopPropagation()">
    <button class="close-btn" onclick="closeUserModal(null)">×</button>
    <?php if($is_logged_in): ?>
      <img class="modal-avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
      <div class="modal-username"><?= htmlspecialchars($user['name']) ?></div>
      <div class="modal-user-status">VIP 書友</div>
      <div class="modal-stats">
        <div class="modal-stat-box">
          <div class="modal-stat-title">甜甜圈</div>
          <div class="modal-stat-val">🍩 <?= number_format($user['donuts']) ?></div>
        </div>
        <div class="modal-stat-box">
          <div class="modal-stat-title">麥穗點數</div>
          <div class="modal-stat-val">🌾 <?= number_format($user['wheat']) ?></div>
        </div>
      </div>
      <a href="challenge.php" class="modal-progress-section" style="display: block;">
        <div class="modal-progress-lbl"><span>今日閱讀挑戰</span><strong><?= $user['reading_progress'] ?>%</strong></div>
        <div class="challenge" style="box-shadow:none; padding:0; background:none; border:0; min-width:auto;">
          <div class="bar" style="background:#F2E2C8; height:10px;"><i style="width:<?= $user['reading_progress'] ?>%"></i></div>
        </div>
     </a>
      <a href="profile.php" class="modal-btn" style="margin-bottom: 10px;">進入個人中心</a>
      <a href="logout.php" class="modal-btn" style="background: linear-gradient(135deg, #9A8675, #7A6A5A); box-shadow: 0 6px 14px rgba(154,134,117,0.3);">登出帳號</a>
    <?php else: ?>
      <div style="font-size: 50px; margin-bottom: 12px;">🦊</div>
      <div class="modal-username" style="margin-bottom: 10px;">歡迎來到故事盒子</div>
      <p style="color: var(--muted); font-size: 14px; margin-bottom: 24px;">登入後即可解鎖漂流瓶、累積閱讀點數與同步書架資訊喔！</p>
      <a href="login.php" class="modal-btn">立即登入 / 註冊</a>
    <?php endif; ?>
  </div>
</div>

<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
  <img class="lightbox-img" id="lightboxImg" src="" alt="放大預覽">
</div>

<main class="main <?= !$is_logged_in ? 'not-logged-in' : '' ?>">

  <div class="topbar">
    <form class="search" action="explore.php" method="get" role="search">
      <span class="ic ic-sm ic-search"></span>
      <input name="q" placeholder="搜尋書名、作者、標籤…">
    </form>

    <a href="challenge.php" class="challenge <?= loginClass($is_logged_in) ?>" title="進入年度閱讀挑戰">
      <span class="ic ic-sm ic-openbook"></span>
      <span>閱讀挑戰</span>
      <div class="bar"><i style="width:<?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%"></i></div>
      <strong><?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%</strong>
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
        <img src="img/coin-donut.png" alt=""> <?= $is_logged_in ? number_format($user['donuts']) : 0 ?>
      </div>
      <div class="point wheat" title="麥穗點數">
        <img src="img/coin-wheat.png" alt=""> <?= $is_logged_in ? number_format($user['wheat']) : 0 ?>
      </div>
    </div>

    <div class="avatar-wrap <?= loginClass($is_logged_in) ?>" onclick="<?= $is_logged_in ? "location.href='profile.php'" : "openUserModal()" ?>">
      <img class="avatar" src="<?= $is_logged_in ? htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?img=99' ?>" alt="User">
      
      <div class="avatar-popover" onclick="event.stopPropagation()">
        <?php if($is_logged_in): ?>
          <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">您好，<?= htmlspecialchars($user['name']) ?></div>
          <div style="font-size:12px; color:var(--muted); margin-bottom: 12px;">今天想讀點什麼呢？</div>
          <a href="profile.php" class="popover-btn" style="margin-bottom: 8px;">進入個人中心</a>
          <a href="logout.php" class="popover-btn" style="background: linear-gradient(135deg, #9A8675, #7A6A5A); box-shadow: 0 4px 10px rgba(154,134,117,0.2);">登出帳號</a>
        <?php else: ?>
          <div style="font-weight:700; margin-bottom:4px; color:var(--ink);">訪客您好，尚未登入</div>
          <div style="font-size:12px; color:var(--muted); margin-bottom: 12px;">登入後即可解鎖專屬閱讀福利！</div>
          <a href="login.php" class="popover-btn">立即登入 / 註冊</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <section class="hero" style="
      position: relative ; 
      border-radius: 28px; 
      overflow: hidden; 
      min-height: 380px; 
      box-shadow: var(--shadow-lg); 
      margin-bottom: 40px; 
      display: flex; 
      align-items: center; 
      justify-content: center;
  ">
    <img class="hero-bg" src="<?= htmlspecialchars($blindbox['cover']) ?>" alt="" style="
        position: absolute; 
        inset: 0; 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        z-index: 1;
    ">
    
    <div class="mask" style="
        position: absolute; 
        inset: 0; 
        z-index: 2; 
        background: radial-gradient(circle, rgba(43,26,15,0.4) 0%, rgba(43,26,15,0.7) 100%);
    "></div>

    <div class="content" style="
        position: relative !important; 
        right: auto !important;      
        top: auto !important;          
        transform: none !important;    
        max-width: 80% !important;  
        
        z-index: 3; 
        text-align: center; 
        padding: 20px;
        margin: 0 auto;               
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    ">
      <div class="badge" style="
          display: inline-flex; 
          align-items: center; 
          gap: 8px; 
          background: rgba(255,255,255,0.9); 
          backdrop-filter: blur(8px); 
          padding: 7px 18px; 
          border-radius: 999px; 
          font-size: 13px; 
          margin-bottom: 16px; 
          color: var(--primary); 
          font-weight: 700; 
          box-shadow: var(--shadow-sm);
      ">
        <span class="icon">✨</span> 歡迎來到咬一口故事 <span class="icon">✨</span>
      </div>
      
      <h2 style="
          font-size: 36px; 
          margin-bottom: 12px; 
          letter-spacing: 1.5px; 
          color: #FFFFFF; 
          line-height: 1.3;
          text-shadow: 0 2px 10px rgba(0,0,0,0.5);
      ">
        <?= htmlspecialchars($blindbox['title']) ?>
      </h2>
      
      <p style="
          color: #F4E7D3; 
          margin-bottom: 26px; 
          font-size: 16px;
          text-shadow: 0 1px 5px rgba(0,0,0,0.4);
      ">
        <?= htmlspecialchars($blindbox['subtitle']) ?>
      </p>
      
      <button class="btn <?php echo $is_logged_in ? '' : 'need-login'; ?>" 
              onclick="<?php echo $is_logged_in ? "location.href='blindbox.php';" : ''; ?>"
              style="
                  background: linear-gradient(135deg, var(--primary), var(--primary-2)); 
                  color: #fff; 
                  font-weight: 700; 
                  padding: 14px 36px; 
                  font-size: 15px;
                  border: 0;
                  border-radius: 999px; 
                  display: inline-flex; 
                  align-items: center; 
                  gap: 10px; 
                  cursor: pointer;
                  box-shadow: 0 12px 28px rgba(227,106,75,0.45); 
                  transition: .25s;
              ">
        <span class="btn-icon">🔮</span> 立即抽取命定故事
      </button>
    </div>
  </section>

  <div class="literary-quote-section" style="
      position: relative;
      z-index: 3;
      text-align: center;
      padding: 0px 20px 40px;
      margin: 0 auto 30px;
      max-width: 650px;
      font-family: 'Noto Serif TC', 'Georgia', serif; /* 💡 應用文藝宋體 */
  ">
    <div style="font-size: 24px; color: var(--primary-2); margin-bottom: 20px; opacity: 0.8; letter-spacing: 4px;"> ── ✦ ── </div>
    
    <p style="
        font-size: 20px;
        line-height: 2.2;
        color: #ff9900; /* 💡 溫暖的米金字體，在深色背景上質感極佳 */
        letter-spacing: 3px; /* 💡 稍微拉開字距，營造呼吸感與詩意 */
        font-weight: 400;
        text-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
        margin-bottom: 18px;
    ">
      「字句是伏筆，靈魂是相逢，願你留此處淺嚐一口。」
    </p>

    <div style="font-size: 24px; color: var(--primary-2); margin-bottom: 20px; opacity: 0.8; letter-spacing: 4px;"> ── ✦ ── </div>
    
  
    </span>
  </div>

  
</main>

<script>
  // =================== 套用使用者偏好主題 ===================
  (function(){
    const t = localStorage.getItem('bite_theme');
    if(t && t !== 'warm') document.body.setAttribute('data-theme', t);
  })();

  // =================== 未登入攔截邏輯 ===================
  // 沒登入時，點到 .need-login（個人頁、書架、購物車、漂流瓶、頭貼…）
  // 一律先彈出登入提示
  document.addEventListener('click', e => {
    const target = e.target.closest('.need-login');

    if (e.target.closest('a[href*="profile.php"]')) return;//記得刪！！！

    if (target) {
      e.preventDefault();
      e.stopPropagation();
      openUserModal();
    }
  }, true);

  // 手機版發布 Sheet 控制
  function toggleMobileSheet(show) {
    const sheet = document.getElementById('mobileSheet');
    if(sheet) {
      if(show) sheet.classList.add('open');
      else sheet.classList.remove('open');
    }
  }

  // 「我的」彈窗控制
  function openUserModal() {
    document.getElementById('userModal').style.display = 'grid';
  }
  function closeUserModal(e) {
    if(!e || e.target === document.getElementById('userModal') || e.target.classList.contains('close-btn')) {
      document.getElementById('userModal').style.display = 'none';
    }
  }

  // 燈箱控制
  function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');
    img.src = src;
    lb.style.display = 'flex';
    setTimeout(() => lb.classList.add('open'), 10);
  }
  function closeLightbox() {
    const lb = document.getElementById('lightbox');
    lb.classList.remove('open');
    setTimeout(() => lb.style.display = 'none', 200);
  }
</script>
</body>
</html>
