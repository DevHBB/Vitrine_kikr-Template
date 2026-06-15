<?php
require_once __DIR__ . '/layout.php';
ensure_tables();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$prev_m=$month-1;$prev_y=$year; if($prev_m<1){$prev_m=12;$prev_y--;}
$next_m=$month+1;$next_y=$year; if($next_m>12){$next_m=1;$next_y++;}

// Actions rapides
$action = $_POST['action'] ?? '';
if ($action === 'update_status') {
    $rid        = (int)$_POST['id'];
    $new_status = $_POST['status'];
    $s2 = db()->prepare("SELECT * FROM kk_appointments WHERE id=?"); $s2->execute([$rid]);
    $rdv2 = $s2->fetch();
    $old_status = $rdv2['status'] ?? '';

    db()->prepare("UPDATE kk_appointments SET status=?,updated_at=NOW() WHERE id=?")->execute([$new_status, $rid]);

    $from = "From: " . get_setting('site_email');
    if ($rdv2 && ps('notif_email','1') === '1') {
        if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
            $date_fmt = $rdv2['slot_date'] ? date('d/m/Y', strtotime($rdv2['slot_date'])) : 'à définir';
            $time_fmt = $rdv2['slot_time'] ? ' à ' . substr($rdv2['slot_time'],0,5) : '';
            mail($rdv2['client_email'], "✅ RDV confirmé — Kik'r Suspension",
                "Bonjour {$rdv2['client_name']},\r\n\r\nVotre rendez-vous est CONFIRMÉ.\r\n\r\n📅 Date : $date_fmt$time_fmt\r\n🔧 Prestation : {$rdv2['service_label']}\r\n\r\nVous pouvez amener votre moto à la date indiquée.\r\n\r\nKik'r Suspension\r\n".get_setting('site_phone'), $from);
        }
        if ($new_status === 'cancelled' && $old_status !== 'cancelled') {
            mail($rdv2['client_email'], "❌ Demande de RDV — Kik'r Suspension",
                "Bonjour {$rdv2['client_name']},\r\n\r\nNous sommes désolés, nous ne pouvons pas donner suite à votre demande pour le moment. N'hésitez pas à nous recontacter.\r\n\r\nKik'r Suspension\r\n".get_setting('site_phone'), $from);
        }
        if ($new_status === 'ready' && $old_status !== 'ready') {
            mail($rdv2['client_email'], "🏍️ Votre moto est prête — Kik'r Suspension",
                "Bonjour {$rdv2['client_name']},\r\n\r\nVotre moto est prête à être récupérée !\r\nMerci de nous appeler avant de venir.\r\n\r\nKik'r Suspension\r\n".get_setting('site_phone'), $from);
        }
    }
    header('Location: '.BASE_URL.'/admin/planning.php?year='.$year.'&month='.$month.'#rdv-'.$rid); exit;
}
if ($action === 'delete_rdv') {
    db()->prepare("DELETE FROM kk_appointments WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: '.BASE_URL.'/admin/planning.php?year='.$year.'&month='.$month); exit;
}
if ($action === 'set_slot') {
    db()->prepare("UPDATE kk_appointments SET slot_date=?,slot_time=? WHERE id=?")
       ->execute([$_POST['slot_date'], $_POST['slot_time']?:null, (int)$_POST['id']]);
    header('Location: '.BASE_URL.'/admin/planning.php?year='.$year.'&month='.$month.'#rdv-'.(int)$_POST['id']); exit;
}

$slots_map = get_slots_for_month($year,$month);
$all_rdvs  = get_appointments('', '');
// Index par date
$rdvs_by_date = [];
foreach ($all_rdvs as $r) {
    if ($r['slot_date']) $rdvs_by_date[$r['slot_date']][] = $r;
}

$months_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

// Statuts
$statuses = [
    'pending'    => ['🟡','En attente',   '#fef9c3','#854d0e'],
    'confirmed'  => ['🔵','Confirmé',     '#dbeafe','#1d4ed8'],
    'in_progress'=> ['🟠','En cours',     '#ffedd5','#c2410c'],
    'ready'      => ['🟢','Prêt',         '#dcfce7','#15803d'],
    'collected'  => ['✅','Récupéré',     '#f0fdf4','#aaa'],
    'cancelled'  => ['❌','Annulé',       '#fef2f2','#dc2626'],
];

// RDV sans date (en attente de planification)
$pending_rdvs = array_filter($all_rdvs, fn($r) => !$r['slot_date'] && $r['status']==='pending');
?>
<div class="adm-topbar">
  <h1>Planning</h1>
  <div style="display:flex;gap:8px;">
    <a href="?add_rdv=1" class="btn btn-primary btn-sm">+ Créer un RDV</a>
    <a href="<?= BASE_URL ?>/planning.php" target="_blank" class="btn btn-secondary btn-sm">🌐 Page publique</a>
    <a href="<?= BASE_URL ?>/admin/planning_settings.php" class="btn btn-ghost btn-sm">⚙️ Paramètres</a>
  </div>
</div>
<div class="adm-content">
<?php if(isset($_GET['add_rdv']) || isset($_GET['rdv_saved'])): ?>
<?php if(isset($_GET['rdv_saved'])): ?><div class="alert alert-ok">✅ RDV créé.</div><?php endif; ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-head"><h2>📋 Créer un RDV manuellement</h2></div>
  <form method="POST" action="<?= BASE_URL ?>/admin/planning_manual_rdv.php">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
      <div class="fgrp"><label>Nom client *</label><input type="text" name="client_name" required list="cl-list"><datalist id="cl-list"><?php foreach(get_clients() as $cl): ?><option value="<?= h($cl['name']) ?>" data-email="<?= h($cl['email']) ?>" data-phone="<?= h($cl['phone']) ?>"><?php endforeach; ?></datalist></div>
      <div class="fgrp"><label>Email</label><input type="email" name="client_email" id="manual-email"></div>
      <div class="fgrp"><label>Téléphone</label><input type="tel" name="client_phone" id="manual-phone"></div>
      <div class="fgrp"><label>Type</label><select name="client_type"><option value="particulier">Particulier</option><option value="pro">🏆 Pilote sponsorisé</option></select></div>
      <div class="fgrp"><label>Prestation</label>
        <select name="service_label"><?php foreach(get_services() as $s): ?><option value="<?= h($s['label']) ?>"><?= h($s['title']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="fgrp"><label>Statut</label>
        <select name="status"><option value="confirmed">🔵 Confirmé</option><option value="pending">🟡 En attente</option></select>
      </div>
      <div class="fgrp"><label>Moto</label><input type="text" name="moto_marque" placeholder="KTM, Yamaha…"></div>
      <div class="fgrp"><label>Modèle</label><input type="text" name="moto_modele" placeholder="YZ450F"></div>
      <div class="fgrp"><label>Date de dépôt *</label><input type="date" name="slot_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="fgrp"><label>Heure</label>
        <select name="slot_time"><?php for($h=8;$h<=17;$h++): ?><option value="<?= sprintf('%02d:00',$h) ?>"><?= sprintf('%02d:00',$h) ?></option><?php endfor; ?></select>
      </div>
      <div class="fgrp"><label>Estimation prix (€)</label><input type="number" name="price_estimate" step="0.01" min="0" placeholder="170.00"></div>
      <div class="fgrp"><label>Notes</label><input type="text" name="notes_admin" placeholder="Informations internes…"></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button type="submit" class="btn btn-primary">💾 Créer le RDV</button>
      <a href="<?= BASE_URL ?>/admin/planning.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Demandes en attente de créneau -->
<?php if(!empty($pending_rdvs)): ?>
<div class="card" style="border:2px solid #fbbf24;margin-bottom:16px;">
  <div class="card-head"><h2><span class="icon">🟡</span> <?= count($pending_rdvs) ?> demande(s) sans créneau</h2></div>
  <?php foreach($pending_rdvs as $rdv): ?>
  <div class="item-row" id="rdv-<?= $rdv['id'] ?>" style="border-left:3px solid #ed0c0f;">
    <?php if($rdv['priority']): ?><span title="Pilote sponsorisé" style="color:#ed0c0f;font-size:14px;">⭐</span><?php endif; ?>
    <div class="item-row-name"><?= h($rdv['client_name']) ?> — <?= h($rdv['service_label']) ?></div>
    <div class="item-row-sub"><?= h($rdv['moto_marque'].' '.$rdv['moto_modele']) ?></div>
    <div class="item-row-sub"><?= h($rdv['client_phone']) ?></div>
    <div class="item-row-actions">
      <!-- Assigner un créneau -->
      <form method="POST" style="display:flex;gap:6px;align-items:center;">
        <input type="hidden" name="action" value="set_slot">
        <input type="hidden" name="id" value="<?= $rdv['id'] ?>">
        <input type="date" name="slot_date" style="border:1px solid var(--border);border-radius:6px;padding:4px 8px;font-size:11px;">
        <select name="slot_time" style="border:1px solid var(--border);border-radius:6px;padding:4px;font-size:11px;">
          <option value="">Heure</option>
          <?php for($h=8;$h<=17;$h++): ?>
          <option value="<?= sprintf('%02d:00',$h) ?>"><?= sprintf('%02d:00',$h) ?></option>
          <option value="<?= sprintf('%02d:30',$h) ?>"><?= sprintf('%02d:30',$h) ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Placer</button>
      </form>
      <a href="<?= BASE_URL ?>/admin/planning_rdv.php?id=<?= $rdv['id'] ?>" class="btn btn-secondary btn-sm">👁 Détail</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Calendrier -->
<div class="card">
  <div class="card-head">
    <h2><span class="icon">📅</span> <?= $months_fr[$month] ?> <?= $year ?></h2>
    <div style="display:flex;gap:6px;">
      <a href="?year=<?= $prev_y ?>&month=<?= $prev_m ?>" class="btn btn-ghost btn-sm">‹ Préc.</a>
      <a href="?year=<?= date('Y') ?>&month=<?= date('m') ?>" class="btn btn-secondary btn-sm">Aujourd'hui</a>
      <a href="?year=<?= $next_y ?>&month=<?= $next_m ?>" class="btn btn-ghost btn-sm">Suiv. ›</a>
    </div>
  </div>

  <?php
  $first_day = (int)date('N', mktime(0,0,0,$month,1,$year));
  $nb_days   = (int)date('t', mktime(0,0,0,$month,1,$year));
  $days_open = array_map('intval', explode(',', ps('days_open','1,2,3,4,5')));
  $max_day   = (int)ps('max_per_day','3');
  $day_names = ['','Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
  ?>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:4px;">
    <?php foreach($day_names as $k=>$dn): if(!$k) continue; ?>
    <div style="text-align:center;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;padding:6px 0;"><?= $dn ?></div>
    <?php endforeach; ?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">
    <?php
    for($i=1;$i<$first_day;$i++) echo '<div></div>';
    for($d=1;$d<=$nb_days;$d++):
      $ds = sprintf('%04d-%02d-%02d',$year,$month,$d);
      $dow = (int)date('N',strtotime($ds));
      $is_open = in_array($dow,$days_open);
      $rdvs_d = $rdvs_by_date[$ds] ?? [];
      $nb_rdvs = count($rdvs_d);
      $is_today = $ds === date('Y-m-d');
      $is_past  = $ds < date('Y-m-d');
      if (!$is_open) { $bg='#f5f5f3'; $fg='#ccc'; }
      elseif ($nb_rdvs >= $max_day) { $bg='#fee2e2'; $fg='#dc2626'; }
      elseif ($nb_rdvs > 0)         { $bg='#fef9c3'; $fg='#854d0e'; }
      else                           { $bg='#f0fdf4'; $fg='#15803d'; }
    ?>
    <div style="background:<?= $bg ?>;border-radius:8px;padding:8px 4px;min-height:64px;position:relative;
      <?= $is_today?'box-shadow:0 0 0 2px #ed0c0f;':'' ?>
      <?= $is_past?'opacity:.6;':'' ?>">
      <div style="font-size:12px;font-weight:700;color:<?= $fg ?>;text-align:center;margin-bottom:4px;"><?= $d ?></div>
      <?php foreach(array_slice($rdvs_d,0,3) as $r):
        $st = $statuses[$r['status']] ?? ['?','','',' #999'];
      ?>
      <a href="<?= BASE_URL ?>/admin/planning_rdv.php?id=<?= $r['id'] ?>" style="display:block;font-size:9px;font-weight:600;color:#333;background:white;border-radius:3px;padding:2px 4px;margin-bottom:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;text-decoration:none;border-left:3px solid <?= $r['priority']?'#ed0c0f':'#ddd' ?>;" title="<?= h($r['client_name']) ?>">
        <?= $st[0] ?> <?= h(mb_substr($r['client_name'],0,12)) ?>
      </a>
      <?php endforeach; ?>
      <?php if(count($rdvs_d)>3): ?><div style="font-size:9px;color:#aaa;text-align:center;">+<?= count($rdvs_d)-3 ?></div><?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>

  <!-- Légende -->
  <div style="display:flex;gap:16px;margin-top:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;"><span style="width:12px;height:12px;background:#f0fdf4;border-radius:3px;display:inline-block;"></span>Libre</div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;"><span style="width:12px;height:12px;background:#fef9c3;border-radius:3px;display:inline-block;"></span>Partiel</div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;"><span style="width:12px;height:12px;background:#fee2e2;border-radius:3px;display:inline-block;"></span>Complet</div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;"><span style="width:12px;height:12px;background:#f5f5f3;border-radius:3px;display:inline-block;"></span>Fermé</div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;"><span style="width:3px;height:14px;background:#ed0c0f;border-radius:2px;display:inline-block;"></span>Pilote sponsorisé</div>
  </div>
</div>

<!-- Liste des RDV du mois -->
<?php
$month_rdvs = array_filter($all_rdvs, fn($r) =>
    $r['slot_date'] && substr($r['slot_date'],0,7) === sprintf('%04d-%02d',$year,$month)
);
?>
<?php if(!empty($month_rdvs)): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-head"><h2><span class="icon">📋</span> RDV de <?= $months_fr[$month] ?> (<?= count($month_rdvs) ?>)</h2></div>
  <?php foreach($month_rdvs as $rdv):
    $st = $statuses[$rdv['status']] ?? ['?','','',' #999'];
  ?>
  <div class="item-row" id="rdv-<?= $rdv['id'] ?>" style="border-left:3px solid <?= $rdv['priority']?'#ed0c0f':'transparent' ?>;">
    <div style="font-size:12px;font-weight:700;color:#555;width:80px;flex-shrink:0;">
      <?= date('d/m', strtotime($rdv['slot_date'])) ?>
      <?php if($rdv['slot_time']): ?><br><span style="font-weight:400;"><?= substr($rdv['slot_time'],0,5) ?></span><?php endif; ?>
    </div>
    <?php if($rdv['priority']): ?><span title="PRO" style="color:#ed0c0f;">⭐</span><?php endif; ?>
    <div class="item-row-name"><?= h($rdv['client_name']) ?></div>
    <div class="item-row-sub"><?= h($rdv['service_label']) ?></div>
    <div class="item-row-sub" style="display:flex;align-items:center;gap:4px;">
      <span style="background:<?= $st[2] ?>;color:<?= $st[3] ?>;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?= $st[1] ?></span>
    </div>
    <div class="item-row-actions">
      <!-- Changer statut rapide -->
      <form method="POST" style="display:flex;gap:4px;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $rdv['id'] ?>">
        <select name="status" style="border:1px solid var(--border);border-radius:6px;padding:4px 6px;font-size:11px;" onchange="this.form.submit()">
          <?php foreach($statuses as $k=>[$ico,$lbl]): ?>
          <option value="<?= $k ?>" <?= $rdv['status']===$k?'selected':'' ?>><?= $ico ?> <?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="<?= BASE_URL ?>/admin/planning_rdv.php?id=<?= $rdv['id'] ?>" class="btn btn-secondary btn-sm">👁</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce RDV ?')">
        <input type="hidden" name="action" value="delete_rdv">
        <input type="hidden" name="id" value="<?= $rdv['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
<?php // appended - handled inline above ?>
