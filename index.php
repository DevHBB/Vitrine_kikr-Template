<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();
require_once __DIR__ . '/layout/header.php';

$hero   = get_hero();
$boxes  = get_hero_boxes();
$specs  = get_specs();
$cats   = array_slice(get_services(), 0, 3);
$hblks  = get_home_blocks();

$box_count  = min(3, count($boxes));
$hero_cls   = $box_count === 0 ? 'hero no-boxes' : 'hero';
$moto_left  = h($hero['moto_left']  ?? '100px');
$moto_width = h($hero['moto_width'] ?? '790px');
$hero_img   = $hero['moto_image'] ?? '';
$anim       = $hero['moto_anim']  ?? 'slide-up';
$anim_delay = (float)($hero['moto_anim_delay'] ?? 0.15);
$anim_name  = match($anim) {
    'fade'        => 'moto-fade',        'zoom'  => 'moto-zoom',
    'slide-right' => 'moto-slide-right', 'flip'  => 'moto-flip',
    'float'       => 'moto-float-in',    default => 'moto-slide-up',
};
$float_extra = $anim==='float'
    ? ',moto-float-loop 4s ease-in-out '.($anim_delay+1).'s infinite'
    : '';
?>

<!-- ==================== HERO ==================== -->
<div class="<?= $hero_cls ?>" style="--moto-left:<?= $moto_left ?>;--moto-width:<?= $moto_width ?>;">
  <div class="hero-bg"></div>

  <div class="hero-moto-zone">
    <?php if($hero_img): ?>
    <img class="hero-moto" src="<?= h($hero_img) ?>" alt="Moto Kik'r"
      style="animation:<?= $anim_name ?> 0.85s cubic-bezier(.22,1,.36,1) <?= $anim_delay ?>s both<?= $float_extra ?>;">
    <?php else: ?>
    <div class="hero-moto-empty">
      <svg width="52" height="52" fill="none" stroke="#bbb" stroke-width="1.2" viewBox="0 0 24 24">
        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
      </svg>
      <p>Uploader votre PNG moto<br><small>Admin → Accueil</small></p>
    </div>
    <?php endif; ?>

    <div class="hero-left">
      <h1 class="hero-title">
        <?= h($hero['title_line1'] ?? 'Prépa moto') ?><br>
        <?= h($hero['title_line2'] ?? 'suspensions') ?>
      </h1>
      <div class="hero-badges">
        <span class="hbadge hbadge-dk">
          <svg fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24" width="15" height="15"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <span class="hbadge hbadge-rd">
          <svg fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24" width="15" height="15"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>
        </span>
      </div>
    </div>

    <div class="hero-card">
      <div class="hc-sub"><?= h($hero['model_sub']  ?? 'Modèle phare') ?></div>
      <div class="hc-code"><?= h($hero['model_code'] ?? 'KX450F') ?></div>
      <p class="hc-desc"><?= h($hero['model_desc']   ?? '') ?></p>
      <a href="<?= BASE_URL ?>/services.php" class="hc-btn">
        <?= h($hero['btn_label'] ?? 'Découvrir') ?>
        <span class="hc-dot">
          <svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="#ed0c0f" stroke-width="2"><path d="M2 5h6M5 2l3 3-3 3"/></svg>
        </span>
      </a>
    </div>
  </div>

  <?php if($box_count > 0): ?>
  <div class="hero-boxes-right count-<?= $box_count ?>">
    <?php foreach($boxes as $box): $cls = $box['style'] ?? 'white'; ?>
    <div class="hero-box <?= h($cls) ?>">
      <?php if($box['type'] === 'reviews'): ?>
        <div class="hero-box-avs">
          <div class="hero-box-av" style="background:#555">JB</div>
          <div class="hero-box-av" style="background:#888">MR</div>
          <div class="hero-box-av" style="background:#aaa;color:#333">TC</div>
          <div class="hero-box-av" style="background:#ed0c0f;font-size:7px">+</div>
        </div>
        <div class="hero-box-reviews-text">
          <?= h($hero['reviews_count'] ?? '500+') ?> <?= h($hero['reviews_text'] ?? 'pilotes satisfaits') ?>
        </div>
        <span class="hero-box-stars">★★★★★</span>
      <?php elseif($box['type'] === 'stat'): ?>
        <?php if($box['label']): ?><div class="hero-box-label"><?= h($box['label']) ?></div><?php endif; ?>
        <?php if($box['value']): ?><div class="hero-box-value"><?= h($box['value']) ?></div><?php endif; ?>
        <?php if($box['sub']):   ?><div class="hero-box-sub"><?= h($box['sub']) ?></div><?php endif; ?>
      <?php elseif($box['type'] === 'text'): ?>
        <?php if($box['label']): ?><div class="hero-box-label"><?= h($box['label']) ?></div><?php endif; ?>
        <?php if($box['value']): ?>
        <div style="font-size:clamp(12px,1.1vw,15px);line-height:1.6;color:<?= $cls==='white'?'#444':'rgba(255,255,255,.85)' ?>;">
          <?= h($box['value']) ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ==================== STATS — 4 chiffres clés ==================== -->
<div class="specs-row">
  <?php
  $ico  = [
    '<path d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/>',
    '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
  ];
  $icls = ['rd','dk','gy','rd'];
  $iclr = ['white','white','#ed0c0f','white'];
  foreach($specs as $i => $sp): $ci = $i % 4; ?>
  <div class="spec-card">
    <div class="spec-ico <?= $icls[$ci] ?>">
      <svg fill="none" stroke="<?= $iclr[$ci] ?>" stroke-width="2.5" viewBox="0 0 24 24"><?= $ico[$ci] ?></svg>
    </div>
    <div>
      <div class="spec-val"><?= h($sp['value']) ?></div>
      <div class="spec-lbl"><?= h($sp['label']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ==================== SERVICES — 3 cartes visuelles ==================== -->
<?php if(!empty($cats)): ?>
<div class="home-services">
  <div class="home-services-header">
    <div class="home-services-title">Nos services</div>
    <a href="<?= BASE_URL ?>/services.php" class="home-services-link">
      Tout voir
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
  </div>
  <div class="home-services-grid" id="svc-grid">
    <?php foreach($cats as $cat): ?>
    <div class="svc-card" onclick="window.location='<?= BASE_URL ?>/services.php'">
      <?php if(!empty($cat['image'])): ?>
      <div class="svc-card-img"><img src="<?= h($cat['image']) ?>" alt="<?= h($cat['title']) ?>" loading="lazy"></div>
      <?php else: ?>
      <div class="svc-card-pattern"></div>
      <?php endif; ?>
      <div class="svc-card-overlay"></div>
      <div class="svc-card-body">
        <div class="svc-card-tag"><?= h($cat['label']) ?></div>
        <div class="svc-card-title"><?= h($cat['title']) ?></div>
        <div class="svc-card-price"><?= h($cat['price']) ?></div>
        <div class="svc-card-arrow">
          Voir le service
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ==================== BLOCS PERSONNALISÉS (admin) ==================== -->
<?php if(!empty($hblks)): ?>
<div class="home-blocks">
  <?php foreach($hblks as $blk):
    $bg     = 'bg-'.($blk['bg'] ?? 'white');
    $layout = $blk['layout'] ?? 'full';
    $extra  = jd($blk['extra'] ?? '{}', []);
    $type   = $blk['type'] ?? 'text';
  ?>
  <?php if($type === 'separator'): ?>
  <div style="padding:0 12px;margin-bottom:12px;"><hr style="border:none;border-top:1px solid #e8e8e6;"></div>

  <?php elseif($type === 'stats'): ?>
  <div class="hblk <?= h($bg) ?>">
    <div class="hblk-stats">
      <?php foreach(jd($blk['content']??'[]',[]) as $st): ?>
      <div class="hblk-stat">
        <div class="hblk-stat-val" style="color:<?= in_array($blk['bg'],['red','dark'])?'white':'#ed0c0f' ?>;"><?= h($st['val']??'') ?></div>
        <div class="hblk-stat-lbl"><?= h($st['lbl']??'') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php elseif($type === 'gallery'): ?>
  <div class="hblk <?= h($bg) ?>">
    <?php $cols='g'.($extra['cols']??3); $imgs=jd($blk['content']??'[]',[]); ?>
    <div class="hblk-gallery <?= h($cols) ?>">
      <?php foreach($imgs as $img): ?>
      <img src="<?= h($img) ?>" alt="" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>

  <?php elseif($type === 'cta'): ?>
  <div class="hblk <?= h($bg) ?>">
    <div class="hblk-cta-banner">
      <?php if($blk['title']): ?><div class="hblk-title"><?= h($blk['title']) ?></div><?php endif; ?>
      <?php if($blk['content']): ?><div class="hblk-content"><?= nl2br(h($blk['content'])) ?></div><?php endif; ?>
      <?php if($blk['btn_label']): ?>
      <?php $bs = in_array($blk['bg'],['dark','red']) ? 'btn-white' : 'btn-red'; ?>
      <a href="<?= h($blk['btn_url'] ?: BASE_URL.'/contact.php') ?>" class="hblk-btn <?= $bs ?>"><?= h($blk['btn_label']) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php else: /* text */ ?>
  <div class="hblk <?= h($bg) ?>" style="margin:0 12px 12px;border-radius:16px;overflow:hidden;">
    <div class="hblk-inner layout-<?= h($layout) ?>">
      <div>
        <?php if($blk['title']): ?><div class="hblk-title"><?= h($blk['title']) ?></div><?php endif; ?>
        <?php if($blk['content']): ?><div class="hblk-content"><?= nl2br(h($blk['content'])) ?></div><?php endif; ?>
        <?php if($blk['btn_label']): ?>
        <?php $bs = in_array($blk['bg'],['dark','red']) ? 'btn-white' : 'btn-dark'; ?>
        <a href="<?= h($blk['btn_url'] ?: BASE_URL.'/contact.php') ?>" class="hblk-btn <?= $bs ?>"><?= h($blk['btn_label']) ?></a>
        <?php endif; ?>
      </div>
      <?php if($blk['image'] && in_array($layout,['2col','2col-rev'])): ?>
      <div class="hblk-img"><img src="<?= h($blk['image']) ?>" alt=""></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- CSS blocs -->
<style>
.hblk{ background:white }
.hblk.bg-gray{ background:#f5f5f3 }
.hblk.bg-dark{ background:#111;color:white }
.hblk.bg-red { background:#ed0c0f;color:white }
.hblk-inner{ padding:48px 40px }
.hblk-inner.layout-full,.hblk-inner.layout-center{ max-width:760px;margin:0 auto;text-align:center }
.hblk-inner.layout-full{ max-width:100%;text-align:left }
.hblk-inner.layout-2col{ display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;max-width:100%;text-align:left }
.hblk-inner.layout-2col-rev{ display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;direction:rtl;max-width:100% }
.hblk-inner.layout-2col-rev>*{ direction:ltr }
.hblk-title{ font-size:clamp(22px,2.5vw,36px);font-weight:900;letter-spacing:-1px;line-height:1.05;margin-bottom:12px }
.hblk-content{ font-size:clamp(13px,1.1vw,16px);line-height:1.75;color:#555;margin-bottom:20px }
.hblk.bg-dark .hblk-content{ color:#aaa }
.hblk.bg-red .hblk-content{ color:rgba(255,255,255,.8) }
.hblk-img{ border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.12) }
.hblk-img img{ width:100%;display:block;object-fit:cover }
.hblk-btn{ display:inline-flex;align-items:center;gap:8px;border-radius:24px;padding:12px 22px;font-size:13px;font-weight:700;text-decoration:none;transition:transform .2s,box-shadow .2s }
.hblk-btn:hover{ transform:translateY(-2px) }
.hblk-btn.btn-dark{ background:#111;color:white;box-shadow:0 4px 14px rgba(0,0,0,.2) }
.hblk-btn.btn-red{ background:#ed0c0f;color:white;box-shadow:0 4px 14px rgba(237,12,15,.35) }
.hblk-btn.btn-white{ background:white;color:#111;box-shadow:0 4px 14px rgba(0,0,0,.1) }
.hblk-stats{ display:flex }
.hblk-stat{ flex:1;padding:36px 28px;text-align:center;border-right:1px solid rgba(0,0,0,.06) }
.hblk-stat:last-child{ border:none }
.hblk-stat-val{ font-size:clamp(36px,4vw,52px);font-weight:900;letter-spacing:-2px;line-height:1 }
.hblk-stat-lbl{ font-size:13px;color:#888;margin-top:6px;font-weight:500 }
.hblk-gallery{ display:grid;gap:8px }
.hblk-gallery.g2{ grid-template-columns:1fr 1fr }
.hblk-gallery.g3{ grid-template-columns:1fr 1fr 1fr }
.hblk-gallery.g4{ grid-template-columns:repeat(4,1fr) }
.hblk-gallery img{ width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px }
.hblk-cta-banner{ text-align:center;padding:56px 40px }
@media(max-width:768px){
  .hblk-inner{ padding:28px 20px }
  .hblk-inner.layout-2col,.hblk-inner.layout-2col-rev{ grid-template-columns:1fr }
  .hblk-stats{ flex-direction:column }
  .hblk-stat{ border-right:none;border-bottom:1px solid rgba(0,0,0,.06) }
  .hblk-gallery.g3,.hblk-gallery.g4{ grid-template-columns:1fr 1fr }
}
</style>

<!-- Animation d'entrée des cartes services au scroll -->
<script>
(function(){
  var cards = document.querySelectorAll('.svc-card');
  if(!cards.length) return;
  var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting) e.target.classList.add('visible');
    });
  }, { threshold: 0.15 });
  cards.forEach(function(c){ obs.observe(c); });
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
