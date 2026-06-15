<?php
require_once __DIR__ . '/../config.php';
start_session();
if(is_admin()){header('Location:'.BASE_URL.'/admin/');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pwd  = trim($_POST['password']??'');
    $hash = get_setting('admin_password','');
    if($hash && password_verify($pwd,$hash)){
        $_SESSION['kikr_admin']=true;
        header('Location:'.BASE_URL.'/admin/');exit;
    }
    $error='Mot de passe incorrect.';
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Kik'r</title><link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css"></head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">Kik<span>'</span>r.</div>
    <div class="login-sub">Panel d'administration</div>
    <?php if($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="fgrp" style="margin-bottom:16px;"><label>Mot de passe</label><input type="password" name="password" autofocus required></div>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Se connecter</button>
    </form>
  </div>
</div>
</body></html>
