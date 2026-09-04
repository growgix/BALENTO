-- =============================================================================
-- BALENTO Migration 001: Create Audit Logs Table
-- Engine: InnoDB, Charset: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NULL,
    `admin_username` VARCHAR(60) NOT NULL,
    `action` VARCHAR(100) NOT NULL COMMENT 'e.g. create_product, update_stock, update_order_status',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'product, order, inventory, coupon, category, pincode, user',
    `entity_id` VARCHAR(50) NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_admin` (`admin_id`),
    KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
