<?php
require_once __DIR__ . '/layout.php';
$action=$_POST['action']??'';
$saved=false;

if($action==='delete'&&!empty($_POST['id'])){
    db()->prepare("DELETE FROM kk_clients WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location:'.BASE_URL.'/admin/clients.php');exit;
}
if($action==='save'){
    $id=(int)($_POST['id']??0);
    $row=[trim($_POST['name']??''),trim($_POST['email']??''),trim($_POST['phone']??''),
          $_POST['type']??'particulier',(int)($_POST['newsletter_opt']??0),(int)($_POST['sms_opt']??0),
          trim($_POST['notes']??'')];
    if($id>0){
        db()->prepare("UPDATE kk_clients SET name=?,email=?,phone=?,type=?,newsletter_opt=?,sms_opt=?,notes=?,updated_at=NOW() WHERE id=?")
           ->execute([...$row,$id]);
    } else {
        db()->prepare("INSERT INTO kk_clients(name,email,phone,type,newsletter_opt,sms_opt,notes) VALUES(?,?,?,?,?,?,?)")
           ->execute($row);
    }
    $saved=true;
}

$search=trim($_GET['q']??'');
$type_filter=$_GET['type']??'';
$clients=get_clients($search,$type_filter);
$edit_id=isset($_GET['edit'])?(int)$_GET['edit']:-1;
$ec=null;
if($edit_id>0){$ec=get_client($edit_id);}
elseif($edit_id===0){$ec=['id'=>0,'name'=>'','email'=>'','phone'=>'','type'=>'particulier','newsletter_opt'=>1,'sms_opt'=>1,'notes'=>''];}
?>
<div class="adm-topbar">
  <h1>Base clients (<?= count($clients) ?>)</h1>
  <div style="display:flex;gap:8px;">
    <form method="GET" style="display:flex;gap:6px;">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher…" style="border:1.5px solid var(--border);border-radius:8px;padding:6px 12px;font-size:12px;width:180px;">
      <select name="type" style="border:1.5px solid var(--border);border-radius:8px;padding:6px;font-size:12px;">
        <option value="">Tous</option>
        <option value="particulier" <?= $type_filter==='particulier'?'selected':'' ?>>Particuliers</option>
        <option value="pro" <?= $type_filter==='pro'?'selected':'' ?>>PRO</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">🔍</button>
    </form>
    <a href="?edit=0" class="btn btn-primary btn-sm">+ Nouveau client</a>
  </div>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<?php if($edit_id>=0): ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-head"><h2><?= $edit_id>0?'✏️ Modifier client':'➕ Nouveau client' ?></h2></div>
  <form method="POST">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">
    <div class="g2">
      <div class="fgrp"><label>Nom *</label><input type="text" name="name" value="<?= h($ec['name']) ?>" required></div>
      <div class="fgrp"><label>Email *</label><input type="email" name="email" value="<?= h($ec['email']) ?>" required></div>
      <div class="fgrp"><label>Téléphone</label><input type="text" name="phone" value="<?= h($ec['phone']) ?>"></div>
      <div class="fgrp"><label>Type</label>
        <select name="type"><option value="particulier" <?= $ec['type']==='particulier'?'selected':'' ?>>Particulier</option><option value="pro" <?= $ec['type']==='pro'?'selected':'' ?>>Pilote sponsorisé</option></select>
      </div>
      <div class="fgrp"><label>Newsletter</label>
        <select name="newsletter_opt"><option value="1" <?= $ec['newsletter_opt']?'selected':'' ?>>✅ Inscrit</option><option value="0" <?= !$ec['newsletter_opt']?'selected':'' ?>>❌ Désabonné</option></select>
      </div>
      <div class="fgrp"><label>SMS</label>
        <select name="sms_opt"><option value="1" <?= $ec['sms_opt']?'selected':'' ?>>✅ Accepte</option><option value="0" <?= !$ec['sms_opt']?'selected':'' ?>>❌ Refuse</option></select>
      </div>
      <div class="fgrp full"><label>Notes internes</label><textarea name="notes"><?= h($ec['notes']) ?></textarea></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="/admin/clients.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="item-list">
  <?php foreach($clients as $cl): ?>
  <div class="item-row">
    <div class="item-row-name"><?= h($cl['name']) ?></div>
    <div class="item-row-sub"><?= h($cl['email']) ?></div>
    <div class="item-row-sub"><?= h($cl['phone']) ?></div>
    <span class="item-row-badge <?= $cl['type']==='pro'?'red':'' ?>"><?= $cl['type']==='pro'?'🏆 Sponsorisé':'Particulier' ?></span>
    <div class="item-row-actions">
      <a href="?edit=<?= $cl['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <a href="<?= BASE_URL ?>/admin/invoices.php?client=<?= $cl['id'] ?>" class="btn btn-ghost btn-sm">🧾</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $cl['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($clients)): ?><p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucun client trouvé.</p><?php endif; ?>
  </div>
</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
