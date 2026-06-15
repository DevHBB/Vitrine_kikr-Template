# Kik'r v3 — Site vitrine MySQL

## 🚀 Installation

1. Déposer le dossier dans `C:\xampp\htdocs\kikr3\`
2. Créer une base de données vide dans phpMyAdmin (ex: `kikr_db`)
3. Aller sur : `http://localhost/kikr3/install/`
4. Suivre les 3 étapes de l'installeur
5. Se connecter à l'admin : `http://localhost/kikr3/admin/`

## ✅ Avantages MySQL vs JSON
- Sauvegarde fiable à 100% (plus de problèmes de permissions)
- Boxes hero : 0 à 3, suppression instantanée
- Toutes les données modifiables depuis l'admin
- Pas de fichiers à rendre writables

## 📁 Structure
- `install/` → Installeur (à supprimer après installation)
- `admin/`   → Panel d'administration
- `lib/`     → Fonctions de lecture DB
- `css/`     → Styles
- `img/`     → Images uploadées
