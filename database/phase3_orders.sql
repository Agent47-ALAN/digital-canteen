-- =====================================================================
-- Digital Canteen - Phase 3 migration (cart, checkout, orders, payments)
-- =====================================================================
-- Import this AFTER database/canteen.sql, from the phpMyAdmin Import tab
-- (select the digital_canteen database first, or leave the USE line below).
--
-- WHY THIS FILE DOES NOT CREATE orders / order_items / payments:
--   The Phase 1 schema (canteen.sql) already created those tables, plus a
--   separate `tokens` table. Phase 3 reuses them, so existing data and the
--   Phase 1 design stay intact. Re-creating them would silently do nothing
--   (CREATE TABLE IF NOT EXISTS) and leave the code and tables out of step.
--
-- What this migration changes (nothing is dropped, deleted or reset):
--   1. payments.method   : adds the value DEMO_ONLINE to the ENUM.
--                          The old value DEMO is kept, so existing rows stay valid.
--   2. orders.checkout_token : new nullable column with a UNIQUE index. It makes
--                          a repeated or refreshed checkout return the same order
--                          instead of creating a duplicate.
--
-- Safe to run more than once.
--
-- Table mapping used by Phase 3 (existing column names are kept):
--   orders      : user_id (customer), status, total_amount, placed_at, updated_at
--   order_items : item_name + unit_price snapshot, quantity, line_total
--   payments    : method (CASH | DEMO_ONLINE), status (PENDING | PAID), amount
--   tokens      : token_number (UNIQUE), one row per order (UNIQUE order_id)
-- =====================================================================

SET NAMES utf8mb4;
USE digital_canteen;

-- 1. Allow the DEMO_ONLINE payment method (re-running this is harmless).
ALTER TABLE payments
    MODIFY COLUMN method ENUM('CASH', 'DEMO', 'DEMO_ONLINE') NOT NULL;

-- 2. Add orders.checkout_token only if it is not there yet.
--    (Plain "ADD COLUMN IF NOT EXISTS" is not available on every MySQL version,
--     so the check is done through information_schema.)
SET @has_checkout_token := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'checkout_token'
);
SET @ddl := IF(
    @has_checkout_token = 0,
    'ALTER TABLE orders ADD COLUMN checkout_token CHAR(32) NULL AFTER notes, ADD UNIQUE KEY uq_orders_checkout_token (checkout_token)',
    'SELECT ''orders.checkout_token already exists'' AS info'
);
PREPARE phase3_stmt FROM @ddl;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;
