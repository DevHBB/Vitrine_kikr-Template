<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Page introuvable';
http_response_code(404);
require_once __DIR__ . '/layout/header.php';
?>
<div style="text-align:center;padding:80px 20px;">
  <div style="font-size:80px;font-weight:900;color:#f0f0f0;line-height:1;">404</div>
  <h1 style="font-size:24px;font-weight:800;margin-bottom:10px;">Page introuvable</h1>
  <p style="color:#888;margin-bottom:24px;">Cette page n'existe pas ou a été déplacée.</p>
  <a href="<?= BASE_URL ?>/" style="background:#ed0c0f;color:white;border-radius:10px;padding:12px 24px;font-size:13px;font-weight:700;text-decoration:none;">← Retour à l'accueil</a>
</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
