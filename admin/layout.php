<?php ob_start(); ?>
<?php
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/data.php';
start_session();
require_admin();

$cur = basename($_SERVER['PHP_SELF'], '.php');
if ($cur === 'index') $cur = 'dashboard';

// Badge demandes en attente
$badge_count = 0;
try {
    $badge_count = (int)db()->query("SELECT COUNT(*) FROM kk_appointments WHERE status='pending'")->fetchColumn();
} catch(Exception $e) {}

$menu = [
    ['sep'=>'Planning'],
    ['id'=>'planning',          'label'=>'Calendrier',         'href'=>BASE_URL.'/admin/planning.php',          'icon'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>','badge'=>true],
    ['id'=>'price_catalog',     'label'=>'Catalogue des prix',   'href'=>BASE_URL.'/admin/price_catalog.php',     'icon'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
    ['id'=>'planning_settings', 'label'=>'Paramètres planning', 'href'=>BASE_URL.'/admin/planning_settings.php','icon'=>'<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41"/>'],
    ['sep'=>'Accueil'],
    ['id'=>'home_blocks',       'label'=>'Blocs accueil',       'href'=>BASE_URL.'/admin/home_blocks.php',       'icon'=>'<rect x="3" y="3" width="18" height="5" rx="1"/><rect x="3" y="10" width="18" height="5" rx="1"/><rect x="3" y="17" width="18" height="4" rx="1"/>'],
    ['sep'=>'Site'],
    ['id'=>'site',              'label'=>'Infos générales',     'href'=>BASE_URL.'/admin/site.php',              'icon'=>'<circle cx="12" cy="12" r="10"/>'],
    ['id'=>'nav',               'label'=>'Navigation',          'href'=>BASE_URL.'/admin/nav.php',               'icon'=>'<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>'],
    ['id'=>'media',             'label'=>'Médiathèque',         'href'=>BASE_URL.'/admin/media.php',             'icon'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
    ['sep'=>'Pages fixes'],
    ['id'=>'hero',              'label'=>'Accueil',             'href'=>BASE_URL.'/admin/hero.php',              'icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
    ['id'=>'about',             'label'=>'Qui sommes-nous',     'href'=>BASE_URL.'/admin/about.php',             'icon'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ['id'=>'services',          'label'=>'Services',            'href'=>BASE_URL.'/admin/services.php',          'icon'=>'<circle cx="12" cy="12" r="3"/>'],
    ['id'=>'partners',          'label'=>'Partenaires',         'href'=>BASE_URL.'/admin/partners.php',          'icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
    ['id'=>'portfolio',         'label'=>'Portfolio',           'href'=>BASE_URL.'/admin/portfolio.php',         'icon'=>'<rect x="3" y="3" width="18" height="18" rx="2"/>'],
    ['id'=>'contact',           'label'=>'Contact',             'href'=>BASE_URL.'/admin/contact.php',           'icon'=>'<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>'],
    ['sep'=>'Pages libres'],
    ['id'=>'pages',             'label'=>'Toutes les pages',    'href'=>BASE_URL.'/admin/pages.php',             'icon'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/>'],
    ['id'=>'page_edit',         'label'=>'Nouvelle page',       'href'=>BASE_URL.'/admin/page_edit.php',         'icon'=>'<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
    ['sep'=>'Clients & Facturation'],
    ['id'=>'clients',           'label'=>'Base clients',        'href'=>BASE_URL.'/admin/clients.php',           'icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['id'=>'invoices',          'label'=>'Facturation',         'href'=>BASE_URL.'/admin/invoices.php',          'icon'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    ['id'=>'quick_payment',      'label'=>'Lien paiement rapide','href'=>BASE_URL.'/admin/quick_payment.php', 'icon'=>'<path d="M13 2H6a2 2 0 0 0-2 2v16l4-2 4 2 4-2 4 2V8z"/>'],
    ['id'=>'payment_settings',  'label'=>'Paiement',            'href'=>BASE_URL.'/admin/payment_settings.php', 'icon'=>'<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
    ['sep'=>'Communication'],
    ['id'=>'newsletter',        'label'=>'Newsletter / SMS',    'href'=>BASE_URL.'/admin/newsletter.php',        'icon'=>'<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
    ['sep'=>'Légal'],
    ['id'=>'legal',   'label'=>'Pages légales',  'href'=>BASE_URL.'/admin/legal.php',   'icon'=>'<path d="M12 1l3 6 6 .75-4.5 4.25L18 18l-6-3-6 3 1.5-5.95L3 7.75 9 7z"/>'],
    ['sep'=>'Configuration'],
    ['id'=>'modules',           'label'=>'Modules & Nav',       'href'=>BASE_URL.'/admin/modules.php',           'icon'=>'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h7v7h-7z"/>'],
    ['sep'=>'Système'],
    ['id'=>'update',   'label'=>'Mise à jour',   'href'=>BASE_URL.'/admin/update.php',  'icon'=>'<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>'],
    ['sep'=>'Compte'],
    ['id'=>'password',          'label'=>'Mot de passe',        'href'=>BASE_URL.'/admin/password.php',         'icon'=>'<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin — Kik'r</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">
</head>
<body>
<div class="adm-wrap">
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-mark"><svg viewBox="0 0 16 16" fill="white"><path d="M8 2L14 8L8 11L2 8Z"/></svg></div>
    <div class="sb-logo-text">Kik<span>'</span>r.</div>
    <span class="sb-logo-badge">ADMIN</span>
  </div>
  <nav class="sb-nav">
    <?php foreach($menu as $item): ?>
    <?php if(isset($item['sep'])): ?>
      <div class="sb-section"><?= $item['sep'] ?></div>
    <?php else: ?>
      <a href="<?= $item['href'] ?>" class="sb-link <?= $cur===$item['id']?'active':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
        <?= $item['label'] ?>
        <?php if(!empty($item['badge']) && $badge_count > 0): ?>
        <span class="sb-badge"><?= $badge_count ?></span>
        <?php endif; ?>
      </a>
    <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot">
    <a href="<?= BASE_URL ?>/" target="_blank">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Voir le site
    </a>
    <a href="<?= BASE_URL ?>/admin/logout.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Déconnexion
    </a>
  </div>
</aside>
<main class="adm-main">
