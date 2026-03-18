# CSI — Centre de Santé Intégré

> Système de gestion médical complet — PHP 8.2 + Symfony 7 + MySQL 8  
> Déploiement sur **Windows avec XAMPP**

---

## 🔑 Comptes de test

| Rôle | Email | Mot de passe | Accès |
|------|-------|--------------|-------|
| **Administrateur** | `admin@csi.ne` | `Admin@2024` | Tout + administration |
| **Médecin** | `dr.kollo@csi.ne` | `Medecin@2024` | Consultations, RDV, espace médecin |
| **Médecin (spéc.)** | `dr.maiga@csi.ne` | `Medecin@2024` | Consultations pédiatriques, RDV |
| **Caissier** | `caisse@csi.ne` | `Caissier@2024` | Facturation, caisse, rapports |
| **Assistant(e)** | `assistant@csi.ne` | `Assistant@2024` | Patients, RDV (lecture) |

> 🔒 **Changer tous les mots de passe en production !**

---

## 🖥️ Installation sur Windows avec XAMPP

### Prérequis à installer dans l'ordre

1. **XAMPP** (PHP 8.2+) → https://www.apachefriends.org/download.html  
   *(choisir version avec PHP 8.2 ou 8.3)*

2. **Composer** → https://getcomposer.org/Composer-Setup.exe  
   *(assistant d'installation, cocher "Add to PATH")*

3. **Git** → https://git-scm.com/download/win  
   *(cocher "Git Bash Here" et "Add to PATH")*

---

## 📋 Installation étape par étape

### ÉTAPE 1 — Démarrer XAMPP

```
1. Ouvrir XAMPP Control Panel
2. Cliquer "Start" pour Apache
3. Cliquer "Start" pour MySQL
4. Vérifier que les deux sont verts (Running)
```

---

### ÉTAPE 2 — Cloner le projet

Ouvrir **Git Bash** (clic droit sur le bureau → "Git Bash Here") :

```bash
cd C:/xampp/htdocs

git clone https://github.com/NasserKailou/centre_ama.git csi

cd csi
```

---

### ÉTAPE 3 — Créer la base de données MySQL

**Option A — Via phpMyAdmin (navigateur)**

```
1. Ouvrir le navigateur → http://localhost/phpmyadmin
2. Cliquer "Nouvelle base de données"
3. Nom : csi_sante
4. Interclassement : utf8mb4_unicode_ci
5. Cliquer "Créer"
6. Cliquer sur "csi_sante" dans le panneau gauche
7. Cliquer l'onglet "Importer"
8. Cliquer "Choisir un fichier"
9. Sélectionner : C:\xampp\htdocs\csi\migrations\Version20260318000000.sql
10. Cliquer "Importer" (bouton en bas)
11. Message vert "Importation réussie" → OK
```

**Option B — Via ligne de commande Git Bash**

```bash
# Se placer dans le dossier du projet
cd C:/xampp/htdocs/csi

# Créer la base et importer (mot de passe root vide par défaut dans XAMPP)
C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS csi_sante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

C:/xampp/mysql/bin/mysql.exe -u root csi_sante < migrations/Version20260318000000.sql

echo "Base de données importée avec succès !"
```

---

### ÉTAPE 4 — Créer un utilisateur MySQL dédié (recommandé)

Dans **phpMyAdmin** → onglet "Comptes utilisateurs" → "Ajouter un compte" :

```
Nom d'utilisateur : csi_user
Hôte              : localhost
Mot de passe      : csi_password
Confirmer         : csi_password
✅ Cocher "Créer une base de données portant son nom et octroyer tous les privilèges"
```

**OU via Git Bash :**

```bash
C:/xampp/mysql/bin/mysql.exe -u root -e "
CREATE USER IF NOT EXISTS 'csi_user'@'localhost' IDENTIFIED BY 'csi_password';
GRANT ALL PRIVILEGES ON csi_sante.* TO 'csi_user'@'localhost';
FLUSH PRIVILEGES;
"
```

---

### ÉTAPE 5 — Configurer les variables d'environnement

Dans **Git Bash** (dans le dossier C:/xampp/htdocs/csi) :

```bash
# Copier le fichier .env en .env.local
cp .env .env.local
```

Ouvrir `C:\xampp\htdocs\csi\.env.local` avec **Bloc-notes** ou **Notepad++** et modifier :

```env
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=csi_secret_dev_32chars_minimum_!!

# Connexion MySQL XAMPP (mot de passe root vide par défaut)
DATABASE_URL="mysql://root:@127.0.0.1:3306/csi_sante?serverVersion=8.0&charset=utf8mb4"

# OU si vous avez créé csi_user :
# DATABASE_URL="mysql://csi_user:csi_password@127.0.0.1:3306/csi_sante?serverVersion=8.0&charset=utf8mb4"

APP_NAME="CSI - Centre de Santé Intégré"
APP_VERSION="1.0.0"
TIMEZONE="Africa/Niamey"
CURRENCY="FCFA"
```

---

### ÉTAPE 6 — Installer les dépendances PHP

Dans **Git Bash** (dossier C:/xampp/htdocs/csi) :

```bash
# Vérifier que Composer fonctionne
composer --version

# Installer les dépendances (peut prendre 2-3 minutes)
composer install --no-dev --optimize-autoloader
```

Si erreur `composer: command not found`, essayer :

```bash
php C:/ProgramData/ComposerSetup/bin/composer.phar install
```

---

### ÉTAPE 7 — Générer le cache Symfony

```bash
# Toujours dans C:/xampp/htdocs/csi
php bin/console cache:clear
php bin/console cache:warmup
```

---

### ÉTAPE 8 — Configurer Apache pour Symfony

**Option A — Utiliser le sous-dossier (plus simple)**

Accéder directement via : `http://localhost/csi/public/`

**Option B — Virtual Host (recommandé, propre)**

Ouvrir le fichier `C:\xampp\apache\conf\extra\httpd-vhosts.conf`  
Ajouter à la fin :

```apache
<VirtualHost *:80>
    ServerName csi.local
    DocumentRoot "C:/xampp/htdocs/csi/public"
    
    <Directory "C:/xampp/htdocs/csi/public">
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog "C:/xampp/apache/logs/csi-error.log"
    CustomLog "C:/xampp/apache/logs/csi-access.log" common
</VirtualHost>
```

Ouvrir `C:\Windows\System32\drivers\etc\hosts` **(en tant qu'Administrateur)** et ajouter :

```
127.0.0.1   csi.local
```

Puis **redémarrer Apache** dans XAMPP Control Panel.

L'application sera accessible sur : **http://csi.local**

---

### ÉTAPE 9 — Vérifier que tout fonctionne

Ouvrir le navigateur et aller sur :

```
http://csi.local
```

OU (sans virtual host) :

```
http://localhost/csi/public/
```

Vous devez voir la page de **connexion CSI**.

---

### ÉTAPE 10 — Se connecter et tester

```
Email    : admin@csi.ne
Mot de passe : Admin@2024
```

✅ Vous êtes redirigé vers le **Tableau de bord**

---

## 🔧 Activation de mod_rewrite (si page blanche ou erreur 404)

Dans XAMPP, vérifier que `mod_rewrite` est activé :

1. Ouvrir `C:\xampp\apache\conf\httpd.conf`
2. Chercher la ligne : `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Retirer le `#` → `LoadModule rewrite_module modules/mod_rewrite.so`
4. Redémarrer Apache

---

## 🐘 Extensions PHP requises

Vérifier dans `C:\xampp\php\php.ini` que ces lignes sont décommentées (sans `;`) :

```ini
extension=pdo_mysql
extension=mbstring
extension=intl
extension=gd
extension=zip
extension=openssl
```

Pour voir les extensions actives :

```bash
php -m | findstr -i "pdo mysql mbstring intl gd zip"
```

---

## 🛠️ Commandes utiles au quotidien

```bash
# Se placer dans le dossier du projet (toujours en premier)
cd C:/xampp/htdocs/csi

# Vider le cache (après modifications de config)
php bin/console cache:clear

# Voir toutes les routes disponibles
php bin/console debug:router

# Créer un nouvel utilisateur admin via console
php bin/console app:create-user

# Vérifier la connexion à la base de données
php bin/console doctrine:query:sql "SELECT COUNT(*) FROM user"

# Mettre à jour les permissions (si problème de cache)
icacls var /grant Everyone:F /T
```

---

## 📁 Structure des dossiers importants

```
C:\xampp\htdocs\csi\
│
├── public\              ← SEUL dossier accessible par Apache (DocumentRoot)
│   ├── index.php        ← Point d'entrée de l'application
│   ├── css\             ← Feuilles de style
│   └── js\              ← Scripts JavaScript
│
├── src\                 ← Code PHP (contrôleurs, entités...)
├── templates\           ← Pages HTML (Twig)
├── config\              ← Configuration Symfony
├── migrations\          ← Fichier SQL (schéma + données)
│
├── .env                 ← Config par défaut (ne pas modifier)
├── .env.local           ← Votre config locale (à créer, étape 5)
│
└── var\                 ← Cache et logs (généré automatiquement)
    ├── cache\
    └── log\
```

---

## ❌ Résolution des erreurs courantes

### Erreur : "An exception occurred while executing a query"
→ La base de données n'est pas importée ou la connexion est incorrecte  
→ Vérifier `DATABASE_URL` dans `.env.local`  
→ Vérifier que MySQL est démarré dans XAMPP

### Erreur : "No such file or directory — vendor/"
→ Les dépendances ne sont pas installées  
→ Relancer : `composer install`

### Erreur 500 — "The controller for URI ... is not callable"
→ Vider le cache : `php bin/console cache:clear`

### Page blanche ou erreur 404 sur toutes les pages
→ `mod_rewrite` Apache non activé (voir section ci-dessus)  
→ Ou `.htaccess` non lu → vérifier `AllowOverride All` dans la config Apache

### Erreur "Access denied for user root"
→ XAMPP a un mot de passe root → modifier `DATABASE_URL` avec le bon mot de passe  
→ Ou créer l'utilisateur `csi_user` (étape 4)

### Erreur "Class not found"
→ `composer dump-autoload --optimize`

### Logs d'erreurs
```bash
# Voir les logs Symfony
type var\log\dev.log

# Logs Apache
type C:\xampp\apache\logs\error.log
```

---

## 🏥 Fonctionnalités disponibles par rôle

### 🔴 Administrateur (`admin@csi.ne`)
- ✅ Tableau de bord global
- ✅ Gestion complète des patients
- ✅ Toutes les consultations
- ✅ Rendez-vous de tous les médecins
- ✅ Caisse et facturation
- ✅ Pharmacie et stock
- ✅ Partenaires et assurances
- ✅ Rapports et statistiques
- ✅ **Administration : gestion des utilisateurs, actes médicaux**

### 🟢 Médecin (`dr.kollo@csi.ne`, `dr.maiga@csi.ne`)
- ✅ Tableau de bord médecin (RDV du jour, examens en attente)
- ✅ Mes consultations + nouvelles consultations
- ✅ Mes rendez-vous
- ✅ Dossiers patients
- ✅ Mon profil (modifier infos + changer mot de passe)
- ❌ Caisse / Facturation (accès limité)

### 🟡 Caissier (`caisse@csi.ne`)
- ✅ Tableau de bord
- ✅ Caisse : créer factures, reçus, situation journalière
- ✅ Patients (lecture)
- ✅ Rapports et statistiques financières
- ✅ Pharmacie (lecture)
- ❌ Consultations médicales

### 🔵 Assistant (`assistant@csi.ne`)
- ✅ Patients : créer, modifier, consulter
- ✅ Rendez-vous : créer, confirmer, annuler
- ✅ Consultations (lecture)
- ✅ Pharmacie (lecture)
- ❌ Caisse / Facturation
- ❌ Administration

---

## 📊 Données pré-chargées

Après l'importation SQL, vous trouverez :

### Patients (8 patients fictifs)
| Dossier | Nom | Particularité |
|---------|-----|---------------|
| CSI-2026-001 | Moussa Abdou | HTA, assuré SONUCI |
| CSI-2026-002 | Hassane Aïchatou | Allergie pénicilline |
| CSI-2026-003 | Issa Boubacar | Diabète type 2 |
| CSI-2026-004 | Mahamane Halima | Enfant (pédiatrie) |
| CSI-2026-005 | Oumarou Zeinabou | Asthme, allergie aspirine |
| CSI-2026-006 | Adamou Souleymane | Certificat médical |
| CSI-2026-007 | Ibrahim Fati | Assurée SONUCI |
| CSI-2026-008 | Yacouba Moustapha | Retraité, HTA+DT2 |

### Rendez-vous
- 4 RDV **aujourd'hui** (2 pour Dr Kollo, 2 pour Dr Maïga)
- 2 RDV **demain**
- 2 RDV **après-demain**

### Médicaments (12 produits)
Amoxicilline, Paracétamol, Ibuprofène, Amlodipine, Metformine, Salbutamol, Quinine, Artésunate, Sérum physiologique, Vitamine C, Cotrimoxazole, Furosémide

### Actes médicaux (18 actes)
Consultations, examens biologiques, examens d'imagerie, traitements

### Partenaires (5)
SONUCI, SUNU Assurances, Sanlam Vie Niger, Mutuelle Générale, Ministère de la Santé

---

## 🚀 Déploiement production (serveur Linux)

Si vous migrez plus tard vers un serveur Linux (non XAMPP), utilisez Docker :

```bash
# Copier .env.prod en .env.local et adapter
cp .env.prod .env.local

# Démarrer la stack complète
docker compose --profile prod up -d

# Application sur http://votre-serveur:80
```

---

## 📞 Support

- **GitHub** : https://github.com/NasserKailou/centre_ama
- **Email** : admin@csi.ne
- **Version** : 1.0.0 — Mars 2026
