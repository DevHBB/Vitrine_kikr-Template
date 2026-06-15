<?php
require_once __DIR__ . '/layout.php';
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'&&!empty($_FILES['files']['name'][0])){
    $files=$_FILES['files']; $count=is_array($files['name'])?count($files['name']):1; $up=0;
    for($i=0;$i<$count;$i++){
        $f=['name'=>is_array($files['name'])?$files['name'][$i]:$files['name'],'type'=>is_array($files['type'])?$files['type'][$i]:$files['type'],'tmp_name'=>is_array($files['tmp_name'])?$files['tmp_name'][$i]:$files['tmp_name'],'error'=>0,'size'=>0];
        $url=upload_media($f,'media');
        if($url){db()->prepare("INSERT INTO kk_media(filename,url,sub)VALUES(?,?,?)")->execute([basename($url),$url,'media']);$up++;}
    }
    $msg="✅ $up fichier(s) uploadé(s).";
}
if(isset($_GET['del'])){
    $url=base64_decode($_GET['del']);
    db()->prepare("DELETE FROM kk_media WHERE url=?")->execute([$url]);
    $file=__DIR__.'/..'.$url; if(file_exists($file))unlink($file);
    header('Location:/admin/media.php');exit;
}
$medias=db()->query('SELECT * FROM kk_media ORDER BY uploaded_at DESC')->fetchAll();
?>
<div class="adm-topbar"><h1>Médiathèque</h1></div>
<div class="adm-content">
<?php if($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<div class="card"><div class="card-head"><h2>⬆️ Upload</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <div class="fgrp"><label>Images (JPG, PNG, WebP — sélection multiple)</label><input type="file" name="files[]" multiple accept="image/*"></div>
    <button type="submit" class="btn btn-primary" style="margin-top:10px;">⬆️ Uploader</button>
  </form>
</div>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2>🖼️ Images (<?= count($medias) ?>)</h2></div>
<?php if(empty($medias)): ?><p style="color:var(--muted);font-size:13px;">Aucune image.</p>
<?php else: ?>
<div class="media-grid">
  <?php foreach($medias as $m): ?>
  <div class="media-item" onclick="copyUrl('<?= h($m['url']) ?>')">
    <img src="<?= h($m['url']) ?>" alt="" loading="lazy">
    <div class="media-filename"><?= h($m['filename']) ?></div>
    <a href="/admin/media.php?del=<?= base64_encode($m['url']) ?>" class="del-btn" onclick="return confirm('Supprimer ?')" title="Supprimer">×</a>
  </div>
  <?php endforeach; ?>
</div>
<p style="font-size:11px;color:var(--muted);margin-top:12px;">💡 Cliquer = copier l'URL</p>
<?php endif; ?>
</div>
</div>
<script>function copyUrl(u){navigator.clipboard.writeText(u).then(()=>{const e=document.createElement('div');e.textContent='✅ URL copiée : '+u;e.style.cssText='position:fixed;bottom:20px;right:20px;background:#111;color:white;padding:10px 16px;border-radius:8px;font-size:12px;z-index:9999;';document.body.appendChild(e);setTimeout(()=>e.remove(),2500);});}</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
