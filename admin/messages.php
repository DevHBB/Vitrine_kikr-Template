<?php
ob_start();
require_once __DIR__ . '/layout.php';
ensure_tables();

// Marquer comme lu
if (isset($_GET['read']) && (int)$_GET['read'] > 0) {
    db()->prepare("UPDATE kk_messages SET read_at=NOW() WHERE id=?")->execute([(int)$_GET['read']]);
}
// Marquer tous comme lus
if (isset($_POST['mark_all_read'])) {
    db()->exec("UPDATE kk_messages SET read_at=NOW() WHERE read_at IS NULL");
    header('Location: '.BASE_URL.'/admin/messages.php'); exit;
}
// Supprimer
if (isset($_POST['delete_msg'])) {
    db()->prepare("DELETE FROM kk_messages WHERE id=?")->execute([(int)$_POST['msg_id']]);
    header('Location: '.BASE_URL.'/admin/messages.php'); exit;
}

$filter  = $_GET['filter'] ?? 'all';
$sql     = "SELECT * FROM kk_messages";
if ($filter === 'unread') $sql .= " WHERE read_at IS NULL";
$sql    .= " ORDER BY created_at DESC";
$msgs    = db()->query($sql)->fetchAll();
$unread  = (int)db()->query("SELECT COUNT(*) FROM kk_messages WHERE read_at IS NULL")->fetchColumn();

$motif_icons = [
    'Prise de RDV'       => '📅',
    'Demande de devis'   => '💶',
    'Question'           => '❓',
    'Autre'              => '📝',
];
?>
<div class="adm-topbar">
  <h1>💬 Messages reçus</h1>
  <div style="display:flex;gap:8px;">
    <a href="?filter=all"    class="btn <?= $filter==='all'   ?'btn-dark':'btn-ghost' ?> btn-sm">Tous (<?= count($msgs) ?>)</a>
    <a href="?filter=unread" class="btn <?= $filter==='unread'?'btn-primary':'btn-ghost' ?> btn-sm">
      Non lus <?= $unread > 0 ? "($unread)" : '' ?>
    </a>
    <?php if($unread > 0): ?>
    <form method="POST" style="display:inline;">
      <button type="submit" name="mark_all_read" class="btn btn-secondary btn-sm">✓ Tout marquer comme lu</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<div class="adm-content">

<?php if(empty($msgs)): ?>
<div class="card" style="text-align:center;padding:48px 20px;">
  <div style="font-size:52px;margin-bottom:14px;">📭</div>
  <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Aucun message</div>
  <div style="font-size:13px;color:var(--muted);">Les messages du formulaire de contact apparaîtront ici.</div>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:10px;">
  <?php foreach($msgs as $msg):
    $is_new = empty($msg['read_at']);
    $ico    = $motif_icons[$msg['motif']] ?? '📝';
  ?>
  <div style="background:white;border-radius:16px;border:2px solid <?= $is_new?'#3b82f6':'#f0f0f0' ?>;overflow:hidden;box-shadow:<?= $is_new?'0 2px 12px rgba(59,130,246,.12)':'var(--shadow)' ?>;">

    <!-- En-tête -->
    <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #f5f5f5;background:<?= $is_new?'#eff6ff':'white' ?>;">
      <span style="font-size:22px;"><?= $ico ?></span>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span style="font-size:14px;font-weight:800;"><?= h($msg['name']) ?></span>
          <?php if($is_new): ?><span style="background:#3b82f6;color:white;font-size:10px;font-weight:800;padding:2px 8px;border-radius:10px;">NOUVEAU</span><?php endif; ?>
          <span style="font-size:11px;color:#aaa;background:#f5f5f3;padding:2px 8px;border-radius:10px;"><?= h($msg['motif']) ?></span>
        </div>
        <div style="font-size:12px;color:#888;margin-top:2px;">
          <a href="mailto:<?= h($msg['email']) ?>" style="color:#3b82f6;"><?= h($msg['email']) ?></a>
          <?= $msg['phone'] ? ' · <a href="tel:'.h($msg['phone']).'" style="color:#555;">'.h($msg['phone']).'</a>' : '' ?>
          · <?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?>
        </div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        <!-- Répondre -->
        <a href="mailto:<?= h($msg['email']) ?>?subject=Re: <?= urlencode('Votre message — '.get_setting('site_name')) ?>"
           class="btn btn-primary btn-sm">📧 Répondre</a>
        <?php if($is_new): ?>
        <a href="?read=<?= $msg['id'] ?>&filter=<?= $filter ?>" class="btn btn-secondary btn-sm" title="Marquer comme lu">✓ Lu</a>
        <?php endif; ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
          <input type="hidden" name="delete_msg" value="1">
          <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">🗑</button>
        </form>
      </div>
    </div>

    <!-- Corps du message -->
    <div style="padding:16px 18px;">
      <div style="font-size:13px;color:#444;line-height:1.8;white-space:pre-wrap;"><?= h($msg['message']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
