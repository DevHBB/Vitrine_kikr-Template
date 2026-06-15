<?php
// ============================================================
//  Kik'r — Config principale
// ============================================================

define('BASE_URL', '');  // '' si racine, '/kikr3' si sous-dossier
define('CFG_FILE',  __DIR__ . '/install/config.ini');
define('INSTALLED', file_exists(CFG_FILE));

// ---- Connexion DB ----
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!INSTALLED) die('Site non installé. <a href="'.BASE_URL.'/install/">Installer</a>');
    $c = parse_ini_file(CFG_FILE);
    try {
        $pdo = new PDO(
            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'], $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        die('Erreur DB : ' . htmlspecialchars($e->getMessage()));
    }
    return $pdo;
}

// ---- Auto-migrate missing tables ----
function seed_legal_pages(PDO $pdo): void {
    $sname = "Kik'r Suspension";
    $year  = date('Y');

    $pages = [

    // ---- MENTIONS LÉGALES ----
    ['mentions-legales', 'Mentions légales', "
<h2>Mentions légales</h2>

<h3>Éditeur du site</h3>
<p>
  <strong>{SITE_NAME}</strong><br>
  Entreprise individuelle / {FORME_JURIDIQUE}<br>
  SIRET : {SIRET}<br>
  Adresse : {ADRESSE}<br>
  Téléphone : {TELEPHONE}<br>
  Email : {EMAIL}
</p>

<h3>Directeur de la publication</h3>
<p>{PROPRIETAIRE}</p>

<h3>Hébergement</h3>
<p>Ce site est hébergé par :<br>
{HEBERGEUR}<br>
{ADRESSE_HEBERGEUR}</p>

<h3>Activité</h3>
<p>{SITE_NAME} est spécialisé dans la préparation, l'entretien et le traitement de suspensions pour motos (motocross, FMX, supermotard, enduro, vitesse).</p>

<h3>Propriété intellectuelle</h3>
<p>L'ensemble du contenu de ce site (textes, images, logos, vidéos) est protégé par le droit d'auteur. Toute reproduction sans autorisation préalable est interdite.</p>

<h3>Responsabilité</h3>
<p>{SITE_NAME} s'efforce d'assurer l'exactitude des informations diffusées sur ce site. Toutefois, les informations publiées sont non contractuelles et peuvent être modifiées à tout moment sans préavis.</p>

<h3>Liens hypertextes</h3>
<p>Ce site peut contenir des liens vers d'autres sites. {SITE_NAME} ne saurait être tenu responsable du contenu de ces sites externes.</p>

<h3>Contact</h3>
<p>Pour toute question relative au site : {EMAIL}</p>
    "],

    // ---- CGV ----
    ['cgv', 'Conditions Générales de Vente', "
<h2>Conditions Générales de Vente</h2>
<p><em>Applicables à compter du $year — Suspensions moto : entretien, préparation et traitement</em></p>

<h3>Article 1 — Objet</h3>
<p>Les présentes Conditions Générales de Vente (CGV) définissent les droits et obligations de {SITE_NAME} et de ses clients dans le cadre des prestations de service relatives aux suspensions de motos deux-roues (fourches, amortisseurs).</p>

<h3>Article 2 — Prestations proposées</h3>
<p>{SITE_NAME} propose les prestations suivantes :</p>
<ul>
  <li><strong>Entretien / Vidange :</strong> démontage, nettoyage, remplacement des éléments d'usure (joints, huile), remontage.</li>
  <li><strong>Préparation hydraulique :</strong> modification du setting interne (ressorts, cales, huile) selon le profil pilote (poids, niveau, discipline, terrain).</li>
  <li><strong>Traitement de surface :</strong> traitement anti-friction (DLC, TIN, Kashima, anodisation) des fourreaux et corps d'amortisseurs.</li>
</ul>

<h3>Article 3 — Tarifs</h3>
<p>Les tarifs sont ceux en vigueur au moment de la prise de rendez-vous, affichés sur le site à titre indicatif. Tout devis établi est valable 30 jours. Les prix sont indiqués en euros TTC. Les pièces d'usure et fournitures (joints, huile, cartouches) ne sont pas incluses dans les tarifs de main-d'œuvre et font l'objet d'une facturation séparée.</p>
<p><strong>Tarif dépose / repose :</strong> si le client demande la dépose et repose de la suspension sur la moto, un forfait supplémentaire s'applique.</p>

<h3>Article 4 — Commandes et prise de rendez-vous</h3>
<p>La prise de rendez-vous s'effectue via le formulaire en ligne, par téléphone ou par email. Toute demande n'est définitivement confirmée qu'après accord explicite de {SITE_NAME} (email ou appel de confirmation). Aucun dépôt de moto ne doit être effectué sans confirmation préalable.</p>

<h3>Article 5 — Dépôt de la moto</h3>
<p>Le client est tenu de déposer sa moto propre, avec le plein d'huile moteur fait. Une fiche d'intervention doit être remplie lors du dépôt (ou transmise en amont). La moto doit être en état de marche ou clairement décrite si elle présente des défauts existants. {SITE_NAME} ne saurait être tenu responsable des défauts préexistants non mentionnés.</p>

<h3>Article 6 — Délais d'intervention</h3>
<p>Les délais communiqués sont donnés à titre indicatif. En période de forte activité (compétitions, saison), les délais peuvent être allongés. {SITE_NAME} s'engage à prévenir le client de tout retard significatif. En aucun cas un retard ne peut donner lieu à indemnisation.</p>

<h3>Article 7 — Paiement</h3>
<p>Le paiement est exigible à la récupération de la moto, sauf accord préalable différent. Les modes de paiement acceptés sont : espèces, chèque, virement bancaire, carte bancaire (via le terminal en atelier ou lien de paiement en ligne). Tout impayé donne lieu à des pénalités de retard au taux légal en vigueur + 40€ d'indemnité forfaitaire de recouvrement.</p>

<h3>Article 8 — Récupération de la moto</h3>
<p>Le client est informé par email et/ou SMS lorsque sa moto est prête. La moto doit être récupérée dans les 7 jours ouvrés suivant cette notification. Au-delà, {SITE_NAME} se réserve le droit de facturer des frais de stockage de 5€/jour.</p>

<h3>Article 9 — Garantie</h3>
<p>Les interventions sont garanties 3 mois sur la main-d'œuvre, à compter de la date de restitution, hors dommages consécutifs à une utilisation anormale ou à un accident. Les pièces fournisseurs sont soumises à leur propre garantie constructeur. La garantie est nulle si la suspension a été démontée par un tiers après intervention.</p>

<h3>Article 10 — Responsabilité</h3>
<p>{SITE_NAME} souscrit une assurance responsabilité civile professionnelle. Sa responsabilité est limitée au montant de la prestation facturée. En aucun cas {SITE_NAME} ne saurait être tenu responsable des dommages indirects, pertes de gains ou préjudices de compétition.</p>

<h3>Article 11 — Litiges</h3>
<p>En cas de litige, une solution amiable sera recherchée en priorité. À défaut, le tribunal compétent est celui du siège de {SITE_NAME}. La loi applicable est la loi française. Conformément à l'article L.612-1 du Code de la consommation, le client peut recourir à la médiation de la consommation.</p>
    "],

    // ---- CGU ----
    ['cgu', "Conditions Générales d'Utilisation", "
<h2>Conditions Générales d'Utilisation</h2>
<p><em>Dernière mise à jour : $year</em></p>

<h3>Article 1 — Acceptation</h3>
<p>L'accès et l'utilisation du site {SITE_NAME} impliquent l'acceptation pleine et entière des présentes CGU. {SITE_NAME} se réserve le droit de les modifier à tout moment.</p>

<h3>Article 2 — Accès au site</h3>
<p>Le site est accessible 24h/24, 7j/7, sauf en cas de maintenance ou de force majeure. {SITE_NAME} ne garantit pas la continuité du service et ne saurait être tenu responsable d'une interruption.</p>

<h3>Article 3 — Formulaires en ligne</h3>
<p>Les informations transmises via les formulaires (prise de RDV, contact, newsletter) sont utilisées exclusivement dans le cadre de la relation commerciale avec {SITE_NAME}. Le client s'engage à fournir des informations exactes.</p>

<h3>Article 4 — Comportement des utilisateurs</h3>
<p>Il est interdit d'utiliser le site à des fins illicites, frauduleuses ou contraires à l'ordre public. {SITE_NAME} se réserve le droit de bloquer tout accès en cas d'abus.</p>

<h3>Article 5 — Propriété intellectuelle</h3>
<p>Tous les éléments du site (textes, images, logo, design) sont la propriété exclusive de {SITE_NAME} ou de leurs auteurs respectifs. Toute reproduction totale ou partielle est interdite sans autorisation écrite.</p>

<h3>Article 6 — Cookies</h3>
<p>Le site peut utiliser des cookies de fonctionnement (session) et de mesure d'audience. Aucun cookie publicitaire tiers n'est déposé sans consentement. Vous pouvez désactiver les cookies dans les paramètres de votre navigateur.</p>

<h3>Article 7 — Droit applicable</h3>
<p>Les présentes CGU sont soumises au droit français. Tout litige sera soumis aux tribunaux compétents du ressort de {SITE_NAME}.</p>
    "],

    // ---- POLITIQUE DE CONFIDENTIALITÉ ----
    ['confidentialite', 'Politique de confidentialité', "
<h2>Politique de confidentialité</h2>
<p><em>Dernière mise à jour : $year — Conforme au RGPD</em></p>

<h3>1. Responsable du traitement</h3>
<p><strong>{SITE_NAME}</strong> — {EMAIL}<br>
SIRET : {SIRET} — {ADRESSE}</p>

<h3>2. Données collectées</h3>
<p>Nous collectons les données suivantes :</p>
<ul>
  <li><strong>Prise de RDV :</strong> nom, email, téléphone, marque/modèle de moto</li>
  <li><strong>Facturation :</strong> nom, adresse, email, données de paiement (traitées par Stripe/PayPal)</li>
  <li><strong>Newsletter :</strong> email, nom (sur inscription volontaire)</li>
  <li><strong>Espace client :</strong> email (authentification sans mot de passe)</li>
</ul>

<h3>3. Finalités des traitements</h3>
<ul>
  <li>Gestion des rendez-vous et de la relation client</li>
  <li>Facturation et suivi des paiements</li>
  <li>Envoi de la newsletter (sur consentement)</li>
  <li>Notifications SMS (sur consentement)</li>
  <li>Amélioration du service</li>
</ul>

<h3>4. Base légale</h3>
<ul>
  <li>Exécution du contrat (RDV, facturation)</li>
  <li>Consentement (newsletter, SMS)</li>
  <li>Intérêt légitime (amélioration du service)</li>
</ul>

<h3>5. Durée de conservation</h3>
<ul>
  <li>Données clients : 5 ans après la dernière prestation (obligation comptable)</li>
  <li>Données newsletter : jusqu'au désabonnement</li>
  <li>Données de paiement : 13 mois (Stripe/PayPal) — nous ne stockons pas les données de carte</li>
</ul>

<h3>6. Partage des données</h3>
<p>Vos données ne sont jamais vendues. Elles peuvent être transmises à :</p>
<ul>
  <li>Stripe Inc. (paiement sécurisé) — politique de confidentialité : stripe.com/privacy</li>
  <li>PayPal (paiement) — politique de confidentialité : paypal.com/privacy</li>
  <li>Prestataires techniques (hébergement) soumis à obligation de confidentialité</li>
</ul>

<h3>7. Vos droits (RGPD)</h3>
<p>Conformément au RGPD, vous disposez des droits suivants :</p>
<ul>
  <li><strong>Accès :</strong> obtenir une copie de vos données</li>
  <li><strong>Rectification :</strong> corriger vos données inexactes</li>
  <li><strong>Effacement :</strong> demander la suppression (« droit à l'oubli »)</li>
  <li><strong>Portabilité :</strong> recevoir vos données dans un format lisible</li>
  <li><strong>Opposition :</strong> vous opposer à certains traitements</li>
  <li><strong>Désabonnement :</strong> via le lien présent dans chaque email</li>
</ul>
<p>Pour exercer ces droits : <strong>{EMAIL}</strong><br>
Réclamation auprès de la CNIL : <a href='https://www.cnil.fr' target='_blank'>www.cnil.fr</a></p>

<h3>8. Sécurité</h3>
<p>Nous mettons en œuvre les mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte ou divulgation.</p>

<h3>9. Cookies</h3>
<p>Voir nos CGU — Article 6.</p>
    "],

    // ---- POLITIQUE DE RETOUR ----
    ['retour', 'Politique de retour & remboursement', "
<h2>Politique de retour et remboursement</h2>

<h3>Prestations de service — Droit de rétractation</h3>
<p>Conformément à l'article L.221-28 du Code de la consommation, <strong>le droit de rétractation de 14 jours ne s'applique pas</strong> aux contrats de prestation de services pleinement exécutés avant la fin du délai de rétractation, notamment lorsque le consommateur a expressément demandé l'exécution immédiate du service (dépôt de moto et démarrage de l'intervention).</p>

<h3>Annulation de rendez-vous</h3>
<ul>
  <li><strong>Plus de 48h avant :</strong> annulation gratuite, remboursement intégral de tout acompte</li>
  <li><strong>Moins de 48h avant :</strong> l'acompte éventuel peut être conservé à titre de dédommagement</li>
  <li><strong>Non-présentation :</strong> facturation d'un forfait de déplacement si convenu</li>
</ul>

<h3>Réclamation sur une prestation</h3>
<p>Toute réclamation doit être formulée dans les <strong>7 jours ouvrés</strong> suivant la restitution de la moto, par email à {EMAIL} ou par courrier recommandé. Au-delà, la prestation est réputée acceptée.</p>
<p>{SITE_NAME} s'engage à examiner toute réclamation sous 5 jours ouvrés et à proposer une solution (reprise de l'intervention, avoir ou remboursement partiel) selon la nature du litige.</p>

<h3>Produits physiques (pièces revendues)</h3>
<p>Le retour de pièces est accepté dans les 14 jours, non montées, dans leur emballage d'origine. Les frais de retour sont à la charge du client.</p>

<h3>Contact</h3>
<p>Email : {EMAIL}<br>
Téléphone : {TELEPHONE}</p>
    "],

    ];

    foreach ($pages as [$slug, $title, $content]) {
        $pdo->prepare('INSERT IGNORE INTO kk_legal_pages(slug,title,content) VALUES(?,?,?)')->execute([$slug,$title,trim($content)]);
    }
}

function ensure_tables(): void {
    static $done2 = false;
    if (!$done2) {
        $done2 = true;
        $pdo = db();
        // Pages légales
    if (!$pdo->query("SHOW TABLES LIKE 'kk_legal_pages'")->fetchColumn()) {
        $pdo->exec("CREATE TABLE kk_legal_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL DEFAULT '',
            title VARCHAR(200) NOT NULL DEFAULT '',
            content MEDIUMTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        seed_legal_pages($pdo);
    }

    // Colonnes prix sur appointments
        try { $pdo->exec("ALTER TABLE kk_appointments ADD COLUMN price_estimate DECIMAL(10,2) DEFAULT NULL"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE kk_appointments ADD COLUMN price_final DECIMAL(10,2) DEFAULT NULL"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE kk_appointments ADD COLUMN price_note VARCHAR(500) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE kk_appointments ADD COLUMN payment_status ENUM('none','link_sent','partial','paid') NOT NULL DEFAULT 'none'"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE kk_appointments ADD COLUMN payment_link_token VARCHAR(64) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        // Catalogue des prix
        if (!$pdo->query("SHOW TABLES LIKE 'kk_price_catalog'")->fetchColumn()) {
            $pdo->exec("CREATE TABLE kk_price_catalog (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                service_id INT UNSIGNED,
                position TINYINT UNSIGNED NOT NULL DEFAULT 0,
                label VARCHAR(300) NOT NULL DEFAULT '',
                description TEXT,
                price_from DECIMAL(10,2) DEFAULT NULL,
                price_to DECIMAL(10,2) DEFAULT NULL,
                price_exact DECIMAL(10,2) DEFAULT NULL,
                unit VARCHAR(50) NOT NULL DEFAULT '',
                highlight TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Données par défaut
            $pdo->exec("INSERT INTO kk_price_catalog(service_id,position,label,description,price_from,price_to,unit,highlight) VALUES
                (1,0,'Vidange fourche',         'Vidange + remplacement huile fourche',          80,  null, 'la paire', 0),
                (1,1,'Vidange amortisseur',     'Vidange amortisseur arrière',                  60,  null, 'unité',    0),
                (1,2,'Kit entretien complet',   'Fourche + amortisseur, joints inclus',        120,  160,  '',         1),
                (2,0,'Prépa fourches',          'Setting hydraulique selon profil pilote',      150,  200,  'la paire', 0),
                (2,1,'Prépa amortisseur',       'Setting selon poids et discipline',             90,  130,  'unité',    0),
                (2,2,'Prépa complète',          'Fourches + amortisseur, kit complet',         320,  420,  '',         1),
                (3,0,'Traitement DLC',          'Revêtement anti-friction noir',               150,  null, 'la paire', 0),
                (3,1,'Traitement TIN',          'Revêtement doré',                             170,  null, 'la paire', 0),
                (3,2,'Anodisation couleur',     'KYB, Kashima, Brun, Bleu, Rouge',             130,  180,  'la paire', 0),
                (3,3,'Rainbow',                 'Traitement multicolore premium',              260,  null, 'la paire', 1)");
        }
    }
    
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = db();
    $existing = $pdo->query("SHOW TABLES LIKE 'kk_home_blocks'")->fetchColumn();
    if (!$existing) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `kk_home_blocks` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
          `type` VARCHAR(50) NOT NULL DEFAULT 'text',
          `title` VARCHAR(300) NOT NULL DEFAULT '',
          `content` MEDIUMTEXT,
          `image` VARCHAR(500) NOT NULL DEFAULT '',
          `bg` ENUM('white','gray','dark','red') NOT NULL DEFAULT 'white',
          `layout` VARCHAR(50) NOT NULL DEFAULT 'full',
          `btn_label` VARCHAR(200) NOT NULL DEFAULT '',
          `btn_url` VARCHAR(500) NOT NULL DEFAULT '',
          `extra` MEDIUMTEXT,
          `active` TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

// ---- Settings (table kk_settings) ----
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $s = db()->prepare('SELECT val FROM kk_settings WHERE `key`=?');
        $s->execute([$key]);
        $cache[$key] = $s->fetchColumn() ?: $default;
    }
    return $cache[$key];
}
function set_setting(string $key, string $val): void {
    db()->prepare('INSERT INTO kk_settings (`key`,val) VALUES(?,?)
        ON DUPLICATE KEY UPDATE val=VALUES(val)')
       ->execute([$key, $val]);
}

// ---- Helpers ----
function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function j(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); }
function jd(string $s, mixed $default = []): mixed {
    $r = json_decode($s, true);
    return ($r !== null) ? $r : $default;
}

// ---- Session ----
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
function is_admin(): bool {
    start_session();
    return !empty($_SESSION['kikr_admin']);
}
function require_admin(): void {
    start_session();
    if (empty($_SESSION['kikr_admin'])) {
        header('Location: ' . BASE_URL . '/admin/login.php'); exit;
    }
}

// ---- Upload ----
function upload_media(array $file, string $sub = ''): ?string {
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if (!in_array(strtolower($file['type']), $allowed)) return null;
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dir  = __DIR__ . '/img/' . ($sub ?: 'media');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = uniqid('img_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return BASE_URL . '/img/' . ($sub ?: 'media') . '/' . $name;
    }
    return null;
}
function slugify(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    foreach (['à'=>'a','á'=>'a','â'=>'a','ä'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
              'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
              'ç'=>'c','ñ'=>'n'] as $from=>$to) $s = str_replace($from,$to,$s);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}
