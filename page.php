<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . BASE_URL . '/'); exit; }
$page = get_page_by_slug($slug);
if (!$page || $page['status'] !== 'published') {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}
$page_title = $page['title'];
require_once __DIR__ . '/layout/header.php';
?>
<div class="pb-hero"><div class="container">
  <h1><?= h($page['title']) ?></h1>
  <?php if($page['subtitle']): ?><p><?= h($page['subtitle']) ?></p><?php endif; ?>
</div></div>
<div class="section s-white"><div class="container">
<?php foreach(($page['blocks'] ?? []) as $blk):
  $type = $blk['type'] ?? 'text';
?>
<?php if($type === 'text'): ?>
  <div style="margin-bottom:32px;">
    <?php if(!empty($blk['title'])): ?><h2 style="font-size:24px;font-weight:800;margin-bottom:12px;"><?= h($blk['title']) ?></h2><?php endif; ?>
    <?php if(!empty($blk['content'])): ?><div style="font-size:15px;line-height:1.8;color:#555;"><?= nl2br(h($blk['content'])) ?></div><?php endif; ?>
    <?php if(!empty($blk['image'])): ?><img src="<?= h($blk['image']) ?>" style="max-width:100%;border-radius:12px;margin-top:16px;"><?php endif; ?>
  </div>
<?php elseif($type === 'image'): ?>
  <div style="margin-bottom:32px;"><img src="<?= h($blk['image'] ?? '') ?>" style="width:100%;border-radius:12px;"></div>
<?php endif; ?>
<?php endforeach; ?>
</div></div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
