<?php
require_once __DIR__ . '/layout.php';
ensure_tables();

$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.BASE_URL.'/admin/planning.php'); exit; }

$s = db()->prepare('SELECT * FROM kk_appointments WHERE id=?');
$s->execute([$id]);
$rdv = $s->fetch();
if (!$rdv) { header('Location: '.BASE_URL.'/admin/planning.php'); exit; }

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Actions AJAX prix
if (in_array($action, ['save_prices','gen_pay_link'])) {
    header('Content-Type: application/json');
    $rid = (int)($_POST['id'] ?? 0);
    if ($action === 'save_prices') {
        $est = $_POST['price_estimate'] !== '' ? (float)$_POST['price_estimate'] : null;
        $fin = $_POST['price_final']    !== '' ? (float)$_POST['price_final']    : null;
        $note = trim($_POST['price_note'] ?? '');
        db()->prepare("UPDATE kk_appointments SET price_estimate=?,price_final=?,price_note=? WHERE id=?")
           ->execute([$est, $fin, $note, $rid]);
        // Notif email client si prix changé
        $rs = db()->prepare('SELECT * FROM kk_appointments WHERE id=?'); $rs->execute([$rid]);
        $rdv2 = $rs->fetch();
        echo json_encode(['ok'=>true]);
    } elseif ($action === 'gen_pay_link') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $link = create_rdv_payment_link($rid, $amount);
            // Notif email
            $rs2 = db()->prepare('SELECT * FROM kk_appointments WHERE id=?'); $rs2->execute([$rid]);
            $rdv3 = $rs2->fetch();
            if ($rdv3 && ps('notif_email','1') === '1') {
                $sname = get_setting('site_name');
                mail($rdv3['client_email'],
                    "💳 Lien de paiement — $sname",
                    "Bonjour {$rdv3['client_name']},\r\n\r\n"
                    . "Voici votre lien de paiement sécurisé :\r\n\r\n$link\r\n\r\n"
                    . "Montant : " . number_format($amount, 2, ',', ' ') . " €\r\n\r\n"
                    . "Cordialement,\r\n$sname\r\n" . get_setting('site_phone'),
                    "From: " . get_setting('site_email'));
            }
            echo json_encode(['ok'=>true, 'link'=>$link]);
        } else {
            echo json_encode(['ok'=>false, 'error'=>'Montant invalide']);
        }
    }
    exit;
}

if ($action === 'save_notes') {
        $old_status  = $rdv['status'];
        $new_status  = $_POST['status'];
        $new_date    = $_POST['slot_date'] ?: null;
        $new_time    = $_POST['slot_time'] ?: null;
        $notes_admin = trim($_POST['notes_admin'] ?? '');

        db()->prepare("UPDATE kk_appointments SET notes_admin=?,status=?,slot_date=?,slot_time=?,updated_at=NOW() WHERE id=?")
           ->execute([$notes_admin, $new_status, $new_date, $new_time, $id]);

        $from = "From: " . get_setting('site_email');

        // ---- Statut → Confirmé ----
        if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
            if (ps('notif_email','1') === '1') {
                $date_fmt = $new_date ? date('d/m/Y', strtotime($new_date)) : 'à définir';
                $time_fmt = $new_time ? ' à ' . substr($new_time, 0, 5) : '';
                $subj = "✅ RDV confirmé — Kik'r Suspension";
                $body = "Bonjour {$rdv['client_name']},\r\n\r\n"
                    . "Votre rendez-vous est CONFIRMÉ.\r\n\r\n"
                    . "📅 Date : $date_fmt$time_fmt\r\n"
                    . "🏍️ Moto : {$rdv['moto_marque']} {$rdv['moto_modele']}\r\n"
                    . "🔧 Prestation : {$rdv['service_label']}\r\n\r\n"
                    . "Vous pouvez amener votre moto à la date indiquée.\r\n"
                    . ($notes_admin ? "\r\nNote : $notes_admin\r\n" : "")
                    . "\r\nCordialement,\r\nKik'r Suspension\r\n" . get_setting('site_phone');
                mail($rdv['client_email'], $subj, $body, $from);
            }
        }

        // ---- Statut → Annulé ----
        if ($new_status === 'cancelled' && $old_status !== 'cancelled') {
            if (ps('notif_email','1') === '1') {
                $subj = "❌ Demande de RDV — Kik'r Suspension";
                $body = "Bonjour {$rdv['client_name']},\r\n\r\n"
                    . "Nous sommes désolés, nous ne pouvons pas donner suite à votre demande de rendez-vous"
                    . ($new_date ? " du " . date('d/m/Y', strtotime($new_date)) : "")
                    . " pour le moment.\r\n\r\n"
                    . ($notes_admin ? "Raison : $notes_admin\r\n\r\n" : "")
                    . "N'hésitez pas à nous recontacter pour trouver un autre créneau.\r\n\r\n"
                    . "Cordialement,\r\nKik'r Suspension\r\n" . get_setting('site_phone');
                mail($rdv['client_email'], $subj, $body, $from);
            }
        }

        // ---- Statut → Prêt ----
        if ($new_status === 'ready' && $old_status !== 'ready') {
            if (ps('notif_email','1') === '1') {
                $subj = "🏍️ Votre moto est prête — Kik'r Suspension";
                $body = "Bonjour {$rdv['client_name']},\r\n\r\n"
                    . "Votre moto est prête à être récupérée !\r\n"
                    . "Merci de nous appeler avant de venir.\r\n\r\n"
                    . "Cordialement,\r\nKik'r Suspension\r\n" . get_setting('site_phone');
                mail($rdv['client_email'], $subj, $body, $from);
            }
        }

        $s->execute([$id]); $rdv = $s->fetch();
        $saved = true;
    }
}

$statuses = [
    'pending'    => ['🟡','En attente'],
    'confirmed'  => ['🔵','Confirmé'],
    'in_progress'=> ['🟠','En cours'],
    'ready'      => ['🟢','Prêt à récupérer'],
    'collected'  => ['✅','Récupéré'],
    'cancelled'  => ['❌','Annulé'],
];
?>
<div class="adm-topbar">
  <h1>RDV #<?= $id ?> — <?= h($rdv['client_name']) ?></h1>
  <div style="display:flex;gap:8px;">
    <a href="<?= BASE_URL ?>/admin/planning.php" class="btn btn-secondary btn-sm">← Retour</a>
    <?php if($rdv['status'] !== 'ready'): ?>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="action" value="save_notes">
      <input type="hidden" name="notes_admin" value="<?= h($rdv['notes_admin']) ?>">
      <input type="hidden" name="status" value="ready">
      <input type="hidden" name="slot_date" value="<?= h($rdv['slot_date']) ?>">
      <input type="hidden" name="slot_time" value="<?= h($rdv['slot_time']) ?>">
      <button type="submit" class="btn btn-primary btn-sm" style="background:#16a34a;">🟢 Moto prête — Notifier</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

  <!-- Infos client + moto -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">👤</span> Client</h2></div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <?php $rows=[
        ['Nom',      $rdv['client_name']],
        ['Email',    $rdv['client_email']],
        ['Téléphone',$rdv['client_phone']],
        ['Type',     $rdv['client_type']==='pro'?'⭐ Pilote sponsorisé':'Particulier'],
        ['Moto',     trim($rdv['moto_marque'].' '.$rdv['moto_modele'].' '.$rdv['moto_annee'])],
        ['Prestation',$rdv['service_label']],
        ['Demandé le',date('d/m/Y H:i',strtotime($rdv['created_at']))],
      ]; foreach($rows as [$k,$v]): ?>
      <tr style="border-bottom:1px solid var(--border);">
        <td style="padding:8px 0;font-weight:600;color:var(--muted);width:40%;"><?= h($k) ?></td>
        <td style="padding:8px 0;"><?= h($v) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if($rdv['client_email']): ?>
      <tr><td colspan="2" style="padding:10px 0;">
        <a href="mailto:<?= h($rdv['client_email']) ?>" class="btn btn-secondary btn-sm">✉️ Envoyer un email</a>
        <a href="tel:<?= h(preg_replace('/\s/','',$rdv['client_phone'])) ?>" class="btn btn-secondary btn-sm">📞 Appeler</a>
      </td></tr>
      <?php endif; ?>
    </table>
    <?php if($rdv['notes_client']): ?>
    <div style="margin-top:12px;background:var(--bg);border-radius:8px;padding:12px;font-size:13px;color:#555;">
      <strong style="display:block;margin-bottom:4px;font-size:11px;text-transform:uppercase;color:var(--muted);">Message client</strong>
      <?= nl2br(h($rdv['notes_client'])) ?>
    </div>
    <?php endif; ?>
    <?php if($rdv['pdf_fiche']): ?>
    <div style="margin-top:12px;">
      <a href="<?= h($rdv['pdf_fiche']) ?>" target="_blank" class="btn btn-dark btn-sm">📄 Fiche d'intervention (PDF)</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Prix & Paiement -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">💰</span> Prix & Paiement</h2></div>
    <?php
    $rdv_price_est  = $rdv['price_estimate'] ?? null;
    $rdv_price_fin  = $rdv['price_final']    ?? null;
    $rdv_pay_status = $rdv['payment_status'] ?? 'none';
    $rdv_pay_token  = $rdv['payment_link_token'] ?? '';
    $pay_statuses   = ['none'=>'—','link_sent'=>'🔗 Lien envoyé','partial'=>'🟡 Partiel','paid'=>'✅ Payé'];
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
      <div class="fgrp">
        <label>Estimation initiale (€)</label>
        <input type="number" id="est-price" value="<?= $rdv_price_est !== null ? number_format((float)$rdv_price_est,2,'.',''): '' ?>" step="0.01" min="0" placeholder="Ex: 170.00" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;width:100%;">
        <span class="hint">Communiquée au client avant intervention</span>
      </div>
      <div class="fgrp">
        <label>Prix final (€)</label>
        <input type="number" id="fin-price" value="<?= $rdv_price_fin !== null ? number_format((float)$rdv_price_fin,2,'.',''): '' ?>" step="0.01" min="0" placeholder="Après intervention" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;width:100%;">
        <span class="hint">Modifiable à tout moment</span>
      </div>
    </div>
    <div class="fgrp" style="margin-bottom:16px;">
      <label>Note sur le prix</label>
      <input type="text" id="price-note" value="<?= h($rdv['price_note']??'') ?>" placeholder="Ex: Pièce supplémentaire remplacée, supplément…" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;width:100%;">
    </div>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
      <span style="font-size:12px;color:var(--muted);">Paiement : <strong><?= $pay_statuses[$rdv_pay_status] ?></strong></span>
      <button type="button" class="btn btn-secondary btn-sm" onclick="savePrices()">💾 Sauver les prix</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="genPayLink()" id="btn-paylink">
        💳 Générer un lien de paiement
      </button>
    </div>
    <?php if($rdv_pay_token): ?>
    <div style="background:var(--bg);border-radius:8px;padding:12px;font-size:12px;">
      <strong>Lien actif :</strong><br>
      <span style="word-break:break-all;color:#1d4ed8;"><?= BASE_URL ?>/payer.php?t=<?= h($rdv_pay_token) ?></span>
      <button type="button" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/payer.php?t=<?= h($rdv_pay_token) ?>').then(()=>{this.textContent='✅ Copié!';setTimeout(()=>this.textContent='📋 Copier',2000)})" class="btn btn-ghost btn-sm" style="margin-top:6px;">📋 Copier</button>
      <a href="mailto:<?= h($rdv['client_email']) ?>?subject=Lien de paiement — Kik'r&body=Bonjour <?= h($rdv['client_name']) ?>,%0A%0AVoici votre lien de paiement :%0A<?= BASE_URL ?>/payer.php?t=<?= h($rdv_pay_token) ?>" class="btn btn-dark btn-sm" style="margin-top:6px;margin-left:4px;">📧 Envoyer par email</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Gestion admin -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">🔧</span> Gestion</h2></div>
    <form method="POST">
      <input type="hidden" name="action" value="save_notes">
      <div class="fgrp" style="margin-bottom:12px;">
        <label>Statut</label>
        <select name="status" style="font-size:14px;">
          <?php foreach($statuses as $k=>[$ico,$lbl]): ?>
          <option value="<?= $k ?>" <?= $rdv['status']===$k?'selected':'' ?>><?= $ico ?> <?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
        <div class="fgrp">
          <label>Date de dépôt</label>
          <input type="date" name="slot_date" value="<?= h($rdv['slot_date']) ?>">
        </div>
        <div class="fgrp">
          <label>Heure</label>
          <select name="slot_time">
            <option value="">—</option>
            <?php for($h=8;$h<=17;$h++): ?>
            <option value="<?= sprintf('%02d:00',$h) ?>" <?= substr($rdv['slot_time'],0,5)===sprintf('%02d:00',$h)?'selected':'' ?>><?= sprintf('%02d:00',$h) ?></option>
            <option value="<?= sprintf('%02d:30',$h) ?>" <?= substr($rdv['slot_time'],0,5)===sprintf('%02d:30',$h)?'selected':'' ?>><?= sprintf('%02d:30',$h) ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="fgrp" style="margin-bottom:14px;">
        <label>Notes internes (non visibles par le client)</label>
        <textarea name="notes_admin" style="min-height:100px;"><?= h($rdv['notes_admin']) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-block">💾 Enregistrer</button>
    </form>
  </div>
</div>
</div>
<script>
function savePrices() {
    var fd = new FormData();
    fd.append('action', 'save_prices');
    fd.append('id', '<?= $id ?>');
    fd.append('price_estimate', document.getElementById('est-price').value);
    fd.append('price_final',    document.getElementById('fin-price').value);
    fd.append('price_note',     document.getElementById('price-note').value);
    fetch('', {method:'POST', body:fd})
      .then(r => r.json())
      .then(d => {
        if(d.ok) {
          var btn = document.querySelector('[onclick="savePrices()"]');
          btn.textContent = '✅ Sauvé !';
          setTimeout(() => btn.textContent = '💾 Sauver les prix', 2000);
        }
      });
}
function genPayLink() {
    var amount = document.getElementById('fin-price').value || document.getElementById('est-price').value;
    if (!amount) { amount = prompt('Montant à payer (€) :'); }
    if (!amount) return;
    var fd = new FormData();
    fd.append('action',  'gen_pay_link');
    fd.append('id',      '<?= $id ?>');
    fd.append('amount',  amount);
    fetch('', {method:'POST', body:fd})
      .then(r => r.json())
      .then(d => {
        if (d.link) {
          navigator.clipboard.writeText(d.link);
          alert('✅ Lien généré et copié !\n\n' + d.link);
          location.reload();
        }
      });
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
