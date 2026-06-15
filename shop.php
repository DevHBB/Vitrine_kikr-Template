<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();
$page_title = 'Shop';

// Récupérer les produits actifs
try {
    $cat_filter = trim($_GET['cat'] ?? '');
    $sql = 'SELECT * FROM kk_products WHERE active=1';
    $p   = [];
    if ($cat_filter) { $sql .= ' AND category=?'; $p[] = $cat_filter; }
    $sql .= ' ORDER BY featured DESC, position, created_at DESC';
    $s = db()->prepare($sql); $s->execute($p);
    $products = $s->fetchAll();
    $cats = db()->query("SELECT DISTINCT category FROM kk_products WHERE active=1 AND category!='' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    $products = []; $cats = [];
}

require_once __DIR__ . '/layout/header.php';
?>

<style>
.shop-hero{background:#111;color:white;padding:48px 0;text-align:center}
.shop-hero h1{font-size:clamp(32px,5vw,56px);font-weight:900;letter-spacing:-2px;margin-bottom:8px}
.shop-hero p{color:#888;font-size:14px}
.shop-wrap{max-width:1100px;margin:0 auto;padding:40px 20px}
.shop-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:32px}
.shop-filter{padding:8px 18px;border-radius:20px;border:1.5px solid #e8e8e8;font-size:12px;font-weight:700;color:#555;text-decoration:none;transition:all .2s}
.shop-filter:hover,.shop-filter.active{background:#111;color:white;border-color:#111}
.shop-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.shop-card{background:white;border-radius:16px;overflow:hidden;border:1.5px solid #f0f0f0;transition:box-shadow .2s,transform .2s;cursor:pointer}
.shop-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.1);transform:translateY(-3px)}
.shop-card-img{aspect-ratio:1;background:#f5f5f3;position:relative;overflow:hidden}
.shop-card-img img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
.shop-card:hover .shop-card-img img{transform:scale(1.04)}
.shop-card-img-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f5f5f3;font-size:48px}
.shop-featured-badge{position:absolute;top:10px;left:10px;background:#ed0c0f;color:white;font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px}
.shop-promo-badge{position:absolute;top:10px;right:10px;background:#111;color:white;font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px}
.shop-card-body{padding:16px}
.shop-card-name{font-size:15px;font-weight:800;margin-bottom:4px;color:#111}
.shop-card-cat{font-size:11px;color:#aaa;font-weight:600;text-transform:uppercase;margin-bottom:8px}
.shop-card-desc{font-size:12px;color:#888;margin-bottom:12px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.shop-card-price{display:flex;align-items:center;gap:8px}
.shop-card-price-main{font-size:20px;font-weight:900;color:#ed0c0f}
.shop-card-price-old{font-size:13px;color:#bbb;text-decoration:line-through}
.shop-stock{font-size:11px;font-weight:700;margin-top:6px}
.shop-stock.ok{color:#15803d}
.shop-stock.low{color:#d97706}
.shop-stock.out{color:#dc2626}
.shop-cta{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;background:#111;color:white;border-radius:10px;padding:10px;font-size:13px;font-weight:700;text-decoration:none;transition:background .2s}
.shop-cta:hover{background:#ed0c0f}
.shop-empty{text-align:center;padding:80px 20px;color:#aaa}
.shop-empty-ico{font-size:64px;margin-bottom:16px}
</style>

<div class="shop-hero">
  <h1>Shop</h1>
  <p>Pièces, accessoires et produits sélectionnés par Kik'r</p>
</div>

<div class="shop-wrap">

  <!-- Filtres catégories -->
  <?php if(!empty($cats)): ?>
  <div class="shop-filters">
    <a href="/shop.php" class="shop-filter <?= !$cat_filter?'active':'' ?>">Tous</a>
    <?php foreach($cats as $cat): ?>
    <a href="/shop.php?cat=<?= urlencode($cat) ?>" class="shop-filter <?= $cat_filter===$cat?'active':'' ?>"><?= h($cat) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if(empty($products)): ?>
  <div class="shop-empty">
    <div class="shop-empty-ico">🛒</div>
    <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Boutique bientôt disponible</h2>
    <p style="margin-bottom:20px;">Les produits sont en cours d'ajout.</p>
    <a href="<?= BASE_URL ?>/contact.php" class="btn-red" style="display:inline-block;">Commander sur devis →</a>
  </div>
  <?php else: ?>
  <div class="shop-grid">
    <?php foreach($products as $prod):
      $images = jd($prod['images'] ?? '[]', []);
      $first_img = $images[0] ?? '';
      $stock = (int)$prod['stock'];
      if ($stock === -1)     { $stock_lbl = ''; $stock_cls = 'ok'; }
      elseif ($stock === 0)  { $stock_lbl = 'Rupture de stock'; $stock_cls = 'out'; }
      elseif ($stock <= 3)   { $stock_lbl = 'Seulement '.$stock.' restant(s)'; $stock_cls = 'low'; }
      else                   { $stock_lbl = 'En stock'; $stock_cls = 'ok'; }
    ?>
    <div class="shop-card">
      <div class="shop-card-img">
        <?php if($first_img): ?>
        <img src="<?= h($first_img) ?>" alt="<?= h($prod['name']) ?>">
        <?php else: ?>
        <div class="shop-card-img-placeholder">📦</div>
        <?php endif; ?>
        <?php if($prod['featured']): ?><div class="shop-featured-badge">⭐ Coup de cœur</div><?php endif; ?>
        <?php if($prod['price_promo']): ?>
        <div class="shop-promo-badge">-<?= round((1 - $prod['price_promo']/$prod['price'])*100) ?>%</div>
        <?php endif; ?>
      </div>
      <div class="shop-card-body">
        <?php if($prod['category']): ?><div class="shop-card-cat"><?= h($prod['category']) ?></div><?php endif; ?>
        <div class="shop-card-name"><?= h($prod['name']) ?></div>
        <?php if($prod['description']): ?><div class="shop-card-desc"><?= h($prod['description']) ?></div><?php endif; ?>
        <div class="shop-card-price">
          <span class="shop-card-price-main"><?= number_format((float)($prod['price_promo'] ?: $prod['price']),2,',','') ?> €</span>
          <?php if($prod['price_promo']): ?><span class="shop-card-price-old"><?= number_format((float)$prod['price'],2,',','') ?> €</span><?php endif; ?>
        </div>
        <?php if($stock_lbl): ?><div class="shop-stock <?= $stock_cls ?>"><?= $stock_lbl ?></div><?php endif; ?>
        <?php if($stock !== 0): ?>
        <a href="<?= BASE_URL ?>/contact.php?motif=other&product=<?= urlencode($prod['name']) ?>" class="shop-cta">
          <svg width="14" height="14" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          Commander
        </a>
        <?php else: ?>
        <div style="margin-top:12px;text-align:center;font-size:12px;color:#dc2626;font-weight:700;">❌ Rupture de stock</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
