<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/data.php';
require_admin();
$id=(int)($_GET['id']??0);
$inv=get_invoice($id);
if(!$inv){die('Introuvable');}
$tot=invoice_totals($inv['invoice_lines'],(float)$inv['tva_rate'],(float)$inv['discount'],$inv['discount_type']);
$sent=false;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $to=trim($_POST['to']??'');$subj=trim($_POST['subject']??'');$msg=trim($_POST['message']??'');
    if($to&&$subj){
        $types=['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'];
        $body="Bonjour {$inv['client_name']},\r\n\r\n$msg\r\n\r\n"
            . "Document : ".$types[$inv['type']]." n°{$inv['number']}\r\n"
            . "Montant TTC : ".number_format($tot['ttc'],2,',',' ')." €\r\n\r\n"
            . "Cordialement,\r\n".get_setting('site_name')."\r\n".get_setting('site_phone');
        if(mail($to,$subj,$body,"From: ".get_setting('site_email'))){
            db()->prepare("UPDATE kk_invoices SET status='sent',updated_at=NOW() WHERE id=? AND status='draft'")->execute([$id]);
            $sent=true;
        } else {$error="Erreur d'envoi";}
    } else {$error="Email et sujet requis.";}
}
require_once __DIR__ . '/layout.php';
?>
<div class="adm-topbar"><h1>📤 Envoyer <?= h($inv['number']) ?></h1><a href="invoices.php?edit=<?= $id ?>" class="btn btn-secondary btn-sm">← Retour</a></div>
<div class="adm-content">
<?php if($sent): ?><div class="alert alert-ok">✅ Email envoyé à <?= h($_POST['to']) ?>.</div><?php endif; ?>
<?php if($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
<div class="card" style="max-width:560px;">
  <form method="POST">
    <div class="fgrp"><label>Destinataire *</label><input type="email" name="to" value="<?= h($inv['client_email']) ?>" required></div>
    <div class="fgrp"><label>Sujet *</label><input type="text" name="subject" value="<?= h(get_setting('site_name')) ?> — <?= ['invoice'=>'Facture','quote'=>'Devis','credit'=>'Avoir'][$inv['type']] ?> n°<?= h($inv['number']) ?>" required></div>
    <div class="fgrp"><label>Message</label><textarea name="message" style="min-height:120px;">Veuillez trouver ci-joint votre <?= ['invoice'=>'facture','quote'=>'devis','credit'=>'avoir'][$inv['type']] ?> n°<?= h($inv['number']) ?> d'un montant de <?= number_format($tot['ttc'],2,',',' ') ?> € TTC.<?= $inv['due_date']?"\r\nDate d'échéance : ".date('d/m/Y',strtotime($inv['due_date'])).'.':'' ?></textarea></div>
    <p style="font-size:11px;color:var(--muted);margin-bottom:12px;">📎 Le lien vers le PDF sera inclus dans l'email.</p>
    <button type="submit" class="btn btn-primary">📤 Envoyer</button>
  </form>
</div>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
