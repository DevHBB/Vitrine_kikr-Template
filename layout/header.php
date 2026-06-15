<?php
// layout/header.php
$nav      = get_nav();
$cur      = basename($_SERVER['PHP_SELF']);
$sname    = get_setting('site_name',    "Kik'r");
$stagline = get_setting('site_tagline', 'Préparation de suspensions moto');
$sphone   = get_setting('site_phone',   '+33 6 00 00 00 00');
$slogo    = get_setting('site_logo',    '');   // URL logo image
$slogo_h  = get_setting('site_logo_height', '38'); // hauteur en px
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($sname) ?><?= isset($page_title) ? ' — '.h($page_title) : ' — '.h($stagline) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<div class="page-wrap">

<?php if(is_admin()): ?>
<div class="admin-bar">
  🔧 Mode Admin actif —
  <span><a href="<?= BASE_URL ?>/admin/">Tableau de bord</a> <a href="<?= BASE_URL ?>/admin/logout.php">Déconnexion</a></span>
</div>
<?php endif; ?>

<header>
<nav class="navbar">

  <!-- LOGO : image si uploadée, sinon texte -->
  <a href="<?= BASE_URL ?>/index.php" class="nav-logo">
    <?php if($slogo): ?>
      <img src="<?= h($slogo) ?>"
           alt="<?= h($sname) ?>"
           style="height:<?= h($slogo_h) ?>px;width:auto;display:block;object-fit:contain;">
    <?php else: ?>
      <div class="nav-logo-icon">
        <svg viewBox="0 0 16 16" fill="white"><path d="M8 2L14 8L8 11L2 8Z"/></svg>
      </div>
      <?= h($sname) ?><span style="font-weight:400;font-size:16px;color:#666;">.</span>
    <?php endif; ?>
  </a>

  <div class="nav-links">
    <?php
    // Séparer parents et enfants
    $nav_parents  = array_filter($nav, fn($n) => !$n['parent_id'] && $n['active']);
    $nav_children = array_filter($nav, fn($n) => $n['parent_id']  && $n['active']);
    $children_by_parent = [];
    foreach($nav_children as $child) $children_by_parent[$child['parent_id']][] = $child;
    foreach($nav_parents as $item):
      $children = $children_by_parent[$item['id']] ?? [];
      $has_children = !empty($children);
    ?>
    <?php if($has_children): ?>
    <div class="nav-dropdown" style="position:relative;">
      <a href="<?= BASE_URL ?>/<?= h($item['href']) ?>"
         class="<?= $cur===$item['href']?'active':'' ?> nav-dropdown-toggle">
        <?= h($item['label']) ?> <span style="font-size:9px;opacity:.6;">▾</span>
      </a>
      <div class="nav-dropdown-menu">
        <?php foreach($children as $child): ?>
        <a href="<?= h(str_starts_with($child['href'],'http')?$child['href']:BASE_URL.'/'.$child['href']) ?>"
           target="<?= h($child['target']??'_self') ?>"
           class="<?= $cur===$child['href']?'active':'' ?>">
          <?= h($child['label']) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= h(str_starts_with($item['href'],'http')?$item['href']:BASE_URL.'/'.$item['href']) ?>"
       target="<?= h($item['target']??'_self') ?>"
       class="<?= $cur===$item['href']?'active':'' ?>"><?= h($item['label']) ?></a>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="nav-right">
    <?php if($sphone): ?>
    <span class="nav-phone">
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.31 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
      </svg>
      <?= h($sphone) ?>
    </span>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/contact.php" class="btn-outline">Devis gratuit</a>
    <a href="<?= BASE_URL ?>/shop.php" class="btn-dark">
      <svg width="12" height="12" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
      </svg>
      Shop
    </a>
    <button class="nav-burger" id="burger" onclick="toggleMobile()" type="button">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<div class="nav-mobile" id="nav-mobile" style="display:none;">
  <?php foreach($nav as $item): ?>
  <a href="<?= BASE_URL ?>/<?= h($item['href']) ?>"
     class="<?= $cur===$item['href']?'active':'' ?>"><?= h($item['label']) ?></a>
  <?php endforeach; ?>
  <div class="nav-mobile-cta">
    <a href="<?= BASE_URL ?>/contact.php" class="btn-red" style="flex:1;justify-content:center;">Devis gratuit</a>
    <a href="<?= BASE_URL ?>/shop.php" class="btn-outline" style="flex:1;text-align:center;">Shop</a>
  </div>
</div>
</header>

<script>
function toggleMobile(){
  var b=document.getElementById('burger'),m=document.getElementById('nav-mobile');
  b.classList.toggle('open');
  m.style.display=(m.style.display==='flex')?'none':'flex';
}
</script>
