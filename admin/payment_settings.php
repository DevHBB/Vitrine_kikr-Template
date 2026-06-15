<?php
require_once __DIR__ . '/layout.php';
$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $fields=['stripe_public_key','stripe_secret_key','paypal_client_id','paypal_secret','bank_iban','bank_bic','bank_name'];
    foreach($fields as $k) set_setting($k,trim($_POST[$k]??''));
    set_setting('payment_before_deposit',isset($_POST['payment_before_deposit'])?'1':'0');
    set_setting('payment_required',isset($_POST['payment_required'])?'1':'0');
    $saved=true;
}
?>
<div class="adm-topbar"><h1>💳 Paramètres paiement</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<form method="POST">

<div class="card">
  <div class="card-head"><h2><span class="icon">🎛️</span> Options générales</h2></div>
  <div style="display:flex;flex-direction:column;gap:12px;">
    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= get_setting('payment_before_deposit','0')==='1'?'var(--red)':'var(--border)' ?>;border-radius:12px;cursor:pointer;background:<?= get_setting('payment_before_deposit','0')==='1'?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="payment_before_deposit" <?= get_setting('payment_before_deposit','0')==='1'?'checked':'' ?> style="width:18px;height:18px;accent-color:#ed0c0f;">
      <div><div style="font-size:13px;font-weight:700;">💳 Paiement avant dépôt de la moto</div><div style="font-size:11px;color:var(--muted);">À la confirmation du RDV, un lien de paiement est envoyé automatiquement au client</div></div>
    </label>
    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= get_setting('payment_required','0')==='1'?'var(--red)':'var(--border)' ?>;border-radius:12px;cursor:pointer;background:<?= get_setting('payment_required','0')==='1'?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="payment_required" <?= get_setting('payment_required','0')==='1'?'checked':'' ?> style="width:18px;height:18px;accent-color:#ed0c0f;">
      <div><div style="font-size:13px;font-weight:700;">⚠️ Paiement obligatoire avant confirmation</div><div style="font-size:11px;color:var(--muted);">Le RDV n'est confirmé qu'après réception du paiement</div></div>
    </label>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><span class="icon">💳</span> Stripe</h2></div>
  <p class="card-hint">Clés disponibles sur <a href="https://dashboard.stripe.com/apikeys" target="_blank">dashboard.stripe.com</a></p>
  <div class="g2">
    <div class="fgrp"><label>Clé publique (pk_live_…)</label><input type="text" name="stripe_public_key" value="<?= h(get_setting('stripe_public_key')) ?>" placeholder="pk_live_…"></div>
    <div class="fgrp"><label>Clé secrète (sk_live_…)</label><input type="password" name="stripe_secret_key" value="<?= h(get_setting('stripe_secret_key')) ?>" placeholder="sk_live_…"></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><span class="icon">🅿️</span> PayPal</h2></div>
  <p class="card-hint">Clés disponibles sur <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank">developer.paypal.com</a></p>
  <div class="g2">
    <div class="fgrp"><label>Client ID</label><input type="text" name="paypal_client_id" value="<?= h(get_setting('paypal_client_id')) ?>"></div>
    <div class="fgrp"><label>Secret</label><input type="password" name="paypal_secret" value="<?= h(get_setting('paypal_secret')) ?>"></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><span class="icon">🏦</span> Virement bancaire</h2></div>
  <div class="g2">
    <div class="fgrp"><label>IBAN</label><input type="text" name="bank_iban" value="<?= h(get_setting('bank_iban')) ?>" placeholder="FR76 …"></div>
    <div class="fgrp"><label>BIC</label><input type="text" name="bank_bic" value="<?= h(get_setting('bank_bic')) ?>" placeholder="BNPAFRPP"></div>
    <div class="fgrp"><label>Nom de la banque</label><input type="text" name="bank_name" value="<?= h(get_setting('bank_name')) ?>"></div>
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
</form>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
