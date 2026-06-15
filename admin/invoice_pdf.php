<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/data.php';
require_admin();
$id=(int)($_GET['id']??0);
$inv=get_invoice($id);
if(!$inv){http_response_code(404);die('Facture introuvable');}
$tot=invoice_totals($inv['invoice_lines'],(float)$inv['tva_rate'],(float)$inv['discount'],$inv['discount_type']);
$types=['invoice'=>'FACTURE','quote'=>'DEVIS','credit'=>'AVOIR'];
$sname=get_setting('site_name',"Kik'r Suspension");
$sphone=get_setting('site_phone');
$semail=get_setting('site_email');
$saddr=get_setting('site_address');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $types[$inv['type']]??'FACTURE' ?> <?= h($inv['number']) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#111;background:white;padding:40px}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;padding-bottom:24px;border-bottom:3px solid #111}
.logo{font-size:28px;font-weight:900;letter-spacing:-1px}
.logo span{color:#ed0c0f}
.doc-type{font-size:32px;font-weight:900;color:#111;text-align:right;letter-spacing:-1px}
.doc-num{font-size:13px;color:#888;text-align:right;margin-top:4px}
.doc-date{font-size:12px;color:#888;text-align:right}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-bottom:36px}
.party-label{font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.party-name{font-size:15px;font-weight:700;margin-bottom:4px}
.party-info{font-size:12px;color:#555;line-height:1.7}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
thead tr{background:#111;color:white}
thead th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
thead th:not(:first-child){text-align:right}
tbody tr{border-bottom:1px solid #f0f0f0}
tbody tr:nth-child(even){background:#fafafa}
tbody td{padding:9px 12px;font-size:12px}
tbody td:not(:first-child){text-align:right;font-variant-numeric:tabular-nums}
.totals{margin-left:auto;width:300px}
.total-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:13px}
.total-row.main{font-size:16px;font-weight:900;border-bottom:none;padding-top:10px;color:#ed0c0f}
.total-row.tva{color:#888;font-size:11px}
.notes-box{margin-top:32px;padding:16px;background:#f5f5f3;border-radius:8px;border-left:3px solid #ed0c0f;font-size:11px;line-height:1.7;color:#555}
.footer{margin-top:48px;padding-top:16px;border-top:1px solid #e8e8e8;font-size:10px;color:#aaa;text-align:center}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700}
.paid{background:#dcfce7;color:#15803d}
.sent{background:#dbeafe;color:#1d4ed8}
.draft{background:#f5f5f3;color:#555}
@media print{body{padding:20px}.no-print{display:none}}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:24px;display:flex;gap:10px;">
  <button onclick="window.print()" style="background:#111;color:white;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">🖨️ Imprimer / PDF</button>
  <a href="invoice_send.php?id=<?= $id ?>" style="background:#ed0c0f;color:white;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">📤 Envoyer par email</a>
  <a href="invoice_pay_link.php?id=<?= $id ?>" style="background:#1d4ed8;color:white;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">💳 Lien de paiement</a>
  <a href="invoices.php" style="background:#f5f5f3;color:#111;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">← Retour</a>
</div>

<div class="header">
  <div>
    <?php $logo=get_setting('site_logo'); if($logo): ?>
    <img src="<?= h(dirname(__DIR__).$logo) ?>" alt="" style="height:48px;object-fit:contain;margin-bottom:8px;display:block;">
    <?php else: ?>
    <div class="logo"><?= h(explode(' ',$sname)[0]) ?><span>'</span><?= h(explode(' ',$sname)[1]??'r') ?>.</div>
    <?php endif; ?>
    <div style="font-size:11px;color:#888;margin-top:8px;line-height:1.6;">
      <?= h($saddr) ?><br><?= h($sphone) ?><br><?= h($semail) ?>
    </div>
  </div>
  <div>
    <div class="doc-type"><?= $types[$inv['type']]??'FACTURE' ?></div>
    <div class="doc-num"><?= h($inv['number']) ?></div>
    <div class="doc-date">Date : <?= date('d/m/Y',strtotime($inv['created_at'])) ?></div>
    <?php if($inv['due_date']): ?><div class="doc-date">Échéance : <?= date('d/m/Y',strtotime($inv['due_date'])) ?></div><?php endif; ?>
    <div style="margin-top:8px;">
      <span class="status-badge <?= $inv['status'] ?>"><?= ['draft'=>'Brouillon','sent'=>'Envoyé','paid'=>'✅ Payé','partial'=>'Partiel','cancelled'=>'Annulé'][$inv['status']]??'' ?></span>
    </div>
  </div>
</div>

<div class="parties">
  <div>
    <div class="party-label">Émetteur</div>
    <div class="party-name"><?= h($sname) ?></div>
    <div class="party-info"><?= nl2br(h($saddr)) ?><br><?= h($sphone) ?><br><?= h($semail) ?></div>
  </div>
  <div>
    <div class="party-label">Client</div>
    <div class="party-name"><?= h($inv['client_name']) ?></div>
    <div class="party-info">
      <?= h($inv['client_email']) ?><?= $inv['client_phone']?'<br>'.h($inv['client_phone']):'' ?>
      <?= $inv['client_address']?'<br>'.nl2br(h($inv['client_address'])):'' ?>
      <?= $inv['client_tva']?'<br>TVA : '.h($inv['client_tva']):'' ?>
    </div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th>Qté</th>
      <th>Prix HT</th>
      <th>TVA</th>
      <th>Total HT</th>
      <th>Total TTC</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($inv['invoice_lines'] as $l):
      $line_ht=((float)($l['qty']??1))*((float)($l['unit_price']??0));
      $line_tva_r=(float)($l['tva']??$inv['tva_rate']??20);
      $line_ttc=$line_ht*(1+$line_tva_r/100);
    ?>
    <tr>
      <td><?= h($l['desc']??'') ?></td>
      <td><?= number_format((float)($l['qty']??1),2,',',' ') ?></td>
      <td><?= number_format((float)($l['unit_price']??0),2,',',' ') ?> €</td>
      <td><?= $line_tva_r ?> %</td>
      <td><?= number_format($line_ht,2,',',' ') ?> €</td>
      <td><?= number_format($line_ttc,2,',',' ') ?> €</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="totals">
  <div class="total-row"><span>Total HT</span><span><?= number_format($tot['ht'],2,',',' ') ?> €</span></div>
  <?php if($tot['discount']>0): ?>
  <div class="total-row"><span>Remise</span><span>- <?= number_format($tot['discount'],2,',',' ') ?> €</span></div>
  <div class="total-row"><span>HT après remise</span><span><?= number_format($tot['ht_after'],2,',',' ') ?> €</span></div>
  <?php endif; ?>
  <div class="total-row tva"><span>TVA <?= $inv['tva_rate'] ?>%</span><span><?= number_format($tot['tva'],2,',',' ') ?> €</span></div>
  <div class="total-row main"><span>TOTAL TTC</span><span><?= number_format($tot['ttc'],2,',',' ') ?> €</span></div>
</div>

<?php if($inv['notes']): ?>
<div class="notes-box"><strong style="display:block;margin-bottom:4px;">Notes</strong><?= nl2br(h($inv['notes'])) ?></div>
<?php endif; ?>

<div class="footer">
  <?= h($sname) ?> — <?= h($sphone) ?> — <?= h($semail) ?><br>
  Document généré le <?= date('d/m/Y à H:i') ?>
</div>
</body>
</html>
