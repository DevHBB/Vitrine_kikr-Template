<?php
// lib/data.php — Toutes les fonctions de lecture DB

function get_hero(): array {
    return db()->query('SELECT * FROM kk_hero WHERE id=1')->fetch() ?: [];
}
function get_hero_boxes(): array {
    return db()->query('SELECT * FROM kk_hero_boxes ORDER BY position')->fetchAll();
}
function get_specs(): array {
    return db()->query('SELECT * FROM kk_specs ORDER BY position')->fetchAll();
}
function get_nav(): array {
    return db()->query('SELECT * FROM kk_nav ORDER BY position')->fetchAll();
}
function get_about(): array {
    $r = db()->query('SELECT * FROM kk_about WHERE id=1')->fetch();
    if ($r) $r['paragraphs'] = jd($r['paragraphs'] ?? '[]', []);
    return $r ?: [];
}
function get_services(): array {
    return db()->query('SELECT * FROM kk_services WHERE active=1 ORDER BY position')->fetchAll();
}
function get_partner_groups(): array {
    $groups = db()->query('SELECT * FROM kk_partner_groups ORDER BY position')->fetchAll();
    foreach ($groups as &$g) {
        $s = db()->prepare('SELECT * FROM kk_partners WHERE group_id=? ORDER BY position');
        $s->execute([$g['id']]);
        $g['items'] = $s->fetchAll();
    }
    return $groups;
}
function get_pilots(): array {
    return db()->query('SELECT * FROM kk_pilots ORDER BY position')->fetchAll();
}
function get_contact(): array {
    $r = db()->query('SELECT * FROM kk_contact WHERE id=1')->fetch();
    if ($r) $r['hours'] = jd($r['hours'] ?? '[]', []);
    return $r ?: [];
}
function get_pages(bool $published_only = true): array {
    $sql = 'SELECT * FROM kk_pages';
    if ($published_only) $sql .= " WHERE status='published'";
    $sql .= ' ORDER BY updated_at DESC';
    return db()->query($sql)->fetchAll();
}
function get_page_by_slug(string $slug): ?array {
    $s = db()->prepare("SELECT * FROM kk_pages WHERE slug=?");
    $s->execute([$slug]);
    $r = $s->fetch();
    if ($r) $r['blocks'] = jd($r['blocks'] ?? '[]', []);
    return $r ?: null;
}

function get_home_blocks(): array {
    try {
        return db()->query('SELECT * FROM kk_home_blocks WHERE active=1 ORDER BY position')->fetchAll();
    } catch (Exception $e) { return []; }
}

// ---- Planning ----
function ps(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $s = db()->prepare('SELECT val FROM kk_planning_settings WHERE `key`=?');
            $s->execute([$key]);
            $cache[$key] = ($v = $s->fetchColumn()) !== false ? $v : $default;
        } catch (Exception $e) { $cache[$key] = $default; }
    }
    return $cache[$key];
}
function set_ps(string $key, string $val): void {
    db()->prepare('INSERT INTO kk_planning_settings(`key`,val) VALUES(?,?) ON DUPLICATE KEY UPDATE val=?')
       ->execute([$key,$val,$val]);
}
function get_slots_for_month(int $year, int $month): array {
    $s = db()->prepare('SELECT * FROM kk_slots WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date,time');
    $s->execute([$year,$month]);
    $rows = $s->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[$r['date']][] = $r;
    return $map;
}
function get_appointments(string $status = '', string $date = ''): array {
    $sql = 'SELECT * FROM kk_appointments WHERE 1=1';
    $p = [];
    if ($status) { $sql .= ' AND status=?'; $p[] = $status; }
    if ($date)   { $sql .= ' AND slot_date=?'; $p[] = $date; }
    $sql .= ' ORDER BY priority DESC, slot_date ASC, slot_time ASC, created_at ASC';
    $s = db()->prepare($sql); $s->execute($p);
    return $s->fetchAll();
}
function count_booked(string $date): int {
    $s = db()->prepare("SELECT COUNT(*) FROM kk_appointments WHERE slot_date=? AND status NOT IN('cancelled')");
    $s->execute([$date]);
    return (int)$s->fetchColumn();
}
function day_status(string $date): string {
    // 'closed','full','partial','free'
    $dow = (int)date('N', strtotime($date)); // 1=Mon 7=Sun
    $days_open = array_map('intval', explode(',', ps('days_open','1,2,3,4,5')));
    if (!in_array($dow, $days_open)) return 'closed';
    $booked = count_booked($date);
    $max    = (int)ps('max_per_day','3');
    if ($booked === 0)    return 'free';
    if ($booked >= $max)  return 'full';
    return 'partial';
}

// ============================================================
// MODULES TOGGLES
// ============================================================
function module_on(string $module): bool {
    return get_setting('module_'.$module, '1') === '1';
}
function module_redirect(string $module): void {
    if (!module_on($module)) {
        header('Location: '.BASE_URL.'/'); exit;
    }
}

// ============================================================
// CLIENTS
// ============================================================
function get_client(int $id): ?array {
    $s = db()->prepare('SELECT * FROM kk_clients WHERE id=?');
    $s->execute([$id]); return $s->fetch() ?: null;
}
function get_clients(string $search='', string $type=''): array {
    $sql='SELECT * FROM kk_clients WHERE 1=1'; $p=[];
    if($search){$sql.=' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $q="%$search%"; $p=[$q,$q,$q];}
    if($type){$sql.=' AND type=?'; $p[]=$type;}
    $sql.=' ORDER BY name ASC';
    $s=db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function upsert_client(array $data): int {
    $s=db()->prepare('SELECT id FROM kk_clients WHERE email=? LIMIT 1');
    $s->execute([$data['email']??'']);
    $existing=$s->fetchColumn();
    if($existing){
        db()->prepare('UPDATE kk_clients SET name=?,phone=?,type=?,notes=?,updated_at=NOW() WHERE id=?')
           ->execute([$data['name']??'',$data['phone']??'',$data['type']??'particulier',$data['notes']??'',$existing]);
        return (int)$existing;
    }
    db()->prepare('INSERT INTO kk_clients(type,name,email,phone,newsletter_opt,sms_opt) VALUES(?,?,?,?,?,?)')
       ->execute([$data['type']??'particulier',$data['name']??'',$data['email']??'',$data['phone']??'',1,1]);
    return (int)db()->lastInsertId();
}

// ============================================================
// FACTURATION
// ============================================================
function next_invoice_number(string $type='invoice'): string {
    $prefix=['invoice'=>'FA','quote'=>'DE','credit'=>'AV'];
    $pre=$prefix[$type]??'FA'; $year=date('Y');
    db()->prepare('INSERT INTO kk_invoice_counters(type,year,counter) VALUES(?,?,1)
        ON DUPLICATE KEY UPDATE counter=counter+1')->execute([$type,$year]);
    $n=(int)db()->prepare('SELECT counter FROM kk_invoice_counters WHERE type=? AND year=?')
        ->execute([$type,$year])||0;
    $s=db()->prepare('SELECT counter FROM kk_invoice_counters WHERE type=? AND year=?');
    $s->execute([$type,$year]); $n=(int)$s->fetchColumn();
    return $pre.'-'.$year.'-'.str_pad($n,4,'0',STR_PAD_LEFT);
}
function get_invoice(int $id): ?array {
    $s=db()->prepare('SELECT * FROM kk_invoices WHERE id=?');
    $s->execute([$id]); $r=$s->fetch();
    if($r) $r['invoice_lines']=jd($r['invoice_lines']??'[]',[]);
    return $r?:null;
}
function get_invoices(string $status='', int $client_id=0): array {
    $sql='SELECT i.*,c.name as client_name_full FROM kk_invoices i LEFT JOIN kk_clients c ON i.client_id=c.id WHERE 1=1'; $p=[];
    if($status){$sql.=' AND i.status=?';$p[]=$status;}
    if($client_id){$sql.=' AND i.client_id=?';$p[]=$client_id;}
    $sql.=' ORDER BY i.created_at DESC';
    $s=db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function invoice_totals(array $lines, float $tva_rate, float $discount=0, string $disc_type='amount'): array {
    $ht=0;
    foreach($lines as $l) $ht+=((float)($l['qty']??1))*((float)($l['unit_price']??0));
    if($disc_type==='percent') $discount=$ht*($discount/100);
    $ht_after=$ht-$discount;
    $tva=$ht_after*($tva_rate/100);
    return ['ht'=>$ht,'discount'=>$discount,'ht_after'=>$ht_after,'tva'=>$tva,'ttc'=>$ht_after+$tva,'tva_rate'=>$tva_rate];
}

// Lien de paiement
function create_payment_link(int $invoice_id, float $amount, int $expires_days=7): string {
    $token=bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO kk_payment_links(token,invoice_id,amount,expires_at) VALUES(?,?,?,?)')
       ->execute([$token,$invoice_id,$amount,date('Y-m-d H:i:s',strtotime("+{$expires_days} days"))]);
    return site_url('/pay.php?t='.$token);
}

// ============================================================
// NEWSLETTER
// ============================================================
function get_subscribers(string $segment='', string $status='active'): array {
    $sql='SELECT * FROM kk_newsletter_subscribers WHERE status=?'; $p=[$status];
    if($segment && $segment!=='all'){$sql.=' AND FIND_IN_SET(?,segment)';$p[]=$segment;}
    $sql.=' ORDER BY created_at DESC';
    $s=db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function subscribe(string $email, string $name='', string $phone=''): bool {
    try {
        $token=bin2hex(random_bytes(16));
        db()->prepare('INSERT INTO kk_newsletter_subscribers(email,name,phone,token,segment) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status="active",name=VALUES(name)')->execute([$email,$name,$phone,$token,'all,newsletter']);
        return true;
    } catch(Exception $e){ return false; }
}
function get_campaigns(): array {
    return db()->query('SELECT * FROM kk_campaigns ORDER BY created_at DESC')->fetchAll();
}
function get_mailing_templates(): array {
    return db()->query('SELECT * FROM kk_mailing_templates ORDER BY name')->fetchAll();
}

// ============================================================
// CATALOGUE DES PRIX
// ============================================================
function get_price_catalog(int $service_id = 0): array {
    $sql = 'SELECT pc.*, s.title as service_title, s.label as service_label
            FROM kk_price_catalog pc
            LEFT JOIN kk_services s ON pc.service_id = s.id
            WHERE pc.active = 1';
    $p = [];
    if ($service_id) { $sql .= ' AND pc.service_id = ?'; $p[] = $service_id; }
    $sql .= ' ORDER BY pc.service_id, pc.position';
    $s = db()->prepare($sql); $s->execute($p);
    return $s->fetchAll();
}

function format_price(array $item): string {
    if ($item['price_exact'] !== null) {
        return number_format((float)$item['price_exact'], 0, ',', ' ') . ' €' . ($item['unit'] ? ' / '.$item['unit'] : '');
    }
    if ($item['price_from'] !== null && $item['price_to'] !== null) {
        return 'De ' . number_format((float)$item['price_from'], 0, ',', ' ') . ' à ' . number_format((float)$item['price_to'], 0, ',', ' ') . ' €' . ($item['unit'] ? ' / '.$item['unit'] : '');
    }
    if ($item['price_from'] !== null) {
        return 'À partir de ' . number_format((float)$item['price_from'], 0, ',', ' ') . ' €' . ($item['unit'] ? ' / '.$item['unit'] : '');
    }
    return 'Sur devis';
}

// Lien de paiement direct sur un RDV
function create_rdv_payment_link(int $rdv_id, float $amount, int $expires_days = 30): string {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
    db()->prepare("UPDATE kk_appointments SET payment_link_token=?, payment_status='pending_payment', updated_at=NOW() WHERE id=?")
       ->execute([$token, $rdv_id]);
    // Stocker aussi dans kk_payment_links pour le suivi
    try {
        db()->prepare("INSERT INTO kk_payment_links(token,invoice_id,amount,expires_at) VALUES(?,NULL,?,?)")
           ->execute([$token, $amount, $expires]);
    } catch(Exception $e) {}
    return site_url('/payer.php?t=' . $token . '&a=' . number_format($amount, 2, '.', ''));
}

// ============================================================
// PAGES LÉGALES
// ============================================================
function get_legal(string $slug): ?array {
    static $cache = [];
    if (!isset($cache[$slug])) {
        try {
            $s = db()->prepare('SELECT * FROM kk_legal_pages WHERE slug=?');
            $s->execute([$slug]);
            $cache[$slug] = $s->fetch() ?: null;
        } catch(Exception $e) { return null; }
    }
    return $cache[$slug];
}
function get_all_legal(): array {
    try {
        return db()->query('SELECT * FROM kk_legal_pages ORDER BY id')->fetchAll();
    } catch(Exception $e) { return []; }
}
function save_legal(string $slug, string $title, string $content): void {
    db()->prepare('INSERT INTO kk_legal_pages(slug,title,content) VALUES(?,?,?)
        ON DUPLICATE KEY UPDATE title=VALUES(title),content=VALUES(content)')
       ->execute([$slug, $title, $content]);
}

// ============================================================
// SHOP
// ============================================================
function get_products(string $cat = '', bool $active_only = true): array {
    $sql = 'SELECT * FROM kk_products WHERE 1=1';
    $p   = [];
    if ($active_only) { $sql .= ' AND active=1'; }
    if ($cat)         { $sql .= ' AND category=?'; $p[] = $cat; }
    $sql .= ' ORDER BY featured DESC, position, name';
    $s = db()->prepare($sql); $s->execute($p);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) $r['images'] = jd($r['images'] ?? '[]', []);
    return $rows;
}

function get_product_by_slug(string $slug): ?array {
    $s = db()->prepare('SELECT * FROM kk_products WHERE slug=? AND active=1 LIMIT 1');
    $s->execute([$slug]);
    $r = $s->fetch();
    if ($r) $r['images'] = jd($r['images'] ?? '[]', []);
    return $r ?: null;
}

function get_shop_cats(): array {
    try {
        return db()->query("SELECT DISTINCT category FROM kk_products WHERE active=1 AND category!='' ORDER BY category")
                   ->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e) { return []; }
}

function next_order_number(): string {
    $y = date('Y');
    try {
        $n = (int)db()->query("SELECT COUNT(*)+1 FROM kk_orders WHERE YEAR(created_at)=$y")->fetchColumn();
    } catch(Exception $e) { $n = 1; }
    return 'CMD-'.$y.'-'.str_pad($n, 4, '0', STR_PAD_LEFT);
}

function facture_url(int $inv_id): string {
    return site_url('/facture.php?id=' . $inv_id);
}

function create_order_invoice(array $order): ?int {
    // Crée une facture liée à la commande
    $lines = [];
    foreach (jd($order['items'] ?? '[]', []) as $item) {
        // Les items du panier utilisent 'price', les lignes de facture 'unit_price'
        $item_price = (float)($item['unit_price'] ?? $item['price'] ?? 0);
        $lines[] = [
            'desc'       => $item['name'] ?? 'Produit',
            'qty'        => (float)($item['qty']  ?? 1),
            'unit_price' => round($item_price / 1.20, 2), // HT
            'tva'        => 20,
        ];
    }
    if ((float)$order['shipping'] > 0) {
        $lines[] = ['desc'=>'Frais de livraison','qty'=>1,'unit_price'=>round((float)$order['shipping']/1.20,2),'tva'=>20];
    }
    $num = next_invoice_number('invoice');
    db()->prepare("INSERT INTO kk_invoices(number,type,status,client_name,client_email,client_phone,client_address,invoice_lines,tva_rate) VALUES(?,?,?,?,?,?,?,?,20)")
       ->execute([$num,'invoice','sent',$order['client_name'],$order['client_email'],$order['client_phone'],$order['shipping_addr']??'',json_encode($lines,JSON_UNESCAPED_UNICODE)]);
    return (int)db()->lastInsertId();
}
