<?php
// ════════════════════════════════════════════════
// MON COMPTE — Authentification OTP sans mot de passe
// ════════════════════════════════════════════════
ob_start();

// Session AVANT tout output
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.gc_maxlifetime',  86400);
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();
module_redirect('client_area');

$page_title = 'Mon espace';
$error      = '';
$success    = '';

// ── Déconnexion ──────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/mon-compte.php');
    exit;
}

// ── ÉTAPE 1 : Envoi OTP ──────────────────────────
// POST → on génère le code, on envoie, on REDIRIGE vers ?step=verify&e=...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
        $step  = 'email';
    } else {
        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Invalider anciens codes, insérer le nouveau
        db()->prepare("UPDATE kk_client_otp SET used=1 WHERE email=?")
           ->execute([$email]);
        db()->prepare("INSERT INTO kk_client_otp(email,code,expires_at) VALUES(?,?,?)")
           ->execute([$email, $code, $expires]);

        // Envoyer email
        $sname = get_setting('site_name', "Kik'r Suspension");
        $from  = "From: $sname <" . get_setting('site_email') . ">";
        mail($email,
            "Code de connexion : $code — $sname",
            "Bonjour,\r\n\r"
            . "Votre code de connexion :\r\n\r\n"
            . "       $code\r\n\r\n"
            . "Valable 15 minutes. Ne le partagez pas.\r\n\r\n"
            . "Cordialement,\r\n$sname",
            $from
        );

        // REDIRECTION PRG — base64url safe (pas de +/=/ qui causent problèmes dans URL)
        $safe = rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
        header('Location: ' . BASE_URL . '/mon-compte.php?step=verify&e=' . $safe . '&sent=1');
        exit;
    }
}

// ── ÉTAPE 2 : Vérification OTP ───────────────────
// L'email vient de l'URL (?e=...) OU du champ hidden du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    // Email : champ hidden en priorité absolue (POST est fiable, session ne l'est pas)
    $email_b64 = trim($_POST['email_b64'] ?? $_GET['e'] ?? '');
    // Décoder base64url safe (inverse de strtr+rtrim de l'envoi)
    $email = strtolower(trim(base64_decode(strtr($email_b64, '-_', '+/') . str_repeat('=', (4 - strlen($email_b64) % 4) % 4)) ?: ''));
    $code      = preg_replace('/\D/', '', trim($_POST['code'] ?? ''));

    if (!$email) {
        $error = 'Session expirée. Recommencez.';
        $step  = 'email';
    } elseif (strlen($code) !== 6) {
        $error = 'Entrez le code à 6 chiffres.';
        $step  = 'verify';
    } else {
        $s = db()->prepare(
            "SELECT * FROM kk_client_otp
             WHERE email=? AND code=? AND used=0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $s->execute([$email, $code]);
        $otp = $s->fetch();

        if (!$otp) {
            // Diagnostic précis
            $s2 = db()->prepare("SELECT expires_at, used FROM kk_client_otp WHERE email=? ORDER BY id DESC LIMIT 1");
            $s2->execute([$email]);
            $last = $s2->fetch();
            if (!$last)            $error = 'Code inconnu. Demandez un nouveau code.';
            elseif ($last['used']) $error = 'Ce code a déjà été utilisé. Demandez un nouveau code.';
            else                   $error = 'Code expiré (15 min). Demandez un nouveau code.';
            $step = 'verify';
        } else {
            // ✅ Code valide
            db()->prepare("UPDATE kk_client_otp SET used=1 WHERE id=?")
               ->execute([$otp['id']]);

            // Créer ou retrouver le client
            $sc = db()->prepare("SELECT id FROM kk_clients WHERE email=? LIMIT 1");
            $sc->execute([$email]);
            $cid = $sc->fetchColumn();
            if (!$cid) {
                db()->prepare("INSERT INTO kk_clients(email,name,type) VALUES(?,?,?)")
                   ->execute([$email, '', 'particulier']);
                $cid = (int)db()->lastInsertId();
            }

            // Stocker en session (simple, pas critique)
            $_SESSION['client_email'] = $email;
            $_SESSION['client_id']    = (int)$cid;

            header('Location: ' . BASE_URL . '/mon-compte.php?welcome=1');
            exit;
        }
    }
}

// ── Mise à jour infos ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $cid = (int)($_SESSION['client_id'] ?? 0);
    if ($cid) {
        db()->prepare("UPDATE kk_clients SET name=?,phone=?,newsletter_opt=?,sms_opt=? WHERE id=?")
           ->execute([
               trim($_POST['name']    ?? ''),
               trim($_POST['phone']   ?? ''),
               (int)($_POST['newsletter_opt'] ?? 0),
               (int)($_POST['sms_opt']        ?? 0),
               $cid,
           ]);
        $success = 'Informations mises à jour.';
    }
}

// ── Déterminer l'étape ────────────────────────────
if (!empty($_SESSION['client_email'])) {
    $step = 'account';
} elseif (isset($_GET['step']) && $_GET['step'] === 'verify') {
    $step      = 'verify';
    $email_b64  = $_GET['e'] ?? '';
    $_b64clean  = strtr($email_b64, '-_', '+/') . str_repeat('=', (4 - strlen($email_b64) % 4) % 4);
    $email_disp = base64_decode($_b64clean) ?: '?';
} else {
    $step = 'email';
}

if (isset($_GET['welcome'])) $success = '';

// ── Charger données compte ────────────────────────
$client = null; $rdvs = []; $invs = [];
if ($step === 'account') {
    $cid    = (int)($_SESSION['client_id'] ?? 0);
    $client = get_client($cid);
    $sr     = db()->prepare('SELECT * FROM kk_appointments WHERE client_id=? ORDER BY created_at DESC');
    $sr->execute([$cid]); $rdvs = $sr->fetchAll();
    $invs   = get_invoices('', $cid);
}

require_once __DIR__ . '/layout/header.php';
$svc_list = get_services();
$statuses = [
    'pending'         => ['🟡','En attente de confirmation','#fef9c3','#854d0e'],
    'pending_payment' => ['💳','En attente de paiement',   '#dbeafe','#1d4ed8'],
    'confirmed'       => ['🔵','Rendez-vous confirmé',     '#dbeafe','#1d4ed8'],
    'in_progress'     => ['🟠','En cours de traitement',   '#ffedd5','#c2410c'],
    'ready'           => ['🟢','Votre moto est prête !',   '#dcfce7','#15803d'],
    'collected'       => ['✅','Récupérée',                '#f0fdf4','#aaa'],
    'cancelled'       => ['❌','Annulé',                   '#fef2f2','#dc2626'],
];
?>

<style>
.mc-hero{background:#111;color:white;padding:48px 0 40px;text-align:center}
.mc-hero h1{font-size:clamp(26px,3vw,40px);font-weight:900;letter-spacing:-1.5px;margin-bottom:6px}
.mc-hero p{color:#888;font-size:14px}
.mc-login{max-width:420px;margin:48px auto;padding:0 20px}
.mc-card{background:white;border-radius:20px;padding:32px;box-shadow:0 4px 32px rgba(0,0,0,.08)}
.mc-card h2{font-size:18px;font-weight:800;margin-bottom:6px}
.mc-card p{font-size:13px;color:#888;margin-bottom:24px;line-height:1.6}
.mc-field{margin-bottom:14px}
.mc-field label{display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.mc-field input{width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:12px 14px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.mc-field input:focus{border-color:#ed0c0f;box-shadow:0 0 0 3px rgba(237,12,15,.08)}
.mc-field.otp-field input{font-size:32px;font-weight:900;letter-spacing:10px;text-align:center;padding:16px}
.mc-btn{width:100%;background:#111;color:white;border:none;border-radius:12px;padding:13px;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit}
.mc-btn:hover{background:#333;transform:translateY(-1px)}
.mc-btn.red{background:#ed0c0f}
.mc-btn.red:hover{background:#c00b0d}
.mc-success{background:#f0fdf4;border-radius:10px;padding:12px 16px;font-size:13px;color:#15803d;margin-bottom:16px}
.mc-error{background:#fef2f2;border-radius:10px;padding:12px 16px;font-size:13px;color:#dc2626;margin-bottom:16px}
.mc-back{font-size:12px;color:#aaa;text-align:center;margin-top:12px;cursor:pointer;text-decoration:underline;background:none;border:none;width:100%;font-family:inherit;display:block}
/* Compte */
.mc-wrap{max-width:900px;margin:0 auto;padding:32px 20px}
.mc-tabs{display:flex;gap:0;border-bottom:2px solid #f0f0f0;margin-bottom:24px;overflow-x:auto}
.mc-tab{padding:10px 20px;font-size:13px;font-weight:700;color:#aaa;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;white-space:nowrap;font-family:inherit}
.mc-tab.active{color:#ed0c0f;border-bottom-color:#ed0c0f}
.mc-section{display:none}.mc-section.active{display:block}
.mc-rdv-card{border:1.5px solid #f0f0f0;border-radius:14px;padding:16px 18px;margin-bottom:10px;display:flex;align-items:center;gap:14px;transition:box-shadow .2s}
.mc-rdv-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.mc-rdv-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.mc-empty{text-align:center;padding:40px 0;color:#aaa;font-size:13px}
.mc-new-rdv{display:inline-flex;align-items:center;gap:8px;background:#ed0c0f;color:white;border-radius:10px;padding:10px 18px;font-size:13px;font-weight:700;text-decoration:none;margin-top:14px}
.mc-info-form{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mc-toggle-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid #f0f0f0}
.tgl{width:40px;height:22px;background:#ddd;border-radius:11px;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0}
.tgl::after{content:'';position:absolute;width:16px;height:16px;background:white;border-radius:50%;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.tgl.on{background:#ed0c0f}
.tgl.on::after{left:21px}
@media(max-width:600px){.mc-info-form{grid-template-columns:1fr}.mc-rdv-card{flex-direction:column;align-items:flex-start}}
</style>

<div class="mc-hero">
  <?php if($step==='account' && $client): ?>
  <h1>Bonjour <?= h($client['name'] ? explode(' ',$client['name'])[0] : 'vous') ?> 👋</h1>
  <p><?= h($_SESSION['client_email']) ?> · <a href="?logout=1" style="color:#555;font-size:12px;">Se déconnecter</a></p>
  <?php else: ?>
  <h1>Mon espace</h1>
  <p>Suivez vos RDV et factures sans mot de passe</p>
  <?php endif; ?>
</div>

<?php if($step==='email'): ?>
<!-- ══ ÉTAPE 1 : Email ══ -->
<div class="mc-login">
  <div class="mc-card">
    <span style="font-size:40px;display:block;margin-bottom:12px;">✉️</span>
    <h2>Accéder à mon espace</h2>
    <p>Entrez votre email — nous vous envoyons un code à 6 chiffres valable 15 minutes. Aucun mot de passe.</p>
    <?php if($error): ?><div class="mc-error"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mc-field">
        <label>Adresse email</label>
        <input type="email" name="email" required autofocus placeholder="jean@mail.fr">
      </div>
      <button type="submit" name="send_otp" value="1" class="mc-btn red">Recevoir mon code →</button>
    </form>
  </div>
</div>

<?php elseif($step==='verify'): ?>
<!-- ══ ÉTAPE 2 : Code OTP ══ -->
<div class="mc-login">
  <div class="mc-card">
    <span style="font-size:40px;display:block;margin-bottom:12px;">🔐</span>
    <h2>Code de connexion</h2>
    <p>Un code à 6 chiffres a été envoyé à<br><strong><?= h($email_disp ?? '?') ?></strong>.<br>Vérifiez vos spams si besoin.</p>
    <?php if(isset($_GET['sent'])): ?><div class="mc-success">✅ Code envoyé !</div><?php endif; ?>
    <?php if($error): ?><div class="mc-error"><?= h($error) ?></div><?php endif; ?>
    <form method="POST" action="<?= BASE_URL ?>/mon-compte.php?step=verify&e=<?= h($email_b64 ?? '') ?>&sent=<?= isset($_GET['sent'])?'1':'' ?>">
      <!-- L'email est dans l'URL ET dans un champ hidden — double sécurité -->
      <input type="hidden" name="email_b64" value="<?= h($email_b64 ?? '') ?>">
      <div class="mc-field otp-field">
        <label>Code reçu par email</label>
        <input type="text" name="code" id="otp-code"
               inputmode="numeric" maxlength="6" pattern="\d{6}"
               autocomplete="one-time-code" autofocus
               placeholder="000000">
      </div>
      <button type="submit" name="verify_otp" value="1" class="mc-btn red" id="otp-btn">Connexion →</button>
    </form>

    <!-- Renvoyer le code -->
    <form method="POST" action="<?= BASE_URL ?>/mon-compte.php" style="margin-top:10px;">
      <input type="hidden" name="email" value="<?= h(base64_decode(strtr($email_b64 ?? '', '-_', '+/') . str_repeat('=', (4 - strlen($email_b64 ?? '') % 4) % 4)) ?: '') ?>">
      <button type="submit" name="send_otp" value="1" class="mc-back">↻ Renvoyer le code</button>
    </form>
    <a href="<?= BASE_URL ?>/mon-compte.php" class="mc-back" style="display:block;margin-top:4px;">← Changer d'email</a>
  </div>
</div>

<?php else: ?>
<!-- ══ ESPACE CLIENT ══ -->
<div class="mc-wrap">
  <?php if($success): ?><div class="mc-success" style="margin-bottom:16px;"><?= h($success) ?></div><?php endif; ?>
  <div class="mc-tabs">
    <button class="mc-tab active" onclick="showTab('rdvs',this)">📅 Mes RDV (<?= count($rdvs) ?>)</button>
    <button class="mc-tab" onclick="showTab('factures',this)">🧾 Factures (<?= count($invs) ?>)</button>
    <button class="mc-tab" onclick="showTab('infos',this)">👤 Mes infos</button>
    <button class="mc-tab" onclick="showTab('nouveau_rdv',this)" style="color:#ed0c0f;">+ Nouveau RDV</button>
  </div>

  <div class="mc-section active" id="tab-rdvs">
    <?php if(empty($rdvs)): ?>
    <div class="mc-empty"><div style="font-size:40px;margin-bottom:8px;">📅</div>Aucun RDV.<br><a href="<?= BASE_URL ?>/planning.php" class="mc-new-rdv">Prendre rendez-vous →</a></div>
    <?php else: ?>
    <?php foreach($rdvs as $rdv): [$ico,$lbl,$bg,$fg]=$statuses[$rdv['status']]??['?','','#f5f5f3','#555']; ?>
    <div class="mc-rdv-card">
      <div style="flex:1">
        <div style="font-size:14px;font-weight:700;"><?= h($rdv['service_label']?:'—') ?></div>
        <div style="font-size:12px;color:#888;">🏍️ <?= h(trim(($rdv['moto_marque']??'').' '.($rdv['moto_modele']??'').' '.($rdv['moto_annee']??''))) ?></div>
        <?php if($rdv['slot_date']): ?><div style="font-size:11px;color:#aaa;">📅 <?= date('d/m/Y',strtotime($rdv['slot_date'])) ?><?= $rdv['slot_time']?' à '.substr($rdv['slot_time'],0,5):'' ?></div><?php endif; ?>
        <?php if($rdv['payment_status']==='pending_payment' && $rdv['payment_link_token']): ?>
        <a href="<?= site_url('/payer.php?t='.$rdv['payment_link_token']) ?>" style="display:inline-block;margin-top:8px;background:#ed0c0f;color:white;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none;">💳 Payer maintenant →</a>
        <?php endif; ?>
      </div>
      <span class="mc-rdv-badge" style="background:<?= $bg ?>;color:<?= $fg ?>"><?= $ico.' '.$lbl ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:16px;"><a href="<?= BASE_URL ?>/planning.php" class="mc-new-rdv">+ Nouveau rendez-vous</a></div>
    <?php endif; ?>
  </div>

  <div class="mc-section" id="tab-factures">
    <?php if(empty($invs)): ?>
    <div class="mc-empty"><div style="font-size:40px;margin-bottom:8px;">🧾</div>Aucune facture.</div>
    <?php else: ?>
    <?php foreach($invs as $inv): $tot=invoice_totals(jd($inv['invoice_lines']??'[]',[]),(float)$inv['tva_rate'],(float)($inv['discount']??0),$inv['discount_type']??'amount'); ?>
    <div class="mc-rdv-card">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700;"><?= ['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'][$inv['type']]??'' ?> <?= h($inv['number']) ?></div>
        <div style="font-size:11px;color:#aaa;"><?= date('d/m/Y',strtotime($inv['created_at'])) ?></div>
      </div>
      <div style="font-size:15px;font-weight:800;"><?= number_format($tot['ttc'],2,',',' ') ?> €</div>
      <a href="<?= facture_url($inv['id']) ?>" target="_blank" style="font-size:12px;color:#3b82f6;text-decoration:none;">📄 Voir</a>
      <span class="mc-rdv-badge" style="background:<?= $inv['status']==='paid'?'#dcfce7':'#fef9c3' ?>;color:<?= $inv['status']==='paid'?'#15803d':'#854d0e' ?>;"><?= $inv['status']==='paid'?'✅ Payé':'⏳ En attente' ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="mc-section" id="tab-infos">
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:18px;">Mes informations</h3>
      <form method="POST">
        <input type="hidden" name="update_info" value="1">
        <div class="mc-info-form">
          <div class="mc-field"><label>Nom complet</label><input type="text" name="name" value="<?= h($client['name']??'') ?>" placeholder="Jean Dupont"></div>
          <div class="mc-field"><label>Email</label><input type="email" value="<?= h($_SESSION['client_email']) ?>" disabled style="background:#fafafa;color:#aaa;"></div>
          <div class="mc-field"><label>Téléphone</label><input type="tel" name="phone" value="<?= h($client['phone']??'') ?>" placeholder="+33 6 …"></div>
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0;">
          <div class="mc-toggle-row">
            <div class="tgl <?= ($client['newsletter_opt']??1)?'on':'' ?>" onclick="tgl(this,'newsletter_opt')"></div>
            <input type="hidden" name="newsletter_opt" id="inp-nl" value="<?= ($client['newsletter_opt']??1)?1:0 ?>">
            <div><div style="font-size:13px;font-weight:600;">Newsletter</div><div style="font-size:11px;color:#aaa;">Recevoir les actualités par email</div></div>
          </div>
          <div class="mc-toggle-row">
            <div class="tgl <?= ($client['sms_opt']??1)?'on':'' ?>" onclick="tgl(this,'sms_opt')"></div>
            <input type="hidden" name="sms_opt" id="inp-sms" value="<?= ($client['sms_opt']??1)?1:0 ?>">
            <div><div style="font-size:13px;font-weight:600;">SMS</div><div style="font-size:11px;color:#aaa;">Recevoir les notifications par SMS</div></div>
          </div>
        </div>
        <button type="submit" class="mc-btn" style="width:auto;padding:10px 24px;margin-top:16px;">💾 Enregistrer</button>
      </form>
    </div>
  </div>

  <div class="mc-section" id="tab-nouveau_rdv">
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:6px;">Nouvelle demande de RDV</h3>
      <p style="font-size:13px;color:#888;margin-bottom:20px;">Vos informations sont pré-remplies.</p>
      <form method="POST" action="<?= BASE_URL ?>/planning.php" enctype="multipart/form-data">
        <input type="hidden" name="rdv_submit" value="1">
        <input type="hidden" name="accept_cgv" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
          <?php foreach([['name','Nom','text',$client['name']??''],['email','Email','email',$_SESSION['client_email']],['phone','Téléphone','tel',$client['phone']??'']] as [$n,$l,$t,$v]): ?>
          <div class="mc-field"><label><?= $l ?></label><input type="<?= $t ?>" name="<?= $n ?>" value="<?= h($v) ?>" <?= $n==='email'?'readonly style="background:#fafafa;"':'' ?>></div>
          <?php endforeach; ?>
          <div class="mc-field"><label>Prestation *</label><select name="service_label" required><option value="">— Choisir —</option><?php foreach($svc_list as $s): ?><option value="<?= h($s['label']) ?>"><?= h($s['title']) ?></option><?php endforeach; ?></select></div>
          <div class="mc-field"><label>Marque moto *</label><input type="text" name="moto_marque" required placeholder="Yamaha, KTM…"></div>
          <div class="mc-field"><label>Modèle</label><input type="text" name="moto_modele" placeholder="YZ450F"></div>
        </div>
        <div class="mc-field" style="margin-bottom:16px;"><label>Message</label><textarea name="notes_client" style="width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:10px;font-size:13px;font-family:inherit;min-height:70px;" placeholder="Poids, niveau, problème…"></textarea></div>
        <button type="submit" class="mc-btn red" style="width:auto;padding:12px 24px;">Envoyer →</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function showTab(name, btn) {
  document.querySelectorAll('.mc-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.mc-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}
function tgl(el, field) {
  var on = el.classList.toggle('on');
  document.getElementById('inp-' + (field === 'newsletter_opt' ? 'nl' : 'sms')).value = on ? 1 : 0;
}
// Auto-submit quand 6 chiffres tapés
var oi = document.getElementById('otp-code');
if (oi) {
  oi.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'').substring(0,6);
    if (this.value.length === 6) {
      var btn = document.getElementById('otp-btn');
      if (btn) { btn.textContent = 'Connexion…'; btn.disabled = true; }
      this.closest('form').submit();
    }
  });
}
</script>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
