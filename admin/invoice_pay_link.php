<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/data.php';
require_admin();
$id=(int)($_GET['id']??0);
$inv=get_invoice($id);
if(!$inv){die('Introuvable');}
$tot=invoice_totals($inv['invoice_lines'],(float)$inv['tva_rate'],(float)$inv['discount'],$inv['discount_type']);
$link='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $amount=(float)$_POST['amount']??$tot['ttc'];
    $days=(int)($_POST['expires']??7);
    $link=create_payment_link($id,$amount,$days);
}
require_once __DIR__ . '/layout.php';
?>
<div class="adm-topbar"><h1>💳 Lien de paiement — <?= h($inv['number']) ?></h1><a href="invoices.php?edit=<?= $id ?>" class="btn btn-secondary btn-sm">← Retour</a></div>
<div class="adm-content">
<div class="card" style="max-width:500px;">
  <div class="card-head"><h2>Générer un lien</h2></div>
  <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">Le client pourra payer en ligne via Stripe, PayPal, ou virement depuis ce lien.</p>
  <form method="POST">
    <div class="fgrp"><label>Montant à payer (€ TTC)</label><input type="number" name="amount" value="<?= number_format($tot['ttc'],2,'.','') ?>" step="0.01" min="0.01" required></div>
    <div class="fgrp"><label>Expiration (jours)</label><input type="number" name="expires" value="7" min="1" max="90"></div>
    <button type="submit" class="btn btn-primary">Générer le lien</button>
  </form>
  <?php if($link): ?>
  <div style="margin-top:20px;background:var(--bg);border-radius:10px;padding:16px;">
    <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:8px;">🔗 LIEN DE PAIEMENT</div>
    <div style="font-size:12px;word-break:break-all;background:white;border-radius:8px;padding:10px;border:1px solid var(--border);"><?= h($link) ?></div>
    <div style="display:flex;gap:8px;margin-top:10px;">
      <button onclick="navigator.clipboard.writeText('<?= h($link) ?>').then(()=>{this.textContent='✅ Copié!';setTimeout(()=>{this.textContent='📋 Copier'},2000)})" class="btn btn-secondary btn-sm">📋 Copier</button>
      <a href="mailto:<?= h($inv['client_email']) ?>?subject=Paiement <?= h($inv['number']) ?>&body=Bonjour <?= h($inv['client_name']) ?>,%0A%0AVeuillez cliquer sur ce lien pour payer votre <?= $inv['type']==='invoice'?'facture':'devis' ?> n°<?= h($inv['number']) ?> :%0A%0A<?= urlencode($link) ?>%0A%0ACordialement" class="btn btn-dark btn-sm">📧 Envoyer par email</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
