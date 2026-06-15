<?php
ob_start();
require_once __DIR__ . '/layout.php';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->exec("DELETE FROM kk_nav");

    $labels  = $_POST['label']  ?? [];
    $hrefs   = $_POST['href']   ?? [];
    $parents = $_POST['parent'] ?? []; // valeur = index dans le tableau
    $actives = $_POST['active'] ?? [];
    $targets = $_POST['target'] ?? [];

    $pos    = 0;
    $id_map = []; // index soumis → db_id

    // 1ère passe : parents (parent[] = "0")
    foreach ($labels as $k => $lbl) {
        if (!trim($lbl)) continue;
        if (empty($parents[$k]) || $parents[$k] === '0' || $parents[$k] === '') {
            db()->prepare("INSERT INTO kk_nav(position,label,href,parent_id,active,target) VALUES(?,?,?,NULL,?,?)")
               ->execute([$pos++, trim($lbl), trim($hrefs[$k] ?? '#'), isset($actives[$k]) ? 1 : 0, $targets[$k] ?? '_self']);
            $id_map[$k] = (int)db()->lastInsertId();
        }
    }
    // 2ème passe : enfants
    foreach ($labels as $k => $lbl) {
        if (!trim($lbl)) continue;
        $pk = $parents[$k] ?? '0';
        if ($pk !== '0' && $pk !== '') {
            $parent_db_id = $id_map[(int)$pk] ?? null;
            db()->prepare("INSERT INTO kk_nav(position,label,href,parent_id,active,target) VALUES(?,?,?,?,?,?)")
               ->execute([$pos++, trim($lbl), trim($hrefs[$k] ?? '#'), $parent_db_id,
                           isset($actives[$k]) ? 1 : 0, $targets[$k] ?? '_self']);
        }
    }
    $saved = true;
}

$nav_items    = db()->query('SELECT * FROM kk_nav ORDER BY COALESCE(parent_id,0), position')->fetchAll();
$parents_only = array_values(array_filter($nav_items, fn($n) => !$n['parent_id']));
$children_by  = [];
foreach ($nav_items as $item) {
    if ($item['parent_id']) $children_by[$item['parent_id']][] = $item;
}

// On va construire un tableau ordonné : [parents puis enfants]
// Et on assigne un index séquentiel à chaque ligne → utilisé dans parent[]
$ordered = [];
$parent_idx_map = []; // db_id du parent → index dans $ordered

foreach ($parents_only as $p) {
    $idx = count($ordered);
    $parent_idx_map[$p['id']] = $idx;
    $ordered[] = array_merge($p, ['_is_child' => false, '_parent_idx' => null]);

    foreach ($children_by[$p['id']] ?? [] as $ch) {
        $ordered[] = array_merge($ch, ['_is_child' => true, '_parent_idx' => $idx]);
    }
}
?>
<style>
/* ===== NAV EDITOR ===== */
.nav-cols {
  display: grid;
  grid-template-columns: 28px 1fr 1fr 180px 130px 72px 36px;
  gap: 8px;
  align-items: center;
}
.nav-header-row {
  padding: 8px 12px 10px;
  font-size: 10px; font-weight: 800; color: var(--muted);
  text-transform: uppercase; letter-spacing: .8px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 10px;
}
.nav-row {
  padding: 10px 12px;
  background: white;
  border-radius: 12px;
  border: 1.5px solid #f0f0ee;
  margin-bottom: 6px;
  transition: border-color .2s, box-shadow .2s, transform .15s;
}
.nav-row:hover {
  border-color: #e0e0e0;
  box-shadow: 0 3px 12px rgba(0,0,0,.07);
  transform: translateY(-1px);
}
.nav-row.is-child {
  background: #fef9f9;
  border-left: 3px solid #ed0c0f;
  margin-left: 36px;
}
.nav-row.is-child:hover { border-left-color: #ed0c0f; }

/* Inputs */
.nav-input {
  width: 100%;
  border: 1.5px solid #ebebeb;
  border-radius: 8px;
  padding: 8px 11px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  background: #fafafa;
  transition: border-color .2s, background .2s, box-shadow .2s;
  color: #222;
}
.nav-input:focus {
  border-color: #ed0c0f;
  background: white;
  box-shadow: 0 0 0 3px rgba(237,12,15,.08);
}
.nav-input::placeholder { color: #ccc; }

/* Selects */
.nav-select {
  width: 100%;
  border: 1.5px solid #ebebeb;
  border-radius: 8px;
  padding: 8px 8px;
  font-size: 12px;
  font-family: inherit;
  background: #fafafa;
  outline: none;
  cursor: pointer;
  color: #444;
  transition: border-color .2s, background .2s;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23aaa'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  padding-right: 24px;
}
.nav-select:focus { border-color: #ed0c0f; background-color: white; }

/* Toggle actif */
.nav-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 3px;
}
.nav-toggle input { width: 16px; height: 16px; accent-color: #ed0c0f; cursor: pointer; }
.nav-toggle span {
  font-size: 9px; font-weight: 800;
  color: #aaa; letter-spacing: .5px;
  text-transform: uppercase;
}
.nav-toggle input:checked + span { color: #ed0c0f; }

/* Bouton supprimer */
.nav-del {
  width: 34px; height: 34px;
  background: #fff0f0; border: 1.5px solid #fecaca;
  border-radius: 8px; cursor: pointer;
  color: #dc2626; font-size: 18px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: all .2s;
}
.nav-del:hover { background: #fecaca; border-color: #fca5a5; transform: scale(1.05); }

/* Handle drag */
.nav-handle {
  color: #d4d4d4; font-size: 15px;
  cursor: grab; text-align: center;
  user-select: none; padding-top: 2px;
  transition: color .15s;
}
.nav-row:hover .nav-handle { color: #aaa; }
</style>

<div class="adm-topbar">
  <h1>🔗 Navigation</h1>
  <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-secondary btn-sm">👁 Voir le site</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Navigation enregistrée.</div><?php endif; ?>

<form method="POST" id="nav-form">
<div class="card">
  <div class="card-head">
    <h2><span class="icon">🔗</span> Liens du menu</h2>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-primary btn-sm" onclick="addRow(false)">+ Lien principal</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="addRow(true)">↳ Sous-lien</button>
    </div>
  </div>
  <p class="card-hint">
    Les <strong>liens principaux</strong> apparaissent dans la navbar.<br>
    Les <strong>sous-liens</strong> apparaissent en dropdown sous leur parent (choisissez le parent dans la colonne "Sous-lien de").<br>
    Décochez "Actif" pour masquer sans supprimer.
  </p>

  <!-- Suggestion de pages -->
  <div style="background:#f5f5f3;border-radius:12px;padding:16px;margin-bottom:20px;">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">💡 Pages disponibles — cliquez pour ajouter rapidement</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;" id="quick-pages">
      <?php
      $all_pages = [
        ['label'=>'Accueil',          'href'=>'index.php',        'icon'=>'🏠'],
        ['label'=>'Qui sommes-nous',   'href'=>'about.php',        'icon'=>'👤'],
        ['label'=>'Services',          'href'=>'services.php',     'icon'=>'🔧'],
        ['label'=>'Partenaires',       'href'=>'partners.php',     'icon'=>'🤝'],
        ['label'=>'Portfolio',         'href'=>'portfolio.php',    'icon'=>'🏍️'],
        ['label'=>'Contact',           'href'=>'contact.php',      'icon'=>'✉️'],
        ['label'=>'SHOP',              'href'=>'shop.php',         'icon'=>'🛒'],
        ['label'=>'Planning / RDV',    'href'=>'planning.php',     'icon'=>'📅'],
        ['label'=>'Mon compte',        'href'=>'mon-compte.php',   'icon'=>'🔐'],
        ['label'=>'Mentions légales',  'href'=>'legal.php?page=mentions-legales','icon'=>'⚖️'],
        ['label'=>'CGV',               'href'=>'legal.php?page=cgv','icon'=>'📋'],
        ['label'=>'Confidentialité',   'href'=>'legal.php?page=confidentialite','icon'=>'🔒'],
      ];
      // Pages libres en DB
      try {
        $custom_pages = db()->query("SELECT slug, title FROM kk_pages WHERE status='published' ORDER BY title")->fetchAll();
        foreach($custom_pages as $cp) {
          $all_pages[] = ['label'=>$cp['title'],'href'=>'page.php?slug='.$cp['slug'],'icon'=>'📄'];
        }
      } catch(Exception $e) {}

      // Marquer les pages déjà dans le menu
      $existing_hrefs = array_column($nav_items, 'href');
      foreach($all_pages as $pg):
        $already = in_array($pg['href'], $existing_hrefs);
      ?>
      <button type="button"
              onclick="quickAdd('<?= addslashes($pg['label']) ?>','<?= addslashes($pg['href']) ?>')"
              style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;
                     border-radius:20px;border:1.5px solid <?= $already?'#ccc':'#e0e0e0' ?>;
                     background:<?= $already?'#f5f5f3':'white' ?>;
                     color:<?= $already?'#aaa':'#444' ?>;
                     font-size:12px;font-weight:600;cursor:pointer;
                     transition:all .2s;"
              <?= $already?'title="Déjà dans le menu"':'' ?>
              onmouseover="if(!<?= $already?'true':'false' ?>)this.style.borderColor='#ed0c0f',this.style.color='#ed0c0f'"
              onmouseout="this.style.borderColor='<?= $already?'#ccc':'#e0e0e0' ?>',this.style.color='<?= $already?'#aaa':'#444' ?>'">
        <span><?= $pg['icon'] ?></span>
        <?= h($pg['label']) ?>
        <?php if($already): ?><span style="font-size:10px;">✓</span><?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- En-têtes colonnes -->
  <div class="nav-cols nav-header-row">
    <span></span>
    <span>Label</span>
    <span>URL</span>
    <span>Sous-lien de</span>
    <span>Ouverture</span>
    <span style="text-align:center">Actif</span>
    <span></span>
  </div>

  <!-- Lignes -->
  <div id="nav-list">
  <?php foreach ($ordered as $idx => $item): ?>
  <div class="nav-row nav-cols <?= $item['_is_child'] ? 'is-child' : '' ?>" data-idx="<?= $idx ?>">
    <div class="nav-handle">⠿</div>

    <input type="text" name="label[]"
           value="<?= h($item['label']) ?>"
           class="nav-input"
           placeholder="<?= $item['_is_child'] ? 'Sous-lien' : 'Lien principal' ?>"
           required>

    <input type="text" name="href[]"
           value="<?= h($item['href'] ?? '#') ?>"
           class="nav-input"
           placeholder="about.php ou https://…">

    <!-- Parent selector : valeur = index dans ordered[] -->
    <select name="parent[]" class="nav-select" onchange="updateChildStyle(this,<?= $idx ?>)">
      <option value="0" <?= !$item['_is_child'] ? 'selected' : '' ?>>— Principal —</option>
      <?php foreach ($parents_only as $p):
        $p_idx = $parent_idx_map[$p['id']] ?? 0;
        $sel   = $item['_is_child'] && $item['parent_id'] == $p['id'];
      ?>
      <option value="<?= $p_idx ?>" <?= $sel ? 'selected' : '' ?>>
        ↳ <?= h($p['label']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select name="target[]" class="nav-select">
      <option value="_self"  <?= ($item['target'] ?? '_self') === '_self'  ? 'selected' : '' ?>>Même onglet</option>
      <option value="_blank" <?= ($item['target'] ?? '_self') === '_blank' ? 'selected' : '' ?>>Nouvel onglet</option>
    </select>

    <div class="nav-toggle">
      <input type="checkbox"
             name="active[<?= $idx ?>]"
             id="act-<?= $idx ?>"
             <?= $item['active'] ? 'checked' : '' ?>
             onchange="this.nextElementSibling.textContent=this.checked?'ON':'OFF'">
      <span><?= $item['active'] ? 'ON' : 'OFF' ?></span>
    </div>

    <button type="button" class="nav-del" onclick="this.closest('.nav-row').remove()" title="Supprimer">×</button>
  </div>
  <?php endforeach; ?>
  </div><!-- /#nav-list -->

  <div style="display:flex;gap:8px;margin-top:12px;">
    <button type="button" class="btn btn-primary btn-sm" onclick="addRow(false)">+ Lien principal</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addRow(true)">↳ Sous-lien</button>
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg" style="margin-top:16px;">💾 Enregistrer</button>
</form>
</div>

<script>
var rowCount = <?= count($ordered) ?>;

// Options parents pour les nouveaux liens
function getParentOptions(selectedIdx) {
  var opts = '<option value="0">— Principal —</option>';
  document.querySelectorAll('#nav-list .nav-row:not(.is-child) [name="label[]"]').forEach(function(inp) {
    var row = inp.closest('.nav-row');
    var idx = row.dataset.idx;
    var lbl = inp.value.trim();
    if (lbl) {
      opts += '<option value="' + idx + '"' + (selectedIdx == idx ? ' selected' : '') + '>↳ ' + lbl + '</option>';
    }
  });
  return opts;
}

function addRow(isChild) {
  var d = document.createElement('div');
  d.className = 'nav-row nav-cols' + (isChild ? ' is-child' : '');
  d.dataset.idx = rowCount;

  d.innerHTML =
    '<div class="nav-handle">⠿</div>'
    + '<input type="text" name="label[]" class="nav-input" placeholder="' + (isChild ? 'Sous-lien' : 'Nouveau lien') + '">'
    + '<input type="text" name="href[]" class="nav-input" placeholder="page.php">'
    + '<select name="parent[]" class="nav-select" onchange="updateChildStyle(this,' + rowCount + ')">'
        + getParentOptions(isChild ? -1 : 0)
      + '</select>'
    + '<select name="target[]" class="nav-select"><option value="_self">Même onglet</option><option value="_blank">Nouvel onglet</option></select>'
    + '<div class="nav-toggle"><input type="checkbox" name="active[' + rowCount + ']" id="act-' + rowCount + '" checked onchange="this.nextElementSibling.textContent=this.checked?\'ON\':\'OFF\'"><span>ON</span></div>'
    + '<button type="button" class="nav-del" onclick="this.closest(\'.nav-row\').remove()" title="Supprimer">×</button>';

  document.getElementById('nav-list').appendChild(d);
  // Sélectionner le premier parent disponible si sous-lien
  if (isChild) {
    var sel = d.querySelector('[name="parent[]"]');
    for (var i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value !== '0') { sel.options[i].selected = true; break; }
    }
  }
  rowCount++;
}

function quickAdd(label, href) {
  // Vérifier si déjà présent
  var exists = false;
  document.querySelectorAll('[name="href[]"]').forEach(function(inp) {
    if (inp.value === href) exists = true;
  });
  // Ajouter quand même (l'utilisateur peut vouloir dupliquer)
  addRow(false);
  var rows = document.querySelectorAll('#nav-list .nav-row');
  var last = rows[rows.length - 1];
  last.querySelector('[name="label[]"]').value = label;
  last.querySelector('[name="href[]"]').value  = href;
  // Scroll vers le nouveau lien
  last.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  // Flash visuel
  last.style.background = '#fef2f2';
  setTimeout(function() { last.style.background = ''; }, 600);
}

function updateChildStyle(sel, idx) {
  var row = sel.closest('.nav-row');
  if (sel.value !== '0') {
    row.classList.add('is-child');
  } else {
    row.classList.remove('is-child');
  }
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
