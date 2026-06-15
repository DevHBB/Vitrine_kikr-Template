<?php
ob_start();
require_once __DIR__ . '/layout.php';

// S'assurer que la colonne stats existe
try {
    db()->exec("ALTER TABLE kk_about ADD COLUMN IF NOT EXISTS stats MEDIUMTEXT AFTER photo");
} catch(Exception $e) {
    // MariaDB < 10.3 ne supporte pas IF NOT EXISTS sur ALTER
    try { db()->exec("ALTER TABLE kk_about ADD COLUMN stats MEDIUMTEXT AFTER photo"); } catch(Exception $e2) {}
}

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paras = array_values(array_filter(
        array_map('trim', $_POST['paragraphs'] ?? []),
        fn($p) => $p !== ''
    ));

    $photo = trim($_POST['photo_url'] ?? '');
    if (!empty($_FILES['photo']['tmp_name'])) {
        $u = upload_media($_FILES['photo'], 'about');
        if ($u) $photo = $u;
    }

    // Stats
    $stats = [];
    foreach (($_POST['stat_val'] ?? []) as $k => $v) {
        if (trim($v) !== '') {
            $stats[] = [
                'val' => trim($v),
                'lbl' => trim($_POST['stat_lbl'][$k] ?? ''),
            ];
        }
    }

    // Vérifier que la ligne id=1 existe
    $exists = db()->query("SELECT COUNT(*) FROM kk_about WHERE id=1")->fetchColumn();
    if (!$exists) {
        db()->exec("INSERT INTO kk_about(id,title,experience,quote,paragraphs,photo,stats) VALUES(1,'QUI SOMMES NOUS ?','22','','[]','','[]')");
    }

    db()->prepare("UPDATE kk_about SET title=?,experience=?,quote=?,paragraphs=?,photo=?,stats=? WHERE id=1")
       ->execute([
           trim($_POST['title']      ?? ''),
           trim($_POST['experience'] ?? ''),
           trim($_POST['quote']      ?? ''),
           json_encode($paras, JSON_UNESCAPED_UNICODE),
           $photo,
           json_encode($stats, JSON_UNESCAPED_UNICODE),
       ]);
    $saved = true;
}

$ab    = get_about();
$paras = $ab['paragraphs'] ?? [];
while (count($paras) < 6) $paras[] = '';

// Stats : lire depuis la colonne stats OU valeurs par défaut
$stats_raw = $ab['stats'] ?? null;
if ($stats_raw) {
    $stats = jd($stats_raw, []);
} else {
    $stats = [
        ['val' => $ab['experience'] ?? '22', 'lbl' => "ans d'expérience"],
        ['val' => '500+',   'lbl' => 'pilotes équipés'],
        ['val' => 'Toutes', 'lbl' => 'marques traitées'],
        ['val' => '',       'lbl' => ''],
    ];
}
while (count($stats) < 4) $stats[] = ['val' => '', 'lbl' => ''];
?>
<div class="adm-topbar">
  <h1>Qui sommes-nous</h1>
  <a href="<?= BASE_URL ?>/about.php" target="_blank" class="btn btn-secondary btn-sm">👁 Voir la page</a>
</div>
<div class="adm-content">
<?php if ($saved): ?><div class="alert alert-ok">✅ Enregistré avec succès.</div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">

  <!-- Titre & texte -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">📝</span> Texte principal</h2></div>
    <div class="g2">
      <div class="fgrp">
        <label>Titre de section</label>
        <input type="text" name="title" value="<?= h($ab['title'] ?? 'QUI SOMMES NOUS ?') ?>">
      </div>
      <div class="fgrp">
        <label>Années d'expérience (chiffre seul)</label>
        <input type="text" name="experience" value="<?= h($ab['experience'] ?? '22') ?>" placeholder="22">
      </div>
      <div class="fgrp full">
        <label>Citation / Accroche</label>
        <textarea name="quote" style="min-height:56px;"><?= h($ab['quote'] ?? '') ?></textarea>
      </div>
    </div>
    <hr class="sep">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Paragraphes</div>
    <?php foreach ($paras as $k => $p): ?>
    <div class="fgrp" style="margin-bottom:10px;">
      <label>Paragraphe <?= $k + 1 ?><?= $k >= 3 ? ' <span style="color:var(--muted);font-weight:400;">(optionnel)</span>' : ' *' ?></label>
      <textarea name="paragraphs[]" style="min-height:60px;"><?= h($p) ?></textarea>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Chiffres clés -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">📊</span> Chiffres clés</h2>
      <span style="font-size:11px;color:var(--muted);">Affichés sous le texte (ex : 22 ans, 500+, Toutes marques)</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
      <?php foreach ($stats as $k => $st): ?>
      <div style="background:var(--bg);border-radius:10px;padding:14px;">
        <div class="fgrp" style="margin-bottom:8px;">
          <label>Valeur <?= $k + 1 ?></label>
          <input type="text" name="stat_val[]" value="<?= h($st['val'] ?? '') ?>" placeholder="500+">
        </div>
        <div class="fgrp">
          <label>Label</label>
          <input type="text" name="stat_lbl[]" value="<?= h($st['lbl'] ?? '') ?>" placeholder="pilotes équipés">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Photo -->
  <div class="card">
    <div class="card-head"><h2><span class="icon">🖼️</span> Photo</h2></div>
    <?php if (!empty($ab['photo'])): ?>
    <div style="margin-bottom:14px;">
      <img src="<?= h($ab['photo']) ?>" style="max-width:200px;border-radius:10px;display:block;">
      <div style="font-size:11px;color:var(--muted);margin-top:6px;"><?= h($ab['photo']) ?></div>
    </div>
    <?php endif; ?>
    <div class="g2">
      <div class="fgrp">
        <label>Uploader une photo</label>
        <input type="file" name="photo" accept="image/*">
      </div>
      <div class="fgrp">
        <label>Ou saisir l'URL</label>
        <input type="text" name="photo_url" value="<?= h($ab['photo'] ?? '') ?>" placeholder="/img/about/photo.jpg">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
</form>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
