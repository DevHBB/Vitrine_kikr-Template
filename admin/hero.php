<?php
require_once __DIR__ . '/layout.php';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();

    // ---- Hero principal ----
    $pdo->prepare("UPDATE kk_hero SET
        title_line1=?, title_line2=?, model_sub=?, model_code=?, model_desc=?,
        btn_label=?, reviews_count=?, reviews_text=?,
        moto_left=?, moto_width=?, moto_anim=?, moto_anim_delay=?
        WHERE id=1")->execute([
        trim($_POST['title_line1'] ?? ''),
        trim($_POST['title_line2'] ?? ''),
        trim($_POST['model_sub']   ?? ''),
        trim($_POST['model_code']  ?? ''),
        trim($_POST['model_desc']  ?? ''),
        trim($_POST['btn_label']   ?? ''),
        trim($_POST['reviews_count'] ?? ''),
        trim($_POST['reviews_text']  ?? ''),
        trim($_POST['moto_left']  ?? '100px'),
        trim($_POST['moto_width'] ?? '790px'),
        trim($_POST['moto_anim']  ?? 'slide-up'),
        (float)($_POST['moto_anim_delay'] ?? 0.15),
    ]);

    // ---- Photo moto ----
    if (!empty($_FILES['moto_image_upload']['tmp_name'])) {
        $url = upload_media($_FILES['moto_image_upload'], 'hero');
        if ($url) $pdo->prepare("UPDATE kk_hero SET moto_image=? WHERE id=1")->execute([$url]);
    } elseif (trim($_POST['moto_image'] ?? '') !== '') {
        $pdo->prepare("UPDATE kk_hero SET moto_image=? WHERE id=1")->execute([trim($_POST['moto_image'])]);
    }

    // ---- Boxes : supprimer tout puis réinsérer ----
    $pdo->exec("DELETE FROM kk_hero_boxes");
    $types   = $_POST['box_type']   ?? [];
    $labels  = $_POST['box_label']  ?? [];
    $values  = $_POST['box_value']  ?? [];
    $subs    = $_POST['box_sub']    ?? [];
    $styles  = $_POST['box_style']  ?? [];
    $pos = 0;
    foreach ($types as $k => $type) {
        if (!$type) continue;
        if ($pos >= 3) break; // max 3
        $pdo->prepare("INSERT INTO kk_hero_boxes (position,type,label,value,sub,style) VALUES(?,?,?,?,?,?)")
            ->execute([$pos, $type, trim($labels[$k]??''), trim($values[$k]??''), trim($subs[$k]??''), $styles[$k]??'white']);
        $pos++;
    }

    // ---- Specs ----
    for ($i = 0; $i < 4; $i++) {
        $pdo->prepare("UPDATE kk_specs SET value=?,label=? WHERE id=?")
            ->execute([trim($_POST["sv_$i"]??''), trim($_POST["sl_$i"]??''), $i+1]);
    }

    $saved = true;
}

$hero  = get_hero();
$boxes = get_hero_boxes();
$specs = get_specs();
// Compléter jusqu'à 3 lignes pour le formulaire
while (count($boxes) < 3) $boxes[] = ['type'=>'','label'=>'','value'=>'','sub'=>'','style'=>'white'];
?>
<div class="adm-topbar"><h1>Accueil — Hero</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré avec succès.</div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<!-- TITRE -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🏍️</span> Titre principal</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Ligne 1</label><input type="text" name="title_line1" value="<?= h($hero['title_line1']??'') ?>"></div>
    <div class="fgrp"><label>Ligne 2</label><input type="text" name="title_line2" value="<?= h($hero['title_line2']??'') ?>"></div>
    <div class="fgrp"><label>Label bouton</label><input type="text" name="btn_label" value="<?= h($hero['btn_label']??'') ?>"></div>
  </div>
</div>

<!-- CARTE MODELE -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📌</span> Carte modèle (bas gauche)</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Sous-titre</label><input type="text" name="model_sub" value="<?= h($hero['model_sub']??'') ?>"></div>
    <div class="fgrp"><label>Code modèle (ex: KX450F)</label><input type="text" name="model_code" value="<?= h($hero['model_code']??'') ?>"></div>
    <div class="fgrp full"><label>Description</label><textarea name="model_desc"><?= h($hero['model_desc']??'') ?></textarea></div>
  </div>
</div>

<!-- AVIS -->
<div class="card">
  <div class="card-head"><h2><span class="icon">⭐</span> Avis clients (dans les boxes)</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Nombre (ex: 500+)</label><input type="text" name="reviews_count" value="<?= h($hero['reviews_count']??'') ?>"></div>
    <div class="fgrp"><label>Texte</label><input type="text" name="reviews_text" value="<?= h($hero['reviews_text']??'') ?>"></div>
  </div>
</div>

<!-- PHOTO MOTO -->
<div class="card">
  <div class="card-head"><h2><span class="icon">🖼️</span> Photo moto (PNG fond transparent)</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Uploader une nouvelle photo</label><input type="file" name="moto_image_upload" accept="image/png,image/webp,image/jpeg"></div>
    <div class="fgrp"><label>Ou URL existante</label><input type="text" name="moto_image" value="<?= h($hero['moto_image']??'') ?>" placeholder="/img/hero/ma-moto.png"></div>
    <div class="fgrp"><label>Décalage gauche (ex: 100px)</label><input type="text" name="moto_left" value="<?= h($hero['moto_left']??'100px') ?>"></div>
    <div class="fgrp"><label>Largeur moto (ex: 790px)</label><input type="text" name="moto_width" value="<?= h($hero['moto_width']??'790px') ?>"></div>
  </div>
  <?php if(!empty($hero['moto_image'])): ?>
  <div style="margin-top:12px;background:#e8e8e6;border-radius:10px;padding:14px;text-align:center;">
    <img src="<?= h($hero['moto_image']) ?>" style="max-height:160px;max-width:100%;object-fit:contain;">
  </div>
  <?php endif; ?>
  <hr class="sep">
  <!-- Animation -->
  <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">🎬 Animation d'entrée</div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
  <?php foreach(['slide-up'=>['⬆️','Slide Up'],'fade'=>['🌫️','Fade'],'zoom'=>['🔍','Zoom'],'slide-right'=>['➡️','Slide Right'],'flip'=>['🔄','Flip 3D'],'float'=>['🎈','Float']] as $k=>[$ico,$nm]): ?>
  <label style="display:flex;flex-direction:column;gap:4px;padding:9px;border:2px solid <?= ($hero['moto_anim']??'slide-up')===$k?'var(--red)':'var(--border)' ?>;border-radius:8px;cursor:pointer;background:<?= ($hero['moto_anim']??'slide-up')===$k?'#fef2f2':'var(--bg)' ?>;">
    <input type="radio" name="moto_anim" value="<?= $k ?>" <?= ($hero['moto_anim']??'slide-up')===$k?'checked':'' ?> style="display:none;">
    <span style="font-size:18px;"><?= $ico ?></span>
    <span style="font-size:12px;font-weight:700;"><?= $nm ?></span>
  </label>
  <?php endforeach; ?>
  </div>
  <div class="fgrp" style="max-width:200px;"><label>Délai (secondes)</label><input type="number" name="moto_anim_delay" value="<?= h($hero['moto_anim_delay']??'0.15') ?>" step="0.05" min="0" max="2"></div>
</div>

<!-- BOXES DROITE -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📦</span> Boîtes côté droit (0 à 3)</h2></div>
  <p class="card-hint"><strong>0 box</strong> = moto pleine largeur | <strong>1 box</strong> = 1 grande box | <strong>2</strong> = moitié chacune | <strong>3</strong> = tiers égaux.<br>Pour supprimer une box : choisir type vide "—".</p>

  <?php foreach($boxes as $i => $box): ?>
  <div style="background:var(--bg);border-radius:10px;padding:16px;margin-bottom:10px;border:1px solid var(--border);">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Box <?= $i+1 ?></div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:8px;">
      <div class="fgrp">
        <label>Type</label>
        <select name="box_type[]">
          <option value="">— Désactivée —</option>
          <option value="reviews" <?= ($box['type']??'')==='reviews'?'selected':'' ?>>⭐ Reviews</option>
          <option value="stat"    <?= ($box['type']??'')==='stat'   ?'selected':'' ?>>📊 Stat</option>
          <option value="text"    <?= ($box['type']??'')==='text'   ?'selected':'' ?>>📝 Texte</option>
        </select>
      </div>
      <div class="fgrp"><label>Label</label><input type="text" name="box_label[]" value="<?= h($box['label']??'') ?>" placeholder="Satisfaction"></div>
      <div class="fgrp"><label>Valeur</label><input type="text" name="box_value[]" value="<?= h($box['value']??'') ?>" placeholder="98%"></div>
      <div class="fgrp"><label>Sous-texte</label><input type="text" name="box_sub[]" value="<?= h($box['sub']??'') ?>" placeholder="pilotes contents"></div>
      <div class="fgrp">
        <label>Style</label>
        <select name="box_style[]">
          <option value="white" <?= ($box['style']??'')==='white'?'selected':'' ?>>⬜ Blanc</option>
          <option value="dark"  <?= ($box['style']??'')==='dark' ?'selected':'' ?>>⬛ Noir</option>
          <option value="red"   <?= ($box['style']??'')==='red'  ?'selected':'' ?>>🟥 Rouge</option>
        </select>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- SPECS -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📊</span> 4 chiffres clés (sous le hero)</h2></div>
  <div class="g4">
    <?php for($i=0;$i<4;$i++): $sp=$specs[$i]??[]; ?>
    <div style="background:var(--bg);border-radius:8px;padding:12px;">
      <div class="fgrp" style="margin-bottom:8px;"><label>Valeur <?=$i+1?></label><input type="text" name="sv_<?=$i?>" value="<?= h($sp['value']??'') ?>"></div>
      <div class="fgrp"><label>Label</label><input type="text" name="sl_<?=$i?>" value="<?= h($sp['label']??'') ?>"></div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer tout</button>
</form>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
