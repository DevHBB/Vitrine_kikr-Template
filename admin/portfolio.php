<?php
require_once __DIR__ . '/layout.php';
$saved=false; $action=$_POST['action']??'';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if($action==='save_header'){set_setting('portfolio_title',trim($_POST['title']??''));set_setting('portfolio_subtitle',trim($_POST['subtitle']??''));$saved=true;}
    if($action==='save_pilot'){
        $pi=(int)($_POST['pi']??0);
        $photo=trim($_POST['photo']??'');
        if(!empty($_FILES['pphoto']['tmp_name'])){$u=upload_media($_FILES['pphoto'],'pilots');if($u)$photo=$u;}
        $row=[trim($_POST['name']??''),trim($_POST['discipline']??''),trim($_POST['bio']??''),$photo];
        if($pi>0) db()->prepare("UPDATE kk_pilots SET name=?,discipline=?,bio=?,photo=? WHERE id=?")->execute([...$row,$pi]);
        else{$pos=(int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_pilots')->fetchColumn();db()->prepare("INSERT INTO kk_pilots(position,name,discipline,bio,photo)VALUES(?,?,?,?,?)")->execute([$pos,...$row]);}
        $saved=true;
    }
    if($action==='delete_pilot'){db()->prepare("DELETE FROM kk_pilots WHERE id=?")->execute([(int)($_POST['pi']??0)]);header('Location:/admin/portfolio.php');exit;}
}
$pilots=get_pilots();
$epi=isset($_GET['edit'])?(int)$_GET['edit']:-1;$ep=null;
if($epi>=0){$s=db()->prepare('SELECT * FROM kk_pilots WHERE id=?');$s->execute([$epi]);$ep=$s->fetch()??[];}
?>
<div class="adm-topbar"><h1>Portfolio</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<div class="card"><div class="card-head"><h2>🏔️ En-tête</h2></div>
  <form method="POST"><input type="hidden" name="action" value="save_header">
    <div class="g2"><div class="fgrp"><label>Titre</label><input type="text" name="title" value="<?= h(get_setting('portfolio_title')) ?>"></div><div class="fgrp"><label>Sous-titre</label><input type="text" name="subtitle" value="<?= h(get_setting('portfolio_subtitle')) ?>"></div></div>
    <button type="submit" class="btn btn-primary" style="margin-top:10px;">💾 Enregistrer</button>
  </form>
</div>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2>🏍️ Pilotes (<?= count($pilots) ?>)</h2><a href="?edit=0" class="btn btn-dark btn-sm">+ Pilote</a></div>
  <div class="item-list">
  <?php foreach($pilots as $p): ?>
  <div class="item-row">
    <?php if($p['photo']): ?><img src="<?= h($p['photo']) ?>" class="item-row-thumb round"><?php else: ?><div class="item-row-thumb round" style="background:#333;"></div><?php endif; ?>
    <div class="item-row-name"><?= h($p['name']) ?></div><div class="item-row-sub"><?= h($p['discipline']) ?></div>
    <div class="item-row-actions">
      <a href="?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="action" value="delete_pilot"><input type="hidden" name="pi" value="<?= $p['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">🗑</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php if($epi>=0): ?>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2><?= $epi>0?'✏️ Modifier':'➕ Nouveau pilote' ?></h2></div>
  <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="save_pilot"><input type="hidden" name="pi" value="<?= $epi ?>">
    <div class="g2">
      <div class="fgrp"><label>Nom *</label><input type="text" name="name" value="<?= h($ep['name']??'') ?>" required></div>
      <div class="fgrp"><label>Discipline</label><input type="text" name="discipline" value="<?= h($ep['discipline']??'') ?>" placeholder="Motocross, FMX…"></div>
      <div class="fgrp full"><label>Bio</label><textarea name="bio"><?= h($ep['bio']??'') ?></textarea></div>
      <div class="fgrp"><label>Photo URL</label><input type="text" name="photo" value="<?= h($ep['photo']??'') ?>">
        <?php if(!empty($ep['photo'])): ?><div class="img-preview"><img src="<?= h($ep['photo']) ?>"></div><?php endif; ?>
      </div>
      <div class="fgrp"><label>Upload photo</label><input type="file" name="pphoto" accept="image/*"></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;"><button type="submit" class="btn btn-primary">💾 Enregistrer</button><a href="/admin/portfolio.php" class="btn btn-secondary">Annuler</a></div>
  </form>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
