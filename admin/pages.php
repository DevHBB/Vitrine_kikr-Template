<?php
require_once __DIR__ . '/layout.php';
?>
<div class="adm-topbar">
  <h1>Pages libres</h1>
  <a href="<?= BASE_URL ?>/admin/page_edit.php" class="btn btn-primary btn-sm">+ Nouvelle page</a>
</div>
<div class="adm-content">
  <?php if(empty($pages)): ?>
  <div class="card" style="text-align:center;padding:48px;">
    <p style="color:var(--muted);margin-bottom:16px;">Aucune page créée pour l'instant.</p>
    <a href="<?= BASE_URL ?>/admin/page_edit.php" class="btn btn-primary">+ Créer ma première page</a>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-head"><h2><span class="icon">📄</span> <?= count($pages) ?> page(s)</h2></div>
    <?php foreach($pages as $p): ?>
    <div class="page-card">
      <div class="page-card-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
      <div>
        <div class="page-card-title"><?= h($p['title']) ?></div>
        <div class="page-card-slug">/page.php?slug=<?= h($p['slug']) ?> — <?= count($p['blocks']??[]) ?> bloc(s)</div>
      </div>
      <span class="page-card-badge <?= ($p['status']??'draft')==='published'?'':'draft' ?>"><?= ($p['status']??'draft')==='published'?'Publié':'Brouillon' ?></span>
      <div style="display:flex;gap:6px;">
        <a href="<?= BASE_URL ?>/page.php?slug=<?= h($p['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">👁</a>
        <a href="<?= BASE_URL ?>/admin/page_edit.php?slug=<?= h($p['slug']) ?>" class="btn btn-secondary btn-sm">✏️ Modifier</a>
        <a href="<?= BASE_URL ?>/admin/pages.php?del=<?= h($p['slug']) ?>" class="btn btn-danger btn-sm"
           onclick="return confirm('Supprimer cette page définitivement ?')">🗑</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php
// Handle delete
if (isset($_GET['del'])) {
    db()->prepare("DELETE FROM kk_pages WHERE slug=?")->execute([$_GET['del']]);
    header('Location: '.BASE_URL.'/admin/pages.php'); exit;
}
require_once __DIR__ . '/layout_end.php';
?>
