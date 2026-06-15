<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$email = trim($_GET['e'] ?? '');
if ($email) {
    try {
        db()->prepare("UPDATE kk_newsletter_subscribers SET status='unsubscribed' WHERE email=?")->execute([$email]);
    } catch(Exception $e) {}
}
$page_title = 'Désabonnement';
require_once __DIR__ . '/layout/header.php';
?>
<div style="max-width:480px;margin:80px auto;text-align:center;padding:0 20px;">
  <div style="font-size:48px;margin-bottom:16px;">✅</div>
  <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Désabonnement confirmé</h2>
  <p style="color:#666;font-size:14px;">Vous ne recevrez plus nos emails.<br>Vous pouvez vous réinscrire à tout moment.</p>
  <a href="<?= BASE_URL ?>/" style="display:inline-block;margin-top:20px;background:#111;color:white;border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;text-decoration:none;">Retour au site</a>
</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
