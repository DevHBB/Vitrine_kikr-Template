<?php require_once __DIR__ . '/layout.php'; ?>
<div class="adm-topbar"><h1>Tableau de bord</h1><a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-secondary btn-sm">🔗 Voir le site</a></div>
<div class="adm-content">
<?php if($badge_count > 0): ?>
<a href="<?= BASE_URL ?>/admin/planning.php" class="notif-bar">
  <div class="notif-bar-count"><?= $badge_count ?></div>
  <div class="notif-bar-text">
    <div class="notif-bar-title">
      <?= $badge_count === 1 ? '1 nouvelle demande de RDV en attente' : "$badge_count nouvelles demandes de RDV en attente" ?>
    </div>
    <div class="notif-bar-sub">Cliquez pour voir et traiter les demandes</div>
  </div>
  <div class="notif-bar-arrow">→</div>
</a>
<?php endif; ?>
  <?php
  $nb_boxes  = db()->query('SELECT COUNT(*) FROM kk_hero_boxes')->fetchColumn();
  $nb_pilots = db()->query('SELECT COUNT(*) FROM kk_pilots')->fetchColumn();
  $nb_svc    = db()->query('SELECT COUNT(*) FROM kk_services WHERE active=1')->fetchColumn();
  $nb_pages  = db()->query('SELECT COUNT(*) FROM kk_pages')->fetchColumn();
  ?>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-val red"><?= $nb_boxes ?></div><div class="stat-lbl">Boxes hero</div></div>
    <div class="stat-card"><div class="stat-val"><?= $nb_pilots ?></div><div class="stat-lbl">Pilotes</div></div>
    <div class="stat-card"><div class="stat-val"><?= $nb_svc ?></div><div class="stat-lbl">Services</div></div>
    <div class="stat-card"><div class="stat-val"><?= $nb_pages ?></div><div class="stat-lbl">Pages libres</div></div>
  </div>
  <div class="card">
    <div class="card-head"><h2><span class="icon">🚀</span> Accès rapide</h2></div>
    <div class="quick-grid">
      <?php foreach([
        [BASE_URL.'/admin/site.php',    'Infos générales',  'Nom, tel, email, réseaux'],
        [BASE_URL.'/admin/hero.php',    'Accueil & Hero',   'Titre, moto, boxes, specs'],
        [BASE_URL.'/admin/services.php','Services',         'Tarifs, descriptions'],
        [BASE_URL.'/admin/partners.php','Partenaires',      'Groupes et logos'],
        [BASE_URL.'/admin/portfolio.php','Portfolio',       'Pilotes & photos'],
        [BASE_URL.'/admin/contact.php', 'Contact',          'Horaires & carte'],
        [BASE_URL.'/admin/media.php',   'Médiathèque',      'Toutes vos images'],
        [BASE_URL.'/admin/pages.php',   'Pages libres',     'Builder de blocs'],
        [BASE_URL.'/admin/page_edit.php','Nouvelle page',   'Créer une page'],
      ] as [$href,$label,$sub]): ?>
      <a href="<?= $href ?>" class="quick-item">
        <div><div class="quick-item-label"><?= $label ?></div><div class="quick-item-sub"><?= $sub ?></div></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
