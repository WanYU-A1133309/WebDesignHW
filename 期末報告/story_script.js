// 用於偵測雙按 Enter
let lastEnterTime = 0;
 
// 反白相關暫存
let pendingSelectedText = '';
let pendingParaIndex    = -1;
 
// ════════════════════════════════════════════════
//  初始化
// ════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
 
    // 1. 高亮已有劃線 (網頁載入時渲染，避免影響後續的使用者反白動作)
    activeAnnotations.forEach(ann => {
        const para = document.querySelector(`#storyArticleText [data-index="${ann.paragraph_index}"]`);
        if (!para) return;

        let html = para.innerHTML;
        
        // 💡 清洗髒代碼
        html = html.replace('', '');
        html = html.replace('<?xml encoding="utf-8" ?>', '');
        
        // 💡 只有當這句話還沒被包過時，才在「網頁剛載入時」包上去
        if (html.includes(ann.selected_text) && !html.includes('highlight-text')) {
            const count = textCommentCounts[ann.selected_text.trim()] || 1;
            
            // 使用我們剛才設計的 data-count 屬性版本
            para.innerHTML = html.replace(
                ann.selected_text,
                `<span class="highlight-text" onclick="focusAnnotation(${ann.paragraph_index})" data-count="${count}">${ann.selected_text}</span>`
            );
        }
    });
 
    // 2. 反白偵測 → 顯示工具列
    const articleArea = document.getElementById('storyArticleText');
    document.addEventListener('selectionchange', () => {
        const sel  = window.getSelection();
        const text = sel.toString().trim();
        if (text.length > 2 && sel.anchorNode && articleArea.contains(sel.anchorNode.parentElement)) {
            const rect = sel.getRangeAt(0).getBoundingClientRect();
            showSelectionToolbar(rect, sel);
        } else {
            // 延遲隱藏，避免點工具列按鈕時閃失
            setTimeout(() => {
                if (!window.getSelection().toString().trim()) hideSelectionToolbar();
            }, 180);
        }
    });
 
    // 3. 點讚代理（事件委派）
    document.addEventListener('click', e => {
        const likeBtn = e.target.closest('.like-btn');
        if (likeBtn) { e.preventDefault(); likeComment(parseInt(likeBtn.dataset.id), likeBtn); }
 
        const replyTrig = e.target.closest('.reply-trigger');
        if (replyTrig) prepareToReply(parseInt(replyTrig.dataset.id), replyTrig.dataset.name);
    });
 
    // 4. 雙 Enter 送出（主輸入框）
    document.getElementById('commentTextarea').addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            const now = Date.now();
            if (now - lastEnterTime < 600) {
                e.preventDefault();
                submitMyComment(CHAPTER_ID);
            }
            lastEnterTime = now;
        }
    });
 
    // 5. 雙 Enter 送出（內嵌輸入框）
    document.getElementById('inlineCommentText').addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            const now = Date.now();
            if (now - lastEnterTime < 600) {
                e.preventDefault();
                submitInlineComment();
            }
            lastEnterTime = now;
        }
    });
 
    // 6. 點擊空白處關閉內嵌框 / 工具列
    document.addEventListener('mousedown', e => {
        const box  = document.getElementById('inlineCommentBox');
        const bar  = document.getElementById('selectionToolbar');
        if (box.style.display  !== 'none' && !box.contains(e.target))  closeInlineBox();
        if (bar.style.display  !== 'none' && !bar.contains(e.target))  hideSelectionToolbar();
    });
});
 
// ════════════════════════════════════════════════
//  反白工具列
// ════════════════════════════════════════════════
function showSelectionToolbar(rect, sel) {
    // 暫存選取內容
    const anchorEl = sel.anchorNode.parentElement;
    let para = anchorEl;
    while (para && !['P','DIV','BLOCKQUOTE'].includes(para.tagName)) para = para.parentElement;
    pendingParaIndex    = para ? (para.getAttribute('data-index') ?? -1) : -1;
    pendingSelectedText = sel.toString().trim();
 
    const bar = document.getElementById('selectionToolbar');
    bar.style.display = 'flex';
    bar.style.left    = `${rect.left + window.scrollX + rect.width / 2 - bar.offsetWidth / 2}px`;
    bar.style.top     = `${rect.top  + window.scrollY - 46}px`;
}
function hideSelectionToolbar() {
    document.getElementById('selectionToolbar').style.display = 'none';
}
 
// 選項 A：反白直接在旁邊打留言（內嵌框）- 💡 升級：固定畫面正中央
function doInlineComment() {
    if (!pendingSelectedText) return;
    hideSelectionToolbar();
    window.getSelection().removeAllRanges();

    // 1. 直接把反白文字塞進你原本就刻好的預覽區 #inlineQuotePreview
    document.getElementById('inlineQuotePreview').textContent = '「' + pendingSelectedText + '」';
    document.getElementById('inlineCommentText').value = '';

    const box = document.getElementById('inlineCommentBox');
    
    // 💡 關鍵修正：清空舊有的左邊、頂部定位資訊，完全交給 CSS fixed 機制處理
    box.style.left = '';
    box.style.top = '';
    
    // 💡 只要將 position 設為 fixed，並 display 改為 flex，它就會自己乖乖到畫面正中間
    box.style.position = 'fixed';
    box.style.display = 'flex';

    // 2. 自動把游標聚焦到輸入框裡，讓讀者可以直接打字
    setTimeout(() => {
        const textarea = document.getElementById('inlineCommentText');
        if (textarea) textarea.focus();
    }, 50);
}
 
// 選項 B：加入側欄（原始行為）
function doSidebarAnnotation() {
    if (!pendingSelectedText) return;
    hideSelectionToolbar();
    window.getSelection().removeAllRanges();
 
    document.getElementById('annotQuoteBox').style.display  = 'block';
    document.getElementById('annotQuoteText').textContent   = pendingSelectedText;
    document.getElementById('annotParaIndex').value         = pendingParaIndex;
    document.getElementById('annotSelectedText').value      = pendingSelectedText;
 
    switchCommentTab('annot');
    document.getElementById('commentTextarea').focus();
}
 
function closeInlineBox() {
    document.getElementById('inlineCommentBox').style.display = 'none';
    pendingSelectedText = '';
    pendingParaIndex    = -1;
}
 
// ════════════════════════════════════════════════
//  送出留言（共用 fetch）
// ════════════════════════════════════════════════
function postComment(payload, onSuccess) {
    const fd = new FormData();
    Object.entries(payload).forEach(([k,v]) => fd.append(k, v));
 
    fetch('story.php?action=add', { method: 'POST', body: fd, credentials: 'include' })
        .then(r => r.text())
        .then(raw => {
            try {
                const data = JSON.parse(raw.trim());
                if (data.status === 'success') onSuccess(data.comment);
                else alert(data.message);
            } catch {
                // 後端有非 JSON 混入，但可能已成功寫入
                location.reload();
            }
        })
        .catch(() => location.reload());
}
 
// 內嵌留言送出
function submitInlineComment() {
    const text = document.getElementById('inlineCommentText').value.trim();
    if (!text) return;
 
    postComment({
        chapter_id: CHAPTER_ID,
        content: text,
        parent_id: 0,
        paragraph_index: pendingParaIndex,
        selected_text: pendingSelectedText
    }, (comment) => {
        closeInlineBox();
        insertCommentCard(comment, 'annot');
        updateTabCount('annot', 1);
    });
}
 
// 側欄留言送出
function submitMyComment(chapterId) {
    const text       = document.getElementById('commentTextarea').value.trim();
    const parentId   = document.getElementById('submitParentId').value || 0;
    const paraIdx    = document.getElementById('annotParaIndex').value;
    const selText    = document.getElementById('annotSelectedText').value;
    const isAnnot    = parseInt(paraIdx) >= 0 && selText;
 
    if (!text) { alert('留言內容寫點東西吧！'); return; }
 
    postComment({
        chapter_id: chapterId,
        content: text,
        parent_id: parentId,
        paragraph_index: paraIdx,
        selected_text: selText
    }, (comment) => {
        document.getElementById('commentTextarea').value = '';
        resetReplyState();
        clearAnnotationState();
 
        if (parseInt(parentId) > 0) {
            // 插入回覆到對應留言卡片下方
            insertReplyCard(comment, parseInt(parentId));
        } else if (isAnnot) {
            insertCommentCard(comment, 'annot');
            updateTabCount('annot', 1);
        } else {
            insertCommentCard(comment, 'general');
            updateTabCount('general', 1);
        }
    });
}
 
// ════════════════════════════════════════════════
//  即時插入留言卡片（不重整）
// ════════════════════════════════════════════════
function buildCommentHTML(c, indent = false) {
    const name   = c.nickname || c.username;
    // 💡 點擊留言頭像或名稱跳轉至該使用者的 user.php
    const userPageLink = `user.php?id=${c.user_id}`;
    const userAvatar = c.avatar ? c.avatar : 'https://i.pravatar.cc/100?img=47';

    const bgLeft = indent ? 'background:#faf6ee; border-left:3px solid var(--border);' : '';
    const quote  = c.selected_text
        ? `<div style="font-size:11px; background:var(--highlight); border-left:3px solid #e6b800; padding:4px 8px; margin:6px 0; border-radius:4px; color:#665500;">「${escHtml(c.selected_text)}」</div>`
        : '';
    return `
        <div class="comment-item" id="cmt-${c.id}" data-para="${c.paragraph_index}" style="${bgLeft}">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">

                <a href="${userPageLink}">
                    <img src="${userAvatar}" style="width:24px; height:24px; border-radius:50%; object-fit:cover; cursor:pointer;">
                </a>

                <a href="${userPageLink}" style="text-decoration:none;">
                    <span style="font-weight:bold; color:var(--brand); font-size:13px; cursor:pointer;">👤 ${escHtml(name)}</span>
                </a>
                <button class="like-btn" data-id="${c.id}" style="background:none; border:none; cursor:pointer; font-size:12px; color:var(--muted); white-space:nowrap; flex-shrink:0;">
                    ❤️ <span class="lk-cnt">0</span>
                </button>
            </div>
            ${quote}
            <div style="margin:6px 0; color:var(--ink); font-size:14px; white-space:pre-line; line-height:1.6;">${escHtml(c.content)}</div>
            <div style="font-size:11px; color:var(--muted); display:flex; justify-content:space-between; align-items:center;">
                <span>🕒 ${c.created_at}</span>
                <span class="reply-trigger" data-id="${c.id}" data-name="${escHtml(name)}" style="color:var(--active-color); cursor:pointer; font-weight:bold; font-size:12px;">↩ 回覆</span>
            </div>
        </div>`;
}
 
function insertCommentCard(comment, tab) {
    const list = document.getElementById(tab === 'annot' ? 'annotCommentList' : 'generalCommentList');
    // 移除「沙發空著」提示
    const empty = list.querySelector('div[style*="text-align:center"]');
    if (empty) empty.remove();
 
    const div = document.createElement('div');
    div.innerHTML = buildCommentHTML(comment);
    list.prepend(div.firstElementChild);
    flashItem(`cmt-${comment.id}`);
}
 
function insertReplyCard(comment, parentId) {
    const parentEl = document.getElementById(`cmt-${parentId}`);
    if (!parentEl) return;
    let replyBox = parentEl.querySelector('.reply-nest');
    if (!replyBox) {
        replyBox = document.createElement('div');
        replyBox.className = 'reply-nest';
        replyBox.style.cssText = 'margin-top:8px; padding:8px 12px; border-radius:8px; background:#faf6ee; border-left:3px solid var(--border); display:flex; flex-direction:column; gap:6px;';
        parentEl.appendChild(replyBox);
    }
    const div = document.createElement('div');
    div.innerHTML = buildCommentHTML(comment, true);
    replyBox.appendChild(div.firstElementChild);
    flashItem(`cmt-${comment.id}`);
}
 
function flashItem(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    el.classList.add('highlight-focus');
    setTimeout(() => el.classList.remove('highlight-focus'), 2000);
}
 
function updateTabCount(tab, delta) {
    const el = document.getElementById(tab === 'annot' ? 'annotCount' : 'generalCount');
    if (!el) return;
    const m = el.textContent.match(/\d+/);
    if (m) el.textContent = `(${parseInt(m[0]) + delta})`;
}
 
// ════════════════════════════════════════════════
//  Tab 切換
// ════════════════════════════════════════════════
function switchCommentTab(type) {
    const isAnnot = type === 'annot';
    document.getElementById('tabGeneralBtn').classList.toggle('active', !isAnnot);
    document.getElementById('tabAnnotBtn').classList.toggle('active', isAnnot);
    document.getElementById('generalCommentList').style.display = isAnnot ? 'none' : 'flex';
    document.getElementById('annotCommentList').style.display   = isAnnot ? 'flex' : 'none';
}
 
// ════════════════════════════════════════════════
//  點讚（切換）
// ════════════════════════════════════════════════
function likeComment(commentId, btnEl) {
    const fd = new FormData();
    fd.append('comment_id', commentId);

    // 💡 防止連點：處理中先鎖住按鈕，回應回來後再解鎖
    if (btnEl.dataset.busy === '1') return;
    btnEl.dataset.busy = '1';

    fetch('story.php?action=like', {
        method: 'POST',
        body: fd,
        credentials: 'include', // 確保 Session 一定隨請求送出
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.text())
        .then(raw => {
            let data;
            try {
                data = JSON.parse(raw.trim());
            } catch (parseErr) {
                // 💡 解析失敗時把伺服器原始回應印出來，方便你直接看到是什麼雜訊混進了 JSON
                console.error('按讚回應不是合法 JSON，原始內容如下：', raw);
                throw parseErr;
            }

            if (data.status === 'success') {
                btnEl.querySelector('.lk-cnt').textContent = data.like_count;
                if (data.action === 'liked') {
                    btnEl.classList.add('liked');
                    btnEl.style.color = 'var(--active-color)';
                } else {
                    btnEl.classList.remove('liked');
                    btnEl.style.color = 'var(--muted)';
                }
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('按讚失敗，請檢查網路連線或稍後再試（詳細錯誤已印在主控台 Console）');
        })
        .finally(() => {
            btnEl.dataset.busy = '0';
        });
}
 
// ════════════════════════════════════════════════
//  回覆準備
// ════════════════════════════════════════════════
function prepareToReply(commentId, nickname) {
    document.getElementById('submitParentId').value = commentId;
    const label = document.getElementById('replyLabel');
    label.style.display  = 'block';
    label.textContent    = `↩ 正在回覆 @${nickname}`;
    const textarea = document.getElementById('commentTextarea');
    textarea.placeholder = `回覆 @${nickname}…（連按兩下 Enter 送出，Shift+Enter 換行）`;
    textarea.focus();
 
    // 清除劃線狀態（回覆不帶劃線）
    clearAnnotationState();
    switchCommentTab('general');
}
 
function resetReplyState() {
    document.getElementById('submitParentId').value = 0;
    document.getElementById('replyLabel').style.display = 'none';
    document.getElementById('commentTextarea').placeholder = '在此留下你對故事或角色的感想...（連按兩下 Enter 可快速送出）';
}
 
function clearAnnotationState() {
    document.getElementById('annotQuoteBox').style.display = 'none';
    document.getElementById('annotParaIndex').value = '-1';
    document.getElementById('annotSelectedText').value = '';
}
// ===================================================
// 🎯 核心新增：閱讀頁左側按鈕「非同步」加入或移除書架 新的
// ===================================================
function toggleStoryBookshelf(storyId, btnElement) {
    const formData = new FormData();
    formData.append('story_id', storyId);

    // 直接連動你在 explore.php 寫好的 toggle_bookshelf 邏輯
    fetch('explore.php?action=toggle_bookshelf', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const starIcon = btnElement.querySelector('.star-icon');
            const textLabel = btnElement.querySelector('.text-label');

            if (data.is_favorite) {
                btnElement.classList.add('active');
                if (starIcon) starIcon.innerText = '★';
                if (textLabel) textLabel.innerText = '已在書架';
                btnElement.title = '移出書架';
            } else {
                btnElement.classList.remove('active');
                if (starIcon) starIcon.innerText = '☆';
                if (textLabel) textLabel.innerText = '收藏至書架';
                btnElement.title = '加入書架';
            }
        } else {
            alert(data.message);
            if (data.message.includes('登入')) {
                window.location.href = 'login.php';
            }
        }
    })
    .catch(err => {
        console.error('閱讀頁收藏功能異常:', err);
        alert('系統連線忙碌中，請稍後再試！');
    });
}
 
// ════════════════════════════════════════════════
//  聚焦劃線留言
// ════════════════════════════════════════════════
// 💡 升級：點擊劃線句子時，右側評論區自動切換、排序並高亮該句的留言
function focusAnnotation(paraIndex) {
    // 1. 自動切換到「劃線重點」分頁
    switchCommentTab('annot');

    // 2. 找到文章中被點擊的那句話的文字內容
    const paraEl = document.querySelector(`#storyArticleText [data-index="${paraIndex}"]`);
    if (!paraEl) return;
    
    const highlightSpan = paraEl.querySelector('.highlight-text');
    if (!highlightSpan) return;
    
    // 取得當前點擊的劃線純文字（去掉圈圈數字）
    const clickedText = highlightSpan.textContent.replace(highlightSpan.querySelector('.count-badge')?.textContent || '', '').trim();

    // 3. 抓取劃線留言列表容器
    const annotList = document.getElementById('annotCommentList');
    if (!annotList) return;

    // 4. 將裡面所有的留言卡片 (.comment-item) 轉成陣列
    const commentItems = Array.from(annotList.querySelectorAll('.comment-item'));

    // 5. 開始排序：如果留言內包含「 clickedText 」（該句劃線），就排到最前面！
    commentItems.sort((a, b) => {
        const aHasText = a.innerHTML.includes(clickedText);
        const bHasText = b.innerHTML.includes(clickedText);
        
        if (aHasText && !bHasText) return -1; // a 排前面
        if (!aHasText && bHasText) return 1;  // b 排前面
        return 0; // 保持原樣（依點讚數/時間）
    });

    // 6. 將排序後的卡片重新塞回列表容器，並對符合的卡片加上震撼視覺特效
    annotList.innerHTML = ''; // 先清空
    
    commentItems.forEach((item, index) => {
        annotList.appendChild(item);

        // 如果這張卡片正是針對該句的留言
        if (item.innerHTML.includes(clickedText)) {
            // 加上你原本就寫好的高亮樣式
            item.classList.add('highlight-focus'); 

            // 如果是排列在第一個的精準卡片，幫評論區外層滾動條自動滾動到它面前
            if (index === 0) {
                setTimeout(() => {
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }

            // 💡 儀式感特效：3 秒後讓高亮框淡淡消失，回復正常
            setTimeout(() => {
                item.classList.remove('highlight-focus');
            }, 3000);
        }
    });
}
 
// ════════════════════════════════════════════════
//  彩蛋解鎖
// ════════════════════════════════════════════════
// ==========================================
// 🍩 核心補強：點擊按鈕使用甜甜圈解鎖彩蛋 這裡啊啊啊啊啊啊啊啊啊啊
// ==========================================
function unlockEasterEgg(chapterId, eggPrice) {
    if (!confirm(`確定要使用 ${eggPrice} 個甜甜圈 🍩 解鎖本章彩蛋番外嗎？`)) {
        return;
    }

    const formData = new FormData();
    formData.append('chapter_id', chapterId);
    formData.append('egg_price', eggPrice);

    // 呼叫你在 story.php 最頂端寫好的後端解鎖處理路由
    fetch('story.php?action=unlock_egg', {
        method: 'POST',
        body: formData,
        credentials: 'include' // 確保 Session 登入狀態有效傳遞
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            // 🎯 關鍵：解鎖成功後刷新網頁，這時你改的 PHP 就會判斷 $egg_unlocked = true，彩蛋文字就跑出來了！
            window.location.reload();
        } else {
            alert(data.message);
            if (data.message.includes('登入')) {
                window.location.href = 'login.php';
            }
        }
    })
    .catch(err => {
        console.error('解鎖連線發生異常:', err);
        alert('系統連線忙碌中，請稍後再試！');
    });
}
 
// ════════════════════════════════════════════════
//  工具函式
// ════════════════════════════════════════════════
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {

    // 💡 終極優化：讓黑底工具列在滑鼠放開時穩穩留著，絕不閃退
    document.addEventListener('mouseup', (e) => {
        const toolbar = document.getElementById('selectionToolbar');
        if (!toolbar) return;

        // 💡 防呆放行 1：如果使用者點擊的是登出按鈕或頭像選單，直接放行
        if (e.target.closest('.avatar-popover') || e.target.closest('a[href="logout.php"]')) {
            return; 
        }

        // 💡 防呆放行 2：如果點擊的是工具列內部或內嵌留言框，絕對不要執行底下的隱藏邏輯
        if (e.target.closest('#selectionToolbar') || e.target.closest('#inlineCommentBox')) {
            return;
        }

        const sel = window.getSelection();
        const text = sel.toString().trim();
        const articleArea = document.getElementById('storyArticleText');

        // 💡 修正 1：將字數限制放寬到 2 個字以上 (>= 2)
        if (text.length >= 2 && sel.anchorNode && articleArea.contains(sel.anchorNode.parentElement)) {
            
            const posX = e.clientX - 100; 
            const posY = e.clientY - 50;  

            // 💡 修正 2：用定時器稍微延遲 10 毫秒顯示，防止與瀏覽器自身的選取重繪衝突
            setTimeout(() => {
                toolbar.style.position = 'fixed';
                toolbar.style.left = `${posX}px`;
                toolbar.style.top = `${posY}px`;
                toolbar.style.display = 'flex'; 
            }, 10);

        } else {
            // 如果沒有選取文字，或是點擊空白處，就隱藏工具列
            hideSelectionToolbar();
            
            // 如果點擊的不是已劃線的黃色文字，自動把置中的內嵌留言框也關掉
            if (!e.target.closest('.highlight-text')) {
                const inlineBox = document.getElementById('inlineCommentBox');
                if (inlineBox) {
                    inlineBox.style.display = 'none';
                    inlineBox.style.transform = ''; 
                }
            }
        }
    });

    // 💡 為了防呆，原本的 hideSelectionToolbar 函數可以保持這樣：
    function hideSelectionToolbar() {
        const toolbar = document.getElementById('selectionToolbar');
        if (toolbar) toolbar.style.display = 'none';
    }
    
    // 💡 監聽全網頁的點擊事件（動態綁定收束按鈕）
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('toggle-replies-btn')) {
            const commentId = e.target.getAttribute('data-id');
            const repliesContainer = document.getElementById('replies-' + commentId);
            
            if (repliesContainer) {
                // 檢查目前是隱藏還是顯示
                if (repliesContainer.style.display === 'none' || repliesContainer.style.display === '') {
                    // 展開：將 display 改為 flex 展開，並把箭頭改成往上 ▴
                    repliesContainer.style.display = 'flex';
                    e.target.innerHTML = `💬 收摺回覆 ▴`;
                } else {
                    // 收摺：將 display 改回 none 隱藏，並換回原本的條數與往下 ▾
                    repliesContainer.style.display = 'none';
                    const count = repliesContainer.querySelectorAll(':scope > .comment-item').length;
                    e.target.innerHTML = `💬 ${count} 條回覆 ▾`;
                }
            }
        }
    });

});