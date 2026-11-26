-- =====================================================================
-- CampusBite (Digital Canteen) - Phase 6 migration (Order Prep Timer)
-- =====================================================================
-- Adds server-side estimated preparation time fields to `orders`.
-- Safe to run more than once.
-- =====================================================================

SET NAMES utf8mb4;
USE digital_canteen;

SET @has_prep_minutes := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'estimated_prep_minutes'
);
SET @ddl_prep_minutes := IF(
    @has_prep_minutes = 0,
    'ALTER TABLE orders ADD COLUMN estimated_prep_minutes INT UNSIGNED NULL DEFAULT NULL AFTER notes',
    'SELECT ''orders.estimated_prep_minutes already exists'' AS info'
);
PREPARE phase6_stmt1 FROM @ddl_prep_minutes;
EXECUTE phase6_stmt1;
DEALLOCATE PREPARE phase6_stmt1;

SET @has_ready_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'estimated_ready_at'
);
SET @ddl_ready_at := IF(
    @has_ready_at = 0,
    'ALTER TABLE orders ADD COLUMN estimated_ready_at TIMESTAMP NULL DEFAULT NULL AFTER estimated_prep_minutes',
    'SELECT ''orders.estimated_ready_at already exists'' AS info'
);
PREPARE phase6_stmt2 FROM @ddl_ready_at;
EXECUTE phase6_stmt2;
DEALLOCATE PREPARE phase6_stmt2;
