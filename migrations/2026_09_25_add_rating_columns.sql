-- Migration: add rating columns to products and sellers
-- Adds `rating` numeric columns if they do not already exist.

ALTER TABLE `products`
ADD COLUMN IF NOT EXISTS `rating` DOUBLE NOT NULL DEFAULT 0.0 AFTER `views`;

ALTER TABLE `sellers`
ADD COLUMN IF NOT EXISTS `rating` DOUBLE NOT NULL DEFAULT 0.0 AFTER `is_verified`;

-- Note: MySQL < 8.0 does not support IF NOT EXISTS for ADD COLUMN; run safely by checking before executing or use a quick script.
-- Example safe check:
-- SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating');
-- IF @has_col = 0 THEN ALTER TABLE products ADD COLUMN rating DOUBLE NOT NULL DEFAULT 0.0; END IF;
