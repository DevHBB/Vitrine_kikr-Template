<?php
ob_start();
// Config session robuste pour XAMPP/shared hosting
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();
module_redirect('client_area');

// Auto-migrate OTP table
try {
    if (!db()->query("SHOW TABLES LIKE 'kk_client_otp'")->fetchColumn()) {
        db()->exec("CREATE TABLE `kk_client_otp` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `email` VARCHAR(200) NOT NULL,
          `code` VARCHAR(6) NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `used` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch(Exception $e) {}

$page_title = 'Mon espace';
$error      = '';
$success    = '';

// Déterminer l'étape de façon non ambiguë
if (!empty($_SESSION['client_email'])) {
    $step = 'account';
} elseif (!empty($_SESSION['otp_email'])) {
    $step = 'verify';
} else {
    $step = 'email';
}

// ---- ÉTAPE 1 : Saisie email → envoi OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Générer code 6 chiffres
        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Invalider anciens codes
        db()->prepare("UPDATE kk_client_otp SET used=1 WHERE email=?")->execute([$email]);
        db()->prepare("INSERT INTO kk_client_otp(email,code,expires_at) VALUES(?,?,?)")
           ->execute([$email, $code, $expires]);

        // Envoyer email
        $sname = get_setting('site_name', "Kik'r Suspension");
        $from  = "From: $sname <".get_setting('site_email').">";
        $subj  = "[$sname] Votre code de connexion : $code";
        $body  = "Bonjour,\r\n\r\n"
               . "Votre code de connexion à votre espace client est :\r\n\r\n"
               . "    $code\r\n\r\n"
               . "Ce code est valable 10 minutes.\r\n\r\n"
               . "Si vous n'avez pas demandé ce code, ignorez cet email.\r\n\r\n"
               . "Cordialement,\r\n$sname";
        mail($email, $subj, $body, $from);

        $_SESSION['otp_email'] = $email;
        session_write_close(); // Forcer l'écriture de la session sur disque
        $step    = 'verify';
        $success = "Code envoyé à $email — vérifiez vos spams si besoin.";
    }
}

// ---- ÉTAPE 2 : Vérification code OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    // Fallback : si session perdue, récupérer l'email depuis le champ hidden
    $email = $_SESSION['otp_email'] ?? trim($_POST['otp_email_fallback'] ?? '');
    if ($email) $_SESSION['otp_email'] = $email; // Remettre en session si absent
    $code  = trim(str_replace([' ','-'], '', $_POST['code'] ?? ''));

    $s = db()->prepare("SELECT * FROM kk_client_otp WHERE email=? AND code=? AND used=0 AND expires_at > NOW()");
    $s->execute([$email, $code]);
    $otp = $s->fetch();

    if (!$otp) {
        $error = 'Code incorrect ou expiré. Recommencez.';
        $step  = 'verify';
    } else {
        // Invalider le code
        db()->prepare("UPDATE kk_client_otp SET used=1 WHERE id=?")->execute([$otp['id']]);

        // Créer ou retrouver le client
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
        unset($_SESSION['otp_email']);
        session_write_close(); // Forcer l'écriture
        // Rouvrir la session pour la suite de la page
        session_start();
        $step = 'account';
    }
}

// ---- Mettre à jour ses infos ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $cid = (int)($_SESSION['client_id'] ?? 0);
    if ($cid) {
        db()->prepare("UPDATE kk_clients SET name=?,phone=?,newsletter_opt=?,sms_opt=? WHERE id=?")
           ->execute([
               trim($_POST['name']    ?? ''),
               trim($_POST['phone']   ?? ''),
               (int)($_POST['newsletter_opt'] ?? 0),
               (int)($_POST['sms_opt'] ?? 0),
               $cid,
           ]);
        $success = 'Informations mises à jour.';
    }
}

// ---- Déconnexion ----
if (isset($_GET['logout'])) {
    unset($_SESSION['client_email'], $_SESSION['client_id'], $_SESSION['otp_email']);
    header('Location: ' . BASE_URL . '/mon-compte.php'); exit;
}

// ---- Charger les données client ----
$client = null;
$rdvs   = [];
$invs   = [];
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
    'pending'    => ['🟡', 'En attente de confirmation', '#fef9c3', '#854d0e'],
    'confirmed'  => ['🔵', 'Rendez-vous confirmé',       '#dbeafe', '#1d4ed8'],
    'in_progress'=> ['🟠', 'En cours de traitement',     '#ffedd5', '#c2410c'],
    'ready'      => ['🟢', 'Votre moto est prête !',     '#dcfce7', '#15803d'],
    'collected'  => ['✅', 'Récupérée',                  '#f0fdf4', '#aaa'],
    'cancelled'  => ['❌', 'Annulé',                     '#fef2f2', '#dc2626'],
];
?>

<style>
/* ===== ESPACE CLIENT ===== */
.mc-hero{
  background:#111; color:white;
  padding:48px 0 40px; text-align:center;
}
.mc-hero h1{ font-size:clamp(26px,3vw,40px);font-weight:900;letter-spacing:-1.5px;margin-bottom:6px }
.mc-hero p{ color:#888;font-size:14px }

/* Connexion */
.mc-login{
  max-width:420px; margin:48px auto; padding:0 20px;
}
.mc-card{
  background:white; border-radius:20px; padding:32px;
  box-shadow:0 4px 32px rgba(0,0,0,.08);
}
.mc-step-ico{ font-size:40px;margin-bottom:12px;display:block }
.mc-card h2{ font-size:18px;font-weight:800;margin-bottom:6px }
.mc-card p{ font-size:13px;color:#888;margin-bottom:24px;line-height:1.6 }
.mc-field{ margin-bottom:14px }
.mc-field label{ display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px }
.mc-field input{
  width:100%;border:1.5px solid #e8e8e8;border-radius:10px;
  padding:12px 14px;font-size:14px;font-family:inherit;outline:none;
  transition:border-color .2s;
}
.mc-field input:focus{ border-color:#ed0c0f;box-shadow:0 0 0 3px rgba(237,12,15,.08) }
.mc-field.otp-field input{
  font-size:28px;font-weight:900;letter-spacing:8px;text-align:center;
}
.mc-btn{
  width:100%;background:#111;color:white;border:none;border-radius:12px;
  padding:13px;font-size:14px;font-weight:700;cursor:pointer;
  transition:background .2s,transform .2s;
}
.mc-btn:hover{ background:#333;transform:translateY(-1px) }
.mc-btn.red{ background:#ed0c0f }
.mc-btn.red:hover{ background:#c00b0d }
.mc-back{ font-size:12px;color:#aaa;text-align:center;margin-top:12px;cursor:pointer;text-decoration:underline;background:none;border:none;width:100% }
.mc-success{ background:#f0fdf4;border-radius:10px;padding:12px 16px;font-size:13px;color:#15803d;margin-bottom:16px }
.mc-error{   background:#fef2f2;border-radius:10px;padding:12px 16px;font-size:13px;color:#dc2626;margin-bottom:16px }

/* Compte */
.mc-wrap{ max-width:900px;margin:0 auto;padding:32px 20px }
.mc-tabs{ display:flex;gap:0;border-bottom:2px solid #f0f0f0;margin-bottom:24px }
.mc-tab{
  padding:10px 20px;font-size:13px;font-weight:700;color:#aaa;
  cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;
  transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;
}
.mc-tab.active{ color:#ed0c0f;border-bottom-color:#ed0c0f }

/* Cards */
.mc-section{ display:none }
.mc-section.active{ display:block }
.mc-rdv-card{
  border:1.5px solid #f0f0f0;border-radius:14px;padding:16px 18px;
  margin-bottom:10px;display:flex;align-items:center;gap:14px;
  transition:box-shadow .2s;
}
.mc-rdv-card:hover{ box-shadow:0 4px 16px rgba(0,0,0,.07) }
.mc-rdv-main{ flex:1 }
.mc-rdv-svc{ font-size:14px;font-weight:700;margin-bottom:3px }
.mc-rdv-moto{ font-size:12px;color:#888 }
.mc-rdv-date{ font-size:11px;color:#aaa;margin-top:2px }
.mc-rdv-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700;
  white-space:nowrap;
}
.mc-empty{ text-align:center;padding:40px 0;color:#aaa;font-size:13px }
.mc-empty-ico{ font-size:40px;margin-bottom:10px }
.mc-new-rdv{
  display:inline-flex;align-items:center;gap:8px;
  background:#ed0c0f;color:white;border-radius:10px;
  padding:10px 18px;font-size:13px;font-weight:700;
  text-decoration:none;margin-top:14px;transition:background .2s;
}
.mc-new-rdv:hover{ background:#c00b0d }

/* Infos */
.mc-info-form{ display:grid;grid-template-columns:1fr 1fr;gap:10px }
.mc-toggle-row{
  display:flex;align-items:center;gap:12px;padding:12px 0;
  border-top:1px solid #f0f0f0;
}
.mc-toggle-row:first-child{ border:none }
.tgl{
  width:40px;height:22px;background:#ddd;border-radius:11px;
  position:relative;cursor:pointer;transition:background .2s;flex-shrink:0;
}
.tgl::after{
  content:'';position:absolute;width:16px;height:16px;background:white;
  border-radius:50%;top:3px;left:3px;transition:left .2s;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.tgl.on{ background:#ed0c0f }
.tgl.on::after{ left:21px }

/* Factures */
.mc-inv-row{
  display:flex;align-items:center;gap:12px;padding:12px 0;
  border-bottom:1px solid #f0f0f0;font-size:13px;
}
.mc-inv-row:last-child{ border:none }

@media(max-width:600px){
  .mc-info-form{ grid-template-columns:1fr }
  .mc-rdv-card{ flex-direction:column;align-items:flex-start }
}
</style>

<!-- HERO -->
<div class="mc-hero">
  <?php if($step === 'account' && $client): ?>
  <h1>Bonjour <?= h(($client['name'] ? explode(' ', $client['name'])[0] : 'vous')) ?> 👋</h1>
  <p><?= h($_SESSION['client_email']) ?> · <a href="?logout=1" style="color:#555;font-size:12px;">Se déconnecter</a></p>
  <?php else: ?>
  <h1>Mon espace</h1>
  <p>Suivez vos RDV et factures sans mot de passe</p>
  <?php endif; ?>
</div>

<?php if ($step === 'email'): ?>
<!-- ============ ÉTAPE 1 : Email ============ -->
<div class="mc-login">
  <div class="mc-card">
    <span class="mc-step-ico">✉️</span>
    <h2>Accéder à mon espace</h2>
    <p>Entrez votre email — nous vous envoyons un code de connexion à 6 chiffres, valable 10 minutes.<br>Aucun mot de passe à retenir.</p>
    <?php if($error): ?><div class="mc-error"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mc-field">
        <label>Votre adresse email</label>
        <input type="email" name="email" required autofocus placeholder="jean@mail.fr">
      </div>
      <button type="submit" name="send_otp" value="1" class="mc-btn red">
        Recevoir mon code →
      </button>
    </form>
  </div>
</div>

<?php elseif ($step === 'verify'): ?>
<!-- ============ ÉTAPE 2 : Code OTP ============ -->
<div class="mc-login">
  <div class="mc-card">
    <span class="mc-step-ico">🔐</span>
    <h2>Code de connexion</h2>
    <p>Un code à 6 chiffres a été envoyé à<br><strong><?= h($_SESSION['otp_email']) ?></strong>.<br>Vérifiez vos spams si besoin.</p>
    <?php if($success): ?><div class="mc-success"><?= h($success) ?></div><?php endif; ?>
    <?php if($error): ?><div class="mc-error"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <!-- Fallback email si session perdue -->
      <input type="hidden" name="otp_email_fallback" value="<?= h($_SESSION['otp_email'] ?? '') ?>">
      <div class="mc-field otp-field">
        <label>Code reçu par email</label>
        <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
               autofocus placeholder="000000" autocomplete="one-time-code">
      </div>
      <button type="submit" name="verify_otp" value="1" class="mc-btn red">
        Connexion →
      </button>
    </form>
    <form method="POST" style="margin-top:12px;">
      <input type="hidden" name="email" value="<?= h($_SESSION['otp_email']) ?>">
      <button type="submit" name="send_otp" value="1" class="mc-back">
        Renvoyer le code
      </button>
    </form>
    <a href="?logout=1" class="mc-back" style="display:block;margin-top:4px;">← Changer d'email</a>
  </div>
</div>

<?php else: ?>
<!-- ============ ESPACE CLIENT ============ -->
<div class="mc-wrap">

  <!-- Onglets -->
  <div class="mc-tabs">
    <button class="mc-tab active" onclick="showTab('rdvs',this)">📅 Mes RDV (<?= count($rdvs) ?>)</button>
    <button class="mc-tab" onclick="showTab('factures',this)">🧾 Factures (<?= count($invs) ?>)</button>
    <button class="mc-tab" onclick="showTab('infos',this)">👤 Mes infos</button>
    <?php if(!empty($rdvs)||true): ?>
    <button class="mc-tab" onclick="showTab('nouveau_rdv',this)" style="color:#ed0c0f;">+ Nouveau RDV</button>
    <?php endif; ?>
  </div>

  <!-- MES RDV -->
  <div class="mc-section active" id="tab-rdvs">
    <?php if(empty($rdvs)): ?>
    <div class="mc-empty">
      <div class="mc-empty-ico">📅</div>
      <div>Aucun rendez-vous pour l'instant.</div>
      <a href="<?= BASE_URL ?>/planning.php" class="mc-new-rdv">Prendre rendez-vous →</a>
    </div>
    <?php else: ?>
    <?php foreach($rdvs as $rdv):
      [$ico,$lbl,$bg,$fg] = $statuses[$rdv['status']] ?? ['?','','',' #999'];
    ?>
    <div class="mc-rdv-card">
      <div class="mc-rdv-main">
        <div class="mc-rdv-svc"><?= h($rdv['service_label'] ?: '—') ?></div>
        <div class="mc-rdv-moto">
          🏍️ <?= h(trim($rdv['moto_marque'].' '.$rdv['moto_modele'].' '.$rdv['moto_annee'])) ?>
        </div>
        <?php if($rdv['slot_date']): ?>
        <div class="mc-rdv-date">
          📅 <?= date('d/m/Y', strtotime($rdv['slot_date'])) ?>
          <?= $rdv['slot_time'] ? ' à '.substr($rdv['slot_time'],0,5) : '' ?>
        </div>
        <?php endif; ?>
      </div>
      <span class="mc-rdv-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;">
        <?= $ico ?> <?= $lbl ?>
      </span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:16px;">
      <a href="<?= BASE_URL ?>/planning.php" class="mc-new-rdv">+ Nouveau rendez-vous</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- FACTURES -->
  <div class="mc-section" id="tab-factures">
    <?php if(empty($invs)): ?>
    <div class="mc-empty">
      <div class="mc-empty-ico">🧾</div>
      <div>Aucune facture.</div>
    </div>
    <?php else: ?>
    <?php foreach($invs as $inv):
      $tot = invoice_totals(jd($inv['invoice_lines']??'[]',[]), (float)$inv['tva_rate'],
                            (float)$inv['discount'], $inv['discount_type']??'amount');
      $type_lbl = ['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'][$inv['type']] ?? '';
    ?>
    <div class="mc-inv-row">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700;"><?= $type_lbl ?> <?= h($inv['number']) ?></div>
        <div style="font-size:11px;color:#aaa;"><?= date('d/m/Y', strtotime($inv['created_at'])) ?></div>
      </div>
      <div style="font-size:15px;font-weight:800;"><?= number_format($tot['ttc'],2,',',' ') ?> €</div>
      <span class="mc-rdv-badge" style="background:<?= $inv['status']==='paid'?'#dcfce7':'#fef9c3' ?>;color:<?= $inv['status']==='paid'?'#15803d':'#854d0e' ?>;">
        <?= $inv['status']==='paid' ? '✅ Payé' : '⏳ En attente' ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- MES INFOS -->
  <div class="mc-section" id="tab-infos">
    <?php if($success && str_contains($success,'mis à jour')): ?>
    <div class="mc-success" style="margin-bottom:16px;"><?= h($success) ?></div>
    <?php endif; ?>
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:18px;">Mes informations</h3>
      <form method="POST">
        <input type="hidden" name="update_info" value="1">
        <div class="mc-info-form">
          <div class="mc-field">
            <label>Nom complet</label>
            <input type="text" name="name" value="<?= h($client['name']??'') ?>" placeholder="Jean Dupont">
          </div>
          <div class="mc-field">
            <label>Email</label>
            <input type="email" value="<?= h($_SESSION['client_email']) ?>" disabled style="background:#fafafa;color:#aaa;">
          </div>
          <div class="mc-field">
            <label>Téléphone</label>
            <input type="tel" name="phone" value="<?= h($client['phone']??'') ?>" placeholder="+33 6 …">
          </div>
        </div>

        <!-- Préférences communications -->
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;">
          <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Préférences de communication</div>
          <div class="mc-toggle-row">
            <div class="tgl <?= ($client['newsletter_opt']??1)?'on':'' ?>" id="tgl-nl" onclick="togglePref(this,'newsletter_opt')"></div>
            <input type="hidden" name="newsletter_opt" id="inp-nl" value="<?= ($client['newsletter_opt']??1)?1:0 ?>">
            <div>
              <div style="font-size:13px;font-weight:600;">Newsletter</div>
              <div style="font-size:11px;color:#aaa;">Recevoir les actualités et offres par email</div>
            </div>
          </div>
          <div class="mc-toggle-row">
            <div class="tgl <?= ($client['sms_opt']??1)?'on':'' ?>" id="tgl-sms" onclick="togglePref(this,'sms_opt')"></div>
            <input type="hidden" name="sms_opt" id="inp-sms" value="<?= ($client['sms_opt']??1)?1:0 ?>">
            <div>
              <div style="font-size:13px;font-weight:600;">SMS</div>
              <div style="font-size:11px;color:#aaa;">Recevoir les notifications par SMS</div>
            </div>
          </div>
        </div>

        <button type="submit" class="mc-btn" style="margin-top:18px;width:auto;padding:10px 24px;">
          💾 Enregistrer
        </button>
      </form>
    </div>
  </div>

  <!-- NOUVEAU RDV avec infos pré-remplies -->
  <div class="mc-section" id="tab-nouveau_rdv">
    <div style="background:white;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);">
      <h3 style="font-size:15px;font-weight:800;margin-bottom:6px;">Nouvelle demande de RDV</h3>
      <p style="font-size:13px;color:#888;margin-bottom:20px;">Vos informations sont pré-remplies. Modifiez-les si besoin.</p>

      <form method="POST" action="<?= BASE_URL ?>/planning.php" enctype="multipart/form-data">
        <input type="hidden" name="rdv_submit" value="1">
        <!-- Infos pré-remplies -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
          <?php foreach([
            ['name','Nom','text',$client['name']??'','Jean Dupont'],
            ['email','Email','email',$_SESSION['client_email'],''],
            ['phone','Téléphone','tel',$client['phone']??'','+33 6 …'],
          ] as [$n,$l,$t,$v,$ph]): ?>
          <div class="mc-field">
            <label><?= $l ?></label>
            <input type="<?= $t ?>" name="<?= $n ?>" value="<?= h($v) ?>" placeholder="<?= h($ph) ?>" <?= $n==='email'?'readonly style="background:#fafafa;"':'' ?>>
          </div>
          <?php endforeach; ?>
          <div class="mc-field">
            <label>Prestation *</label>
            <select name="service_label" required>
              <option value="">— Choisir —</option>
              <?php foreach($svc_list as $s): ?>
              <option value="<?= h($s['label']) ?>"><?= h($s['title']) ?></option>
              <?php endforeach; ?>
              <option value="Autre">Autre</option>
            </select>
          </div>
          <div class="mc-field">
            <label>Marque moto *</label>
            <input type="text" name="moto_marque" required placeholder="Yamaha, KTM…">
          </div>
          <div class="mc-field">
            <label>Modèle</label>
            <input type="text" name="moto_modele" placeholder="YZ450F">
          </div>
          <div class="mc-field">
            <label>Année</label>
            <input type="number" name="moto_annee" placeholder="2022" min="1990" max="<?= date('Y')+1 ?>">
          </div>
        </div>
        <div class="mc-field" style="margin-bottom:16px;">
          <label>Message / Précisions</label>
          <textarea name="notes_client" style="width:100%;border:1.5px solid #e8e8e8;border-radius:10px;padding:10px 14px;font-size:13px;font-family:inherit;min-height:80px;resize:vertical;outline:none;" placeholder="Poids, niveau, problème constaté…"></textarea>
        </div>
        <button type="submit" class="mc-btn red" style="width:auto;padding:12px 24px;">
          Envoyer ma demande →
        </button>
        <div style="font-size:11px;color:#aaa;margin-top:8px;">Vous serez recontacté pour confirmer la date.</div>
      </form>
    </div>
  </div>

</div><!-- /.mc-wrap -->
<?php endif; ?>

<script>
function showTab(name, btn) {
  document.querySelectorAll('.mc-section').forEach(function(s){ s.classList.remove('active'); });
  document.querySelectorAll('.mc-tab').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}
function togglePref(el, field) {
  el.classList.toggle('on');
  var isOn = el.classList.contains('on');
  document.getElementById('inp-'+field.replace('_opt','')).value = isOn ? 1 : 0;
  // Map field name to input id
  if(field === 'newsletter_opt') document.getElementById('inp-nl').value = isOn ? 1 : 0;
  if(field === 'sms_opt')        document.getElementById('inp-sms').value = isOn ? 1 : 0;
}
// Format OTP : nettoyer et auto-soumettre avec un court délai
var otpInput = document.querySelector('input[name="code"]');
if (otpInput) {
  otpInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').substring(0, 6);
    if (this.value.length === 6) {
      // Petit délai pour que l'utilisateur voie le code complet
      var self = this;
      setTimeout(function() { self.form.submit(); }, 300);
    }
  });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
