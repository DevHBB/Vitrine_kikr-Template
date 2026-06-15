<?php // layout/footer.php ?>
</div><!-- /.page-wrap -->
<footer>
  <div class="footer-top">
    <a href="<?= BASE_URL ?>/index.php" class="footer-logo">
    <?php $fl=get_setting('site_logo',''); if($fl): ?>
    <img src="<?= h($fl) ?>" alt="<?= h(get_setting('site_name')) ?>" style="height:32px;width:auto;object-fit:contain;filter:brightness(0) invert(1);opacity:.8;">
    <?php else: ?>
    <?= h(get_setting('site_name',"Kik'r")) ?><span style="font-weight:400;font-size:16px;color:#555;">.</span>
    <?php endif; ?>
  </a>
    <div class="footer-links">
      <?php foreach(get_nav() as $item): ?>
      <a href="<?= BASE_URL ?>/<?= h($item['href']) ?>"><?= h($item['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="footer-social">
      <?php foreach([
        'site_instagram' => ['Instagram', '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>'],
        'site_facebook'  => ['Facebook',  '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>'],
        'site_tiktok'    => ['TikTok',    '<path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>'],
        'site_twitter'   => ['X / Twitter','<path d="M4 4l16 16M4 20L20 4"/>'],
        'site_youtube'   => ['YouTube',   '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/>'],
        'site_linkedin'  => ['LinkedIn',  '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>'],
      ] as $key => [$label, $icon]):
        $url = get_setting($key,'');
        if(!$url) continue;
      ?>
      <a href="<?= h($url) ?>" target="_blank" aria-label="<?= $label ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Liens légaux -->
  <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;padding:16px 20px;border-top:1px solid #222;font-size:11px;">
    <a href="<?= BASE_URL ?>/legal.php?page=mentions-legales" style="color:#555;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#555'">Mentions légales</a>
    <a href="<?= BASE_URL ?>/legal.php?page=cgv"              style="color:#555;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#555'">CGV</a>
    <a href="<?= BASE_URL ?>/legal.php?page=cgu"              style="color:#555;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#555'">CGU</a>
    <a href="<?= BASE_URL ?>/legal.php?page=confidentialite"  style="color:#555;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#555'">Confidentialité</a>
    <a href="<?= BASE_URL ?>/legal.php?page=retour"           style="color:#555;text-decoration:none;transition:color .2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#555'">Retours</a>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy"><?= h(get_setting('footer_copyright', "© 2024 Kik'r Suspension.")) ?></div>
    <div class="footer-tagline"><?= h(get_setting('site_tagline')) ?></div>
  </div>
</footer>
</body>
</html>
