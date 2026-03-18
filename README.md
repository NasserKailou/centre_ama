# CSI — Centre de Santé Intégré

> Système de gestion médical complet pour un centre de santé — Développé avec Symfony 7 + MySQL 8

---

## 🏥 Présentation

**CSI** est une application web de gestion médicale intégrée permettant de gérer :
- Les patients et leurs dossiers médicaux complets
- Les consultations et prescriptions
- Les rendez-vous (planning médecin)
- La pharmacie et le stock de médicaments
- La facturation et la caisse (avec gestion des assurances)
- Les rapports et statistiques de l'établissement

---

## ✅ Fonctionnalités implémentées

### 👥 Gestion des Patients
- Création / modification / consultation de dossiers patients
- Historique des consultations et examens
- Gestion des antécédents médicaux, allergies
- Lien avec partenaires assurances

### 🗓️ Rendez-vous
- Création, modification, annulation de RDV
- Confirmation et démarrage de consultation depuis le RDV
- Vue planning par médecin et par date

### 🩺 Consultations
- Saisie complète : anamnèse, examen clinique, diagnostic, traitement
- Paramètres vitaux (TA, température, poids, taille, FC)
- Prescriptions d'examens biologiques / radiologiques
- Statuts : planifiée → en cours → terminée

### 💊 Pharmacie
- Catalogue de produits pharmaceutiques
- Gestion du stock avec mouvements d'entrée/sortie
- Alertes stock faible / rupture
- Péremptions

### 💰 Caisse & Facturation
- Facturation combinée actes médicaux + médicaments
- Gestion assurances : part assureur / part patient
- Impression de reçus et factures
- Situation journalière de caisse
- Modes de paiement : cash, mobile money, virement

### 📊 Rapports & Statistiques
- KPIs : consultations, CA, patients, factures
- Graphiques évolution temporelle (Chart.js)
- Répartition recettes par type
- Activité par médecin, top actes, partenaires
- Export Excel et PDF

### 🔐 Administration
- Gestion des utilisateurs et rôles
- Activation/désactivation des comptes
- Réinitialisation de mots de passe
- Configuration des actes médicaux

### 🤝 Partenaires
- Assurances, mutuelles, organismes d'État
- Taux de prise en charge configurables
- Situation financière par partenaire

---

## 🗂️ Architecture technique

### Stack
| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.2 + Symfony 7 |
| Base de données | MySQL 8.0 (Doctrine ORM) |
| Templates | Twig |
| Frontend | CSS custom (thème bleu CSI) + Vanilla JS |
| Graphiques | Chart.js 4 |
| Pagination | KnpPaginatorBundle |
| PDF | DomPDF |
| Export Excel | PhpSpreadsheet |
| Container | Docker (PHP-FPM + Nginx + Supervisor) |

### Rôles utilisateurs
| Rôle | Accès |
|------|-------|
| `ROLE_ADMIN` | Accès total + administration |
| `ROLE_MEDECIN` | Consultations, RDV, son espace médecin |
| `ROLE_CAISSIER` | Facturation, caisse, rapports |
| `ROLE_ASSISTANT` | Patients, RDV, consultations (lecture) |

---

## 📁 Structure du projet

```
webapp/
├── src/
│   ├── Controller/          # 11 contrôleurs (Admin, Caisse, Consultation, Dashboard...)
│   ├── Entity/              # 11 entités Doctrine
│   └── Repository/          # Repositories avec requêtes personnalisées
├── templates/               # Templates Twig organisés par module
│   ├── base.html.twig       # Layout principal avec sidebar
│   ├── admin/, caisse/, consultation/, dashboard/
│   ├── medecin/, partenaire/, patient/, pharmacie/
│   ├── rapport/, rdv/, security/
├── public/
│   ├── css/app.css          # Thème CSI principal (~22k)
│   ├── css/components.css   # Composants UI
│   └── js/app.js            # JavaScript application
├── migrations/
│   └── Version20260318000000.sql   # Migration SQL complète avec données initiales
├── docker/
│   ├── nginx/               # Config Nginx optimisée
│   ├── php/                 # Config PHP-FPM production
│   ├── supervisord.conf     # Superviseur de processus
│   └── entrypoint.sh        # Script de démarrage container
├── config/                  # Configuration Symfony
├── docker-compose.yml       # Stack complète Docker
├── Dockerfile               # Multi-stage build optimisé
└── .env / .env.prod         # Variables d'environnement
```

---

## 🚀 Installation & Démarrage

### Option 1 — Docker Compose (recommandé)

```bash
# 1. Cloner le dépôt
git clone https://github.com/VOTRE_ORG/csi-sante.git
cd csi-sante

# 2. Copier les variables d'environnement
cp .env.prod .env.local
# Éditer .env.local et changer APP_SECRET et les mots de passe

# 3. Démarrer la stack
docker compose up -d db phpmyadmin     # Dev (avec phpMyAdmin sur :8080)
# OU
docker compose --profile prod up -d    # Production complète

# 4. L'application est disponible sur http://localhost:80
```

### Option 2 — Installation locale

```bash
# Prérequis : PHP 8.2+, Composer, MySQL 8, Node (optionnel)

# 1. Dépendances PHP
composer install

# 2. Configuration
cp .env .env.local
# Modifier DATABASE_URL dans .env.local

# 3. Base de données
mysql -u root -p < migrations/Version20260318000000.sql

# 4. Cache Symfony
php bin/console cache:warmup

# 5. Serveur de développement
symfony serve
# OU
php -S localhost:8000 -t public/
```

---

## 🔑 Accès par défaut

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | `admin@csi.ne` | `Admin@2024` |

> ⚠️ **Changer le mot de passe admin en production !**

---

## 🗃️ Modèles de données

### Entités principales

```
User          → Utilisateurs du système (médecins, caissiers, admins...)
Patient       → Dossier patient avec antécédents et assurance
RendezVous    → Planning médecin-patient
Consultation  → Consultation avec paramètres vitaux
PrescriptionExamen → Examens prescrits lors d'une consultation
ActeMedical   → Catalogue des actes médicaux et tarifs
ProduitPharmaceutique → Catalogue médicaments et stock
MouvementStock → Traçabilité des mouvements de stock
FactureGlobale  → Facture consolidée (actes + pharmacie)
LigneFacture    → Détail des lignes de facture
Partenaire    → Assurances, mutuelles, organismes
```

---

## 🐳 Variables d'environnement production

```env
APP_ENV=prod
APP_SECRET=<32 caractères aléatoires>
DATABASE_URL="mysql://user:password@host:3306/csi_sante?serverVersion=8.0.32"
APP_NAME="CSI - Centre de Santé Intégré"
CURRENCY=FCFA
TIMEZONE=Africa/Niamey
AUTO_MIGRATE=true
```

---

## 📋 Données pré-chargées

La migration SQL inclut :
- **1 compte admin** : admin@csi.ne / Admin@2024
- **18 actes médicaux** : consultations, examens, traitements, hospitalisations
- **5 partenaires** : SONUCI, SUNU Assurances, Sanlam, Mutuelle Générale, Ministère Santé

---

## 📌 Statut du projet

| Module | Statut |
|--------|--------|
| Authentification & Sécurité | ✅ Complet |
| Gestion Patients | ✅ Complet |
| Rendez-vous | ✅ Complet |
| Consultations | ✅ Complet |
| Pharmacie & Stock | ✅ Complet |
| Caisse & Facturation | ✅ Complet |
| Rapports (avec graphiques) | ✅ Complet |
| Administration | ✅ Complet |
| Espace Médecin | ✅ Complet |
| Docker / Déploiement | ✅ Complet |
| Tests unitaires | 🔲 Non démarré |
| API REST | 🔲 Optionnel |

---

## 📄 Licence

Propriétaire — Usage interne CSI uniquement.

---

*Dernière mise à jour : mars 2026*
