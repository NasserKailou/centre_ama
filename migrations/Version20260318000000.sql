-- ============================================================
-- CSI - Centre de Santé Intégré
-- Migration SQL complète v1.0.0
-- MySQL 8.0 — Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── TABLE user ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user` (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    email           VARCHAR(180)    NOT NULL,
    roles           JSON            NOT NULL,
    password        VARCHAR(255)    NOT NULL,
    nom             VARCHAR(100)    NOT NULL,
    prenom          VARCHAR(100)    NOT NULL,
    telephone       VARCHAR(20)         NULL,
    specialite      VARCHAR(100)        NULL,
    actif           TINYINT(1)      NOT NULL DEFAULT 1,
    last_login      DATETIME            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE partenaire ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS partenaire (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nom                 VARCHAR(200)    NOT NULL,
    type                VARCHAR(50)     NOT NULL DEFAULT 'assurance',
    adresse             TEXT                NULL,
    telephone           VARCHAR(20)         NULL,
    email               VARCHAR(150)        NULL,
    contact             VARCHAR(150)        NULL,
    numero_contrat      VARCHAR(100)        NULL,
    taux_prise_en_charge DECIMAL(5,2)  NOT NULL DEFAULT 80.00,
    description         TEXT                NULL,
    actif               TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE patient ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS patient (
    id                          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    numero_dossier              VARCHAR(30)     NOT NULL,
    nom                         VARCHAR(100)    NOT NULL,
    prenom                      VARCHAR(100)    NOT NULL,
    telephone                   VARCHAR(20)     NOT NULL,
    date_naissance              DATE                NULL,
    sexe                        VARCHAR(10)         NULL,
    groupe_sanguin              VARCHAR(5)          NULL,
    adresse                     VARCHAR(200)        NULL,
    contact_urgence             VARCHAR(200)        NULL,
    allergies                   TEXT                NULL,
    antecedents_medicaux        TEXT                NULL,
    antecedents_chirurgicaux    TEXT                NULL,
    antecedents_familiaux       TEXT                NULL,
    profession                  VARCHAR(100)        NULL,
    assurance                   VARCHAR(50)         NULL,
    numero_assurance            VARCHAR(100)        NULL,
    partenaire_id               INT UNSIGNED        NULL,
    created_at                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_patient_dossier (numero_dossier),
    KEY idx_patient_telephone (telephone),
    KEY idx_patient_nom (nom),
    CONSTRAINT fk_patient_partenaire FOREIGN KEY (partenaire_id)
        REFERENCES partenaire(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE rendez_vous ────────────────────────────────────
CREATE TABLE IF NOT EXISTS rendez_vous (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    patient_id  INT UNSIGNED    NOT NULL,
    medecin_id  INT UNSIGNED    NOT NULL,
    date_heure  DATETIME        NOT NULL,
    motif       TEXT            NOT NULL,
    statut      VARCHAR(20)     NOT NULL DEFAULT 'planifie',
    duree       INT                 NULL DEFAULT 30,
    notes       TEXT                NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rdv_date (date_heure),
    KEY idx_rdv_patient (patient_id),
    KEY idx_rdv_medecin (medecin_id),
    CONSTRAINT fk_rdv_patient FOREIGN KEY (patient_id)
        REFERENCES patient(id) ON DELETE CASCADE,
    CONSTRAINT fk_rdv_medecin FOREIGN KEY (medecin_id)
        REFERENCES `user`(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE consultation ───────────────────────────────────
CREATE TABLE IF NOT EXISTS consultation (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    patient_id          INT UNSIGNED    NOT NULL,
    medecin_id          INT UNSIGNED    NOT NULL,
    rendez_vous_id      INT UNSIGNED        NULL,
    date_heure          DATETIME        NOT NULL,
    motif               TEXT            NOT NULL,
    anamnese            TEXT                NULL,
    examen_clinique     TEXT                NULL,
    diagnostic          TEXT                NULL,
    traitement          TEXT                NULL,
    observations        TEXT                NULL,
    tension             VARCHAR(20)         NULL,
    temperature         DECIMAL(4,1)        NULL,
    poids               DECIMAL(5,2)        NULL,
    taille              DECIMAL(5,2)        NULL,
    frequence_cardiaque INT                 NULL,
    statut              VARCHAR(20)     NOT NULL DEFAULT 'planifiee',
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consult_date (date_heure),
    KEY idx_consult_patient (patient_id),
    KEY idx_consult_medecin (medecin_id),
    CONSTRAINT fk_consult_patient   FOREIGN KEY (patient_id)      REFERENCES patient(id)    ON DELETE CASCADE,
    CONSTRAINT fk_consult_medecin   FOREIGN KEY (medecin_id)      REFERENCES `user`(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_consult_rdv       FOREIGN KEY (rendez_vous_id)  REFERENCES rendez_vous(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE prescription_examen ────────────────────────────
CREATE TABLE IF NOT EXISTS prescription_examen (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    consultation_id     INT UNSIGNED    NOT NULL,
    type_examen         VARCHAR(200)    NOT NULL,
    instructions        TEXT                NULL,
    resultat            TEXT                NULL,
    valeurs_reference   TEXT                NULL,
    observations        TEXT                NULL,
    statut              VARCHAR(20)     NOT NULL DEFAULT 'prescrit',
    date_resultat       DATETIME            NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exam_consult (consultation_id),
    CONSTRAINT fk_exam_consultation FOREIGN KEY (consultation_id)
        REFERENCES consultation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE acte_medical ───────────────────────────────────
CREATE TABLE IF NOT EXISTS acte_medical (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    designation         VARCHAR(200)    NOT NULL,
    code                VARCHAR(50)         NULL,
    categorie           VARCHAR(50)         NULL,
    prix_normal         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    prix_pris_en_charge DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    actif               TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_acte_designation (designation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE produit_pharmaceutique ─────────────────────────
CREATE TABLE IF NOT EXISTS produit_pharmaceutique (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    designation         VARCHAR(200)    NOT NULL,
    dci                 VARCHAR(100)        NULL,
    reference           VARCHAR(50)         NULL,
    categorie           VARCHAR(50)         NULL,
    forme               VARCHAR(50)         NULL,
    dosage              VARCHAR(50)         NULL,
    unite               VARCHAR(20)         NULL,
    prix_achat          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    prix_vente          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    stock_disponible    INT             NOT NULL DEFAULT 0,
    stock_minimum       INT             NOT NULL DEFAULT 10,
    fournisseur         VARCHAR(100)        NULL,
    date_peremption     DATE                NULL,
    actif               TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_produit_designation (designation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE mouvement_stock ────────────────────────────────
CREATE TABLE IF NOT EXISTS mouvement_stock (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    produit_id  INT UNSIGNED    NOT NULL,
    type        VARCHAR(20)     NOT NULL,
    quantite    INT             NOT NULL,
    stock_apres INT             NOT NULL,
    reference   VARCHAR(100)        NULL,
    notes       TEXT                NULL,
    user_id     INT UNSIGNED        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mouv_produit (produit_id),
    KEY idx_mouv_date (created_at),
    CONSTRAINT fk_mouv_produit FOREIGN KEY (produit_id) REFERENCES produit_pharmaceutique(id) ON DELETE CASCADE,
    CONSTRAINT fk_mouv_user   FOREIGN KEY (user_id)    REFERENCES `user`(id)                 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE facture_globale ────────────────────────────────
CREATE TABLE IF NOT EXISTS facture_globale (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    numero              VARCHAR(30)     NOT NULL,
    patient_id          INT UNSIGNED    NOT NULL,
    caissier_id         INT UNSIGNED    NOT NULL,
    consultation_id     INT UNSIGNED        NULL,
    partenaire_id       INT UNSIGNED        NULL,
    montant_total       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    montant_actes       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    montant_pharmacie   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    taux_assurance      DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    part_assurance      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    part_patient        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    statut              VARCHAR(20)     NOT NULL DEFAULT 'paye',
    statut_assurance    VARCHAR(20)         NULL DEFAULT 'en_attente',
    mode_paiement       VARCHAR(20)     NOT NULL DEFAULT 'cash',
    notes               TEXT                NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_facture_numero (numero),
    KEY idx_facture_date (created_at),
    KEY idx_facture_patient (patient_id),
    KEY idx_facture_statut (statut),
    CONSTRAINT fk_facture_patient     FOREIGN KEY (patient_id)      REFERENCES patient(id)       ON DELETE RESTRICT,
    CONSTRAINT fk_facture_caissier    FOREIGN KEY (caissier_id)     REFERENCES `user`(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_facture_consult     FOREIGN KEY (consultation_id) REFERENCES consultation(id)  ON DELETE SET NULL,
    CONSTRAINT fk_facture_partenaire  FOREIGN KEY (partenaire_id)   REFERENCES partenaire(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE ligne_facture ──────────────────────────────────
CREATE TABLE IF NOT EXISTS ligne_facture (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    facture_globale_id  INT UNSIGNED    NOT NULL,
    type_ligne          VARCHAR(20)     NOT NULL DEFAULT 'acte',
    acte_medical_id     INT UNSIGNED        NULL,
    produit_id          INT UNSIGNED        NULL,
    designation         VARCHAR(255)    NOT NULL,
    quantite            INT             NOT NULL DEFAULT 1,
    prix_unitaire       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    sous_total          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_ligne_facture (facture_globale_id),
    CONSTRAINT fk_ligne_facture     FOREIGN KEY (facture_globale_id)
        REFERENCES facture_globale(id) ON DELETE CASCADE,
    CONSTRAINT fk_ligne_acte        FOREIGN KEY (acte_medical_id)
        REFERENCES acte_medical(id)   ON DELETE SET NULL,
    CONSTRAINT fk_ligne_produit     FOREIGN KEY (produit_id)
        REFERENCES produit_pharmaceutique(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABLE migration_versions (Doctrine) ─────────────────
CREATE TABLE IF NOT EXISTS doctrine_migration_versions (
    version         VARCHAR(191)    NOT NULL,
    executed_at     DATETIME            NULL,
    execution_time  INT                 NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- ============================================================
-- UTILISATEURS DE TEST
-- ============================================================
-- Les mots de passe utilisent bcrypt ($2y$ compatible PHP/Symfony)
-- $2b$ (Python) = $2y$ (PHP) : compatibles à 100%
-- ============================================================

-- 1. Administrateur (mdp: Admin@2024)
INSERT IGNORE INTO `user` (email, roles, password, nom, prenom, telephone, specialite, actif) VALUES
(
    'admin@csi.ne',
    '["ROLE_ADMIN"]',
    '$2b$12$qMaRQsMD1GcxzHORBS4I8uyyOWIT/RYBG79r02WwYgqoJPkJdcxiC',
    'Système',
    'Admin',
    '+227 20 00 00 00',
    NULL,
    1
);

-- 2. Médecin généraliste (mdp: Medecin@2024)
INSERT IGNORE INTO `user` (email, roles, password, nom, prenom, telephone, specialite, actif) VALUES
(
    'dr.kollo@csi.ne',
    '["ROLE_MEDECIN"]',
    '$2b$12$gtj5fc9yquWvqsthxuwqiODkFbu44Fr3zrASm8TeF.waB7W93A4WS',
    'Kollo',
    'Ibrahim',
    '+227 96 11 22 33',
    'Médecine générale',
    1
);

-- 3. Médecin spécialiste (mdp: Medecin@2024)
INSERT IGNORE INTO `user` (email, roles, password, nom, prenom, telephone, specialite, actif) VALUES
(
    'dr.maiga@csi.ne',
    '["ROLE_MEDECIN"]',
    '$2b$12$gtj5fc9yquWvqsthxuwqiODkFbu44Fr3zrASm8TeF.waB7W93A4WS',
    'Maïga',
    'Fatouma',
    '+227 97 44 55 66',
    'Pédiatrie',
    1
);

-- 4. Caissier (mdp: Caissier@2024)
INSERT IGNORE INTO `user` (email, roles, password, nom, prenom, telephone, specialite, actif) VALUES
(
    'caisse@csi.ne',
    '["ROLE_CAISSIER"]',
    '$2b$12$BYnG.0Ed7mFZb/TN9.ITAOHx8ZsZ1.FDiq5lZ.z514CjEPGzbybyS',
    'Hamidou',
    'Ramatou',
    '+227 90 77 88 99',
    NULL,
    1
);

-- 5. Assistant(e) médical(e) (mdp: Assistant@2024)
INSERT IGNORE INTO `user` (email, roles, password, nom, prenom, telephone, specialite, actif) VALUES
(
    'assistant@csi.ne',
    '["ROLE_ASSISTANT"]',
    '$2b$12$XLXhJsRpE3RYQZ79gF9fM.kseVOb3jplpbasUDrr3NQeGf6vgOAJ.',
    'Issoufou',
    'Mariama',
    '+227 99 12 34 56',
    NULL,
    1
);

-- Actes médicaux de base
INSERT IGNORE INTO acte_medical (designation, code, categorie, prix_normal, prix_pris_en_charge, actif) VALUES
('Consultation générale',           'CONS-001', 'consultation',  5000,  7000, 1),
('Consultation spécialisée',        'CONS-002', 'consultation',  8000, 10000, 1),
('Consultation pédiatrique',        'CONS-003', 'consultation',  5000,  7000, 1),
('Consultation gynécologique',      'CONS-004', 'consultation',  8000, 10000, 1),
('Numération Formule Sanguine',     'EXAM-001', 'examen',        3500,  4500, 1),
('Glycémie à jeun',                 'EXAM-002', 'examen',        1500,  2000, 1),
('Radiographie thoracique',         'EXAM-003', 'examen',        5000,  7000, 1),
('Échographie abdominale',          'EXAM-004', 'examen',       10000, 13000, 1),
('ECG',                             'EXAM-005', 'examen',        5000,  7000, 1),
('Sérologie paludisme (TDR)',       'EXAM-006', 'examen',        2000,  2500, 1),
('Bilan hépatique complet',         'EXAM-007', 'examen',        8000, 10000, 1),
('ECBU',                            'EXAM-008', 'examen',        3000,  4000, 1),
('Consultation urgence',            'CONS-005', 'consultation',  7500, 10000, 1),
('Pose perfusion',                  'TRAI-001', 'traitement',    2500,  3500, 1),
('Injection intramusculaire',       'TRAI-002', 'traitement',    1000,  1500, 1),
('Pansement simple',                'TRAI-003', 'traitement',    1500,  2000, 1),
('Suture plaie',                    'TRAI-004', 'traitement',    5000,  7000, 1),
('Hospitalisation / jour',          'HOSP-001', 'traitement',   10000, 15000, 1);

-- Partenaires par défaut
INSERT IGNORE INTO partenaire (nom, type, taux_prise_en_charge, actif) VALUES
('SONUCI - Assurance',          'assurance',  80, 1),
('SUNU Assurances',             'assurance',  80, 1),
('Sanlam Vie Niger',            'assurance',  75, 1),
('Mutuelle Générale',           'mutuelle',   70, 1),
('Ministère de la Santé',       'etat',       100, 1);

-- ============================================================
-- DONNÉES DE DÉMONSTRATION
-- Patients, consultations et RDV fictifs pour tester l'appli
-- ============================================================

-- Patients de démonstration
INSERT IGNORE INTO patient (numero_dossier, nom, prenom, telephone, date_naissance, sexe, groupe_sanguin, adresse, allergies, antecedents_medicaux, profession, partenaire_id, created_at) VALUES
('CSI-2026-001', 'Moussa',    'Abdou',      '+227 96 01 01 01', '1985-03-15', 'M', 'O+',  'Niamey - Plateau',      NULL,               'Hypertension artérielle', 'Fonctionnaire',  1, '2026-01-10 08:00:00'),
('CSI-2026-002', 'Hassane',   'Aïchatou',   '+227 97 02 02 02', '1992-07-22', 'F', 'A+',  'Niamey - Yantala',      'Pénicilline',      NULL,                      'Commerçante',    2, '2026-01-15 09:30:00'),
('CSI-2026-003', 'Issa',      'Boubacar',   '+227 99 03 03 03', '1978-11-05', 'M', 'B+',  'Niamey - Talladjé',     NULL,               'Diabète type 2',          'Enseignant',     NULL, '2026-01-20 10:00:00'),
('CSI-2026-004', 'Mahamane',  'Halima',     '+227 90 04 04 04', '2018-04-10', 'F', 'O-',  'Niamey - Koira Kano',   NULL,               NULL,                      NULL,             NULL, '2026-02-01 08:30:00'),
('CSI-2026-005', 'Oumarou',   'Zeinabou',   '+227 96 05 05 05', '1965-09-30', 'F', 'AB+', 'Tillabéri',             'Aspirine',         'Asthme bronchique',       'Agricultrice',   3, '2026-02-05 14:00:00'),
('CSI-2026-006', 'Adamou',    'Souleymane', '+227 97 06 06 06', '1990-12-18', 'M', 'A-',  'Niamey - Dar Es Salam', NULL,               NULL,                      'Chauffeur',      NULL, '2026-02-10 11:00:00'),
('CSI-2026-007', 'Ibrahim',   'Fati',       '+227 99 07 07 07', '2001-06-25', 'F', 'B-',  'Niamey - Gamkalé',      NULL,               NULL,                      'Étudiante',      1, '2026-02-15 09:00:00'),
('CSI-2026-008', 'Yacouba',   'Moustapha',  '+227 90 08 08 08', '1955-02-14', 'M', 'O+',  'Dosso',                 NULL,               'HTA, Diabète type 2',     'Retraité',       4, '2026-03-01 07:30:00');

-- Rendez-vous de démonstration (médecin ID=2 = Dr Kollo, médecin ID=3 = Dr Maïga)
INSERT IGNORE INTO rendez_vous (patient_id, medecin_id, date_heure, motif, statut, duree, notes, created_at) VALUES
(1, 2, DATE_ADD(CURDATE(), INTERVAL 0 DAY) + INTERVAL 8 HOUR,  'Contrôle tension artérielle',         'confirme',  30, NULL,                   NOW()),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 0 DAY) + INTERVAL 9 HOUR,  'Consultation générale - fièvre',      'planifie',  30, NULL,                   NOW()),
(4, 3, DATE_ADD(CURDATE(), INTERVAL 0 DAY) + INTERVAL 10 HOUR, 'Consultation pédiatrique - toux',     'planifie',  20, 'Enfant 7 ans',         NOW()),
(5, 3, DATE_ADD(CURDATE(), INTERVAL 0 DAY) + INTERVAL 11 HOUR, 'Suivi asthme',                        'confirme',  30, NULL,                   NOW()),
(3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 8 HOUR,  'Suivi diabète - bilan trimestriel',   'planifie',  45, 'Apporter résultats',   NOW()),
(6, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 9 HOUR,  'Certificat médical visite d\'emploi', 'planifie',  20, NULL,                   NOW()),
(7, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR,  'Consultation gynécologique',          'planifie',  30, NULL,                   NOW()),
(8, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 10 HOUR, 'Consultation urgence - douleurs',     'planifie',  30, 'Patient âgé, HTA+DT2', NOW());

-- Produits pharmaceutiques de démonstration
INSERT IGNORE INTO produit_pharmaceutique (designation, dci, reference, categorie, forme, dosage, unite, prix_achat, prix_vente, stock_disponible, stock_minimum, fournisseur, actif, created_at) VALUES
('Amoxicilline 500mg',          'Amoxicilline',     'AMX-500',  'antibiotique',      'gelule',    '500 mg',    'boite',   1200,  2500,  150,  20, 'LABOREX Niger',  1, NOW()),
('Paracétamol 500mg',           'Paracétamol',      'PARA-500', 'analgesique',       'comprime',  '500 mg',    'boite',    400,   900,  300,  30, 'LABOREX Niger',  1, NOW()),
('Ibuprofène 400mg',            'Ibuprofène',       'IBU-400',  'anti-inflammatoire','comprime',  '400 mg',    'boite',    700,  1500,   80,  15, 'COPHARNI',       1, NOW()),
('Amlodipine 10mg',             'Amlodipine',       'AML-10',   'antihypertenseur',  'comprime',  '10 mg',     'boite',    900,  2000,   60,  10, 'COPHARNI',       1, NOW()),
('Metformine 850mg',            'Metformine',       'MET-850',  'antidiabetique',    'comprime',  '850 mg',    'boite',    800,  1800,   90,  15, 'LABOREX Niger',  1, NOW()),
('Salbutamol spray',            'Salbutamol',       'SAL-INH',  'anti-inflammatoire','inhalateur','100µg/dose','flacon',  2500,  6000,   25,   5, 'COPHARNI',       1, NOW()),
('Quinine 500mg Inj.',          'Quinine',          'QUI-INJ',  'antiparasitaire',   'injectable','500 mg/2ml','ampoule',  350,   800,  200,  30, 'LABOREX Niger',  1, NOW()),
('Artésunate 200mg',            'Artésunate',       'ART-200',  'antiparasitaire',   'comprime',  '200 mg',    'boite',   1500,  3500,   75,  15, 'LABOREX Niger',  1, NOW()),
('Sérum physiologique 500ml',   'NaCl 0.9%',        'NaCl-500', 'solution_injectable','solution', '0.9%',      'flacon',   400,   900,  100,  20, 'COPHARNI',       1, NOW()),
('Vitamine C 500mg',            'Acide ascorbique', 'VIT-C',    'vitamines',         'comprime',  '500 mg',    'boite',    300,   700,  120,  20, 'LABOREX Niger',  1, NOW()),
('Cotrimoxazole 480mg',         'Cotrimoxazole',    'CTX-480',  'antibiotique',      'comprime',  '480 mg',    'boite',    600,  1200,  100,  20, 'COPHARNI',       1, NOW()),
('Furosémide 40mg',             'Furosémide',       'FUR-40',   'antihypertenseur',  'comprime',  '40 mg',     'boite',    400,   900,   45,  10, 'LABOREX Niger',  1, NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DE LA MIGRATION CSI v1.0.0
-- ============================================================
