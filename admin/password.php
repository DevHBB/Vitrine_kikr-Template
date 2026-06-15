<?php
require_once __DIR__ . '/layout.php';
$saved=$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $cur=trim($_POST['current']??'');
    $new=trim($_POST['new_pwd']??'');
    $cfm=trim($_POST['confirm']??'');
    $hash=get_setting('admin_password','');
    if(!password_verify($cur,$hash)) $error='Mot de passe actuel incorrect.';
    elseif(strlen($new)<6) $error='6 caractères minimum.';
    elseif($new!==$cfm) $error='Les mots de passe ne correspondent pas.';
    else { set_setting('admin_password',password_hash($new,PASSWORD_BCRYPT)); $saved=true; }
}
?>
<div class="adm-topbar"><h1>Changer le mot de passe</h1></div>
<div class="adm-content">
<?php if($saved): ?><div class="alert alert-ok">✅ Mot de passe mis à jour.</div><?php endif; ?>
<?php if($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
<div class="card" style="max-width:420px;">
  <div class="card-head"><h2><span class="icon">🔐</span> Nouveau mot de passe</h2></div>
  <form method="POST">
    <div class="fgrp" style="margin-bottom:12px;"><label>Actuel</label><input type="password" name="current" required></div>
    <div class="fgrp" style="margin-bottom:12px;"><label>Nouveau</label><input type="password" name="new_pwd" required minlength="6"></div>
    <div class="fgrp" style="margin-bottom:16px;"><label>Confirmer</label><input type="password" name="confirm" required></div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">💾 Mettre à jour</button>
  </form>
</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
