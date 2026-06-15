<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();
$saved  = false;
$action = $_POST['action'] ?? '';

if ($action === 'reorder' && !empty($_POST['ids'])) {
    $ids = array_map('intval', explode(',', $_POST['ids']));
    foreach ($ids as $pos => $id) {
        db()->prepare("UPDATE kk_home_blocks SET position=? WHERE id=?")->execute([$pos, $id]);
    }
    echo json_encode(['ok' => true]); exit;
}
if ($action === 'delete' && !empty($_POST['id'])) {
    db()->prepare("DELETE FROM kk_home_blocks WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . BASE_URL . '/admin/home_blocks.php'); exit;
}
if ($action === 'toggle' && !empty($_POST['id'])) {
    db()->prepare("UPDATE kk_home_blocks SET active = 1 - active WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . BASE_URL . '/admin/home_blocks.php'); exit;
}

if ($action === 'save') {
    $id     = (int)($_POST['id'] ?? 0);
    $type   = trim($_POST['type']    ?? 'text');
    $title  = trim($_POST['title']   ?? '');
    $bg     = trim($_POST['bg']      ?? 'white');
    $layout = trim($_POST['layout']  ?? 'full');
    $btn_l  = trim($_POST['btn_label'] ?? '');
    $btn_u  = trim($_POST['btn_url']   ?? '');
    $active = (int)($_POST['active']   ?? 1);

    // Image upload
    $image = trim($_POST['image_url'] ?? '');
    if (!empty($_FILES['image_upload']['tmp_name'])) {
        $url = upload_media($_FILES['image_upload'], 'media');
        if ($url) $image = $url;
    }

    // Contenu : toujours présent dans le POST, on choisit selon le type
    $content = '';
    $extra   = '{}';

    if ($type === 'text' || $type === 'cta') {
        $content = trim($_POST['content_text'] ?? '');
    } elseif ($type === 'stats') {
        $vals = $_POST['stat_val'] ?? [];
        $lbls = $_POST['stat_lbl'] ?? [];
        $stats = [];
        foreach ($vals as $k => $v) {
            if (trim($v)) $stats[] = ['val' => trim($v), 'lbl' => trim($lbls[$k] ?? '')];
        }
        $content = json_encode($stats, JSON_UNESCAPED_UNICODE);
    } elseif ($type === 'gallery') {
        $imgs = array_values(array_filter(array_map('trim', explode("\n", $_POST['gallery_urls'] ?? ''))));
        if (!empty($_FILES['gallery_upload']['tmp_name'][0])) {
            foreach ($_FILES['gallery_upload']['tmp_name'] as $k => $tmp) {
                if ($tmp) {
                    $f = ['name'=>$_FILES['gallery_upload']['name'][$k],'type'=>$_FILES['gallery_upload']['type'][$k],'tmp_name'=>$tmp,'error'=>0,'size'=>0];
                    $url = upload_media($f, 'media');
                    if ($url) $imgs[] = $url;
                }
            }
        }
        $content = json_encode($imgs, JSON_UNESCAPED_UNICODE);
        $extra   = json_encode(['cols' => (int)($_POST['gallery_cols'] ?? 3)]);
    }
    // separator : content reste vide

    if ($id > 0) {
        db()->prepare("UPDATE kk_home_blocks SET type=?,title=?,content=?,image=?,bg=?,layout=?,btn_label=?,btn_url=?,extra=?,active=? WHERE id=?")
           ->execute([$type,$title,$content,$image,$bg,$layout,$btn_l,$btn_u,$extra,$active,$id]);
    } else {
        $pos = (int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_home_blocks')->fetchColumn();
        db()->prepare("INSERT INTO kk_home_blocks(position,type,title,content,image,bg,layout,btn_label,btn_url,extra,active) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$pos,$type,$title,$content,$image,$bg,$layout,$btn_l,$btn_u,$extra,$active]);
    }
    $saved = true;
}

$blocks  = db()->query('SELECT * FROM kk_home_blocks ORDER BY position')->fetchAll();
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$eb      = null;
if ($edit_id === 0) {
    $eb = ['id'=>0,'type'=>'text','title'=>'','content'=>'','image'=>'','bg'=>'white','layout'=>'full','btn_label'=>'','btn_url'=>'','extra'=>'{}','active'=>1];
} elseif ($edit_id > 0) {
    $s = db()->prepare('SELECT * FROM kk_home_blocks WHERE id=?');
    $s->execute([$edit_id]); $eb = $s->fetch() ?: null;
}

$cur_type = $eb['type'] ?? 'text';
$cur_stats = $cur_type === 'stats' ? jd($eb['content'] ?? '[]', []) : [];
while (count($cur_stats) < 4) $cur_stats[] = ['val'=>'','lbl'=>''];
$cur_gallery = $cur_type === 'gallery' ? jd($eb['content'] ?? '[]', []) : [];
?>
<div class="adm-topbar">
  <h1>🧱 Blocs page d'accueil</h1>
  <a href="?edit=0" class="btn btn-primary btn-sm">+ Ajouter un bloc</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Bloc enregistré. <a href="<?= BASE_URL ?>/" target="_blank">Voir la page →</a></div><?php endif; ?>

<!-- Liste des blocs -->
<div class="card">
  <div class="card-head"><h2>Blocs actifs (<?= count($blocks) ?>)</h2></div>
  <?php if(empty($blocks)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucun bloc. Cliquez sur "+ Ajouter" pour commencer.</p>
  <?php else: ?>
  <div class="item-list" id="sortable-blocks">
    <?php foreach($blocks as $blk):
      $icons = ['text'=>'📝','cta'=>'🎯','stats'=>'📊','gallery'=>'🖼️','separator'=>'—'];
      $preview = $blk['title'] ?: mb_substr(strip_tags($blk['content']??''),0,50);
    ?>
    <div class="item-row" data-id="<?= $blk['id'] ?>" style="opacity:<?= $blk['active']?'1':'.45' ?>;">
      <span style="cursor:grab;color:#ccc;font-size:18px;">⠿</span>
      <span style="font-size:16px;"><?= $icons[$blk['type']] ?? '◻️' ?></span>
      <div class="item-row-name"><?= h($preview ?: '(vide)') ?></div>
      <span class="item-row-badge <?= $blk['active']?'':'draft' ?>"><?= $blk['active']?'Actif':'Masqué' ?></span>
      <div class="item-row-actions">
        <a href="?edit=<?= $blk['id'] ?>" class="btn btn-secondary btn-sm">✏️ Modifier</a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $blk['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm"><?= $blk['active']?'Masquer':'Afficher' ?></button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce bloc ?')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $blk['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- FORMULAIRE ÉDITEUR -->
<?php if($edit_id >= 0 && $eb): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_id > 0 ? '✏️ Modifier le bloc #'.$edit_id : '➕ Nouveau bloc' ?></h2></div>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <!-- ======= TYPE DE BLOC ======= -->
    <div style="margin-bottom:20px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">1. Choisir le type de bloc</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach(['text'=>['📝','Texte / Image'],'cta'=>['🎯','Bannière CTA'],'stats'=>['📊','Stats'],'gallery'=>['🖼️','Galerie'],'separator'=>['—','Séparateur']] as $k=>[$ico,$lbl]): ?>
        <label id="tab-<?= $k ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 18px;border:2px solid <?= $cur_type===$k?'#ed0c0f':'#e8e8e8' ?>;border-radius:10px;cursor:pointer;background:<?= $cur_type===$k?'#fef2f2':'white' ?>;min-width:80px;text-align:center;">
          <input type="radio" name="type" value="<?= $k ?>" <?= $cur_type===$k?'checked':'' ?> style="display:none;" onchange="switchType('<?= $k ?>')">
          <span style="font-size:22px;"><?= $ico ?></span>
          <span style="font-size:11px;font-weight:700;"><?= $lbl ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ======= OPTIONS GÉNÉRALES ======= -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
      <div class="fgrp">
        <label>Fond</label>
        <select name="bg">
          <option value="white" <?= ($eb['bg']??'')==='white'?'selected':''?>>⬜ Blanc</option>
          <option value="gray"  <?= ($eb['bg']??'')==='gray' ?'selected':''?>>🔲 Gris</option>
          <option value="dark"  <?= ($eb['bg']??'')==='dark' ?'selected':''?>>⬛ Noir</option>
          <option value="red"   <?= ($eb['bg']??'')==='red'  ?'selected':''?>>🟥 Rouge</option>
        </select>
      </div>
      <div class="fgrp" id="layout-wrap" style="<?= in_array($cur_type,['text','cta'])?'':'display:none;' ?>">
        <label>Disposition</label>
        <select name="layout">
          <option value="full"     <?= ($eb['layout']??'')==='full'    ?'selected':''?>>Pleine largeur</option>
          <option value="center"   <?= ($eb['layout']??'')==='center'  ?'selected':''?>>Centré</option>
          <option value="2col"     <?= ($eb['layout']??'')==='2col'    ?'selected':''?>>2 col. texte|image</option>
          <option value="2col-rev" <?= ($eb['layout']??'')==='2col-rev'?'selected':''?>>2 col. inversées</option>
        </select>
      </div>
      <div class="fgrp">
        <label>Visibilité</label>
        <select name="active">
          <option value="1" <?= ($eb['active']??1)?'selected':''?>>👁 Visible</option>
          <option value="0" <?= !($eb['active']??1)?'selected':''?>>🙈 Masqué</option>
        </select>
      </div>
    </div>

    <!-- ======= SECTION TEXTE/CTA ======= -->
    <div id="section-text" style="<?= in_array($cur_type,['text','cta'])?'':'display:none;' ?>">
      <div style="background:#f9f9f9;border-radius:10px;padding:16px;margin-bottom:12px;">
        <div class="fgrp" style="margin-bottom:10px;">
          <label>Titre du bloc</label>
          <input type="text" name="title" value="<?= h($eb['title']??'') ?>" placeholder="ex: Notre expertise">
        </div>
        <div class="fgrp" style="margin-bottom:10px;">
          <label>Texte / Contenu ✍️</label>
          <textarea name="content_text" style="min-height:140px;width:100%;border:1.5px solid #ddd;border-radius:8px;padding:10px;font-size:13px;font-family:inherit;resize:vertical;outline:none;"><?= h(in_array($cur_type,['text','cta'])?($eb['content']??')':'') ?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="fgrp">
            <label>Label du bouton (optionnel)</label>
            <input type="text" name="btn_label" value="<?= h($eb['btn_label']??'') ?>" placeholder="En savoir plus">
          </div>
          <div class="fgrp">
            <label>URL du bouton</label>
            <input type="text" name="btn_url" value="<?= h($eb['btn_url']??'') ?>" placeholder="/contact.php">
          </div>
        </div>
      </div>
      <div style="background:#f9f9f9;border-radius:10px;padding:16px;">
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Image (optionnel)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="fgrp">
            <label>URL de l'image</label>
            <input type="text" name="image_url" value="<?= h($eb['image']??'') ?>" placeholder="/img/media/photo.jpg">
            <?php if(!empty($eb['image'])): ?><div style="margin-top:8px;"><img src="<?= h($eb['image']) ?>" style="max-height:80px;border-radius:6px;"></div><?php endif; ?>
          </div>
          <div class="fgrp">
            <label>Uploader une image</label>
            <input type="file" name="image_upload" accept="image/*">
          </div>
        </div>
      </div>
    </div>

    <!-- ======= SECTION STATS ======= -->
    <div id="section-stats" style="<?= $cur_type==='stats'?'':'display:none;' ?>">
      <div style="background:#f9f9f9;border-radius:10px;padding:16px;">
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:12px;">Chiffres clés (jusqu'à 4)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <?php foreach($cur_stats as $k => $st): ?>
          <div style="background:white;border-radius:8px;padding:12px;">
            <div class="fgrp" style="margin-bottom:8px;">
              <label>Valeur <?= $k+1 ?> (ex: 500+)</label>
              <input type="text" name="stat_val[]" value="<?= h($st['val']??'') ?>" placeholder="500+">
            </div>
            <div class="fgrp">
              <label>Label</label>
              <input type="text" name="stat_lbl[]" value="<?= h($st['lbl']??'') ?>" placeholder="pilotes équipés">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ======= SECTION GALERIE ======= -->
    <div id="section-gallery" style="<?= $cur_type==='gallery'?'':'display:none;' ?>">
      <div style="background:#f9f9f9;border-radius:10px;padding:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div class="fgrp">
            <label>Nombre de colonnes</label>
            <select name="gallery_cols">
              <?php foreach([2,3,4] as $n): ?><option value="<?= $n ?>" <?= (jd($eb['extra']??'{}',['cols'=>3])['cols']==$n)?'selected':''?>><?= $n ?> colonnes</option><?php endforeach; ?>
            </select>
          </div>
          <div class="fgrp">
            <label>Uploader des images</label>
            <input type="file" name="gallery_upload[]" multiple accept="image/*">
          </div>
        </div>
        <div class="fgrp">
          <label>URLs d'images existantes (1 par ligne)</label>
          <textarea name="gallery_urls" style="min-height:80px;width:100%;border:1.5px solid #ddd;border-radius:8px;padding:10px;font-size:12px;font-family:monospace;resize:vertical;outline:none;"><?= h(implode("\n", $cur_gallery)) ?></textarea>
        </div>
      </div>
    </div>

    <!-- ======= SECTION SÉPARATEUR ======= -->
    <div id="section-separator" style="<?= $cur_type==='separator'?'':'display:none;' ?>">
      <div style="background:#f9f9f9;border-radius:10px;padding:16px;text-align:center;color:var(--muted);font-size:13px;">
        Ce bloc ajoute un espace de séparation entre les sections de la page.
      </div>
    </div>

    <!-- BOUTON SUBMIT -->
    <div style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f0;">
      <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer le bloc</button>
      <a href="<?= BASE_URL ?>/admin/home_blocks.php" class="btn btn-secondary btn-lg">Annuler</a>
      <?php if($edit_id > 0): ?>
      <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-ghost btn-lg">👁 Voir le site</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>
</div>

<script>
var sectionIds = ['text','stats','gallery','separator'];

function switchType(type) {
  // Montrer la bonne section
  sectionIds.forEach(function(s) {
    var el = document.getElementById('section-' + s);
    if (el) el.style.display = (s === type || (s === 'text' && type === 'cta')) ? 'block' : 'none';
  });
  // Layout uniquement pour text/cta
  var lw = document.getElementById('layout-wrap');
  if (lw) lw.style.display = (type === 'text' || type === 'cta') ? 'block' : 'none';
  // Mettre à jour les onglets visuels
  ['text','cta','stats','gallery','separator'].forEach(function(k) {
    var tab = document.getElementById('tab-' + k);
    if (!tab) return;
    var sel = (k === type);
    tab.style.borderColor = sel ? '#ed0c0f' : '#e8e8e8';
    tab.style.background  = sel ? '#fef2f2' : 'white';
    var inp = tab.querySelector('input[type="radio"]');
    if (inp) inp.checked = sel;
  });
}
// Init : s'assurer que la bonne section est visible au chargement
switchType('<?= $cur_type ?>');

// Drag & drop réordonnage
var list = document.getElementById('sortable-blocks');
if (list) {
  var dragged = null;
  list.querySelectorAll('.item-row').forEach(function(row) {
    row.setAttribute('draggable', 'true');
    row.addEventListener('dragstart', function() { dragged = row; row.style.opacity = '.4'; });
    row.addEventListener('dragend',   function() { row.style.opacity = '1'; saveOrder(); });
    row.addEventListener('dragover',  function(e) {
      e.preventDefault();
      var r = row.getBoundingClientRect();
      list.insertBefore(dragged, e.clientY < r.top + r.height/2 ? row : row.nextSibling);
    });
  });
  function saveOrder() {
    var ids = [...list.querySelectorAll('.item-row')].map(r => r.dataset.id).join(',');
    var fd  = new FormData(); fd.append('action', 'reorder'); fd.append('ids', ids);
    fetch('', { method: 'POST', body: fd });
  }
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
