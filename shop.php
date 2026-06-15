<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = 'Shop';
$cat_filter = trim($_GET['cat'] ?? '');
$search     = trim($_GET['q']   ?? '');
$slug       = trim($_GET['p']   ?? '');    // page produit
$view       = $slug ? 'product' : 'listing';

// ─── Page produit ───
$product = null;
if ($view === 'product') {
    $product = get_product_by_slug($slug);
    if (!$product) { header('Location: '.BASE_URL.'/shop.php'); exit; }
    $page_title = $product['name'].' — Shop';
}

// ─── Panier en session ───
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Ajouter au panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $s   = db()->prepare('SELECT * FROM kk_products WHERE id=? AND active=1'); $s->execute([$pid]);
    $p   = $s->fetch();
    if ($p) {
        $key = 'p'.$pid;
        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$key] = [
                'id'    => $pid,
                'name'  => $p['name'],
                'price' => (float)($p['price_promo'] ?: $p['price']),
                'image' => (jd($p['images'],'[]')[0] ?? ''),
                'qty'   => $qty,
                'slug'  => $p['slug'],
            ];
        }
        session_write_close(); session_start();
    }
    // Réponse AJAX ou redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        $total_items = array_sum(array_column($_SESSION['cart'], 'qty'));
        echo json_encode(['ok'=>true,'cart_count'=>$total_items]);
        exit;
    }
    header('Location: '.BASE_URL.'/shop.php?cart=1'); exit;
}

// Retirer du panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_cart'])) {
    unset($_SESSION['cart']['p'.(int)$_POST['product_id']]);
    session_write_close(); session_start();
    header('Location: '.BASE_URL.'/shop.php?cart=1'); exit;
}

// Update quantité panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty_update'] ?? [] as $key => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) unset($_SESSION['cart'][$key]);
        else           $_SESSION['cart'][$key]['qty'] = $qty;
    }
    session_write_close(); session_start();
    header('Location: '.BASE_URL.'/shop.php?cart=1'); exit;
}

// Passer commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = $_SESSION['cart'] ?? [];
    if (!empty($cart)) {
        $name    = trim($_POST['name']  ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $addr    = trim($_POST['addr']  ?? '');
        $notes   = trim($_POST['notes'] ?? '');
        $ship    = (float)($_POST['shipping'] ?? 0);
        $method  = $_POST['payment_method'] ?? 'livraison';

        $items   = array_values($cart);
        $subtotal= array_sum(array_map(fn($i) => $i['price']*$i['qty'], $items));
        $total   = $subtotal + $ship;
        $num     = next_order_number();

        // Créer ou retrouver client
        $sc = db()->prepare('SELECT id FROM kk_clients WHERE email=? LIMIT 1');
        $sc->execute([$email]);
        $cid = $sc->fetchColumn() ?: null;
        if (!$cid) {
            db()->prepare('INSERT INTO kk_clients(name,email,phone,type) VALUES(?,?,?,?)')->execute([$name,$email,$phone,'particulier']);
            $cid = (int)db()->lastInsertId();
        }

        // Créer commande
        db()->prepare("INSERT INTO kk_orders(number,client_id,client_name,client_email,client_phone,shipping_addr,items,subtotal,shipping,total,payment_method,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$num,$cid,$name,$email,$phone,$addr,json_encode($items,JSON_UNESCAPED_UNICODE),$subtotal,$ship,$total,$method,$notes]);
        $order_id = (int)db()->lastInsertId();

        // Créer facture automatiquement
        $order = compact('items','subtotal','shipping','total','client_name','client_email','client_phone') + ['shipping_addr'=>$addr,'client_name'=>$name,'client_email'=>$email,'client_phone'=>$phone,'shipping'=>$ship,'items'=>json_encode($items)];
        $inv_id = create_order_invoice($order);
        if ($inv_id) db()->prepare("UPDATE kk_orders SET invoice_id=? WHERE id=?")->execute([$inv_id,$order_id]);

        // Maj stock
        foreach ($items as $item) {
            db()->prepare("UPDATE kk_products SET stock=GREATEST(stock-?,0) WHERE id=? AND stock>0")->execute([$item['qty'],$item['id']]);
        }

        // Email client
        $sname = get_setting('site_name');
        $body  = "Bonjour $name,\r\n\r\nMerci pour votre commande n°$num !\r\n\r\n";
        foreach ($items as $it) $body .= "• {$it['name']} x{$it['qty']} — ".number_format($it['price']*$it['qty'],2,',','')." €\r\n";
        $body .= "\r\nTotal : ".number_format($total,2,',','')." €\r\n\r\nNous vous contacterons rapidement pour confirmer.\r\n\r\nCordialement,\r\n$sname";
        mail($email,"✅ Commande $num — $sname",$body,"From: ".get_setting('site_email'));

        // Email admin
        $admin_body = "Nouvelle commande $num de $name ($email)\r\nTotal : ".number_format($total,2,',','')." €\r\nMode : $method";
        mail(get_setting('site_email'),"🛒 Nouvelle commande $num",$admin_body,"From: ".get_setting('site_email'));

        // Vider panier
        $_SESSION['cart'] = [];
        session_write_close(); session_start();
        $view = 'thanks';
        $order_num_done = $num;
    }
}

$products = get_products($cat_filter);
if ($search) {
    $products = array_filter($products, fn($p) => stripos($p['name'],$search)!==false || stripos($p['description']??'',$search)!==false);
}
$cats        = get_shop_cats();
$cart        = $_SESSION['cart'] ?? [];
$cart_count  = array_sum(array_column($cart,'qty'));
$cart_total  = array_sum(array_map(fn($i)=>$i['price']*$i['qty'],$cart));
$show_cart   = isset($_GET['cart']);

require_once __DIR__ . '/layout/header.php';
$stripe_pk  = get_setting('stripe_public_key','');
$paypal_cid = get_setting('paypal_client_id','');
$bank_iban  = get_setting('bank_iban','');
?>
<style>
/* ──────── SHOP GLOBAL ──────── */
.shop-banner{background:#111;color:white;padding:52px 0 44px;position:relative;overflow:hidden}
.shop-banner::before{content:'';position:absolute;inset:0;background:url('/img/media/shop-bg.jpg') center/cover no-repeat;opacity:.15}
.shop-banner .inner{position:relative;max-width:1200px;margin:0 auto;padding:0 24px}
.shop-banner h1{font-size:clamp(36px,5vw,60px);font-weight:900;letter-spacing:-2px;margin-bottom:8px}
.shop-banner p{color:#888;font-size:14px;margin-bottom:20px}
.shop-search{display:flex;gap:0;max-width:400px}
.shop-search input{flex:1;border:none;background:white;border-radius:10px 0 0 10px;padding:11px 16px;font-size:13px;font-family:inherit;outline:none}
.shop-search button{background:#ed0c0f;color:white;border:none;border-radius:0 10px 10px 0;padding:0 16px;cursor:pointer;font-size:13px;font-weight:700}

/* ──────── LAYOUT ──────── */
.shop-wrap{max-width:1200px;margin:0 auto;padding:36px 24px;display:grid;grid-template-columns:220px 1fr;gap:32px;align-items:start}
@media(max-width:768px){.shop-wrap{grid-template-columns:1fr}}

/* ──────── SIDEBAR ──────── */
.shop-sidebar{position:sticky;top:80px}
.shop-sidebar-card{background:white;border-radius:16px;padding:20px;box-shadow:var(--shadow);margin-bottom:14px}
.shop-sidebar-title{font-size:11px;font-weight:800;color:var(--gray4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px}
.shop-cat-link{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:8px;font-size:13px;font-weight:600;color:#555;text-decoration:none;transition:all .15s;margin-bottom:2px}
.shop-cat-link:hover,.shop-cat-link.active{background:#fef2f2;color:#ed0c0f}
.shop-cat-link.active{font-weight:800}
.shop-cat-count{font-size:11px;color:#aaa;background:#f5f5f3;border-radius:10px;padding:1px 7px}

/* ──────── GRILLE ──────── */
.shop-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}
.shop-card{background:white;border-radius:16px;overflow:hidden;box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;position:relative}
.shop-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(0,0,0,.12)}
.shop-card-media{position:relative;aspect-ratio:1;overflow:hidden;background:#f5f5f3}
.shop-card-media img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.shop-card:hover .shop-card-media img{transform:scale(1.06)}
.shop-card-media-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:52px;color:#ddd}
.shop-badge{position:absolute;top:10px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.shop-badge-featured{left:10px;background:#ed0c0f;color:white}
.shop-badge-promo{right:10px;background:#111;color:white}
.shop-badge-out{left:10px;background:#fee2e2;color:#dc2626}
.shop-card-body{padding:16px}
.shop-card-cat{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.shop-card-name{font-size:14px;font-weight:800;color:#111;margin-bottom:6px;line-height:1.3}
.shop-card-desc{font-size:12px;color:#888;line-height:1.5;margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.shop-card-footer{display:flex;align-items:center;justify-content:space-between;gap:8px}
.shop-price{display:flex;align-items:baseline;gap:6px}
.shop-price-main{font-size:18px;font-weight:900;color:#ed0c0f}
.shop-price-old{font-size:12px;color:#bbb;text-decoration:line-through}
.shop-add-btn{width:36px;height:36px;background:#111;color:white;border:none;border-radius:10px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:background .2s;flex-shrink:0}
.shop-add-btn:hover{background:#ed0c0f}
.shop-add-btn:disabled{background:#e0e0e0;cursor:not-allowed}
.shop-card-link{display:block;text-decoration:none;color:inherit}
.shop-stock-low{font-size:10px;font-weight:700;color:#d97706;margin-top:4px}

/* ──────── PAGE PRODUIT ──────── */
.prod-wrap{max-width:1100px;margin:0 auto;padding:40px 24px}
.prod-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
@media(max-width:768px){.prod-grid{grid-template-columns:1fr}}
.prod-gallery{position:sticky;top:80px}
.prod-main-img{aspect-ratio:1;background:#f5f5f3;border-radius:20px;overflow:hidden;margin-bottom:12px}
.prod-main-img img{width:100%;height:100%;object-fit:cover}
.prod-thumbs{display:flex;gap:8px;flex-wrap:wrap}
.prod-thumb{width:64px;height:64px;border-radius:10px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .2s}
.prod-thumb:hover,.prod-thumb.active{border-color:#ed0c0f}
.prod-thumb img{width:100%;height:100%;object-fit:cover}
.prod-info h1{font-size:clamp(24px,3vw,36px);font-weight:900;letter-spacing:-1.5px;margin-bottom:8px}
.prod-price-wrap{display:flex;align-items:baseline;gap:10px;margin:16px 0}
.prod-price-main{font-size:32px;font-weight:900;color:#ed0c0f}
.prod-price-old{font-size:18px;color:#bbb;text-decoration:line-through}
.prod-promo-pct{background:#fef2f2;color:#ed0c0f;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:800}
.prod-desc{font-size:14px;line-height:1.8;color:#555;margin-bottom:24px}
.prod-qty{display:flex;align-items:center;gap:0;border:2px solid #e8e8e8;border-radius:12px;overflow:hidden;width:fit-content;margin-bottom:16px}
.prod-qty button{width:40px;height:44px;border:none;background:white;font-size:20px;cursor:pointer;transition:background .2s}
.prod-qty button:hover{background:#f5f5f3}
.prod-qty input{width:60px;height:44px;border:none;border-left:2px solid #e8e8e8;border-right:2px solid #e8e8e8;text-align:center;font-size:16px;font-weight:700;font-family:inherit;outline:none}
.prod-add-big{width:100%;background:#111;color:white;border:none;border-radius:14px;padding:15px;font-size:15px;font-weight:800;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:10px}
.prod-add-big:hover{background:#ed0c0f}
.prod-trust{display:flex;gap:16px;margin-top:20px;flex-wrap:wrap}
.prod-trust-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#888}
.prod-breadcrumb{font-size:12px;color:#aaa;margin-bottom:20px}
.prod-breadcrumb a{color:#aaa;text-decoration:none}
.prod-breadcrumb a:hover{color:#ed0c0f}

/* ──────── PANIER SLIDE ──────── */
.cart-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;opacity:0;pointer-events:none;transition:opacity .25s}
.cart-overlay.open{opacity:1;pointer-events:auto}
.cart-panel{position:fixed;top:0;right:0;bottom:0;width:420px;max-width:100vw;background:white;z-index:1001;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;box-shadow:-8px 0 40px rgba(0,0,0,.15)}
.cart-panel.open{transform:translateX(0)}
.cart-head{padding:20px 22px;border-bottom:1.5px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between}
.cart-head h3{font-size:18px;font-weight:800}
.cart-close{width:34px;height:34px;background:#f5f5f3;border:none;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:background .2s}
.cart-close:hover{background:#e0e0e0}
.cart-items{flex:1;overflow-y:auto;padding:16px 22px}
.cart-item{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #f5f5f3;align-items:center}
.cart-item-img{width:60px;height:60px;border-radius:10px;object-fit:cover;background:#f5f5f3;flex-shrink:0}
.cart-item-info{flex:1;min-width:0}
.cart-item-name{font-size:13px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cart-item-price{font-size:13px;font-weight:800;color:#ed0c0f}
.cart-item-qty{display:flex;align-items:center;gap:0;border:1.5px solid #e8e8e8;border-radius:8px;overflow:hidden;margin-top:5px;width:fit-content}
.cart-item-qty button{width:28px;height:26px;border:none;background:white;cursor:pointer;font-size:14px;transition:background .15s}
.cart-item-qty button:hover{background:#f5f5f3}
.cart-item-qty span{width:32px;text-align:center;font-size:12px;font-weight:700;border-left:1.5px solid #e8e8e8;border-right:1.5px solid #e8e8e8;height:26px;display:flex;align-items:center;justify-content:center}
.cart-item-del{color:#ccc;background:none;border:none;cursor:pointer;font-size:16px;transition:color .15s}
.cart-item-del:hover{color:#dc2626}
.cart-foot{padding:16px 22px;border-top:2px solid #f0f0f0}
.cart-subtotal{display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-bottom:14px}
.cart-checkout-btn{width:100%;background:#ed0c0f;color:white;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer;transition:background .2s}
.cart-checkout-btn:hover{background:#c00b0d}
.cart-empty{text-align:center;padding:48px 20px;color:#aaa}
.cart-empty-ico{font-size:52px;margin-bottom:12px}
.cart-count-badge{position:absolute;top:-5px;right:-5px;background:#ed0c0f;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center}

/* ──────── CHECKOUT ──────── */
.checkout-wrap{max-width:680px;margin:0 auto;padding:40px 24px}
.checkout-card{background:white;border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:16px}
.checkout-title{font-size:16px;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.checkout-item{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f5f5f3}
.checkout-item:last-child{border:none}
.co-f{margin-bottom:12px}
.co-f label{display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.co-f input,.co-f select,.co-f textarea{width:100%;border:1.5px solid #e8e8e8;border-radius:9px;padding:9px 12px;font-size:13px;font-family:inherit;outline:none;transition:border-color .2s}
.co-f input:focus,.co-f select:focus{border-color:#ed0c0f}
.pay-opt{border:2px solid #e8e8e8;border-radius:10px;padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;margin-bottom:8px;transition:all .2s}
.pay-opt:hover,.pay-opt.sel{border-color:#ed0c0f;background:#fef2f2}
.co-total-row{display:flex;justify-content:space-between;font-size:14px;padding:6px 0;border-bottom:1px solid #f5f5f3}
.co-total-row.main{font-size:18px;font-weight:900;border:none;padding-top:12px;color:#ed0c0f}
.co-btn{width:100%;background:#111;color:white;border:none;border-radius:12px;padding:15px;font-size:15px;font-weight:800;cursor:pointer;transition:background .2s}
.co-btn:hover{background:#ed0c0f}

/* ──────── THANKS ──────── */
.thanks-wrap{text-align:center;padding:80px 24px;max-width:500px;margin:0 auto}

/* ──────── EMPTY ──────── */
.shop-empty{text-align:center;padding:80px 20px;grid-column:1/-1}
.shop-empty-ico{font-size:64px;margin-bottom:16px}

/* ──────── CART BTN FLOATING ──────── */
.cart-float{position:fixed;bottom:28px;right:28px;background:#111;color:white;border:none;border-radius:50px;padding:14px 22px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 8px 28px rgba(0,0,0,.25);transition:all .2s;display:flex;align-items:center;gap:8px;z-index:500}
.cart-float:hover{background:#ed0c0f;transform:scale(1.04)}
.cart-float.hidden{transform:scale(0);opacity:0;pointer-events:none}
</style>

<!-- CART ICON in navbar override -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Mettre à jour le badge panier dans la nav
  var navCart = document.querySelector('.btn-dark[href*="shop"]');
  if (navCart) {
    var cnt = <?= $cart_count ?>;
    if (cnt > 0) {
      navCart.style.position = 'relative';
      var b = document.createElement('span');
      b.className = 'cart-count-badge';
      b.textContent = cnt;
      navCart.appendChild(b);
    }
  }
});
</script>

<?php if($view === 'thanks'): ?>
<!-- ═══════════════════ CONFIRMATION ═══════════════════ -->
<div class="thanks-wrap">
  <div style="font-size:72px;margin-bottom:16px;">✅</div>
  <h1 style="font-size:28px;font-weight:900;margin-bottom:10px;">Merci pour votre commande !</h1>
  <div style="background:#f0fdf4;border-radius:16px;padding:20px;margin:20px 0;">
    <div style="font-size:13px;color:#166534;font-weight:700;">Commande n° <?= h($order_num_done) ?></div>
    <div style="font-size:12px;color:#166534;margin-top:4px;">Un email de confirmation vous a été envoyé.</div>
  </div>
  <p style="font-size:14px;color:#888;line-height:1.7;margin-bottom:24px;">
    Nous allons traiter votre commande et vous contacter rapidement pour confirmer les détails.
  </p>
  <a href="<?= BASE_URL ?>/shop.php" style="display:inline-block;background:#111;color:white;border-radius:12px;padding:12px 28px;font-size:14px;font-weight:700;text-decoration:none;">← Continuer mes achats</a>
</div>

<?php elseif($view === 'product' && $product): ?>
<!-- ═══════════════════ PAGE PRODUIT ═══════════════════ -->
<div class="prod-wrap">
  <div class="prod-breadcrumb">
    <a href="<?= BASE_URL ?>/shop.php">Shop</a>
    <?php if($product['category']): ?> › <a href="<?= BASE_URL ?>/shop.php?cat=<?= urlencode($product['category']) ?>"><?= h($product['category']) ?></a><?php endif; ?>
    › <?= h($product['name']) ?>
  </div>

  <div class="prod-grid">
    <!-- Galerie -->
    <div class="prod-gallery">
      <div class="prod-main-img" id="prod-main-img">
        <?php if(!empty($product['images'][0])): ?>
        <img src="<?= h($product['images'][0]) ?>" alt="<?= h($product['name']) ?>" id="main-img-el">
        <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:80px;color:#ddd;">📦</div>
        <?php endif; ?>
      </div>
      <?php if(count($product['images']) > 1): ?>
      <div class="prod-thumbs">
        <?php foreach($product['images'] as $k => $img): ?>
        <div class="prod-thumb <?= $k===0?'active':'' ?>" onclick="setMainImg('<?= h($img) ?>',this)">
          <img src="<?= h($img) ?>" alt="">
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Infos -->
    <div class="prod-info">
      <?php if($product['category']): ?><div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?= h($product['category']) ?></div><?php endif; ?>
      <h1><?= h($product['name']) ?></h1>
      <?php if($product['featured']): ?><div style="display:inline-block;background:#fef2f2;color:#ed0c0f;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:800;margin-bottom:12px;">⭐ Coup de cœur</div><?php endif; ?>

      <div class="prod-price-wrap">
        <span class="prod-price-main"><?= number_format((float)($product['price_promo'] ?: $product['price']),2,',','') ?> €</span>
        <?php if($product['price_promo']): ?>
        <span class="prod-price-old"><?= number_format((float)$product['price'],2,',','') ?> €</span>
        <span class="prod-promo-pct">-<?= round((1-$product['price_promo']/$product['price'])*100) ?>%</span>
        <?php endif; ?>
      </div>

      <?php if($product['description']): ?>
      <div class="prod-desc"><?= nl2br(h($product['description'])) ?></div>
      <?php endif; ?>

      <?php
      $stock = (int)$product['stock'];
      $out   = $stock === 0;
      $low   = $stock > 0 && $stock <= 3;
      ?>
      <?php if($low): ?><div style="color:#d97706;font-size:12px;font-weight:700;margin-bottom:10px;">⚠️ Plus que <?= $stock ?> en stock !</div><?php endif; ?>

      <?php if(!$out): ?>
      <form method="POST" id="add-form">
        <input type="hidden" name="add_to_cart" value="1">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <div class="prod-qty">
          <button type="button" onclick="chgQty(-1)">−</button>
          <input type="number" name="qty" id="qty-inp" value="1" min="1" max="<?= $stock>0?$stock:99 ?>">
          <button type="button" onclick="chgQty(1)">+</button>
        </div>
        <button type="submit" class="prod-add-big">
          <svg width="18" height="18" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          Ajouter au panier
        </button>
      </form>
      <?php else: ?>
      <div style="background:#fee2e2;border-radius:12px;padding:14px;text-align:center;color:#dc2626;font-weight:700;margin-top:8px;">❌ Rupture de stock</div>
      <?php endif; ?>

      <div class="prod-trust">
        <div class="prod-trust-item">🚚 Livraison rapide</div>
        <div class="prod-trust-item">🔒 Paiement sécurisé</div>
        <div class="prod-trust-item">↩️ Retours acceptés</div>
      </div>
    </div>
  </div>
</div>
<script>
function setMainImg(src, thumb) {
  document.getElementById('main-img-el').src = src;
  document.querySelectorAll('.prod-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}
function chgQty(d) {
  var inp = document.getElementById('qty-inp');
  inp.value = Math.max(1, parseInt(inp.value||1) + d);
}
// AJAX add to cart
document.getElementById('add-form')?.addEventListener('submit', function(e) {
  e.preventDefault();
  var fd = new FormData(this);
  fetch('', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(d => {
      if (d.ok) { openCart(); updateCartBadge(d.cart_count); }
    });
});
</script>

<?php elseif($show_cart && !empty($cart)): ?>
<!-- ═══════════════════ CHECKOUT PAGE ═══════════════════ -->
<div class="checkout-wrap">
  <h1 style="font-size:28px;font-weight:900;letter-spacing:-1px;margin-bottom:24px;">🛒 Votre panier</h1>

  <!-- Récap articles -->
  <div class="checkout-card">
    <div class="checkout-title">📦 Articles</div>
    <form method="POST" id="update-cart-form">
      <input type="hidden" name="update_cart" value="1">
      <?php foreach($cart as $key => $item): ?>
      <div class="checkout-item">
        <?php if($item['image']): ?><img src="<?= h($item['image']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;flex-shrink:0;"><?php endif; ?>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:700;"><?= h($item['name']) ?></div>
          <div style="font-size:13px;font-weight:800;color:#ed0c0f;"><?= number_format($item['price'],2,',','') ?> € / unité</div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
          <input type="number" name="qty_update[<?= h($key) ?>]" value="<?= $item['qty'] ?>" min="0" max="99"
                 style="width:54px;border:1.5px solid #e8e8e8;border-radius:8px;padding:5px 8px;font-size:13px;text-align:center;">
        </div>
        <div style="font-size:14px;font-weight:800;width:70px;text-align:right;"><?= number_format($item['price']*$item['qty'],2,',','') ?> €</div>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="remove_cart" value="1"><input type="hidden" name="product_id" value="<?= $item['id'] ?>">
          <button type="submit" style="background:none;border:none;color:#ccc;cursor:pointer;font-size:18px;" title="Supprimer">×</button>
        </form>
      </div>
      <?php endforeach; ?>
    </form>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button type="submit" form="update-cart-form" class="btn btn-secondary btn-sm">Mettre à jour</button>
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-ghost btn-sm">← Continuer les achats</a>
    </div>
  </div>

  <!-- Formulaire commande -->
  <div class="checkout-card">
    <div class="checkout-title">👤 Vos informations</div>
    <form method="POST" id="checkout-form">
      <input type="hidden" name="checkout" value="1">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="co-f"><label>Nom complet *</label><input type="text" name="name" required placeholder="Jean Dupont"></div>
        <div class="co-f"><label>Email *</label><input type="email" name="email" required placeholder="jean@mail.fr"></div>
        <div class="co-f"><label>Téléphone</label><input type="tel" name="phone" placeholder="+33 6 …"></div>
        <div class="co-f"><label>Livraison</label>
          <select name="shipping" onchange="updateShipping(this.value)">
            <option value="0">🏪 Retrait en atelier (gratuit)</option>
            <option value="8.90">📦 Colissimo (8,90 €)</option>
            <option value="12.00">⚡ Express 24h (12,00 €)</option>
          </select>
        </div>
        <div class="co-f" style="grid-column:1/-1;"><label>Adresse de livraison</label><textarea name="addr" style="min-height:60px;" placeholder="Rue, ville, code postal…"></textarea></div>
        <div class="co-f" style="grid-column:1/-1;"><label>Notes / Commentaires</label><input type="text" name="notes" placeholder="Instructions particulières…"></div>
      </div>

      <!-- Paiement -->
      <div style="margin-top:14px;">
        <div style="font-size:11px;font-weight:800;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Mode de paiement</div>
        <?php if($stripe_pk): ?>
        <div class="pay-opt" onclick="selPay(this,'stripe')"><input type="radio" name="payment_method" value="stripe" style="display:none;"> <span style="font-size:18px;">💳</span> <div><div style="font-size:13px;font-weight:700;">Carte bancaire</div><div style="font-size:11px;color:#aaa;">Visa, Mastercard — Stripe sécurisé</div></div><span style="margin-left:auto;font-size:10px;color:#aaa;">🔒</span></div>
        <?php endif; ?>
        <?php if($paypal_cid): ?>
        <div class="pay-opt" onclick="selPay(this,'paypal')"><input type="radio" name="payment_method" value="paypal" style="display:none;"> <span style="font-size:18px;">🅿️</span> <div><div>PayPal</div></div></div>
        <?php endif; ?>
        <?php if($bank_iban): ?>
        <div class="pay-opt" onclick="selPay(this,'virement')"><input type="radio" name="payment_method" value="virement" style="display:none;"> <span style="font-size:18px;">🏦</span> <div><div>Virement bancaire</div><div style="font-size:11px;color:#aaa;">IBAN reçu par email</div></div></div>
        <?php endif; ?>
        <div class="pay-opt sel" id="pay-livraison" onclick="selPay(this,'livraison')"><input type="radio" name="payment_method" value="livraison" checked style="display:none;"> <span style="font-size:18px;">🤝</span> <div><div>Paiement à la livraison / retrait</div></div></div>
      </div>

      <!-- Total -->
      <div style="background:#f9f9f9;border-radius:12px;padding:16px;margin-top:16px;">
        <div class="co-total-row"><span>Sous-total</span><span><?= number_format($cart_total,2,',','') ?> €</span></div>
        <div class="co-total-row"><span>Livraison</span><span id="ship-price">Gratuit</span></div>
        <div class="co-total-row main"><span>Total TTC</span><span id="total-price"><?= number_format($cart_total,2,',','') ?> €</span></div>
      </div>
      <input type="hidden" name="shipping" id="ship-hidden" value="0">

      <button type="submit" class="co-btn" style="margin-top:16px;">Confirmer la commande →</button>
    </form>
  </div>
</div>
<script>
var cartBase = <?= $cart_total ?>;
function updateShipping(v) {
  v = parseFloat(v) || 0;
  document.getElementById('ship-hidden').value = v;
  document.getElementById('ship-price').textContent = v > 0 ? v.toFixed(2).replace('.',',') + ' €' : 'Gratuit';
  document.getElementById('total-price').textContent = (cartBase + v).toFixed(2).replace('.',',') + ' €';
  // sync aussi le select
  document.querySelectorAll('[name="shipping"]').forEach(s => { if(s !== document.querySelector('[name="shipping"]')) s.value = v; });
}
function selPay(el, val) {
  document.querySelectorAll('.pay-opt').forEach(o => o.classList.remove('sel'));
  el.classList.add('sel');
  el.querySelector('input').checked = true;
}
</script>

<?php else: ?>
<!-- ═══════════════════ LISTING ═══════════════════ -->
<div class="shop-banner">
  <div class="inner">
    <h1>Shop</h1>
    <p>Huiles, pièces et accessoires sélectionnés par Kik'r Suspension</p>
    <form class="shop-search" method="GET" action="/shop.php">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher un produit…">
      <button type="submit">🔍</button>
    </form>
  </div>
</div>

<div class="shop-wrap">
  <!-- Sidebar -->
  <aside class="shop-sidebar">
    <div class="shop-sidebar-card">
      <div class="shop-sidebar-title">Catégories</div>
      <a href="/shop.php<?= $search?'?q='.urlencode($search):'' ?>" class="shop-cat-link <?= !$cat_filter?'active':'' ?>">
        Tout voir
        <span class="shop-cat-count"><?= count(get_products()) ?></span>
      </a>
      <?php foreach($cats as $cat):
        $cnt = count(get_products($cat));
      ?>
      <a href="/shop.php?cat=<?= urlencode($cat) ?><?= $search?'&q='.urlencode($search):'' ?>"
         class="shop-cat-link <?= $cat_filter===$cat?'active':'' ?>">
        <?= h($cat) ?>
        <span class="shop-cat-count"><?= $cnt ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if($cart_count > 0): ?>
    <div class="shop-sidebar-card">
      <div class="shop-sidebar-title">Panier</div>
      <div style="font-size:22px;font-weight:900;color:#ed0c0f;margin-bottom:4px;"><?= number_format($cart_total,2,',','') ?> €</div>
      <div style="font-size:12px;color:#aaa;margin-bottom:12px;"><?= $cart_count ?> article<?= $cart_count>1?'s':'' ?></div>
      <a href="/shop.php?cart=1" class="btn-red" style="display:block;text-align:center;border-radius:10px;padding:10px;">Commander →</a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- Grille -->
  <div>
    <?php if($search || $cat_filter): ?>
    <div style="margin-bottom:20px;font-size:13px;color:#888;">
      <?= $cat_filter ? '<strong>'.h($cat_filter).'</strong>' : '' ?>
      <?= $search ? 'Résultats pour "<strong>'.h($search).'</strong>"' : '' ?>
      · <?= count($products) ?> produit<?= count($products)>1?'s':'' ?>
      <a href="/shop.php" style="color:#ed0c0f;margin-left:8px;">✕ Effacer</a>
    </div>
    <?php endif; ?>

    <?php if(empty($products)): ?>
    <div class="shop-empty">
      <div class="shop-empty-ico">🔍</div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:8px;">Aucun produit trouvé</h2>
      <p><a href="/shop.php" style="color:#ed0c0f;">Voir tous les produits</a></p>
    </div>
    <?php else: ?>
    <div class="shop-grid">
      <?php foreach($products as $p):
        $stock = (int)$p['stock'];
        $out   = $stock === 0;
        $low   = $stock > 0 && $stock <= 3;
        $price = (float)($p['price_promo'] ?: $p['price']);
      ?>
      <div class="shop-card">
        <a href="/shop.php?p=<?= h($p['slug']) ?>" class="shop-card-link">
          <div class="shop-card-media">
            <?php if(!empty($p['images'][0])): ?>
            <img src="<?= h($p['images'][0]) ?>" alt="<?= h($p['name']) ?>">
            <?php else: ?>
            <div class="shop-card-media-ph">📦</div>
            <?php endif; ?>
            <?php if($out): ?><div class="shop-badge shop-badge-out">Rupture</div>
            elseif($p['featured']): ?><div class="shop-badge shop-badge-featured">⭐ Vedette</div><?php endif; ?>
            <?php if($p['price_promo']): ?><div class="shop-badge shop-badge-promo">-<?= round((1-$p['price_promo']/$p['price'])*100) ?>%</div><?php endif; ?>
          </div>
          <div class="shop-card-body">
            <?php if($p['category']): ?><div class="shop-card-cat"><?= h($p['category']) ?></div><?php endif; ?>
            <div class="shop-card-name"><?= h($p['name']) ?></div>
            <?php if($p['description']): ?><div class="shop-card-desc"><?= h($p['description']) ?></div><?php endif; ?>
            <?php if($low): ?><div class="shop-stock-low">⚠️ Plus que <?= $stock ?> en stock</div><?php endif; ?>
          </div>
        </a>
        <div style="padding:0 16px 16px;">
          <div class="shop-card-footer">
            <div class="shop-price">
              <span class="shop-price-main"><?= number_format($price,2,',','') ?> €</span>
              <?php if($p['price_promo']): ?><span class="shop-price-old"><?= number_format((float)$p['price'],2,',','') ?> €</span><?php endif; ?>
            </div>
            <form method="POST">
              <input type="hidden" name="add_to_cart" value="1">
              <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="qty" value="1">
              <button type="submit" class="shop-add-btn" <?= $out?'disabled':'' ?> title="Ajouter au panier">+</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Panier flottant -->
<button class="cart-float <?= $cart_count===0?'hidden':'' ?>" onclick="window.location='/shop.php?cart=1'" id="cart-float-btn">
  🛒 <?= $cart_count ?> article<?= $cart_count>1?'s':'' ?> · <?= number_format($cart_total,2,',','') ?> €
</button>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
