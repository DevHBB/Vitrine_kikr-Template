<?php
require_once __DIR__ . '/layout.php';
ensure_tables();

$saved = false;
$slug  = $_GET['page'] ?? 'mentions-legales';
$pages_meta = [
    'mentions-legales' => ['⚖️',  'Mentions légales',              'Obligatoire — éditeur, hébergeur, activité'],
    'cgv'              => ['📋',  'Conditions Générales de Vente', 'Obligatoire si vente de prestations'],
    'cgu'              => ['📱',  "Conditions Générales d'Utilisation", 'Règles d\'utilisation du site'],
    'confidentialite'  => ['🔒',  'Politique de confidentialité',  'Obligatoire RGPD'],
    'retour'           => ['↩️',  'Retours & Remboursements',       'Annulations, garanties, réclamations'],
];

// Infos légales globales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_global'])) {
    foreach (['legal_owner','legal_siret','legal_forme','legal_host','legal_host_addr'] as $k) {
        set_setting($k, trim($_POST[$k] ?? ''));
    }
    $saved = true;
}

// Sauvegarder une page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page'])) {
    $s = trim($_POST['slug'] ?? '');
    $t = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($s && $t) {
        save_legal($s, $t, $content);
        $slug = $s;
        $saved = true;
    }
}

$current_page = get_legal($slug);
?>
<div class="adm-topbar">
  <h1>⚖️ Pages légales</h1>
  <a href="<?= BASE_URL ?>/legal.php?page=<?= h($slug) ?>" target="_blank" class="btn btn-secondary btn-sm">👁 Voir sur le site</a>
</div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<!-- Infos globales -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-head">
    <h2><span class="icon">🏢</span> Informations légales globales</h2>
    <span style="font-size:11px;color:var(--muted);">Utilisées dans toutes les pages légales</span>
  </div>
  <p class="card-hint">Ces informations remplacent les variables <code>{PROPRIETAIRE}</code>, <code>{SIRET}</code>, etc. dans toutes vos pages légales.</p>
  <form method="POST">
    <input type="hidden" name="save_global" value="1">
    <div class="g2">
      <div class="fgrp">
        <label>Propriétaire / Gérant</label>
        <input type="text" name="legal_owner" value="<?= h(get_setting('legal_owner')) ?>" placeholder="Prénom Nom">
      </div>
      <div class="fgrp">
        <label>Forme juridique</label>
        <input type="text" name="legal_forme" value="<?= h(get_setting('legal_forme','Entreprise individuelle')) ?>" placeholder="EI, SARL, EURL…">
      </div>
      <div class="fgrp">
        <label>SIRET</label>
        <input type="text" name="legal_siret" value="<?= h(get_setting('legal_siret')) ?>" placeholder="000 000 000 00000">
      </div>
      <div class="fgrp">
        <label>Hébergeur du site</label>
        <input type="text" name="legal_host" value="<?= h(get_setting('legal_host')) ?>" placeholder="OVH SAS, Ionos…">
      </div>
      <div class="fgrp full">
        <label>Adresse de l'hébergeur</label>
        <input type="text" name="legal_host_addr" value="<?= h(get_setting('legal_host_addr')) ?>" placeholder="2 rue Kellermann, 59100 Roubaix">
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px;">💾 Enregistrer</button>
  </form>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start;">

  <!-- Navigation pages -->
  <div class="card" style="padding:8px;">
    <?php foreach($pages_meta as $s => [$ico,$title,$hint]): ?>
    <a href="?page=<?= $s ?>" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;margin-bottom:2px;background:<?= $slug===$s?'#fef2f2':'transparent' ?>;transition:background .15s;">
      <span style="font-size:18px;flex-shrink:0;margin-top:1px;"><?= $ico ?></span>
      <div>
        <div style="font-size:12px;font-weight:700;color:<?= $slug===$s?'#ed0c0f':'#111' ?>;"><?= $title ?></div>
        <div style="font-size:10px;color:#aaa;margin-top:1px;"><?= $hint ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Éditeur -->
  <div class="card">
    <?php if($current_page): ?>
    <div class="card-head">
      <h2><?= $pages_meta[$slug][0] ?? '📄' ?> <?= h($current_page['title']) ?></h2>
      <span style="font-size:11px;color:var(--muted);">Modifié le <?= date('d/m/Y à H:i', strtotime($current_page['updated_at'])) ?></span>
    </div>

    <!-- Variables disponibles -->
    <div style="background:#f5f5f3;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:11px;">
      <strong style="display:block;margin-bottom:5px;">Variables disponibles (remplacées automatiquement) :</strong>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach(['{SITE_NAME}','{EMAIL}','{TELEPHONE}','{ADRESSE}','{PROPRIETAIRE}','{SIRET}','{FORME_JURIDIQUE}','{HEBERGEUR}'] as $v): ?>
        <code style="background:white;border:1px solid #e0e0e0;border-radius:4px;padding:2px 6px;cursor:pointer;"
              onclick="insertVar('<?= $v ?>')"><?= $v ?></code>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="POST">
      <input type="hidden" name="save_page" value="1">
      <input type="hidden" name="slug" value="<?= h($slug) ?>">
      <div class="fgrp" style="margin-bottom:10px;">
        <label>Titre de la page</label>
        <input type="text" name="title" value="<?= h($current_page['title']) ?>" required>
      </div>
      <div class="fgrp">
        <label>Contenu (HTML autorisé)</label>
        <textarea name="content" id="legal-editor" style="min-height:520px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;"><?= h($current_page['content']) ?></textarea>
        <span class="hint">Balises HTML supportées : &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a&gt;</span>
      </div>
      <div style="display:flex;gap:8px;margin-top:14px;">
        <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
        <a href="<?= BASE_URL ?>/legal.php?page=<?= h($slug) ?>" target="_blank" class="btn btn-secondary">👁 Prévisualiser</a>
      </div>
    </form>
    <?php else: ?>
    <p style="color:var(--muted);font-size:13px;text-align:center;padding:30px;">
      Page non trouvée. <a href="<?= BASE_URL ?>/admin/legal.php">Retour</a>
    </p>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
function insertVar(v) {
  var ta = document.getElementById('legal-editor');
  var pos = ta.selectionStart;
  ta.value = ta.value.substring(0, pos) + v + ta.value.substring(ta.selectionEnd);
  ta.selectionStart = ta.selectionEnd = pos + v.length;
  ta.focus();
}
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
