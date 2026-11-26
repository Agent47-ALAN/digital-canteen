-- =====================================================================
-- Digital Canteen - Phase 5.1 migration (notification foundation)
-- =====================================================================
-- Import this AFTER canteen.sql and phase3_orders.sql, from the phpMyAdmin
-- Import tab with the digital_canteen database selected.
-- Do NOT re-import canteen.sql. Safe to run more than once.
--
-- It only extends the EXISTING `notifications` table (no new table):
--
--   event_type : which event caused it, e.g. ORDER_PLACED, ORDER_READY.
--   title      : short heading shown in the bell list and push messages.
--   event_key  : a label for "this event, for this order" such as
--                'order:42:ORDER_READY'. Together with user_id it is UNIQUE,
--                so the same event can never notify the same person twice.
--   index (user_id, id) : makes "newest notifications of this user" fast.
--
-- The table is empty today, and the new columns have defaults, so nothing
-- existing can break. Nothing is dropped, deleted or reset.
--
-- (`push_subscriptions` is left alone until the push step, Phase 5.4.)
-- =====================================================================

SET NAMES utf8mb4;
USE digital_canteen;

-- Add the columns only if they are not there yet. (ADD COLUMN IF NOT EXISTS is not
-- available on every MySQL version, so the check goes through information_schema.)
SET @has_event_key := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'event_key'
);
SET @ddl := IF(
    @has_event_key = 0,
    'ALTER TABLE notifications
        ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT ''GENERAL'' AFTER order_id,
        ADD COLUMN title VARCHAR(120) NOT NULL DEFAULT '''' AFTER event_type,
        ADD COLUMN event_key VARCHAR(100) NULL AFTER title,
        ADD UNIQUE KEY uq_notifications_event (user_id, event_key),
        ADD KEY idx_notifications_user_id (user_id, id)',
    'SELECT ''notifications already migrated'' AS info'
);
PREPARE phase5_stmt FROM @ddl;
EXECUTE phase5_stmt;
DEALLOCATE PREPARE phase5_stmt;
