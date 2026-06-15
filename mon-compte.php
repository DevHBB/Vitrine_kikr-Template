<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();
module_redirect('client_area');

$page_title = 'Mon espace';
$error = $success = '';

// ── Déconnexion
if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: ' . BASE_URL . '/mon-compte.php'); exit;
}

// ── Mise à jour infos client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $cid = (int)($_SESSION['client_id'] ?? 0);
    if ($cid) {
        db()->prepare("UPDATE kk_clients SET name=?,phone=?,newsletter_opt=?,sms_opt=? WHERE id=?")
           ->execute([trim($_POST['name']??''), trim($_POST['phone']??''),
                      (int)($_POST['newsletter_opt']??0), (int)($_POST['sms_opt']??0), $cid]);
        $success = 'Informations mises à jour.';
    }
}

// ── ÉTAPE 1 : Envoi du code OTP
// L'email voyage UNIQUEMENT dans les champs POST, jamais dans l'URL ni la session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
        $step  = 'email';
    } else {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        db()->prepare("UPDATE kk_client_otp SET used=1 WHERE email=?")->execute([$email]);
        db()->prepare("INSERT INTO kk_client_otp(email,code,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
           ->execute([$email, $code]);
        $sname = get_setting('site_name');
        mail($email, "Code : $code — $sname",
            "Bonjour,\r\n\r\nVotre code de connexion :\r\n\r\n    $code\r\n\r\nValable 15 minutes.\r\n\r\nCordialement,\r\n$sname",
            "From: $sname <" . get_setting('site_email') . ">"
        );
        // On reste sur la page verify — l'email est dans un champ POST caché
        $step        = 'verify';
        $otp_email   = $email; // sera affiché et mis dans le hidden
        $success     = "Code envoyé à $email";
    }
}

// ── ÉTAPE 2 : Vérification du code
// L'email vient du champ hidden <input name="otp_email"> dans le formulaire verify
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $email = strtolower(trim($_POST['otp_email'] ?? ''));
    $code  = preg_replace('/\D/', '', trim($_POST['code'] ?? ''));
    if (!$email) {
        $error = 'Email manquant, recommencez.'; $step = 'email';
    } elseif (strlen($code) !== 6) {
        $error = 'Entrez les 6 chiffres du code.'; $step = 'verify'; $otp_email = $email;
    } else {
        $s = db()->prepare(
            "SELECT * FROM kk_client_otp WHERE email=? AND code=? AND used=0
             AND expires_at > NOW() ORDER BY id DESC LIMIT 1"
        );
        $s->execute([$email, $code]);
        $otp = $s->fetch();
        if (!$otp) {
            // Diagnostic
            $d = db()->prepare("SELECT expires_at, used FROM kk_client_otp WHERE email=? ORDER BY id DESC LIMIT 1");
            $d->execute([$email]);
            $last = $d->fetch();
            if (!$last)            $error = 'Code inconnu. Demandez un nouveau code.';
            elseif ($last['used']) $error = 'Code déjà utilisé. Demandez un nouveau code.';
            else                   $error = 'Code expiré. Demandez un nouveau code.';
            $step = 'verify'; $otp_email = $email;
        } else {
            db()->prepare("UPDATE kk_client_otp SET used=1 WHERE id=?")->execute([$otp['id']]);
            $sc = db()->prepare("SELECT id FROM kk_clients WHERE email=? LIMIT 1");
            $sc->execute([$email]);
            $cid = $sc->fetchColumn();
            if (!$cid) {
                db()->prepare("INSERT INTO kk_clients(email,name,type) VALUES(?,?,?)")
                   ->execute([$email, '', 'particulier']);
                $cid = (int)db()->lastInsertId();
            }
            $_SESSION['client_email'] = $email;
            $_SESSION['client_id']    = (int)$cid;
            $step = 'account';
        }
    }
}

// ── Déterminer l'étape si pas encore définie par un POST
if (!isset($step)) {
    if (!empty($_SESSION['client_email'])) $step = 'account';
    else                                    $step = 'email';
}

// ── Données compte
$client = null; $rdvs = []; $invs = [];
if ($step === 'account') {
    $cid    = (int)($_SESSION['client_id'] ?? 0);
    $client = get_client($cid);
    $sr = db()->prepare('SELECT * FROM kk_appointments WHERE client_id=? ORDER BY created_at DESC');
    $sr->execute([$cid]); $rdvs = $sr->fetchAll();
    $invs = get_invoices('', $cid);
}

require_once __DIR__ . '/layout/header.php';
$statuses = [
    'pending'         => ['🟡', 'En attente de confirmation', '#fef9c3', '#854d0e'],
    'pending_payment' => ['💳', 'En attente de paiement',    '#dbeafe', '#1d4ed8'],
    'confirmed'       => ['🔵', 'Rendez-vous confirmé',      '#dbeafe', '#1d4ed8'],
    'in_progress'     => ['🟠', 'En cours de traitement',    '#ffedd5', '#c2410c'],
    'ready'           => ['🟢', 'Votre moto est prête !',    '#dcfce7', '#15803d'],
    'collected'       => ['✅', 'Récupérée',                 '#f0fdf4', '#aaa'],
    'cancelled'       => ['❌', 'Annulé',                    '#fef2f2', '#dc2626'],
];
?>
<style>
.mc-hero{background:#111;color:white;padding:48px 0 40px;text-align:center}
.mc-hero h1{font-size:clamp(26px,3vw,40px);font-weight:900;letter-spacing:-1.5px;margin-bottom:6px}
.mc-hero p{color:#888;font-size:14px}
.mc-login{max-width:400px;margin:48px auto;padding:0 20px}
.mc-card{background:white;border-radius:20px;padding:32px;box-shadow:0 4px 32px rgba(0,0,0,.1)}
.mc-card h2{font-size:18px;font-weight:800;margin-bottom:6px}
.mc-card p{font-size:13px;color:#888;margin-bottom:22px;line-height:1.6}
.mc-field{margin-bottom:14px}
.mc-field label{display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.mc-field input{width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:12px 14px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.mc-field input:focus{border-color:#ed0c0f}
.mc-otp input{font-size:32px;font-weight:900;letter-spacing:10px;text-align:center;padding:16px}
.mc-btn{width:100%;border:none;border-radius:12px;padding:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s}
.mc-btn-red{background:#ed0c0f;color:white}.mc-btn-red:hover{background:#c00b0d}
.mc-btn-dark{background:#111;color:white}.mc-btn-dark:hover{background:#333}
.mc-ok{background:#f0fdf4;border-radius:10px;padding:12px;font-size:13px;color:#15803d;margin-bottom:14px}
.mc-err{background:#fef2f2;border-radius:10px;padding:12px;font-size:13px;color:#dc2626;margin-bottom:14px}
.mc-link{display:block;text-align:center;font-size:12px;color:#aaa;margin-top:10px;cursor:pointer;text-decoration:underline;background:none;border:none;width:100%;font-family:inherit}
.mc-wrap{max-width:900px;margin:0 auto;padding:32px 20px}
.mc-tabs{display:flex;border-bottom:2px solid #f0f0f0;margin-bottom:24px;overflow-x:auto}
.mc-tab{padding:10px 18px;font-size:13px;font-weight:700;color:#aaa;cursor:pointer;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;background:none;font-family:inherit;white-space:nowrap;transition:all .2s}
.mc-tab.on{color:#ed0c0f;border-bottom-color:#ed0c0f}
.mc-sec{display:none}.mc-sec.on{display:block}
.mc-rdv{border:1.5px solid #f0f0f0;border-radius:14px;padding:16px;margin-bottom:10px;display:flex;align-items:center;gap:14px;transition:box-shadow .2s}
.mc-rdv:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.mc-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.mc-empty{text-align:center;padding:40px 0;color:#aaa}
.tgl{width:40px;height:22px;background:#ddd;border-radius:11px;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0;display:inline-block}
.tgl:after{content:'';position:absolute;width:16px;height:16px;background:white;border-radius:50%;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.tgl.on{background:#ed0c0f}.tgl.on:after{left:21px}
@media(max-width:600px){.mc-rdv{flex-direction:column;align-items:flex-start}}
</style>

<div class="mc-hero">
  <?php if($step==='account' && $client): ?>
  <h1>Bonjour <?= h($client['name'] ? explode(' ',$client['name'])[0] : 'vous') ?> 👋</h1>
  <p><?= h($_SESSION['client_email']) ?> · <a href="?logout=1" style="color:#555;font-size:12px;">Déconnexion</a></p>
  <?php else: ?>
  <h1>Mon espace</h1>
  <p>Accédez à vos RDV et factures</p>
  <?php endif; ?>
</div>

<?php if($step === 'email'): ?>
<div class="mc-login">
  <div class="mc-card">
    <span style="font-size:40px;display:block;margin-bottom:12px;">✉️</span>
    <h2>Connexion</h2>
    <p>Entrez votre email, vous recevrez un code à 6 chiffres. Aucun mot de passe.</p>
    <?php if($error): ?><div class="mc-err"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mc-field">
        <label>Email</label>
        <input type="email" name="email" required autofocus placeholder="jean@mail.fr">
      </div>
      <button type="submit" name="send_otp" value="1" class="mc-btn mc-btn-red">Recevoir mon code →</button>
    </form>
  </div>
</div>

<?php elseif($step === 'verify'): ?>
<div class="mc-login">
  <div class="mc-card">
    <span style="font-size:40px;display:block;margin-bottom:12px;">🔐</span>
    <h2>Code de connexion</h2>
    <p>Code envoyé à <strong><?= h($otp_email ?? '') ?></strong>.<br>Vérifiez vos spams.</p>
    <?php if($success): ?><div class="mc-ok">✅ <?= h($success) ?></div><?php endif; ?>
    <?php if($error):   ?><div class="mc-err"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <!-- Email dans un champ hidden POST - pas dans l'URL, pas dans la session -->
      <input type="hidden" name="otp_email" value="<?= h($otp_email ?? '') ?>">
      <div class="mc-field mc-otp">
        <label>Code reçu</label>
        <input type="text" name="code" id="otp-inp" inputmode="numeric"
               maxlength="6" autocomplete="one-time-code" autofocus placeholder="000000">
      </div>
      <button type="submit" name="verify_otp" value="1" class="mc-btn mc-btn-red" id="otp-btn">
        Se connecter →
      </button>
    </form>
    <form method="POST" style="margin-top:10px;">
      <input type="hidden" name="email" value="<?= h($otp_email ?? '') ?>">
      <button type="submit" name="send_otp" value="1" class="mc-link">↻ Renvoyer le code</button>
    </form>
    <a href="<?= BASE_URL ?>/mon-compte.php" class="mc-link" style="display:block;margin-top:4px;">← Changer d'email</a>
  </div>
</div>

<?php else: // account ?>
<div class="mc-wrap">
  <?php if($success): ?><div class="mc-ok" style="margin-bottom:16px;"><?= h($success) ?></div><?php endif; ?>
  <div class="mc-tabs">
    <button class="mc-tab on" onclick="tab('rdvs',this)">📅 RDV (<?= count($rdvs) ?>)</button>
    <button class="mc-tab"    onclick="tab('invs',this)">🧾 Factures (<?= count($invs) ?>)</button>
    <button class="mc-tab"    onclick="tab('info',this)">👤 Mes infos</button>
    <button class="mc-tab" onclick="tab('newrdv',this)" style="color:#ed0c0f;">+ Nouveau RDV</button>
  </div>

  <div class="mc-sec on" id="t-rdvs">
    <?php if(empty($rdvs)): ?><div class="mc-empty">Aucun RDV.<br><a href="<?= BASE_URL ?>/planning.php" style="color:#ed0c0f;font-weight:700;">Prendre RDV →</a></div>
    <?php else: foreach($rdvs as $r): [$ico,$lbl,$bg,$fg]=$statuses[$r['status']]??['?','','#f5f5f3','#555']; ?>
    <div class="mc-rdv">
      <div style="flex:1">
        <div style="font-size:14px;font-weight:700;"><?= h($r['service_label']?:'—') ?></div>
        <div style="font-size:12px;color:#888;">🏍️ <?= h(trim(($r['moto_marque']??'').' '.($r['moto_modele']??''))) ?></div>
        <?php if($r['slot_date']): ?><div style="font-size:11px;color:#aaa;">📅 <?= date('d/m/Y',strtotime($r['slot_date'])) ?></div><?php endif; ?>
        <?php if(($r['payment_status']??'') === 'pending_payment' && !empty($r['payment_link_token'])): ?>
        <a href="<?= site_url('/payer.php?t='.$r['payment_link_token']) ?>" style="display:inline-block;margin-top:8px;background:#ed0c0f;color:white;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none;">💳 Payer →</a>
        <?php endif; ?>
      </div>
      <span class="mc-badge" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= $ico.' '.$lbl ?></span>
    </div>
    <?php endforeach; endif; ?>
    <div style="margin-top:12px;"><a href="<?= BASE_URL ?>/planning.php" style="display:inline-flex;align-items:center;gap:8px;background:#ed0c0f;color:white;border-radius:10px;padding:10px 18px;font-size:13px;font-weight:700;text-decoration:none;">+ Nouveau RDV</a></div>
  </div>

  <div class="mc-sec" id="t-invs">
    <?php if(empty($invs)): ?><div class="mc-empty">Aucune facture.</div>
    <?php else: foreach($invs as $inv): $tot=invoice_totals(jd($inv['invoice_lines']??'[]',[]),(float)$inv['tva_rate']); ?>
    <div class="mc-rdv">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700;"><?= ['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'][$inv['type']]??'' ?> <?= h($inv['number']) ?></div>
        <div style="font-size:11px;color:#aaa;"><?= date('d/m/Y',strtotime($inv['created_at'])) ?></div>
      </div>
      <div style="font-size:15px;font-weight:800;"><?= number_format($tot['ttc'],2,',',' ') ?> €</div>
      <a href="<?= facture_url($inv['id']) ?>" target="_blank" style="font-size:12px;color:#3b82f6;">📄 Voir</a>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="mc-sec" id="t-info">
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:18px;">Mes informations</h3>
      <form method="POST">
        <input type="hidden" name="update_info" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
          <div class="mc-field"><label>Nom</label><input type="text" name="name" value="<?= h($client['name']??'') ?>"></div>
          <div class="mc-field"><label>Email</label><input type="email" value="<?= h($_SESSION['client_email']) ?>" disabled style="background:#fafafa;color:#aaa;"></div>
          <div class="mc-field"><label>Téléphone</label><input type="tel" name="phone" value="<?= h($client['phone']??'') ?>"></div>
        </div>
        <div style="border-top:1px solid #f0f0f0;padding-top:14px;">
          <label style="display:flex;align-items:center;gap:12px;margin-bottom:12px;cursor:pointer;">
            <span class="tgl <?= ($client['newsletter_opt']??1)?'on':'' ?>" id="tgl-nl" onclick="togInp(this,'inp-nl')"></span>
            <input type="hidden" name="newsletter_opt" id="inp-nl" value="<?= ($client['newsletter_opt']??1)?1:0 ?>">
            <span style="font-size:13px;font-weight:600;">Newsletter</span>
          </label>
          <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
            <span class="tgl <?= ($client['sms_opt']??1)?'on':'' ?>" id="tgl-sms" onclick="togInp(this,'inp-sms')"></span>
            <input type="hidden" name="sms_opt" id="inp-sms" value="<?= ($client['sms_opt']??1)?1:0 ?>">
            <span style="font-size:13px;font-weight:600;">SMS</span>
          </label>
        </div>
        <button type="submit" class="mc-btn mc-btn-dark" style="width:auto;padding:10px 24px;margin-top:16px;">💾 Enregistrer</button>
      </form>
    </div>
  </div>

  <div class="mc-sec" id="t-newrdv">
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;">Nouvelle demande de RDV</h3>
      <form method="POST" action="<?= BASE_URL ?>/planning.php" enctype="multipart/form-data">
        <input type="hidden" name="rdv_submit" value="1">
        <input type="hidden" name="accept_cgv" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
          <div class="mc-field"><label>Nom</label><input type="text" name="name" value="<?= h($client['name']??'') ?>"></div>
          <div class="mc-field"><label>Email</label><input type="email" name="email" value="<?= h($_SESSION['client_email']) ?>" readonly style="background:#fafafa;"></div>
          <div class="mc-field"><label>Téléphone</label><input type="tel" name="phone" value="<?= h($client['phone']??'') ?>"></div>
          <div class="mc-field"><label>Prestation *</label>
            <select name="service_label" required style="width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:9px 12px;font-size:13px;">
              <option value="">— Choisir —</option>
              <?php foreach(get_services() as $s): ?><option value="<?= h($s['label']) ?>"><?= h($s['title']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mc-field"><label>Marque *</label><input type="text" name="moto_marque" required placeholder="Yamaha, KTM…"></div>
          <div class="mc-field"><label>Modèle</label><input type="text" name="moto_modele" placeholder="YZ450F"></div>
        </div>
        <div class="mc-field" style="margin-bottom:16px;"><label>Message</label><textarea name="notes_client" style="width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:10px;font-size:13px;font-family:inherit;min-height:70px;"></textarea></div>
        <button type="submit" class="mc-btn mc-btn-red" style="width:auto;padding:12px 24px;">Envoyer →</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function tab(id, btn) {
  document.querySelectorAll('.mc-sec').forEach(function(s){s.classList.remove('on');});
  document.querySelectorAll('.mc-tab').forEach(function(b){b.classList.remove('on');});
  document.getElementById('t-'+id).classList.add('on');
  btn.classList.add('on');
}
function togInp(el, id) {
  var on = el.classList.toggle('on');
  document.getElementById(id).value = on ? 1 : 0;
}
// Auto-submit OTP quand 6 chiffres tapés
var oi = document.getElementById('otp-inp');
if (oi) {
  oi.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'').substring(0,6);
    if (this.value.length === 6) {
      var btn = document.getElementById('otp-btn');
      if (btn) { btn.textContent = '…'; btn.disabled = true; }
      this.closest('form').submit();
    }
  });
}
</script>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
