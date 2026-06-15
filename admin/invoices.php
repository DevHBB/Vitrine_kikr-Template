<?php
require_once __DIR__ . '/layout.php';
ensure_tables();
$action=$_POST['action']??'';
$saved=false;

// Supprimer
if($action==='delete'){
    db()->prepare("DELETE FROM kk_invoices WHERE id=?")->execute([(int)($_POST['id']??0)]);
    header('Location:'.BASE_URL.'/admin/invoices.php');exit;
}
// Changer statut
if($action==='status'){
    db()->prepare("UPDATE kk_invoices SET status=?,updated_at=NOW() WHERE id=?")->execute([$_POST['status'],(int)$_POST['id']]);
    if($_POST['status']==='paid'){
        db()->prepare("UPDATE kk_invoices SET payment_date=NOW() WHERE id=?")->execute([(int)$_POST['id']]);
    }
    header('Location:'.BASE_URL.'/admin/invoices.php');exit;
}
// Sauvegarder facture
if($action==='save'){
    $id=(int)($_POST['id']??0);
    $type=$_POST['inv_type']??'invoice';
    // Lignes
    $lines=[];
    foreach(($_POST['line_desc']??[]) as $k=>$desc){
        if(trim($desc)){
            $lines[]=['desc'=>trim($desc),'qty'=>(float)($_POST['line_qty'][$k]??1),'unit_price'=>(float)($_POST['line_price'][$k]??0),'tva'=>(float)($_POST['line_tva'][$k]??20)];
        }
    }
    $row=[
        $type,$_POST['status']??'draft',
        (int)($_POST['client_id']??0)?:(null),
        trim($_POST['client_name']??''),trim($_POST['client_email']??''),
        trim($_POST['client_phone']??''),trim($_POST['client_address']??''),
        trim($_POST['client_tva']??''),
        (int)($_POST['appointment_id']??0)?:(null),
        json_encode($lines,JSON_UNESCAPED_UNICODE),
        (float)($_POST['tva_rate']??20),(float)($_POST['discount']??0),
        $_POST['discount_type']??'amount',trim($_POST['notes']??''),
        $_POST['due_date']??null,
    ];
    if($id>0){
        db()->prepare("UPDATE kk_invoices SET type=?,status=?,client_id=?,client_name=?,client_email=?,client_phone=?,client_address=?,client_tva=?,appointment_id=?,invoice_lines=?,tva_rate=?,discount=?,discount_type=?,notes=?,due_date=?,updated_at=NOW() WHERE id=?")
           ->execute([...$row,$id]);
    } else {
        $num=next_invoice_number($type);
        db()->prepare("INSERT INTO kk_invoices(number,type,status,client_id,client_name,client_email,client_phone,client_address,client_tva,appointment_id,invoice_lines,tva_rate,discount,discount_type,notes,due_date) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$num,...$row]);
        $id=(int)db()->lastInsertId();
    }
    // Créer avoir
    if($action==='save'&&!empty($_POST['make_credit'])&&$id>0){
        $orig=get_invoice($id);
        $cnum=next_invoice_number('credit');
        db()->prepare("INSERT INTO kk_invoices(number,type,status,client_id,client_name,client_email,client_phone,invoice_lines,tva_rate,related_invoice_id) VALUES(?,?,?,?,?,?,?,?,?,?)")
           ->execute([$cnum,'credit','draft',$orig['client_id']??null,$orig['client_name'],$orig['client_email'],$orig['client_phone'],$orig['invoice_lines']??'[]',$orig['tva_rate']??20,$id]);
    }
    header('Location:'.BASE_URL.'/admin/invoices.php?edit='.$id.'&saved=1');exit;
}

$filter_status=$_GET['status']??'';
$filter_client=(int)($_GET['client']??0);
$invoices=get_invoices($filter_status,$filter_client);
$edit_id=isset($_GET['edit'])?(int)$_GET['edit']:-1;
$ei=null;
if($edit_id>=0){
    $ei=$edit_id>0?get_invoice($edit_id):['id'=>0,'type'=>'invoice','status'=>'draft','client_id'=>null,'client_name'=>'','client_email'=>'','client_phone'=>'','client_address'=>'','client_tva'=>'','invoice_lines'=>[['desc'=>'','qty'=>1,'unit_price'=>0,'tva'=>20]],'tva_rate'=>20,'discount'=>0,'discount_type'=>'amount','notes'=>'','due_date'=>date('Y-m-d',strtotime('+30 days')),'number'=>''];
}
$statuses=['draft'=>['✏️','Brouillon','#f5f5f3','#555'],'sent'=>['📤','Envoyé','#dbeafe','#1d4ed8'],'paid'=>['✅','Payé','#dcfce7','#15803d'],'partial'=>['🟡','Partiel','#fef9c3','#854d0e'],'cancelled'=>['❌','Annulé','#fee2e2','#dc2626']];
$types=['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'];
$clients_list=get_clients();
?>
<div class="adm-topbar">
  <h1>Facturation</h1>
  <div style="display:flex;gap:6px;">
    <?php foreach([''=>'Tout','draft'=>'Brouillons','sent'=>'Envoyés','paid'=>'Payés'] as $k=>$lbl): ?>
    <a href="?status=<?= $k ?>" class="btn <?= $filter_status===$k?'btn-primary':'btn-ghost' ?> btn-sm"><?= $lbl ?></a>
    <?php endforeach; ?>
    <a href="?edit=0" class="btn btn-dark btn-sm">+ Créer</a>
  </div>
</div>
<div class="adm-content">
<?php if(isset($_GET['saved'])): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<!-- LISTE -->
<div class="card">
  <?php if(empty($invoices)): ?><p style="color:var(--muted);font-size:13px;padding:20px;text-align:center;">Aucune facture.</p>
  <?php else: ?>
  <div class="item-list">
  <?php foreach($invoices as $inv):
    [$ico,$lbl,$bg,$fg]=$statuses[$inv['status']]??['?','','',' #999'];
    $lines=jd($inv['invoice_lines']??'[]',[]);
    $tot=invoice_totals($lines,(float)$inv['tva_rate'],(float)$inv['discount'],$inv['discount_type']);
  ?>
  <div class="item-row">
    <div style="font-size:11px;font-weight:700;color:var(--muted);width:100px;flex-shrink:0;"><?= h($inv['number']) ?><br><span style="color:#bbb;font-weight:400;"><?= $types[$inv['type']]??'' ?></span></div>
    <div class="item-row-name"><?= h($inv['client_name']) ?></div>
    <div class="item-row-sub"><?= number_format($tot['ttc'],2,',',' ') ?> € TTC</div>
    <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?= $ico.' '.$lbl ?></span>
    <div style="font-size:11px;color:var(--muted);"><?= date('d/m/Y',strtotime($inv['created_at'])) ?></div>
    <div class="item-row-actions">
      <a href="?edit=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <a href="<?= BASE_URL ?>/admin/invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">📄 PDF</a>
      <a href="<?= BASE_URL ?>/admin/invoice_send.php?id=<?= $inv['id'] ?>" class="btn btn-ghost btn-sm">📤 Email</a>
      <a href="<?= BASE_URL ?>/admin/invoice_pay_link.php?id=<?= $inv['id'] ?>" class="btn btn-ghost btn-sm">💳 Lien</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $inv['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ÉDITEUR -->
<?php if($edit_id>=0): $tot=invoice_totals($ei['invoice_lines']??[],(float)($ei['tva_rate']??20),(float)($ei['discount']??0),$ei['discount_type']??'amount'); ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_id>0?'✏️ '.$ei['number']:'➕ Nouveau document' ?></h2></div>
  <form method="POST" id="inv-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">
    <div class="g2" style="margin-bottom:14px;">
      <div class="fgrp"><label>Type</label>
        <select name="inv_type">
          <?php foreach($types as $k=>$v): ?><option value="<?= $k ?>" <?= ($ei['type']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp"><label>Statut</label>
        <select name="status">
          <?php foreach($statuses as $k=>[$i,$l]): ?><option value="<?= $k ?>" <?= ($ei['status']??'')===$k?'selected':'' ?>><?= $i.' '.$l ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp"><label>Date d'échéance</label><input type="date" name="due_date" value="<?= h($ei['due_date']??'') ?>"></div>
    </div>

    <!-- Client -->
    <div style="background:var(--bg);border-radius:10px;padding:14px;margin-bottom:14px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Client</div>
      <div class="fgrp" style="margin-bottom:10px;">
        <label>Choisir un client existant</label>
        <select onchange="fillClient(this)" style="width:100%;">
          <option value="">— Nouveau client —</option>
          <?php foreach($clients_list as $cl): ?><option value="<?= $cl['id'] ?>" data-name="<?= h($cl['name']) ?>" data-email="<?= h($cl['email']) ?>" data-phone="<?= h($cl['phone']) ?>" <?= ($ei['client_id']??0)==$cl['id']?'selected':'' ?>><?= h($cl['name']) ?> (<?= h($cl['email']) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="client_id" id="client_id_field" value="<?= h($ei['client_id']??'') ?>">
      <div class="g2">
        <div class="fgrp"><label>Nom *</label><input type="text" name="client_name" id="cn" value="<?= h($ei['client_name']) ?>" required></div>
        <div class="fgrp"><label>Email</label><input type="email" name="client_email" id="ce" value="<?= h($ei['client_email']) ?>"></div>
        <div class="fgrp"><label>Téléphone</label><input type="text" name="client_phone" id="cp" value="<?= h($ei['client_phone']) ?>"></div>
        <div class="fgrp"><label>N° TVA intra.</label><input type="text" name="client_tva" value="<?= h($ei['client_tva']??'') ?>" placeholder="FR12345678901"></div>
        <div class="fgrp full"><label>Adresse de facturation</label><textarea name="client_address" style="min-height:60px;"><?= h($ei['client_address']??'') ?></textarea></div>
      </div>
    </div>

    <!-- Lignes -->
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Lignes</div>
    <div style="display:grid;grid-template-columns:3fr 1fr 1.2fr 0.8fr 28px;gap:6px;margin-bottom:6px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;">
      <span>Description</span><span>Qté</span><span>Prix unit. HT</span><span>TVA %</span><span></span>
    </div>
    <div id="lines-wrap">
    <?php foreach($ei['invoice_lines'] as $k=>$l): ?>
    <div class="inv-line" style="display:grid;grid-template-columns:3fr 1fr 1.2fr 0.8fr 28px;gap:6px;margin-bottom:6px;">
      <input type="text"   name="line_desc[]"  value="<?= h($l['desc']??'') ?>"       placeholder="Description" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;font-family:inherit;width:100%;">
      <input type="number" name="line_qty[]"   value="<?= $l['qty']??1 ?>"            min="0.01" step="0.01"   style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()">
      <input type="number" name="line_price[]" value="<?= $l['unit_price']??0 ?>"     min="0"   step="0.01"   style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()">
      <input type="number" name="line_tva[]"   value="<?= $l['tva']??20 ?>"           min="0"   step="0.1"    style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()">
      <button type="button" onclick="this.closest('.inv-line').remove();calcTotal()" style="background:#fee2e2;border:none;border-radius:7px;cursor:pointer;color:#dc2626;font-size:14px;">×</button>
    </div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addLine()" style="margin-bottom:16px;">+ Ligne</button>

    <!-- Totaux -->
    <div class="g2">
      <div>
        <div class="fgrp"><label>Remise</label>
          <div style="display:flex;gap:6px;">
            <input type="number" name="discount" id="disc" value="<?= $ei['discount']??0 ?>" min="0" step="0.01" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;width:100%;" onchange="calcTotal()">
            <select name="discount_type" id="disc_type" style="border:1.5px solid var(--border);border-radius:7px;padding:7px;font-size:12px;" onchange="calcTotal()">
              <option value="amount" <?= ($ei['discount_type']??'')==='amount'?'selected':'' ?>>€</option>
              <option value="percent" <?= ($ei['discount_type']??'')==='percent'?'selected':'' ?>>%</option>
            </select>
          </div>
        </div>
        <div class="fgrp"><label>TVA globale %</label><input type="number" name="tva_rate" id="tva_rate" value="<?= $ei['tva_rate']??20 ?>" min="0" step="0.1" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;width:100%;" onchange="calcTotal()"></div>
        <div class="fgrp full"><label>Notes</label><textarea name="notes" style="min-height:70px;"><?= h($ei['notes']??'') ?></textarea></div>
      </div>
      <div style="background:var(--bg);border-radius:12px;padding:18px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;"><span>Total HT</span><span id="tot-ht"><?= number_format($tot['ht'],2,',',' ') ?> €</span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;color:#aaa;" id="disc-row"><span>Remise</span><span id="tot-disc">- <?= number_format($tot['discount'],2,',',' ') ?> €</span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;"><span>HT après remise</span><span id="tot-ht-after"><?= number_format($tot['ht_after'],2,',',' ') ?> €</span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;color:#888;"><span id="tva-lbl">TVA <?= $ei['tva_rate']??20 ?>%</span><span id="tot-tva"><?= number_format($tot['tva'],2,',',' ') ?> €</span></div>
        <hr style="border:none;border-top:2px solid var(--border);margin:10px 0;">
        <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:900;"><span>Total TTC</span><span id="tot-ttc" style="color:var(--red);"><?= number_format($tot['ttc'],2,',',' ') ?> €</span></div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="<?= BASE_URL ?>/admin/invoice_pdf.php?id=<?= $edit_id ?>" target="_blank" class="btn btn-secondary" <?= $edit_id<=0?'style="pointer-events:none;opacity:.4;"':'' ?>>📄 Aperçu PDF</a>
      <a href="<?= BASE_URL ?>/admin/invoice_send.php?id=<?= $edit_id ?>" class="btn btn-ghost" <?= $edit_id<=0?'style="pointer-events:none;opacity:.4;"':'' ?>>📤 Envoyer par email</a>
      <a href="<?= BASE_URL ?>/admin/invoice_pay_link.php?id=<?= $edit_id ?>" class="btn btn-ghost" <?= $edit_id<=0?'style="pointer-events:none;opacity:.4;"':'' ?>>💳 Générer lien paiement</a>
      <?php if($edit_id>0 && ($ei['type']??'')==='invoice'): ?>
      <button type="submit" name="make_credit" value="1" class="btn btn-ghost" onclick="return confirm('Créer un avoir pour cette facture ?')">↩️ Créer un avoir</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>
</div>

<script>
function fillClient(sel){
  var opt=sel.options[sel.selectedIndex];
  document.getElementById('client_id_field').value=sel.value;
  document.getElementById('cn').value=opt.dataset.name||'';
  document.getElementById('ce').value=opt.dataset.email||'';
  document.getElementById('cp').value=opt.dataset.phone||'';
}
function addLine(){
  var w=document.getElementById('lines-wrap');
  var d=document.createElement('div');
  d.className='inv-line';
  d.style='display:grid;grid-template-columns:3fr 1fr 1.2fr 0.8fr 28px;gap:6px;margin-bottom:6px;';
  d.innerHTML='<input type="text" name="line_desc[]" placeholder="Description" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;font-family:inherit;width:100%;"><input type="number" name="line_qty[]" value="1" min="0.01" step="0.01" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()"><input type="number" name="line_price[]" value="0" min="0" step="0.01" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()"><input type="number" name="line_tva[]" value="20" min="0" step="0.1" style="border:1.5px solid var(--border);border-radius:7px;padding:7px 8px;font-size:12px;font-family:inherit;width:100%;" onchange="calcTotal()"><button type="button" onclick="this.closest(\'.inv-line\').remove();calcTotal()" style="background:#fee2e2;border:none;border-radius:7px;cursor:pointer;color:#dc2626;font-size:14px;">×</button>';
  w.appendChild(d);
}
function fmt(n){return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,' ');}
function calcTotal(){
  var lines=document.querySelectorAll('.inv-line');
  var ht=0;
  lines.forEach(function(l){
    var qty=parseFloat(l.querySelector('[name="line_qty[]"]').value)||0;
    var pr=parseFloat(l.querySelector('[name="line_price[]"]').value)||0;
    ht+=qty*pr;
  });
  var disc=parseFloat(document.getElementById('disc').value)||0;
  var dType=document.getElementById('disc_type').value;
  var dAmt=dType==='percent'?ht*(disc/100):disc;
  var htA=ht-dAmt;
  var tvaR=parseFloat(document.getElementById('tva_rate').value)||0;
  var tva=htA*(tvaR/100);
  var ttc=htA+tva;
  document.getElementById('tot-ht').textContent=fmt(ht)+' €';
  document.getElementById('tot-disc').textContent='- '+fmt(dAmt)+' €';
  document.getElementById('tot-ht-after').textContent=fmt(htA)+' €';
  document.getElementById('tva-lbl').textContent='TVA '+tvaR+'%';
  document.getElementById('tot-tva').textContent=fmt(tva)+' €';
  document.getElementById('tot-ttc').textContent=fmt(ttc)+' €';
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
