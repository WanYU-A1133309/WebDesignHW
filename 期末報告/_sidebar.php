<?php
// 共用左側欄。使用前設 $active 變數（home/explore/bottle/profile/bookshelf/cart）
$active = $active ?? '';
?>
<aside class="sidebar" id="sidebar">
  <button class="toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="收合 / 展開">‹</button>

  <a href="index.php" class="logo-link" data-label="回首頁" title="回首頁">
    <div class="logo-mark"></div>
    <span class="logo-text">咬一口故事</span>
  </a>

  <nav class="nav">
    <a href="index.php"     class="<?= $active==='home'?'active':'' ?>"      data-label="首頁"><span class="ic ic-home"></span><span class="label">首頁</span></a>
    <a href="explore.php"   class="<?= $active==='explore'?'active':'' ?>"   data-label="探索"><span class="ic ic-search"></span><span class="label">探索</span></a>
    <a href="bottle.php"    class="<?= $active==='bottle'?'active':'' ?>"    data-label="漂流瓶"><span class="ic ic-bottle"></span><span class="label">漂流瓶</span></a>
    <div class="divider"></div>
    <a href="profile.php"   class="<?= $active==='profile'?'active':'' ?>"   data-label="個人頁"><span class="ic ic-user"></span><span class="label">個人頁</span></a>
    <a href="bookshelf.php" class="<?= $active==='bookshelf'?'active':'' ?>" data-label="我的書架"><span class="ic ic-books"></span><span class="label">我的書架</span></a>
    <a href="cart.php"      class="<?= $active==='cart'?'active':'' ?>"      data-label="購物車"><span class="ic ic-cart"></span><span class="label">購物車</span></a>
  </nav>

  <div class="publish" id="publish">
    <button class="publish-btn" data-label="發布新作" onclick="document.getElementById('publish').classList.toggle('open')">
      <span class="ic ic-pen"></span><span class="label">發布新作</span>
    </button>
    <div class="publish-menu">
      <a href="works.php"><span class="ic ic-sm ic-openbook"></span> 我的作品</a>
      <a href="drafts.php"><span class="ic ic-sm ic-draft"></span> 草稿箱</a>
      
    </div>
  </div>

  <a class="logout" href="logout.php"><span class="ic ic-sm ic-exit"></span><span class="label">登出</span></a>
</aside>

<script>
(function(){
  const sb = document.getElementById('sidebar');
  if (!sb) return;
  if (localStorage.getItem('sb') === '1') sb.classList.add('collapsed');
  sb.querySelector('.toggle').addEventListener('click', () => {
    localStorage.setItem('sb', sb.classList.contains('collapsed') ? '1' : '0');
  });
  document.addEventListener('click', e => {
    const p = document.getElementById('publish');
    if (p && !p.contains(e.target)) p.classList.remove('open');
  });
})();
</script>
