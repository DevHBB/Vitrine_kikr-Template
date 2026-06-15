<?php
require_once __DIR__ . '/layout.php';

$slug  = trim($_GET['slug'] ?? '');
$page  = null;
$saved = false;
$is_new = true;

if ($slug) {
    $page = get_page_by_slug($slug);
    if ($page) { $is_new = false; }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $new_slug = trim($_POST['slug']  ?? ($slug ?: slugify($title)));
    $subtitle = trim($_POST['subtitle'] ?? '');
    $banner   = trim($_POST['banner'] ?? '');
    $status   = $_POST['status'] ?? 'draft';

    // Parse blocks from POST
    $blocks = [];
    $types   = $_POST['blk_type']   ?? [];
    $ids     = $_POST['blk_id']     ?? [];
    $bgs     = $_POST['blk_bg']     ?? [];

    foreach ($types as $i => $type) {
        $id = $ids[$i] ?? ('b' . uniqid());
        $bg = $bgs[$i] ?? 'white';
        $blk = ['id'=>$id,'type'=>$type,'bg'=>$bg];

        switch ($type) {
            case 'heading':
                $blk['text']  = trim($_POST["blk_{$i}_text"]  ?? '');
                $blk['level'] = (int)($_POST["blk_{$i}_level"] ?? 2);
                $blk['align'] = $_POST["blk_{$i}_align"] ?? 'left';
                break;
            case 'text':
                $blk['content'] = trim($_POST["blk_{$i}_content"] ?? '');
                $blk['align']   = $_POST["blk_{$i}_align"] ?? 'left';
                break;
            case 'image':
                $blk['src']       = trim($_POST["blk_{$i}_src"]       ?? '');
                $blk['alt']       = trim($_POST["blk_{$i}_alt"]       ?? '');
                $blk['caption']   = trim($_POST["blk_{$i}_caption"]   ?? '');
                $blk['max_width'] = trim($_POST["blk_{$i}_max_width"] ?? '100%');
                // Handle upload
                if (!empty($_FILES["blk_{$i}_upload"]['tmp_name'])) {
                    $url = upload_media($_FILES["blk_{$i}_upload"]);
                    if ($url) $blk['src'] = $url;
                }
                break;
            case 'separator':
                $blk['red'] = isset($_POST["blk_{$i}_red"]);
                break;
            case 'cta':
                $blk['title']     = trim($_POST["blk_{$i}_title"]     ?? '');
                $blk['subtitle']  = trim($_POST["blk_{$i}_subtitle"]  ?? '');
                $blk['btn_label'] = trim($_POST["blk_{$i}_btn_label"] ?? '');
                $blk['btn_url']   = trim($_POST["blk_{$i}_btn_url"]   ?? '');
                break;
            case 'gallery':
                $blk['cols'] = (int)($_POST["blk_{$i}_cols"] ?? 3);
                $raw = array_filter(array_map('trim', explode("\n", $_POST["blk_{$i}_images"] ?? '')));
                $blk['images'] = array_values($raw);
                // Multi-upload
                if (!empty($_FILES["blk_{$i}_uploads"]['name'][0])) {
                    foreach ($_FILES["blk_{$i}_uploads"]['tmp_name'] as $k => $tmp) {
                        if ($tmp) {
                            $f = ['name'=>$_FILES["blk_{$i}_uploads"]['name'][$k],'type'=>$_FILES["blk_{$i}_uploads"]['type'][$k],'tmp_name'=>$tmp,'error'=>0,'size'=>$_FILES["blk_{$i}_uploads"]['size'][$k]];
                            $url = upload_media($f);
                            if ($url) $blk['images'][] = $url;
                        }
                    }
                }
                break;
            case 'columns':
                $num_cols = (int)($_POST["blk_{$i}_num_cols"] ?? 2);
                $cols = [];
                for ($c = 0; $c < $num_cols; $c++) {
                    $col = [
                        'title' => trim($_POST["blk_{$i}_col{$c}_title"] ?? ''),
                        'text'  => trim($_POST["blk_{$i}_col{$c}_text"]  ?? ''),
                        'image' => trim($_POST["blk_{$i}_col{$c}_image"] ?? ''),
                    ];
                    if (!empty($_FILES["blk_{$i}_col{$c}_upload"]['tmp_name'])) {
                        $url = upload_media($_FILES["blk_{$i}_col{$c}_upload"]);
                        if ($url) $col['image'] = $url;
                    }
                    $cols[] = $col;
                }
                $blk['cols'] = $cols;
                $blk['num_cols'] = $num_cols;
                break;
            case 'video':
                $blk['url'] = trim($_POST["blk_{$i}_url"] ?? '');
                break;
            case 'accordion':
                $qs = $_POST["blk_{$i}_q"] ?? [];
                $as = $_POST["blk_{$i}_a"] ?? [];
                $items = [];
                foreach ($qs as $k => $q) {
                    if (trim($q)) $items[] = ['q'=>trim($q),'a'=>trim($as[$k]??'')];
                }
                $blk['items'] = $items;
                break;
            case 'banner_hero':
                $blk['title']     = trim($_POST["blk_{$i}_title"]     ?? '');
                $blk['subtitle']  = trim($_POST["blk_{$i}_subtitle"]  ?? '');
                $blk['btn_label'] = trim($_POST["blk_{$i}_btn_label"] ?? '');
                $blk['btn_url']   = trim($_POST["blk_{$i}_btn_url"]   ?? '');
                $blk['image']     = trim($_POST["blk_{$i}_image"]     ?? '');
                if (!empty($_FILES["blk_{$i}_img_upload"]['tmp_name'])) {
                    $url = upload_media($_FILES["blk_{$i}_img_upload"]);
                    if ($url) $blk['image'] = $url;
                }
                break;
        }
        $blocks[] = $blk;
    }

    // Banner upload for page hero
    if (!empty($_FILES['banner_upload']['tmp_name'])) {
        $url = upload_media($_FILES['banner_upload']);
        if ($url) $banner = $url;
    }

    $new_page = [
        'title'    => $title,
        'slug'     => $new_slug,
        'subtitle' => $subtitle,
        'banner'   => $banner,
        'status'   => $status,
        'blocks'   => $blocks,
        'updated'  => date('Y-m-d H:i'),
    ];

    // MySQL upsert
    $existing = db()->prepare("SELECT id FROM kk_pages WHERE slug=?");
    $existing->execute([$new_slug]);
    $exists = $existing->fetchColumn();
    if ($exists) {
        db()->prepare("UPDATE kk_pages SET title=?,subtitle=?,banner=?,status=?,blocks=?,updated_at=NOW() WHERE slug=?")
           ->execute([$title,$subtitle,$banner,$status,json_encode($blocks,JSON_UNESCAPED_UNICODE),$new_slug]);
    } else {
        db()->prepare("INSERT INTO kk_pages(slug,title,subtitle,banner,status,blocks) VALUES(?,?,?,?,?,?)")
           ->execute([$new_slug,$title,$subtitle,$banner,$status,json_encode($blocks,JSON_UNESCAPED_UNICODE)]);
    }
    $slug   = $new_slug;
    $page   = $new_page;
    $is_new = false;
    $saved  = true;
}

$blocks = $page['blocks'] ?? [];

// Block type labels
$blk_labels = [
    'heading'     => '🔤 Titre',
    'text'        => '📝 Texte',
    'image'       => '🖼️ Image',
    'separator'   => '— Séparateur',
    'cta'         => '🎯 Bouton CTA',
    'gallery'     => '🖼️ Galerie',
    'columns'     => '⬛ Colonnes',
    'video'       => '▶️ Vidéo',
    'accordion'   => '📂 Accordéon',
    'banner_hero' => '🏔️ Bannière Hero',
];
?>
<div class="adm-topbar">
  <h1><?= $is_new ? '➕ Nouvelle page' : '✏️ ' . h($page['title'] ?? '') ?></h1>
  <div style="display:flex;gap:8px;">
    <?php if(!$is_new): ?>
    <a href="<?= BASE_URL ?>/page.php?slug=<?= h($slug) ?>" target="_blank" class="btn btn-secondary btn-sm">👁 Prévisualiser</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/pages.php" class="btn btn-ghost btn-sm">← Retour</a>
  </div>
</div>
<div class="adm-content">
  <?php if($saved): ?><div class="alert alert-ok">✅ Page enregistrée. <a href="<?= BASE_URL ?>/page.php?slug=<?= h($slug) ?>" target="_blank">Voir la page →</a></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="page-form">
  <!-- Page settings -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">⚙️</span> Paramètres de la page</h2></div>
    <div class="g2">
      <div class="fgrp"><label>Titre *</label><input type="text" name="title" value="<?= h($page['title']??'') ?>" required id="inp-title" oninput="autoSlug()"></div>
      <div class="fgrp"><label>Slug URL (auto)</label><input type="text" name="slug" value="<?= h($page['slug']??'') ?>" id="inp-slug" required></div>
      <div class="fgrp"><label>Sous-titre / description</label><input type="text" name="subtitle" value="<?= h($page['subtitle']??'') ?>"></div>
      <div class="fgrp">
        <label>Statut</label>
        <select name="status">
          <option value="draft"     <?= ($page['status']??'')==='draft'     ?'selected':'' ?>>Brouillon</option>
          <option value="published" <?= ($page['status']??'')==='published' ?'selected':'' ?>>Publié</option>
        </select>
      </div>
      <div class="fgrp full"><label>Image bannière de la page (URL ou upload)</label>
        <input type="text" name="banner" value="<?= h($page['banner']??'') ?>" placeholder="/img/media/…">
        <input type="file" name="banner_upload" accept="image/*" style="margin-top:6px;">
        <?php if(!empty($page['banner'])): ?><div class="img-preview"><img src="<?= h($page['banner']) ?>"></div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Block list -->
  <div class="card" style="margin-top:16px;">
    <div class="card-head"><h2><span class="icon">🧱</span> Blocs de contenu</h2></div>
    <p class="card-hint">Les blocs s'affichent dans l'ordre ci-dessous. Vous pouvez en ajouter, modifier ou supprimer.</p>

    <div class="blk-list" id="blk-list">
    <?php foreach ($blocks as $i => $blk):
      $type  = $blk['type'] ?? 'text';
      $label = $blk_labels[$type] ?? $type;
      switch($type) {
        case 'heading':     $preview = $blk['text'] ?? ''; break;
        case 'text':        $preview = mb_substr($blk['content']??'', 0, 60); break;
        case 'image':       $preview = $blk['src'] ?? '(image)'; break;
        case 'cta':         $preview = $blk['title'] ?? ''; break;
        case 'gallery':     $preview = count($blk['images']??[]) . ' image(s)'; break;
        case 'columns':     $preview = count($blk['cols']??[]) . ' colonne(s)'; break;
        case 'video':       $preview = $blk['url'] ?? ''; break;
        case 'accordion':   $preview = count($blk['items']??[]) . ' entrée(s)'; break;
        case 'banner_hero': $preview = $blk['title'] ?? ''; break;
        default:            $preview = '';
      }
    ?>
    <div class="blk-item" id="bi-<?= $i ?>">
      <input type="hidden" name="blk_type[]" value="<?= h($type) ?>">
      <input type="hidden" name="blk_id[]"   value="<?= h($blk['id']??'') ?>">
      <div class="blk-item-head" onclick="toggleBlk(<?= $i ?>)">
        <span class="blk-item-drag">⠿</span>
        <span class="blk-item-type"><?= $label ?></span>
        <span class="blk-item-preview"><?= h($preview) ?></span>
        <button type="button" class="btn btn-danger btn-sm" onclick="event.stopPropagation();removeBlk(<?= $i ?>)" title="Supprimer">✕</button>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0;transition:transform .2s;" id="chev-<?= $i ?>"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="blk-item-body" id="body-<?= $i ?>">
        <?php
        // Background selector (common to all)
        $bgv = $blk['bg'] ?? 'white';
        echo '<div class="fgrp" style="margin-bottom:14px;"><label>Fond de section</label>
        <select name="blk_'.$i.'_bg" onchange="document.querySelector(\'[name=\\\'blk_bg[]\\\'\',this.closest(\'.blk-item\'))">
          <option value="white"'.($bgv==='white'?' selected':'').'>Blanc</option>
          <option value="gray"'.($bgv==='gray'?' selected':'').'>Gris clair</option>
          <option value="dark"'.($bgv==='dark'?' selected':'').'>Noir</option>
        </select></div>';
        // hidden bg field for each block
        ?>
        <input type="hidden" name="blk_bg[]" value="<?= h($bgv) ?>" id="bg-<?= $i ?>">
        <script>
        document.querySelectorAll('[name="blk_<?= $i ?>_bg"]').forEach(s=>s.addEventListener('change',e=>document.getElementById('bg-<?= $i ?>').value=e.target.value));
        </script>

        <?php switch($type):
          case 'heading': ?>
          <div class="g2">
            <div class="fgrp full"><label>Texte du titre</label><input type="text" name="blk_<?=$i?>_text" value="<?= h($blk['text']??'') ?>"></div>
            <div class="fgrp"><label>Niveau</label>
              <select name="blk_<?=$i?>_level">
                <option value="2" <?=($blk['level']??2)==2?'selected':''?>>H2 — Grand titre</option>
                <option value="3" <?=($blk['level']??2)==3?'selected':''?>>H3 — Sous-titre</option>
              </select>
            </div>
            <div class="fgrp"><label>Alignement</label>
              <select name="blk_<?=$i?>_align">
                <option value="left"   <?=($blk['align']??'')==='left'  ?'selected':''?>>Gauche</option>
                <option value="center" <?=($blk['align']??'')==='center'?'selected':''?>>Centre</option>
                <option value="right"  <?=($blk['align']??'')==='right' ?'selected':''?>>Droite</option>
              </select>
            </div>
          </div>

          <?php break; case 'text': ?>
          <div class="fgrp"><label>Contenu (texte)</label><textarea name="blk_<?=$i?>_content" style="min-height:100px;"><?= h($blk['content']??'') ?></textarea></div>
          <div class="fgrp"><label>Alignement</label><select name="blk_<?=$i?>_align"><option value="left" <?=($blk['align']??'')==='left'?'selected':''?>>Gauche</option><option value="center" <?=($blk['align']??'')==='center'?'selected':''?>>Centre</option></select></div>

          <?php break; case 'image': ?>
          <div class="g2">
            <div class="fgrp full"><label>URL de l'image</label><input type="text" name="blk_<?=$i?>_src" value="<?= h($blk['src']??'') ?>" placeholder="/img/media/…"></div>
            <div class="fgrp full"><label>Ou uploader une image</label><input type="file" name="blk_<?=$i?>_upload" accept="image/*"></div>
            <?php if(!empty($blk['src'])): ?><div class="img-preview full"><img src="<?= h($blk['src']) ?>"></div><?php endif; ?>
            <div class="fgrp"><label>Texte alt</label><input type="text" name="blk_<?=$i?>_alt" value="<?= h($blk['alt']??'') ?>"></div>
            <div class="fgrp"><label>Légende</label><input type="text" name="blk_<?=$i?>_caption" value="<?= h($blk['caption']??'') ?>"></div>
            <div class="fgrp"><label>Largeur max (ex: 600px, 80%)</label><input type="text" name="blk_<?=$i?>_max_width" value="<?= h($blk['max_width']??'100%') ?>"></div>
          </div>

          <?php break; case 'separator': ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;">
            <input type="checkbox" name="blk_<?=$i?>_red" <?= !empty($blk['red'])?'checked':'' ?>>
            Séparateur rouge
          </label>

          <?php break; case 'cta': ?>
          <div class="g2">
            <div class="fgrp"><label>Titre</label><input type="text" name="blk_<?=$i?>_title" value="<?= h($blk['title']??'') ?>"></div>
            <div class="fgrp"><label>Sous-titre</label><input type="text" name="blk_<?=$i?>_subtitle" value="<?= h($blk['subtitle']??'') ?>"></div>
            <div class="fgrp"><label>Label du bouton</label><input type="text" name="blk_<?=$i?>_btn_label" value="<?= h($blk['btn_label']??'') ?>"></div>
            <div class="fgrp"><label>URL du bouton</label><input type="text" name="blk_<?=$i?>_btn_url" value="<?= h($blk['btn_url']??'#') ?>"></div>
          </div>

          <?php break; case 'gallery': ?>
          <div class="g2">
            <div class="fgrp"><label>Colonnes (2, 3 ou 4)</label><input type="number" name="blk_<?=$i?>_cols" value="<?= h($blk['cols']??3) ?>" min="2" max="4"></div>
            <div class="fgrp"><label>Uploader des images</label><input type="file" name="blk_<?=$i?>_uploads[]" multiple accept="image/*"></div>
            <div class="fgrp full"><label>URLs existantes (1 par ligne)</label><textarea name="blk_<?=$i?>_images" style="min-height:80px;"><?= h(implode("\n", $blk['images']??[])) ?></textarea></div>
          </div>
          <?php if(!empty($blk['images'])): ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
            <?php foreach($blk['images'] as $img): ?><img src="<?= h($img) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;"><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php break; case 'columns': ?>
          <?php $ncols = $blk['num_cols'] ?? count($blk['cols']??[2,2]); ?>
          <div class="fgrp" style="margin-bottom:12px;"><label>Nombre de colonnes</label><input type="number" name="blk_<?=$i?>_num_cols" value="<?= $ncols ?>" min="2" max="3"></div>
          <?php for($c=0;$c<$ncols;$c++): $col=$blk['cols'][$c]??[]; ?>
          <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:8px;">COLONNE <?= $c+1 ?></div>
            <div class="g2">
              <div class="fgrp"><label>Titre</label><input type="text" name="blk_<?=$i?>_col<?=$c?>_title" value="<?= h($col['title']??'') ?>"></div>
              <div class="fgrp"><label>Image URL ou upload</label>
                <input type="text" name="blk_<?=$i?>_col<?=$c?>_image" value="<?= h($col['image']??'') ?>" placeholder="/img/media/…">
                <input type="file" name="blk_<?=$i?>_col<?=$c?>_upload" accept="image/*" style="margin-top:4px;">
              </div>
              <div class="fgrp full"><label>Texte</label><textarea name="blk_<?=$i?>_col<?=$c?>_text"><?= h($col['text']??'') ?></textarea></div>
            </div>
          </div>
          <?php endfor; ?>

          <?php break; case 'video': ?>
          <div class="fgrp"><label>URL YouTube ou embed (ex: https://www.youtube.com/watch?v=…)</label><input type="text" name="blk_<?=$i?>_url" value="<?= h($blk['url']??'') ?>" placeholder="https://youtube.com/watch?v=…"></div>

          <?php break; case 'accordion': ?>
          <?php $items = $blk['items'] ?? [['q'=>'','a'=>'']]; ?>
          <div id="acc-items-<?= $i ?>">
          <?php foreach($items as $k=>$item): ?>
          <div style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:6px;">
            <div class="fgrp" style="margin-bottom:6px;"><label>Question <?=$k+1?></label><input type="text" name="blk_<?=$i?>_q[]" value="<?= h($item['q']??'') ?>"></div>
            <div class="fgrp"><label>Réponse</label><textarea name="blk_<?=$i?>_a[]"><?= h($item['a']??'') ?></textarea></div>
          </div>
          <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="addAccItem(<?=$i?>)">+ Ajouter une entrée</button>

          <?php break; case 'banner_hero': ?>
          <div class="g2">
            <div class="fgrp"><label>Titre</label><input type="text" name="blk_<?=$i?>_title" value="<?= h($blk['title']??'') ?>"></div>
            <div class="fgrp"><label>Sous-titre</label><input type="text" name="blk_<?=$i?>_subtitle" value="<?= h($blk['subtitle']??'') ?>"></div>
            <div class="fgrp"><label>Label bouton</label><input type="text" name="blk_<?=$i?>_btn_label" value="<?= h($blk['btn_label']??'') ?>"></div>
            <div class="fgrp"><label>URL bouton</label><input type="text" name="blk_<?=$i?>_btn_url" value="<?= h($blk['btn_url']??'#') ?>"></div>
            <div class="fgrp full"><label>Image de fond (URL ou upload)</label>
              <input type="text" name="blk_<?=$i?>_image" value="<?= h($blk['image']??'') ?>" placeholder="/img/media/…">
              <input type="file" name="blk_<?=$i?>_img_upload" accept="image/*" style="margin-top:5px;">
              <?php if(!empty($blk['image'])): ?><div class="img-preview"><img src="<?= h($blk['image']) ?>"></div><?php endif; ?>
            </div>
          </div>

        <?php break; endswitch; ?>
      </div>
    </div>
    <?php endforeach; ?>
    </div><!-- #blk-list -->

    <!-- Add block buttons -->
    <div style="margin-top:14px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Ajouter un bloc</div>
      <div class="blk-add-bar">
        <?php foreach($blk_labels as $type => $label): ?>
        <button type="button" class="blk-add-btn" onclick="addBlock('<?= $type ?>')"><?= $label ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:16px;">
    <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer la page</button>
    <?php if(!$is_new): ?><a href="<?= BASE_URL ?>/page.php?slug=<?= h($slug) ?>" target="_blank" class="btn btn-secondary btn-lg">👁 Voir la page</a><?php endif; ?>
  </div>
  </form>
</div>

<script>
// Auto-slug from title
function autoSlug() {
  const t = document.getElementById('inp-title').value;
  const s = t.toLowerCase()
    .replace(/[àáâãäå]/g,'a').replace(/[éèêë]/g,'e')
    .replace(/[îïì]/g,'i').replace(/[ôõö]/g,'o')
    .replace(/[ùúûü]/g,'u').replace(/[ç]/g,'c')
    .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  document.getElementById('inp-slug').value = s;
}

// Toggle block open/close
function toggleBlk(i) {
  const b = document.getElementById('bi-'+i);
  const body = document.getElementById('body-'+i);
  const chev = document.getElementById('chev-'+i);
  const open = b.classList.toggle('open');
  body.style.display = open ? 'block' : 'none';
  chev.style.transform = open ? 'rotate(180deg)' : '';
}

// Remove block
function removeBlk(i) {
  if (!confirm('Supprimer ce bloc ?')) return;
  document.getElementById('bi-'+i).remove();
}

let blkCount = <?= count($blocks) ?>;

function addBlock(type) {
  const i = blkCount++;
  const labels = <?= json_encode($blk_labels) ?>;
  const label = labels[type] || type;

  let inner = `
    <input type="hidden" name="blk_bg[]" value="white" id="bg-${i}">
    <div class="fgrp" style="margin-bottom:14px;"><label>Fond</label>
    <select onchange="document.getElementById('bg-${i}').value=this.value">
      <option value="white">Blanc</option><option value="gray">Gris</option><option value="dark">Noir</option>
    </select></div>`;

  switch(type) {
    case 'heading':
      inner += `<div class="g2">
        <div class="fgrp full"><label>Titre</label><input type="text" name="blk_${i}_text" value=""></div>
        <div class="fgrp"><label>Niveau</label><select name="blk_${i}_level"><option value="2">H2 Grand</option><option value="3">H3 Sous</option></select></div>
        <div class="fgrp"><label>Alignement</label><select name="blk_${i}_align"><option value="left">Gauche</option><option value="center">Centre</option></select></div>
      </div>`; break;
    case 'text':
      inner += `<div class="fgrp"><label>Contenu</label><textarea name="blk_${i}_content" style="min-height:100px;"></textarea></div>
      <div class="fgrp"><label>Alignement</label><select name="blk_${i}_align"><option value="left">Gauche</option><option value="center">Centre</option></select></div>`; break;
    case 'image':
      inner += `<div class="g2">
        <div class="fgrp full"><label>URL image</label><input type="text" name="blk_${i}_src" placeholder="/img/media/…"></div>
        <div class="fgrp full"><label>Uploader</label><input type="file" name="blk_${i}_upload" accept="image/*"></div>
        <div class="fgrp"><label>Alt</label><input type="text" name="blk_${i}_alt"></div>
        <div class="fgrp"><label>Légende</label><input type="text" name="blk_${i}_caption"></div>
        <div class="fgrp"><label>Largeur max</label><input type="text" name="blk_${i}_max_width" value="100%"></div>
      </div>`; break;
    case 'separator':
      inner += `<label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;"><input type="checkbox" name="blk_${i}_red"> Séparateur rouge</label>`; break;
    case 'cta':
      inner += `<div class="g2">
        <div class="fgrp"><label>Titre</label><input type="text" name="blk_${i}_title"></div>
        <div class="fgrp"><label>Sous-titre</label><input type="text" name="blk_${i}_subtitle"></div>
        <div class="fgrp"><label>Label bouton</label><input type="text" name="blk_${i}_btn_label"></div>
        <div class="fgrp"><label>URL bouton</label><input type="text" name="blk_${i}_btn_url" value="#"></div>
      </div>`; break;
    case 'gallery':
      inner += `<div class="g2">
        <div class="fgrp"><label>Colonnes (2-4)</label><input type="number" name="blk_${i}_cols" value="3" min="2" max="4"></div>
        <div class="fgrp"><label>Uploader</label><input type="file" name="blk_${i}_uploads[]" multiple accept="image/*"></div>
        <div class="fgrp full"><label>URLs (1 par ligne)</label><textarea name="blk_${i}_images" style="min-height:60px;"></textarea></div>
      </div>`; break;
    case 'columns':
      inner += `<div class="fgrp" style="margin-bottom:10px;"><label>Nb colonnes</label><input type="number" name="blk_${i}_num_cols" value="2" min="2" max="3"></div>
      ${[0,1].map(c=>`<div style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:6px;">
        <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:8px;">COL ${c+1}</div>
        <div class="g2">
          <div class="fgrp"><label>Titre</label><input type="text" name="blk_${i}_col${c}_title"></div>
          <div class="fgrp"><label>Image URL</label><input type="text" name="blk_${i}_col${c}_image" placeholder="/img/media/…">
          <input type="file" name="blk_${i}_col${c}_upload" accept="image/*" style="margin-top:4px;"></div>
          <div class="fgrp full"><label>Texte</label><textarea name="blk_${i}_col${c}_text"></textarea></div>
        </div></div>`).join('')}`; break;
    case 'video':
      inner += `<div class="fgrp"><label>URL YouTube</label><input type="text" name="blk_${i}_url" placeholder="https://youtube.com/watch?v=…"></div>`; break;
    case 'accordion':
      inner += `<div id="acc-items-${i}">
        <div style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:6px;">
          <div class="fgrp" style="margin-bottom:6px;"><label>Question 1</label><input type="text" name="blk_${i}_q[]"></div>
          <div class="fgrp"><label>Réponse</label><textarea name="blk_${i}_a[]"></textarea></div>
        </div></div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addAccItem(${i})">+ Ajouter</button>`; break;
    case 'banner_hero':
      inner += `<div class="g2">
        <div class="fgrp"><label>Titre</label><input type="text" name="blk_${i}_title"></div>
        <div class="fgrp"><label>Sous-titre</label><input type="text" name="blk_${i}_subtitle"></div>
        <div class="fgrp"><label>Bouton label</label><input type="text" name="blk_${i}_btn_label"></div>
        <div class="fgrp"><label>Bouton URL</label><input type="text" name="blk_${i}_btn_url" value="#"></div>
        <div class="fgrp full"><label>Image fond (URL ou upload)</label>
          <input type="text" name="blk_${i}_image" placeholder="/img/media/…">
          <input type="file" name="blk_${i}_img_upload" accept="image/*" style="margin-top:5px;"></div>
      </div>`; break;
  }

  const div = document.createElement('div');
  div.className = 'blk-item open';
  div.id = 'bi-'+i;
  div.innerHTML = `
    <input type="hidden" name="blk_type[]" value="${type}">
    <input type="hidden" name="blk_id[]" value="">
    <div class="blk-item-head" onclick="toggleBlk(${i})">
      <span class="blk-item-drag">⠿</span>
      <span class="blk-item-type">${label}</span>
      <span class="blk-item-preview"></span>
      <button type="button" class="btn btn-danger btn-sm" onclick="event.stopPropagation();removeBlk(${i})">✕</button>
    </div>
    <div class="blk-item-body" id="body-${i}" style="display:block;">${inner}</div>`;
  document.getElementById('blk-list').appendChild(div);
  div.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function addAccItem(blkIdx) {
  const c = document.getElementById('acc-items-'+blkIdx);
  const k = c.querySelectorAll('[name^="blk_'+blkIdx+'_q"]').length;
  const d = document.createElement('div');
  d.style.cssText = 'background:var(--bg);border-radius:8px;padding:10px;margin-bottom:6px;';
  d.innerHTML = `<div class="fgrp" style="margin-bottom:6px;"><label>Question ${k+1}</label><input type="text" name="blk_${blkIdx}_q[]"></div>
  <div class="fgrp"><label>Réponse</label><textarea name="blk_${blkIdx}_a[]"></textarea></div>`;
  c.appendChild(d);
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
