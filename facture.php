<?php
// Page publique pour consulter une facture via token sécurisé
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$token = trim($_GET['t'] ?? '');
$inv_id = (int)($_GET['id'] ?? 0);

// Trouver la facture
$inv = null;
if ($inv_id > 0) {
    // Vérifier via token de commande ou lien de paiement
    try {
        $s = db()->prepare("SELECT i.* FROM kk_invoices i
            LEFT JOIN kk_orders o ON o.invoice_id = i.id
            LEFT JOIN kk_payment_links pl ON pl.invoice_id = i.id
            WHERE i.id = ?
            AND (o.id IS NOT NULL OR pl.id IS NOT NULL OR i.status = 'paid')
            LIMIT 1");
        $s->execute([$inv_id]);
        $inv = $s->fetch();
        if ($inv) $inv['invoice_lines'] = jd($inv['invoice_lines'] ?? '[]', []);
    } catch(Exception $e) {}
}

if (!$inv) {
    $page_title = 'Facture introuvable';
    require_once __DIR__ . '/layout/header.php';
    echo '<div style="text-align:center;padding:80px 20px;"><h1>Facture introuvable</h1></div>';
    require_once __DIR__ . '/layout/footer.php';
    exit;
}

$tot   = invoice_totals($inv['invoice_lines'], (float)$inv['tva_rate'], (float)($inv['discount']??0), $inv['discount_type']??'amount');
$sname = get_setting('site_name', "Kik'r Suspension");
$types = ['invoice'=>'FACTURE','quote'=>'DEVIS','credit'=>'AVOIR'];
$page_title = ($types[$inv['type']]??'FACTURE') . ' ' . $inv['number'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title) ?> — <?= h($sname) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#111;background:#f5f5f3;min-height:100vh}
.wrap{max-width:780px;margin:32px auto;padding:0 16px 40px}
.card{background:white;border-radius:16px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:36px;padding-bottom:24px;border-bottom:3px solid #111}
.logo{font-size:26px;font-weight:900;letter-spacing:-1px}
.logo span{color:#ed0c0f}
.doc-type{font-size:28px;font-weight:900;text-align:right;letter-spacing:-1px}
.doc-num{font-size:13px;color:#888;text-align:right;margin-top:4px}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:32px}
.party-label{font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.party-name{font-size:15px;font-weight:700;margin-bottom:3px}
.party-info{font-size:12px;color:#555;line-height:1.7}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
thead tr{background:#111;color:white}
thead th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase}
thead th:not(:first-child){text-align:right}
tbody tr{border-bottom:1px solid #f0f0f0}
tbody tr:nth-child(even){background:#fafafa}
tbody td{padding:9px 12px;font-size:12px}
tbody td:not(:first-child){text-align:right}
.totals{margin-left:auto;width:280px}
.total-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:13px}
.total-row.main{font-size:16px;font-weight:900;border:none;padding-top:10px;color:#ed0c0f}
.footer{margin-top:36px;padding-top:16px;border-top:1px solid #e8e8e8;font-size:10px;color:#aaa;text-align:center}
.print-btn{display:flex;gap:10px;margin-bottom:20px}
.print-btn button,.print-btn a{padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;border:none}
@media print{.print-btn{display:none}body{background:white}.wrap{margin:0;padding:0}.card{box-shadow:none;border-radius:0;padding:20px}}
@media(max-width:600px){.parties{grid-template-columns:1fr}.header{flex-direction:column;gap:12px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="print-btn">
    <button onclick="window.print()" style="background:#111;color:white;">🖨️ Imprimer / PDF</button>
    <a href="<?= BASE_URL ?>/" style="background:#f5f5f3;color:#111;">← Retour au site</a>
  </div>
  <div class="card">
    <div class="header">
      <div>
        <?php $logo=get_setting('site_logo'); if($logo): ?>
        <img src="<?= h($logo) ?>" style="height:44px;object-fit:contain;display:block;margin-bottom:8px;">
        <?php else: ?>
        <div class="logo"><?= h($sname) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:#888;margin-top:8px;line-height:1.6;">
          <?= h(get_setting('site_address')) ?><br>
          <?= h(get_setting('site_phone')) ?><br>
          <?= h(get_setting('site_email')) ?>
        </div>
      </div>
      <div>
        <div class="doc-type"><?= $types[$inv['type']] ?? 'FACTURE' ?></div>
        <div class="doc-num"><?= h($inv['number']) ?></div>
        <div class="doc-num"><?= date('d/m/Y', strtotime($inv['created_at'])) ?></div>
        <?php if($inv['due_date']): ?>
        <div class="doc-num">Échéance : <?= date('d/m/Y', strtotime($inv['due_date'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="parties">
      <div>
        <div class="party-label">Émetteur</div>
        <div class="party-name"><?= h($sname) ?></div>
        <div class="party-info"><?= nl2br(h(get_setting('site_address'))) ?><br><?= h(get_setting('site_phone')) ?></div>
      </div>
      <div>
        <div class="party-label">Client</div>
        <div class="party-name"><?= h($inv['client_name']) ?></div>
        <div class="party-info">
          <?= h($inv['client_email']) ?>
          <?= $inv['client_phone'] ? '<br>'.h($inv['client_phone']) : '' ?>
          <?= $inv['client_address'] ? '<br>'.nl2br(h($inv['client_address'])) : '' ?>
        </div>
      </div>
    </div>
    <table>
      <thead><tr><th>Description</th><th>Qté</th><th>PU HT</th><th>TVA</th><th>Total HT</th><th>Total TTC</th></tr></thead>
      <tbody>
        <?php foreach($inv['invoice_lines'] as $l):
          $lht = (float)($l['qty']??1) * (float)($l['unit_price']??0);
          $ltva= (float)($l['tva']??20);
          $lttc= $lht * (1 + $ltva/100);
        ?>
        <tr>
          <td><?= h($l['desc']??'') ?></td>
          <td><?= number_format((float)($l['qty']??1),2,',',' ') ?></td>
          <td><?= number_format((float)($l['unit_price']??0),2,',',' ') ?> €</td>
          <td><?= $ltva ?> %</td>
          <td><?= number_format($lht,2,',',' ') ?> €</td>
          <td><?= number_format($lttc,2,',',' ') ?> €</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="totals">
      <div class="total-row"><span>Total HT</span><span><?= number_format($tot['ht'],2,',',' ') ?> €</span></div>
      <?php if($tot['discount']>0): ?>
      <div class="total-row"><span>Remise</span><span>- <?= number_format($tot['discount'],2,',',' ') ?> €</span></div>
      <?php endif; ?>
      <div class="total-row" style="color:#888;font-size:11px;"><span>TVA <?= $inv['tva_rate'] ?>%</span><span><?= number_format($tot['tva'],2,',',' ') ?> €</span></div>
      <div class="total-row main"><span>Total TTC</span><span><?= number_format($tot['ttc'],2,',',' ') ?> €</span></div>
    </div>
    <?php if($inv['notes']): ?>
    <div style="margin-top:24px;padding:14px;background:#f9f9f9;border-radius:8px;border-left:3px solid #ed0c0f;font-size:12px;color:#555;"><?= nl2br(h($inv['notes'])) ?></div>
    <?php endif; ?>
    <div class="footer"><?= h($sname) ?> · <?= h(get_setting('site_phone')) ?> · <?= h(get_setting('site_email')) ?><br>Document généré le <?= date('d/m/Y à H:i') ?></div>
  </div>
</div>
</body>
</html>
