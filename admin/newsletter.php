<?php
require_once __DIR__ . '/layout.php';
ensure_tables();
$action=$_POST['action']??'';
$saved=false;$msg='';

if($action==='save_template'){
    $tid=(int)($_POST['tid']??0);
    if($tid>0) db()->prepare("UPDATE kk_mailing_templates SET name=?,subject=?,body_html=?,body_txt=?,updated_at=NOW() WHERE id=?")->execute([trim($_POST['tname']??''),trim($_POST['tsubject']??''),trim($_POST['tbody_html']??''),trim($_POST['tbody_txt']??''),$tid]);
    else{db()->prepare("INSERT INTO kk_mailing_templates(name,subject,body_html,body_txt) VALUES(?,?,?,?)")->execute([trim($_POST['tname']??''),trim($_POST['tsubject']??''),trim($_POST['tbody_html']??''),trim($_POST['tbody_txt']??'')]);}
    $saved=true;
}
if($action==='save_campaign'){
    $cid=(int)($_POST['cid']??0);
    $row=[trim($_POST['cname']??''), (int)($_POST['template_id']??0)?:null, trim($_POST['csubject']??''), trim($_POST['cbody_html']??''), trim($_POST['csegment']??'all'), trim($_POST['cchannel']??'email'), trim($_POST['csms_text']??''), 'draft'];
    if($cid>0) db()->prepare("UPDATE kk_campaigns SET name=?,template_id=?,subject=?,body_html=?,segment=?,channel=?,sms_text=?,status=?,updated_at=NOW() WHERE id=?")->execute([...$row,$cid]);
    else{db()->prepare("INSERT INTO kk_campaigns(name,template_id,subject,body_html,segment,channel,sms_text,status) VALUES(?,?,?,?,?,?,?,?)")->execute($row); $cid=(int)db()->lastInsertId();}
    header('Location:'.BASE_URL.'/admin/newsletter.php?tab=campaigns&saved=1');exit;
}
if($action==='send_campaign'){
    $cid=(int)($_POST['cid']??0);
    $c=db()->prepare('SELECT * FROM kk_campaigns WHERE id=?');$c->execute([$cid]);$camp=$c->fetch();
    if($camp){
        $subs=get_subscribers($camp['segment']);
        $sent=0;$from="From: ".get_setting('site_name')." <".get_setting('site_email').">";
        $pixel_base=BASE_URL.'/track_open.php';
        foreach($subs as $sub){
            $track_token=bin2hex(random_bytes(16));
            db()->prepare("INSERT INTO kk_campaign_sends(campaign_id,email,token) VALUES(?,?,?)")->execute([$cid,$sub['email'],$track_token]);
            $html=$camp['body_html'];
            $html=str_replace(['{{name}}','{{email}}'],[$sub['name'],$sub['email']],$html);
            $unsubscribe=BASE_URL.'/unsubscribe.php?e='.urlencode($sub['email']).'&t='.($sub['token']??'');
            $html.='<br><br><hr style="border:none;border-top:1px solid #eee;"><p style="font-size:10px;color:#aaa;text-align:center;">Vous recevez cet email car vous êtes inscrit(e) à notre newsletter.<br><a href="'.$unsubscribe.'">Se désabonner</a></p>';
            $html.='<img src="'.$pixel_base.'?t='.$track_token.'" width="1" height="1" style="display:none">';
            $headers="From: ".get_setting('site_name')." <".get_setting('site_email').">\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0";
            mail($sub['email'],$camp['subject'],$html,$headers);
            $sent++;
        }
        db()->prepare("UPDATE kk_campaigns SET status='sent',sent_at=NOW(),total_sent=? WHERE id=?")->execute([$sent,$cid]);
        $msg="✅ Campagne envoyée à $sent destinataire(s).";$saved=true;
    }
}
if($action==='delete_sub') { db()->prepare("DELETE FROM kk_newsletter_subscribers WHERE id=?")->execute([(int)$_POST['id']]); header('Location:'.BASE_URL.'/admin/newsletter.php?tab=subscribers');exit; }
if($action==='import_subs') {
    $lines=explode("\n",trim($_POST['import_list']??''));$n=0;
    foreach($lines as $line){ $parts=array_map('trim',explode(',',$line)); if(!empty($parts[0])&&filter_var($parts[0],FILTER_VALIDATE_EMAIL)){ subscribe($parts[0],$parts[1]??'');$n++; } }
    $msg="✅ $n abonné(s) importé(s).";$saved=true;
}

$tab=$_GET['tab']??'campaigns';
$campaigns=get_campaigns();
$templates=get_mailing_templates();
$subs=get_subscribers();
$edit_c=isset($_GET['edit_c'])?(int)$_GET['edit_c']:-1;$ec_data=null;
if($edit_c>=0){if($edit_c>0){$cx=db()->prepare('SELECT * FROM kk_campaigns WHERE id=?');$cx->execute([$edit_c]);$ec_data=$cx->fetch();}else{$ec_data=['id'=>0,'name'=>'','template_id'=>null,'subject'=>'','body_html'=>'','segment'=>'all','channel'=>'email','sms_text'=>''];}}
$edit_t=isset($_GET['edit_t'])?(int)$_GET['edit_t']:-1;$et_data=null;
if($edit_t>=0){if($edit_t>0){$tx=db()->prepare('SELECT * FROM kk_mailing_templates WHERE id=?');$tx->execute([$edit_t]);$et_data=$tx->fetch();}else{$et_data=['id'=>0,'name'=>'','subject'=>'','body_html'=>'<h2>{{name}}</h2><p>Votre contenu ici</p>','body_txt'=>'Bonjour {{name}},'];}}
$stats_total=count($subs);$stats_pro=count(array_filter($subs,fn($s)=>strpos($s['segment']??'','pro')!==false));
$total_sent_all=array_sum(array_column($campaigns,'total_sent'));$total_opens=array_sum(array_column($campaigns,'total_open'));
?>
<div class="adm-topbar">
  <h1>📧 Newsletter & Mailing</h1>
  <div style="display:flex;gap:6px;">
    <?php foreach(['campaigns'=>'Campagnes','subscribers'=>'Abonnés','templates'=>'Templates'] as $t=>$l): ?>
    <a href="?tab=<?= $t ?>" class="btn <?= $tab===$t?'btn-primary':'btn-ghost' ?> btn-sm"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="adm-content">
<?php if($saved&&$msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-ok">✅ Enregistré.</div><?php endif; ?>

<!-- Stats -->
<div class="stat-grid" style="margin-bottom:16px;">
  <div class="stat-card"><div class="stat-val"><?= $stats_total ?></div><div class="stat-lbl">Abonnés actifs</div></div>
  <div class="stat-card"><div class="stat-val"><?= count($campaigns) ?></div><div class="stat-lbl">Campagnes</div></div>
  <div class="stat-card"><div class="stat-val"><?= $total_sent_all ?></div><div class="stat-lbl">Emails envoyés</div></div>
  <div class="stat-card"><div class="stat-val red"><?= $total_sent_all>0?round($total_opens/$total_sent_all*100).'%':'—' ?></div><div class="stat-lbl">Taux d'ouverture</div></div>
</div>

<?php if($tab==='campaigns'): ?>
<!-- CAMPAGNES -->
<div class="card">
  <div class="card-head"><h2>📢 Campagnes</h2><a href="?tab=campaigns&edit_c=0" class="btn btn-primary btn-sm">+ Nouvelle</a></div>
  <div class="item-list">
  <?php foreach($campaigns as $camp): $or=$camp['total_sent']>0?round($camp['total_open']/$camp['total_sent']*100):0; ?>
  <div class="item-row">
    <div class="item-row-name"><?= h($camp['name']) ?></div>
    <div class="item-row-sub"><?= h($camp['subject']) ?></div>
    <span class="item-row-badge <?= $camp['status']==='sent'?'green':'' ?>"><?= ['draft'=>'✏️ Brouillon','sent'=>'✅ Envoyé','sending'=>'⏳ Envoi'][$camp['status']]??$camp['status'] ?></span>
    <div style="font-size:11px;color:var(--muted);"><?= $camp['total_sent'] ?> envois · <?= $or ?>% ouverture</div>
    <div class="item-row-actions">
      <a href="?tab=campaigns&edit_c=<?= $camp['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
      <?php if($camp['status']==='draft'): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Envoyer cette campagne maintenant ?')">
        <input type="hidden" name="action" value="send_campaign"><input type="hidden" name="cid" value="<?= $camp['id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">📤 Envoyer</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($campaigns)): ?><p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucune campagne.</p><?php endif; ?>
  </div>
</div>
<?php if($edit_c>=0): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_c>0?'✏️ Modifier':'➕ Nouvelle campagne' ?></h2></div>
  <form method="POST">
    <input type="hidden" name="action" value="save_campaign"><input type="hidden" name="cid" value="<?= $edit_c ?>">
    <div class="g2">
      <div class="fgrp"><label>Nom *</label><input type="text" name="cname" value="<?= h($ec_data['name']??'') ?>" required></div>
      <div class="fgrp"><label>Canal</label>
        <select name="cchannel">
          <option value="email" <?= ($ec_data['channel']??'')==='email'?'selected':'' ?>>📧 Email</option>
          <option value="sms"   <?= ($ec_data['channel']??'')==='sms'?'selected':'' ?>>📱 SMS</option>
          <option value="both"  <?= ($ec_data['channel']??'')==='both'?'selected':'' ?>>📧+📱 Les deux</option>
        </select>
      </div>
      <div class="fgrp"><label>Segment cible</label>
        <select name="csegment">
          <option value="all"        <?= ($ec_data['segment']??'')==='all'?'selected':'' ?>>👥 Tous</option>
          <option value="newsletter" <?= ($ec_data['segment']??'')==='newsletter'?'selected':'' ?>>📧 Newsletter</option>
          <option value="rdv"        <?= ($ec_data['segment']??'')==='rdv'?'selected':'' ?>>📅 Clients RDV</option>
          <option value="pro"        <?= ($ec_data['segment']??'')==='pro'?'selected':'' ?>>⭐ Clients PRO</option>
        </select>
      </div>
      <div class="fgrp"><label>Template (optionnel)</label>
        <select name="template_id" onchange="loadTemplate(this)">
          <option value="">— Aucun —</option>
          <?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" data-html="<?= h($t['body_html']) ?>" data-subj="<?= h($t['subject']) ?>" <?= ($ec_data['template_id']??0)==$t['id']?'selected':'' ?>><?= h($t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp full"><label>Sujet de l'email *</label><input type="text" name="csubject" id="csubject" value="<?= h($ec_data['subject']??'') ?>" required></div>
      <div class="fgrp full"><label>Corps de l'email (HTML)</label>
        <textarea name="cbody_html" id="cbody_html" style="min-height:200px;font-family:monospace;font-size:12px;"><?= h($ec_data['body_html']??'') ?></textarea>
        <span class="hint">Variables disponibles : {{name}}, {{email}}</span>
      </div>
      <div class="fgrp full"><label>Texte SMS (160 car. max)</label><input type="text" name="csms_text" value="<?= h($ec_data['sms_text']??'') ?>" maxlength="160" placeholder="Message SMS…"></div>
    </div>
    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
  </form>
</div>
<script>function loadTemplate(sel){var o=sel.options[sel.selectedIndex];if(o.value){document.getElementById('cbody_html').value=o.dataset.html||'';document.getElementById('csubject').value=o.dataset.subj||'';}}</script>
<?php endif; ?>

<?php elseif($tab==='subscribers'): ?>
<!-- ABONNÉS -->
<div class="card">
  <div class="card-head"><h2>👥 Abonnés (<?= count($subs) ?>)</h2></div>
  <div style="margin-bottom:12px;">
    <details style="background:var(--bg);border-radius:10px;padding:14px;">
      <summary style="cursor:pointer;font-size:13px;font-weight:700;">📥 Importer des abonnés (CSV : email,nom)</summary>
      <form method="POST" style="margin-top:12px;">
        <input type="hidden" name="action" value="import_subs">
        <div class="fgrp"><label>Un abonné par ligne : email,nom</label><textarea name="import_list" style="min-height:80px;font-family:monospace;font-size:12px;" placeholder="jean@mail.fr,Jean Dupont&#10;marie@mail.fr,Marie"></textarea></div>
        <button type="submit" class="btn btn-primary btn-sm">Importer</button>
      </form>
    </details>
  </div>
  <div class="item-list">
  <?php foreach($subs as $sub): ?>
  <div class="item-row">
    <div class="item-row-name"><?= h($sub['name']?:$sub['email']) ?></div>
    <div class="item-row-sub"><?= h($sub['email']) ?></div>
    <div class="item-row-sub"><?= h($sub['phone']??'') ?></div>
    <span class="item-row-badge"><?= h($sub['segment']) ?></span>
    <div class="item-row-actions">
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="action" value="delete_sub"><input type="hidden" name="id" value="<?= $sub['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($subs)): ?><p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Aucun abonné.</p><?php endif; ?>
  </div>
</div>

<?php elseif($tab==='templates'): ?>
<!-- TEMPLATES -->
<div class="card">
  <div class="card-head"><h2>📝 Templates email</h2><a href="?tab=templates&edit_t=0" class="btn btn-primary btn-sm">+ Nouveau</a></div>
  <div class="item-list">
  <?php foreach($templates as $t): ?>
  <div class="item-row">
    <div class="item-row-name"><?= h($t['name']) ?></div>
    <div class="item-row-sub"><?= h($t['subject']) ?></div>
    <div class="item-row-actions"><a href="?tab=templates&edit_t=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">✏️</a></div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php if($edit_t>=0): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><?= $edit_t>0?'✏️ Modifier':'➕ Nouveau template' ?></h2></div>
  <form method="POST">
    <input type="hidden" name="action" value="save_template"><input type="hidden" name="tid" value="<?= $edit_t ?>">
    <div class="g2">
      <div class="fgrp"><label>Nom du template</label><input type="text" name="tname" value="<?= h($et_data['name']??'') ?>" required></div>
      <div class="fgrp"><label>Sujet par défaut</label><input type="text" name="tsubject" value="<?= h($et_data['subject']??'') ?>"></div>
    </div>
    <div class="fgrp"><label>Corps HTML</label><textarea name="tbody_html" style="min-height:280px;font-family:monospace;font-size:12px;"><?= h($et_data['body_html']??'') ?></textarea></div>
    <div class="fgrp"><label>Version texte (fallback)</label><textarea name="tbody_txt" style="min-height:80px;"><?= h($et_data['body_txt']??'') ?></textarea></div>
    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
