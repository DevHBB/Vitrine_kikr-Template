<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();
$saved = false;

// Les catégories sont déduites des produits ET stockées dans kk_settings (liste custom)
// Auto-add colonne categories si manquante
try { db()->exec("ALTER TABLE kk_products ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED DEFAULT NULL"); } catch(Exception $e) {}

$action = $_POST['action'] ?? '';

// Récupérer catégories depuis les produits existants
$cats_from_products = db()->query(
    "SELECT category, COUNT(*) as cnt FROM kk_products WHERE category != '' GROUP BY category ORDER BY category"
)->fetchAll();

// Catégories custom stockées en settings
$cats_custom_raw = get_setting('shop_categories', '');
$cats_custom = $cats_custom_raw ? array_filter(array_map('trim', explode(',', $cats_custom_raw))) : [];

// Sauvegarder catégories
if ($action === 'save_cats') {
    $new_cats = array_values(array_filter(array_map('trim', $_POST['cats'] ?? [])));
    set_setting('shop_categories', implode(',', $new_cats));
    $saved = true;
    $cats_custom = $new_cats;
}

// Renommer une catégorie sur tous les produits
if ($action === 'rename') {
    $old = trim($_POST['old_name'] ?? '');
    $new = trim($_POST['new_name'] ?? '');
    if ($old && $new) {
        db()->prepare("UPDATE kk_products SET category=? WHERE category=?")->execute([$new, $old]);
        // Mettre à jour dans la liste custom aussi
        $cats_custom = array_map(fn($c) => $c === $old ? $new : $c, $cats_custom);
        set_setting('shop_categories', implode(',', $cats_custom));
        $saved = true;
        $cats_from_products = db()->query(
            "SELECT category, COUNT(*) as cnt FROM kk_products WHERE category != '' GROUP BY category ORDER BY category"
        )->fetchAll();
    }
}

// Supprimer une catégorie (vide les produits de cette cat)
if ($action === 'delete_cat') {
    $cat = trim($_POST['cat_name'] ?? '');
    if ($cat) {
        db()->prepare("UPDATE kk_products SET category='' WHERE category=?")->execute([$cat]);
        $cats_custom = array_filter($cats_custom, fn($c) => $c !== $cat);
        set_setting('shop_categories', implode(',', array_values($cats_custom)));
        header('Location: '.BASE_URL.'/admin/shop_cats.php'); exit;
    }
}

// Fusionner toutes les catégories connues
$all_cats = array_unique(array_merge(
    $cats_custom,
    array_column($cats_from_products, 'category')
));
sort($all_cats);
$cnt_map = array_column($cats_from_products, 'cnt', 'category');
?>
<div class="adm-topbar">
  <h1>🏷️ Catégories boutique</h1>
  <a href="<?= BASE_URL ?>/admin/shop.php?edit=0" class="btn btn-primary btn-sm">+ Nouveau produit</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

<!-- Catégories existantes -->
<div class="card">
  <div class="card-head"><h2><span class="icon">📋</span> Catégories (<?= count($all_cats) ?>)</h2></div>
  <p class="card-hint">Les catégories apparaissent dans le menu filtres de la boutique.</p>

  <?php if(empty($all_cats)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucune catégorie. Créez-en une ci-contre.</p>
  <?php else: ?>
  <?php foreach($all_cats as $cat): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border-radius:10px;margin-bottom:6px;">
    <div style="flex:1;">
      <div style="font-size:13px;font-weight:700;"><?= h($cat) ?></div>
      <div style="font-size:11px;color:var(--muted);"><?= (int)($cnt_map[$cat] ?? 0) ?> produit(s)</div>
    </div>
    <!-- Renommer -->
    <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="old_name" value="<?= h($cat) ?>">
      <input type="text" name="new_name" value="<?= h($cat) ?>"
             style="border:1.5px solid var(--border);border-radius:7px;padding:5px 8px;font-size:12px;width:120px;outline:none;">
      <button type="submit" class="btn btn-secondary btn-sm">Renommer</button>
    </form>
    <!-- Supprimer -->
    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer la catégorie «<?= h($cat) ?>» ? Les produits ne seront pas supprimés.')">
      <input type="hidden" name="action" value="delete_cat">
      <input type="hidden" name="cat_name" value="<?= h($cat) ?>">
      <button type="submit" class="btn btn-danger btn-sm">🗑</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Créer / gérer -->
<div>
  <div class="card">
    <div class="card-head"><h2><span class="icon">➕</span> Créer des catégories</h2></div>
    <p class="card-hint">Ajoutez vos catégories ici. Elles apparaîtront dans la liste déroulante lors de la création de produits.</p>
    <form method="POST">
      <input type="hidden" name="action" value="save_cats">
      <div id="cats-list">
        <?php foreach(array_merge($all_cats, ['']) as $k => $cat): ?>
        <?php if($k === 0 && empty($all_cats)) $cat = ''; ?>
        <div class="cat-row" style="display:flex;gap:6px;margin-bottom:6px;">
          <input type="text" name="cats[]" value="<?= h($cat) ?>"
                 placeholder="ex: Huile fourche, Pièces, Accessoires…"
                 style="flex:1;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;">
          <button type="button" onclick="this.closest('.cat-row').remove()"
                  style="width:32px;background:#fee2e2;border:none;border-radius:8px;cursor:pointer;color:#dc2626;font-size:16px;">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="addCat()" style="margin-bottom:14px;">+ Ajouter une ligne</button>
      <button type="submit" class="btn btn-primary" style="display:block;width:100%;">💾 Enregistrer les catégories</button>
    </form>
  </div>

  <div class="card" style="margin-top:16px;">
    <div class="card-head"><h2><span class="icon">💡</span> Idées de catégories</h2></div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      <?php foreach(['Huile fourche','Huile amortisseur','Joints & Kits','Ressorts','Cartouches','Accessoires','Pièces détachées','Outils','Entretien'] as $sug): ?>
      <button type="button" onclick="addCatNamed('<?= h($sug) ?>')"
              style="padding:6px 12px;background:var(--bg);border:1.5px solid var(--border);border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;"
              onmouseover="this.style.borderColor='#ed0c0f';this.style.color='#ed0c0f'"
              onmouseout="this.style.borderColor='var(--border)';this.style.color=''">
        + <?= h($sug) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<!-- Aperçu produits par catégorie -->
<?php if(!empty($cats_from_products)): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><span class="icon">📊</span> Produits par catégorie</h2></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;">
    <?php foreach($cats_from_products as $row): ?>
    <a href="<?= BASE_URL ?>/admin/shop.php?cat=<?= urlencode($row['category']) ?>"
       style="display:block;background:var(--bg);border-radius:12px;padding:16px;text-decoration:none;transition:box-shadow .2s;"
       onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="font-size:24px;margin-bottom:8px;">🏷️</div>
      <div style="font-size:13px;font-weight:700;color:#111;"><?= h($row['category']) ?></div>
      <div style="font-size:12px;color:#aaa;margin-top:3px;"><?= $row['cnt'] ?> produit<?= $row['cnt']>1?'s':'' ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div>

<script>
function addCat() {
  var list = document.getElementById('cats-list');
  var d = document.createElement('div');
  d.className = 'cat-row';
  d.style.cssText = 'display:flex;gap:6px;margin-bottom:6px;';
  d.innerHTML = '<input type="text" name="cats[]" placeholder="Nouvelle catégorie…" style="flex:1;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;">'
    + '<button type="button" onclick="this.closest(\'.cat-row\').remove()" style="width:32px;background:#fee2e2;border:none;border-radius:8px;cursor:pointer;color:#dc2626;font-size:16px;">×</button>';
  list.appendChild(d);
  d.querySelector('input').focus();
}
function addCatNamed(name) {
  // Vérifier si pas déjà dans la liste
  var exists = false;
  document.querySelectorAll('#cats-list input').forEach(function(inp) {
    if (inp.value.toLowerCase() === name.toLowerCase()) exists = true;
  });
  if (exists) { alert('Cette catégorie existe déjà.'); return; }
  addCat();
  var inputs = document.querySelectorAll('#cats-list input');
  inputs[inputs.length-1].value = name;
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
