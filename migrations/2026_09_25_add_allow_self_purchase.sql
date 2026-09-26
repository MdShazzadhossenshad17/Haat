-- Migration: add allow_self_purchase to sellers table
-- Run this once on your production/staging database. Non-destructive: adds a new column with default 0.

ALTER TABLE `sellers`
ADD COLUMN `allow_self_purchase` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;

-- Optional: backfill from existing logic or set specific seller IDs to allow self-purchase
-- UPDATE `sellers` SET `allow_self_purchase` = 1 WHERE `user_id` IN (1);
