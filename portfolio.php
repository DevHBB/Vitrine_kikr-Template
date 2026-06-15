<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Portfolio';
require_once __DIR__ . '/layout/header.php';
$pilots = get_pilots();
?>
<div class="pf-banner">
  <div class="pf-banner-img"><div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;"><svg width="48" height="48" fill="none" stroke="#333" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div></div>
  <div class="pf-banner-text">
    <div class="pf-banner-title"><?= h(get_setting('portfolio_title','PORTFOLIO')) ?></div>
    <div class="pf-banner-sub"><?= h(get_setting('portfolio_subtitle','Ils nous font confiance.')) ?></div>
  </div>
</div>
<div class="pf-grid">
  <?php foreach($pilots as $pilot): ?>
  <div class="pf-item">
    <?php if(!empty($pilot['photo'])): ?>
    <img src="<?= h($pilot['photo']) ?>" alt="<?= h($pilot['name']) ?>"
         style="width:100%;height:100%;object-fit:cover;"
         onerror="this.parentElement.style.background='#1a1a1a'">
    <?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#1a1a1a;"><svg width="44" height="44" fill="none" stroke="#555" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.58-7 8-7s8 3 8 7"/></svg></div><?php endif; ?>
    <div class="pf-overlay"><div><div class="pf-name"><?= h($pilot['name']) ?></div><div class="pf-disc"><?= h($pilot['discipline']) ?></div></div></div>
    <div class="pf-caption"><div class="cn"><?= h($pilot['name']) ?></div><div class="cd"><?= h($pilot['discipline']) ?></div></div>
  </div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
