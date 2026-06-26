<?php //21 29 690-693 已改頭像 32閱讀挑戰
// 咬一口故事 - 探索頁 explore.php (多標籤複合篩選 + 仿行動端App智慧搜尋分類面板)
session_start();
require_once 'db.php'; 
date_default_timezone_set('Asia/Taipei');

// ===================================================
// 1. 使用者登入狀態與資料撈取
// ===================================================
$is_logged_in = isset($_SESSION['user_id']);
// 確保 loginClass 函式存在（如果 index.php 有，這裡也要有）
if (!function_exists('loginClass')) {
    function loginClass($is_logged_in) {
        return $is_logged_in ? '' : 'need-login';
    }
}
$user = null;

if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, nickname, avatar, donuts, wheat FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_user) {
            $user = [
                'id'      => $db_user['id'],
                'name'    => !empty($db_user['nickname']) ? $db_user['nickname'] : $db_user['username'],
                'avatar'  => !empty($db_user['avatar']) ? $db_user['avatar'] : 'https://i.pravatar.cc/100?img=47', 
                'donuts'  => $db_user['donuts'],
                'wheat'   => $db_user['wheat'],
                'reading_progress' => $real_progress, 
            ];
        } else {
            $is_logged_in = false;
        }
    } catch (PDOException $e) {
        
    }
}

// ===================================================
// 2. 預先撈取標籤與熱門創作者
// ===================================================
$tags_pool = ['奇幻', '校園', '治癒系', '懸疑', '同人', '古風', '科幻', '末日', '機器人', '純愛', '微波爐', '櫻花', '時空旅人', '溫馨', '照相館', '連載'];
$tags_pool = array_unique($tags_pool);

try {
    $auth_stmt = $pdo->query("SELECT id, username, nickname FROM users ORDER BY id DESC LIMIT 10");
    $search_authors = $auth_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $search_authors = [];
}

// ===================================================
// 3. 接收並清洗篩選與排序參數
// ===================================================
$current_cat   = $_GET['cat'] ?? 'all';       
$current_sort  = $_GET['sort'] ?? 'new';      
$search_query  = trim($_GET['q'] ?? '');      

$current_tags = [];
if (isset($_GET['tag'])) {
    if (is_array($_GET['tag'])) {
        $current_tags = array_filter(array_map('trim', $_GET['tag']));
    } elseif (!empty($_GET['tag'])) {
        $current_tags = [trim($_GET['tag'])];
    }
}

// ===================================================
// 4. 動態建構主故事牆 SQL 查詢語句 (優化精準過濾排程文)
// ===================================================
$sql = "SELECT s.*, u.username, u.nickname
        FROM stories s
        JOIN users u ON s.user_id = u.id 
        WHERE 1=1 
        AND s.status IN ('ongoing', 'completed', 'published') -- 確保故事主狀態是上架的
        AND EXISTS (
            SELECT 1 FROM chapters c 
            WHERE c.story_id = s.id 
            AND (
                c.status = 'published'
                OR 
                (c.status = 'scheduled' AND c.publish_at <= NOW())
            )
        )";
$params = [];

// 分類篩選
if ($current_cat === 'featured') {
    $sql .= " AND s.type = 'serial'";
} elseif ($current_cat === 'short') {
    $sql .= " AND s.type = 'single'";
}

// 多標籤複合篩選
if (!empty($current_tags)) {
    $tag_queries = [];
    foreach ($current_tags as $t) {
        $tag_queries[] = "s.tags LIKE ?";
        $params[] = "%" . $t . "%";
    }
    $sql .= " AND (" . implode(" OR ", $tag_queries) . ")";
}

// 關鍵字智慧搜尋
if (!empty($search_query)) {
    $sql .= " AND (
        s.title LIKE ? 
        OR s.intro LIKE ? 
        OR s.tags LIKE ? 
        OR u.username LIKE ? 
        OR u.nickname LIKE ?
    )";
    $like_param = "%" . $search_query . "%";
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
    $params[] = $like_param;
}

// 📌 排序與群組
$sql .= " GROUP BY s.id";

if ($current_sort === 'new') {
    $sql .= " ORDER BY s.created_at DESC";
} else {
    $sql .= " ORDER BY s.id DESC"; 
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $books_list = [];
}

// ===================================================
// 5. 推薦榜資料 (同步防堵排程文)
// ===================================================
try {
    $rank_sql = "SELECT s.*, u.username, u.nickname 
                 FROM stories s
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.status IN ('ongoing', 'completed', 'published')
                 AND EXISTS (
                     SELECT 1 FROM chapters c 
                     WHERE c.story_id = s.id 
                     AND (
                         c.status = 'published'
                         OR 
                         (c.status = 'scheduled' AND c.publish_at <= NOW())
                     )
                 )
                 GROUP BY s.id
                 ORDER BY RAND() 
                 LIMIT 5";
    $rank_stmt = $pdo->query($rank_sql);
    $rank_list = $rank_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rank_list = [];
}

// ===================================================
// 6.處理探索頁星星點擊（加入/取消收藏書架）的非同步請求
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_bookshelf') {
    // 回傳 JSON 格式
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => '請先登入後再進行收藏喔！']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $story_id = intval($_POST['story_id'] ?? 0);
    
    if ($story_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => '無效的故事編號']);
        exit;
    }
    
    try {
        // 檢查是否已經收藏過
        $check_stmt = $pdo->prepare("SELECT id FROM bookshelves WHERE user_id = ? AND story_id = ?");
        $check_stmt->execute([$user_id, $story_id]);
        $fav = $check_stmt->fetch();
        
        if ($fav) {
            // 已經收藏過 -> 這次點擊代表「取消收藏」
            $del_stmt = $pdo->prepare("DELETE FROM bookshelves WHERE user_id = ? AND story_id = ?");
            $del_stmt->execute([$user_id, $story_id]);
            echo json_encode(['status' => 'success', 'is_favorite' => false, 'message' => '已移出書架']);
        } else {
            // 尚未收藏 -> 這次點擊代表「加入收藏」
            $ins_stmt = $pdo->prepare("INSERT INTO bookshelves (user_id, story_id) VALUES (?, ?)");
            $ins_stmt->execute([$user_id, $story_id]);
            echo json_encode(['status' => 'success', 'is_favorite' => true, 'message' => '已成功加入書架！']);
        }
        exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => '系統錯誤：' . $e->getMessage()]);
        exit;
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>咬一口故事 ‧ 探索故事</title>
<link rel="stylesheet" href="css/create.css">
<style>
/* ============ 核心樣式與變數 ============ */
:root{
  --bg:#FBF5EC;
  --bg-2:#F4E7D3;
  --surface:#FFFFFF;
  --surface-glass:rgba(255,255,255,.75);
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
body{
  color:var(--ink);min-height:100vh;display:flex;position:relative;overflow-x:hidden;
  background: radial-gradient(900px 600px at -10% -10%, #FFD9C2 0%, transparent 60%), radial-gradient(800px 600px at 110% 10%, #F8E6BE 0%, transparent 55%), linear-gradient(180deg, var(--bg), var(--bg-2));
}
body::before{ content:"";position:fixed;inset:0;pointer-events:none;z-index:0; background-size:22px 22px; background-image:radial-gradient(rgba(120,80,40,.05) 1px, transparent 1px); }
a{color:inherit;text-decoration:none}

/* ============ 圖示 ============ */
.ic { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; flex-shrink: 0; font-style: normal; filter: drop-shadow(0 1px 1px rgba(0,0,0,.08)); vertical-align: middle; }
.ic::before { display: inline-block; visibility: visible !important; }
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
.ic-hash::before { content: '🏷️'; }
.ic-trophy::before { content: '🏆'; }
.ic-sm{width:20px;height:20px;font-size:14px}

/* ============ Sidebar 側邊欄 ============ */
.sidebar{
  width:248px;flex-shrink:0;height:100vh;position:sticky;top:0;z-index:100;
  background:linear-gradient(180deg,#FFFCF6 0%,#FFF6E8 100%); border-right:1px solid var(--line); padding:22px 16px;display:flex;flex-direction:column;gap:6px; transition:width .35s ease; box-shadow:6px 0 30px rgba(80,40,10,.04);
}
.sidebar.collapsed{width:78px;padding:22px 12px}
.logo{ display:flex;align-items:center;gap:12px;padding:6px 10px;margin-bottom:14px; font-weight:800;font-size:19px;color:var(--ink); overflow:hidden;white-space:nowrap; }
.logo-mark { width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0; background: var(--primary); box-shadow: var(--shadow); display: grid; place-items: center; font-size: 22px; }
.sidebar.collapsed .logo-text{opacity:0;transform:translateX(-8px);pointer-events:none}
.logo-text{transition:.3s}
.toggle{ position:absolute;top:30px;right:-14px;width:28px;height:28px;border-radius:50%; background:#fff;border:1px solid var(--line);color:var(--primary); display:grid;place-items:center;cursor:pointer;font-size:14px;font-weight:900; box-shadow:var(--shadow);transition:.3s;z-index:6; }
.sidebar.collapsed .toggle{transform:rotate(180deg)}

.nav{display:flex;flex-direction:column;gap:4px}
.nav a{ display:flex;align-items:center;gap:14px;padding:11px 12px;border-radius:14px; color:var(--ink);font-size:15px;transition:.25s;position:relative; white-space:nowrap;overflow:hidden; }
.sidebar.collapsed .nav a { justify-content:center; padding:11px 8px; }
.sidebar.collapsed .nav a .label{opacity:0;width:0;margin-left:-14px}
.nav a:hover{background:#FFEFDD;color:var(--primary)}
.nav a.active{ background:linear-gradient(135deg,#FFE4D2,#FFD3B6); color:var(--primary);font-weight:700; box-shadow:inset 0 0 0 1px rgba(227,106,75,.15); }
.divider{height:1px;background:linear-gradient(90deg,transparent,var(--line),transparent);margin:10px 4px}

.publish{position:relative;margin-top:10px}
.publish-btn{ width:100%;border:0;cursor:pointer; background:linear-gradient(135deg,var(--primary),var(--primary-2)); color:#fff;font-weight:700;padding:13px;border-radius:16px;font-size:15px; box-shadow:0 10px 24px rgba(227,106,75,.35); display:flex;align-items:center;justify-content:center;gap:10px; }
.sidebar.collapsed .publish-btn .label { display: none; }
.publish-menu{ position:absolute;left:0;right:0;top:calc(100% + 10px); background:#fff;border:1px solid var(--line);border-radius:16px; box-shadow:var(--shadow-lg);padding:8px;display:none;min-width:200px;z-index:110; }
.publish.open .publish-menu{display:block;animation:pop .25s ease}
.publish-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:14px;white-space:nowrap}
.publish-menu a:hover{background:#FFEFDD;color:var(--primary)}

.logout{ margin-top:auto;color:var(--muted);font-size:14px;padding:10px 12px; display:flex;align-items:center;gap:14px;border-radius:12px; white-space:nowrap;overflow:hidden; }
.sidebar.collapsed .logout .label { display: none; }

/* ============ 手機版導覽列 ============ */
.mobile-nav{ display:none;position:fixed;bottom:0;left:0;right:0;height:66px; background:rgba(255,252,246,0.92);backdrop-filter:blur(20px); border-top:1px solid var(--line);z-index:100; justify-content:space-around;align-items:center; box-shadow:0 -4px 20px rgba(80,40,10,.05); }
.mobile-nav a{ display:flex;flex-direction:column;align-items:center;gap:4px; color:var(--muted);font-size:11px; }
.mobile-nav a.active{color:var(--primary);font-weight:700}

/* ============ 頂部 Topbar 搜尋區塊 ============ */
.main{flex:1;min-width:0;padding:24px 36px 80px;position:relative;z-index:1}
.topbar{display:flex;align-items:center;gap:14px;margin-bottom:28px;position:relative;z-index:150;}

.search-wrapper { position: relative; flex: 1; min-width: 280px; }
.search{ display:flex;align-items:center;gap:10px; background:var(--surface-glass);backdrop-filter:blur(14px); border:1px solid rgba(255,255,255,.7);border-radius:999px; padding:11px 20px;box-shadow:var(--shadow); transition: border-color .3s; }
.search:focus-within { border-color: var(--primary-2); background: #fff; }
.search input{flex:1;border:0;outline:0;font-size:15px;background:transparent;color:var(--ink)}

/* ============ 🔍 仿行動端 App 智慧搜尋分類面板 ============ */
.search-dropdown {
  position: absolute; top: calc(100% + 10px); left: 0; right: 0;
  background: rgba(255, 252, 246, 0.98); backdrop-filter: blur(25px);
  border: 1px solid var(--line); border-radius: 24px; padding: 0;
  box-shadow: var(--shadow-lg); 
  display: none;      /* 預設隱藏 */
  height: 0;          /* 強制高度為 0 */
  overflow: hidden;   /* 防止內容溢出撐開 */
  pointer-events: none; /* 隱藏時不可點擊 */
  flex-direction: column; z-index: 999;
  animation: slideDown 0.2s ease; max-height: 480px;
}
.search-dropdown.open { 
  display: flex !important; 
  height: auto; 
  pointer-events: auto;
}

.dropdown-tabs {
  display: flex; background: var(--bg-2); border-bottom: 1px solid var(--line); padding: 4px;
}
.dropdown-tab-btn {
  flex: 1; text-align: center; padding: 8px 0; font-size: 13px; font-weight: bold; 
  color: var(--muted); border-radius: 12px; cursor: pointer; border: 0; background: transparent; transition: .2s;
}
.dropdown-tab-btn.active {
  background: #fff; color: var(--ink); box-shadow: var(--shadow-sm);
}

.dropdown-panel-content {
  padding: 16px; overflow-y: auto; display: none; flex-direction: column; gap: 12px;
}
.dropdown-panel-content.active { display: flex; }

.dropdown-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.dropdown-tag-item { padding: 6px 14px; background: #FFF1E0; color: var(--primary); font-size: 12px; font-weight: 700; border-radius: 99px; transition: .2s; cursor: pointer; }
.dropdown-tag-item:hover { background: var(--primary); color: #fff; }

.dropdown-authors { display: flex; flex-direction: column; gap: 4px; }
.dropdown-author-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 14px; transition: .2s; cursor: pointer; }
.dropdown-author-item:hover { background: #FFEFDD; color: var(--primary); }
.dropdown-author-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary-2); color: #fff; display: grid; place-items: center; font-size: 14px; font-weight: bold; }

.search-hint { font-size: 12px; color: var(--muted); text-align: center; padding: 12px 0; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pop { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.challenge{ display:flex;align-items:center;gap:10px;background:var(--surface-glass);backdrop-filter:blur(14px); border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:9px 16px; box-shadow:var(--shadow);font-size:14px; }
.challenge .bar{width:60px;height:8px;background:#F2E2C8;border-radius:999px;overflow:hidden;}
.challenge .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-2));}
.points{display:flex;gap:10px}
.point{ display:flex;align-items:center;gap:8px;background:var(--surface-glass);border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:7px 14px; font-weight:800;box-shadow:var(--shadow);font-size:14px; }
.point.donut{color:#D6557A}.point.wheat{color:#A87A1F}

/* ============ 💡 頂部多標籤複合篩選面板 ============ */
.explore-tabs { display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--line); padding-bottom: 8px; }
.explore-tab { padding: 10px 24px; font-size: 16px; font-weight: 700; color: var(--muted); border-radius: 12px 12px 0 0; position: relative; cursor: pointer;}
.explore-tab.active { color: var(--primary); }
.explore-tab.active::after { content: ""; position: absolute; bottom: -10px; left: 0; right: 0; height: 3px; background: var(--primary); }

.filter-panel { background: var(--surface-glass); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.8); border-radius: 22px; padding: 20px; margin-bottom: 32px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 14px; }
.filter-row { display: flex; align-items: flex-start; gap: 16px; font-size: 14px; }
.filter-label { color: var(--ink); font-weight: 800; padding-top: 6px; white-space: nowrap; display: flex; align-items: center; gap: 4px;}
.filter-options { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }

.filter-opt { padding: 6px 14px; border-radius: 99px; font-size: 13px; color: var(--ink); background: #fff; border: 1px solid var(--line); cursor: pointer; transition: .22s; display: inline-flex; align-items: center; gap: 4px; user-select: none;}
.filter-opt:hover { border-color: var(--primary-2); background: #FFFBF7;}
.filter-opt.active { background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: #fff; font-weight: 700; border-color: transparent; box-shadow: 0 4px 10px rgba(227,106,75,0.25); }

.filter-actions { display: flex; justify-content: flex-end; gap: 12px; border-top: 1px dashed var(--line); padding-top: 12px; margin-top: 4px; }
.btn-action { padding: 7px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; border: 0;}
.btn-submit { background: var(--ink); color: #fff; cursor: pointer; }
.btn-submit:hover { opacity: 0.9; }
.btn-reset { background: var(--bg-2); color: var(--muted); cursor: pointer; }
.btn-reset:hover { background: var(--line); color: var(--ink); }

/* ============ 頂部頭像懸浮視窗 (Popover) ============ */
.user-menu-container {
  position: relative;
  display: inline-block;
}
.topbar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: var(--shadow-sm);
  object-fit: cover;
  vertical-align: middle;
  cursor: pointer;
  transition: transform 0.2s;
}
.user-menu-container:hover .topbar-avatar {
  transform: scale(1.05);
}

/* 彈出視窗主體 */
.avatar-popover {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 280px;
  background: #FFFDF9;
  border-radius: 24px;
  padding: 24px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--line);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
  z-index: 999;
  text-align: center;
}
/* 懸浮時顯示 */
.user-menu-container:hover .avatar-popover {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
/* 彈出視窗裝飾小三角 */
.avatar-popover::before {
  content: '';
  position: absolute;
  top: -6px;
  right: 14px;
  width: 12px;
  height: 12px;
  background: #FFFDF9;
  border-left: 1px solid var(--line);
  border-top: 1px solid var(--line);
  transform: rotate(45deg);
}

/* 內部元件樣式 */
.popover-title { font-size: 18px; font-weight: 800; color: var(--ink); margin-bottom: 6px; }
.popover-desc { font-size: 13px; color: var(--muted); margin-bottom: 20px; }
.popover-btn {
  display: block; width: 100%; padding: 12px; border-radius: 16px;
  font-size: 14px; font-weight: 700; text-align: center; cursor: pointer; border: 0; transition: .2s;
}
.popover-btn.primary {
  background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: #fff;
  box-shadow: 0 6px 16px rgba(227,106,75,0.25); margin-bottom: 10px;
}
.popover-btn.primary:hover { opacity: 0.9; transform: translateY(-1px); }
.popover-btn.secondary { background: #8C7C72; color: #fff; }
.popover-btn.secondary:hover { background: #76685F; transform: translateY(-1px); }

/* ============ 故事牆與排版 ============ */
.explore-layout { display: flex; gap: 32px; align-items: flex-start; }
.explore-main-list { flex: 1; min-width: 0; }
.explore-sidebar-rank { width: 320px; flex-shrink: 0; background: var(--surface-glass); border-radius: 24px; padding: 20px; border: 1px solid rgba(255,255,255,0.8); box-shadow: var(--shadow-sm); }
.explore-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }

.card { display: flex; flex-direction: column; background: var(--surface); border-radius: 18px; overflow: hidden; box-shadow: var(--shadow-sm); transition: transform 0.3s ease, box-shadow 0.3s ease; }
.card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }

.card .image-cover-wrap { width: 100%; aspect-ratio: 3/4; overflow: hidden; position: relative; cursor: pointer; display: flex; align-items: center; justify-content: center; background-size: cover; background-position: center; }
.card .image-cover-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.card:hover .image-cover-wrap img { transform: scale(1.06); }

.card .image-cover-wrap .card-style-title { position: absolute; font-size: 18px; font-weight: 900; color: #fff; text-align: center; padding: 0 16px; text-shadow: 0 2px 8px rgba(0,0,0,0.65); z-index: 2; line-height: 1.5; pointer-events: none; }
.card .image-cover-wrap.style-none .card-style-title { color: var(--muted); text-shadow: none; }

.card .image-cover-wrap .cover-overlay {
  position: absolute; inset: 0; background: rgba(43,26,15,0.75); backdrop-filter: blur(4px); padding: 20px; color: #fff; display: flex; flex-direction: column; justify-content: center; opacity: 0; transition: opacity 0.3s ease; z-index: 5;
}
.card:hover .image-cover-wrap .cover-overlay { opacity: 1; }
.card .cover-overlay-title { font-size: 15px; font-weight: 800; margin-bottom: 8px; color: var(--primary-2); text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }
.card .cover-overlay-intro { font-size: 12px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; color: #EEE; }

.body { padding: 14px 16px; }
.card .title { font-weight: 800; font-size: 15px; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--ink); display: block; }
.card .meta { display: flex; justify-content: space-between; align-items: center; color: var(--muted); font-size: 12px; }
.card .author-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; font-weight: 500; }
.card .tag-badge { background: #FFEFDD; color: var(--primary); font-weight: 700; padding: 3px 9px; border-radius: 6px; font-size: 11px; }

/* 排行榜 */
.rank-title { font-size: 16px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--line); padding-bottom: 8px; }
.rank-item { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; padding: 8px; border-radius: 12px; cursor: pointer; }
.rank-item:hover { background: #FFF9F2; }
.rank-num { width: 24px; height: 24px; border-radius: 6px; background: var(--line); display: grid; place-items: center; font-size: 12px; font-weight: 800; color: var(--muted); }

.rank-cover-wrap { width: 42px; height: 56px; border-radius: 6px; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative; background-size: cover; background-position: center; }
.rank-img { width: 100%; height: 100%; object-fit: cover; }
.rank-cover-wrap .rank-style-title { position: absolute; font-size: 10px; font-weight: bold; color: #fff; text-align: center; width: 100%; padding: 0 2px; box-sizing: border-box; pointer-events: none; text-shadow: 0 1px 3px rgba(0,0,0,0.6); z-index: 2; word-break: break-all; }
.rank-cover-wrap.style-none .rank-style-title { color: var(--muted); text-shadow: none; font-size: 9px; }

.rank-info { flex: 1; min-width: 0; }
.rank-book-title { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--ink); }
.rank-book-meta { font-size: 11px; color: var(--muted); display: flex; justify-content: space-between; }

.empty-state { grid-column: span 4; text-align: center; padding: 60px 20px; color: var(--muted); background: rgba(255,255,255,0.4); border-radius: 20px; border: 1px dashed var(--line); }

.challenge{
  display:flex;align-items:center;gap:10px;background:var(--surface-glass);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:9px 16px;
  box-shadow:var(--shadow);min-width:240px;font-size:14px;
  
  /* 💡 新增以下三行：滑鼠指標變手勢、加上平滑過渡與動畫 */
  cursor: pointer;
  transition: transform 0.2s ease, background-color 0.2s ease;
}
/* 💡 新增 hover 懸浮效果 */
.challenge:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.9);
}

/* ============ 🎯 探索頁星星收藏按鈕專屬樣式 ============ */
.star-btn {
  /* 絕對定位，設定距離卡片右邊與上面的距離 */
  position: absolute;
  top: 14px;
  right: 14px;
  
  /* 造型：圓形、白色半透明背景、磨砂玻璃質感 */
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(237, 223, 200, 0.6); /* 融入你原本的 var(--line) 配色 */
  border-radius: 50%;
  width: 36px;
  height: 36px;
  
  /* 字體與顏色 */
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #a0aec0; /* 預設呈暗灰色 */
  
  /* 動畫效果：加上一點回彈彈性（cubic-bezier），看起來更有活力 */
  transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  
  /* 核心設定：確保星星蓋在原本封面的 overlay 之上 */
  z-index: 10; 
  
  box-shadow: 0 4px 10px rgba(80,40,10,0.08);
  outline: none;
}

/* 懸浮效果：稍微放大、變白、換顏色 */
.star-btn:hover {
  transform: scale(1.15);
  background: #ffffff;
  color: #ffb800;
  box-shadow: 0 6px 15px rgba(80,40,10,0.15);
}

/* 🎯 收藏啟用（Active）時的黃金實心星狀態 */
.star-btn.active {
  color: #ffb800;
  background: #fffdf2;
  border-color: #ffd000;
}


@media (max-width: 1400px) { .explore-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1100px) { .explore-layout { flex-direction: column; } .explore-sidebar-rank { width: 100%; margin-top: 10px; } .explore-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px) { .sidebar { display: none; } .mobile-nav { display: flex; } .main { padding: 16px 16px 100px; } .explore-grid { grid-template-columns: repeat(3, 1fr); gap: 14px; } }
@media (max-width: 520px) { .explore-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } }
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">‹</button>
  <div class="logo"><div class="logo-mark">📖</div><span class="logo-text">咬一口故事</span></div>
  <nav class="nav">
    <a href="index.php"><span class="ic ic-home"></span><span class="label">首頁</span></a>
    <a href="explore.php" class="active"><span class="ic ic-search"></span><span class="label">探索</span></a>
    <a href="bottle.php"><span class="ic ic-bottle"></span><span class="label">漂流瓶</span></a>
    <div class="divider"></div>
    <a href="profile.php"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
    <a href="bookshelf.php"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
    <a href="cart.php"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
  </nav>
  
  <div class="publish" id="publish">
    <button class="publish-btn" onclick="event.stopPropagation(); document.getElementById('publish').classList.toggle('open')">
      <span class="ic ic-pen"></span><span class="label">發布新作</span>
    </button>
    <div class="publish-menu">
      <a href="create.php" style="background: var(--bg-2); color: var(--primary); font-weight: 800; margin-bottom: 4px;">➕ 開始寫故事</a>
      <div class="divider" style="margin: 4px 0;"></div>
      <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
      <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
      
    </div>
  </div>
  
  <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
</aside>

<nav class="mobile-nav">
    <a href="index.php"><span class="ic ic-home"></span>首頁</a>
    <a href="explore.php"><span class="ic ic-search"></span>探索</a>
    <button class="mob-pub-trigger" onclick="toggleMobileSheet(true)"><div class="center-pub-btn">＋</div></button>
    <a href="bookshelf.php"><span class="ic ic-books"></span>書架</a>
    <a href="javascript:void(0)" onclick="openUserModal()"><span class="ic ic-user"></span>我的</a>
</nav>

<div class="mobile-publish-sheet" id="mobileSheet">
    <h4>選擇創作動作</h4>
    <div class="sheet-grid">
        <a href="create.php" class="sheet-item"><span>✍️</span><strong>開始寫故事</strong></a>
        <a href="works.php" class="sheet-item" style="background: linear-gradient(135deg, #FFEEDD, #FFE4D2); border: 1px solid var(--primary-2);"><span>📖</span>我的作品</a>
        <a href="drafts.php" class="sheet-item"><span>📁</span>草稿箱</a>
        
    </div>
    <button class="sheet-close" onclick="toggleMobileSheet(false)">取消</button>
</div>

<main class="main">
  <div class="topbar">
    <div class="search-wrapper" id="searchWrapper">
      <div class="search">
        <span class="ic ic-sm ic-search"></span>
        <input id="searchInput" placeholder="輸入關鍵字..." value="<?= htmlspecialchars($search_query) ?>" oninput="filterDropdownData()" onfocus="showDropdown()" autocomplete="off">
      </div>
      
      <div class="search-dropdown" id="searchDropdown">
        <div class="dropdown-tabs">
          <button type="button" class="dropdown-tab-btn active" onclick="switchSearchTab(event, 'tab-all')">故事</button>
          <button type="button" class="dropdown-tab-btn" onclick="switchSearchTab(event, 'tab-authors')">用戶 / 創作者</button>
          <button type="button" class="dropdown-tab-btn" onclick="switchSearchTab(event, 'tab-tags')">標籤分類</button>
        </div>

        <div id="tab-all" class="dropdown-panel-content active">
          <div class="search-hint">💡 輸入內容可自動過濾頁籤內用戶與標籤，按 Enter 鍵可直接搜尋故事全站內容</div>
        </div>

        <div id="tab-authors" class="dropdown-panel-content">
          <div class="dropdown-authors" id="dropdownAuthorsList">
            <?php if(!empty($search_authors)): ?>
              <?php foreach($search_authors as $sa): 
                $display_auth_name = !empty($sa['nickname']) ? $sa['nickname'] : $sa['username'];
              ?>
                <div class="dropdown-author-item" data-name="<?= htmlspecialchars(strtolower($display_auth_name . ' ' . $sa['username'])) ?>" onclick="location.href='user.php?id=<?= $sa['id'] ?>'">
                  <div class="dropdown-author-avatar">✍️</div>
                  <div>
                    <strong style="font-size:14px; color:var(--ink);"><?= htmlspecialchars($display_auth_name) ?></strong>
                    <span style="font-size:11px; color:var(--muted); margin-left:6px;">@<?= htmlspecialchars($sa['username']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="search-hint">暫無創作者資料</div>
            <?php endif; ?>
          </div>
        </div>

        

        <div id="tab-tags" class="dropdown-panel-content">
          <div class="dropdown-tags" id="dropdownTagsList">
            <?php foreach ($tags_pool as $t): ?>
              <span class="dropdown-tag-item" data-tag="<?= htmlspecialchars(strtolower($t)) ?>" onclick="toggleTagFromSearch('<?= htmlspecialchars($t) ?>')">#<?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <a href="challenge.php" class="challenge <?= loginClass($is_logged_in) ?>" title="進入年度閱讀挑戰">
      <span class="ic ic-sm ic-openbook"></span>
      <span>閱讀挑戰</span>
      <div class="bar"><i style="width:<?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%"></i></div>
      <strong><?= $is_logged_in ? (int)$user['reading_progress'] : 0 ?>%</strong>
    </a>
    <div class="points">
      <div class="point donut">🍩 <?= $is_logged_in ? number_format($user['donuts']) : 0 ?></div>
      <div class="point wheat">🌾 <?= $is_logged_in ? number_format($user['wheat']) : 0 ?></div>

      <div class="avatar-wrap">
            <a href="profile.php">
                <!-- 🎯 修正：將 $user_avatar 更換為 $user['avatar']，並在未登入時給予預設圖 -->
                <img class="avatar" src="<?= $is_logged_in ? htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?img=47' ?>" alt="User" style="cursor: pointer; width: 46px; height: 46px; border-radius: 50%; object-fit: cover;">
            </a>
        <!-- 下拉彈出視窗 -->
        <div class="avatar-popover">
          <?php if ($is_logged_in): ?>
            <!-- 🟢 已登入狀態 -->
            <div class="popover-title">您好，<?= htmlspecialchars($user['name']) ?></div>
            <div class="popover-desc">今天想讀點什麼呢？</div>
            
            <a href="profile.php" class="popover-btn primary">進入個人中心</a>
            <a href="logout.php" class="popover-btn secondary">登出帳號</a>
          <?php else: ?>
            <!-- 🔴 未登入狀態 (對應 image_5c62e7.png) -->
            <div class="popover-title">訪客您好，尚未登入</div>
            <div class="popover-desc">登入後即可解鎖專屬閱讀福利！</div>
            
            <a href="login.php" class="popover-btn primary">立即登入 / 註冊</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="explore-tabs">
    <a href="explore.php?cat=all&<?= http_build_query(['tag' => $current_tags, 'sort' => $current_sort, 'q' => $search_query]) ?>" class="explore-tab <?= $current_cat==='all'?'active':'' ?>">全部故事</a>
    <a href="explore.php?cat=featured&<?= http_build_query(['tag' => $current_tags, 'sort' => $current_sort, 'q' => $search_query]) ?>" class="explore-tab <?= $current_cat==='featured'?'active':'' ?>">精選長篇</a>
    <a href="explore.php?cat=short&<?= http_build_query(['tag' => $current_tags, 'sort' => $current_sort, 'q' => $search_query]) ?>" class="explore-tab <?= $current_cat==='short'?'active':'' ?>">免費短文</a>
  </div>

  <form id="filterForm" method="GET" action="explore.php">
    <input type="hidden" name="cat" value="<?= htmlspecialchars($current_cat) ?>">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($current_sort) ?>">
    <input type="hidden" name="q" id="hiddenFormQ" value="<?= htmlspecialchars($search_query) ?>">

    <div class="filter-panel">
      <div class="filter-row">
        <div class="filter-label"><span class="ic ic-sm ic-hash"></span> 複合標籤篩選 (可多選):</div>
        <div class="filter-options">
          <?php foreach ($tags_pool as $t): 
            $is_checked = in_array($t, $current_tags);
          ?>
            <div class="filter-opt <?= $is_checked ? 'active' : '' ?>" onclick="toggleFilterTag(this)">
              <input type="checkbox" name="tag[]" value="<?= htmlspecialchars($t) ?>" <?= $is_checked ? 'checked' : '' ?> style="display:none;">
              #<?= htmlspecialchars($t) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="filter-actions">
        <button type="button" class="btn-action btn-reset" onclick="resetFilters()">清除條件</button>
        <button type="submit" class="btn-action btn-submit">確認篩選條件</button>
      </div>
    </div>
  </form>

  <div class="explore-layout">
    <div class="explore-main-list">
     <div class="explore-grid">
        <?php if (!empty($books_list)): ?>
          <?php foreach ($books_list as $b): 
            $disp_author = !empty($b['nickname']) ? $b['nickname'] : $b['username'];
            $first_tag = !empty($b['tags']) ? explode(',', $b['tags'])[0] : '綜合';
            
            $cov_type = trim($b['cover_type'] ?? 'none');
            $cov_img  = trim($b['cover_image'] ?? '');
            
            if ($cov_type === 'none' || empty($cov_type)) {
                $style_class = 'style-none';
            } else {
                $style_class = 'style-' . $cov_type; 
            }

            // 🎯 新增：判斷當前登入者是否已收藏這本書 (原本排版毫無影響)
            $is_fav = false;
            if ($is_logged_in) {
                $fav_stmt = $pdo->prepare("SELECT 1 FROM bookshelves WHERE user_id = ? AND story_id = ?");
                $fav_stmt->execute([$_SESSION['user_id'], $b['id']]);
                $is_fav = (bool)$fav_stmt->fetch();
            }
          ?>
          
          <div class="card" style="position: relative;">
            
            <button type="button" 
                    class="star-btn <?= $is_fav ? 'active' : '' ?>" 
                    onclick="toggleBookshelf(<?= $b['id'] ?>, this, event)" 
                    title="<?= $is_fav ? '移出書架' : '加入書架' ?>">
                <?= $is_fav ? '★' : '☆' ?>
            </button>

            <div class="image-cover-wrap class-cover-box <?= $style_class ?>" onclick="location.href='story.php?id=<?= $b['id'] ?>'">
              <?php if ($cov_type === 'upload' && !empty($cov_img)): ?>
                <img src="<?= htmlspecialchars($cov_img) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="card-style-title"><?= htmlspecialchars($b['title']) ?></span>
              <?php endif; ?>
              
              <div class="cover-overlay">
                <div class="cover-overlay-title"><?= htmlspecialchars($b['title']) ?></div>
                <div class="cover-overlay-intro"><?= htmlspecialchars($b['intro']) ?></div>
              </div>
            </div>

            <div class="body">
              <a href="story.php?id=<?= $b['id'] ?>" class="title"><?= htmlspecialchars($b['title']) ?></a>
              <div class="meta">
                <span class="author-name">👤 <?= htmlspecialchars($disp_author) ?></span>
                <span class="tag-badge">#<?= htmlspecialchars($first_tag) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">🍂 找不到任何相符的故事，換個篩選條件或關鍵字試試看吧！</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="explore-sidebar-rank">
      <div class="rank-title"><span class="ic ic-sm ic-trophy"></span> 為您推薦（推薦榜）</div>
      <div>
        <?php if (!empty($rank_list)): ?>
          <?php $idx = 1; foreach ($rank_list as $rb): 
            $disp_rank_author = !empty($rb['nickname']) ? $rb['nickname'] : $rb['username'];
            
            $r_cov_type = trim($rb['cover_type'] ?? 'none');
            $r_cov_img  = trim($rb['cover_image'] ?? '');
            
            if ($r_cov_type === 'none' || empty($r_cov_type)) {
                $r_style_class = 'style-none';
            } else {
                $r_style_class = 'style-' . $r_cov_type;
            }
          ?>
          <div class="rank-item" onclick="location.href='story.php?id=<?= $rb['id'] ?>'">
            <div class="rank-num"><?= $idx++ ?></div>
            
            <div class="rank-cover-wrap class-cover-box <?= $r_style_class ?>">
              <?php if ($r_cov_type === 'upload' && !empty($r_cov_img)): ?>
                <img class="rank-img" src="<?= htmlspecialchars($r_cov_img) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="rank-style-title"><?= mb_substr($rb['title'], 0, 4, 'utf-8') ?></span>
              <?php endif; ?>
            </div>

            <div class="rank-info">
              <div class="rank-book-title"><?= htmlspecialchars($rb['title']) ?></div>
              <div class="rank-book-meta"><span><?= htmlspecialchars($disp_rank_author) ?></span></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="search-hint">暫無推薦資料</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<script>
// ==========================================
// 1. 全域 UI 點擊事件處理
// ==========================================
document.addEventListener('click', function(e) {
  // 發布菜單的切換
  const publish = document.getElementById('publish');
  if(publish && !publish.contains(e.target)) {
    publish.classList.remove('open');
  }

  // 搜尋智慧下拉面板點擊外部關閉
  const searchWrapper = document.getElementById('searchWrapper');
  const searchDropdown = document.getElementById('searchDropdown');
  if (searchWrapper && !searchWrapper.contains(e.target)) {
    searchDropdown.classList.remove('open');
  }
});

// ==========================================
// 2. 🔍 智慧搜尋面板控制邏輯
// ==========================================
function showDropdown() {
  document.getElementById('searchDropdown').classList.add('open');
}

// 智慧搜尋分類面板頁籤切換
function switchSearchTab(e, tabId) {
  e.stopPropagation();
  // 清除全部按鈕 active
  document.querySelectorAll('.dropdown-tab-btn').forEach(btn => btn.classList.remove('active'));
  // 清除全部面板 active
  document.querySelectorAll('.dropdown-panel-content').forEach(p => p.classList.remove('active'));
  
  // 啟用目前選擇的項目
  e.currentTarget.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

// 仿 App 即時動態過濾下拉面板內容
function filterDropdownData() {
  const inputVal = document.getElementById('searchInput').value.trim().toLowerCase();
  
  // 連動表單的隱藏 Input，讓複合標籤送出時也能帶有當前關鍵字
  document.getElementById('hiddenFormQ').value = inputVal;

  // 1. 過濾創作者
  const authorItems = document.querySelectorAll('.dropdown-author-item');
  authorItems.forEach(item => {
    const nameData = item.getAttribute('data-name') || '';
    if (nameData.includes(inputVal)) {
      item.style.display = 'flex';
    } else {
      item.style.display = 'none';
    }
  });

  // 2. 過濾標籤
  const tagItems = document.querySelectorAll('.dropdown-tag-item');
  tagItems.forEach(item => {
    const tagData = item.getAttribute('data-tag') || '';
    if (tagData.includes(inputVal)) {
      item.style.display = 'inline-block';
    } else {
      item.style.display = 'none';
    }
  });
}

// 在智慧下拉選單點擊標籤直接觸發搜尋
function toggleTagFromSearch(tagName) {
  const form = document.getElementById('filterForm');
  
  // 建立一個乾淨的新表單傳送，避免多重重複問題
  const cleanUrl = `explore.php?cat=${encodeURIComponent(form.elements['cat'].value)}&tag[]=${encodeURIComponent(tagName)}`;
  location.href = cleanUrl;
}

// 監聽 Enter 鍵直接執行大範圍站內搜尋
document.getElementById('searchInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    const inputVal = this.value.trim();
    const cat = document.getElementById('filterForm').elements['cat'].value;
    location.href = `explore.php?cat=${encodeURIComponent(cat)}&q=${encodeURIComponent(inputVal)}`;
  }
});

// ==========================================
// 3. 🏷️ 複合式標籤過濾面板邏輯
// ==========================================
function toggleFilterTag(el) {
  const checkbox = el.querySelector('input[type="checkbox"]');
  if (checkbox) {
    checkbox.checked = !checkbox.checked;
    if (checkbox.checked) {
      el.classList.add('active');
    } else {
      el.classList.remove('active');
    }
  }
}

// 清除所有標籤篩選與搜尋條件
function resetFilters() {
  const form = document.getElementById('filterForm');
  form.querySelectorAll('.filter-opt').forEach(opt => opt.classList.remove('active'));
  form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
  document.getElementById('searchInput').value = '';
  document.getElementById('hiddenFormQ').value = '';
  form.submit();
}

function toggleMobileSheet(open) { 
    const sheet = document.getElementById('mobileSheet');
    if(sheet) sheet.classList.toggle('open', open); 
}
function openUserModal() { 
    const modal = document.getElementById('userModal');
    if(modal) modal.style.display = 'grid'; 
}
function closeUserModal() { 
    const modal = document.getElementById('userModal');
    if(modal) modal.style.display = 'none'; 
}
</script>
</body>
</html>


<script>
// 1. 手機版發布 Sheet 控制
function toggleMobileSheet(show) {
    const sheet = document.getElementById('mobileSheet');
    if(sheet) {
        if(show) sheet.classList.add('open');
        else sheet.classList.remove('open');
    }
}

// 2. 未登入彈窗控制（對齊你首頁的邏輯）
function openUserModal() {
    alert('請先登入解鎖更多功能！'); // 或者觸發你的登入彈窗
}

// 3. 搜尋下拉選單顯示/隱藏
function showDropdown() {
    const dropdown = document.getElementById('searchDropdown');
    if(dropdown) dropdown.classList.add('active'); // 確保你 CSS 有寫 .search-dropdown.active { display:block; }
}

// 點擊空白處關閉搜尋選單
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('searchWrapper');
    const dropdown = document.getElementById('searchDropdown');
    if (wrapper && !wrapper.contains(e.target) && dropdown) {
        dropdown.classList.remove('active');
    }
});

// 4. 搜尋下拉選單的頁籤切換 (故事 / 創作者 / 標籤)
function switchSearchTab(event, tabId) {
    event.stopPropagation();
    // 隱藏所有面板
    document.querySelectorAll('.dropdown-panel-content').forEach(panel => {
        panel.classList.remove('active');
    });
    // 取消所有按鈕高亮
    document.querySelectorAll('.dropdown-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    // 顯示目標面板與高亮按鈕
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

// 5. 下拉選單即時過濾（篩選創作者與標籤名稱）
function filterDropdownData() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    
    // 同步到隱藏表單
    const hiddenQ = document.getElementById('hiddenFormQ');
    if(hiddenQ) hiddenQ.value = input;

    // 過濾創作者
    document.querySelectorAll('.dropdown-author-item').forEach(item => {
        const name = item.getAttribute('data-name') || '';
        item.style.display = name.includes(input) ? 'flex' : 'none';
    });

    // 過濾下拉標籤
    document.querySelectorAll('.dropdown-tag-item').forEach(item => {
        const tag = item.getAttribute('data-tag') || '';
        item.style.display = tag.includes(input) ? 'inline-block' : 'none';
    });
}

// 6. 點擊下拉選單的標籤直接進行搜尋
function toggleTagFromSearch(tagName) {
    window.location.href = `explore.php?cat=<?= $current_cat ?>&tag[]=${encodeURIComponent(tagName)}`;
}

// 7. 複合式標籤大面板篩選（勾選/取消勾選效果）
function toggleFilterTag(element) {
    const checkbox = element.querySelector('input[type="checkbox"]');
    if(checkbox) {
        checkbox.checked = !checkbox.checked;
        if(checkbox.checked) {
            element.classList.add('active');
        } else {
            element.classList.remove('active');
        }
    }
}

// 8. 清除篩選條件
function resetFilters() {
    const form = document.getElementById('filterForm');
    if(form) {
        form.querySelectorAll('.filter-opt').forEach(opt => opt.classList.remove('active'));
        form.querySelectorAll('input[type="checkbox"]').forEach(chk => chk.checked = false);
        document.getElementById('searchInput').value = '';
        if(document.getElementById('hiddenFormQ')) document.getElementById('hiddenFormQ').value = '';
        form.submit(); // 重置後自動重新整理
    }
}

// 支援輸入搜尋關鍵字後按 Enter 直接送出表單
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('filterForm').submit();
    }
});

// ==========================================
// 🎯 核心修正：新增處理星星非同步加入/移除書架
// ==========================================
function toggleBookshelf(storyId, btnElement, event) {
    // 關鍵！阻止點擊星星時觸發底下書本卡片的 location.href 跳轉事件
    event.stopPropagation();

    const formData = new FormData();
    formData.append('story_id', storyId);

    // 呼叫你在 explore.php 最頂端第 6 區塊寫好的非同步路由
    fetch('explore.php?action=toggle_bookshelf', {
        method: 'POST',
        body: formData,
        credentials: 'include' // 攜帶 Session 狀態
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.is_favorite) {
                // 成功收藏：按鈕亮起、換成實心星
                btnElement.classList.add('active');
                btnElement.innerText = '★';
                btnElement.title = '移出書架';
            } else {
                // 成功取消收藏：按鈕暗下、換成空心星
                btnElement.classList.remove('active');
                btnElement.innerText = '☆';
                btnElement.title = '加入書架';
            }
        } else {
            // 如果未登入，會跳出你寫的「請先登入後再進行收藏喔！」
            alert(data.message);
            if(data.message.includes('登入')) {
                location.href = 'login.php'; 
            }
        }
    })
    .catch(err => {
        console.error('收藏連線失敗:', err);
        alert('系統系統忙碌中，請稍後再試！');
    });
}
</script>
</body>
</html>
