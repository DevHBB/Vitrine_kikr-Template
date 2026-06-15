<?php
// Kik'r — Installeur

$root = dirname(__DIR__);
$cfg  = __DIR__ . '/config.ini';

if (file_exists($cfg)) {
    // Vérifier si l'installation est complète (mot de passe défini)
    try {
        $c_check = parse_ini_file($cfg);
        $pdo_check = new PDO(
            "mysql:host={$c_check['db_host']};dbname={$c_check['db_name']};charset=utf8mb4",
            $c_check['db_user'], $c_check['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pwd_check = $pdo_check->query("SELECT val FROM kk_settings WHERE `key`='admin_password' LIMIT 1")->fetchColumn();
        if ($pwd_check) {
            // Installation complète → admin
            header('Location: ../admin/'); exit;
        } else {
            // Config.ini existe mais pas encore de mot de passe → étape 4
            $step = (int)($_GET['step'] ?? 3);
            if ($step < 3) $step = 3;
        }
    } catch(Exception $e) {
        // DB pas encore accessible → rester sur étape 2
        $step = 2;
    }
}

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$ok     = false;

// ---- STEP 2 : Base de données ----
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = trim($_POST['db_pass'] ?? '');

    if (!$name) $errors[] = 'Nom de la base requis.';
    if (!$user) $errors[] = 'Utilisateur requis.';

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");

            $sql   = file_get_contents(__DIR__ . '/schema.sql');
            $stmts = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($stmts as $stmt) {
                if (trim($stmt)) try { $pdo->exec($stmt); } catch(Exception $e) {}
            }

            seed_defaults($pdo);

            $ini = "[db]\ndb_host=$host\ndb_name=$name\ndb_user=$user\ndb_pass=$pass\n";
            file_put_contents($cfg, $ini);

            header('Location: ?step=3'); exit;
        } catch (PDOException $e) {
            $errors[] = 'Erreur MySQL : ' . $e->getMessage();
        }
    }
}

// ---- STEP 3 : Config site (nom, logo, smtp, horaires, planning) ----
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $c   = parse_ini_file($cfg);
    $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
                    $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    $set = function(string $key, string $val) use ($pdo) {
        $pdo->prepare("INSERT INTO kk_settings(`key`,val) VALUES(?,?) ON DUPLICATE KEY UPDATE val=?")
           ->execute([$key, $val, $val]);
    };
    $psp = function(string $key, string $val) use ($pdo) {
        $pdo->prepare("INSERT INTO kk_planning_settings(`key`,val) VALUES(?,?) ON DUPLICATE KEY UPDATE val=?")
           ->execute([$key, $val, $val]);
    };

    // Identité
    $set('site_name',    trim($_POST['site_name']    ?? "Kik'r"));
    $set('site_tagline', trim($_POST['site_tagline'] ?? 'Préparation de suspensions moto'));
    $set('site_phone',   trim($_POST['site_phone']   ?? ''));
    $set('site_email',   trim($_POST['site_email']   ?? ''));
    $set('site_address', trim($_POST['site_address'] ?? ''));

    // Logo
    if (!empty($_FILES['site_logo']['tmp_name'])) {
        $ext  = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
        $dir  = $root . '/img/media';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'logo.' . $ext;
        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dir . '/' . $fname)) {
            $set('site_logo', '/img/media/' . $fname);
        }
    }

    // Planning
    $psp('rdv_mode',        $_POST['rdv_mode']      ?? 'request');
    $psp('rdv_public',      $_POST['rdv_public']    ?? 'partial');
    $psp('days_open',       implode(',', array_keys(array_filter($_POST['days_open'] ?? []))));
    $psp('time_open',       trim($_POST['time_open']  ?? '09:00'));
    $psp('time_close',      trim($_POST['time_close'] ?? '18:00'));
    $psp('max_per_day',     trim($_POST['max_per_day']?? '3'));
    $psp('rdv_delay_days',  trim($_POST['rdv_delay']  ?? '1'));
    $psp('notif_email',     '1');

    header('Location: ?step=4'); exit;
}

// ---- STEP 4 : Mot de passe admin ----
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = trim($_POST['password'] ?? '');
    $cfm = trim($_POST['confirm']  ?? '');
    if (strlen($pwd) < 6) $errors[] = 'Mot de passe : 6 caractères minimum.';
    if ($pwd !== $cfm)    $errors[] = 'Les mots de passe ne correspondent pas.';
    if (!$errors) {
        $c   = parse_ini_file($cfg);
        $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
                        $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO kk_settings(`key`,val) VALUES('admin_password',?)
            ON DUPLICATE KEY UPDATE val=?")->execute([$hash, $hash]);
        $ok = true;
    }
}

function seed_defaults(PDO $pdo): void {
    $pdo->exec("INSERT IGNORE INTO kk_hero (id,title_line1,title_line2,model_code,model_sub,model_desc,btn_label,reviews_count,reviews_text) VALUES
        (1,'Prépa moto','suspensions','KX450F','Modèle phare',
        'La prépa référence en motocross compétition. Kit complet fourche + amortisseur, calibré pour la piste.',
        'Découvrir','500+','pilotes satisfaits')");
    $pdo->exec("INSERT IGNORE INTO kk_hero_boxes (id,position,type,label,value,sub,style) VALUES
        (1,0,'reviews','','','','white'),
        (2,1,'stat','Satisfaction','98%','pilotes contents','white'),
        (3,2,'stat','Expérience','22 ans','de préparation','dark')");
    $pdo->exec("INSERT IGNORE INTO kk_specs (id,position,value,label,icon) VALUES
        (1,0,'48h','Délai express','bolt'),
        (2,1,'15 ans',\"D'expérience\",'clock'),
        (3,2,'500+','Pilotes équipés','star'),
        (4,3,'Toutes','Marques / modèles','bag')");
    $paras = json_encode(["Kik'r Suspension fête ses 22 ans de bons et loyaux services dédiés aux suspensions.","L'atelier est spécialisé dans le 2 roues : motocross, FMX, supermotard, enduro et vitesse.","En perpétuelle recherche d'améliorations pour le bon fonctionnement des suspensions.","Basé dans le Sud Est de la France, spécialistes de la préparation toutes marques."], JSON_UNESCAPED_UNICODE);
    $pdo->prepare("INSERT IGNORE INTO kk_about (id,title,experience,quote,paragraphs) VALUES (1,'QUI SOMMES NOUS ?','22',\"La passion et l'écoute sont 2 qualités qui nous animent.\",?)")->execute([$paras]);
    $pdo->prepare("INSERT IGNORE INTO kk_services (id,position,slug,label,title,description,price,highlight,treatments) VALUES
        (1,0,'entretien','ENTRETIEN','Vidange & nettoyage fourche/amortisseur',\"Démontage complet, nettoyage, remplacement des éléments d'usure.\", 'À partir de 120€ TTC','','[]'),
        (2,1,'preparation','PRÉPARATION','Préparation hydraulique',\"Démontage complet, modification du setting interne selon votre poids, niveau et type d'utilisation.\",'À partir de 170€ TTC','OFFRE FOURCHES+AMORTISSEUR 420€ TTC','[]'),
        (3,2,'anodisation','TRAITEMENT','Traitement & Anodisation','Traitement anti-friction des fourreaux. Coloris : KYB Factory, Or, Noir, Kashima, Brun, Bleu, Rouge.','À partir de 170€ TTC la paire','',?)")
        ->execute([json_encode(['DLC noir','TIN or','CHROMA bleu violiné','Rainbow 520€ TTC'], JSON_UNESCAPED_UNICODE)]);
    $pdo->exec("INSERT IGNORE INTO kk_partner_groups (id,position,label,color) VALUES (1,0,'SERVICE CENTER','#f5c400'),(2,1,'REVENDEUR','#ed0c0f'),(3,2,'CERAKOTE PARTENAIRE','#ed0c0f')");
    $pdo->exec("INSERT IGNORE INTO kk_partners (id,group_id,position,name,type) VALUES (1,1,0,'Öhlins','Service Center'),(2,1,1,'BOS Suspension','Service Center'),(3,2,0,'KYB','Revendeur'),(4,2,1,'WP Suspension','Revendeur'),(5,2,2,'Showa','Revendeur'),(6,2,3,'Andreani','Revendeur'),(7,3,0,'Joker Lab','Cerakote')");
    $pdo->exec("INSERT IGNORE INTO kk_pilots (id,position,name,discipline) VALUES (1,0,'Brice IZZO','FMX'),(2,1,'Robin KAPPEL','Supermotard'),(3,2,'David RINALDO','Motocross'),(4,3,'Axel MARIE LUCE','Supermotard'),(5,4,'Giani CATORC','Motocross'),(6,5,'Jean Baptiste MARRONE','Vitesse')");
    $hours = json_encode([['day'=>'Lundi — Vendredi','hours'=>'9h — 18h','rdv'=>false],['day'=>'Samedi','hours'=>'Sur RDV','rdv'=>true],['day'=>'Dimanche','hours'=>'Fermé','rdv'=>false]], JSON_UNESCAPED_UNICODE);
    $pdo->prepare("INSERT IGNORE INTO kk_contact (id,title,subtitle,fields_note,hours) VALUES (1,'Contactez-nous','Prenez rendez-vous ou demandez un devis','Décrivez votre moto, votre poids et votre terrain de pratique.',?)")->execute([$hours]);
    $pdo->exec("INSERT IGNORE INTO kk_nav (id,position,label,href) VALUES (1,0,'Accueil','index.php'),(2,1,'Qui sommes-nous','about.php'),(3,2,'Services','services.php'),(4,3,'Partenaires','partners.php'),(5,4,'Portfolio','portfolio.php'),(6,5,'Contact','contact.php'),(7,6,'SHOP','shop.php'),(8,7,'RDV','planning.php'),
        (9,8,'Mon compte','mon-compte.php')");
    $pdo->exec("INSERT IGNORE INTO kk_settings (`key`,val) VALUES
        ('site_name',\"Kik'r\"),('site_tagline','Préparation de suspensions moto'),
        ('site_phone','+33 6 00 00 00 00'),('site_email','contact@kikr-suspension.fr'),
        ('site_address','Sud-Est de la France'),('site_logo',''),('site_logo_height','38'),
        ('site_instagram',''),('site_facebook',''),
        ('footer_copyright',\"© 2024 Kik'r Suspension.\"),
        ('services_intro',\"L'atelier Kik'r fonctionne sur Rendez-Vous.\"),
        ('services_disclaimer',\"Les tarifs n'incluent ni les pièces d'usure, ni les fournitures.\"),
        ('services_depose_repose','Tarif dépose et repose 40€ TTC'),
        ('partners_intro','Nous travaillons avec eux.'),('partners_subtitle',\"Sans de bons partenaires il n'y aurait pas de bonnes suspensions.\"),
        ('portfolio_title','PORTFOLIO'),('portfolio_subtitle','Ils nous font confiance.')");
    $def_ps = ['rdv_mode'=>'request','rdv_public'=>'partial','slot_mode'=>'manual','slot_morning_time'=>'09:00','slot_interval'=>'120','notif_email'=>'1','notif_sms'=>'0','sms_api_key'=>'','sms_sender'=>"Kik'r",'pro_priority'=>'1','pdf_fiche_url'=>'','days_open'=>'1,2,3,4,5','time_open'=>'09:00','time_close'=>'18:00','rdv_delay_days'=>'1','max_per_day'=>'3'];
    foreach ($def_ps as $k=>$v) $pdo->prepare("INSERT IGNORE INTO kk_planning_settings(`key`,val) VALUES(?,?)")->execute([$k,$v]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation — Kik'r CMS</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#111;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:white;border-radius:20px;padding:40px;width:100%;max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.logo{font-size:30px;font-weight:900;letter-spacing:-1px;margin-bottom:4px}
.logo span{color:#ed0c0f}
.sub{font-size:13px;color:#888;margin-bottom:28px}
/* Étapes */
.steps{display:flex;gap:0;margin-bottom:32px;border-radius:12px;overflow:hidden;border:1.5px solid #f0f0f0}
.step-item{flex:1;padding:10px 8px;text-align:center;font-size:11px;font-weight:600;color:#ccc;border-right:1.5px solid #f0f0f0;position:relative}
.step-item:last-child{border-right:none}
.step-item.done{background:#ed0c0f;color:white}
.step-item.active{background:#fef2f2;color:#ed0c0f;font-weight:800}
.step-num{display:block;font-size:16px;font-weight:900;margin-bottom:2px}
/* Formulaire */
h2{font-size:17px;font-weight:800;margin-bottom:6px;color:#111}
.step-desc{font-size:12px;color:#888;margin-bottom:22px;line-height:1.6}
.fgrp{margin-bottom:14px}
.fgrp label{display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.fgrp input,.fgrp select{width:100%;border:1.5px solid #e0e0e0;border-radius:8px;padding:10px 13px;font-size:13px;outline:none;transition:border-color .2s;font-family:inherit}
.fgrp input:focus,.fgrp select:focus{border-color:#ed0c0f}
.fgrp small{font-size:11px;color:#bbb;margin-top:4px;display:block}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn{width:100%;background:#ed0c0f;color:white;border:none;border-radius:10px;padding:13px;font-size:14px;font-weight:700;cursor:pointer;transition:background .2s;margin-top:8px;font-family:inherit}
.btn:hover{background:#c00b0d}
.btn-ghost{background:#f5f5f3;color:#111;margin-top:6px}
.btn-ghost:hover{background:#e8e8e8}
.err{background:#fff0f0;border:1px solid #fcc;border-radius:8px;padding:12px 14px;font-size:13px;color:#c00;margin-bottom:16px}
/* Notice */
.notice{background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px 16px;font-size:12px;color:#15803d;margin-bottom:18px;line-height:1.6}
.notice strong{display:block;margin-bottom:4px;font-size:13px}
/* Jours */
.days-grid{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.day-btn{padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;color:#666;transition:all .2s}
.day-btn:has(input:checked){border-color:#ed0c0f;background:#fef2f2;color:#ed0c0f}
input[type=checkbox]{display:none}
/* Radio cards */
.radio-cards{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:6px}
.radio-card{padding:12px 8px;border:1.5px solid #e0e0e0;border-radius:10px;cursor:pointer;text-align:center;font-size:11px;font-weight:600;color:#666;transition:all .2s}
.radio-card:has(input:checked){border-color:#ed0c0f;background:#fef2f2;color:#ed0c0f}
.radio-card-ico{font-size:20px;margin-bottom:4px}
/* Logo preview */
.logo-preview{width:100%;height:80px;background:#f5f5f3;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;overflow:hidden}
.logo-preview img{max-height:60px;max-width:100%;object-fit:contain}
/* Succès */
.ok-box{text-align:center;padding:20px 0}
.ok-box .check{font-size:56px;margin-bottom:14px}
.ok-box h2{font-size:22px;margin-bottom:8px}
.ok-box p{color:#666;font-size:13px;margin-bottom:20px;line-height:1.6}
.ok-box a{display:inline-block;background:#111;color:white;border-radius:10px;padding:12px 28px;font-size:14px;font-weight:700;text-decoration:none;transition:background .2s}
.ok-box a:hover{background:#333}
@media(max-width:480px){.g2{grid-template-columns:1fr}.radio-cards{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="box">
  <div class="logo">Kik<span>'</span>r<span style="font-weight:300;font-size:20px;color:#ccc;">.</span></div>
  <div class="sub">Installation du CMS — Étape <?= $step ?>/4</div>

  <!-- Barre des étapes -->
  <div class="steps">
    <?php $step_labels = ['1'=>'Bienvenue','2'=>'Base de données','3'=>'Configuration','4'=>'Mot de passe']; ?>
    <?php foreach($step_labels as $n=>$lbl): ?>
    <div class="step-item <?= $step>(int)$n?'done':($step==(int)$n?'active':'') ?>">
      <span class="step-num"><?= $n ?></span>
      <?= $lbl ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if(!empty($errors)): ?>
  <div class="err"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <!-- ======== ÉTAPE 1 : Bienvenue ======== -->
  <?php if($step === 1): ?>
  <h2>👋 Bienvenue dans l'installeur</h2>
  <p class="step-desc">L'installation prend moins de 2 minutes et se fait en 4 étapes.<br>Vous aurez besoin de vos identifiants MySQL (disponibles dans phpMyAdmin).</p>
  <div class="notice">
    <strong>✅ Prérequis</strong>
    PHP 8.0+ · MySQL 5.7+ · Extension PDO MySQL activée<br>
    → Créez une base de données vide dans phpMyAdmin avant de continuer.
  </div>
  <a href="?step=2" class="btn" style="display:block;text-align:center;text-decoration:none;">Commencer →</a>

  <!-- ======== ÉTAPE 2 : Base de données ======== -->
  <?php elseif($step === 2): ?>
  <h2>🗄️ Connexion à la base de données</h2>
  <p class="step-desc">Renseignez vos identifiants MySQL. Sur XAMPP, l'utilisateur est généralement "root" sans mot de passe.</p>
  <form method="POST" action="?step=2">
    <div class="fgrp"><label>Hôte MySQL</label><input type="text" name="db_host" value="localhost" required><small>Généralement "localhost"</small></div>
    <div class="g2">
      <div class="fgrp"><label>Nom de la base *</label><input type="text" name="db_name" placeholder="kikr_db" required></div>
      <div class="fgrp"><label>Utilisateur *</label><input type="text" name="db_user" value="root" required></div>
    </div>
    <div class="fgrp"><label>Mot de passe</label><input type="password" name="db_pass" placeholder="Vide sur XAMPP local"></div>
    <button type="submit" class="btn">Créer les tables →</button>
  </form>

  <!-- ======== ÉTAPE 3 : Configuration ======== -->
  <?php elseif($step === 3): ?>
  <h2>⚙️ Configuration du site</h2>
  <div class="notice">
    <strong>💡 Ne vous inquiétez pas !</strong>
    Tout ce que vous configurez ici est <strong>modifiable à tout moment</strong> depuis l'admin.<br>
    Vous pouvez remplir les informations de base maintenant et affiner plus tard.
  </div>
  <form method="POST" action="?step=3" enctype="multipart/form-data">

    <!-- Identité -->
    <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">🌐 Identité</div>
    <div class="g2">
      <div class="fgrp"><label>Nom du site *</label><input type="text" name="site_name" value="Kik'r Suspension" required><small>Affiché si pas de logo</small></div>
      <div class="fgrp"><label>Tagline</label><input type="text" name="site_tagline" placeholder="Préparation de suspensions moto"></div>
    </div>

    <!-- Logo -->
    <div class="fgrp">
      <label>Logo (PNG fond transparent recommandé)</label>
      <div class="logo-preview" id="logo-preview"><span style="font-size:11px;color:#bbb;">Aperçu logo</span></div>
      <input type="file" name="site_logo" accept="image/png,image/svg+xml,image/webp,image/jpeg"
             onchange="previewLogo(this)" style="border:1.5px dashed #e0e0e0;border-radius:8px;padding:8px;width:100%;cursor:pointer;">
      <small>Optionnel — vous pouvez uploader votre logo plus tard dans Admin → Infos générales</small>
    </div>

    <!-- Coordonnées -->
    <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin:18px 0 10px;">📞 Coordonnées</div>
    <div class="g2">
      <div class="fgrp"><label>Téléphone</label><input type="tel" name="site_phone" placeholder="+33 6 00 00 00 00"></div>
      <div class="fgrp"><label>Email de contact *</label><input type="email" name="site_email" placeholder="contact@monsite.fr" required><small>Vous recevrez les RDV sur cet email</small></div>
    </div>
    <div class="fgrp"><label>Adresse / Localisation</label><input type="text" name="site_address" placeholder="Ville, région…"></div>

    <!-- Planning -->
    <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin:18px 0 10px;">📅 Planning (modifiable en détail dans Admin → Paramètres planning)</div>

    <div class="fgrp">
      <label>Mode de prise de rendez-vous</label>
      <div class="radio-cards">
        <label class="radio-card"><input type="radio" name="rdv_mode" value="request" checked><div class="radio-card-ico">📬</div>Demande<br><small style="font-weight:400;color:#888;">Vous validez</small></label>
        <label class="radio-card"><input type="radio" name="rdv_mode" value="direct"><div class="radio-card-ico">🗓️</div>Direct<br><small style="font-weight:400;color:#888;">Client réserve</small></label>
        <label class="radio-card"><input type="radio" name="rdv_mode" value="hybrid"><div class="radio-card-ico">⚡</div>Hybride<br><small style="font-weight:400;color:#888;">Les deux</small></label>
      </div>
    </div>

    <div class="fgrp">
      <label>Jours d'ouverture</label>
      <div class="days-grid">
        <?php foreach([1=>'Lun',2=>'Mar',3=>'Mer',4=>'Jeu',5=>'Ven',6=>'Sam',7=>'Dim'] as $n=>$d): ?>
        <label class="day-btn"><input type="checkbox" name="days_open[<?= $n ?>]" <?= $n<=5?'checked':'' ?>><?= $d ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="g2">
      <div class="fgrp"><label>Heure d'ouverture</label><input type="time" name="time_open" value="09:00"></div>
      <div class="fgrp"><label>Heure de fermeture</label><input type="time" name="time_close" value="18:00"></div>
      <div class="fgrp"><label>Max dépôts / jour</label><input type="number" name="max_per_day" value="3" min="1" max="20"></div>
      <div class="fgrp"><label>Délai min. (jours)</label><input type="number" name="rdv_delay" value="1" min="0" max="30"><small>0 = même jour possible</small></div>
    </div>

    <button type="submit" class="btn">Enregistrer et continuer →</button>
    <a href="?step=4" class="btn btn-ghost" style="display:block;text-align:center;text-decoration:none;padding:13px;">Passer cette étape →</a>
  </form>

  <!-- ======== ÉTAPE 4 : Mot de passe ======== -->
  <?php elseif($step === 4 && !$ok): ?>
  <h2>🔐 Mot de passe administrateur</h2>
  <p class="step-desc">Choisissez votre mot de passe pour accéder au panneau d'administration.</p>
  <form method="POST" action="?step=4">
    <div class="fgrp"><label>Mot de passe *</label><input type="password" name="password" required minlength="6" autofocus></div>
    <div class="fgrp"><label>Confirmer *</label><input type="password" name="confirm" required></div>
    <button type="submit" class="btn">Terminer l'installation ✓</button>
  </form>

  <!-- ======== SUCCÈS ======== -->
  <?php elseif($ok): ?>
  <div class="ok-box">
    <div class="check">🎉</div>
    <h2>Installation terminée !</h2>
    <p>
      Votre site Kik'r est prêt.<br><br>
      <strong>👉 Prochaines étapes dans l'admin :</strong><br>
      Uploader la photo de votre moto · Personnaliser le hero<br>
      Configurer vos services · Ajouter votre logo<br>
      Uploader votre fiche PDF d'intervention
    </p>
    <a href="../admin/">Accéder à l'admin →</a>
  </div>
  <?php endif; ?>
</div>

<script>
function previewLogo(input) {
  var prev = document.getElementById('logo-preview');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      prev.innerHTML = '<img src="'+e.target.result+'" style="max-height:60px;max-width:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
