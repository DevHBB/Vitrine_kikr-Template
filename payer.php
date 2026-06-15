<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$token  = trim($_GET['t'] ?? '');
$amount = (float)($_GET['a'] ?? 0);
$error  = '';
$rdv    = null;

if (!$token) { header('Location: ' . BASE_URL . '/'); exit; }

$s = db()->prepare("SELECT * FROM kk_appointments WHERE payment_link_token=?");
$s->execute([$token]);
$rdv = $s->fetch();

if (!$rdv) {
    $error = 'Lien invalide ou expiré.';
} elseif ($rdv['payment_status'] === 'paid') {
    $error = 'Ce paiement a déjà été effectué. Merci !';
} else {
    // Utiliser le prix final si dispo, sinon estimation, sinon GET
    $amount = (float)($rdv['price_final'] ?? $rdv['price_estimate'] ?? $amount);
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rdv && !$error) {
    $method = $_POST['payment_method'] ?? '';
    if (in_array($method, ['virement','livraison'])) {
        db()->prepare("UPDATE kk_appointments SET payment_status=?,updated_at=NOW() WHERE id=?")
           ->execute([$method === 'livraison' ? 'partial' : 'link_sent', $rdv['id']]);
        // Email confirmation
        $from  = "From: " . get_setting('site_email');
        $sname = get_setting('site_name');
        if ($method === 'virement') {
            $body = "Bonjour {$rdv['client_name']},\r\n\r\n"
                  . "Votre demande de paiement par virement a été enregistrée.\r\n\r\n"
                  . "Montant : " . number_format($amount, 2, ',', ' ') . " €\r\n"
                  . "IBAN : " . get_setting('bank_iban') . "\r\n"
                  . "BIC : "  . get_setting('bank_bic')  . "\r\n"
                  . "Référence : RDV-{$rdv['id']}\r\n\r\n"
                  . "Cordialement,\r\n$sname";
        } else {
            $body = "Bonjour {$rdv['client_name']},\r\n\r\nVotre choix de paiement à la livraison a été enregistré.\r\nMontant à régler lors du dépôt : " . number_format($amount, 2, ',', ' ') . " €\r\n\r\nCordialement,\r\n$sname";
        }
        mail($rdv['client_email'], "Confirmation paiement — $sname", $body, $from);
        $success = true;
    }
}

$page_title = 'Paiement sécurisé';
require_once __DIR__ . '/layout/header.php';
$stripe_pk  = get_setting('stripe_public_key', '');
$paypal_cid = get_setting('paypal_client_id', '');
$bank_iban  = get_setting('bank_iban', '');
?>

<style>
.payer-wrap{max-width:480px;margin:60px auto;padding:0 20px}
.payer-card{background:white;border-radius:20px;padding:36px;box-shadow:0 8px 48px rgba(0,0,0,.1)}
.payer-sname{font-size:13px;color:#aaa;margin-bottom:20px;font-weight:500}
.payer-amount{font-size:48px;font-weight:900;letter-spacing:-2px;color:#111;line-height:1}
.payer-ref{font-size:12px;color:#888;margin:6px 0 28px;line-height:1.6}
.payer-note{background:#fef9c3;border-radius:8px;padding:10px 14px;font-size:12px;color:#854d0e;margin-bottom:20px}
.pay-methods{display:flex;flex-direction:column;gap:8px;margin-bottom:20px}
.pay-method{border:2px solid #e8e8e8;border-radius:12px;padding:14px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;transition:all .2s;background:white}
.pay-method:hover,.pay-method.sel{border-color:#ed0c0f;background:#fef2f2}
.pay-ico{font-size:20px;width:30px;text-align:center}
.pay-btn{width:100%;background:#111;color:white;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer;transition:all .2s}
.pay-btn:hover{background:#333;transform:translateY(-1px)}
.pay-secure{font-size:11px;color:#aaa;text-align:center;margin-top:10px}
.payer-success{text-align:center;padding:20px 0}
.payer-error{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;color:#dc2626;font-size:13px;margin-bottom:20px}
</style>

<div class="payer-wrap">
<div class="payer-card">
  <?php $logo = get_setting('site_logo'); if($logo): ?>
  <img src="<?= h($logo) ?>" alt="" style="height:36px;object-fit:contain;margin-bottom:16px;display:block;">
  <?php else: ?>
  <div style="font-size:20px;font-weight:900;margin-bottom:16px;"><?= h(get_setting('site_name',"Kik'r")) ?></div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
  <div class="payer-error">❌ <?= h($error) ?></div>
  <?php return; ?>
  <?php endif; ?>

  <?php if($success): ?>
  <div class="payer-success">
    <div style="font-size:56px;margin-bottom:14px;">✅</div>
    <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Merci !</h2>
    <p style="color:#555;font-size:14px;line-height:1.6;">
      Votre choix de paiement a été enregistré.<br>
      Vous recevrez une confirmation par email.
    </p>
  </div>

  <?php else: ?>
  <div class="payer-amount"><?= number_format($amount, 2, ',', ' ') ?> €</div>
  <div class="payer-ref">
    <?= h(get_setting('site_name')) ?> — RDV #<?= $rdv['id'] ?><br>
    <?= h($rdv['service_label']) ?> — <?= h(trim($rdv['moto_marque'].' '.$rdv['moto_modele'])) ?>
    <?php if($rdv['slot_date']): ?><br>📅 <?= date('d/m/Y', strtotime($rdv['slot_date'])) ?><?php endif; ?>
  </div>
  <?php if($rdv['price_note']): ?>
  <div class="payer-note">ℹ️ <?= h($rdv['price_note']) ?></div>
  <?php endif; ?>

  <form method="POST" id="pay-form">
    <div class="pay-methods">

      <?php if($stripe_pk): ?>
      <label class="pay-method" onclick="selM(this,'stripe')">
        <input type="radio" name="payment_method" value="stripe" style="display:none;">
        <span class="pay-ico">💳</span>
        <div><div>Carte bancaire</div><div style="font-size:11px;color:#888;">Visa, Mastercard — Stripe sécurisé</div></div>
        <span style="margin-left:auto;font-size:10px;color:#aaa;">🔒</span>
      </label>
      <?php endif; ?>

      <?php if($paypal_cid): ?>
      <label class="pay-method" onclick="selM(this,'paypal')">
        <input type="radio" name="payment_method" value="paypal" style="display:none;">
        <span class="pay-ico">🅿️</span>
        <div><div>PayPal</div><div style="font-size:11px;color:#888;">Compte PayPal ou carte</div></div>
      </label>
      <?php endif; ?>

      <?php if($bank_iban): ?>
      <label class="pay-method" onclick="selM(this,'virement')">
        <input type="radio" name="payment_method" value="virement" style="display:none;">
        <span class="pay-ico">🏦</span>
        <div><div>Virement bancaire</div><div style="font-size:11px;color:#888;">IBAN envoyé par email</div></div>
      </label>
      <?php endif; ?>

      <label class="pay-method" onclick="selM(this,'livraison')">
        <input type="radio" name="payment_method" value="livraison" style="display:none;">
        <span class="pay-ico">🤝</span>
        <div><div>Paiement lors du dépôt</div><div style="font-size:11px;color:#888;">Espèces, chèque ou CB sur place</div></div>
      </label>
    </div>

    <!-- Stripe card element -->
    <div id="stripe-box" style="display:none;margin-bottom:16px;">
      <div id="stripe-el" style="border:1.5px solid #e8e8e8;border-radius:10px;padding:14px;background:white;"></div>
      <div id="stripe-err" style="color:#dc2626;font-size:12px;margin-top:6px;"></div>
    </div>
    <!-- Message paiement sur place -->
    <div id="livraison-msg" style="display:none;background:#f0fdf4;border-radius:12px;padding:16px;margin-bottom:14px;border:1.5px solid #86efac;">
      <div style="font-size:14px;font-weight:800;color:#15803d;margin-bottom:6px;">✅ Parfait, on vous attend !</div>
      <p style="font-size:13px;color:#166534;line-height:1.7;margin:0;">
        Notre équipe vous attend pour le paiement en main propre.<br>
        <strong>Votre moto ne pourra être déposée qu'après confirmation de notre part.</strong><br><br>
        Si vous souhaitez déposer votre moto dès votre venue, appelez-nous d'abord pour vérifier nos disponibilités.<br>
        <a href="tel:<?= h(get_setting('site_phone')) ?>" style="color:#15803d;font-weight:700;"><?= h(get_setting('site_phone')) ?></a>
      </p>
    </div>

    <!-- PayPal boutons -->
    <div id="paypal-box" style="display:none;margin-bottom:14px;">
      <div id="paypal-btn-container" style="min-height:45px;"></div>
      <?php if(!$paypal_cid): ?>
      <div style="background:#fef9c3;border-radius:8px;padding:10px;font-size:12px;color:#854d0e;">⚠️ PayPal non configuré — allez dans Admin → Paiement pour ajouter vos clés.</div>
      <?php endif; ?>
    </div>

    <button type="submit" class="pay-btn" id="pay-btn">Valider le paiement</button>
    <div class="pay-secure">🔒 Paiement 100% sécurisé</div>
  </form>
  <?php endif; ?>
</div>
</div>

<?php if(!$success && !$error): ?>
<script>
function selM(lbl, method) {
  document.querySelectorAll('.pay-method').forEach(m => m.classList.remove('sel'));
  lbl.classList.add('sel');
  lbl.querySelector('input').checked = true;
  document.getElementById('stripe-box').style.display  = method === 'stripe'  ? 'block' : 'none';
  document.getElementById('paypal-box').style.display  = method === 'paypal'  ? 'block' : 'none';
  document.getElementById('pay-btn').style.display     = method === 'paypal'  ? 'none'  : 'block';
}
<?php if($stripe_pk): ?>
var stripe = Stripe('<?= h($stripe_pk) ?>');
var elements = stripe.elements();
var card = elements.create('card', {style:{base:{fontSize:'14px',color:'#111'}}});
card.mount('#stripe-el');
document.getElementById('pay-form').addEventListener('submit', async function(e) {
  if (document.querySelector('[name="payment_method"]:checked')?.value === 'stripe') {
    e.preventDefault();
    var {error, paymentMethod} = await stripe.createPaymentMethod({type:'card', card:card});
    if (error) { document.getElementById('stripe-err').textContent = error.message; return; }
    var inp = document.createElement('input'); inp.type='hidden'; inp.name='stripe_pm'; inp.value=paymentMethod.id;
    this.appendChild(inp); this.submit();
  }
});
<?php endif; ?>
<?php if($paypal_cid): ?>
paypal.Buttons({
  createOrder: (d,a) => a.order.create({purchase_units:[{amount:{value:'<?= number_format($amount,2,'.','')?>'}}]}),
  onApprove: (d,a) => a.order.capture().then(details => {
    var f=document.getElementById('pay-form');
    f.insertAdjacentHTML('beforeend','<input type="hidden" name="payment_method" value="paypal"><input type="hidden" name="paypal_order_id" value="'+d.orderID+'">');
    f.submit();
  })
}).render('#paypal-btn-container');
<?php endif; ?>
</script>
<?php if($stripe_pk): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
<?php if($paypal_cid): ?><script src="https://www.paypal.com/sdk/js?client-id=<?= h($paypal_cid) ?>&currency=EUR"></script><?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
