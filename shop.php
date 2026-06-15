<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Shop';
require_once __DIR__ . '/layout/header.php';
?>
<div class="pb-hero"><div class="container"><h1>SHOP</h1></div></div>
<div class="section s-white" style="text-align:center;padding:80px 0;"><div class="container">
  <div style="font-size:52px;margin-bottom:16px;">🔧</div>
  <h2 style="font-size:24px;font-weight:800;margin-bottom:10px;">Boutique en cours de construction</h2>
  <p style="color:#888;margin-bottom:28px;">Bientôt disponible.</p>
  <a href="<?= BASE_URL ?>/contact.php" class="btn-red">Commander sur devis</a>
</div></div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
