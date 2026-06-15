<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
$page_title = 'Contact';
$ct = get_contact();
$success = false; $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['contact_submit'])) {
    $name  = trim($_POST['name']   ?? '');
    $email = trim($_POST['email']  ?? '');
    $phone = trim($_POST['phone']  ?? '');
    $motif = trim($_POST['motif']  ?? '');
    $msg   = trim($_POST['message']?? '');
    if ($name && $email && $msg) {
        $to = get_setting('site_email');
        if ($to) mail($to,"[$motif] Message de $name",$msg,"From: $email\r\nReply-To: $email");
        mail($email,"Message reçu — Kik'r Suspension","Bonjour $name,\n\nVotre message a bien été reçu.\n\nKik'r Suspension","From: $to");
        $success = true;
    } else { $error = 'Merci de remplir tous les champs.'; }
}
require_once __DIR__ . '/layout/header.php';
?>

<style>
.contact-hero{ background:#111;color:white;padding:52px 0 44px;text-align:center }
.contact-hero h1{ font-size:clamp(30px,4vw,50px);font-weight:900;letter-spacing:-2px;margin-bottom:8px }
.contact-hero p{ color:#888;font-size:15px }
.contact-wrap{ max-width:1000px;margin:0 auto;padding:48px 20px;display:grid;grid-template-columns:3fr 2fr;gap:48px;align-items:start }

/* Sélecteur de motif */
.motif-grid{ display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:28px }
.motif-card{
  border:2px solid #e8e8e8;border-radius:14px;padding:16px;
  cursor:pointer;text-align:center;transition:all .25s cubic-bezier(.22,1,.36,1);
  background:white;
}
.motif-card:hover{ border-color:#ed0c0f;transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08) }
.motif-card.active{ border-color:#ed0c0f;background:#fef2f2 }
.motif-card-icon{ font-size:28px;margin-bottom:6px }
.motif-card-label{ font-size:13px;font-weight:700;color:#111 }
.motif-card.active .motif-card-label{ color:#ed0c0f }
.motif-card-sub{ font-size:11px;color:#aaa;margin-top:3px }

/* Formulaire animé */
.contact-form-area{
  overflow:hidden;transition:max-height .5s cubic-bezier(.22,1,.36,1), opacity .4s;
  max-height:0;opacity:0;
}
.contact-form-area.open{ max-height:1200px;opacity:1 }

/* Champs */
.cf{ margin-bottom:14px }
.cf label{ display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px }
.cf input,.cf select,.cf textarea{
  width:100%;border:1.5px solid #e8e8e8;border-radius:10px;
  padding:10px 14px;font-size:13px;font-family:inherit;
  outline:none;transition:border-color .2s,box-shadow .2s;background:#fafafa;
}
.cf input:focus,.cf select:focus,.cf textarea:focus{
  border-color:#ed0c0f;background:white;box-shadow:0 0 0 3px rgba(237,12,15,.08);
}
.cf textarea{ resize:vertical;min-height:110px }
.cf-row{ display:grid;grid-template-columns:1fr 1fr;gap:10px }
.cf-submit{
  width:100%;background:#ed0c0f;color:white;border:none;border-radius:12px;
  padding:14px;font-size:14px;font-weight:800;cursor:pointer;
  box-shadow:0 4px 16px rgba(237,12,15,.3);
  transition:background .2s,transform .2s,box-shadow .2s;
}
.cf-submit:hover{ background:#c00b0d;transform:translateY(-1px);box-shadow:0 8px 24px rgba(237,12,15,.4) }

/* RDV redirect banner */
.rdv-banner{
  background:#111;border-radius:14px;padding:20px 22px;
  display:flex;align-items:center;gap:16px;margin-top:20px;
  text-decoration:none;transition:background .2s;
  overflow:hidden;max-height:0;opacity:0;
  transition:max-height .5s cubic-bezier(.22,1,.36,1),opacity .4s,background .2s;
}
.rdv-banner.open{ max-height:100px;opacity:1 }
.rdv-banner:hover{ background:#222 }
.rdv-banner-text{ flex:1 }
.rdv-banner-title{ font-size:14px;font-weight:800;color:white;margin-bottom:3px }
.rdv-banner-sub{ font-size:12px;color:#888 }
.rdv-banner-btn{ background:#ed0c0f;color:white;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;white-space:nowrap }

/* Infos droite */
.contact-info-box{ background:white;border-radius:20px;padding:28px;box-shadow:0 4px 24px rgba(0,0,0,.06) }
.ci-row{ display:flex;align-items:flex-start;gap:14px;margin-bottom:22px }
.ci-ico{ width:40px;height:40px;background:#ed0c0f;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0 }
.ci-ico svg{ width:18px;height:18px;stroke:white;fill:none;stroke-width:2 }
.ci-lbl{ font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px }
.ci-val{ font-size:14px;font-weight:600;color:#111 }
.hours-box{ background:#f5f5f3;border-radius:12px;padding:16px;margin-top:20px }
.hours-row{ display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e8e8e8;font-size:13px }
.hours-row:last-child{ border:none }
.hours-day{ font-weight:600 }
.hours-time{ color:#888 }
.hours-rdv{ color:#ed0c0f;font-size:11px;font-weight:700 }

.success-box{ background:#f0fdf4;border:1px solid #86efac;border-radius:16px;padding:32px;text-align:center;animation:fadeInUp .6s cubic-bezier(.22,1,.36,1) }
@keyframes fadeInUp{ from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

@media(max-width:768px){
  .contact-wrap{ grid-template-columns:1fr;gap:28px }
  .cf-row{ grid-template-columns:1fr }
  .motif-grid{ grid-template-columns:1fr 1fr }
}
</style>

<div class="contact-hero">
  <div style="max-width:560px;margin:0 auto;padding:0 20px;">
    <h1>Contactez-nous</h1>
    <p>Choisissez le motif de votre demande</p>
  </div>
</div>

<div class="contact-wrap">
  <div>
    <?php if($success): ?>
    <div class="success-box">
      <div style="font-size:48px;margin-bottom:12px;">✅</div>
      <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Message envoyé !</h2>
      <p style="color:#555;">Nous vous répondrons rapidement.</p>
    </div>
    <?php else: ?>

    <!-- Motifs -->
    <div class="motif-grid">
      <?php foreach([
        ['rdv',     '🗓️', 'Prendre RDV',        'Déposer ma moto'],
        ['devis',   '💬', 'Demande de devis',    'Tarif & infos'],
        ['question','❓', 'Question technique',  'Conseil suspension'],
        ['other',   '✉️', 'Autre message',       'Autre demande'],
      ] as [$k,$ico,$lbl,$sub]): ?>
      <div class="motif-card <?= ($_POST['motif']??'')===$k?'active':'' ?>"
           id="motif-<?= $k ?>"
           onclick="selectMotif('<?= $k ?>')">
        <div class="motif-card-icon"><?= $ico ?></div>
        <div class="motif-card-label"><?= $lbl ?></div>
        <div class="motif-card-sub"><?= $sub ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Bannière RDV (si motif RDV sélectionné) -->
    <a href="<?= BASE_URL ?>/planning.php" class="rdv-banner <?= ($_POST['motif']??'')==='rdv'?'open':'' ?>" id="rdv-banner">
      <svg width="28" height="28" fill="none" stroke="#ed0c0f" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <div class="rdv-banner-text">
        <div class="rdv-banner-title">Accéder au planning en ligne</div>
        <div class="rdv-banner-sub">Choisissez votre créneau directement sur le calendrier</div>
      </div>
      <div class="rdv-banner-btn">Voir le planning →</div>
    </a>

    <!-- Formulaire de contact (caché jusqu'au choix) -->
    <div class="contact-form-area <?= !empty($_POST['motif'])?'open':'' ?>" id="form-area">
      <?php if(!empty($error)): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;font-size:13px;color:#dc2626;margin-bottom:14px;"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="contact_submit" value="1">
        <input type="hidden" name="motif" id="motif-input" value="<?= h($_POST['motif']??'') ?>">

        <div class="cf-row">
          <div class="cf"><label>Nom *</label><input type="text" name="name" value="<?= h($_POST['name']??'') ?>" required placeholder="Jean Dupont"></div>
          <div class="cf"><label>Téléphone</label><input type="tel" name="phone" value="<?= h($_POST['phone']??'') ?>" placeholder="+33 6 …"></div>
        </div>
        <div class="cf"><label>Email *</label><input type="email" name="email" value="<?= h($_POST['email']??'') ?>" required placeholder="jean@mail.fr"></div>
        <div class="cf"><label>Message *</label>
          <textarea name="message" required id="msg-field" placeholder="Décrivez votre demande…"><?= h($_POST['message']??'') ?></textarea>
        </div>
        <button type="submit" class="cf-submit" id="cf-btn">Envoyer →</button>
      </form>
    </div>

    <?php endif; ?>
  </div>

  <!-- Infos contact -->
  <div>
    <div class="contact-info-box">
      <div class="ci-row">
        <div class="ci-ico"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.31 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
        <div><div class="ci-lbl">Téléphone</div><div class="ci-val"><?= h(get_setting('site_phone')) ?></div></div>
      </div>
      <div class="ci-row">
        <div class="ci-ico"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <div><div class="ci-lbl">Email</div><div class="ci-val"><?= h(get_setting('site_email')) ?></div></div>
      </div>
      <div class="ci-row">
        <div class="ci-ico"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
        <div><div class="ci-lbl">Localisation</div><div class="ci-val"><?= h(get_setting('site_address')) ?></div></div>
      </div>
      <div class="hours-box">
        <div style="font-size:11px;font-weight:700;color:#ed0c0f;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Horaires</div>
        <?php foreach($ct['hours']??[] as $h): ?>
        <div class="hours-row">
          <span class="hours-day"><?= h($h['day']) ?></span>
          <span><?php if(!empty($h['rdv'])): ?><span class="hours-rdv">Sur RDV</span><?php else: ?><span class="hours-time"><?= h($h['hours']) ?></span><?php endif; ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if(!empty($ct['map_embed'])): ?>
      <div style="margin-top:16px;border-radius:10px;overflow:hidden;aspect-ratio:4/3;">
        <iframe src="<?= h($ct['map_embed']) ?>" width="100%" height="100%" frameborder="0" loading="lazy"></iframe>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var motifPlaceholders = {
  rdv:     'Marque et modèle de votre moto, type de prestation souhaitée, poids pilote, discipline…',
  devis:   'Décrivez votre moto, la prestation souhaitée, et toute information utile pour l\'estimation…',
  question:'Posez votre question technique, décrivez le problème constaté…',
  other:   'Votre message…'
};
var motifBtns = {
  rdv:     '🗓️ Prendre rendez-vous',
  devis:   '💬 Demander un devis',
  question:'❓ Envoyer ma question',
  other:   '✉️ Envoyer mon message'
};

function selectMotif(motif) {
  // Cards
  document.querySelectorAll('.motif-card').forEach(function(c){ c.classList.remove('active') });
  document.getElementById('motif-'+motif).classList.add('active');
  document.getElementById('motif-input').value = motif;

  // RDV banner
  var banner = document.getElementById('rdv-banner');
  if (motif === 'rdv') {
    banner.classList.add('open');
  } else {
    banner.classList.remove('open');
  }

  // Formulaire
  var area = document.getElementById('form-area');
  area.classList.add('open');

  // Placeholder et bouton
  if (motifPlaceholders[motif]) {
    document.getElementById('msg-field').placeholder = motifPlaceholders[motif];
  }
  if (motifBtns[motif]) {
    document.getElementById('cf-btn').textContent = motifBtns[motif];
  }

  // Scroll vers formulaire
  setTimeout(function(){
    area.scrollIntoView({behavior:'smooth', block:'nearest'});
  }, 200);
}

// Restaurer si POST avec erreur
<?php if(!empty($_POST['motif'])): ?>
selectMotif('<?= h($_POST['motif']) ?>');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
