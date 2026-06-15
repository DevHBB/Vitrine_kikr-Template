<?php
require_once __DIR__ . '/layout.php';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Modules : si la checkbox n'est pas dans POST = décochée = 0
    $modules = ['planning','shop','newsletter','payment','client_area','invoice','mailing_sms'];
    foreach ($modules as $m) {
        set_setting('module_'.$m, isset($_POST['module_'.$m]) ? '1' : '0');
    }

    // Nav items
    $all_ids = db()->query('SELECT id FROM kk_nav')->fetchAll(PDO::FETCH_COLUMN);
    $active_ids = array_map('intval', $_POST['nav_active'] ?? []);
    foreach ($all_ids as $nid) {
        db()->prepare('UPDATE kk_nav SET active=? WHERE id=?')
           ->execute([in_array((int)$nid, $active_ids) ? 1 : 0, $nid]);
    }
    $saved = true;
}

$modules_def = [
    'planning'    => ['📅', 'Planning & RDV',      'Page planning, prise de RDV en ligne'],
    'shop'        => ['🛒', 'Boutique',             'Page shop, vente en ligne'],
    'newsletter'  => ['📧', 'Newsletter',           'Inscriptions et envoi de newsletters'],
    'payment'     => ['💳', 'Paiement en ligne',    'Stripe, PayPal, liens de paiement'],
    'client_area' => ['👤', 'Espace client',        'Compte client, suivi RDV et factures'],
    'invoice'     => ['🧾', 'Facturation',          'Devis, factures, avoirs PDF'],
    'mailing_sms' => ['📱', 'SMS',                  'Notifications et campagnes SMS'],
];
$nav_items = db()->query('SELECT * FROM kk_nav ORDER BY position')->fetchAll();
?>
<div class="adm-topbar"><h1>🔧 Modules & Navigation</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Paramètres enregistrés.</div><?php endif; ?>

<form method="POST">

<!-- MODULES -->
<div class="card">
  <div class="card-head"><h2><span class="icon">⚡</span> Modules actifs</h2></div>
  <p class="card-hint" style="margin-bottom:16px;">Désactiver un module le masque sur le site. Les données sont conservées.</p>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <?php foreach($modules_def as $k => [$ico,$lbl,$desc]):
      $on = module_on($k);
    ?>
    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= $on?'var(--red)':'var(--border)' ?>;border-radius:12px;cursor:pointer;background:<?= $on?'#fef2f2':'var(--bg)' ?>;transition:all .2s;"
           onclick="toggleModule(this, '<?= $k ?>')">
      <div class="tgl <?= $on?'on':'' ?>" id="tgl-<?= $k ?>"></div>
      <!-- Champ caché qui prend la valeur 1 quand coché -->
      <input type="hidden" name="module_<?= $k ?>" value="<?= $on?'1':'0' ?>" id="inp-<?= $k ?>">
      <div>
        <div style="font-size:20px;margin-bottom:3px;"><?= $ico ?></div>
        <div style="font-size:13px;font-weight:700;"><?= $lbl ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= $desc ?></div>
      </div>
      <div style="margin-left:auto;font-size:12px;font-weight:700;color:<?= $on?'#ed0c0f':'#bbb' ?>;"><?= $on?'ON':'OFF' ?></div>
    </label>
    <?php endforeach; ?>
  </div>
</div>

<!-- NAVIGATION -->
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><span class="icon">🔗</span> Liens de navigation — activer / désactiver</h2></div>
  <p class="card-hint"><a href="<?= BASE_URL ?>/admin/nav.php">Modifier les libellés, URL et sous-menus →</a></p>
  <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;">
    <?php foreach($nav_items as $item): ?>
    <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1.5px solid <?= $item['active']?'var(--red)':'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= $item['active']?'#fef2f2':'var(--bg)' ?>;">
      <input type="checkbox" name="nav_active[]" value="<?= $item['id'] ?>" <?= $item['active']?'checked':'' ?> style="width:16px;height:16px;accent-color:#ed0c0f;">
      <span style="font-size:14px;font-weight:600;"><?= h($item['label']) ?></span>
      <?php if($item['parent_id']): ?><span style="font-size:10px;color:#aaa;">↳ sous-menu</span><?php endif; ?>
      <span style="font-size:11px;color:#aaa;margin-left:4px;">→ <?= h($item['href']) ?></span>
    </label>
    <?php endforeach; ?>
  </div>
  <a href="<?= BASE_URL ?>/admin/nav.php" class="btn btn-secondary btn-sm" style="margin-top:10px;">+ Ajouter / modifier des liens</a>
</div>

<button type="submit" class="btn btn-primary btn-lg" style="margin-top:16px;">💾 Enregistrer</button>
</form>
</div>

<style>
.tgl{width:44px;height:24px;background:#ddd;border-radius:12px;position:relative;flex-shrink:0;transition:background .2s}
.tgl::after{content:'';position:absolute;width:18px;height:18px;background:white;border-radius:50%;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.tgl.on{background:#ed0c0f}
.tgl.on::after{left:23px}
</style>
<script>
function toggleModule(lbl, key) {
  var tgl = document.getElementById('tgl-'+key);
  var inp = document.getElementById('inp-'+key);
  var isOn = tgl.classList.toggle('on');
  inp.value = isOn ? '1' : '0';
  // Aussi mettre le name pour que ce soit soumis comme "module_X"
  inp.name = 'module_'+key;
  lbl.style.borderColor = isOn ? 'var(--red)' : 'var(--border)';
  lbl.style.background  = isOn ? '#fef2f2'   : 'var(--bg)';
  lbl.querySelector('[style*="margin-left:auto"]').textContent = isOn ? 'ON' : 'OFF';
  lbl.querySelector('[style*="margin-left:auto"]').style.color = isOn ? '#ed0c0f' : '#bbb';
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
