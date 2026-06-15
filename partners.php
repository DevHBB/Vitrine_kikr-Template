<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Partenaires';
require_once __DIR__ . '/layout/header.php';
$groups = get_partner_groups();
?>
<div class="section s-dark" style="padding-top:56px;"><div class="container">
  <h1 class="partners-title"><?= h(get_setting('partners_intro')) ?></h1>
  <p class="partners-sub"><?= h(get_setting('partners_subtitle')) ?></p>
  <?php foreach($groups as $group): ?>
  <div class="pg-group">
    <div class="pg-badge" style="background:<?= h($group['color']) ?>;color:<?= $group['color']==='#f5c400'?'#111':'white' ?>;"><?= h($group['label']) ?></div>
    <div class="pg-grid">
      <?php foreach($group['items'] as $item): ?>
      <div class="pg-card">
        <?php if(!empty($item['logo'])): ?>
          <div class="pg-card-logo">
            <img src="<?= h($item['logo']) ?>"
                 alt="<?= h($item['name']) ?>"
                 onerror="this.closest('.pg-card-logo').innerHTML='<div class=pg-card-name><?= h($item['name']) ?></div>'">
          </div>
        <?php else: ?>
          <div class="pg-card-logo"><div class="pg-card-name"><?= h($item['name']) ?></div></div>
        <?php endif; ?>
        <?php if($item['type']): ?><div style="font-size:10px;color:#aaa;margin-top:6px;text-align:center;"><?= h($item['type']) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div></div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
