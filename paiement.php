<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$token = trim($_GET['t'] ?? '');
$error = '';

if (!$token) { header('Location: ' . BASE_URL . '/'); exit; }

$s = db()->prepare("SELECT pl.*, i.client_name, i.client_email, i.number as inv_number
    FROM kk_payment_links pl
    LEFT JOIN kk_invoices i ON pl.invoice_id = i.id
    WHERE pl.token = ?");
$s->execute([$token]);
$pl = $s->fetch();

if (!$pl)                                              $error = 'Lien invalide.';
elseif ($pl['expires_at'] < date('Y-m-d H:i:s'))      $error = 'Ce lien de paiement a expiré.';
elseif (!empty($pl['used_at']))                        $error = '✅ Ce paiement a déjà été effectué. Merci !';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $method = $_POST['payment_method'] ?? '';
    if (in_array($method, ['virement', 'livraison', 'stripe', 'paypal'])) {
        db()->prepare("UPDATE kk_payment_links SET used_at = NOW() WHERE token = ?")->execute([$token]);
        if ($pl['invoice_id']) {
            db()->prepare("UPDATE kk_invoices SET status='paid', payment_method=?, payment_date=NOW() WHERE id=?")
               ->execute([$method, $pl['invoice_id']]);
        }
        // Email confirmation
        if ($pl['client_email']) {
            $sname = get_setting('site_name');
            $body  = "Bonjour {$pl['client_name']},\r\n\r\n"
                   . "Votre paiement de " . number_format((float)$pl['amount'], 2, ',', ' ') . " € a bien été enregistré";
            if ($method === 'virement') {
                $body .= ".\r\n\r\n📋 Informations de virement :\r\n"
                       . "IBAN : " . get_setting('bank_iban') . "\r\n"
                       . "BIC : "  . get_setting('bank_bic')  . "\r\n"
                       . "Montant : " . number_format((float)$pl['amount'], 2, ',', ' ') . " €\r\n"
                       . "Référence : " . ($pl['inv_number'] ?: 'PAY-'.$pl['id']);
            } else {
                $body .= " via " . match($method) {
                    'stripe' => 'carte bancaire', 'paypal' => 'PayPal',
                    'livraison' => 'paiement à la livraison', default => $method
                };
            }
            $body .= "\r\n\r\nMerci pour votre confiance !\r\nCordialement,\r\n$sname";
            mail($pl['client_email'], "✅ Paiement confirmé — $sname", $body, "From: " . get_setting('site_email'));
        }
        $success = true;
    }
}

$page_title  = 'Paiement sécurisé';
$stripe_pk   = get_setting('stripe_public_key',  '');
$paypal_cid  = get_setting('paypal_client_id',   '');
$bank_iban   = get_setting('bank_iban', '');
$sname       = get_setting('site_name', "Kik'r Suspension");
require_once __DIR__ . '/layout/header.php';
?>

<style>
.pmt-wrap{max-width:480px;margin:60px auto;padding:0 20px}
.pmt-card{background:white;border-radius:20px;padding:36px;box-shadow:0 8px 48px rgba(0,0,0,.1)}
.pmt-amount{font-size:52px;font-weight:900;letter-spacing:-3px;color:#111;line-height:1;margin-bottom:4px}
.pmt-label{font-size:14px;color:#888;margin-bottom:6px}
.pmt-note{font-size:12px;color:#aaa;margin-bottom:24px}
.pmt-method{border:2px solid #e8e8e8;border-radius:12px;padding:14px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;transition:all .25s;margin-bottom:8px}
.pmt-method:hover,.pmt-method.sel{border-color:#ed0c0f;background:#fef2f2}
.pmt-ico{font-size:22px;width:32px;text-align:center}
.pmt-btn{width:100%;background:#ed0c0f;color:white;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer;margin-top:12px;transition:all .2s}
.pmt-btn:hover{background:#c00b0d;transform:translateY(-1px)}
.pmt-secure{font-size:11px;color:#aaa;text-align:center;margin-top:10px}
.pmt-err{background:#fef2f2;border-radius:10px;padding:14px;color:#dc2626;font-size:14px;margin-bottom:16px}
.pmt-ok{text-align:center;padding:20px 0}
</style>

<div class="pmt-wrap">
<div class="pmt-card">

  <!-- Logo -->
  <?php $logo = get_setting('site_logo'); if($logo): ?>
  <img src="<?= h($logo) ?>" alt="<?= h($sname) ?>" style="height:38px;object-fit:contain;margin-bottom:20px;display:block;">
  <?php else: ?>
  <div style="font-size:22px;font-weight:900;letter-spacing:-1px;margin-bottom:20px;"><?= h($sname) ?></div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
  <div class="pmt-err"><?= h($error) ?></div>

  <?php elseif($success): ?>
  <div class="pmt-ok">
    <div style="font-size:60px;margin-bottom:16px;">✅</div>
    <h2 style="font-size:24px;font-weight:800;margin-bottom:8px;">Merci !</h2>
    <p style="color:#555;font-size:14px;line-height:1.7;">
      Votre paiement a bien été enregistré.<br>
      Un email de confirmation vous a été envoyé.
    </p>
    <?php if(isset($_POST['payment_method']) && $_POST['payment_method']==='virement'): ?>
    <div style="background:#f5f5f3;border-radius:12px;padding:16px;margin-top:20px;text-align:left;">
      <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:10px;">Coordonnées bancaires</div>
      <div style="font-size:13px;line-height:1.8;">
        <strong>IBAN :</strong> <?= h(get_setting('bank_iban')) ?><br>
        <strong>BIC :</strong>  <?= h(get_setting('bank_bic'))  ?><br>
        <strong>Montant :</strong> <?= number_format((float)$pl['amount'], 2, ',', ' ') ?> €<br>
        <strong>Référence :</strong> <?= h($pl['inv_number'] ?: 'PAY-'.$pl['id']) ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php else: ?>

  <div class="pmt-amount"><?= number_format((float)$pl['amount'], 2, ',', ' ') ?> €</div>
  <div class="pmt-label"><?= h($pl['client_name'] ? 'Pour '.$pl['client_name'] : $sname) ?></div>
  <?php if($pl['inv_number']): ?>
  <div class="pmt-note">Facture <?= h($pl['inv_number']) ?></div>
  <?php endif; ?>

  <form method="POST" id="pmt-form">

    <?php if($stripe_pk): ?>
    <div class="pmt-method" onclick="selM(this,'stripe')">
      <input type="radio" name="payment_method" value="stripe" style="display:none;">
      <span class="pmt-ico">💳</span>
      <div><div>Carte bancaire</div><div style="font-size:11px;color:#888;">Visa, Mastercard — Stripe</div></div>
      <span style="margin-left:auto;font-size:10px;background:#f0f0f0;padding:2px 6px;border-radius:5px;">🔒 Sécurisé</span>
    </div>
    <?php endif; ?>

    <?php if($paypal_cid): ?>
    <div class="pmt-method" onclick="selM(this,'paypal')">
      <input type="radio" name="payment_method" value="paypal" style="display:none;">
      <span class="pmt-ico">🅿️</span>
      <div><div>PayPal</div><div style="font-size:11px;color:#888;">Compte PayPal ou carte</div></div>
    </div>
    <?php endif; ?>

    <?php if($bank_iban): ?>
    <div class="pmt-method" onclick="selM(this,'virement')">
      <input type="radio" name="payment_method" value="virement" style="display:none;">
      <span class="pmt-ico">🏦</span>
      <div><div>Virement bancaire</div><div style="font-size:11px;color:#888;">IBAN reçu par email après confirmation</div></div>
    </div>
    <?php endif; ?>

    <div class="pmt-method" onclick="selM(this,'livraison')">
      <input type="radio" name="payment_method" value="livraison" style="display:none;">
      <span class="pmt-ico">🤝</span>
      <div><div>Paiement en main propre</div><div style="font-size:11px;color:#888;">Espèces, chèque ou CB sur place</div></div>
    </div>

    <div id="livraison-msg" style="display:none;background:#f0fdf4;border-radius:12px;padding:14px;margin-top:4px;border:1.5px solid #86efac;">
      <div style="font-size:13px;font-weight:800;color:#15803d;margin-bottom:5px;">✅ On vous attend !</div>
      <p style="font-size:12px;color:#166534;line-height:1.7;margin:0;">
        Notre équipe vous attend pour le règlement en main propre.<br>
        <strong>Votre moto ne pourra être déposée qu'après notre confirmation.</strong><br>
        Si vous souhaitez venir en même temps, appelez-nous avant :<br>
        <a href="tel:<?= h(get_setting('site_phone')) ?>" style="color:#15803d;font-weight:700;"><?= h(get_setting('site_phone')) ?></a>
      </p>
    </div>

    <!-- Stripe element -->
    <div id="stripe-box" style="display:none;margin:12px 0;">
      <div id="stripe-el" style="border:1.5px solid #e8e8e8;border-radius:10px;padding:14px;background:#fafafa;"></div>
      <div id="stripe-err" style="color:#dc2626;font-size:12px;margin-top:5px;"></div>
    </div>
    <div id="paypal-box" style="display:none;margin:12px 0;">
      <div id="paypal-container"></div>
    </div>

    <button type="submit" class="pmt-btn" id="pmt-btn">Valider →</button>
    <div class="pmt-secure">🔒 Paiement sécurisé — Aucun stockage de données bancaires</div>
  </form>
  <?php endif; ?>

</div>
</div>

<?php if(!$success && empty($error)): ?>
<?php if($stripe_pk):  ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
<?php if($paypal_cid): ?><script src="https://www.paypal.com/sdk/js?client-id=<?= h($paypal_cid) ?>&currency=EUR"></script><?php endif; ?>
<script>
function selM(el, method) {
  document.querySelectorAll('.pmt-method').forEach(function(m){ m.classList.remove('sel'); });
  el.classList.add('sel');
  el.querySelector('input').checked = true;
  document.getElementById('stripe-box').style.display   = method === 'stripe'   ? 'block' : 'none';
  document.getElementById('paypal-box').style.display   = method === 'paypal'   ? 'block' : 'none';
  document.getElementById('pmt-btn').style.display      = method === 'paypal'   ? 'none'  : 'block';
  var lm = document.getElementById('livraison-msg');
  if (lm) lm.style.display = method === 'livraison' ? 'block' : 'none';
  var btn = document.getElementById('pmt-btn');
  if (method === 'livraison') btn.textContent = 'Je paierai en main propre →';
  else if (method === 'virement') btn.textContent = 'Confirmer le virement →';
  else btn.textContent = 'Valider le paiement';
}
<?php if($stripe_pk): ?>
const stripe   = Stripe('<?= h($stripe_pk) ?>');
const elements = stripe.elements();
const card     = elements.create('card', {style:{base:{fontSize:'14px'}}});
card.mount('#stripe-el');
document.getElementById('pmt-form').addEventListener('submit', async e => {
  if (document.querySelector('[name="payment_method"]:checked')?.value === 'stripe') {
    e.preventDefault();
    const {error, paymentMethod} = await stripe.createPaymentMethod({type:'card', card});
    if (error) { document.getElementById('stripe-err').textContent = error.message; return; }
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'stripe_pm'; inp.value = paymentMethod.id;
    e.target.appendChild(inp); e.target.submit();
  }
});
<?php endif; ?>
<?php if($paypal_cid): ?>
paypal.Buttons({
  createOrder: (d,a) => a.order.create({purchase_units:[{amount:{value:'<?= number_format((float)$pl['amount'],2,'.','')?>'}}]}),
  onApprove: (d,a) => a.order.capture().then(() => {
    document.getElementById('pmt-form').insertAdjacentHTML('beforeend',
      '<input type="hidden" name="payment_method" value="paypal">' +
      '<input type="hidden" name="paypal_order_id" value="'+d.orderID+'">');
    document.getElementById('pmt-form').submit();
  })
}).render('#paypal-container');
<?php endif; ?>
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
