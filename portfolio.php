<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Portfolio';
require_once __DIR__ . '/layout/header.php';
$pilots = get_pilots();
?>
<?php $pf_banner_img = get_setting('portfolio_banner_img',''); ?>
<div class="pf-banner">
  <div class="pf-banner-img">
    <?php if($pf_banner_img): ?>
      <img src="<?= h($pf_banner_img) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else: ?>
      <div style="width:100%;height:100%;background:linear-gradient(135deg,#111 0%,#222 100%);display:flex;align-items:center;justify-content:center;">
        <svg width="64" height="64" fill="none" stroke="#333" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      </div>
    <?php endif; ?>
  </div>
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
    <div class="pf-overlay">
      <div>
        <?php if(!empty($pilot['sponsor_logo'])): ?>
        <div style="margin-bottom:6px;"><img src="<?= h($pilot['sponsor_logo']) ?>" style="height:24px;max-width:80px;object-fit:contain;filter:brightness(0) invert(1);opacity:.9;"></div>
        <?php endif; ?>
        <div class="pf-name">
          <?= h($pilot['name']) ?>
          <?php if(!empty($pilot['number'])): ?><span style="color:#ed0c0f;font-size:18px;"> #<?= h($pilot['number']) ?></span><?php endif; ?>
        </div>
        <div class="pf-disc"><?= h($pilot['discipline']) ?></div>
      </div>
    </div>
    <div class="pf-caption">
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="cn"><?= h($pilot['name']) ?></div>
        <?php if(!empty($pilot['number'])): ?><span style="color:#ed0c0f;font-size:12px;font-weight:800;">#<?= h($pilot['number']) ?></span><?php endif; ?>
      </div>
      <div class="cd"><?= h($pilot['discipline']) ?></div>
      <?php if(!empty($pilot['sponsor_logo'])): ?>
      <div style="margin-top:4px;">
        <img src="<?= h($pilot['sponsor_logo']) ?>" style="height:18px;max-width:70px;object-fit:contain;">
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
