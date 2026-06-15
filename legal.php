<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$slug = trim($_GET['page'] ?? '');
$allowed = ['mentions-legales','cgv','cgu','confidentialite','retour'];
if (!$slug || !in_array($slug, $allowed)) {
    header('Location: ' . BASE_URL . '/'); exit;
}

$page = get_legal($slug);
if (!$page) { header('HTTP/1.0 404 Not Found'); exit; }

// Remplacer les variables par les vraies valeurs
$vars = [
    '{SITE_NAME}'         => get_setting('site_name',    "Kik'r Suspension"),
    '{EMAIL}'             => get_setting('site_email',   'contact@kikr-suspension.fr'),
    '{TELEPHONE}'         => get_setting('site_phone',   '+33 6 00 00 00 00'),
    '{ADRESSE}'           => get_setting('site_address', 'Sud-Est de la France'),
    '{PROPRIETAIRE}'      => get_setting('legal_owner',  get_setting('site_name', "Kik'r")),
    '{SIRET}'             => get_setting('legal_siret',  'À compléter'),
    '{FORME_JURIDIQUE}'   => get_setting('legal_forme',  'Entreprise individuelle'),
    '{HEBERGEUR}'         => get_setting('legal_host',   'À compléter'),
    '{ADRESSE_HEBERGEUR}' => get_setting('legal_host_addr', ''),
];
$content = str_replace(array_keys($vars), array_values($vars), $page['content']);
$page_title = $page['title'];

require_once __DIR__ . '/layout/header.php';
?>

<style>
.legal-wrap{max-width:820px;margin:0 auto;padding:48px 20px 60px}
.legal-header{margin-bottom:36px;padding-bottom:24px;border-bottom:2px solid #f0f0f0}
.legal-header h1{font-size:clamp(24px,3vw,36px);font-weight:900;letter-spacing:-1.5px;margin-bottom:8px}
.legal-header .upd{font-size:12px;color:#aaa}
.legal-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:36px}
.legal-nav a{
  padding:7px 14px;border-radius:20px;border:1.5px solid #e8e8e8;
  font-size:12px;font-weight:600;color:#555;text-decoration:none;
  transition:all .2s;
}
.legal-nav a:hover,.legal-nav a.active{
  border-color:#111;background:#111;color:white;
}
.legal-content{ font-size:14px;line-height:1.85;color:#333 }
.legal-content h2{ font-size:22px;font-weight:900;letter-spacing:-1px;margin:32px 0 12px;color:#111 }
.legal-content h3{ font-size:16px;font-weight:700;margin:24px 0 8px;color:#111 }
.legal-content p{ margin-bottom:14px }
.legal-content ul{ margin:10px 0 14px 20px }
.legal-content ul li{ margin-bottom:6px }
.legal-content strong{ font-weight:700;color:#111 }
.legal-content a{ color:#ed0c0f }
.legal-content em{ color:#888;font-style:italic }
</style>

<div class="legal-wrap">

  <!-- Navigation entre les pages légales -->
  <div class="legal-nav">
    <?php foreach([
      'mentions-legales' => 'Mentions légales',
      'cgv'              => 'CGV',
      'cgu'              => 'CGU',
      'confidentialite'  => 'Confidentialité',
      'retour'           => 'Retours & Remboursements',
    ] as $s => $lbl): ?>
    <a href="<?= BASE_URL ?>/legal.php?page=<?= $s ?>" class="<?= $slug===$s?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>

  <div class="legal-header">
    <h1><?= h($page['title']) ?></h1>
    <div class="upd">Dernière mise à jour : <?= date('d/m/Y', strtotime($page['updated_at'])) ?></div>
  </div>

  <div class="legal-content">
    <?= $content ?>
  </div>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
