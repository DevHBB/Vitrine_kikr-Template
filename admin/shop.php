<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();

$action = $_POST['action'] ?? '';
$saved  = false;

if ($action === 'save') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (!$slug) $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name));

    // Images : URL existantes + uploads
    $imgs = array_values(array_filter(array_map('trim', explode("\n", $_POST['images_urls'] ?? ''))));
    if (!empty($_FILES['images_upload']['tmp_name'])) {
        foreach ($_FILES['images_upload']['tmp_name'] as $k => $tmp) {
            if ($tmp) {
                $f = ['name'=>$_FILES['images_upload']['name'][$k],'type'=>$_FILES['images_upload']['type'][$k],'tmp_name'=>$tmp,'error'=>0,'size'=>0];
                $url = upload_media($f, 'media');
                if ($url) $imgs[] = $url;
            }
        }
    }

    $row = [
        trim($name),
        $slug,
        trim($_POST['description'] ?? ''),
        (float)($_POST['price']      ?? 0),
        $_POST['price_promo'] !== '' ? (float)$_POST['price_promo'] : null,
        json_encode($imgs, JSON_UNESCAPED_UNICODE),
        trim($_POST['category']  ?? ''),
        (int)($_POST['stock']    ?? -1),
        (int)($_POST['active']   ?? 1),
        (int)($_POST['featured'] ?? 0),
    ];
    if ($id > 0) {
        db()->prepare("UPDATE kk_products SET name=?,slug=?,description=?,price=?,price_promo=?,images=?,category=?,stock=?,active=?,featured=? WHERE id=?")
           ->execute([...$row, $id]);
    } else {
        $pos = (int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_products')->fetchColumn();
        db()->prepare("INSERT INTO kk_products(name,slug,description,price,price_promo,images,category,stock,active,featured,position) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([...$row, $pos]);
    }
    $saved = true;
}
if ($action === 'delete') {
    db()->prepare("DELETE FROM kk_products WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: '.BASE_URL.'/admin/shop.php'); exit;
}
if ($action === 'toggle') {
    db()->prepare("UPDATE kk_products SET active=1-active WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: '.BASE_URL.'/admin/shop.php'); exit;
}

$products = db()->query("SELECT * FROM kk_products ORDER BY position, created_at DESC")->fetchAll();
$cats     = array_unique(array_filter(array_column($products, 'category')));

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$ep = null;
if ($edit_id === 0) {
    $ep = ['id'=>0,'name'=>'','slug'=>'','description'=>'','price'=>'','price_promo'=>'','images'=>'[]','category'=>'','stock'=>-1,'active'=>1,'featured'=>0];
} elseif ($edit_id > 0) {
    $s = db()->prepare('SELECT * FROM kk_products WHERE id=?'); $s->execute([$edit_id]);
    $ep = $s->fetch() ?: null;
}
?>
<div class="adm-topbar">
  <h1>🛒 Shop — Produits</h1>
  <div style="display:flex;gap:8px;">
    <a href="<?= BASE_URL ?>/shop.php" target="_blank" class="btn btn-secondary btn-sm">👁 Voir le shop</a>
    <a href="?edit=0" class="btn btn-primary btn-sm">+ Ajouter un produit</a>
  </div>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Produit enregistré.</div><?php endif; ?>

<!-- LISTE PRODUITS -->
<div class="card">
  <div class="card-head"><h2>Produits (<?= count($products) ?>)</h2></div>
  <?php if(empty($products)): ?>
  <div style="text-align:center;padding:32px;color:var(--muted);">
    <div style="font-size:40px;margin-bottom:12px;">📦</div>
    <div style="font-size:14px;font-weight:700;margin-bottom:6px;">Aucun produit</div>
    <a href="?edit=0" class="btn btn-primary btn-sm">+ Ajouter votre premier produit</a>
  </div>
  <?php else: ?>
  <div class="item-list">
    <?php foreach($products as $p):
      $p_imgs = jd($p['images'] ?? '[]', []);
      $thumb  = $p_imgs[0] ?? '';
    ?>
    <div class="item-row" style="opacity:<?= $p['active']?'1':'.5' ?>;">
      <?php if($thumb): ?>
      <img src="<?= h($thumb) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0;">
      <?php else: ?>
      <div style="width:48px;height:48px;background:#f5f5f3;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">📦</div>
      <?php endif; ?>
      <div class="item-row-name"><?= h($p['name']) ?></div>
      <div style="font-size:13px;font-weight:800;color:var(--red);"><?= number_format((float)$p['price'],2,',',' ') ?> €<?= $p['price_promo']?' <span style="font-size:10px;text-decoration:line-through;color:#aaa;font-weight:400;">'.number_format((float)$p['price_promo'],2,',',' ').' €</span>':'' ?></div>
      <?php if($p['category']): ?><span class="item-row-badge"><?= h($p['category']) ?></span><?php endif; ?>
      <?php if($p['featured']): ?><span style="font-size:11px;color:#f59e0b;">⭐ Vedette</span><?php endif; ?>
      <span class="item-row-badge <?= $p['active']?'':'draft' ?>"><?= $p['active']?'Actif':'Masqué' ?></span>
      <div class="item-row-actions">
        <a href="?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm"><?= $p['active']?'Masquer':'Afficher' ?></button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ÉDITEUR -->
<?php if($edit_id >= 0 && $ep): ?>
<?php $ep_imgs = jd($ep['images'] ?? '[]', []); ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_id > 0 ? '✏️ Modifier '.h($ep['name']) : '➕ Nouveau produit' ?></h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <div class="g2">
      <div class="fgrp"><label>Nom du produit *</label><input type="text" name="name" value="<?= h($ep['name']) ?>" required oninput="autoSlugProd(this)"></div>
      <div class="fgrp"><label>Slug URL</label><input type="text" name="slug" id="prod-slug" value="<?= h($ep['slug']) ?>"><span class="hint">Auto-généré depuis le nom</span></div>
      <div class="fgrp full"><label>Description</label><textarea name="description" style="min-height:100px;"><?= h($ep['description'] ?? '') ?></textarea></div>
      <div class="fgrp"><label>Prix (€ TTC) *</label><input type="number" name="price" value="<?= h($ep['price']) ?>" step="0.01" min="0" required></div>
      <div class="fgrp"><label>Prix barré (€) — promo</label><input type="number" name="price_promo" value="<?= h($ep['price_promo'] ?? '') ?>" step="0.01" min="0" placeholder="Laisser vide si pas de promo"></div>
      <div class="fgrp"><label>Catégorie</label>
        <input type="text" name="category" value="<?= h($ep['category']) ?>" placeholder="Huile, Pièce, Accessoire…" list="cat-list">
        <datalist id="cat-list"><?php foreach($cats as $cat): ?><option value="<?= h($cat) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="fgrp"><label>Stock (-1 = illimité)</label><input type="number" name="stock" value="<?= h($ep['stock']) ?>" min="-1"></div>
      <div class="fgrp"><label>Statut</label>
        <select name="active"><option value="1" <?= $ep['active']?'selected':''?>>👁 Visible</option><option value="0" <?= !$ep['active']?'selected':''?>>🙈 Masqué</option></select>
      </div>
      <div class="fgrp"><label>Mise en avant</label>
        <select name="featured"><option value="0" <?= !$ep['featured']?'selected':''?>>Non</option><option value="1" <?= $ep['featured']?'selected':''?>>⭐ Oui</option></select>
      </div>
    </div>

    <!-- Images -->
    <div style="background:var(--bg);border-radius:10px;padding:16px;margin-top:8px;">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:12px;">📸 Photos du produit</div>
      <?php if($ep_imgs): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <?php foreach($ep_imgs as $img): ?>
        <img src="<?= h($img) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1.5px solid #e8e8e8;">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="g2">
        <div class="fgrp"><label>Uploader des photos</label><input type="file" name="images_upload[]" multiple accept="image/*"></div>
        <div class="fgrp"><label>URLs existantes (1 par ligne)</label><textarea name="images_urls" style="min-height:70px;font-family:monospace;font-size:11px;"><?= h(implode("\n", $ep_imgs)) ?></textarea></div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px;">
      <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
      <a href="<?= BASE_URL ?>/admin/shop.php" class="btn btn-secondary btn-lg">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>
</div>
<script>
function autoSlugProd(inp) {
  var s = inp.value.toLowerCase()
    .replace(/[àáâãä]/g,'a').replace(/[éèêë]/g,'e').replace(/[îï]/g,'i')
    .replace(/[ôö]/g,'o').replace(/[ùûü]/g,'u').replace(/ç/g,'c')
    .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  document.getElementById('prod-slug').value = s;
}
</script>
<?php
// ─── COMMANDES ───
if (!isset($_GET['edit'])):
try {
    $orders = db()->query("SELECT o.*,i.number as inv_num FROM kk_orders o LEFT JOIN kk_invoices i ON o.invoice_id=i.id ORDER BY o.created_at DESC LIMIT 50")->fetchAll();
} catch(Exception $e) { $orders = []; }
$order_statuses = ['pending'=>['🟡','En attente'],'confirmed'=>['🔵','Confirmé'],'shipped'=>['📦','Expédié'],'delivered'=>['✅','Livré'],'cancelled'=>['❌','Annulé']];

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_order_status'])) {
    db()->prepare("UPDATE kk_orders SET status=? WHERE id=?")->execute([$_POST['new_status'],(int)$_POST['order_id']]);
    header('Location: '.BASE_URL.'/admin/shop.php'); exit;
}
?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><span class="icon">📋</span> Commandes (<?= count($orders) ?>)</h2></div>
  <?php if(empty($orders)): ?>
  <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucune commande reçue pour l'instant.</p>
  <?php else: ?>
  <div class="item-list">
    <?php foreach($orders as $ord):
      [$ico,$lbl] = $order_statuses[$ord['status']] ?? ['?',''];
      $items = jd($ord['items']??'[]',[]);
    ?>
    <div class="item-row">
      <div style="flex-shrink:0;width:90px;">
        <div style="font-size:11px;font-weight:800;color:var(--muted);"><?= h($ord['number']) ?></div>
        <div style="font-size:10px;color:#bbb;"><?= date('d/m/Y',strtotime($ord['created_at'])) ?></div>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:700;"><?= h($ord['client_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= h($ord['client_email']) ?> · <?= count($items) ?> article(s)</div>
      </div>
      <div style="font-size:15px;font-weight:900;color:var(--red);white-space:nowrap;"><?= number_format((float)$ord['total'],2,',','') ?> €</div>
      <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:#f5f5f3;"><?= $ico.' '.$lbl ?></span>
      <div class="item-row-actions">
        <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
          <input type="hidden" name="update_order_status" value="1"><input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
          <select name="new_status" style="border:1.5px solid var(--border);border-radius:7px;padding:5px 6px;font-size:11px;">
            <?php foreach($order_statuses as $k=>[$i,$l]): ?><option value="<?= $k ?>" <?= $ord['status']===$k?'selected':''?>><?= $i.' '.$l ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">OK</button>
        </form>
        <?php if($ord['inv_num']): ?>
        <a href="<?= BASE_URL ?>/admin/invoice_pdf.php?id=<?= $ord['invoice_id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Voir la facture">📄</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/layout_end.php'; ?>
<?php // Section commandes - appended ?>
