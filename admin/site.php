<?php
require_once __DIR__ . '/layout.php';
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['site_name','site_tagline','site_phone','site_email','site_address',
               'site_instagram','site_facebook','footer_copyright','site_logo_height'];
    foreach ($fields as $k) set_setting($k, trim($_POST[$k] ?? ''));

    // Upload logo
    if (!empty($_FILES['site_logo_upload']['tmp_name'])) {
        $url = upload_media($_FILES['site_logo_upload'], 'media');
        if ($url) set_setting('site_logo', $url);
    } elseif (isset($_POST['site_logo_url'])) {
        set_setting('site_logo', trim($_POST['site_logo_url']));
    }
    // Supprimer le logo
    if (!empty($_POST['remove_logo'])) set_setting('site_logo', '');

    $saved = true;
}
?>
<div class="adm-topbar"><h1>Infos générales</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<form method="POST">
<div class="card"><div class="card-head"><h2><span class="icon">🌐</span> Identité & Logo</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Nom du site</label><input type="text" name="site_name" value="<?= h(get_setting('site_name')) ?>"><span class="hint">Affiché si pas de logo</span></div>
    <div class="fgrp"><label>Tagline</label><input type="text" name="site_tagline" value="<?= h(get_setting('site_tagline')) ?>"></div>
  </div>
  <hr class="sep">
  <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">🖼️ Logo (optionnel — remplace le nom texte)</div>

  <?php $cur_logo = get_setting('site_logo',''); ?>
  <?php if($cur_logo): ?>
  <div style="display:flex;align-items:center;gap:16px;padding:14px;background:var(--bg);border-radius:10px;margin-bottom:12px;">
    <img src="<?= h($cur_logo) ?>" alt="Logo actuel" style="height:48px;width:auto;object-fit:contain;border-radius:6px;background:#f5f5f3;padding:4px;">
    <div style="flex:1;">
      <div style="font-size:12px;font-weight:700;margin-bottom:2px;">Logo actuel</div>
      <div style="font-size:11px;color:var(--muted);"><?= h($cur_logo) ?></div>
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;color:#dc2626;">
      <input type="checkbox" name="remove_logo" value="1"> Supprimer le logo
    </label>
  </div>
  <?php endif; ?>

  <div class="g2">
    <div class="fgrp">
      <label>Uploader votre logo (PNG, SVG, WebP)</label>
      <input type="file" name="site_logo_upload" accept="image/png,image/svg+xml,image/webp,image/jpeg">
      <span class="hint">PNG fond transparent recommandé</span>
    </div>
    <div class="fgrp">
      <label>Ou coller l'URL</label>
      <input type="text" name="site_logo_url" value="<?= h(get_setting('site_logo','')) ?>" placeholder="/img/media/logo.png">
    </div>
    <div class="fgrp">
      <label>Hauteur du logo (px)</label>
      <input type="number" name="site_logo_height" value="<?= h(get_setting('site_logo_height','38')) ?>" min="20" max="80">
      <span class="hint">38px recommandé pour la navbar</span>
    </div>
  </div>
</div>
<div class="card"><div class="card-head"><h2><span class="icon">📞</span> Coordonnées</h2></div>
  <div class="g2">
    <div class="fgrp"><label>Téléphone</label><input type="tel" name="site_phone" value="<?= h(get_setting('site_phone')) ?>"></div>
    <div class="fgrp"><label>Email</label><input type="email" name="site_email" value="<?= h(get_setting('site_email')) ?>"></div>
    <div class="fgrp full"><label>Adresse</label><input type="text" name="site_address" value="<?= h(get_setting('site_address')) ?>"></div>
  </div>
</div>
<div class="card"><div class="card-head"><h2><span class="icon">📱</span> Réseaux sociaux</h2></div>
  <p class="card-hint">Laissez vide les réseaux que vous n'utilisez pas — ils ne seront pas affichés.</p>
  <div class="g2">
    <div class="fgrp"><label>Instagram</label><input type="url" name="site_instagram" value="<?= h(get_setting('site_instagram')) ?>" placeholder="https://instagram.com/…"></div>
    <div class="fgrp"><label>Facebook</label><input type="url" name="site_facebook" value="<?= h(get_setting('site_facebook')) ?>" placeholder="https://facebook.com/…"></div>
    <div class="fgrp"><label>TikTok</label><input type="url" name="site_tiktok" value="<?= h(get_setting('site_tiktok')) ?>" placeholder="https://tiktok.com/@…"></div>
    <div class="fgrp"><label>X / Twitter</label><input type="url" name="site_twitter" value="<?= h(get_setting('site_twitter')) ?>" placeholder="https://x.com/…"></div>
    <div class="fgrp"><label>YouTube</label><input type="url" name="site_youtube" value="<?= h(get_setting('site_youtube')) ?>" placeholder="https://youtube.com/…"></div>
    <div class="fgrp"><label>LinkedIn</label><input type="url" name="site_linkedin" value="<?= h(get_setting('site_linkedin')) ?>" placeholder="https://linkedin.com/in/…"></div>
  </div>
</div>
<div class="card"><div class="card-head"><h2><span class="icon">©</span> Footer</h2></div>
  <div class="fgrp"><label>Copyright</label><input type="text" name="footer_copyright" value="<?= h(get_setting('footer_copyright')) ?>"></div>
</div>
<button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer</button>
</form>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
<?php // Appended — payment settings handled in planning_settings.php ?>
