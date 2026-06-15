<?php
require_once __DIR__ . '/layout.php';
ensure_tables();

$saved = false;
$link  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name  = trim($_POST['client_name']  ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $amount       = (float)($_POST['amount']    ?? 0);
    $label        = trim($_POST['label']        ?? 'Prestation');
    $note         = trim($_POST['note']         ?? '');
    $create_inv   = !empty($_POST['create_invoice']);

    if ($amount > 0 && $client_email) {
        // Créer ou retrouver le client
        $client_id = null;
        if ($client_email) {
            $sc = db()->prepare('SELECT id FROM kk_clients WHERE email=?');
            $sc->execute([$client_email]);
            $client_id = $sc->fetchColumn() ?: null;
            if (!$client_id) {
                db()->prepare('INSERT INTO kk_clients(name,email,type) VALUES(?,?,?)')->execute([$client_name,$client_email,'particulier']);
                $client_id = (int)db()->lastInsertId();
            }
        }

        // Créer facture si demandé
        $invoice_id = null;
        if ($create_inv) {
            $num   = next_invoice_number('invoice');
            $lines = json_encode([[
                'desc'       => $label,
                'qty'        => 1,
                'unit_price' => round($amount / 1.20, 2), // HT = TTC / 1.20
                'tva'        => 20,
            ]], JSON_UNESCAPED_UNICODE);
            db()->prepare("INSERT INTO kk_invoices(number,type,status,client_id,client_name,client_email,invoice_lines,tva_rate,due_date) VALUES(?,?,?,?,?,?,?,?,?)")
               ->execute([$num,'invoice','sent',$client_id,$client_name,$client_email,$lines,20,date('Y-m-d',strtotime('+30 days'))]);
            $invoice_id = (int)db()->lastInsertId();
        }

        // Créer le lien de paiement
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        db()->prepare('INSERT INTO kk_payment_links(token,invoice_id,amount,expires_at) VALUES(?,?,?,?)')
           ->execute([$token, $invoice_id, $amount, $expires]);

        $link = (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '') . BASE_URL . '/paiement.php?t=' . $token;

        // Envoyer email si demandé
        if (!empty($_POST['send_email']) && $client_email) {
            $sname = get_setting('site_name');
            $subj  = "💳 Lien de paiement — $sname";
            $body  = "Bonjour $client_name,\r\n\r\n"
                   . "$sname vous envoie un lien de paiement sécurisé.\r\n\r\n"
                   . "Montant : " . number_format($amount, 2, ',', ' ') . " €\r\n"
                   . "Libellé : $label\r\n"
                   . ($note ? "Note : $note\r\n" : '')
                   . "\r\nPayer en ligne :\r\n$link\r\n\r\n"
                   . "Ce lien est valable 7 jours.\r\n\r\nCordialement,\r\n$sname\r\n" . get_setting('site_phone');
            mail($client_email, $subj, $body, "From: " . get_setting('site_email'));
        }

        $saved = true;
    }
}

// Historique des liens
$links = db()->query("
    SELECT pl.*, i.number as inv_number, i.client_name, i.client_email
    FROM kk_payment_links pl
    LEFT JOIN kk_invoices i ON pl.invoice_id = i.id
    ORDER BY pl.created_at DESC
    LIMIT 50
")->fetchAll();
?>
<div class="adm-topbar">
  <h1>💳 Lien de paiement rapide</h1>
</div>
<div class="adm-content">
<?php if($saved): ?>
<div class="alert alert-ok" style="margin-bottom:0;">
  ✅ Lien généré <?= !empty($_POST['send_email']) ? 'et envoyé par email' : '' ?>!
  <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <code style="background:#f5f5f3;padding:8px 12px;border-radius:8px;font-size:12px;word-break:break-all;flex:1;"><?= h($link) ?></code>
    <button onclick="navigator.clipboard.writeText('<?= h($link) ?>').then(()=>{this.textContent='✅ Copié!';setTimeout(()=>this.textContent='📋 Copier',2000)})" class="btn btn-primary btn-sm">📋 Copier</button>
    <a href="<?= h($link) ?>" target="_blank" class="btn btn-secondary btn-sm">👁 Voir</a>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">

<!-- FORMULAIRE -->
<div class="card">
  <div class="card-head"><h2><span class="icon">⚡</span> Créer un lien</h2></div>
  <p class="card-hint">Générez un lien de paiement en quelques secondes pour n'importe quel montant, avec ou sans facture associée.</p>
  <form method="POST">

    <div style="background:var(--bg);border-radius:10px;padding:16px;margin-bottom:16px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">💰 Paiement</div>
      <div class="fgrp" style="margin-bottom:10px;">
        <label>Montant TTC (€) *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required autofocus
               placeholder="150.00"
               style="font-size:24px;font-weight:800;border:1.5px solid var(--border);border-radius:10px;padding:12px 16px;width:100%;outline:none;"
               onfocus="this.style.borderColor='var(--red)'" onblur="this.style.borderColor='var(--border)'">
      </div>
      <div class="fgrp">
        <label>Libellé de la prestation *</label>
        <input type="text" name="label" required placeholder="Préparation suspension YZ450F"
               style="border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;width:100%;outline:none;">
      </div>
      <div class="fgrp">
        <label>Note (optionnel)</label>
        <textarea name="note" placeholder="Supplément pièce remplacée, détail de l'intervention…"
                  style="border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;width:100%;min-height:60px;resize:vertical;outline:none;font-family:inherit;"></textarea>
      </div>
    </div>

    <div style="background:var(--bg);border-radius:10px;padding:16px;margin-bottom:16px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">👤 Client</div>
      <div class="fgrp" style="margin-bottom:10px;">
        <label>Email client *</label>
        <input type="email" name="client_email" required placeholder="client@mail.fr"
               style="border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;width:100%;outline:none;"
               list="clients-list">
        <datalist id="clients-list">
          <?php foreach(get_clients() as $cl): ?>
          <option value="<?= h($cl['email']) ?>"><?= h($cl['name']) ?></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="fgrp">
        <label>Nom client</label>
        <input type="text" name="client_name" placeholder="Jean Dupont"
               style="border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;width:100%;outline:none;">
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;">
        <input type="checkbox" name="create_invoice" value="1" checked style="width:16px;height:16px;accent-color:#ed0c0f;">
        <div>
          <div style="font-size:13px;font-weight:700;">🧾 Générer une facture automatiquement</div>
          <div style="font-size:11px;color:var(--muted);">Une facture PDF sera créée et accessible dans Facturation</div>
        </div>
      </label>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;">
        <input type="checkbox" name="send_email" value="1" checked style="width:16px;height:16px;accent-color:#ed0c0f;">
        <div>
          <div style="font-size:13px;font-weight:700;">📧 Envoyer le lien par email</div>
          <div style="font-size:11px;color:var(--muted);">Email envoyé automatiquement au client</div>
        </div>
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
      ⚡ Générer le lien de paiement
    </button>
    <p style="font-size:11px;color:var(--muted);text-align:center;margin-top:8px;">Le lien est valable 7 jours</p>
  </form>
</div>

<!-- HISTORIQUE -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📋</span> Historique des liens</h2></div>
  <?php if(empty($links)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucun lien généré.</p>
  <?php else: ?>
  <div class="item-list">
    <?php foreach($links as $pl):
      $expired = $pl['expires_at'] && $pl['expires_at'] < date('Y-m-d H:i:s');
      $used    = !empty($pl['used_at']);
    ?>
    <div class="item-row">
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:700;"><?= h($pl['client_name'] ?: '—') ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= h($pl['client_email'] ?: '') ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= date('d/m/Y H:i', strtotime($pl['created_at'])) ?></div>
      </div>
      <div style="font-size:14px;font-weight:800;color:var(--red);white-space:nowrap;">
        <?= number_format((float)$pl['amount'], 2, ',', ' ') ?> €
      </div>
      <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:8px;
        background:<?= $used?'#dcfce7':($expired?'#fee2e2':'#fef9c3') ?>;
        color:<?= $used?'#15803d':($expired?'#dc2626':'#854d0e') ?>;">
        <?= $used ? '✅ Payé' : ($expired ? '⏰ Expiré' : '🔗 Actif') ?>
      </span>
      <?php if(!$used && !$expired): ?>
      <button onclick="navigator.clipboard.writeText('<?= (isset($_SERVER['HTTP_HOST'])?'http://'.$_SERVER['HTTP_HOST']:'').BASE_URL ?>/paiement.php?t=<?= h($pl['token']) ?>').then(()=>{this.textContent='✅';setTimeout(()=>this.textContent='📋',1500)})"
              class="btn btn-ghost btn-sm" title="Copier le lien">📋</button>
      <?php endif; ?>
      <?php if($pl['inv_number']): ?>
      <a href="<?= BASE_URL ?>/admin/invoice_pdf.php?id=<?= $pl['invoice_id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Voir la facture">📄</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
