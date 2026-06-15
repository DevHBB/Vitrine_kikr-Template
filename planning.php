<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/data.php';
ensure_tables();

$page_title  = 'Prendre rendez-vous';
$rdv_public  = ps('rdv_public', 'partial');
$rdv_mode    = ps('rdv_mode',   'request');
$days_open   = array_map('intval', explode(',', ps('days_open','1,2,3,4,5')));
$delay_days  = (int)ps('rdv_delay_days','1');
$min_date    = date('Y-m-d', strtotime("+{$delay_days} days"));
$max_per_day = (int)ps('max_per_day','3');
$pdf_url     = ps('pdf_fiche_url','');
$services    = get_services();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$selected_date = $_GET['date'] ?? '';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rdv_submit'])) {
    $name   = trim($_POST['name']   ?? '');
    $email  = trim($_POST['email']  ?? '');
    $phone  = trim($_POST['phone']  ?? '');
    $mmarq  = trim($_POST['moto_marque']   ?? '');
    $mmodl  = trim($_POST['moto_modele']   ?? '');
    $mannee = trim($_POST['moto_annee']    ?? '');
    $svc_lb = trim($_POST['service_label'] ?? '');
    $ctype  = ($_POST['client_type'] ?? '') === 'pro' ? 'pro' : 'particulier';
    $notes  = trim($_POST['notes_client']  ?? '');
    $slot_d = trim($_POST['slot_date']     ?? '');
    $slot_t = trim($_POST['slot_time']     ?? '');

    if (!$name)  $errors[] = 'Nom requis.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (!$phone) $errors[] = 'Téléphone requis.';
    if (!$mmarq) $errors[] = 'Marque de moto requise.';
    if (!$svc_lb) $errors[] = 'Prestation requise.';

    $fiche_url = '';
    if (!empty($_FILES['fiche_pdf']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['fiche_pdf']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $dir = __DIR__ . '/img/fiches';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = uniqid('fiche_') . '.pdf';
            if (move_uploaded_file($_FILES['fiche_pdf']['tmp_name'], $dir.'/'.$fname))
                $fiche_url = BASE_URL.'/img/fiches/'.$fname;
        }
    }

    if (!$errors) {
        $sc = db()->prepare("SELECT id FROM kk_clients WHERE email=? LIMIT 1");
        $sc->execute([$email]);
        $client_id = $sc->fetchColumn();
        if (!$client_id) {
            db()->prepare("INSERT INTO kk_clients(type,name,email,phone) VALUES(?,?,?,?)")
               ->execute([$ctype,$name,$email,$phone]);
            $client_id = (int)db()->lastInsertId();
        }
        $duree    = (stripos($svc_lb,'prépa')!==false||stripos($svc_lb,'prepa')!==false) ? 2 : 1;
        $priority = ($ctype==='pro' && ps('pro_priority','1')==='1') ? 1 : 0;
        db()->prepare("INSERT INTO kk_appointments
            (client_id,client_name,client_email,client_phone,client_type,
             moto_marque,moto_modele,moto_annee,service_label,
             slot_date,slot_time,duree_jours,notes_client,pdf_fiche,priority)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$client_id,$name,$email,$phone,$ctype,
               $mmarq,$mmodl,$mannee?:null,$svc_lb,
               $slot_d?:null,$slot_t?:null,$duree,$notes,$fiche_url,$priority]);

        $admin_email = get_setting('site_email');
        if ($admin_email && ps('notif_email','1')==='1') {
            $pref = $priority ? '[🏆 Sponsorisé] ' : '';
            mail($admin_email, $pref."Nouvelle demande RDV — $name",
                "Nom: $name\nEmail: $email\nTél: $phone\nMoto: $mmarq $mmodl\nPrestation: $svc_lb\n".($slot_d?"Date: $slot_d\n":"")."\n$notes",
                "From: $email\r\nReply-To: $email");
        }
        if (ps('notif_email','1')==='1') {
            $sname = get_setting('site_name');
            $body  = $rdv_mode==='request'
                ? "Bonjour $name,\r\n\r\nVotre demande est EN ATTENTE DE CONFIRMATION.\r\nNe déposez pas votre moto avant notre appel de confirmation.\r\n\r\n⚠️ Aucun créneau n'est réservé tant que vous n'avez pas reçu notre confirmation.\r\n\r\nKik'r Suspension\r\n".get_setting('site_phone')
                : "Bonjour $name,\r\n\r\nVotre demande de RDV a bien été reçue. Nous vous contacterons rapidement.\r\n\r\n$sname";
            mail($email, $rdv_mode==='request'?"Demande RDV reçue — En attente de confirmation":"Demande RDV reçue — $sname", $body, "From: ".get_setting('site_email'));
        }
        $success = true;
    } else {
        $selected_date = $slot_d;
    }
}

require_once __DIR__ . '/layout/header.php';
$months_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$prev_m=$month-1;$prev_y=$year; if($prev_m<1){$prev_m=12;$prev_y--;}
$next_m=$month+1;$next_y=$year; if($next_m>12){$next_m=1;$next_y++;}
?>

<style>
.rdv-hero{background:#111;color:white;padding:48px 0 40px;text-align:center}
.rdv-hero h1{font-size:clamp(28px,4vw,48px);font-weight:900;letter-spacing:-2px;margin-bottom:8px}
.rdv-hero p{color:#888;font-size:14px}
/* Layout principal : calendrier | formulaire */
.rdv-main{max-width:1100px;margin:0 auto;padding:40px 20px;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start}
/* Calendrier */
.rdv-cal{background:white;border-radius:20px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.07)}
.rdv-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.rdv-cal-nav a{width:34px;height:34px;background:#f5f5f3;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#111;font-size:18px;transition:all .2s}
.rdv-cal-nav a:hover{background:#111;color:white}
.rdv-cal-title{font-size:16px;font-weight:800}
.rdv-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.rdv-cal-dow{text-align:center;font-size:9px;font-weight:700;color:#bbb;text-transform:uppercase;padding:5px 0}
.rdv-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:8px;font-size:13px;font-weight:600;border:2px solid transparent;transition:all .2s}
.rdv-day.free{background:#f0fdf4;color:#15803d;cursor:pointer}
.rdv-day.free:hover,.rdv-day.partial:hover{transform:scale(1.1);border-color:currentColor}
.rdv-day.partial{background:#fef9c3;color:#854d0e;cursor:pointer}
.rdv-day.full{background:#fee2e2;color:#dc2626}
.rdv-day.closed{background:#f9f9f9;color:#ddd}
.rdv-day.past{opacity:.3;cursor:not-allowed}
.rdv-day.today{box-shadow:0 0 0 2px #111}
.rdv-day.selected{background:#ed0c0f!important;color:white!important;border-color:#ed0c0f!important;transform:scale(1.08)}
.rdv-legend{display:flex;gap:12px;margin-top:14px;flex-wrap:wrap}
.rdv-legend-item{display:flex;align-items:center;gap:5px;font-size:11px;color:#666}
.rdv-legend-dot{width:10px;height:10px;border-radius:3px}
/* PDF */
.rdv-pdf{display:flex;align-items:center;gap:12px;background:#111;border-radius:12px;padding:14px 16px;margin-top:14px;text-decoration:none;transition:background .2s}
.rdv-pdf:hover{background:#222}
/* Formulaire */
.rdv-form-card{background:white;border-radius:20px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.07);position:sticky;top:20px}
.rdv-form-card h2{font-size:18px;font-weight:800;margin-bottom:4px}
.rdv-date-badge{display:inline-flex;align-items:center;gap:6px;background:#fef2f2;color:#ed0c0f;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:700;margin:8px 0 16px}
.rdv-type{display:flex;gap:8px;margin-bottom:16px}
.rdv-type-btn{flex:1;padding:10px;border:2px solid #e8e8e8;border-radius:10px;text-align:center;cursor:pointer;font-size:12px;font-weight:700;color:#666;transition:all .2s}
.rdv-type-btn.active{border-color:#ed0c0f;background:#fef2f2;color:#ed0c0f}
.rdv-f{margin-bottom:12px}
.rdv-f label{display:block;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.rdv-f input,.rdv-f select,.rdv-f textarea{width:100%;border:1.5px solid #e8e8e8;border-radius:9px;padding:9px 12px;font-size:13px;font-family:inherit;outline:none;transition:border-color .2s;background:#fafafa}
.rdv-f input:focus,.rdv-f select:focus,.rdv-f textarea:focus{border-color:#ed0c0f;background:white}
.rdv-f textarea{resize:vertical;min-height:70px}
.rdv-f-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.rdv-submit{width:100%;background:#ed0c0f;color:white;border:none;border-radius:12px;padding:13px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 4px 16px rgba(237,12,15,.3);transition:all .2s;margin-top:6px}
.rdv-submit:hover{background:#c00b0d;transform:translateY(-1px)}
.rdv-success{text-align:center;padding:30px 0}
.rdv-errors{background:#fef2f2;border-radius:10px;padding:12px 14px;font-size:13px;color:#dc2626;margin-bottom:14px}
/* Catalogue prix - en bas */
.rdv-catalog{max-width:1100px;margin:0 auto 48px;padding:0 20px}
.rdv-catalog h2{font-size:22px;font-weight:900;letter-spacing:-1px;margin-bottom:20px}
.rdv-catalog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}
.rdv-price-card{background:white;border-radius:14px;padding:16px 18px;border:1.5px solid #f0f0f0;transition:box-shadow .2s}
.rdv-price-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08)}
.rdv-price-card.highlight{border-color:#fecaca;background:#fef9f9}
.rdv-price-label{font-size:13px;font-weight:700;margin-bottom:3px}
.rdv-price-desc{font-size:11px;color:#888;margin-bottom:8px}
.rdv-price-val{font-size:16px;font-weight:900;color:#ed0c0f}
@media(max-width:768px){
  .rdv-main{grid-template-columns:1fr;gap:20px}
  .rdv-form-card{position:static}
  .rdv-f-row{grid-template-columns:1fr}
}
</style>

<!-- HERO -->
<div class="rdv-hero">
  <div style="max-width:600px;margin:0 auto;padding:0 20px;">
    <h1>Prendre rendez-vous</h1>
    <p>Sélectionnez un jour disponible puis remplissez le formulaire</p>
  </div>
</div>

<!-- GRID : CALENDRIER | FORMULAIRE -->
<div class="rdv-main">

  <!-- COLONNE 1 : Calendrier -->
  <div>
    <div class="rdv-cal">
      <div class="rdv-cal-nav">
        <a href="?year=<?= $prev_y ?>&month=<?= $prev_m ?><?= $selected_date?"&date=$selected_date":'' ?>">‹</a>
        <div class="rdv-cal-title"><?= $months_fr[$month] ?> <?= $year ?></div>
        <a href="?year=<?= $next_y ?>&month=<?= $next_m ?><?= $selected_date?"&date=$selected_date":'' ?>">›</a>
      </div>
      <div class="rdv-cal-grid" style="margin-bottom:6px;">
        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?><div class="rdv-cal-dow"><?= $d ?></div><?php endforeach; ?>
      </div>
      <div class="rdv-cal-grid">
        <?php
        $first_dow = (int)date('N', mktime(0,0,0,$month,1,$year));
        $nb_days   = (int)date('t',  mktime(0,0,0,$month,1,$year));
        for($i=1;$i<$first_dow;$i++) echo '<div></div>';
        for($d=1;$d<=$nb_days;$d++):
          $ds      = sprintf('%04d-%02d-%02d',$year,$month,$d);
          $dow     = (int)date('N',strtotime($ds));
          $is_open = in_array($dow,$days_open);
          $is_past = $ds < $min_date;
          $is_today= $ds === date('Y-m-d');
          $booked  = count_booked($ds);
          if (!$is_open)              $status = 'closed';
          elseif ($is_past)           $status = 'past';
          elseif ($booked>=$max_per_day) $status = 'full';
          elseif ($booked>0)          $status = 'partial';
          else                        $status = 'free';
          $is_sel  = $ds === $selected_date;
          $cls = $status.($is_today?' today':'').($is_sel?' selected':'');
          $clickable = in_array($status,['free','partial']);
        ?>
        <div class="rdv-day <?= $cls ?>"
          <?php if($clickable): ?>onclick="selectDay('<?= $ds ?>','<?= $d ?> <?= $months_fr[$month] ?>')"<?php endif; ?>
          title="<?= $d.' '.$months_fr[$month] ?>">
          <span><?= $d ?></span>
        </div>
        <?php endfor; ?>
      </div>
      <div class="rdv-legend">
        <div class="rdv-legend-item"><div class="rdv-legend-dot" style="background:#dcfce7;"></div>Disponible</div>
        <div class="rdv-legend-item"><div class="rdv-legend-dot" style="background:#fef9c3;"></div>Quelques places</div>
        <div class="rdv-legend-item"><div class="rdv-legend-dot" style="background:#fee2e2;"></div>Complet</div>
      </div>
    </div>
    <?php if($pdf_url): ?>
    <a href="<?= h($pdf_url) ?>" download class="rdv-pdf">
      <svg width="24" height="24" fill="none" stroke="#ed0c0f" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <div><div style="font-size:13px;font-weight:700;color:white;">Fiche d'intervention</div><div style="font-size:11px;color:#666;">Télécharger, remplir et joindre</div></div>
      <span style="margin-left:auto;background:#ed0c0f;color:white;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;">⬇️ PDF</span>
    </a>
    <?php endif; ?>
  </div>

  <!-- COLONNE 2 : Formulaire -->
  <div class="rdv-form-card" id="rdv-form-wrap">
    <?php if($success): ?>
    <div class="rdv-success">
      <?php if($rdv_mode==='request'): ?>
      <div style="font-size:52px;margin-bottom:12px;">📬</div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:8px;">Demande reçue !</h2>
      <p style="color:#555;font-size:13px;line-height:1.7;">Votre demande est <strong>en attente de confirmation</strong>.<br>Ne déposez pas votre moto avant notre appel.<br><br><span style="color:#ed0c0f;font-weight:700;">⚠️ Aucun créneau réservé sans confirmation.</span></p>
      <?php else: ?>
      <div style="font-size:52px;margin-bottom:12px;">✅</div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:8px;">Demande envoyée !</h2>
      <p style="color:#555;font-size:13px;">Nous vous contacterons rapidement pour confirmer.</p>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/planning.php" style="display:inline-block;margin-top:16px;background:#111;color:white;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">Retour →</a>
    </div>
    <?php else: ?>

    <h2>Votre demande</h2>
    <p style="font-size:12px;color:#aaa;margin-bottom:10px;">← Cliquez un jour disponible pour choisir votre date</p>
    <div id="date-badge" style="<?= $selected_date?'':'display:none;' ?>">
      <div class="rdv-date-badge">📅 <span id="date-badge-txt"><?= $selected_date ? date('d/m/Y',strtotime($selected_date)) : '' ?></span></div>
    </div>
    <?php if(!empty($errors)): ?><div class="rdv-errors"><?= implode('<br>',array_map('h',$errors)) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="rdv-form">
      <input type="hidden" name="rdv_submit" value="1">
      <input type="hidden" name="slot_date" id="slot-date-inp" value="<?= h($selected_date) ?>">

      <div class="rdv-type">
        <div class="rdv-type-btn active" id="btn-part" onclick="setType('particulier')">👤 Particulier</div>
        <div class="rdv-type-btn" id="btn-spon" onclick="setType('pro')">🏆 Pilote sponsorisé</div>
      </div>
      <input type="hidden" name="client_type" id="client-type" value="particulier">

      <div class="rdv-f-row">
        <div class="rdv-f"><label>Nom *</label><input type="text" name="name" value="<?= h($_POST['name']??'') ?>" required placeholder="Jean Dupont"></div>
        <div class="rdv-f"><label>Téléphone *</label><input type="tel" name="phone" value="<?= h($_POST['phone']??'') ?>" required placeholder="+33 6 …"></div>
      </div>
      <div class="rdv-f"><label>Email *</label><input type="email" name="email" value="<?= h($_POST['email']??'') ?>" required></div>
      <div class="rdv-f"><label>Prestation *</label>
        <select name="service_label" required>
          <option value="">— Choisir —</option>
          <?php foreach($services as $s): ?><option value="<?= h($s['label']) ?>" <?= ($_POST['service_label']??'')===$s['label']?'selected':'' ?>><?= h($s['title']) ?></option><?php endforeach; ?>
          <option value="Autre">Autre / Renseignement</option>
        </select>
      </div>
      <div class="rdv-f-row">
        <div class="rdv-f"><label>Marque moto *</label><input type="text" name="moto_marque" value="<?= h($_POST['moto_marque']??'') ?>" required placeholder="Yamaha…"></div>
        <div class="rdv-f"><label>Modèle</label><input type="text" name="moto_modele" value="<?= h($_POST['moto_modele']??'') ?>" placeholder="YZ450F"></div>
      </div>
      <div class="rdv-f-row">
        <div class="rdv-f"><label>Année</label><input type="number" name="moto_annee" value="<?= h($_POST['moto_annee']??'') ?>" placeholder="2022" min="1990" max="<?= date('Y')+1 ?>"></div>
        <div class="rdv-f"><label>Heure souhaitée</label>
          <select name="slot_time">
            <option value="">— Au choix —</option>
            <?php for($h=8;$h<=16;$h++): ?>
            <option value="<?= sprintf('%02d:00',$h) ?>"><?= sprintf('%02d:00',$h) ?></option>
            <option value="<?= sprintf('%02d:30',$h) ?>"><?= sprintf('%02d:30',$h) ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="rdv-f"><label>Message / Précisions</label>
        <textarea name="notes_client" placeholder="Poids pilote, discipline, terrain, problème constaté…"><?= h($_POST['notes_client']??'') ?></textarea>
      </div>
      <div class="rdv-f"><label>Fiche PDF (optionnel)</label>
        <input type="file" name="fiche_pdf" accept=".pdf" style="background:white;">
      </div>
      <button type="submit" class="rdv-submit">Envoyer ma demande →</button>
      <p style="font-size:11px;color:#aaa;text-align:center;margin-top:8px;">Nous vous contacterons sous 24h pour confirmer.</p>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- CATALOGUE DES PRIX EN BAS -->
<?php
$catalog_items = get_price_catalog();
$cat_by_svc = [];
foreach($catalog_items as $ci) { $key=$ci['service_label']??'Tarifs'; $cat_by_svc[$key][]=$ci; }
if(!empty($cat_by_svc)): ?>
<div class="rdv-catalog">
  <h2>💰 Nos tarifs indicatifs</h2>
  <?php foreach($cat_by_svc as $svc_lbl=>$items): ?>
  <div style="margin-bottom:24px;">
    <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><?= h($svc_lbl) ?></div>
    <div class="rdv-catalog-grid">
      <?php foreach($items as $ci): ?>
      <div class="rdv-price-card <?= $ci['highlight']?'highlight':'' ?>">
        <div class="rdv-price-label"><?= $ci['highlight']?'⭐ ':'' ?><?= h($ci['label']) ?></div>
        <div class="rdv-price-desc"><?= h($ci['description']??'') ?></div>
        <div class="rdv-price-val"><?= format_price($ci) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <p style="font-size:11px;color:#aaa;">* Tarifs indicatifs HT. Prix définitif établi après diagnostic. Pièces d'usure non incluses.</p>
</div>
<?php endif; ?>

<script>
function selectDay(date, label) {
  document.getElementById('slot-date-inp').value = date;
  document.getElementById('date-badge').style.display = 'block';
  document.getElementById('date-badge-txt').textContent = label;
  document.querySelectorAll('.rdv-day.selected').forEach(e => e.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  if(window.innerWidth < 768) document.getElementById('rdv-form-wrap').scrollIntoView({behavior:'smooth',block:'start'});
  var fw = document.getElementById('rdv-form-wrap');
  fw.style.transition='transform .3s'; fw.style.transform='scale(1.01)';
  setTimeout(()=>{ fw.style.transform='scale(1)'; }, 200);
}
function setType(t) {
  document.getElementById('client-type').value = t;
  document.getElementById('btn-part').className='rdv-type-btn'+(t==='particulier'?' active':'');
  document.getElementById('btn-spon').className='rdv-type-btn'+(t==='pro'?' active':'');
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
