-- added.sql
-- Schema changes applied to db_skyline.
-- Apply: phpMyAdmin -> db_skyline -> SQL tab -> paste and run, or
--        mysql -u root db_skyline < added.sql
--
-- Safe to run more than once. Each change is applied once and recorded, so re-running does not
-- re-apply a data backfill over rows created since.


-- Records which changes have been applied. Without this a re-run cannot tell "the column was
-- just added, so backfill" from "the column was already there, so leave the data alone".

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `migration`  VARCHAR(100) NOT NULL,
  `applied_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- 2026-10-03  expenses_type.approval_required
-- The "CEO approval required" radio on the Add and Edit Expense Type forms.
-- DEFAULT 0 so existing rows keep their current behaviour.
-- ---------------------------------------------------------------------------

SET @ddl := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `expenses_type` ADD COLUMN `approval_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses_type' AND COLUMN_NAME = 'approval_required'
);
PREPARE ocp_stmt FROM @ddl; EXECUTE ocp_stmt; DEALLOCATE PREPARE ocp_stmt;


-- ---------------------------------------------------------------------------
-- 2026-10-03  expenses approval signatures
-- An expense whose type requires approval is signed by three roles, in order:
--   reviewed_by      Admin + Admin dept + Accounting
--   prepared_by      Admin + Admin dept + CEO
--   acknowledged_by  Admin + Admin dept + CEO
-- approval_status is 'not_required' | 'pending' | 'partially_signed' | 'approved'.
-- Each signature is the pad's data URL, as gasoline_purchase_orders stores its own.
-- ---------------------------------------------------------------------------

SET @ddl := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `expenses`
           ADD COLUMN `approval_status`        VARCHAR(20) NOT NULL DEFAULT ''not_required'' AFTER `person_name`,
           ADD COLUMN `reviewed_by`            INT          NULL AFTER `approval_status`,
           ADD COLUMN `reviewed_signature`     LONGTEXT     NULL AFTER `reviewed_by`,
           ADD COLUMN `reviewed_at`            DATETIME     NULL AFTER `reviewed_signature`,
           ADD COLUMN `prepared_by`            INT          NULL AFTER `reviewed_at`,
           ADD COLUMN `prepared_signature`     LONGTEXT     NULL AFTER `prepared_by`,
           ADD COLUMN `prepared_at`            DATETIME     NULL AFTER `prepared_signature`,
           ADD COLUMN `acknowledged_by`        INT          NULL AFTER `prepared_at`,
           ADD COLUMN `acknowledged_signature` LONGTEXT     NULL AFTER `acknowledged_by`,
           ADD COLUMN `acknowledged_at`        DATETIME     NULL AFTER `acknowledged_signature`',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'approval_status'
);
PREPARE ocp_stmt FROM @ddl; EXECUTE ocp_stmt; DEALLOCATE PREPARE ocp_stmt;


-- One-time data steps, guarded by the marker so they never run twice.

SET @ddl := (
    SELECT IF(COUNT(*) = 0,
        -- Expenses recorded before this change were never asked for signatures, so their existing
        -- behaviour is kept by marking them approved. New rows cannot be caught by this: it is
        -- recorded as applied the first time it runs.
        'UPDATE `expenses` SET `approval_status` = ''approved''
          WHERE `expense_type_id` IN (SELECT `id` FROM `expenses_type` WHERE `approval_required` = 1)',
        'DO 0')
    FROM `schema_migrations` WHERE `migration` = '2026-10-03-expenses-backfill-approved'
);
PREPARE ocp_stmt FROM @ddl; EXECUTE ocp_stmt; DEALLOCATE PREPARE ocp_stmt;

SET @ddl := (
    SELECT IF(COUNT(*) = 0,
        -- Expenses on an approval-required type that were recorded since the columns appeared.
        'UPDATE `expenses` e JOIN `expenses_type` t ON t.id = e.expense_type_id
            SET e.approval_status = ''pending''
          WHERE t.approval_required = 1 AND e.approval_status = ''not_required''',
        'DO 0')
    FROM `schema_migrations` WHERE `migration` = '2026-10-03-expenses-backfill-pending'
);
PREPARE ocp_stmt FROM @ddl; EXECUTE ocp_stmt; DEALLOCATE PREPARE ocp_stmt;

INSERT IGNORE INTO `schema_migrations` (`migration`) VALUES
    ('2026-10-03-expenses_type-approval_required'),
    ('2026-10-03-expenses-approval-columns'),
    ('2026-10-03-expenses-backfill-approved'),
    ('2026-10-03-expenses-backfill-pending');
