<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Services';
require_once __DIR__ . '/layout/header.php';
$cats = get_services();
?>
<div class="pb-hero"><div class="container"><h1>NOS SERVICES</h1><p><?= h(get_setting('services_intro')) ?></p></div></div>
<div class="section s-dark"><div class="container">
  <div class="svc-banner">
    <?php foreach($cats as $cat): ?><div class="svc-banner-item <?= !empty($cat['image'])?'has-img':'' ?>">
      <?php if(!empty($cat['image'])): ?><img src="<?= h($cat['image']) ?>" alt=""><?php endif; ?>
      <div class="svc-banner-lbl"><?= h($cat['label']) ?></div>
    </div><?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-bottom:36px;"><span style="display:inline-block;background:#ed0c0f;color:white;font-size:16px;font-weight:900;padding:8px 24px;letter-spacing:1px;">Services ATELIER</span></div>
  <div class="svc-grid">
    <?php foreach($cats as $cat): ?>
    <div>
      <?php if(!empty($cat['image'])): ?><div class="svc-item-img"><img src="<?= h($cat['image']) ?>" alt=""></div><?php else: ?><div class="svc-item-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41"/></svg></div><?php endif; ?>
      <h3 class="svc-title"><?= h($cat['title']) ?></h3>
      <p class="svc-desc"><?= h($cat['description']) ?></p>
      <div class="svc-price"><?= h($cat['price']) ?></div>
      <?php if(!empty($cat['highlight'])): ?><div class="svc-highlight"><?= h($cat['highlight']) ?></div><?php endif; ?>
      <?php $tr=jd($cat['treatments']??'[]',[]); if($tr): ?><ul class="svc-treatments"><?php foreach($tr as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:36px;padding-top:28px;border-top:1px solid #222;font-size:13px;color:#888;">
    <strong style="color:white;"><?= h(get_setting('services_depose_repose')) ?></strong><br>
    <span style="margin-top:6px;display:block;"><?= h(get_setting('services_disclaimer')) ?></span>
  </div>
</div></div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
