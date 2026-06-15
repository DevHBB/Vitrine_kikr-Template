<?php
require_once __DIR__ . '/layout.php';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hours = [];
    $days   = $_POST['hday']   ?? [];
    $htimes = $_POST['hhours'] ?? [];
    foreach ($days as $k => $d) {
        if (trim($d)) {
            $hours[] = [
                'day'   => trim($d),
                'hours' => trim($htimes[$k] ?? ''),
                'rdv'   => isset($_POST['hrdv'][$k]),
            ];
        }
    }
    db()->prepare("UPDATE kk_contact SET title=?,subtitle=?,fields_note=?,map_embed=?,hours=? WHERE id=1")
       ->execute([
           trim($_POST['title']       ?? ''),
           trim($_POST['subtitle']    ?? ''),
           trim($_POST['fields_note'] ?? ''),
           trim($_POST['map_embed']   ?? ''),
           json_encode($hours, JSON_UNESCAPED_UNICODE),
       ]);
    $saved = true;
}

$ct    = get_contact();
$hours = $ct['hours'] ?? [];
// Toujours avoir au moins 7 lignes
while (count($hours) < 7) $hours[] = ['day'=>'','hours'=>'','rdv'=>false];
?>
<div class="adm-topbar"><h1>Contact & Horaires</h1><a href="<?= BASE_URL ?>/contact.php" target="_blank" class="btn btn-secondary btn-sm">👁 Voir la page</a></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<form method="POST">

<div class="card"><div class="card-head"><h2>📋 Textes de la page</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Titre</label><input type="text" name="title" value="<?= h($ct['title']??'Contactez-nous') ?>"></div>
    <div class="fgrp"><label>Sous-titre</label><input type="text" name="subtitle" value="<?= h($ct['subtitle']??'') ?>"></div>
    <div class="fgrp full"><label>Note formulaire</label><input type="text" name="fields_note" value="<?= h($ct['fields_note']??'') ?>" placeholder="Décrivez votre moto, poids, terrain…"></div>
  </div>
</div>

<div class="card"><div class="card-head"><h2>🕐 Horaires d'ouverture</h2></div>
  <div style="display:grid;grid-template-columns:2fr 1.5fr auto;gap:6px;margin-bottom:8px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;">
    <span>Jour</span><span>Horaires</span><span style="white-space:nowrap;">Sur RDV uniquement</span>
  </div>
  <?php foreach($hours as $k => $h): ?>
  <div style="display:grid;grid-template-columns:2fr 1.5fr auto;gap:6px;margin-bottom:6px;align-items:center;">
    <input type="text" name="hday[]"   value="<?= h($h['day']??'') ?>"   placeholder="ex: Lundi — Vendredi"
           style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;font-family:inherit;outline:none;background:var(--bg);width:100%;">
    <input type="text" name="hhours[]" value="<?= h($h['hours']??'') ?>" placeholder="ex: 9h — 18h ou Fermé"
           style="border:1.5px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px;font-family:inherit;outline:none;background:var(--bg);width:100%;">
    <label style="display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1.5px solid var(--border);border-radius:7px;white-space:nowrap;background:<?= !empty($h['rdv'])?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="hrdv[<?= $k ?>]" <?= !empty($h['rdv'])?'checked':'' ?> style="accent-color:#ed0c0f;width:15px;height:15px;">
      Sur RDV
    </label>
  </div>
  <?php endforeach; ?>
  <p style="font-size:11px;color:var(--muted);margin-top:6px;">Laissez les lignes vides pour les masquer.</p>
</div>

<div class="card"><div class="card-head"><h2>🗺️ Google Maps</h2></div>
  <div class="fgrp"><label>URL de la carte Google Maps (iframe src)</label>
    <input type="url" name="map_embed" value="<?= h($ct['map_embed']??'') ?>" placeholder="https://www.google.com/maps/embed?pb=…">
    <span class="hint">Google Maps → Partager → Intégrer → copier l'URL src de l'iframe</span>
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
</form>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
