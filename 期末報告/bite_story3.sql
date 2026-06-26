-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-06-26 12:20:59
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `bite_story3`
--

-- --------------------------------------------------------

--
-- 資料表結構 `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL COMMENT '書名',
  `author` varchar(50) NOT NULL COMMENT '作者',
  `cover` varchar(255) NOT NULL COMMENT '封面圖片網址或路徑',
  `tag` varchar(30) NOT NULL COMMENT '標籤分类',
  `description` text DEFAULT NULL COMMENT '書籍簡介',
  `price` int(11) NOT NULL DEFAULT 100 COMMENT '購買所需的麥穗數量',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=上架中, 0=下架',
  `category` enum('featured','short','bottle') NOT NULL DEFAULT 'featured' COMMENT '分類位置',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `cover`, `tag`, `description`, `price`, `is_active`, `category`, `created_at`) VALUES
(1, '寂寞烘焙坊的午後微光', '林海微瀾', 'uploads/covers/shop_book_01.png', '療癒,奇幻', '【療癒系暢銷神作】在城市最不顯眼的轉角，有一家只在雨天和深夜營業的烘焙坊。這裡不賣普通的麵包，只賣「能喚醒遺忘記憶」的特製點心。一場關於食物、記憶與救贖的溫暖旅程。', 120, 1, 'featured', '2026-06-25 08:50:40'),
(2, '時光齒輪的逆行軌跡', '艾薩克·陳', 'uploads/covers/shop_book_02.png', '科幻,燒腦', '【燒腦科幻巨作】2046年，人類成功研發出能讓意識回到過去的「時光齒輪」技術，但每次逆行都必須付出靈魂碎片作為代價。主角莫言為了挽回十年前一場致命的火災，毅然決然踏上逆行之旅。', 180, 1, 'short', '2026-06-25 08:50:40'),
(3, '落櫻與刀：幕末孤煙錄', '橘川右衛門', 'uploads/covers/shop_book_03.png', '歷史,浪漫', '【經典歷史浪漫】動盪的幕末時代，維新浪潮席捲日本。一個隱姓埋名的天才盲眼劍客，與一位抱持著新思想的醫學少女在落櫻紛飛的京都相遇。跨越時代的命運交響曲。', 150, 1, 'featured', '2026-06-25 08:50:40'),
(4, '微光森林與長夜旅人', '鹿野尋星', 'uploads/covers/shop_book_04.png', '奇幻,冒險', '【奇幻冒險詩】傳說在世界盡頭，有一片永夜的森林，裡面居住著會發光的植物與古老的精靈。流浪的觀星者伊利亞，帶著一盞熄滅的提燈走入這片禁地，尋找能點燃生命之火的「永恆微光」。', 160, 1, 'short', '2026-06-25 08:50:40'),
(5, '藏在日常裡的微糖心理學', '蘇澄安', 'uploads/covers/shop_book_05.png', '心理,心靈', '【官方強力推薦】這不是一本教你怎麼成功的教科書，而是一杯給疲憊心靈的微糖熱可可。心理師透過25個真實的日常對話與治癒心理學，學會溫柔地擁抱那個不完美、卻無比珍貴的自己。', 90, 1, 'featured', '2026-06-25 08:50:40');

-- --------------------------------------------------------

--
-- 資料表結構 `bookshelves`
--

CREATE TABLE `bookshelves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '使用者ID',
  `story_id` int(11) NOT NULL COMMENT '收藏的故事ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `bookshelves`
--

INSERT INTO `bookshelves` (`id`, `user_id`, `story_id`, `created_at`) VALUES
(1, 1, 6, '2026-06-24 20:50:16'),
(3, 1, 5, '2026-06-24 21:22:44'),
(7, 7, 5, '2026-06-25 07:47:38'),
(8, 3, 5, '2026-06-25 10:13:33'),
(9, 5, 5, '2026-06-25 20:34:58');

-- --------------------------------------------------------

--
-- 資料表結構 `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '會員ID',
  `shop_book_id` int(11) NOT NULL COMMENT '外部書籍ID',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '數量',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `challenge_books`
--

CREATE TABLE `challenge_books` (
  `id` int(11) NOT NULL,
  `challenge_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `challenge_books`
--

INSERT INTO `challenge_books` (`id`, `challenge_id`, `book_id`) VALUES
(1, 1, 3),
(2, 2, 6),
(3, 2, 5),
(4, 2, 4);

-- --------------------------------------------------------

--
-- 資料表結構 `chapters`
--

CREATE TABLE `chapters` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL COMMENT '關聯的故事ID',
  `chapter_number` int(11) NOT NULL DEFAULT 1 COMMENT '第幾章',
  `title` varchar(255) DEFAULT NULL COMMENT '章節名稱',
  `content` longtext NOT NULL COMMENT '儲存富文本HTML內文',
  `egg_content` longtext DEFAULT NULL COMMENT '儲存付費解鎖的彩蛋富文本HTML',
  `word_count` int(11) NOT NULL DEFAULT 0 COMMENT '清洗後的真實字數',
  `status` enum('draft','published','scheduled') NOT NULL DEFAULT 'draft' COMMENT '狀態: 草稿/已發布/預約排程',
  `price_donuts` int(11) DEFAULT 0 COMMENT '彩蛋收費點數',
  `publish_at` datetime DEFAULT NULL COMMENT '發布時間',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `chapters`
--

INSERT INTO `chapters` (`id`, `story_id`, `chapter_number`, `title`, `content`, `egg_content`, `word_count`, `status`, `price_donuts`, `publish_at`, `created_at`) VALUES
(1, 1, 1, '遺落在櫻花季的微波爐', '<p data-path-to-node=\"5,1\">阿杰的便利商店裡，有一台不對外開放的微波爐。那台微波爐貼著一張泛黃的紙條，寫著：「<b>請勿加熱超過 1999 秒</b>」。</p><p data-path-to-node=\"5,2\">櫻花落下的那個下午，女孩帶著一盒已經結冰的草莓大福走了進來。她沒有說話，只是指了指那台微波爐。阿杰嘆了口氣，按下啟動鍵。隨著機械的運轉聲，微波爐裡散發出的不是食物的香氣，而是那年夏天，他們在海邊吹過的風。</p><p data-path-to-node=\"5,3\">「如果倒數結束，我還能在這裡看見你嗎？」女孩看著微波爐上跳動的數字，眼眶微濕。阿杰沒有回答，只是悄悄將時間又多按了 30 秒。</p>', NULL, 217, 'published', 0, NULL, '2026-06-18 18:58:49'),
(2, 2, 1, '第一章：最後一張全家福', '<p data-path-to-node=\"9,1\">霧氣瀰漫的十字路口，只有一盞壞掉的霓虹燈孤獨地閃爍著。招牌上寫著「時空照相館」，每逢深夜零時才開門，且每晚只接待一位客人。</p><p data-path-to-node=\"9,2\">顧城坐在吧台後，優雅地擦拭著那台 19 世紀的舊相機。門鈴響起，走進來的是一位穿著破舊西裝、渾身濕透的男子。男子的眼神裡沒有焦距，只有無盡的絕望。</p><p data-path-to-node=\"9,3\">「我想拍一張照片。」男子的聲音顫抖著，「一張……我女兒還對我微笑的照片。哪怕代價是我的靈魂。」\r\n顧城微微一笑，調整了相機的焦距：「歡迎光臨。在這裡，代價從來不是靈魂，而是你最珍貴的一段記憶。你，準備好交換了嗎？」</p>', NULL, 237, 'published', 0, NULL, '2026-06-18 19:00:44'),
(3, 2, 2, '第二章：記憶的黑盒子', '<p data-path-to-node=\"13,1\">快門喀擦一聲，白色的煙霧瞬間瀰漫了整間照相館。</p><p data-path-to-node=\"13,2\">那個濕透的男子閉上雙眼，當他再度睜開眼時，他發現自己站在十年前的女兒生日宴會上。女兒穿著粉紅色的澎澎裙，正捧著蛋糕向他跑來，嘴裡喊著：「爸爸，許願！」</p><p data-path-to-node=\"13,3\">男子流下淚水，緊緊抱住女兒。然而，站在吧台後的顧城卻看著手中的底片，眉頭深鎖。底片的邊緣，正燃燒著一絲不尋常的黑色火焰。「原來，他帶來的不是遺憾，而是詛咒。」顧城低語。</p>', '<p data-path-to-node=\"13,5\"><b data-path-to-node=\"13,5\" data-index-in-node=\"28\">【作者獨家番外：顧城的日記本】</b>\r\n其實，那個男子根本沒有女兒。十年前的那場大火裡，是他為了領取保險金，親手鎖上了女兒房間的門。他來到時空照相館，不是為了重溫美好，而是試圖在記憶中抹除自己的罪孽。</p><p data-path-to-node=\"13,6\">我給了他照片，但也收走了他「唯一還能感受到愧疚」的記憶。現在的他，只是個失去靈魂、遊走在街頭的空殼罷了。</p><p data-path-to-node=\"13,7\"><i data-path-to-node=\"13,7\" data-index-in-node=\"0\">（甜甜圈感謝祭：謝謝大家解鎖這章的彩蛋！顧城的過去將在下一章揭曉，敬請期待！）</i></p>', 182, 'published', 10, NULL, '2026-06-18 19:38:41'),
(4, 3, 1, '給機器人的情書', '<p data-path-to-node=\"17,1\">紀元 2046 年，人類文明熄滅的第十個冬天。</p><p data-path-to-node=\"17,2\">廢棄的觀測站裡，型號編號 AX-103 的家用機器人，正用生鏽的手指，在報廢的螢幕上一字一字地敲打著。它的記憶體只剩下 2% 的容量，系統每隔三分鐘就會跳出「核心過熱」的紅色警告。</p><p data-path-to-node=\"17,3\">但它沒有停止。它正在寫一封信，給那個在三千個日夜前，親手按下它啟動鍵、隨後死於寒冬的人類少女。</p><p data-path-to-node=\"17,4\">「檢測到外部溫度為零下 40 度。檢測到心跳感應訊號：無。但根據我的邏輯迴路運算，只要這封信還在傳輸，您就依然存在於我的重啟清單中。」</p>', NULL, 219, 'scheduled', 0, '2026-06-19 11:45:00', '2026-06-18 19:41:37'),
(5, 4, 1, '第一章：用一枚過期硬幣，換一場大雨', '<div>街角那台綠色的舊自動販賣機，從來不賣飲料。它的投幣孔上方貼著一張歪歪斜斜的貼紙，寫著：「不接受流通貨幣，只收過期記憶。」</div><div>午後，一個沒帶傘的少年站在販賣機前。他翻遍了口袋，最後掏出了一枚 1999 年發行、已經磨損得看不清字樣的舊硬幣。那是他小時候，外公給他的最後一個獎勵。</div><div>少年猶豫了一下，把硬幣投了進去。機器發出沉悶的運轉聲，隨後，掉下來的不是罐頭，而是一個透明的玻璃瓶。瓶子裡，正下著一場小小的、帶著泥土芳香的暴雨。</div>', NULL, 207, 'published', 0, NULL, '2026-06-18 19:51:16'),
(6, 5, 1, '第一章：最後一張全家福', '<p data-path-to-node=\"4\">在城市的邊緣，有一間只在午夜十二點後才開張的「時空照相館」。</p><p data-path-to-node=\"5\">這家店的規矩很特別：<b data-path-to-node=\"5\" data-index-in-node=\"10\">不收現金，只收你心底最珍貴的一段回憶。</b> 而作為交換，神秘的店長會允許你使用那台老舊的雙眼相機，回到過去的某個時間點，拍下一張照片。</p><p data-path-to-node=\"6\">今晚的客人，是一位眼眶泛紅、手裡緊緊抓著一張破舊照片的年輕人，阿健。</p><p data-path-to-node=\"7\">「店長，我想回到十年前的除夕夜……」阿健的聲音有些顫抖，「那一天，是我們家最後一次吃團圓飯。隔天父親就因為意外過世了。那時候我們家太窮，連一台相機都沒有，我甚至找不到一張清楚的合照來想念他。」</p><p data-path-to-node=\"8\">店長微微點頭，沒有多說什麼。他轉身從櫃子裡拿出一卷散發著微光的底片，裝進了那台沉甸甸的古董相機裡。</p><p data-path-to-node=\"9\">「記住，你只有十分鐘的時間。你只能看、只能拍照，<i data-path-to-node=\"9\" data-index-in-node=\"24\">絕對不能試圖改變過去的任何事</i>，否則你將會永遠被困在時空的夾縫中。」店長嚴肅地叮嚀著。</p><p data-path-to-node=\"10\">隨著一聲清脆的快門聲，阿健眼前的景象開始劇烈扭曲……</p>', NULL, 375, 'published', 0, NULL, '2026-06-19 05:45:41'),
(7, 6, 1, '第一章：微光相館', '<p data-path-to-node=\"8\">下著暴雨的午夜十一點四十五分，城市角落那間掛著「微光相館」木牌的老舊店面，迎來了最後一位客人。</p><p data-path-to-node=\"9\">林哲全身上下都被淋透了，唯獨懷裡緊緊護著一本泛黃的相簿。他失魂落魄地坐在櫃檯前，對著櫃檯後方正在修理老相機的老闆沙啞地說：「聽說……你這裡可以讓人回到照片裡的那一天？」</p><p data-path-to-node=\"10\">老闆停下手中的動作，抬起頭。那是一張看不出年紀的臉，眼神深邃得像一潭死水。老闆微微一笑，擦了擦指尖的黑油，淡淡地開口：「規矩很簡單。你只能回去『照片按下的那一秒』，不能改變過去，而且，代價是你未來的一年壽命。即使這樣，你也要試？」</p><p data-path-to-node=\"11\">「我要試！」林哲毫不猶豫地從相簿裡抽出一張照片。照片裡是他與罹患絕症過世的妻子，在夕陽下的海灘上開懷大笑的合影。</p><p data-path-to-node=\"12\">老闆接過照片，放進了一台笨重的、彷彿上個世紀留下來的黃銅相機裡。他調好焦距，對準了林哲：「看著鏡頭，別眨眼。」</p><p data-path-to-node=\"13\"><b data-path-to-node=\"13\" data-index-in-node=\"0\">喀擦。</b></p><p data-path-to-node=\"14\">一道強光閃過，林哲猛地睜開眼。耳邊傳來了久違的海浪聲，熟悉的茉莉花香撲鼻而來。他一轉頭，妻子那張紅潤、健康的笑臉就在眼前。她正笑著對他說：「哲，看鏡頭，一、二、三！」</p><p data-path-to-node=\"15\">林哲的眼淚瞬間奪眶而出。雖然只有一秒，雖然他什麼都不能做、不能擁抱她、不能告訴她未來的噩耗，但能再次看到她活生生地對著自己笑，這一年的壽命，值了。</p><p data-path-to-node=\"16\">光芒再度閃爍。林哲回到了冰冷的照相館，他擦乾眼淚，雖然身體感到一陣虛弱（那是失去一年壽命的代價），但他的眼神終於有了光芒。他對著老闆深深一鞠躬：「謝謝你。這是我這一年來，最快樂的一秒。」</p><p data-path-to-node=\"17\">林哲轉身推開門，走進了雨幕中。</p><p data-path-to-node=\"18\">照相館內恢復了死寂。老闆看著林哲離去的背影，無奈地嘆了口氣。他轉過身，拉開櫃檯後方的暗門。暗門裡，竟然密密麻麻地貼滿了林哲的照片。有林哲小時候、上學時、工作時，以及……林哲跟妻子相遇時的每一刻。</p><p data-path-to-node=\"19\">老闆看著鏡子裡自己那張與林哲有著七分相似、卻衰老許多的臉，苦笑著自言自語：</p><p data-path-to-node=\"20\">「傻孩子。這已經是你第七次來找我了。你以為你付出的代價，真的是你自己的壽命嗎？」</p><p data-path-to-node=\"21\">老闆劇烈地咳嗽了起來，看著自己佈滿皺紋的手。</p><p data-path-to-node=\"22\">「只要能看見你振作起來……這個當父親的，就算把剩下的壽命全給了這台相機，又有什麼關係呢？」</p><p data-path-to-node=\"23\">屋外的雨，依舊下個不停。</p>', '<p data-path-to-node=\"28\">感謝你看到這裡！其實微光相館的牆上，還掛著一面從不照人的古鏡。\r\n據說，當初父親為了開啟這台時空相機，不只交易了壽命，還交易了自己的「存在」。這就是為什麼，林哲每一次來，都認不出眼前的老闆就是失蹤多年的父親。</p><p data-path-to-node=\"29\">有些愛，哪怕被時空遺忘，也依然在角落裡默默守護著你。如果你喜歡這個故事，歡迎投餵甜甜圈，或者在下方留言告訴我你的感受喔！</p>', 877, 'published', 5, NULL, '2026-06-19 05:52:51'),
(8, 7, 1, '深夜十一點的熱牛奶', '<p data-path-to-node=\"6\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">下班時，台北下起了細雨。亮晶晶的柏油路倒映著霓虹燈光，像是一條被踩碎的銀河。</p><p data-path-to-node=\"7\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">語喬拉高了風衣領子，疲憊地走進巷弄裡那家二十四小時營業的獨立書店。這是一家不賣暢銷書，只在深夜為失眠者亮燈的地方。店主是一位年過六旬、總是笑瞇瞇的穆先生。</p><p data-path-to-node=\"8\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">「今天又加班啦？」穆先生遞過來一杯熱牛奶，杯壁貼心地墊了張厚紙巾。</p><p data-path-to-node=\"9\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">「嗯，提案又被退回了。」語喬接過杯子，溫熱的感覺從手掌心一路傳進心底，讓她緊繃了一整天的肩膀終於鬆了下來。她窩進角落的沙發裡，隨手抽了一本詩集。</p><p data-path-to-node=\"10\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">在這個小小的空間裡，沒有KPI，沒有回不完的訊息，只有窗外的雨聲和屋內淡淡的木頭香。語喬喝了一口牛奶，看著乳白色的微溫液體，突然明白，生活雖然總有挫折，但總有一些微小的善意，像這杯深夜的熱牛奶，在不為人知的角落裡，溫柔地接住每一個快要墜落的靈魂。</p><p data-path-to-node=\"11\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">離開書店時，雨停了。語喬深吸了一口夜晚清新的空氣，步伐變得輕盈起來。她知道，明天依舊有挑戰，但至少今晚，她被好好地治癒了。</p>', '<p data-path-to-node=\"6\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">深夜一點，書店的燈火依然溫暖。送走語喬後，穆先生伸了個懶懶的腰，轉身走進吧檯後方隱密的廚房。</p><p data-path-to-node=\"7\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">如果此時有熟客闖進來，一定會大吃一驚。那位平時滿口詩集、氣質儒雅的穆先生，此刻正一臉嚴肅地從冰箱裡拿出了一條巨大的美乃滋，還有一個印著銀色死魚眼圖案的馬克杯。</p><p data-path-to-node=\"8\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">這是只有在凌晨一點後、不對外營業時才會出現的「穆先生特調」。</p><p data-path-to-node=\"9\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">他將熱呼呼的黑咖啡倒進杯子裡，接著以一種極其精準、宛如武士拔刀的速度與狠勁，在黑咖啡表面用美乃滋擠出了一個完美的三層高塔。</p><p data-path-to-node=\"10\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">「呼……這才是成熟男人的味道。」穆先生滿意地喝了一口。</p><p data-path-to-node=\"11\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">這時，後門傳來輕微的抓門聲。穆先生打開門，一隻眼神犀利、神情宛如鬼之副局長的浪貓走了進來。貓咪嫌棄地看了一眼穆先生杯子裡的美乃滋咖啡，發出了一聲冷酷的「切——」，隨後熟練地用爪子拍了拍地上的貓碗。</p><p data-path-to-node=\"12\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">「好好，知道你只要高級柴魚片。」穆先生一邊碎碎念，一邊蹲下身餵貓。</p><p data-path-to-node=\"13\" style=\"caret-color: rgb(0, 0, 0); color: rgb(0, 0, 0);\">在這個城市的深夜裡，每個人都有自己不為人知、甚至是奇奇怪怪的療癒方式。只要能讓自己滿血復活，管它是讀詩、喝熱牛奶，還是來一杯美乃滋咖啡呢？</p>', 404, 'draft', 10, NULL, '2026-06-26 00:28:17');

-- --------------------------------------------------------

--
-- 資料表結構 `chapter_annotations`
--

CREATE TABLE `chapter_annotations` (
  `id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL COMMENT '關聯章節ID',
  `user_id` int(11) NOT NULL COMMENT '留言者ID',
  `paragraph_index` int(11) NOT NULL DEFAULT -1 COMMENT '第幾段話(P標籤)，-1代表一般留言',
  `selected_text` text DEFAULT NULL COMMENT '用戶反白選取的原文句子',
  `content` text NOT NULL COMMENT '評論留言內容',
  `parent_id` int(11) NOT NULL DEFAULT 0 COMMENT '被回覆的留言ID，0代表第一層主留言',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '累計點讚數',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `chapter_annotations`
--

INSERT INTO `chapter_annotations` (`id`, `chapter_id`, `user_id`, `paragraph_index`, `selected_text`, `content`, `parent_id`, `like_count`, `created_at`) VALUES
(1, 1, 1, -1, '', '屁股', 0, 0, '2026-06-24 01:13:19'),
(2, 5, 1, -1, '', '喜歡', 0, 0, '2026-06-24 01:17:16'),
(3, 5, 1, -1, '', '屁股', 0, 1, '2026-06-24 01:18:08'),
(4, 5, 1, 1, '一個沒帶傘的', '為甚麼要強調這個', 0, 0, '2026-06-24 01:19:44'),
(5, 6, 1, -1, '', '真的好看', 0, 3, '2026-06-24 01:54:09'),
(6, 6, 1, -1, '', '我也覺得', 5, 0, '2026-06-24 01:54:31'),
(7, 6, 1, 1, '不收現金，只收你心底最珍貴的一段回憶。', '刷卡可以嗎', 0, 0, '2026-06-24 01:54:55'),
(8, 6, 1, -1, '', '還好', 5, 1, '2026-06-24 03:16:31'),
(9, 6, 1, 0, '城市的邊緣', '郊區', 0, 0, '2026-06-24 03:31:51'),
(10, 6, 1, -1, '', '求踢', 0, 0, '2026-06-24 03:57:12'),
(11, 6, 1, 1, '不收現金，只收你心底最珍貴的一段回憶。', '哇喔', 0, 0, '2026-06-24 04:18:51'),
(12, 6, 1, -1, '', '?', 8, 0, '2026-06-24 07:42:20'),
(13, 6, 5, 0, '時空照相館', '時光代理人', 0, 0, '2026-06-25 22:39:03');

-- --------------------------------------------------------

--
-- 資料表結構 `daily_tasks`
--

CREATE TABLE `daily_tasks` (
  `id` int(11) NOT NULL,
  `task_key` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `reward_text` varchar(100) NOT NULL,
  `pts_reward` int(11) NOT NULL DEFAULT 10,
  `target_value` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `daily_tasks`
--

INSERT INTO `daily_tasks` (`id`, `task_key`, `title`, `description`, `reward_text`, `pts_reward`, `target_value`, `is_active`) VALUES
(1, 'signin', '每日簽到咬一口', '每日首次訪問網站即可完成', '+10 麥穗', 10, 1, 1),
(2, 'read_15', '初品書香 15 分鐘', '累積線上閱讀小說時間達 15 分鐘', '+15 麥穗', 15, 15, 1),
(3, 'read_40', '深度沉浸 40 分鐘', '今日累積線上閱讀時間', '+25 麥穗', 20, 40, 1),
(4, 'blindbox', '開啟 AI 故事盲盒', '前往盲盒中心，試試今天的命定故事', '+15 麥穗', 15, 1, 1),
(5, 'comment', '留下足跡大評閱', '在任一作品章節下方發表一則互動評論', '+10 麥穗', 15, 1, 1),
(6, 'vote', '愛的推薦票投遞', '為你喜歡的作品投出 1 張每日免費推薦票', '+10 麥穗', 10, 1, 1),
(7, 'share', '好書不藏私分享', '點擊章節分享，將故事傳遞給一位好友', '+20 麥穗', 15, 1, 1);

-- --------------------------------------------------------

--
-- 資料表結構 `drifting_bottles`
--

CREATE TABLE `drifting_bottles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT '丟瓶子的讀者ID (未登入則為 NULL)',
  `nickname` varchar(50) DEFAULT '神秘讀者' COMMENT '拋出時的暱稱',
  `story_id` int(11) NOT NULL COMMENT '關聯推薦的故事ID',
  `content` text NOT NULL COMMENT '漂流瓶的秘密心得內文',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `drifting_bottles`
--

INSERT INTO `drifting_bottles` (`id`, `user_id`, `nickname`, `story_id`, `content`, `created_at`) VALUES
(1, 1, '測試帳戶01', 3, '本來只是想點進來隨便翻翻，結果直接跌進作者架構的科幻世界裡！世界觀大氣又精緻，強烈推薦！', '2026-06-25 01:25:09'),
(2, 2, '測試帳戶02', 1, '太甜了吧！看完好想談戀愛，兩個人在微波爐前的互動描寫得好細膩，甜食黨必看！', '2026-06-25 01:25:09'),
(3, 3, '我來測試功能的', 6, '今天熬夜把這篇故事看完了！文字溫柔得像在說每個人心底最深的小祕密。看到最後章節真的忍不住掉眼淚 🕯️。', '2026-06-25 01:25:09'),
(4, 1, '我來測試功能的', 5, '希望所有人都能來看', '2026-06-25 02:36:31'),
(5, 5, 'akinaa', 1, '雷霆書名', '2026-06-25 10:58:51'),
(6, 7, '', 6, '彩蛋好細節ㄚㄚㄚㄚㄚ', '2026-06-26 00:08:01');

-- --------------------------------------------------------

--
-- 資料表結構 `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_no` varchar(50) NOT NULL COMMENT '訂單編號',
  `total_wheat` int(11) NOT NULL DEFAULT 0 COMMENT '此訂單消耗的麥穗總數',
  `status` enum('paid','cancelled') NOT NULL DEFAULT 'paid' COMMENT '狀態',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_no`, `total_wheat`, `status`, `created_at`) VALUES
(1, 3, 'WHEAT202606252012234731', 160, 'paid', '2026-06-25 10:12:23'),
(2, 5, 'WHEAT202606260048036586', 150, 'paid', '2026-06-25 22:48:03');

-- --------------------------------------------------------

--
-- 資料表結構 `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `shop_book_id` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `wheat_price` int(11) NOT NULL COMMENT '購買當下的麥穗單價',
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `shop_book_id`, `book_title`, `wheat_price`, `quantity`) VALUES
(1, 1, 4, '微光森林與長夜旅人', 160, 1),
(2, 2, 3, '落櫻與刀：幕末孤煙錄', 150, 1);

-- --------------------------------------------------------

--
-- 資料表結構 `shop_book_chapters`
--

CREATE TABLE `shop_book_chapters` (
  `id` int(11) NOT NULL,
  `shop_book_id` int(11) NOT NULL COMMENT '關聯 shop_books 的 id',
  `chapter_number` int(11) NOT NULL DEFAULT 1 COMMENT '第幾章',
  `title` varchar(255) NOT NULL COMMENT '章節標題',
  `content` longtext NOT NULL COMMENT '章節正文內容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `signin_logs`
--

CREATE TABLE `signin_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `signin_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `signin_logs`
--

INSERT INTO `signin_logs` (`id`, `user_id`, `signin_date`, `created_at`) VALUES
(1, 5, '2026-06-25', '2026-06-24 00:52:42');

-- --------------------------------------------------------

--
-- 資料表結構 `stories`
--

CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '作者ID',
  `type` enum('single','serial') DEFAULT 'serial' COMMENT '作品類型: single短篇一發完, serial長篇連載',
  `title` varchar(100) NOT NULL,
  `intro` varchar(255) DEFAULT NULL COMMENT '一句話引文',
  `tags` varchar(255) DEFAULT NULL COMMENT '逗號分隔的標籤，例: 奇幻,愛情',
  `status` varchar(20) DEFAULT 'ongoing' COMMENT '故事狀態: ongoing連載中, completed已完結',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover_type` varchar(50) DEFAULT 'none' COMMENT 'none:無封面, upload:自訂上傳, style1~style10:內建風格',
  `cover_image` varchar(255) DEFAULT NULL COMMENT '上傳的圖片檔案路徑或內建風格的顏色/樣式設定'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `type`, `title`, `intro`, `tags`, `status`, `created_at`, `cover_type`, `cover_image`) VALUES
(1, 1, 'single', '遺落在櫻花季的微波爐', '如果時間能加熱，我希望能在它冷透前，再聽一次你的聲音。', '治癒,奇幻,遺憾,微波爐,櫻花', 'published', '2026-06-18 18:58:49', 'none', NULL),
(2, 1, 'serial', '時空照相館', '這家店不拍現在，只拍你靈魂深處最想重來的那個瞬間。', '懸疑,時空旅人,溫馨,照相館,連載', 'published', '2026-06-18 19:00:44', 'none', NULL),
(3, 1, 'single', '給機器人的情書', '當世界只剩下廢鐵，我的核心程式依然只為你運行。', '科幻,末日,機器人,純愛', 'published', '2026-06-18 19:41:37', 'none', NULL),
(4, 1, 'serial', '時光販賣機的午後', '如果能回到過去，你願意用什麼來交換？', '治癒,奇幻', 'published', '2026-06-18 19:51:16', 'none', NULL),
(5, 1, 'serial', '24點的照相館', '只要按下快門，就能帶你回到最想念的那一天。', '奇幻', 'published', '2026-06-19 05:45:41', 'style3', NULL),
(6, 1, 'serial', '深夜限時的時空照相館', '按下快門，出賣現在，買回過去。', '奇幻,治癒,反轉,深夜,時空', 'published', '2026-06-19 05:52:51', 'style9', NULL),
(7, 5, 'serial', '城市裡的撿光者', '「在這個行色匆匆的城市裡，我們都在趕路，卻忘了等等那個落後的靈魂。」', '#微小說,#都會療癒,#生活點滴,#深夜食堂,#心靈雞湯', 'ongoing', '2026-06-26 00:28:17', 'style8', NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user' COMMENT '權限角色',
  `is_muted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=正常, 1=禁言',
  `is_contracted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:一般作者, 1:簽約作者',
  `donuts` int(11) NOT NULL DEFAULT 0 COMMENT '甜甜圈點數',
  `wheat` int(11) NOT NULL DEFAULT 0 COMMENT '麥穗點數',
  `reset_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT 'https://i.pravatar.cc/100?img=47' COMMENT '大頭貼網址',
  `bio` text DEFAULT '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。' COMMENT '個人簡介',
  `exp` int(11) DEFAULT 0 COMMENT '經驗值',
  `level` int(11) DEFAULT 1 COMMENT '等級',
  `gender` varchar(10) DEFAULT '不公開',
  `genre` varchar(100) DEFAULT NULL,
  `show_gender` tinyint(1) DEFAULT 1,
  `show_genre` tinyint(1) DEFAULT 1,
  `show_stats` tinyint(1) DEFAULT 1,
  `signin_streak` int(11) DEFAULT 0,
  `total_signins` int(11) DEFAULT 0,
  `last_signin_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `nickname`, `email`, `password`, `role`, `is_muted`, `is_contracted`, `donuts`, `wheat`, `reset_token`, `created_at`, `avatar`, `bio`, `exp`, `level`, `gender`, `genre`, `show_gender`, `show_genre`, `show_stats`, `signin_streak`, `total_signins`, `last_signin_date`) VALUES
(1, 'iamuser01', '測試帳戶01', 'luwanyu941211@gmail.com', '$2y$10$VYfEVl1W02wFdsSf3pzmCuXQvnyp6S8u67KoNVZLVPOwogfcexd66', 'user', 0, 0, 1995, 0, NULL, '2026-06-13 09:01:06', 'uploads/avatars/avatar_1_1782357020.jpeg', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '不公開', '', 1, 1, 1, 1, 0, '2026-06-25'),
(2, 'iamuser02', '測試帳戶02', 'cherry94121111@gmail.com', '$2y$10$pEXbCMSeUKdAH1Km.WD0dejNXjsYyyF0SE2YCTLssfqtAqJvk2nF6', 'user', 0, 1, 0, 0, NULL, '2026-06-13 09:30:59', 'https://i.pravatar.cc/100?img=47', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '不公開', NULL, 1, 1, 1, 0, 0, NULL),
(3, 'test03', '我來測試功能的', 'a1133309@mail.nuk.edu.tw', '$2y$10$SIHmMzu9cAUziwAL0hgXdOLcIPoCChgDq2.hqi1xiPVo0sS0lSLze', 'user', 0, 0, 9995, 340, NULL, '2026-06-24 08:42:02', 'https://i.pravatar.cc/100?img=47', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '不公開', NULL, 1, 1, 1, 1, 0, '2026-06-25'),
(4, 'pnm', 'pnmm', 'pnm10192081@gmail.com', '$2y$10$9BouYEpTR7.R6HQP1ZiHJOPC9ff0bPIgGSJG6srzotllvpIq33Yz6', 'user', 0, 0, 1020, 300, NULL, '2026-06-18 10:44:15', 'uploads/avatars/avatar_1_1781847053.jpeg', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '女', '', 1, 1, 1, 1, 17, '2026-06-24'),
(5, 'akina', 'akinaa', 'akinaaaaa91@gmail.com', '$2y$10$mkpcBRcnyjbYmcThPJe0oOdAC/WEjFH/uM/weKp.KvpGcG2CBmBMy', 'user', 0, 0, 95235, 29850, NULL, '2026-06-18 14:49:34', 'uploads/avatars/avatar_2_1781851851.jpeg', '土銀赤安世界第一 誰說作品老了我cp就不火了', 0, 1, '女', '', 1, 1, 1, 3, 32, '2026-06-25'),
(6, 'a', 'aa', 'a@gmail.com', '$2y$10$wD.MS3/.klc77/7NOly1IOVR8Vv8D9.GOmiYwLAP9RrNCSB8CjK9.', 'user', 0, 0, 1010, 300, NULL, '2026-06-18 15:08:22', 'https://i.pravatar.cc/100?img=47', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '不公開', NULL, 1, 1, 1, 1, 17, '2026-06-19'),
(7, 'admin01', '', '', '$2y$10$SIHmMzu9cAUziwAL0hgXdOLcIPoCChgDq2.hqi1xiPVo0sS0lSLze', 'admin', 0, 0, 45, 0, NULL, '2026-06-25 00:48:25', 'https://i.pravatar.cc/100?img=47', '喜歡溫暖的故事與夜晚的咖啡，每天讀一點點。', 0, 1, '不公開', NULL, 1, 1, 1, 0, 0, NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_name` varchar(50) NOT NULL,
  `badge_icon` varchar(20) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_name`, `badge_icon`, `awarded_at`) VALUES
(1, 5, '2026年度閱讀達人', '🏆', '2026-06-25 23:35:21');

-- --------------------------------------------------------

--
-- 資料表結構 `user_books`
--

CREATE TABLE `user_books` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0 COMMENT '閱讀進度百分比或章節數',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `user_challenges`
--

CREATE TABLE `user_challenges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_year` int(11) NOT NULL,
  `target_count` int(11) NOT NULL,
  `is_rewarded` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_challenges`
--

INSERT INTO `user_challenges` (`id`, `user_id`, `target_year`, `target_count`, `is_rewarded`, `created_at`) VALUES
(1, 1, 2026, 1, 0, '2026-06-24 19:26:01');

-- --------------------------------------------------------

--
-- 資料表結構 `user_daily_chests`
--

CREATE TABLE `user_daily_chests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `chest_20` tinyint(1) NOT NULL DEFAULT 0,
  `chest_50` tinyint(1) NOT NULL DEFAULT 0,
  `chest_80` tinyint(1) NOT NULL DEFAULT 0,
  `chest_100` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_daily_chests`
--

INSERT INTO `user_daily_chests` (`id`, `user_id`, `log_date`, `chest_20`, `chest_50`, `chest_80`, `chest_100`) VALUES
(1, 5, '2026-06-23', 0, 0, 0, 0),
(2, 5, '2026-06-24', 0, 0, 0, 0),
(3, 4, '2026-06-24', 0, 0, 0, 0),
(4, 5, '2026-06-25', 0, 0, 0, 0),
(5, 3, '2026-06-25', 0, 0, 0, 0),
(6, 1, '2026-06-25', 0, 0, 0, 0),
(7, 5, '2026-06-26', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- 資料表結構 `user_follows`
--

CREATE TABLE `user_follows` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL COMMENT '追蹤者',
  `following_id` int(11) NOT NULL COMMENT '被追蹤者',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_follows`
--

INSERT INTO `user_follows` (`id`, `follower_id`, `following_id`, `created_at`) VALUES
(1, 5, 1, '2026-06-25 22:42:52');

-- --------------------------------------------------------

--
-- 資料表結構 `user_reading_history`
--

CREATE TABLE `user_reading_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chapter_idx` int(11) DEFAULT 0,
  `chapter` int(11) DEFAULT 0,
  `chapter_number` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `user_reading_history`
--

INSERT INTO `user_reading_history` (`id`, `user_id`, `book_id`, `progress`, `updated_at`, `chapter_idx`, `chapter`, `chapter_number`) VALUES
(1, 5, 5, 100, '2026-06-26 01:36:11', 0, 0, 0),
(5, 5, 4, 100, '2026-06-25 23:55:00', 0, 0, 0),
(8, 5, 6, 100, '2026-06-25 23:55:29', 0, 0, 0),
(11, 5, 1, 100, '2026-06-25 22:36:25', 0, 0, 0),
(12, 5, 3, 100, '2026-06-25 23:58:27', 0, 0, 0),
(16, 5, 2, 50, '2026-06-25 22:37:52', 0, 0, 0);

-- --------------------------------------------------------

--
-- 資料表結構 `user_shop_shelf`
--

CREATE TABLE `user_shop_shelf` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '會員ID',
  `shop_book_id` int(11) NOT NULL COMMENT '官方書籍ID',
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `user_shop_shelf`
--

INSERT INTO `user_shop_shelf` (`id`, `user_id`, `shop_book_id`, `purchased_at`) VALUES
(1, 3, 4, '2026-06-25 10:12:23'),
(2, 5, 3, '2026-06-25 22:48:03');

-- --------------------------------------------------------

--
-- 資料表結構 `user_task_progress`
--

CREATE TABLE `user_task_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `current_value` int(11) NOT NULL DEFAULT 0,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `log_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_task_progress`
--

INSERT INTO `user_task_progress` (`id`, `user_id`, `task_id`, `current_value`, `is_done`, `log_date`) VALUES
(1, 5, 1, 0, 0, '2026-06-23'),
(2, 5, 2, 0, 0, '2026-06-23'),
(3, 5, 3, 0, 0, '2026-06-23'),
(4, 5, 4, 0, 0, '2026-06-23'),
(5, 5, 5, 0, 0, '2026-06-23'),
(6, 5, 6, 0, 0, '2026-06-23'),
(7, 5, 7, 0, 0, '2026-06-23'),
(8, 5, 1, 1, 1, '2026-06-24'),
(9, 5, 2, 0, 0, '2026-06-24'),
(10, 5, 3, 0, 0, '2026-06-24'),
(11, 5, 4, 0, 0, '2026-06-24'),
(12, 5, 5, 0, 0, '2026-06-24'),
(13, 5, 6, 0, 0, '2026-06-24'),
(14, 5, 7, 0, 0, '2026-06-24'),
(15, 4, 1, 1, 1, '2026-06-24'),
(16, 4, 2, 0, 0, '2026-06-24'),
(17, 4, 3, 0, 0, '2026-06-24'),
(18, 4, 4, 0, 0, '2026-06-24'),
(19, 4, 5, 0, 0, '2026-06-24'),
(20, 4, 6, 0, 0, '2026-06-24'),
(21, 4, 7, 0, 0, '2026-06-24'),
(22, 5, 1, 1, 1, '2026-06-25'),
(23, 5, 2, 0, 0, '2026-06-25'),
(24, 5, 3, 0, 0, '2026-06-25'),
(25, 5, 4, 0, 0, '2026-06-25'),
(26, 5, 5, 0, 0, '2026-06-25'),
(27, 5, 6, 0, 0, '2026-06-25'),
(28, 5, 7, 0, 0, '2026-06-25'),
(29, 3, 1, 1, 1, '2026-06-25'),
(30, 3, 2, 0, 0, '2026-06-25'),
(31, 3, 3, 0, 0, '2026-06-25'),
(32, 3, 4, 0, 0, '2026-06-25'),
(33, 3, 5, 0, 0, '2026-06-25'),
(34, 3, 6, 0, 0, '2026-06-25'),
(35, 3, 7, 0, 0, '2026-06-25'),
(36, 1, 1, 1, 1, '2026-06-25'),
(37, 1, 2, 0, 0, '2026-06-25'),
(38, 1, 3, 0, 0, '2026-06-25'),
(39, 1, 4, 0, 0, '2026-06-25'),
(40, 1, 5, 0, 0, '2026-06-25'),
(41, 1, 6, 0, 0, '2026-06-25'),
(42, 1, 7, 0, 0, '2026-06-25'),
(43, 5, 1, 0, 0, '2026-06-26'),
(44, 5, 2, 0, 0, '2026-06-26'),
(45, 5, 3, 0, 0, '2026-06-26'),
(46, 5, 4, 0, 0, '2026-06-26'),
(47, 5, 5, 0, 0, '2026-06-26'),
(48, 5, 6, 0, 0, '2026-06-26'),
(49, 5, 7, 0, 0, '2026-06-26');

-- --------------------------------------------------------

--
-- 資料表結構 `user_themes`
--

CREATE TABLE `user_themes` (
  `user_id` int(11) NOT NULL,
  `theme_name` varchar(20) NOT NULL,
  `unlocked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_themes`
--

INSERT INTO `user_themes` (`user_id`, `theme_name`, `unlocked_at`) VALUES
(1, 'forest', '2026-06-25 11:32:48'),
(1, 'night', '2026-06-25 11:32:44'),
(5, 'forest', '2026-06-24 16:20:42'),
(5, 'night', '2026-06-20 17:41:04'),
(5, 'sakura', '2026-06-24 16:21:23');

-- --------------------------------------------------------

--
-- 資料表結構 `user_unlocks`
--

CREATE TABLE `user_unlocks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '購買的使用者ID',
  `story_id` int(11) NOT NULL COMMENT '故事ID',
  `chapter_id` int(11) NOT NULL COMMENT '章節ID',
  `unlocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `user_unlocks`
--

INSERT INTO `user_unlocks` (`id`, `user_id`, `story_id`, `chapter_id`, `unlocked_at`) VALUES
(1, 1, 0, 7, '2026-06-24 21:10:27'),
(2, 7, 0, 7, '2026-06-25 08:07:34'),
(3, 3, 0, 7, '2026-06-25 08:35:47');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `bookshelves`
--
ALTER TABLE `bookshelves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_story_bookmark` (`user_id`,`story_id`),
  ADD KEY `fk_shelf_story` (`story_id`);

--
-- 資料表索引 `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_book_unique` (`user_id`,`shop_book_id`),
  ADD KEY `fk_cart_book_new` (`shop_book_id`);

--
-- 資料表索引 `challenge_books`
--
ALTER TABLE `challenge_books`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chapters_story` (`story_id`);

--
-- 資料表索引 `chapter_annotations`
--
ALTER TABLE `chapter_annotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_annotations_user` (`user_id`),
  ADD KEY `fk_annotations_chapter` (`chapter_id`);

--
-- 資料表索引 `daily_tasks`
--
ALTER TABLE `daily_tasks`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `drifting_bottles`
--
ALTER TABLE `drifting_bottles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bottle_story` (`story_id`),
  ADD KEY `fk_bottle_user` (`user_id`);

--
-- 資料表索引 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no_unique` (`order_no`);

--
-- 資料表索引 `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_order` (`order_id`);

--
-- 資料表索引 `shop_book_chapters`
--
ALTER TABLE `shop_book_chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_shop_chapters_book` (`shop_book_id`);

--
-- 資料表索引 `signin_logs`
--
ALTER TABLE `signin_logs`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stories_user` (`user_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nickname` (`nickname`),
  ADD UNIQUE KEY `email` (`email`);

--
-- 資料表索引 `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_badge_user` (`user_id`);

--
-- 資料表索引 `user_books`
--
ALTER TABLE `user_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- 資料表索引 `user_challenges`
--
ALTER TABLE `user_challenges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_year_unique` (`user_id`,`target_year`);

--
-- 資料表索引 `user_daily_chests`
--
ALTER TABLE `user_daily_chests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_chest_date` (`user_id`,`log_date`);

--
-- 資料表索引 `user_follows`
--
ALTER TABLE `user_follows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `follower_id` (`follower_id`),
  ADD KEY `following_id` (`following_id`);

--
-- 資料表索引 `user_reading_history`
--
ALTER TABLE `user_reading_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_book` (`user_id`,`book_id`);

--
-- 資料表索引 `user_shop_shelf`
--
ALTER TABLE `user_shop_shelf`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_book_shop_shelf_unique` (`user_id`,`shop_book_id`),
  ADD KEY `fk_shop_shelf_book_final` (`shop_book_id`);

--
-- 資料表索引 `user_task_progress`
--
ALTER TABLE `user_task_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_task_date` (`user_id`,`task_id`,`log_date`),
  ADD KEY `fk_taskprog_task` (`task_id`);

--
-- 資料表索引 `user_themes`
--
ALTER TABLE `user_themes`
  ADD PRIMARY KEY (`user_id`,`theme_name`);

--
-- 資料表索引 `user_unlocks`
--
ALTER TABLE `user_unlocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_chapter_unlock` (`user_id`,`chapter_id`),
  ADD KEY `fk_unlocks_chapter` (`chapter_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `bookshelves`
--
ALTER TABLE `bookshelves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `challenge_books`
--
ALTER TABLE `challenge_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `chapter_annotations`
--
ALTER TABLE `chapter_annotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `daily_tasks`
--
ALTER TABLE `daily_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `drifting_bottles`
--
ALTER TABLE `drifting_bottles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `shop_book_chapters`
--
ALTER TABLE `shop_book_chapters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `signin_logs`
--
ALTER TABLE `signin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_books`
--
ALTER TABLE `user_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_challenges`
--
ALTER TABLE `user_challenges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_daily_chests`
--
ALTER TABLE `user_daily_chests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_follows`
--
ALTER TABLE `user_follows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_reading_history`
--
ALTER TABLE `user_reading_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_shop_shelf`
--
ALTER TABLE `user_shop_shelf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_task_progress`
--
ALTER TABLE `user_task_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_unlocks`
--
ALTER TABLE `user_unlocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `bookshelves`
--
ALTER TABLE `bookshelves`
  ADD CONSTRAINT `fk_shelf_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shelf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_book_new` FOREIGN KEY (`shop_book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `chapters`
--
ALTER TABLE `chapters`
  ADD CONSTRAINT `fk_chapters_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `chapter_annotations`
--
ALTER TABLE `chapter_annotations`
  ADD CONSTRAINT `fk_annotations_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_annotations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `drifting_bottles`
--
ALTER TABLE `drifting_bottles`
  ADD CONSTRAINT `fk_bottle_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bottle_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- 資料表的限制式 `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `fk_stories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `user_reading_history`
--
ALTER TABLE `user_reading_history`
  ADD CONSTRAINT `user_reading_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
