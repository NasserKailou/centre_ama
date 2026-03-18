-- ============================================================
-- Migration 0001 : Ajout colonnes manquantes
-- ============================================================
-- À exécuter après la migration initiale (Version20260318000000.sql)
-- Compatible MySQL/MariaDB (XAMPP)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─── facture_globale : colonnes manquantes ─────────────────
ALTER TABLE `facture_globale`
    ADD COLUMN IF NOT EXISTS `montant_recu`    DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `mode_paiement`,
    ADD COLUMN IF NOT EXISTS `monnaie_rendue`  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `montant_recu`,
    ADD COLUMN IF NOT EXISTS `date_paiement`   DATETIME          NULL              AFTER `monnaie_rendue`;

-- ─── prescription_examen : colonnes manquantes ────────────
ALTER TABLE `prescription_examen`
    ADD COLUMN IF NOT EXISTS `acte_medical_id` INT UNSIGNED  NULL AFTER `consultation_id`,
    ADD COLUMN IF NOT EXISTS `notes`           TEXT          NULL AFTER `observations`;

-- Ajouter la contrainte FK si elle n'existe pas
-- (MariaDB ne supporte pas ADD CONSTRAINT IF NOT EXISTS directement)
-- On le fait via procédure pour éviter les erreurs
DROP PROCEDURE IF EXISTS csi_add_fk_exam_acte;
DELIMITER //
CREATE PROCEDURE csi_add_fk_exam_acte()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'prescription_examen'
          AND CONSTRAINT_NAME = 'fk_exam_acte'
    ) THEN
        ALTER TABLE `prescription_examen`
            ADD CONSTRAINT `fk_exam_acte`
            FOREIGN KEY (`acte_medical_id`)
            REFERENCES `acte_medical`(`id`)
            ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL csi_add_fk_exam_acte();
DROP PROCEDURE IF EXISTS csi_add_fk_exam_acte;

SET FOREIGN_KEY_CHECKS = 1;
