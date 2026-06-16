# Kik'r Suspension — CMS

Site vitrine + boutique + planning + facturation pour Kik'r Suspension, développé sur mesure en PHP 8.2 / MySQL.

## Stack technique

- PHP 8.2+ (aucune dépendance externe, pas de Composer)
- MySQL / MariaDB
- JavaScript vanilla (aucun framework)
- Hébergement compatible : OVH, shared hosting classique, XAMPP en local

## Installation

1. Déposer les fichiers sur le serveur (racine ou sous-dossier).
2. Créer une base MySQL/MariaDB vide.
3. Aller sur `/install/` dans le navigateur et suivre les 4 étapes :
   - Bienvenue
   - Connexion à la base de données (hôte, nom, utilisateur, mot de passe)
   - Configuration du site (nom, email, téléphone)
   - Création du mot de passe administrateur
4. Une fois l'installation terminée, le dossier `/install/` se bloque automatiquement (il peut aussi être supprimé).
5. Se connecter sur `/admin/login.php`.

Si le site est servi depuis un sous-dossier (ex. `/kikr3`), renseigner ce chemin dans `BASE_URL` au début de `config.php`.

## Fonctionnalités

### Site public
- Page d'accueil avec blocs personnalisables (texte, image, services à la une)
- Pages "Qui sommes-nous", Services, Portfolio, Partenaires
- Pages légales (mentions légales, CGV, confidentialité) générées automatiquement, modifiables
- Pages libres illimitées (créées depuis l'admin, avec constructeur de blocs)
- Formulaire de contact avec protection reCAPTCHA v3 (optionnelle)

### Planning & rendez-vous
- Formulaire de prise de RDV public (marque/modèle moto, prestation, créneau)
- Gestion des créneaux et disponibilités côté admin
- Statuts de RDV : en attente, confirmé, en cours, prêt, récupéré, annulé
- Génération automatique d'un lien de paiement à la prise de RDV (configurable)
- Espace client pour suivre ses RDV

### Boutique en ligne
- Catalogue produits avec variantes/caractéristiques, stock, catégories
- Panier (slide-up), tunnel de commande
- Paiement Stripe, PayPal, virement bancaire ou paiement en main propre/à la livraison
- Génération automatique de facture PDF à la commande
- Gestion des commandes côté admin

### Facturation
- Devis, factures, avoirs au format PDF
- Compteurs de numérotation automatique
- Liens de paiement à usage unique envoyés par email
- Suivi du statut de paiement (en attente / payé)

### Espace client (sans mot de passe)
- Connexion par code à usage unique envoyé par email (OTP, valable 15 minutes)
- Consultation des RDV, factures, historique
- Modification des informations personnelles et préférences (newsletter, SMS)

### Newsletter & campagnes
- Éditeur visuel à blocs (logo, image, texte, bouton, 2 colonnes, séparateur, pied de page) — aucune connaissance HTML requise
- Mode HTML brut disponible en option avancée
- Gestion des abonnés, segments (tous / newsletter / clients RDV / clients pro)
- Envoi de campagnes email et/ou SMS
- Suivi des ouvertures et désabonnement

### Administration
- Tableau de bord avec sidebar par sections (Planning, Accueil, Site, Pages fixes, Pages libres)
- Gestion des modules : chaque grande fonctionnalité (planning, boutique, newsletter, paiement, espace client, facturation, SMS) peut être désactivée indépendamment sans perte de données
- Médiathèque centralisée
- Gestion de la navigation du site
- Mise à jour du CMS depuis GitHub directement depuis l'admin

## Paramétrages disponibles (Admin)

### Infos générales — `admin/site.php`
Nom du site, slogan, logo (upload ou URL), hauteur du logo, téléphone, email, adresse, réseaux sociaux (Facebook, Instagram, LinkedIn, TikTok, Twitter, YouTube), texte de copyright du pied de page.

### Paiement — `admin/payment_settings.php`
- Clé publique/secrète Stripe
- Client ID / secret PayPal
- IBAN, BIC, nom de banque (pour virement)
- Toggle : paiement obligatoire avant confirmation de RDV
- Toggle : autoriser le paiement en main propre / à la livraison (désactivable sur tous les formulaires de paiement en un clic)
- Mode de paiement RDV (à la prise de RDV ou après confirmation manuelle)

### Email / SMS / Sécurité — `admin/smtp_settings.php`
- Configuration SMTP (hôte, port, sécurité, identifiants, nom d'expéditeur) ou envoi via la fonction mail() native du serveur
- Fournisseur SMS, clé API, secret, expéditeur
- Clé reCAPTCHA v3 (site + secret) et score minimum de validation
- Envoi d'un email/SMS de test depuis l'interface

### Modules — `admin/modules.php`
Active/désactive sans perte de données : Planning & RDV, Boutique, Newsletter, Paiement en ligne, Espace client, Facturation, SMS. Permet aussi d'activer/désactiver les éléments du menu de navigation public.

### Catalogue des prix — `admin/price_catalog.php`
Grille tarifaire des prestations affichée publiquement.

### Paramètres planning — `admin/planning_settings.php`
Créneaux disponibles, durée par défaut des prestations.

## Identifiants et accès

- Admin : `/admin/login.php` — mot de passe défini à l'installation, modifiable depuis `admin/password.php`.
- Espace client : `/mon-compte.php` — connexion par code email, aucun mot de passe à gérer.

## Mise à jour

Le CMS vérifie automatiquement les nouvelles versions disponibles sur le dépôt GitHub `DevHBB/Vitrine_kikr-Template` et permet une mise à jour en un clic depuis `admin/update.php`, en conservant les données et les fichiers uploadés (`data/`, médias).

## Notes techniques

- Aucune donnée n'est perdue lors des mises à jour : les dossiers de données et médias sont protégés.
- Les colonnes SQL sont unifiées (pas de doublons bilingues type `label_fr`).
- PHP 8.2+ recommandé pour l'ensemble des fonctionnalités.
