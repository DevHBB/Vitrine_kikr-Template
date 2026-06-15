<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . BASE_URL . '/'); exit; }

$page = get_page_by_slug($slug);

if (!$page) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// Page en brouillon : accessible seulement aux admins
if ($page['status'] !== 'published') {
    if (!is_admin()) {
        http_response_code(404);
        require_once __DIR__ . '/404.php';
        exit;
    }
    // Admin peut voir le brouillon avec bandeau
    $draft_preview = true;
}

$page_title = $page['title'];
require_once __DIR__ . '/layout/header.php';

$bg_map = [
    'white' => '#ffffff',
    'gray'  => '#f5f5f3',
    'dark'  => '#111111',
    'red'   => '#ed0c0f',
];
?>

<?php if(!empty($draft_preview)): ?>
<div style="background:#fef9c3;border-bottom:2px solid #fbbf24;padding:10px 20px;font-size:13px;font-weight:700;color:#854d0e;text-align:center;">
  ⚠️ Brouillon — cette page n'est pas encore publiée.
  <a href="<?= BASE_URL ?>/admin/page_edit.php?slug=<?= h($slug) ?>" style="color:#854d0e;margin-left:12px;">Modifier →</a>
</div>
<?php endif; ?>

<?php if(!empty($page['banner'])): ?>
<div style="width:100%;max-height:380px;overflow:hidden;">
  <img src="<?= h($page['banner']) ?>" style="width:100%;object-fit:cover;display:block;">
</div>
<?php endif; ?>

<div style="max-width:960px;margin:0 auto;padding:48px 24px;">
  <h1 style="font-size:clamp(28px,4vw,48px);font-weight:900;letter-spacing:-2px;margin-bottom:<?= $page['subtitle']?'8px':'32px' ?>;"><?= h($page['title']) ?></h1>
  <?php if(!empty($page['subtitle'])): ?>
  <p style="font-size:16px;color:#888;margin-bottom:32px;"><?= h($page['subtitle']) ?></p>
  <?php endif; ?>
</div>

<?php foreach(($page['blocks'] ?? []) as $blk):
  $type = $blk['type'] ?? 'text';
  $bg   = $bg_map[$blk['bg'] ?? 'white'] ?? '#fff';
  $dark = ($blk['bg'] ?? '') === 'dark';
?>
<section style="background:<?= $bg ?>;padding:48px 0;">
<div style="max-width:960px;margin:0 auto;padding:0 24px;">

  <?php if($type === 'heading'): ?>
    <?php $lvl = (int)($blk['level'] ?? 2); $align = $blk['align'] ?? 'left'; ?>
    <h<?= $lvl ?> style="font-size:<?= $lvl===2?'32px':'22px' ?>;font-weight:900;letter-spacing:-1px;text-align:<?= $align ?>;color:<?= $dark?'white':'#111' ?>;">
      <?= h($blk['text'] ?? '') ?>
    </h<?= $lvl ?>>

  <?php elseif($type === 'text'): ?>
    <div style="font-size:15px;line-height:1.85;color:<?= $dark?'#ccc':'#555' ?>;text-align:<?= $blk['align']??'left' ?>;">
      <?= nl2br(h($blk['content'] ?? '')) ?>
    </div>

  <?php elseif($type === 'image'): ?>
    <div style="text-align:center;">
      <img src="<?= h($blk['src'] ?? '') ?>" alt="<?= h($blk['alt'] ?? '') ?>"
           style="max-width:<?= h($blk['max_width'] ?? '100%') ?>;border-radius:12px;display:block;margin:0 auto;">
      <?php if(!empty($blk['caption'])): ?>
      <p style="font-size:12px;color:#aaa;margin-top:8px;"><?= h($blk['caption']) ?></p>
      <?php endif; ?>
    </div>

  <?php elseif($type === 'separator'): ?>
    <hr style="border:none;border-top:2px solid <?= !empty($blk['red'])?'#ed0c0f':'#e8e8e8' ?>;margin:0;">

  <?php elseif($type === 'cta'): ?>
    <div style="text-align:center;padding:20px 0;">
      <?php if(!empty($blk['title'])): ?><h2 style="font-size:28px;font-weight:900;margin-bottom:8px;color:<?= $dark?'white':'#111' ?>;"><?= h($blk['title']) ?></h2><?php endif; ?>
      <?php if(!empty($blk['subtitle'])): ?><p style="color:<?= $dark?'#aaa':'#888' ?>;margin-bottom:20px;"><?= h($blk['subtitle']) ?></p><?php endif; ?>
      <?php if(!empty($blk['btn_label'])): ?>
      <a href="<?= h($blk['btn_url'] ?? '#') ?>" style="display:inline-block;background:#ed0c0f;color:white;border-radius:10px;padding:12px 28px;font-size:14px;font-weight:800;text-decoration:none;">
        <?= h($blk['btn_label']) ?>
      </a>
      <?php endif; ?>
    </div>

  <?php elseif($type === 'gallery'): ?>
    <?php $cols = (int)($blk['cols'] ?? 3); ?>
    <div style="display:grid;grid-template-columns:repeat(<?= $cols ?>,1fr);gap:12px;">
      <?php foreach(($blk['images'] ?? []) as $img): ?>
      <img src="<?= h($img) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;">
      <?php endforeach; ?>
    </div>

  <?php elseif($type === 'columns'): ?>
    <?php $ncols = $blk['num_cols'] ?? count($blk['cols'] ?? [[]]); ?>
    <div style="display:grid;grid-template-columns:repeat(<?= $ncols ?>,1fr);gap:24px;">
      <?php foreach(($blk['cols'] ?? []) as $col): ?>
      <div>
        <?php if(!empty($col['image'])): ?><img src="<?= h($col['image']) ?>" style="width:100%;border-radius:10px;margin-bottom:12px;object-fit:cover;"><?php endif; ?>
        <?php if(!empty($col['title'])): ?><h3 style="font-size:18px;font-weight:800;margin-bottom:8px;color:<?= $dark?'white':'#111' ?>;"><?= h($col['title']) ?></h3><?php endif; ?>
        <?php if(!empty($col['text'])): ?><p style="font-size:14px;line-height:1.7;color:<?= $dark?'#ccc':'#555' ?>;"><?= nl2br(h($col['text'])) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

  <?php elseif($type === 'video'): ?>
    <?php
      $vu = $blk['url'] ?? '';
      preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $vu, $m);
      $vid = $m[1] ?? '';
    ?>
    <?php if($vid): ?>
    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;">
      <iframe src="https://www.youtube.com/embed/<?= h($vid) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" allowfullscreen></iframe>
    </div>
    <?php endif; ?>

  <?php elseif($type === 'accordion'): ?>
    <div>
      <?php foreach(($blk['items'] ?? []) as $k => $item): ?>
      <details style="border-bottom:1px solid #f0f0f0;padding:14px 0;">
        <summary style="font-size:14px;font-weight:700;cursor:pointer;color:<?= $dark?'white':'#111' ?>;list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <?= h($item['q'] ?? '') ?> <span style="color:#ed0c0f;font-size:18px;">+</span>
        </summary>
        <p style="font-size:14px;line-height:1.7;color:<?= $dark?'#ccc':'#555' ?>;margin-top:10px;"><?= nl2br(h($item['a'] ?? '')) ?></p>
      </details>
      <?php endforeach; ?>
    </div>

  <?php elseif($type === 'banner_hero'): ?>
    <div style="position:relative;border-radius:16px;overflow:hidden;min-height:300px;display:flex;align-items:center;justify-content:center;text-align:center;<?= !empty($blk['image'])?'background:url('.h($blk['image']).') center/cover no-repeat;':'background:#111;' ?>">
      <div style="position:absolute;inset:0;background:rgba(0,0,0,.5);"></div>
      <div style="position:relative;padding:48px 24px;color:white;">
        <?php if(!empty($blk['title'])): ?><h2 style="font-size:36px;font-weight:900;margin-bottom:10px;"><?= h($blk['title']) ?></h2><?php endif; ?>
        <?php if(!empty($blk['subtitle'])): ?><p style="font-size:16px;color:#ccc;margin-bottom:20px;"><?= h($blk['subtitle']) ?></p><?php endif; ?>
        <?php if(!empty($blk['btn_label'])): ?><a href="<?= h($blk['btn_url']??'#') ?>" style="display:inline-block;background:#ed0c0f;color:white;border-radius:10px;padding:12px 28px;font-size:14px;font-weight:800;text-decoration:none;"><?= h($blk['btn_label']) ?></a><?php endif; ?>
      </div>
    </div>

  <?php endif; ?>

</div>
</section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
