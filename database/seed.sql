-- =============================================================================
-- BALENTO - Initial Database Seed Data
-- Target: MySQL 8.0+ / MariaDB 10.6+
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Categories
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `categories`;
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Totes', 'tote', 'Spacious architectural totes engineered with padded laptop sleeves and reinforced handles.', 1, 1),
(2, 'Shoulder Bags', 'shoulder', 'Sculptural crescent shoulder silhouettes with fluid contours and magnetic closures.', 2, 1),
(3, 'Crossbody Bags', 'crossbody', 'Hands-free compact daily essentials with card organizers and adjustable straps.', 3, 1),
(4, 'Hobo Bags', 'hobo', 'Relaxed slouch silhouettes crafted from ultra-supple full-grain nappa leather.', 4, 1),
(5, 'Structured Bags', 'structured', 'Architectural top-handle bags with accordion gussets and protective brass feet.', 5, 1);

-- -----------------------------------------------------------------------------
-- 2. Products
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `products`;
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `tag`, `price`, `compare_at_price`, `description`, `dimensions`, `weight`, `is_active`, `sort_order`) VALUES
(1, 1, 'Verona Tote', 'verona-tote', 'Best Seller', 2499.00, 2999.00, 'Spacious architectural tote with a padded 14-inch laptop compartment, key leash, and reinforced leather shoulder drop.', '38cm (W) × 30cm (H) × 14cm (D)', '680 grams', 1, 1),
(2, 2, 'Elara Shoulder', 'elara-shoulder', 'Trending', 2299.00, 2799.00, 'Sculptural crescent shoulder bag with a fluid silhouette, buttery soft hand feel, and smooth magnetic closure.', '28cm (W) × 18cm (H) × 8cm (D)', '420 grams', 1, 2),
(3, 3, 'Cora Crossbody', 'cora-crossbody', 'Essential', 2099.00, 2499.00, 'Clean, hands-free daily essential with an adjustable strap, dual internal card organizers, and quick-access back slip pocket.', '22cm (W) × 16cm (H) × 6cm (D)', '360 grams', 1, 3),
(4, 4, 'Alba Hobo', 'alba-hobo', 'Editor''s Pick', 2399.00, 2899.00, 'Relaxed slouch silhouette crafted from ultra-supple nappa leather with generous internal capacity and comfortable carry.', '34cm (W) × 26cm (H) × 12cm (D)', '510 grams', 1, 4),
(5, 5, 'Mira Structured', 'mira-structured', 'New', 2499.00, 2999.00, 'Architectural top-handle bag with a detachable crossbody strap, structured accordion gussets, and gold-tone protective base feet.', '26cm (W) × 20cm (H) × 10cm (D)', '580 grams', 1, 5);

-- -----------------------------------------------------------------------------
-- 3. Product Features
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `product_features`;
INSERT INTO `product_features` (`product_id`, `feature_text`, `sort_order`) VALUES
-- Verona Tote
(1, '14" Dedicated Laptop Sleeve', 1),
(1, 'Concealed Magnetic Closure', 2),
(1, 'Water-resistant Microfiber Lining', 3),
(1, 'Integrated Solid Brass Key Leash', 4),
-- Elara Shoulder
(2, 'Ergonomic Wide Shoulder Strap', 1),
(2, 'Interior Dual Card Organizer', 2),
(2, 'Custom Brushed Brass Hardware', 3),
-- Cora Crossbody
(3, 'Adjustable 5-point Crossbody Strap', 1),
(3, 'Secure Interior Zipper Compartment', 2),
(3, 'Scratch-resistant Textured Grain', 3),
-- Alba Hobo
(4, 'Ultra-soft Supple Nappa Finish', 1),
(4, 'Spacious Slouch Profile', 2),
(4, 'Dual Magnetic Clasp Closure', 3),
-- Mira Structured
(5, 'Dual Structured Top Carry Handles', 1),
(5, 'Detachable & Adjustable Crossbody Strap', 2),
(5, 'Protective Gold-Tone Metal Base Feet', 3);

-- -----------------------------------------------------------------------------
-- 4. Product Variants (Color and Stock)
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `product_variants`;
INSERT INTO `product_variants` (`id`, `product_id`, `sku`, `color_name`, `color_hex`, `stock_quantity`, `is_active`) VALUES
-- Verona Tote
(1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 45, 1),
(2, 1, 'BAL-VER-COG', 'Cognac', '#8B5A2B', 38, 1),
(3, 1, 'BAL-VER-COF', 'Coffee Brown', '#4A3B32', 25, 1),
-- Elara Shoulder
(4, 2, 'BAL-ELA-BLK', 'Black', '#1c1b1b', 30, 1),
(5, 2, 'BAL-ELA-COG', 'Cognac', '#8B5A2B', 40, 1),
(6, 2, 'BAL-ELA-COF', 'Coffee Brown', '#4A3B32', 20, 1),
-- Cora Crossbody
(7, 3, 'BAL-COR-BLK', 'Black', '#1c1b1b', 50, 1),
(8, 3, 'BAL-COR-COG', 'Cognac', '#8B5A2B', 35, 1),
(9, 3, 'BAL-COR-COF', 'Coffee Brown', '#4A3B32', 28, 1),
-- Alba Hobo
(10, 4, 'BAL-ALB-BLK', 'Black', '#1c1b1b', 22, 1),
(11, 4, 'BAL-ALB-COG', 'Cognac', '#8B5A2B', 30, 1),
(12, 4, 'BAL-ALB-COF', 'Coffee Brown', '#4A3B32', 18, 1),
-- Mira Structured
(13, 5, 'BAL-MIR-BLK', 'Black', '#1c1b1b', 25, 1),
(14, 5, 'BAL-MIR-COG', 'Cognac', '#8B5A2B', 32, 1),
(15, 5, 'BAL-MIR-COF', 'Coffee Brown', '#4A3B32', 15, 1);

-- -----------------------------------------------------------------------------
-- 5. Product Images
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `product_images`;
INSERT INTO `product_images` (`product_id`, `variant_id`, `image_url`, `alt_text`, `image_type`, `sort_order`) VALUES
-- Verona Tote
(1, 1, 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=900&q=80', 'Verona Tote Front View', 'primary', 1),
(1, 2, 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=900&q=80', 'Verona Tote Detail & Styling', 'hover', 2),
(1, NULL, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80', 'Verona Tote Interior Laptop Compartment', 'gallery', 3),

-- Elara Shoulder
(2, 4, 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=900&q=80', 'Elara Shoulder Bag Front View', 'primary', 1),
(2, 5, 'https://images.unsplash.com/photo-1591561954557-26941169b49e?auto=format&fit=crop&w=900&q=80', 'Elara Shoulder Bag On Shoulder', 'hover', 2),

-- Cora Crossbody
(3, 7, 'https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=900&q=80', 'Cora Crossbody Front Profile', 'primary', 1),
(3, 8, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80', 'Cora Crossbody Lifestyle Carry', 'hover', 2),

-- Alba Hobo
(4, 10, 'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=900&q=80', 'Alba Hobo Slouch Silhouette', 'primary', 1),
(4, 11, 'https://images.unsplash.com/photo-1575032617751-6ddec2089882?auto=format&fit=crop&w=900&q=80', 'Alba Hobo Soft Leather Texture', 'hover', 2),

-- Mira Structured
(5, 13, 'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=900&q=80', 'Mira Structured Bag Front View', 'primary', 1),
(5, 14, 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=900&q=80', 'Mira Structured Bag Brass Base Feet', 'hover', 2);

-- -----------------------------------------------------------------------------
-- 6. Coupons
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `coupons`;
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_cap`, `usage_limit`, `usage_count`, `is_active`, `starts_at`, `expires_at`) VALUES
(1, 'WELCOME10', 'percentage', 10.00, 0.00, 1000.00, NULL, 0, 1, '2026-01-01 00:00:00', '2030-12-31 23:59:59'),
(2, 'BALENTO', 'percentage', 10.00, 0.00, 1000.00, NULL, 0, 1, '2026-01-01 00:00:00', '2030-12-31 23:59:59'),
(3, 'PRIVILEGE500', 'fixed', 500.00, 2200.00, NULL, 500, 0, 1, '2026-01-01 00:00:00', '2030-12-31 23:59:59');

-- -----------------------------------------------------------------------------
-- 7. Pincodes (Tier 1 & 2 Indian Metro Hubs)
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `pincodes`;
INSERT INTO `pincodes` (`pincode`, `city`, `state`, `is_serviceable`, `cod_available`, `estimated_days`, `shipping_zone`) VALUES
-- Karnataka (Bengaluru)
('560034', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),
('560001', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),
('560038', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),
('560102', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),
('560068', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),

-- Maharashtra (Mumbai & Pune)
('400050', 'Mumbai', 'Maharashtra', 1, 1, 2, 'West Metro'),
('400001', 'Mumbai', 'Maharashtra', 1, 1, 2, 'West Metro'),
('400051', 'Mumbai', 'Maharashtra', 1, 1, 2, 'West Metro'),
('400053', 'Mumbai', 'Maharashtra', 1, 1, 2, 'West Metro'),
('411001', 'Pune', 'Maharashtra', 1, 1, 3, 'West Tier-1'),

-- Delhi NCR
('110003', 'New Delhi', 'Delhi', 1, 1, 2, 'North Metro'),
('110001', 'New Delhi', 'Delhi', 1, 1, 2, 'North Metro'),
('110017', 'New Delhi', 'Delhi', 1, 1, 2, 'North Metro'),
('110048', 'New Delhi', 'Delhi', 1, 1, 2, 'North Metro'),
('122002', 'Gurugram', 'Haryana', 1, 1, 2, 'North Metro'),
('201301', 'Noida', 'Uttar Pradesh', 1, 1, 2, 'North Metro'),

-- Telangana (Hyderabad)
('500034', 'Hyderabad', 'Telangana', 1, 1, 3, 'South Metro'),
('500081', 'Hyderabad', 'Telangana', 1, 1, 3, 'South Metro'),

-- Tamil Nadu (Chennai)
('600002', 'Chennai', 'Tamil Nadu', 1, 1, 3, 'South Metro'),
('600028', 'Chennai', 'Tamil Nadu', 1, 1, 3, 'South Metro'),

-- West Bengal (Kolkata)
('700001', 'Kolkata', 'West Bengal', 1, 1, 3, 'East Metro'),

-- Goa
('403507', 'Goa', 'Goa', 1, 1, 3, 'West Coastal');

-- -----------------------------------------------------------------------------
-- 8. Lookbook Items (Curated Editorial Showcase)
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `lookbook_items`;
INSERT INTO `lookbook_items` (`id`, `city_key`, `city_title`, `person_name`, `person_title`, `product_id`, `image_url`, `fallback_url`, `quote`, `sort_order`, `is_active`) VALUES
(1, 'bengaluru', 'Bengaluru • Koramangala', 'Sneha Reddy', 'Founder & Designer', 1, 'https://i.pinimg.com/736x/df/77/80/df7780072b22595ff903332c9ba0ca2a.jpg', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80', 'The Verona effortlessly carries my 14" MacBook, sketchbook, and tech pouches across client pitches without looking bulky.', 1, 1),
(2, 'mumbai', 'Mumbai • Bandra West', 'Tanvi Kapoor', 'Creative Director', 2, 'https://i.pinimg.com/736x/87/46/72/8746726c9dae887d159a4be29e843c08.jpg', 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=900&q=80', 'The fluid crescent silhouette transitions from morning meetings in BKC to seaside dinner drinks in Bandra effortlessly.', 2, 1),
(3, 'delhi', 'New Delhi • Khan Market', 'Meera Verma', 'Architect', 3, 'https://i.pinimg.com/736x/c7/2b/23/c72b23a9d20c388654497e06a3da0120.jpg', 'https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=900&q=80', 'Hands-free perfection. The scratch-resistant grain has endured daily commutes and weekend gallery strolls beautifully.', 3, 1),
(4, 'goa', 'Goa • Assagao', 'Aarohi Dave', 'Writer & Curator', 4, 'https://i.pinimg.com/736x/88/54/b5/8854b5dfd46ffb0ca47a9ef719aa0c7d.jpg', 'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=900&q=80', 'Unstructured elegance at its finest. The nappa leather feels like liquid butter and fits all my weekend essentials.', 4, 1);

-- -----------------------------------------------------------------------------
-- 9. Admins (Default Local Dev Admin - Password: Password@123)
-- -----------------------------------------------------------------------------
TRUNCATE TABLE `admins`;
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`) VALUES
(1, 'admin', 'admin@balento.com', '$2y$12$1Po4tgeAc3O0qmT7rEmlwuZpYBeqqRkUN5Rk4oA3K0n8vIrZNojZ.', 'admin', 1);

SET FOREIGN_KEY_CHECKS = 1;
