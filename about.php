<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Qui sommes-nous';
$ab = get_about();
$paragraphs = $ab['paragraphs'] ?? [];
$stats = json_decode($ab['stats'] ?? '[]', true) ?: [
    ['val' => $ab['experience'] ?? '22', 'lbl' => "ans d'expérience"],
    ['val' => '500+',   'lbl' => 'pilotes équipés'],
    ['val' => 'Toutes', 'lbl' => 'marques traitées'],
];
require_once __DIR__ . '/layout/header.php';
?>
<div class="pb-hero"><div class="container"><h1><?= h($ab['title'] ?? 'QUI SOMMES NOUS ?') ?></h1></div></div>
<div class="section s-white"><div class="container">
  <div class="about-grid">
    <div class="about-photo">
      <?php if(!empty($ab['photo'])): ?>
        <img src="<?= h($ab['photo']) ?>" alt="Kik'r">
      <?php else: ?>
        <div class="about-ph">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <p>Photo via admin</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="about-body">
      <?php if(!empty($ab['quote'])): ?>
      <div class="about-quote"><?= h($ab['quote']) ?></div>
      <?php endif; ?>
      <?php foreach($paragraphs as $p): if(!trim($p)) continue; ?>
      <p><?= h($p) ?></p>
      <?php endforeach; ?>
      <?php if(!empty($stats)): ?>
      <div class="about-stats">
        <?php foreach($stats as $st): if(!trim($st['val']??'')) continue; ?>
        <div>
          <div class="about-stat-val red"><?= h($st['val']) ?></div>
          <div class="about-stat-lbl"><?= h($st['lbl']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div></div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
