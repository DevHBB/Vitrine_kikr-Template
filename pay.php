<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$token=trim($_GET['t']??'');
if(!$token){header('Location:'.BASE_URL.'/');exit;}

$s=db()->prepare('SELECT pl.*,i.client_name,i.client_email,i.number,i.type FROM kk_payment_links pl JOIN kk_invoices i ON pl.invoice_id=i.id WHERE pl.token=?');
$s->execute([$token]);
$pl=$s->fetch();

if(!$pl){$error='Lien invalide ou expiré.';goto render;}
if($pl['expires_at']&&$pl['expires_at']<date('Y-m-d H:i:s')){$error='Ce lien de paiement a expiré.';goto render;}
if($pl['used_at']){$error='Ce paiement a déjà été effectué.';goto render;}

$stripe_pk=get_setting('stripe_public_key','');
$paypal_cid=get_setting('paypal_client_id','');
$bank_iban=get_setting('bank_iban','');
$bank_bic=get_setting('bank_bic','');
$methods=['stripe','paypal','virement','livraison'];
$success=false;

if($_SERVER['REQUEST_METHOD']==='POST'){
    $method=$_POST['payment_method']??'';
    if($method==='virement'||$method==='livraison'){
        db()->prepare("UPDATE kk_payment_links SET used_at=NOW() WHERE token=?")->execute([$token]);
        db()->prepare("UPDATE kk_invoices SET payment_method=?,status='sent',updated_at=NOW() WHERE id=?")->execute([$method,$pl['invoice_id']]);
        $success=true;
        // Email confirmation
        $from="From: ".get_setting('site_email');
        $body="Bonjour {$pl['client_name']},\r\n\r\n".($method==='virement'?"Votre demande de paiement par virement a été enregistrée.\r\nIBAN : ".get_setting('bank_iban')."\r\nBIC : ".get_setting('bank_bic')."\r\nMontant : ".number_format($pl['amount'],2,',',' ')." €\r\nRéférence : ".$pl['number']:"Votre paiement à la livraison a été enregistré. Vous réglerez lors du dépôt de votre moto.")."\r\n\r\nCordialement,\r\n".get_setting('site_name');
        mail($pl['client_email'],"Confirmation paiement — ".$pl['number'],$body,$from);
    }
}

$error='';
render:
$page_title='Paiement sécurisé';
require_once __DIR__ . '/layout/header.php';
?>
<style>
.pay-wrap{max-width:520px;margin:60px auto;padding:0 20px}
.pay-card{background:white;border-radius:20px;padding:36px;box-shadow:0 8px 48px rgba(0,0,0,.1)}
.pay-logo{font-size:22px;font-weight:900;margin-bottom:24px}
.pay-logo span{color:#ed0c0f}
.pay-amount{font-size:42px;font-weight:900;letter-spacing:-2px;color:#111;margin-bottom:4px}
.pay-ref{font-size:13px;color:#888;margin-bottom:28px}
.pay-methods{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
.pay-method{border:2px solid #e8e8e8;border-radius:12px;padding:14px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:600;transition:all .2s;background:white}
.pay-method:hover,.pay-method.sel{border-color:#ed0c0f;background:#fef2f2}
.pay-method input{display:none}
.pay-ico{font-size:20px;width:32px;text-align:center}
.pay-btn{width:100%;background:#ed0c0f;color:white;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer;margin-top:8px;transition:background .2s,transform .2s}
.pay-btn:hover{background:#c00b0d;transform:translateY(-1px)}
.pay-secure{font-size:11px;color:#aaa;text-align:center;margin-top:12px}
.pay-success{text-align:center;padding:20px 0}
.pay-error{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;font-size:13px;color:#dc2626;margin-bottom:20px}
</style>
<div class="pay-wrap">
<div class="pay-card">
  <?php $slogo=get_setting('site_logo'); if($slogo): ?>
  <img src="<?= h($slogo) ?>" alt="" style="height:36px;object-fit:contain;margin-bottom:20px;display:block;">
  <?php else: ?>
  <div class="pay-logo"><?= h(get_setting('site_name',"Kik'r")) ?></div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
  <div class="pay-error">❌ <?= h($error) ?></div>
  <?php elseif($success): ?>
  <div class="pay-success">
    <div style="font-size:56px;margin-bottom:16px;">✅</div>
    <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Paiement enregistré !</h2>
    <p style="color:#555;font-size:14px;">Vous recevrez une confirmation par email.</p>
  </div>
  <?php else: ?>

  <div class="pay-amount"><?= number_format($pl['amount'],2,',',' ') ?> €</div>
  <div class="pay-ref"><?= h(get_setting('site_name')) ?> — <?= ['invoice'=>'Facture','quote'=>'Devis'][$pl['type']]??'Document' ?> <?= h($pl['number']) ?> — <?= h($pl['client_name']) ?></div>

  <form method="POST" id="pay-form">
    <div class="pay-methods">

      <?php if($stripe_pk): ?>
      <label class="pay-method" onclick="selMethod(this,'stripe')">
        <input type="radio" name="payment_method" value="stripe">
        <span class="pay-ico">💳</span>
        <div><div>Carte bancaire</div><div style="font-size:11px;color:#888;">Visa, Mastercard, CB — via Stripe</div></div>
        <span style="margin-left:auto;font-size:10px;color:#aaa;">🔒 Sécurisé</span>
      </label>
      <?php endif; ?>

      <?php if($paypal_cid): ?>
      <label class="pay-method" onclick="selMethod(this,'paypal')">
        <input type="radio" name="payment_method" value="paypal">
        <span class="pay-ico">🅿️</span>
        <div><div>PayPal</div><div style="font-size:11px;color:#888;">Compte PayPal ou carte</div></div>
      </label>
      <?php endif; ?>

      <?php if($bank_iban): ?>
      <label class="pay-method" onclick="selMethod(this,'virement')">
        <input type="radio" name="payment_method" value="virement">
        <span class="pay-ico">🏦</span>
        <div><div>Virement bancaire</div><div style="font-size:11px;color:#888;">IBAN affiché après confirmation</div></div>
      </label>
      <?php endif; ?>

      <label class="pay-method" onclick="selMethod(this,'livraison')">
        <input type="radio" name="payment_method" value="livraison">
        <span class="pay-ico">🤝</span>
        <div><div>Paiement à la livraison</div><div style="font-size:11px;color:#888;">Lors du dépôt ou de la récupération</div></div>
      </label>
    </div>

    <div id="stripe-section" style="display:none;margin-bottom:16px;">
      <div id="stripe-element" style="border:1.5px solid #e8e8e8;border-radius:10px;padding:14px;background:white;"></div>
      <div id="stripe-error" style="color:#dc2626;font-size:12px;margin-top:6px;"></div>
    </div>

    <div id="paypal-section" style="display:none;margin-bottom:16px;">
      <div id="paypal-button-container"></div>
    </div>

    <button type="submit" class="pay-btn" id="pay-btn">Payer <?= number_format($pl['amount'],2,',',' ') ?> €</button>
    <div class="pay-secure">🔒 Paiement sécurisé — Vos données sont protégées</div>
  </form>
  <?php endif; ?>
</div>
</div>

<?php if(!$success && empty($error)): ?>
<script>
var selectedMethod='';
var stripeEl=null, stripeInst=null;

function selMethod(lbl, method){
  document.querySelectorAll('.pay-method').forEach(function(m){m.classList.remove('sel')});
  lbl.classList.add('sel');
  lbl.querySelector('input').checked=true;
  selectedMethod=method;
  document.getElementById('stripe-section').style.display=method==='stripe'?'block':'none';
  document.getElementById('paypal-section').style.display=method==='paypal'?'block':'none';
  var btn=document.getElementById('pay-btn');
  btn.style.display=method==='paypal'?'none':'block';
}

<?php if($stripe_pk): ?>
var stripe=Stripe('<?= h($stripe_pk) ?>');
var elements=stripe.elements();
stripeEl=elements.create('card',{style:{base:{fontSize:'14px',color:'#111'}}});
stripeEl.mount('#stripe-element');
document.getElementById('pay-form').addEventListener('submit',async function(e){
  if(selectedMethod==='stripe'){
    e.preventDefault();
    var r=await stripe.createPaymentMethod({type:'card',card:stripeEl});
    if(r.error){document.getElementById('stripe-error').textContent=r.error.message;}
    else{
      // Soumettre au serveur avec le PM
      var inp=document.createElement('input');inp.type='hidden';inp.name='stripe_pm';inp.value=r.paymentMethod.id;
      document.getElementById('pay-form').appendChild(inp);
      document.getElementById('pay-form').submit();
    }
  }
});
<?php endif; ?>

<?php if($paypal_cid): ?>
paypal.Buttons({
  createOrder:function(data,actions){
    return actions.order.create({purchase_units:[{amount:{value:'<?= number_format($pl['amount'],2,'.','' ) ?>'}}]});
  },
  onApprove:function(data,actions){
    return actions.order.capture().then(function(details){
      var f=document.getElementById('pay-form');
      var i=document.createElement('input');i.type='hidden';i.name='payment_method';i.value='paypal';
      var j=document.createElement('input');j.type='hidden';j.name='paypal_order_id';j.value=data.orderID;
      f.appendChild(i);f.appendChild(j);f.submit();
    });
  }
}).render('#paypal-button-container');
<?php endif; ?>
</script>
<?php if($stripe_pk): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
<?php if($paypal_cid): ?><script src="https://www.paypal.com/sdk/js?client-id=<?= h($paypal_cid) ?>&currency=EUR"></script><?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
