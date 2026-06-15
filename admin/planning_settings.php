<?php
require_once __DIR__ . '/layout.php';
ensure_tables();

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'rdv_mode','rdv_public','rdv_show_slots',
        'slot_mode','slot_morning_time','slot_interval',
        'notif_email','notif_sms','sms_api_key','sms_sender',
        'pro_priority','days_open','time_open','time_close',
        'rdv_delay_days','max_per_day',
    ];
    // days_open est un tableau de cases à cocher
    $_POST['days_open'] = implode(',', array_keys(array_filter($_POST['days_open_arr'] ?? [])));

    foreach ($fields as $k) {
        set_ps($k, trim($_POST[$k] ?? ''));
    }
    // Paiement RDV
    set_setting('payment_rdv_enabled', isset($_POST['payment_rdv_enabled'])?'1':'0');
    set_ps('payment_rdv_mode',           trim($_POST['payment_rdv_mode']          ?? 'choice'));
    set_ps('payment_rdv_acompte',        isset($_POST['payment_rdv_acompte'])?'1':'0');
    set_ps('payment_rdv_acompte_amount', trim($_POST['payment_rdv_acompte_amount']?? '50'));
    set_ps('payment_rdv_message',        trim($_POST['payment_rdv_message']        ?? ''));

    // Upload fiche PDF
    if (!empty($_FILES['pdf_fiche']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['pdf_fiche']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $dir = __DIR__ . '/../img/fiches';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'fiche_intervention.pdf';
            if (move_uploaded_file($_FILES['pdf_fiche']['tmp_name'], $dir . '/' . $fname)) {
                set_ps('pdf_fiche_url', BASE_URL . '/img/fiches/' . $fname);
            }
        }
    }
    $saved = true;
}

$days_open_arr = array_map('intval', explode(',', ps('days_open','1,2,3,4,5')));
$day_names = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
?>
<div class="adm-topbar">
  <h1>⚙️ Paramètres du planning</h1>
  <a href="<?= BASE_URL ?>/admin/planning.php" class="btn btn-secondary btn-sm">← Retour planning</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Paramètres enregistrés.</div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<!-- Mode de réservation -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📅</span> Mode de prise de rendez-vous</h2></div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
    <?php foreach([
      'request' => ['📬','Demande simple','Le client envoie une demande, vous le rappelez et placez manuellement'],
      'direct'  => ['🗓️','Réservation directe','Le client choisit son créneau sur le calendrier en ligne'],
      'hybrid'  => ['⚡','Hybride','Particuliers = demande, Pros = accès direct'],
    ] as $k=>[$ico,$lbl,$desc]): ?>
    <label style="padding:14px;border:2px solid <?= ps('rdv_mode')===$k?'var(--red)':'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= ps('rdv_mode')===$k?'#fef2f2':'var(--bg)' ?>;">
      <input type="radio" name="rdv_mode" value="<?= $k ?>" <?= ps('rdv_mode')===$k?'checked':'' ?> style="display:none;">
      <div style="font-size:22px;margin-bottom:6px;"><?= $ico ?></div>
      <div style="font-size:13px;font-weight:700;margin-bottom:4px;"><?= $lbl ?></div>
      <div style="font-size:11px;color:var(--muted);"><?= $desc ?></div>
    </label>
    <?php endforeach; ?>
  </div>
</div>

<!-- Affichage public -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🌐</span> Affichage du planning public</h2></div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
    <?php foreach([
      'hidden'  => ['🔒','Masqué','Planning non visible, redirigé vers Contact'],
      'partial' => ['🟡','Dispo / Complet','Affiche juste si un jour est dispo ou plein'],
      'full'    => ['📅','Complet','Affiche Libre / Partiel / Complet / Fermé'],
    ] as $k=>[$ico,$lbl,$desc]): ?>
    <label style="padding:14px;border:2px solid <?= ps('rdv_public')===$k?'var(--red)':'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= ps('rdv_public')===$k?'#fef2f2':'var(--bg)' ?>;">
      <input type="radio" name="rdv_public" value="<?= $k ?>" <?= ps('rdv_public')===$k?'checked':'' ?> style="display:none;">
      <div style="font-size:22px;margin-bottom:6px;"><?= $ico ?></div>
      <div style="font-size:13px;font-weight:700;margin-bottom:4px;"><?= $lbl ?></div>
      <div style="font-size:11px;color:var(--muted);"><?= $desc ?></div>
    </label>
    <?php endforeach; ?>
  </div>
</div>

<!-- Horaires et capacité -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🕐</span> Horaires & capacité</h2></div>
  <div class="g2">
    <div class="fgrp">
      <label>Jours d'ouverture</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
        <?php foreach($day_names as $n=>$dn): ?>
        <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;padding:5px 10px;border:1.5px solid <?= in_array($n,$days_open_arr)?'var(--red)':'var(--border)' ?>;border-radius:8px;background:<?= in_array($n,$days_open_arr)?'#fef2f2':'var(--bg)' ?>;">
          <input type="checkbox" name="days_open_arr[<?= $n ?>]" <?= in_array($n,$days_open_arr)?'checked':'' ?> style="accent-color:#ed0c0f;">
          <?= $dn ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div class="fgrp"><label>Heure d'ouverture</label><input type="time" name="time_open" value="<?= h(ps('time_open','09:00')) ?>"></div>
      <div class="fgrp"><label>Heure de fermeture</label><input type="time" name="time_close" value="<?= h(ps('time_close','18:00')) ?>"></div>
    </div>
    <div class="fgrp">
      <label>Nb max de dépôts par jour</label>
      <input type="number" name="max_per_day" value="<?= h(ps('max_per_day','3')) ?>" min="1" max="20">
      <span class="hint">Au-delà, le jour apparaît "Complet"</span>
    </div>
    <div class="fgrp">
      <label>Délai minimum avant RDV (jours)</label>
      <input type="number" name="rdv_delay_days" value="<?= h(ps('rdv_delay_days','1')) ?>" min="0" max="30">
      <span class="hint">0 = même jour possible, 1 = à partir de demain</span>
    </div>
  </div>

  <hr class="sep">
  <div class="g2">
    <div class="fgrp">
      <label>Mode des créneaux de dépôt</label>
      <select name="slot_mode">
        <option value="manual"  <?= ps('slot_mode')==='manual'?'selected':'' ?>>Au cas par cas (vous placez manuellement)</option>
        <option value="morning" <?= ps('slot_mode')==='morning'?'selected':'' ?>>Dépôt le matin uniquement</option>
        <option value="interval"<?= ps('slot_mode')==='interval'?'selected':'' ?>>Créneaux toutes les X minutes</option>
      </select>
    </div>
    <div class="fgrp">
      <label>Heure de dépôt matin (si mode matin)</label>
      <input type="time" name="slot_morning_time" value="<?= h(ps('slot_morning_time','09:00')) ?>">
    </div>
    <div class="fgrp">
      <label>Intervalle entre créneaux (minutes)</label>
      <input type="number" name="slot_interval" value="<?= h(ps('slot_interval','120')) ?>" min="30" max="480" step="30">
    </div>
  </div>
</div>

<!-- Paiement RDV -->
<div class="card">
  <div class="card-head"><h2><span class="icon">💳</span> Paiement lors de la prise de RDV</h2></div>
  <div style="display:flex;flex-direction:column;gap:10px;">

    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= get_setting('payment_rdv_enabled','0')==='1'?'var(--red)':'var(--border)' ?>;border-radius:12px;cursor:pointer;background:<?= get_setting('payment_rdv_enabled','0')==='1'?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="payment_rdv_enabled" <?= get_setting('payment_rdv_enabled','0')==='1'?'checked':'' ?> style="width:18px;height:18px;accent-color:#ed0c0f;">
      <div>
        <div style="font-size:13px;font-weight:700;">✅ Activer le paiement lors de la prise de RDV</div>
        <div style="font-size:11px;color:var(--muted);">Affiche les options de paiement sur la page de RDV</div>
      </div>
    </label>

    <div style="padding:14px;border:1.5px solid var(--border);border-radius:12px;background:var(--bg);">
      <div style="font-size:12px;font-weight:700;margin-bottom:10px;">Quand le client doit-il payer ?</div>
      <?php foreach([
        'now'     => ['💳', 'Paiement immédiat obligatoire', 'Le client doit payer pour valider sa demande'],
        'choice'  => ['🔄', 'Au choix du client',            'Le client choisit de payer maintenant ou lors du dépôt'],
        'confirm' => ['📬', 'À la confirmation',              "Lien de paiement envoyé automatiquement quand vous confirmez le RDV"],
        'deposit' => ['🤝', 'Lors du dépôt uniquement',       'Pas de paiement en ligne, règlement sur place'],
      ] as $k=>[$ico,$lbl,$desc]): ?>
      <label style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;cursor:pointer;margin-bottom:4px;background:<?= ps('payment_rdv_mode','choice')===$k?'white':'transparent' ?>;<?= ps('payment_rdv_mode','choice')===$k?'box-shadow:0 1px 4px rgba(0,0,0,.06);':'' ?>">
        <input type="radio" name="payment_rdv_mode" value="<?= $k ?>" <?= ps('payment_rdv_mode','choice')===$k?'checked':'' ?> style="accent-color:#ed0c0f;">
        <div>
          <div style="font-size:13px;font-weight:700;"><?= $ico ?> <?= $lbl ?></div>
          <div style="font-size:11px;color:var(--muted);"><?= $desc ?></div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= ps('payment_rdv_acompte','0')==='1'?'var(--red)':'var(--border)' ?>;border-radius:12px;cursor:pointer;background:<?= ps('payment_rdv_acompte','0')==='1'?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="payment_rdv_acompte" <?= ps('payment_rdv_acompte','0')==='1'?'checked':'' ?> style="width:18px;height:18px;accent-color:#ed0c0f;">
      <div>
        <div style="font-size:13px;font-weight:700;">💶 Demander un acompte</div>
        <div style="font-size:11px;color:var(--muted);">Montant ou % à préciser — le solde est payé à la livraison</div>
      </div>
    </label>

    <div class="g2">
      <div class="fgrp">
        <label>Montant acompte (€) ou %</label>
        <input type="text" name="payment_rdv_acompte_amount" value="<?= h(ps('payment_rdv_acompte_amount','50')) ?>" placeholder="50 ou 30%">
        <span class="hint">Ex: "50" = 50€ fixe / "30%" = 30% du devis</span>
      </div>
      <div class="fgrp">
        <label>Message affiché au client</label>
        <input type="text" name="payment_rdv_message" value="<?= h(ps('payment_rdv_message','Un acompte est demandé pour confirmer votre rendez-vous.')) ?>">
      </div>
    </div>
  </div>
</div>

<!-- Notifications -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🔔</span> Notifications</h2></div>
  <div class="g2">
    <div class="fgrp">
      <label>Email automatique</label>
      <select name="notif_email">
        <option value="1" <?= ps('notif_email')==='1'?'selected':'' ?>>✅ Activé</option>
        <option value="0" <?= ps('notif_email')==='0'?'selected':'' ?>>❌ Désactivé</option>
      </select>
    </div>
    <div class="fgrp">
      <label>SMS automatique</label>
      <select name="notif_sms">
        <option value="0" <?= ps('notif_sms')==='0'?'selected':'' ?>>❌ Désactivé</option>
        <option value="1" <?= ps('notif_sms')==='1'?'selected':'' ?>>✅ Activé (nécessite clé API)</option>
      </select>
    </div>
    <div class="fgrp">
      <label>Clé API SMS (OVH / Twilio…)</label>
      <input type="text" name="sms_api_key" value="<?= h(ps('sms_api_key','')) ?>" placeholder="Votre clé API">
    </div>
    <div class="fgrp">
      <label>Nom expéditeur SMS</label>
      <input type="text" name="sms_sender" value="<?= h(ps('sms_sender',"Kik'r")) ?>" maxlength="11">
    </div>
  </div>
</div>

<!-- Pilotes sponsorisés -->
<div class="card">
  <div class="card-head"><h2><span class="icon">⭐</span> Pilotes sponsorisés / Contrat</h2></div>
  <div class="fgrp">
    <label>Traitement prioritaire des clients PRO</label>
    <select name="pro_priority">
      <option value="1" <?= ps('pro_priority')==='1'?'selected':'' ?>>✅ Oui — les RDV pros remontent en tête</option>
      <option value="0" <?= ps('pro_priority')==='0'?'selected':'' ?>>❌ Non — traitement identique</option>
    </select>
  </div>
</div>

<!-- Fiche PDF -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📄</span> Fiche d'intervention (PDF)</h2></div>
  <p class="card-hint">Uploadez votre fiche PDF vierge. Elle sera téléchargeable par les clients sur la page planning.</p>
  <?php if(ps('pdf_fiche_url')): ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;padding:12px;background:var(--bg);border-radius:8px;">
    <svg width="24" height="24" fill="none" stroke="#ed0c0f" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <div style="flex:1;font-size:12px;font-weight:600;">Fiche actuelle</div>
    <a href="<?= h(ps('pdf_fiche_url')) ?>" target="_blank" class="btn btn-secondary btn-sm">👁 Voir</a>
  </div>
  <?php endif; ?>
  <div class="fgrp">
    <label>Uploader une nouvelle fiche (PDF)</label>
    <input type="file" name="pdf_fiche" accept=".pdf">
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer tous les paramètres</button>
</form>
</div>

<script>
// Highlight radio au clic
document.querySelectorAll('input[type="radio"]').forEach(r => {
  r.addEventListener('change', () => {
    r.closest('label').parentElement.querySelectorAll('label').forEach(l => {
      const inp = l.querySelector('input');
      l.style.borderColor = inp.checked ? 'var(--red)' : 'var(--border)';
      l.style.background  = inp.checked ? '#fef2f2'   : 'var(--bg)';
    });
  });
});
// Highlight checkbox jours
document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
  cb.addEventListener('change', () => {
    const lbl = cb.closest('label');
    lbl.style.borderColor = cb.checked ? 'var(--red)' : 'var(--border)';
    lbl.style.background  = cb.checked ? '#fef2f2'   : 'var(--bg)';
  });
});
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
