<?php
ob_start();
require_once __DIR__ . '/layout.php';

$templates_def = [
    'email_rdv_request' => [
        'label' => '📅 Confirmation demande RDV (au client)',
        'vars'  => ['{NOM}','[NOM_SITE}','{TELEPHONE}','{SERVICE}','{DATE}','{LIEN_PAIEMENT}'],
        'default_subject' => 'Demande RDV reçue — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Votre demande de RDV a bien été reçue.

⚠️ Ne déposez pas votre moto avant notre appel de confirmation. Aucun créneau n'est réservé tant que vous n'avez pas reçu notre confirmation.

Cordialement,
{NOM_SITE}
{TELEPHONE}",
    ],
    'email_rdv_confirmed' => [
        'label' => '✅ RDV confirmé (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{TELEPHONE}','{SERVICE}','{DATE}','{HEURE}'],
        'default_subject' => '✅ RDV confirmé — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Votre rendez-vous est confirmé !

🔧 Prestation : {SERVICE}
📅 Date       : {DATE} à {HEURE}

Vous pouvez déposer votre moto à l'atelier à l'heure convenue.

Cordialement,
{NOM_SITE}
{TELEPHONE}",
    ],
    'email_rdv_pay_link' => [
        'label' => '💳 Lien de paiement RDV (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{TELEPHONE}','{SERVICE}','{DATE}','{LIEN_PAIEMENT}','{MONTANT}'],
        'default_subject' => 'Finalisez votre RDV — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Votre demande de RDV a bien été reçue.

Pour finaliser votre réservation, merci de régler en ligne :
{LIEN_PAIEMENT}

Montant : {MONTANT} €

Votre rendez-vous sera confirmé dès réception du paiement.

⚠️ Ne déposez pas votre moto avant notre confirmation.

Cordialement,
{NOM_SITE}
{TELEPHONE}",
    ],
    'email_rdv_ready' => [
        'label' => '🏍️ Moto prête (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{TELEPHONE}'],
        'default_subject' => '🏍️ Votre moto est prête — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Votre moto est prête et disponible au retrait !

Nos horaires : consultez notre site.
Pour tout renseignement : {TELEPHONE}

Cordialement,
{NOM_SITE}",
    ],
    'email_paiement_recu' => [
        'label' => '✅ Paiement reçu (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{MONTANT}','{LIEN_FACTURE}'],
        'default_subject' => '✅ Paiement confirmé — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Votre paiement de {MONTANT} € a bien été reçu.

📄 Votre facture : {LIEN_FACTURE}

Merci pour votre confiance !

Cordialement,
{NOM_SITE}",
    ],
    'email_commande_shop' => [
        'label' => '🛒 Confirmation commande boutique (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{TELEPHONE}','{NUMERO_CMD}','{ARTICLES}','{TOTAL}','{LIEN_FACTURE}'],
        'default_subject' => '✅ Commande {NUMERO_CMD} confirmée — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Merci pour votre commande n°{NUMERO_CMD} !

{ARTICLES}

Total : {TOTAL} €

📄 Votre facture : {LIEN_FACTURE}

Nous vous contacterons rapidement pour confirmer la livraison.

Cordialement,
{NOM_SITE}
{TELEPHONE}",
    ],
    'email_contact_confirm' => [
        'label' => '📧 Confirmation message contact (au client)',
        'vars'  => ['{NOM}','{NOM_SITE}','{MOTIF}','{MESSAGE}'],
        'default_subject' => 'Votre message a bien été reçu — {NOM_SITE}',
        'default_body'    =>
"Bonjour {NOM},

Nous avons bien reçu votre message concernant : {MOTIF}.

Nous vous répondrons dans les meilleurs délais.

---
Votre message :
{MESSAGE}
---

Cordialement,
{NOM_SITE}",
    ],
];

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($templates_def as $key => $_) {
        if (isset($_POST['subj_'.$key])) {
            set_setting('tpl_subj_'.$key, trim($_POST['subj_'.$key]));
            set_setting('tpl_body_'.$key, trim($_POST['body_'.$key]));
        }
    }
    $saved = true;
}

function get_tpl_subject(string $key, string $default): string {
    return get_setting('tpl_subj_'.$key, $default);
}
function get_tpl_body(string $key, string $default): string {
    return get_setting('tpl_body_'.$key, $default);
}

$active_tab = $_GET['tpl'] ?? array_key_first($templates_def);
if (!isset($templates_def[$active_tab])) $active_tab = array_key_first($templates_def);
?>
<div class="adm-topbar">
  <h1>📧 Templates d'email</h1>
  <a href="<?= BASE_URL ?>/admin/smtp_settings.php" class="btn btn-secondary btn-sm">⚙️ Config SMTP</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Templates enregistrés.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:240px 1fr;gap:16px;align-items:start;">

  <!-- Liste des templates -->
  <div class="card" style="padding:8px;">
    <?php foreach($templates_def as $key => $tpl): ?>
    <a href="?tpl=<?= $key ?>"
       style="display:block;padding:10px 12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;color:<?= $active_tab===$key?'#ed0c0f':'#444' ?>;background:<?= $active_tab===$key?'#fef2f2':'transparent' ?>;margin-bottom:2px;transition:all .15s;">
      <?= $tpl['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Éditeur -->
  <?php $tpl = $templates_def[$active_tab]; ?>
  <div class="card">
    <div class="card-head">
      <h2><?= $tpl['label'] ?></h2>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="subj_<?= $active_tab ?>" value="<?= h($tpl['default_subject']) ?>">
        <input type="hidden" name="body_<?= $active_tab ?>" value="<?= h($tpl['default_body']) ?>">
        <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Remettre ce template aux valeurs par défaut ?')">🔄 Défaut</button>
      </form>
    </div>

    <!-- Variables -->
    <div style="background:#f5f5f3;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;">
      <strong style="display:block;margin-bottom:6px;">Variables disponibles (cliquez pour insérer) :</strong>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach($tpl['vars'] as $var): ?>
        <code onclick="insertVar('<?= $var ?>')"
              style="background:white;border:1px solid #e0e0e0;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:11px;transition:all .15s;"
              onmouseover="this.style.borderColor='#ed0c0f';this.style.color='#ed0c0f'"
              onmouseout="this.style.borderColor='#e0e0e0';this.style.color=''"><?= h($var) ?></code>
        <?php endforeach; ?>
        <!-- Variables globales toujours dispo -->
        <?php foreach(['{NOM_SITE}','{TELEPHONE}','{EMAIL_SITE}'] as $gv): ?>
        <?php if(!in_array($gv,$tpl['vars'])): ?>
        <code onclick="insertVar('<?= $gv ?>')"
              style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:11px;">
          <?= h($gv) ?>
        </code>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="POST">
      <div class="fgrp" style="margin-bottom:14px;">
        <label>Sujet de l'email</label>
        <input type="text" name="subj_<?= $active_tab ?>"
               value="<?= h(get_tpl_subject($active_tab, $tpl['default_subject'])) ?>"
               id="tpl-subject"
               style="font-size:14px;font-weight:600;">
      </div>
      <div class="fgrp">
        <label>Corps de l'email</label>
        <textarea name="body_<?= $active_tab ?>" id="tpl-body"
                  style="min-height:320px;font-family:'Courier New',monospace;font-size:13px;line-height:1.7;"><?= h(get_tpl_body($active_tab, $tpl['default_body'])) ?></textarea>
      </div>

      <!-- Aperçu -->
      <details style="margin-top:10px;margin-bottom:14px;">
        <summary style="cursor:pointer;font-size:12px;font-weight:700;color:var(--muted);">👁 Aperçu (avec valeurs de démonstration)</summary>
        <div id="preview-box" style="background:#fafafa;border:1.5px solid #f0f0f0;border-radius:10px;padding:16px;margin-top:8px;font-size:13px;white-space:pre-wrap;line-height:1.7;color:#333;"></div>
      </details>

      <button type="submit" class="btn btn-primary">💾 Enregistrer ce template</button>
    </form>
  </div>
</div>
</div>

<script>
function insertVar(v) {
  var fields = [document.getElementById('tpl-subject'), document.getElementById('tpl-body')];
  var active = document.activeElement;
  var target = fields.includes(active) ? active : document.getElementById('tpl-body');
  var pos = target.selectionStart;
  target.value = target.value.substring(0,pos) + v + target.value.substring(target.selectionEnd);
  target.selectionStart = target.selectionEnd = pos + v.length;
  target.focus();
  updatePreview();
}

var demo = {
  '{NOM}':          'Jean Dupont',
  '{NOM_SITE}':     '<?= h(get_setting('site_name')) ?>',
  '{TELEPHONE}':    '<?= h(get_setting('site_phone')) ?>',
  '{EMAIL_SITE}':   '<?= h(get_setting('site_email')) ?>',
  '{SERVICE}':      'Préparation hydraulique fourches + amortisseur',
  '{DATE}':         '15/07/2026',
  '{HEURE}':        '09:00',
  '{MONTANT}':      '170,00',
  '{LIEN_PAIEMENT}':'https://votresite.fr/payer.php?t=abc123',
  '{LIEN_FACTURE}': 'https://votresite.fr/admin/invoice_pdf.php?id=42',
  '{NUMERO_CMD}':   'CMD-2026-0001',
  '{ARTICLES}':     '• Huile fourche KYB x1 — 24,90 €',
  '{TOTAL}':        '24,90',
  '{MOTIF}':        'Demande de devis',
  '{MESSAGE}':      'Bonjour, j\'aimerais avoir un devis pour ma KTM 2023.',
};

function updatePreview() {
  var body = document.getElementById('tpl-body').value;
  Object.entries(demo).forEach(([k,v]) => { body = body.split(k).join(v); });
  document.getElementById('preview-box').textContent = body;
}

document.getElementById('tpl-body').addEventListener('input', updatePreview);
updatePreview();
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
