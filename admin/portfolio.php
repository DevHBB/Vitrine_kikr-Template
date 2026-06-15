<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();
$saved = false;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_settings'])) {
        set_setting('portfolio_title',    trim($_POST['portfolio_title']    ?? ''));
        set_setting('portfolio_subtitle', trim($_POST['portfolio_subtitle'] ?? ''));
        if (!empty($_FILES['portfolio_banner']['tmp_name'])) {
            $u = upload_media($_FILES['portfolio_banner'], 'media');
            if ($u) set_setting('portfolio_banner_img', $u);
        } elseif (isset($_POST['portfolio_banner_url'])) {
            set_setting('portfolio_banner_img', trim($_POST['portfolio_banner_url'] ?? ''));
        }
        $saved = true;
    }

    if ($action === 'save_pilot') {
        $pi     = (int)($_POST['pi'] ?? 0);
        $photo  = trim($_POST['photo'] ?? '');
        $slogo  = trim($_POST['sponsor_logo'] ?? '');

        if (!empty($_FILES['pphoto']['tmp_name'])) {
            $u = upload_media($_FILES['pphoto'], 'pilots');
            if ($u) $photo = $u;
        }
        if (!empty($_FILES['psponsor']['tmp_name'])) {
            $u = upload_media($_FILES['psponsor'], 'pilots');
            if ($u) $slogo = $u;
        }

        $row = [
            trim($_POST['name']        ?? ''),
            trim($_POST['number']      ?? ''),
            trim($_POST['discipline']  ?? ''),
            trim($_POST['bio']         ?? ''),
            trim($_POST['results']     ?? ''),
            $photo,
            $slogo,
        ];

        if ($pi > 0) {
            db()->prepare("UPDATE kk_pilots SET name=?,number=?,discipline=?,bio=?,results=?,photo=?,sponsor_logo=? WHERE id=?")
               ->execute([...$row, $pi]);
        } else {
            $pos = (int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_pilots')->fetchColumn();
            db()->prepare("INSERT INTO kk_pilots(position,name,number,discipline,bio,results,photo,sponsor_logo) VALUES(?,?,?,?,?,?,?,?)")
               ->execute([$pos, ...$row]);
        }
        $saved = true;
    }

    if ($action === 'delete_pilot') {
        db()->prepare("DELETE FROM kk_pilots WHERE id=?")->execute([(int)($_POST['pi'] ?? 0)]);
        header('Location: ' . BASE_URL . '/admin/portfolio.php'); exit;
    }
    if ($action === 'reorder') {
        $ids = array_map('intval', explode(',', $_POST['ids'] ?? ''));
        foreach ($ids as $pos => $id) {
            db()->prepare("UPDATE kk_pilots SET position=? WHERE id=?")->execute([$pos, $id]);
        }
        echo json_encode(['ok' => true]); exit;
    }
}

$pilots   = get_pilots();
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$ep       = null;
if ($edit_id === 0) {
    $ep = ['id'=>0,'name'=>'','number'=>'','discipline'=>'','bio'=>'','results'=>'','photo'=>'','sponsor_logo'=>''];
} elseif ($edit_id > 0) {
    $s = db()->prepare('SELECT * FROM kk_pilots WHERE id=?'); $s->execute([$edit_id]);
    $ep = $s->fetch() ?: null;
}
?>
<div class="adm-topbar">
  <h1>Portfolio — Pilotes sponsorisés</h1>
  <div style="display:flex;gap:8px;">
    <a href="?edit=0" class="btn btn-primary btn-sm">+ Ajouter un pilote</a>
    <a href="<?= BASE_URL ?>/portfolio.php" target="_blank" class="btn btn-secondary btn-sm">👁 Voir la page</a>
  </div>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<!-- Settings banner -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-head"><h2><span class="icon">🎨</span> En-tête de page</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="save_settings" value="1">
    <div class="g2">
      <div class="fgrp"><label>Titre</label><input type="text" name="portfolio_title" value="<?= h(get_setting('portfolio_title','PORTFOLIO')) ?>"></div>
      <div class="fgrp"><label>Sous-titre</label><input type="text" name="portfolio_subtitle" value="<?= h(get_setting('portfolio_subtitle','Ils nous font confiance.')) ?>"></div>
    </div>
    <div class="g2">
      <div class="fgrp"><label>Image bannière (upload)</label><input type="file" name="portfolio_banner" accept="image/*"></div>
      <div class="fgrp"><label>Ou URL</label><input type="text" name="portfolio_banner_url" value="<?= h(get_setting('portfolio_banner_img','')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm">💾 Enregistrer l'en-tête</button>
  </form>
</div>

<!-- Liste des pilotes -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🏍️</span> Pilotes (<?= count($pilots) ?>)</h2></div>
  <?php if(empty($pilots)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucun pilote. Cliquez sur "+ Ajouter" pour commencer.</p>
  <?php else: ?>
  <div class="item-list" id="pilots-sortable">
    <?php foreach($pilots as $p): ?>
    <div class="item-row" data-id="<?= $p['id'] ?>" style="align-items:center;gap:14px;">
      <span style="color:#ccc;cursor:grab;font-size:16px;">⠿</span>
      <?php if(!empty($p['photo'])): ?>
      <img src="<?= h($p['photo']) ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;">
      <?php else: ?>
      <div style="width:44px;height:44px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;">👤</div>
      <?php endif; ?>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:700;"><?= h($p['name']) ?><?= $p['number'] ? ' <span style="color:#aaa;font-weight:400;">#'.h($p['number']).'</span>' : '' ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= h($p['discipline']) ?></div>
      </div>
      <?php if(!empty($p['sponsor_logo'])): ?>
      <img src="<?= h($p['sponsor_logo']) ?>" style="height:28px;max-width:80px;object-fit:contain;border-radius:4px;background:white;padding:2px 6px;">
      <?php endif; ?>
      <div class="item-row-actions">
        <a href="?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce pilote ?')">
          <input type="hidden" name="action" value="delete_pilot">
          <input type="hidden" name="pi" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Formulaire édition pilote -->
<?php if($edit_id >= 0 && $ep): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head">
    <h2><?= $edit_id > 0 ? '✏️ Modifier ' . h($ep['name']) : '➕ Nouveau pilote sponsorisé' ?></h2>
  </div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_pilot">
    <input type="hidden" name="pi" value="<?= $edit_id ?>">

    <div class="g2">
      <div class="fgrp"><label>Nom complet *</label><input type="text" name="name" value="<?= h($ep['name']??'') ?>" required placeholder="Prénom Nom"></div>
      <div class="fgrp"><label>Numéro de course</label><input type="text" name="number" value="<?= h($ep['number']??'') ?>" placeholder="44"></div>
      <div class="fgrp"><label>Discipline *</label>
        <input type="text" name="discipline" value="<?= h($ep['discipline']??'') ?>" list="disc-list" placeholder="Motocross, FMX, Supermotard…">
        <datalist id="disc-list">
          <?php foreach(['Motocross','FMX','Supermotard','Enduro','Vitesse','Trial'] as $d): ?>
          <option value="<?= $d ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
    </div>

    <div class="fgrp"><label>Biographie / Présentation</label><textarea name="bio" style="min-height:80px;"><?= h($ep['bio']??'') ?></textarea></div>
    <div class="fgrp"><label>Résultats / Palmarès</label><textarea name="results" style="min-height:60px;" placeholder="Champion de France MX2 2023, Top 5 EMX..."><?= h($ep['results']??'') ?></textarea></div>

    <hr class="sep">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

      <!-- Photo pilote -->
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">📸 Photo du pilote</div>
        <?php if(!empty($ep['photo'])): ?>
        <div style="margin-bottom:10px;"><img src="<?= h($ep['photo']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;display:block;"></div>
        <?php endif; ?>
        <div class="fgrp"><label>Uploader</label><input type="file" name="pphoto" accept="image/*"></div>
        <div class="fgrp"><label>Ou URL</label><input type="text" name="photo" value="<?= h($ep['photo']??'') ?>" placeholder="/img/pilots/…"></div>
      </div>

      <!-- Logo sponsor -->
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">🏷️ Logo sponsor / équipe</div>
        <?php if(!empty($ep['sponsor_logo'])): ?>
        <div style="margin-bottom:10px;background:#f5f5f3;border-radius:8px;padding:8px;display:inline-block;">
          <img src="<?= h($ep['sponsor_logo']) ?>" style="height:40px;max-width:120px;object-fit:contain;display:block;">
        </div>
        <?php endif; ?>
        <div class="fgrp"><label>Uploader (PNG fond transparent)</label><input type="file" name="psponsor" accept="image/*"></div>
        <div class="fgrp"><label>Ou URL</label><input type="text" name="sponsor_logo" value="<?= h($ep['sponsor_logo']??'') ?>" placeholder="/img/pilots/logo.png"></div>
        <span class="hint">S'affiche à côté du nom sur la page portfolio</span>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px;">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="<?= BASE_URL ?>/admin/portfolio.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>
</div>

<script>
// Drag & drop réordonnage
var list = document.getElementById('pilots-sortable');
if (list) {
  var dragged = null;
  list.querySelectorAll('.item-row').forEach(function(row) {
    row.setAttribute('draggable','true');
    row.addEventListener('dragstart', function(){ dragged=row; row.style.opacity='.4'; });
    row.addEventListener('dragend',   function(){ row.style.opacity='1'; saveOrder(); });
    row.addEventListener('dragover',  function(e){
      e.preventDefault();
      var r=row.getBoundingClientRect();
      list.insertBefore(dragged, e.clientY < r.top+r.height/2 ? row : row.nextSibling);
    });
  });
  function saveOrder(){
    var ids=[...list.querySelectorAll('.item-row')].map(r=>r.dataset.id).join(',');
    var fd=new FormData(); fd.append('action','reorder'); fd.append('ids',ids);
    fetch('',{method:'POST',body:fd});
  }
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
