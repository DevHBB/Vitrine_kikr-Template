<?php
require_once __DIR__ . '/layout.php';
$saved=false; $action=$_POST['action']??'';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if($action==='save_intro'){
        set_setting('partners_intro',   trim($_POST['intro']??''));
        set_setting('partners_subtitle',trim($_POST['subtitle']??''));
        $saved=true;
    }
    if($action==='save_group'){
        $gi=(int)($_POST['gi']??0);
        $lbl=trim($_POST['group_label']??''); $col=trim($_POST['color']??'#ed0c0f');
        if($gi>0) db()->prepare("UPDATE kk_partner_groups SET label=?,color=? WHERE id=?")->execute([$lbl,$col,$gi]);
        else { db()->prepare("INSERT INTO kk_partner_groups(position,label,color)VALUES(?,?,?)")->execute([(int)db()->query('SELECT COALESCE(MAX(position),0)+1 FROM kk_partner_groups')->fetchColumn(),$lbl,$col]); $gi=(int)db()->lastInsertId(); }
        db()->prepare("DELETE FROM kk_partners WHERE group_id=?")->execute([$gi]);
        $names=$_POST['iname']??[]; $types=$_POST['itype']??[]; $logos=$_POST['ilogo']??[];
        foreach($names as $k=>$n){ if(trim($n)){
            $logo=$logos[$k]??'';
            if(!empty($_FILES['ilogo_up']['tmp_name'][$k])){ $f=['name'=>$_FILES['ilogo_up']['name'][$k],'type'=>$_FILES['ilogo_up']['type'][$k],'tmp_name'=>$_FILES['ilogo_up']['tmp_name'][$k],'error'=>0,'size'=>$_FILES['ilogo_up']['size'][$k]]; $u=upload_media($f,'partners'); if($u) $logo=$u; }
            db()->prepare("INSERT INTO kk_partners(group_id,position,name,type,logo)VALUES(?,?,?,?,?)")->execute([$gi,$k,trim($n),trim($types[$k]??''),$logo]);
        }}
        $saved=true;
    }
    if($action==='delete_group'){ db()->prepare("DELETE FROM kk_partner_groups WHERE id=?")->execute([(int)($_POST['gi']??0)]); header('Location:/admin/partners.php');exit; }
}
$groups=get_partner_groups();
$egi=isset($_GET['edit'])?(int)$_GET['edit']:-1; $eg=null;
if($egi>=0){ $s=db()->prepare('SELECT * FROM kk_partner_groups WHERE id=?');$s->execute([$egi]);$eg=$s->fetch();
  if($eg){$s=db()->prepare('SELECT * FROM kk_partners WHERE group_id=? ORDER BY position');$s->execute([$egi]);$eg['items']=$s->fetchAll();}
}
?>
<div class="adm-topbar"><h1>Partenaires</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>
<div class="card"><div class="card-head"><h2>📝 Textes</h2></div>
  <form method="POST"><input type="hidden" name="action" value="save_intro">
    <div class="g2">
      <div class="fgrp"><label>Titre</label><input type="text" name="intro" value="<?= h(get_setting('partners_intro')) ?>"></div>
      <div class="fgrp"><label>Sous-titre</label><input type="text" name="subtitle" value="<?= h(get_setting('partners_subtitle')) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:10px;">💾 Enregistrer</button>
  </form>
</div>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2>🤝 Groupes</h2><a href="?edit=0" class="btn btn-dark btn-sm">+ Groupe</a></div>
  <div class="item-list">
  <?php foreach($groups as $g): ?>
  <div class="item-row">
    <div style="width:14px;height:14px;border-radius:3px;background:<?= h($g['color']) ?>;flex-shrink:0;"></div>
    <div class="item-row-name"><?= h($g['label']) ?></div>
    <div class="item-row-sub"><?= count($g['items']) ?> partenaire(s)</div>
    <div class="item-row-actions">
      <a href="?edit=<?= $g['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete_group"><input type="hidden" name="gi" value="<?= $g['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php if($egi>=0): $items=$eg['items']??[['name'=>'','type'=>'','logo'=>'']]; ?>
<div class="card" style="margin-top:16px;"><div class="card-head"><h2><?= $egi>0?'✏️ Modifier':'➕ Nouveau groupe' ?></h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_group"><input type="hidden" name="gi" value="<?= $egi ?>">
    <div class="g2"><div class="fgrp"><label>Nom du groupe</label><input type="text" name="group_label" value="<?= h($eg['label']??'') ?>" required></div><div class="fgrp"><label>Couleur</label><input type="color" name="color" value="<?= h($eg['color']??'#ed0c0f') ?>"></div></div>
    <hr class="sep">
    <div id="pl">
    <?php foreach($items as $k=>$item): ?>
    <div class="g2" style="margin-bottom:8px;"><div class="fgrp"><label>Nom <?=$k+1?></label><input type="text" name="iname[]" value="<?= h($item['name']) ?>"></div><div class="fgrp"><label>Type</label><input type="text" name="itype[]" value="<?= h($item['type']) ?>" placeholder="Revendeur…"></div><div class="fgrp"><label>Logo URL</label><input type="text" name="ilogo[]" value="<?= h($item['logo']??'') ?>"><input type="file" name="ilogo_up[]" accept="image/*" style="margin-top:4px;"></div></div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addP()" style="margin-bottom:12px;">+ Partenaire</button>
    <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">💾 Enregistrer</button><a href="/admin/partners.php" class="btn btn-secondary">Annuler</a></div>
  </form>
</div>
<script>let pc=<?= count($items) ?>;function addP(){const c=document.getElementById('pl');const d=document.createElement('div');d.className='g2';d.style.marginBottom='8px';d.innerHTML=`<div class="fgrp"><label>Nom ${pc+1}</label><input type="text" name="iname[]"></div><div class="fgrp"><label>Type</label><input type="text" name="itype[]" placeholder="Revendeur…"></div><div class="fgrp"><label>Logo URL</label><input type="text" name="ilogo[]"><input type="file" name="ilogo_up[]" accept="image/*" style="margin-top:4px;"></div>`;c.appendChild(d);pc++;}</script>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
