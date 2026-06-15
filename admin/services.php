<?php
require_once __DIR__ . '/layout.php';
$saved = false;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_general') {
        set_setting('services_intro',        trim($_POST['intro']         ?? ''));
        set_setting('services_disclaimer',   trim($_POST['disclaimer']    ?? ''));
        set_setting('services_depose_repose',trim($_POST['depose_repose'] ?? ''));
        $saved = true;
    }
    if ($action === 'save_service') {
        $id  = (int)($_POST['id'] ?? 0);
        $img = !empty($_FILES['svc_img']['tmp_name']) ? (upload_media($_FILES['svc_img'],'services') ?: trim($_POST['image_url']??'')) : trim($_POST['image_url']??'');
        $tr  = json_encode(array_values(array_filter(array_map('trim',explode("\n",$_POST['treatments']??'')))),JSON_UNESCAPED_UNICODE);
        $row = [trim($_POST['label']??''),trim($_POST['title']??''),trim($_POST['description']??''),trim($_POST['price']??''),trim($_POST['highlight']??''),$tr,$img,(int)($_POST['active']??1)];
        if ($id > 0) {
            db()->prepare("UPDATE kk_services SET label=?,title=?,description=?,price=?,highlight=?,treatments=?,image=?,active=? WHERE id=?")->execute([...$row,$id]);
        } else {
            $pos=(int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_services')->fetchColumn();
            $slug=slugify($_POST['title']??'service');
            db()->prepare("INSERT INTO kk_services(position,slug,label,title,description,price,highlight,treatments,image,active)VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$pos,$slug,...$row]);
        }
        $saved = true;
    }
    if ($action === 'delete_service') {
        db()->prepare("DELETE FROM kk_services WHERE id=?")->execute([(int)($_POST['id']??0)]);
        header('Location: /admin/services.php'); exit;
    }
}

$services = get_services();
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$edit_svc = null;
if ($edit_id >= 0) {
    $s = db()->prepare('SELECT * FROM kk_services WHERE id=?'); $s->execute([$edit_id]);
    $edit_svc = $s->fetch() ?: [];
    $edit_svc['treatments_text'] = implode("\n", jd($edit_svc['treatments']??'[]',[]));
}
?>
<div class="adm-topbar"><h1>Services</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<div class="card"><div class="card-head"><h2><span class="icon">⚙️</span> Textes généraux</h2></div>
  <form method="POST"><input type="hidden" name="action" value="save_general">
    <div class="g2">
      <div class="fgrp"><label>Intro</label><input type="text" name="intro" value="<?= h(get_setting('services_intro')) ?>"></div>
      <div class="fgrp"><label>Dépose/Repose</label><input type="text" name="depose_repose" value="<?= h(get_setting('services_depose_repose')) ?>"></div>
      <div class="fgrp full"><label>Disclaimer</label><input type="text" name="disclaimer" value="<?= h(get_setting('services_disclaimer')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:10px;">💾 Enregistrer</button>
  </form>
</div>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2><span class="icon">📋</span> Services (<?= count($services) ?>)</h2><a href="?edit=0" class="btn btn-dark btn-sm">+ Ajouter</a></div>
  <div class="item-list">
  <?php foreach($services as $svc): ?>
  <div class="item-row">
    <?php if($svc['image']): ?><img src="<?= h($svc['image']) ?>" class="item-row-thumb"><?php endif; ?>
    <div class="item-row-name"><?= h($svc['title']) ?></div>
    <div class="item-row-sub"><?= h($svc['price']) ?></div>
    <div class="item-row-actions">
      <a href="?edit=<?= $svc['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete_service"><input type="hidden" name="id" value="<?= $svc['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php if($edit_id >= 0): ?>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2><?= $edit_id>0?'✏️ Modifier':'➕ Nouveau' ?></h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_service"><input type="hidden" name="id" value="<?= $edit_id ?>">
    <div class="g2">
      <div class="fgrp"><label>Label bannière</label><input type="text" name="label" value="<?= h($edit_svc['label']??'') ?>"></div>
      <div class="fgrp full"><label>Titre *</label><input type="text" name="title" value="<?= h($edit_svc['title']??'') ?>" required></div>
      <div class="fgrp full"><label>Description</label><textarea name="description"><?= h($edit_svc['description']??'') ?></textarea></div>
      <div class="fgrp"><label>Prix</label><input type="text" name="price" value="<?= h($edit_svc['price']??'') ?>"></div>
      <div class="fgrp"><label>Highlight</label><input type="text" name="highlight" value="<?= h($edit_svc['highlight']??'') ?>"></div>
      <div class="fgrp full"><label>Traitements (1 par ligne)</label><textarea name="treatments"><?= h($edit_svc['treatments_text']??'') ?></textarea></div>
      <div class="fgrp"><label>Image URL</label><input type="text" name="image_url" value="<?= h($edit_svc['image']??'') ?>"></div>
      <div class="fgrp"><label>Upload image</label><input type="file" name="svc_img" accept="image/*"></div>
      <div class="fgrp"><label>Actif</label><select name="active"><option value="1" <?= ($edit_svc['active']??1)?'selected':''?>>Oui</option><option value="0" <?= !($edit_svc['active']??1)?'selected':''?>>Non</option></select></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="/admin/services.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
