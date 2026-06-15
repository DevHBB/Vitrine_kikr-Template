<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();
$saved = false;

// Changer statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    db()->prepare("UPDATE kk_orders SET status=?,updated_at=NOW() WHERE id=?")
       ->execute([$_POST['status'], (int)$_POST['order_id']]);
    // Email notif si expédié
    if ($_POST['status'] === 'shipped') {
        $s = db()->prepare('SELECT * FROM kk_orders WHERE id=?'); $s->execute([(int)$_POST['order_id']]);
        $ord = $s->fetch();
        if ($ord && $ord['client_email']) {
            $sname = get_setting('site_name');
            mail($ord['client_email'], "📦 Commande expédiée — $sname",
                "Bonjour {$ord['client_name']},\r\n\r\nVotre commande n°{$ord['number']} a été expédiée.\r\n\r\nCordialement,\r\n$sname",
                "From: ".get_setting('site_email'));
        }
    }
    $saved = true;
}

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT o.*, i.number as inv_number FROM kk_orders o LEFT JOIN kk_invoices i ON o.invoice_id=i.id WHERE 1=1";
$p   = [];
if ($status_filter) { $sql .= ' AND o.status=?'; $p[] = $status_filter; }
$sql .= ' ORDER BY o.created_at DESC';
try {
    $st = db()->prepare($sql); $st->execute($p);
    $orders = $st->fetchAll();
} catch(Exception $e) { $orders = []; }

$statuses = [
    'pending'   => ['🟡','En attente',  '#fef9c3','#854d0e'],
    'confirmed' => ['🔵','Confirmé',    '#dbeafe','#1d4ed8'],
    'shipped'   => ['📦','Expédié',     '#f3e8ff','#7c3aed'],
    'delivered' => ['✅','Livré',       '#dcfce7','#15803d'],
    'cancelled' => ['❌','Annulé',      '#fee2e2','#dc2626'],
];

// Stats
try {
    $stats = db()->query("SELECT status, COUNT(*) as cnt, SUM(total) as rev FROM kk_orders GROUP BY status")->fetchAll();
    $stats_map = array_column($stats, null, 'status');
    $total_rev = array_sum(array_column($stats, 'rev'));
    $total_cnt = array_sum(array_column($stats, 'cnt'));
} catch(Exception $e) { $stats_map = []; $total_rev = 0; $total_cnt = 0; }
?>
<div class="adm-topbar">
  <h1>📋 Commandes boutique</h1>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <a href="?" class="btn <?= !$status_filter?'btn-dark':'btn-ghost' ?> btn-sm">Toutes (<?= $total_cnt ?>)</a>
    <?php foreach($statuses as $k=>[$ico,$lbl]): ?>
    <a href="?status=<?= $k ?>" class="btn <?= $status_filter===$k?'btn-primary':'btn-ghost' ?> btn-sm"><?= $ico ?> <?= $lbl ?> (<?= (int)($stats_map[$k]['cnt']??0) ?>)</a>
    <?php endforeach; ?>
  </div>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Statut mis à jour.</div><?php endif; ?>

<!-- Stats rapides -->
<div class="stat-grid" style="margin-bottom:16px;">
  <div class="stat-card"><div class="stat-val"><?= $total_cnt ?></div><div class="stat-lbl">Commandes totales</div></div>
  <div class="stat-card"><div class="stat-val red"><?= number_format($total_rev,0,',',' ') ?> €</div><div class="stat-lbl">CA total</div></div>
  <div class="stat-card"><div class="stat-val"><?= (int)($stats_map['pending']['cnt']??0) ?></div><div class="stat-lbl">En attente</div></div>
  <div class="stat-card"><div class="stat-val"><?= (int)($stats_map['delivered']['cnt']??0) ?></div><div class="stat-lbl">Livrées</div></div>
</div>

<!-- Liste commandes -->
<div class="card">
  <?php if(empty($orders)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:32px;">
    <span style="font-size:40px;display:block;margin-bottom:12px;">📭</span>
    Aucune commande<?= $status_filter?' avec ce statut':'' ?>.
  </p>
  <?php else: ?>
  <div class="item-list">
    <?php foreach($orders as $ord):
      [$ico,$lbl,$bg,$fg] = $statuses[$ord['status']] ?? ['?','','#f5f5f3','#555'];
      $items = jd($ord['items']??'[]',[]);
    ?>
    <div class="item-row">
      <!-- Numéro & date -->
      <div style="flex-shrink:0;min-width:100px;">
        <div style="font-size:12px;font-weight:800;color:var(--muted);"><?= h($ord['number']) ?></div>
        <div style="font-size:10px;color:#bbb;margin-top:1px;"><?= date('d/m/Y H:i',strtotime($ord['created_at'])) ?></div>
      </div>
      <!-- Client -->
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:700;"><?= h($ord['client_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= h($ord['client_email']) ?></div>
        <!-- Articles -->
        <div style="font-size:11px;color:#aaa;margin-top:2px;">
          <?= implode(' · ', array_map(fn($i) => h($i['name']).' ×'.$i['qty'], array_slice($items,0,3))) ?>
          <?= count($items)>3?'…':'' ?>
        </div>
      </div>
      <!-- Total -->
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:16px;font-weight:900;color:var(--red);"><?= number_format((float)$ord['total'],2,',','') ?> €</div>
        <div style="font-size:10px;color:#aaa;"><?= h($ord['payment_method']) ?></div>
      </div>
      <!-- Statut -->
      <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;">
        <?= $ico ?> <?= $lbl ?>
      </span>
      <!-- Actions -->
      <div class="item-row-actions">
        <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
          <input type="hidden" name="update_status" value="1">
          <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
          <select name="status" style="border:1.5px solid var(--border);border-radius:7px;padding:5px 6px;font-size:11px;font-family:inherit;">
            <?php foreach($statuses as $k=>[$i,$l]): ?>
            <option value="<?= $k ?>" <?= $ord['status']===$k?'selected':''?>><?= $i.' '.$l ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">OK</button>
        </form>
        <?php if($ord['invoice_id']): ?>
        <a href="<?= BASE_URL ?>/admin/invoice_pdf.php?id=<?= $ord['invoice_id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Facture PDF">📄</a>
        <?php endif; ?>
        <?php if($ord['client_email']): ?>
        <a href="mailto:<?= h($ord['client_email']) ?>?subject=Commande <?= h($ord['number']) ?>" class="btn btn-ghost btn-sm" title="Contacter le client">📧</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
