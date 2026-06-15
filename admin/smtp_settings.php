<?php
ob_start();
require_once __DIR__ . '/layout.php';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'smtp_host','smtp_port','smtp_user','smtp_from_name',
        'smtp_secure','sms_provider','sms_api_key','sms_api_secret','sms_sender',
        'ovh_service_name',
    ];
    foreach ($fields as $k) set_setting($k, trim($_POST[$k] ?? ''));
    // reCAPTCHA
    set_setting('recaptcha_site',   trim($_POST['recaptcha_site']   ?? ''));
    set_setting('recaptcha_secret', trim($_POST['recaptcha_secret'] ?? ''));
    set_setting('recaptcha_score',  trim($_POST['recaptcha_score']  ?? '0.5'));
    // Mot de passe SMTP : ne pas effacer si vide
    if (!empty($_POST['smtp_pass'])) set_setting('smtp_pass', trim($_POST['smtp_pass']));
    set_setting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
    $saved = true;
}

// Test d'envoi
$test_result = '';
if (isset($_POST['send_test'])) {
    $to = trim($_POST['test_email'] ?? get_setting('site_email'));
    if ($to) {
        $ok = mail($to, 'Test email — ' . get_setting('site_name'),
            "Ceci est un email de test envoyé depuis l'admin Kik'r.\n\nSi vous recevez cet email, la configuration fonctionne.",
            "From: " . get_setting('site_name') . " <" . get_setting('site_email') . ">");
        $test_result = $ok
            ? "<div class='alert alert-ok'>✅ Email envoyé à $to — vérifiez votre boîte.</div>"
            : "<div class='alert alert-err'>❌ Échec d'envoi. Vérifiez la config SMTP ci-dessous.</div>";
    }
}
?>
<div class="adm-topbar"><h1>📬 Paramètres email & SMS</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<?= $test_result ?>

<form method="POST">

<!-- EMAIL -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📧</span> Configuration email</h2></div>
  <p class="card-hint">
    Par défaut, PHP utilise la fonction <code>mail()</code> native — suffisant sur la plupart des hébergeurs mutualisés.<br>
    Si vos emails n'arrivent pas, configurez un serveur SMTP (Gmail, OVH, Infomaniak, Brevo…).
  </p>

  <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid <?= get_setting('smtp_enabled','0')==='1'?'var(--red)':'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= get_setting('smtp_enabled','0')==='1'?'#fef2f2':'var(--bg)' ?>;margin-bottom:16px;">
    <input type="checkbox" name="smtp_enabled" <?= get_setting('smtp_enabled','0')==='1'?'checked':'' ?> style="width:16px;height:16px;accent-color:#ed0c0f;">
    <div>
      <div style="font-size:13px;font-weight:700;">Utiliser un serveur SMTP</div>
      <div style="font-size:11px;color:var(--muted);">Désactivé = PHP mail() natif (recommandé pour hébergements mutualisés)</div>
    </div>
  </label>

  <div class="g2">
    <div class="fgrp">
      <label>Hôte SMTP</label>
      <input type="text" name="smtp_host" value="<?= h(get_setting('smtp_host','')) ?>" placeholder="smtp.gmail.com / ssl0.ovh.net / mail.infomaniak.com">
    </div>
    <div class="fgrp">
      <label>Port</label>
      <select name="smtp_port">
        <?php foreach(['587'=>'587 (TLS — recommandé)','465'=>'465 (SSL)','25'=>'25 (non chiffré)'] as $p=>$l): ?>
        <option value="<?= $p ?>" <?= get_setting('smtp_port','587')===$p?'selected':''?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fgrp">
      <label>Chiffrement</label>
      <select name="smtp_secure">
        <option value="tls" <?= get_setting('smtp_secure','tls')==='tls'?'selected':''?>>TLS (recommandé)</option>
        <option value="ssl" <?= get_setting('smtp_secure','tls')==='ssl'?'selected':''?>>SSL</option>
        <option value=""    <?= get_setting('smtp_secure','tls')===''   ?'selected':''?>>Aucun</option>
      </select>
    </div>
    <div class="fgrp">
      <label>Nom d'expéditeur</label>
      <input type="text" name="smtp_from_name" value="<?= h(get_setting('smtp_from_name', get_setting('site_name'))) ?>" placeholder="Kik'r Suspension">
    </div>
    <div class="fgrp">
      <label>Email SMTP (identifiant)</label>
      <input type="email" name="smtp_user" value="<?= h(get_setting('smtp_user','')) ?>" placeholder="contact@monsite.fr">
    </div>
    <div class="fgrp">
      <label>Mot de passe SMTP</label>
      <input type="password" name="smtp_pass" value="" placeholder="Laissez vide pour conserver l'actuel" autocomplete="new-password">
    </div>
  </div>

  <div style="background:#f0fdf4;border-radius:8px;padding:12px 14px;font-size:12px;color:#15803d;margin-top:4px;line-height:1.7;">
    <strong>Configurations rapides :</strong><br>
    Gmail : smtp.gmail.com · port 587 · TLS · (mot de passe = App Password Google)<br>
    OVH : ssl0.ovh.net · port 465 · SSL<br>
    Infomaniak : mail.infomaniak.com · port 587 · TLS<br>
    Brevo (ex Sendinblue) : smtp-relay.brevo.com · port 587 · TLS
  </div>
</div>

<!-- TEST EMAIL -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🧪</span> Tester l'envoi d'email</h2></div>
  <div style="display:flex;gap:8px;align-items:flex-end;">
    <div class="fgrp" style="flex:1;margin:0;">
      <label>Adresse de test</label>
      <input type="email" name="test_email" value="<?= h(get_setting('site_email')) ?>" placeholder="votre@email.fr">
    </div>
    <button type="submit" name="send_test" value="1" class="btn btn-secondary">📤 Envoyer un test</button>
  </div>
</div>

<!-- SMS -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📱</span> Configuration SMS</h2></div>
  <p class="card-hint">Les SMS sont utilisés pour les notifications de RDV et les campagnes SMS. Choisissez votre provider.</p>

  <div class="fgrp" style="margin-bottom:16px;">
    <label>Provider SMS</label>
    <select name="sms_provider" id="sms-provider" onchange="showSmsFields(this.value)">
      <option value=""       <?= get_setting('sms_provider','')==''     ?'selected':''?>>— Désactivé —</option>
      <option value="ovh"    <?= get_setting('sms_provider','')=='ovh'  ?'selected':''?>>OVH SMS (France, recommandé)</option>
      <option value="twilio" <?= get_setting('sms_provider','')=='twilio'?'selected':''?>>Twilio (international)</option>
    </select>
  </div>

  <!-- OVH -->
  <div id="sms-ovh" style="display:<?= get_setting('sms_provider','')=='ovh'?'block':'none' ?>;">
    <div style="background:#fef9c3;border-radius:8px;padding:10px 14px;font-size:12px;color:#854d0e;margin-bottom:12px;line-height:1.6;">
      <strong>OVH SMS :</strong> Créez un compte sur <a href="https://www.ovhtelecom.fr/sms/" target="_blank" style="color:#854d0e;">ovhtelecom.fr/sms</a><br>
      → API → Clés d'application → Créer une clé
    </div>
    <div class="g2">
      <div class="fgrp"><label>Clé d'application (Application Key)</label><input type="text" name="sms_api_key" value="<?= h(get_setting('sms_api_key','')) ?>" placeholder="xxxxxxxxxxxxxxxx"></div>
      <div class="fgrp"><label>Secret (Application Secret)</label><input type="password" name="sms_api_secret" value="<?= h(get_setting('sms_api_secret','')) ?>" placeholder="xxxxxxxxxxxxxxxx"></div>
      <div class="fgrp"><label>Nom de service SMS</label><input type="text" name="ovh_service_name" value="<?= h(get_setting('ovh_service_name','')) ?>" placeholder="sms-xxxxx-1"></div>
      <div class="fgrp"><label>Expéditeur (11 car. max)</label><input type="text" name="sms_sender" value="<?= h(get_setting('sms_sender',"Kik'r")) ?>" maxlength="11" placeholder="KikrSusp"></div>
    </div>
  </div>

  <!-- Twilio -->
  <div id="sms-twilio" style="display:<?= get_setting('sms_provider','')=='twilio'?'block':'none' ?>;">
    <div style="background:#dbeafe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1d4ed8;margin-bottom:12px;line-height:1.6;">
      <strong>Twilio :</strong> Console sur <a href="https://console.twilio.com" target="_blank" style="color:#1d4ed8;">console.twilio.com</a> → Account SID + Auth Token
    </div>
    <div class="g2">
      <div class="fgrp"><label>Account SID</label><input type="text" name="sms_api_key" value="<?= h(get_setting('sms_api_key','')) ?>" placeholder="ACxxxxxxxx"></div>
      <div class="fgrp"><label>Auth Token</label><input type="password" name="sms_api_secret" value="<?= h(get_setting('sms_api_secret','')) ?>"></div>
      <div class="fgrp"><label>Numéro expéditeur</label><input type="text" name="sms_sender" value="<?= h(get_setting('sms_sender','')) ?>" placeholder="+33600000000"></div>
    </div>
  </div>
</div>

<!-- reCAPTCHA -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🤖</span> reCAPTCHA v3 (anti-spam)</h2></div>
  <p class="card-hint">
    Protège vos formulaires (contact, RDV, newsletter) contre les bots et le spam.<br>
    reCAPTCHA v3 est <strong>invisible</strong> — aucun clic requis pour vos visiteurs.<br>
    <a href="https://www.google.com/recaptcha/admin/create" target="_blank" style="color:var(--red);">Obtenir des clés gratuitement sur Google →</a>
  </p>
  <div class="g2">
    <div class="fgrp">
      <label>Clé du site (Site Key)</label>
      <input type="text" name="recaptcha_site" value="<?= h(get_setting('recaptcha_site','')) ?>" placeholder="6Lc…">
      <span class="hint">Utilisée dans le HTML côté client</span>
    </div>
    <div class="fgrp">
      <label>Clé secrète (Secret Key)</label>
      <input type="password" name="recaptcha_secret" value="<?= h(get_setting('recaptcha_secret','')) ?>" placeholder="6Lc…">
      <span class="hint">Utilisée côté serveur pour vérifier</span>
    </div>
    <div class="fgrp">
      <label>Score minimum (0.0 à 1.0)</label>
      <input type="number" name="recaptcha_score" value="<?= h(get_setting('recaptcha_score','0.5')) ?>" step="0.1" min="0" max="1">
      <span class="hint">0.5 recommandé. Plus élevé = plus strict.</span>
    </div>
  </div>
  <div style="background:#f0fdf4;border-radius:8px;padding:12px 14px;font-size:12px;color:#15803d;margin-top:4px;line-height:1.7;">
    <strong>Comment obtenir vos clés :</strong><br>
    1. Allez sur <a href="https://www.google.com/recaptcha/admin/create" target="_blank" style="color:#15803d;">google.com/recaptcha/admin/create</a><br>
    2. Choisissez <strong>reCAPTCHA v3</strong><br>
    3. Ajoutez votre domaine (ex: monsite.fr)<br>
    4. Copiez les deux clés ci-dessus
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
</form>
</div>

<script>
function showSmsFields(v) {
  document.getElementById('sms-ovh').style.display    = v === 'ovh'    ? 'block' : 'none';
  document.getElementById('sms-twilio').style.display = v === 'twilio' ? 'block' : 'none';
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
<?php // Appended - handled in save above ?>
