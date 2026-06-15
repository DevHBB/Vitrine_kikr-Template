<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/data.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: '.BASE_URL.'/admin/planning.php'); exit; }

$name    = trim($_POST['client_name']  ?? '');
$email   = trim($_POST['client_email'] ?? '');
$phone   = trim($_POST['client_phone'] ?? '');
$ctype   = $_POST['client_type']       ?? 'particulier';
$svc_lb  = trim($_POST['service_label']?? '');
$status  = $_POST['status']            ?? 'confirmed';
$mmarq   = trim($_POST['moto_marque']  ?? '');
$mmodl   = trim($_POST['moto_modele']  ?? '');
$slot_d  = $_POST['slot_date']         ?? '';
$slot_t  = $_POST['slot_time']         ?? '';
$est     = $_POST['price_estimate'] !== '' ? (float)$_POST['price_estimate'] : null;
$notes_a = trim($_POST['notes_admin']  ?? '');
$priority= $ctype === 'pro' ? 1 : 0;

// Créer ou retrouver le client
$client_id = null;
if ($email) {
    $s = db()->prepare('SELECT id FROM kk_clients WHERE email=?'); $s->execute([$email]);
    $client_id = $s->fetchColumn() ?: null;
    if (!$client_id) {
        db()->prepare('INSERT INTO kk_clients(type,name,email,phone) VALUES(?,?,?,?)')->execute([$ctype,$name,$email,$phone]);
        $client_id = (int)db()->lastInsertId();
    }
}

db()->prepare("INSERT INTO kk_appointments
    (client_id,client_name,client_email,client_phone,client_type,
     moto_marque,moto_modele,service_label,
     slot_date,slot_time,duree_jours,status,notes_admin,price_estimate,priority)
    VALUES(?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)")
   ->execute([$client_id,$name,$email,$phone,$ctype,$mmarq,$mmodl,$svc_lb,$slot_d,$slot_t,$status,$notes_a,$est,$priority]);

$rdv_id = (int)db()->lastInsertId();

// Email de confirmation si confirmé et email fourni
if ($status === 'confirmed' && $email) {
    $sname = get_setting('site_name');
    $date_fmt = date('d/m/Y', strtotime($slot_d)) . ' à ' . substr($slot_t, 0, 5);
    mail($email, "✅ RDV confirmé — $sname",
        "Bonjour $name,\r\n\r\nVotre rendez-vous est confirmé.\r\n📅 $date_fmt\r\n🔧 $svc_lb\r\n\r\nKik'r Suspension\r\n".get_setting('site_phone'),
        "From: ".get_setting('site_email'));
}

header('Location: ' . BASE_URL . '/admin/planning_rdv.php?id=' . $rdv_id . '&created=1');
exit;
