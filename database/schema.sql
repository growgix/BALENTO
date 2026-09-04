-- =============================================================================
-- BALENTO - Database Schema
-- Target: MySQL 8.0+ / MariaDB 10.6+
-- Engine: InnoDB
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Table: categories
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_slug` (`slug`),
    KEY `idx_categories_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: products
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `tag` VARCHAR(50) NULL COMMENT 'e.g. Best Seller, Trending, Essential, Editor''s Pick, New',
    `price` DECIMAL(10, 2) NOT NULL,
    `compare_at_price` DECIMAL(10, 2) NULL,
    `description` TEXT NOT NULL,
    `dimensions` VARCHAR(100) NULL COMMENT 'e.g. 38cm (W) x 30cm (H) x 14cm (D)',
    `weight` VARCHAR(50) NULL COMMENT 'e.g. 680 grams',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_slug` (`slug`),
    KEY `idx_products_category_id` (`category_id`),
    KEY `idx_products_active_sort` (`is_active`, `sort_order`),
    KEY `idx_products_price` (`price`),
    CONSTRAINT `fk_products_category_id` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: product_features (Specs bullet highlights)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_features`;
CREATE TABLE `product_features` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `feature_text` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_features_product` (`product_id`, `sort_order`),
    CONSTRAINT `fk_product_features_product_id` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: product_variants (Color variations with individual inventory)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(60) NOT NULL,
    `color_name` VARCHAR(50) NOT NULL COMMENT 'e.g. Black, Cognac, Coffee Brown',
    `color_hex` VARCHAR(10) NOT NULL COMMENT 'e.g. #1c1b1b',
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_variants_sku` (`sku`),
    UNIQUE KEY `uk_product_color` (`product_id`, `color_name`),
    KEY `idx_product_variants_product` (`product_id`),
    KEY `idx_product_variants_active_stock` (`is_active`, `stock_quantity`),
    CONSTRAINT `fk_product_variants_product_id` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: product_images (Primary, hover, gallery sets)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL COMMENT 'Optional variant-specific image binding',
    `image_url` VARCHAR(500) NOT NULL,
    `alt_text` VARCHAR(255) NULL,
    `image_type` ENUM('primary', 'hover', 'gallery') NOT NULL DEFAULT 'gallery',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_images_product_type` (`product_id`, `image_type`, `sort_order`),
    KEY `idx_product_images_variant` (`variant_id`),
    CONSTRAINT `fk_product_images_product_id` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_images_variant_id` 
        FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: coupons
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10, 2) NOT NULL COMMENT 'Percentage (e.g. 10.00) or Flat Amount',
    `min_order_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `max_discount_cap` DECIMAL(10, 2) NULL COMMENT 'Max cap for percentage discount',
    `usage_limit` INT UNSIGNED NULL COMMENT 'Null = unlimited',
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_coupons_code` (`code`),
    KEY `idx_coupons_active_dates` (`is_active`, `starts_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: pincodes (Serviceability & Delivery Estimation)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pincodes`;
CREATE TABLE `pincodes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pincode` VARCHAR(6) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `is_serviceable` TINYINT(1) NOT NULL DEFAULT 1,
    `cod_available` TINYINT(1) NOT NULL DEFAULT 1,
    `estimated_days` INT UNSIGNED NOT NULL DEFAULT 3,
    `shipping_zone` VARCHAR(50) NOT NULL DEFAULT 'National',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pincodes_pincode` (`pincode`),
    KEY `idx_pincodes_serviceable` (`is_serviceable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: orders (Atomic checkout & financial snapshot)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(32) NOT NULL COMMENT 'Format: BAL-2026-XXXXXX',
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(6) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `discount_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `coupon_code` VARCHAR(50) NULL,
    `payment_method` ENUM('upi', 'card', 'cod') NOT NULL DEFAULT 'upi',
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `order_status` ENUM('placed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'placed',
    `is_gift` TINYINT(1) NOT NULL DEFAULT 0,
    `gift_note` VARCHAR(300) NULL,
    `idempotency_key` VARCHAR(64) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orders_order_number` (`order_number`),
    UNIQUE KEY `uk_orders_idempotency` (`idempotency_key`),
    KEY `idx_orders_customer_email` (`customer_email`),
    KEY `idx_orders_customer_phone` (`customer_phone`),
    KEY `idx_orders_status` (`order_status`),
    KEY `idx_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: order_items (Immutable historical snapshot of purchased products)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `variant_id` INT UNSIGNED NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `color_name` VARCHAR(50) NOT NULL,
    `sku` VARCHAR(60) NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_price` DECIMAL(10, 2) NOT NULL,
    `monogram_initials` VARCHAR(10) NULL COMMENT 'Max 3 chars (e.g. BM)',
    `monogram_foil` VARCHAR(20) NULL COMMENT 'gold, silver, blind',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order_id` (`order_id`),
    KEY `idx_order_items_product_id` (`product_id`),
    CONSTRAINT `fk_order_items_order_id` 
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product_id` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_variant_id` 
        FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table: newsletter_subscribers
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) NOT NULL,
    `source` VARCHAR(50) NOT NULL DEFAULT 'footer',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_newsletter_email` (`email`),
    KEY `idx_newsletter_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Table: lookbook_items (Street Style Editorial Content)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `lookbook_items`;
CREATE TABLE `lookbook_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `city_key` VARCHAR(50) NOT NULL COMMENT 'e.g. bengaluru, mumbai, delhi, goa',
    `city_title` VARCHAR(100) NOT NULL COMMENT 'e.g. Bengaluru • Koramangala',
    `person_name` VARCHAR(100) NOT NULL,
    `person_title` VARCHAR(100) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `fallback_url` VARCHAR(500) NULL,
    `quote` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_lookbook_city_key` (`city_key`),
    KEY `idx_lookbook_active_sort` (`is_active`, `sort_order`),
    KEY `idx_lookbook_product` (`product_id`),
    CONSTRAINT `fk_lookbook_product_id` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. Table: admins (Authentication & Authorization)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(60) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(30) NOT NULL DEFAULT 'admin' COMMENT 'admin, manager, staff',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_username` (`username`),
    UNIQUE KEY `uk_admins_email` (`email`),
    KEY `idx_admins_active_role` (`is_active`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Table: audit_logs (Administrative Action & Security Tracking)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
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

SET FOREIGN_KEY_CHECKS = 1;
