-- Kik'r — Schéma MySQL
-- Encodage : utf8mb4

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---- Settings (clé/valeur) ----
CREATE TABLE IF NOT EXISTS `kk_settings` (
  `key`  VARCHAR(100) NOT NULL,
  `val`  MEDIUMTEXT   NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Hero ----
CREATE TABLE IF NOT EXISTS `kk_hero` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title_line1`  VARCHAR(200) NOT NULL DEFAULT 'Prépa moto',
  `title_line2`  VARCHAR(200) NOT NULL DEFAULT 'suspensions',
  `model_sub`    VARCHAR(200) NOT NULL DEFAULT 'Modèle phare',
  `model_code`   VARCHAR(100) NOT NULL DEFAULT 'KX450F',
  `model_desc`   TEXT,
  `btn_label`    VARCHAR(100) NOT NULL DEFAULT 'Découvrir',
  `btn_url`      VARCHAR(255) NOT NULL DEFAULT '/services.php',
  `moto_image`   VARCHAR(500) NOT NULL DEFAULT '',
  `moto_left`    VARCHAR(50)  NOT NULL DEFAULT '100px',
  `moto_width`   VARCHAR(50)  NOT NULL DEFAULT '790px',
  `moto_anim`    VARCHAR(50)  NOT NULL DEFAULT 'slide-up',
  `moto_anim_delay` DECIMAL(4,2) NOT NULL DEFAULT 0.15,
  `reviews_count` VARCHAR(50)  NOT NULL DEFAULT '500+',
  `reviews_text`  VARCHAR(200) NOT NULL DEFAULT 'pilotes satisfaits',
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Hero boxes (0 à 3) ----
CREATE TABLE IF NOT EXISTS `kk_hero_boxes` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `type`     ENUM('reviews','stat','text') NOT NULL DEFAULT 'stat',
  `label`    VARCHAR(200) NOT NULL DEFAULT '',
  `value`    VARCHAR(500) NOT NULL DEFAULT '',
  `sub`      VARCHAR(300) NOT NULL DEFAULT '',
  `style`    ENUM('white','dark','red') NOT NULL DEFAULT 'white'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Specs (4 chiffres clés) ----
CREATE TABLE IF NOT EXISTS `kk_specs` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `value`    VARCHAR(100) NOT NULL DEFAULT '',
  `label`    VARCHAR(200) NOT NULL DEFAULT '',
  `icon`     VARCHAR(50)  NOT NULL DEFAULT 'bolt'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- About ----
CREATE TABLE IF NOT EXISTS `kk_about` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(300) NOT NULL DEFAULT 'QUI SOMMES NOUS ?',
  `experience`  VARCHAR(20)  NOT NULL DEFAULT '22',
  `quote`       TEXT,
  `paragraphs`  MEDIUMTEXT,   -- JSON array
  `photo`       VARCHAR(500)  NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Services ----
CREATE TABLE IF NOT EXISTS `kk_services` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `slug`       VARCHAR(100) NOT NULL DEFAULT '',
  `label`      VARCHAR(200) NOT NULL DEFAULT '',
  `title`      VARCHAR(300) NOT NULL DEFAULT '',
  `description` TEXT,
  `price`      VARCHAR(200) NOT NULL DEFAULT '',
  `highlight`  VARCHAR(300) NOT NULL DEFAULT '',
  `treatments` MEDIUMTEXT,  -- JSON array
  `image`      VARCHAR(500) NOT NULL DEFAULT '',
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Partners groups ----
CREATE TABLE IF NOT EXISTS `kk_partner_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `label`       VARCHAR(200) NOT NULL DEFAULT '',
  `color`       VARCHAR(20)  NOT NULL DEFAULT '#ed0c0f'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Partners ----
CREATE TABLE IF NOT EXISTS `kk_partners` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT UNSIGNED NOT NULL,
  `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `name`     VARCHAR(200) NOT NULL DEFAULT '',
  `type`     VARCHAR(100) NOT NULL DEFAULT '',
  `logo`     VARCHAR(500) NOT NULL DEFAULT '',
  FOREIGN KEY (`group_id`) REFERENCES `kk_partner_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Portfolio pilots ----
CREATE TABLE IF NOT EXISTS `kk_pilots` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `name`       VARCHAR(200) NOT NULL DEFAULT '',
  `discipline` VARCHAR(100) NOT NULL DEFAULT '',
  `bio`        TEXT,
  `photo`      VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Contact / hours ----
CREATE TABLE IF NOT EXISTS `kk_contact` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(300) NOT NULL DEFAULT 'Contactez-nous',
  `subtitle`    VARCHAR(400) NOT NULL DEFAULT '',
  `fields_note` VARCHAR(500) NOT NULL DEFAULT '',
  `map_embed`   TEXT,
  `hours`       MEDIUMTEXT   -- JSON array
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Navigation ----
CREATE TABLE IF NOT EXISTS `kk_nav` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `label`    VARCHAR(200) NOT NULL DEFAULT '',
  `href`     VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Custom pages (builder) ----
CREATE TABLE IF NOT EXISTS `kk_pages` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug`     VARCHAR(200) NOT NULL DEFAULT '',
  `title`    VARCHAR(300) NOT NULL DEFAULT '',
  `subtitle` VARCHAR(500) NOT NULL DEFAULT '',
  `banner`   VARCHAR(500) NOT NULL DEFAULT '',
  `status`   ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `blocks`   MEDIUMTEXT,  -- JSON array
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Mediathèque ----
CREATE TABLE IF NOT EXISTS `kk_media` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `filename`   VARCHAR(500) NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `sub`        VARCHAR(100) NOT NULL DEFAULT 'media',
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- ---- Blocs homepage (sous le hero) ----
CREATE TABLE IF NOT EXISTS `kk_home_blocks` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `type`     VARCHAR(50) NOT NULL DEFAULT 'text',
  `title`    VARCHAR(300) NOT NULL DEFAULT '',
  `content`  MEDIUMTEXT,
  `image`    VARCHAR(500) NOT NULL DEFAULT '',
  `bg`       ENUM('white','gray','dark','red') NOT NULL DEFAULT 'white',
  `layout`   VARCHAR(50) NOT NULL DEFAULT 'full',
  `btn_label` VARCHAR(200) NOT NULL DEFAULT '',
  `btn_url`   VARCHAR(500) NOT NULL DEFAULT '',
  `extra`    MEDIUMTEXT,
  `active`   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Planning settings ----
CREATE TABLE IF NOT EXISTS `kk_planning_settings` (
  `key` VARCHAR(100) NOT NULL,
  `val` TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Clients ----
CREATE TABLE IF NOT EXISTS `kk_clients` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type`       ENUM('particulier','pro') NOT NULL DEFAULT 'particulier',
  `name`       VARCHAR(200) NOT NULL,
  `email`      VARCHAR(200) NOT NULL,
  `phone`      VARCHAR(50)  NOT NULL DEFAULT '',
  `company`    VARCHAR(200) NOT NULL DEFAULT '',
  `notes`      TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Rendez-vous ----
CREATE TABLE IF NOT EXISTS `kk_appointments` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `client_id`    INT UNSIGNED,
  `client_name`  VARCHAR(200) NOT NULL,
  `client_email` VARCHAR(200) NOT NULL,
  `client_phone` VARCHAR(50)  NOT NULL DEFAULT '',
  `client_type`  ENUM('particulier','pro') NOT NULL DEFAULT 'particulier',
  `moto_marque`  VARCHAR(100) NOT NULL DEFAULT '',
  `moto_modele`  VARCHAR(100) NOT NULL DEFAULT '',
  `moto_annee`   YEAR,
  `service_id`   INT UNSIGNED,
  `service_label` VARCHAR(200) NOT NULL DEFAULT '',
  `slot_date`    DATE,
  `slot_time`    TIME,
  `duree_jours`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status`       ENUM('pending','confirmed','in_progress','ready','collected','cancelled') NOT NULL DEFAULT 'pending',
  `priority`     TINYINT(1) NOT NULL DEFAULT 0,
  `notes_client` TEXT,
  `notes_admin`  TEXT,
  `pdf_fiche`    VARCHAR(500) NOT NULL DEFAULT '',
  `notif_sent`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `kk_clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Créneaux disponibles ----
CREATE TABLE IF NOT EXISTS `kk_slots` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date`     DATE NOT NULL,
  `time`     TIME NOT NULL DEFAULT '09:00:00',
  `max`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `booked`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `closed`   TINYINT(1) NOT NULL DEFAULT 0,
  `note`     VARCHAR(300) NOT NULL DEFAULT '',
  UNIQUE KEY `date_time` (`date`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logo site
ALTER TABLE `kk_settings` MODIFY `key` VARCHAR(100) NOT NULL;
-- (pas de modif schema nécessaire, le logo sera une entrée dans kk_settings)

-- ============================================================
-- MODULE CLIENTS ÉTENDU
-- ============================================================
ALTER TABLE `kk_clients`
  ADD COLUMN IF NOT EXISTS `password_hash` VARCHAR(255) NOT NULL DEFAULT '' AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `newsletter_opt` TINYINT(1) NOT NULL DEFAULT 1 AFTER `email_verified`,
  ADD COLUMN IF NOT EXISTS `sms_opt` TINYINT(1) NOT NULL DEFAULT 1 AFTER `newsletter_opt`,
  ADD COLUMN IF NOT EXISTS `stripe_customer_id` VARCHAR(200) NOT NULL DEFAULT '' AFTER `sms_opt`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ============================================================
-- FACTURATION
-- ============================================================
CREATE TABLE IF NOT EXISTS `kk_invoices` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number`        VARCHAR(50)  NOT NULL DEFAULT '',
  `type`          ENUM('quote','invoice','credit') NOT NULL DEFAULT 'invoice',
  `status`        ENUM('draft','sent','paid','partial','cancelled') NOT NULL DEFAULT 'draft',
  `client_id`     INT UNSIGNED,
  `client_name`   VARCHAR(200) NOT NULL DEFAULT '',
  `client_email`  VARCHAR(200) NOT NULL DEFAULT '',
  `client_phone`  VARCHAR(50)  NOT NULL DEFAULT '',
  `client_address` TEXT,
  `client_tva`    VARCHAR(50)  NOT NULL DEFAULT '',
  `appointment_id` INT UNSIGNED,
  `invoice_lines` MEDIUMTEXT,   -- JSON array of lines
  `tva_rate`      DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  `discount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` ENUM('amount','percent') NOT NULL DEFAULT 'amount',
  `notes`         TEXT,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT '',
  `payment_date`  DATE,
  `payment_ref`   VARCHAR(200) NOT NULL DEFAULT '',
  `stripe_pi_id`  VARCHAR(200) NOT NULL DEFAULT '',
  `paypal_order_id` VARCHAR(200) NOT NULL DEFAULT '',
  `due_date`      DATE,
  `related_invoice_id` INT UNSIGNED,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `number` (`number`),
  FOREIGN KEY (`client_id`) REFERENCES `kk_clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compteur de numéros de facture
CREATE TABLE IF NOT EXISTS `kk_invoice_counters` (
  `type`    VARCHAR(20) NOT NULL,
  `year`    YEAR NOT NULL,
  `counter` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`type`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Liens de paiement
CREATE TABLE IF NOT EXISTS `kk_payment_links` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `token`      VARCHAR(64)  NOT NULL DEFAULT '',
  `invoice_id` INT UNSIGNED DEFAULT NULL,
  `amount`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency`   VARCHAR(3)   NOT NULL DEFAULT 'EUR',
  `expires_at` DATETIME,
  `used_at`    DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NEWSLETTER / MAILING
-- ============================================================
CREATE TABLE IF NOT EXISTS `kk_newsletter_subscribers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(200) NOT NULL,
  `name`       VARCHAR(200) NOT NULL DEFAULT '',
  `phone`      VARCHAR(50)  NOT NULL DEFAULT '',
  `segment`    SET('all','rdv','pro','newsletter') NOT NULL DEFAULT 'newsletter',
  `status`     ENUM('active','unsubscribed','bounced') NOT NULL DEFAULT 'active',
  `token`      VARCHAR(64)  NOT NULL DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kk_mailing_templates` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(200) NOT NULL DEFAULT '',
  `subject`    VARCHAR(500) NOT NULL DEFAULT '',
  `body_html`  MEDIUMTEXT,
  `body_txt`   MEDIUMTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kk_campaigns` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(200) NOT NULL DEFAULT '',
  `template_id` INT UNSIGNED,
  `subject`    VARCHAR(500) NOT NULL DEFAULT '',
  `body_html`  MEDIUMTEXT,
  `segment`    VARCHAR(50)  NOT NULL DEFAULT 'all',
  `channel`    ENUM('email','sms','both') NOT NULL DEFAULT 'email',
  `sms_text`   VARCHAR(160) NOT NULL DEFAULT '',
  `status`     ENUM('draft','scheduled','sending','sent') NOT NULL DEFAULT 'draft',
  `sent_at`    DATETIME,
  `scheduled_at` DATETIME,
  `total_sent` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_open` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `kk_mailing_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kk_campaign_sends` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT UNSIGNED NOT NULL,
  `email`       VARCHAR(200) NOT NULL DEFAULT '',
  `opened`      TINYINT(1) NOT NULL DEFAULT 0,
  `opened_at`   DATETIME,
  `token`       VARCHAR(64)  NOT NULL DEFAULT '',
  INDEX `campaign_id` (`campaign_id`),
  INDEX `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NAVIGATION ÉTENDUE (sous-menus + toggles)
-- ============================================================
ALTER TABLE `kk_nav`
  ADD COLUMN IF NOT EXISTS `parent_id` INT UNSIGNED DEFAULT NULL AFTER `href`,
  ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `parent_id`,
  ADD COLUMN IF NOT EXISTS `target` VARCHAR(20) NOT NULL DEFAULT '_self' AFTER `active`;

-- ============================================================
-- MODULES TOGGLES (settings)
-- ============================================================
-- Stockés dans kk_settings avec préfixe module_
-- module_planning, module_shop, module_newsletter,
-- module_payment, module_client_area, module_invoice

-- Codes OTP espace client (magic login)
CREATE TABLE IF NOT EXISTS `kk_client_otp` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(200) NOT NULL,
  `code`       VARCHAR(6)   NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prix sur les rendez-vous
ALTER TABLE `kk_appointments`
  ADD COLUMN IF NOT EXISTS `price_estimate` DECIMAL(10,2) DEFAULT NULL AFTER `priority`,
  ADD COLUMN IF NOT EXISTS `price_final`    DECIMAL(10,2) DEFAULT NULL AFTER `price_estimate`,
  ADD COLUMN IF NOT EXISTS `price_note`     VARCHAR(500)  NOT NULL DEFAULT '' AFTER `price_final`,
  ADD COLUMN IF NOT EXISTS `payment_status` ENUM('none','link_sent','partial','paid') NOT NULL DEFAULT 'none' AFTER `price_note`,
  ADD COLUMN IF NOT EXISTS `payment_link_token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `payment_status`;

-- Catalogue des prix (par service, avec options)
CREATE TABLE IF NOT EXISTS `kk_price_catalog` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `service_id`  INT UNSIGNED,
  `position`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `label`       VARCHAR(300) NOT NULL DEFAULT '',
  `description` TEXT,
  `price_from`  DECIMAL(10,2) DEFAULT NULL,
  `price_to`    DECIMAL(10,2) DEFAULT NULL,
  `price_exact` DECIMAL(10,2) DEFAULT NULL,
  `unit`        VARCHAR(50)   NOT NULL DEFAULT '',
  `highlight`   TINYINT(1)    NOT NULL DEFAULT 0,
  `active`      TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pages légales
CREATE TABLE IF NOT EXISTS `kk_legal_pages` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug`    VARCHAR(50)  NOT NULL DEFAULT '',
  `title`   VARCHAR(200) NOT NULL DEFAULT '',
  `content` MEDIUMTEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SHOP
-- ============================================================
CREATE TABLE IF NOT EXISTS `kk_products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `position`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `slug`        VARCHAR(200) NOT NULL DEFAULT '',
  `name`        VARCHAR(300) NOT NULL DEFAULT '',
  `description` TEXT,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_promo` DECIMAL(10,2) DEFAULT NULL,
  `images`      TEXT,
  `category`    VARCHAR(100) NOT NULL DEFAULT '',
  `stock`       INT NOT NULL DEFAULT -1,
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  `featured`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SHOP : commandes
-- ============================================================
CREATE TABLE IF NOT EXISTS `kk_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number`         VARCHAR(50)  NOT NULL DEFAULT '',
  `status`         ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `client_id`      INT UNSIGNED,
  `client_name`    VARCHAR(200) NOT NULL DEFAULT '',
  `client_email`   VARCHAR(200) NOT NULL DEFAULT '',
  `client_phone`   VARCHAR(50)  NOT NULL DEFAULT '',
  `shipping_addr`  TEXT,
  `items`          MEDIUMTEXT,   -- JSON [{product_id, name, qty, unit_price}]
  `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50)  NOT NULL DEFAULT '',
  `payment_status` ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `invoice_id`     INT UNSIGNED,
  `notes`          TEXT,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
