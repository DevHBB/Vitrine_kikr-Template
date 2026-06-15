<?php
require_once __DIR__ . '/layout.php';
ensure_tables();
$action = $_POST['action'] ?? '';
$saved  = false;

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $row = [
        (int)($_POST['service_id']  ?? 0) ?: null,
        (int)($_POST['position']    ?? 0),
        trim($_POST['label']        ?? ''),
        trim($_POST['description']  ?? ''),
        $_POST['price_from']  !== '' ? (float)$_POST['price_from']  : null,
        $_POST['price_to']    !== '' ? (float)$_POST['price_to']    : null,
        $_POST['price_exact'] !== '' ? (float)$_POST['price_exact'] : null,
        trim($_POST['unit']         ?? ''),
        (int)($_POST['highlight']   ?? 0),
        (int)($_POST['active']      ?? 1),
    ];
    if ($id > 0) {
        db()->prepare("UPDATE kk_price_catalog SET service_id=?,position=?,label=?,description=?,price_from=?,price_to=?,price_exact=?,unit=?,highlight=?,active=? WHERE id=?")
           ->execute([...$row, $id]);
    } else {
        db()->prepare("INSERT INTO kk_price_catalog(service_id,position,label,description,price_from,price_to,price_exact,unit,highlight,active) VALUES(?,?,?,?,?,?,?,?,?,?)")
           ->execute($row);
    }
    $saved = true;
}
if ($action === 'delete') {
    db()->prepare("DELETE FROM kk_price_catalog WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . BASE_URL . '/admin/price_catalog.php'); exit;
}

$services = get_services();
$catalog  = db()->query('SELECT pc.*, s.label as service_label FROM kk_price_catalog pc LEFT JOIN kk_services s ON pc.service_id=s.id ORDER BY pc.service_id, pc.position')->fetchAll();

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$ei = null;
if ($edit_id === 0) {
    $ei = ['id'=>0,'service_id'=>'','position'=>0,'label'=>'','description'=>'','price_from'=>'','price_to'=>'','price_exact'=>'','unit'=>'','highlight'=>0,'active'=>1];
} elseif ($edit_id > 0) {
    $s = db()->prepare('SELECT * FROM kk_price_catalog WHERE id=?'); $s->execute([$edit_id]);
    $ei = $s->fetch() ?: null;
}

// Grouper par service
$grouped = [];
foreach ($catalog as $item) {
    $key = $item['service_label'] ?? 'Général';
    $grouped[$key][] = $item;
}
?>
<div class="adm-topbar">
  <h1>💰 Catalogue des prix</h1>
  <a href="?edit=0" class="btn btn-primary btn-sm">+ Ajouter un tarif</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<!-- Aperçu du catalogue -->
<?php foreach($grouped as $svc_lbl => $items): ?>
<div class="card" style="margin-bottom:12px;">
  <div class="card-head"><h2><span class="icon">🏷️</span> <?= h($svc_lbl) ?></h2></div>
  <div class="item-list">
  <?php foreach($items as $item): ?>
  <div class="item-row" style="<?= $item['highlight']?'border-left:3px solid #ed0c0f;':'' ?>">
    <?php if($item['highlight']): ?><span style="color:#ed0c0f;font-size:14px;">⭐</span><?php endif; ?>
    <div class="item-row-name"><?= h($item['label']) ?></div>
    <div class="item-row-sub" style="font-size:11px;color:#888;"><?= h($item['description']??'') ?></div>
    <div style="font-size:13px;font-weight:700;color:#ed0c0f;white-space:nowrap;"><?= format_price($item) ?></div>
    <span class="item-row-badge <?= $item['active']?'':'draft' ?>"><?= $item['active']?'Actif':'Masqué' ?></span>
    <div class="item-row-actions">
      <a href="?edit=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if(empty($catalog)): ?>
<div class="card"><p style="color:var(--muted);font-size:13px;text-align:center;padding:24px;">Aucun tarif. Cliquez sur "+ Ajouter" pour commencer.</p></div>
<?php endif; ?>

<!-- Formulaire -->
<?php if($edit_id >= 0 && $ei): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_id > 0 ? '✏️ Modifier' : '➕ Nouveau tarif' ?></h2></div>
  <p class="card-hint">
    Laissez "Prix exact" vide pour utiliser "De X à Y €". Laissez tout vide pour afficher "Sur devis".
    Les tarifs s'affichent sur la page de prise de RDV.
  </p>
  <form method="POST">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">
    <div class="g2">
      <div class="fgrp">
        <label>Service associé</label>
        <select name="service_id">
          <option value="">— Général —</option>
          <?php foreach($services as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($ei['service_id']??'')==$s['id']?'selected':'' ?>><?= h($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp">
        <label>Ordre d'affichage</label>
        <input type="number" name="position" value="<?= $ei['position']??0 ?>" min="0">
      </div>
      <div class="fgrp full">
        <label>Libellé de la prestation *</label>
        <input type="text" name="label" value="<?= h($ei['label']??'') ?>" required placeholder="ex: Vidange fourche + amortisseur">
      </div>
      <div class="fgrp full">
        <label>Description courte</label>
        <input type="text" name="description" value="<?= h($ei['description']??'') ?>" placeholder="ex: Démontage, nettoyage, remplacement huile">
      </div>
    </div>

    <hr class="sep">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">💰 Tarification</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;">
      <div class="fgrp">
        <label>Prix exact (€)</label>
        <input type="number" name="price_exact" value="<?= $ei['price_exact']??'' ?>" step="0.01" min="0" placeholder="120">
        <span class="hint">Prioritaire sur fourchette</span>
      </div>
      <div class="fgrp">
        <label>Prix minimum (€)</label>
        <input type="number" name="price_from" value="<?= $ei['price_from']??'' ?>" step="0.01" min="0" placeholder="80">
      </div>
      <div class="fgrp">
        <label>Prix maximum (€)</label>
        <input type="number" name="price_to" value="<?= $ei['price_to']??'' ?>" step="0.01" min="0" placeholder="160">
      </div>
      <div class="fgrp">
        <label>Unité (optionnel)</label>
        <input type="text" name="unit" value="<?= h($ei['unit']??'') ?>" placeholder="la paire, unité…">
      </div>
    </div>

    <div class="g2" style="margin-top:8px;">
      <div class="fgrp">
        <label>Mise en avant</label>
        <select name="highlight">
          <option value="0" <?= !($ei['highlight']??0)?'selected':'' ?>>Non</option>
          <option value="1" <?= ($ei['highlight']??0)?'selected':'' ?>>⭐ Oui (bordure rouge)</option>
        </select>
      </div>
      <div class="fgrp">
        <label>Visibilité</label>
        <select name="active">
          <option value="1" <?= ($ei['active']??1)?'selected':'' ?>>👁 Visible</option>
          <option value="0" <?= !($ei['active']??1)?'selected':'' ?>>🙈 Masqué</option>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px;">
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
      <a href="/admin/price_catalog.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
