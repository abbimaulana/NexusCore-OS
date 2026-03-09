-- ============================================================
-- NexusCore OS — Complete Database Schema & Seed Data
-- Import this file via phpMyAdmin or CLI: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `nexuscore_os`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `nexuscore_os`;

-- ---------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`        VARCHAR(80)  NOT NULL UNIQUE,
    `password`        VARCHAR(255) NOT NULL,
    `email`           VARCHAR(150) NOT NULL DEFAULT '',
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`    DATETIME     NULL DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: settings
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT         NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: products
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(200) NOT NULL,
    `slug`            VARCHAR(220) NOT NULL UNIQUE,
    `description`     TEXT         NOT NULL DEFAULT '',
    `price`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `stock_type`      ENUM('unlimited','finite') NOT NULL DEFAULT 'unlimited',
    `stock_qty`       INT UNSIGNED NOT NULL DEFAULT 0,
    `image`           VARCHAR(300) NOT NULL DEFAULT '',
    `category`        VARCHAR(100) NOT NULL DEFAULT '',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `flash_sale_price` DECIMAL(15,2) NULL DEFAULT NULL,
    `flash_sale_end`  DATETIME NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: orders
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code`     VARCHAR(30)  NOT NULL UNIQUE,
    `buyer_name`     VARCHAR(150) NOT NULL,
    `buyer_email`    VARCHAR(150) NOT NULL,
    `buyer_wa`       VARCHAR(30)  NOT NULL,
    `service_detail` TEXT         NOT NULL DEFAULT '',
    `payment_method` VARCHAR(100) NOT NULL DEFAULT '',
    `total_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status`         ENUM('pending','paid','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: order_items
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`     INT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `product_name` VARCHAR(200) NOT NULL,
    `price`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `qty`          INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `fk_order_items_order` (`order_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: payment_methods
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`        ENUM('bank','ewallet','qris') NOT NULL,
    `label`       VARCHAR(100) NOT NULL,
    `detail_json` TEXT NOT NULL DEFAULT '{}',
    `qr_image`    VARCHAR(300) NOT NULL DEFAULT '',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: projects
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(200) NOT NULL,
    `description` TEXT         NOT NULL DEFAULT '',
    `tech_stack`  VARCHAR(300) NOT NULL DEFAULT '',
    `github_url`  VARCHAR(300) NOT NULL DEFAULT '',
    `demo_url`    VARCHAR(300) NOT NULL DEFAULT '',
    `image`       VARCHAR(300) NOT NULL DEFAULT '',
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: gallery
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `image_path` VARCHAR(300) NOT NULL,
    `caption`    VARCHAR(300) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: chatbot_responses
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chatbot_responses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `keywords`     TEXT NOT NULL DEFAULT '',
    `response_text` TEXT NOT NULL DEFAULT '',
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: contact_messages
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `message`    TEXT NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: recovery_log (failed recovery attempts)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recovery_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempted_email` VARCHAR(150) NOT NULL,
    `ip_address`  VARCHAR(50)  NOT NULL DEFAULT '',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===============================================================
-- SEED DATA
-- ===============================================================

-- Admin user (password: nexus2026 — bcrypt hash)
INSERT INTO `users` (`username`, `password`, `email`) VALUES
('admin', '$2y$10$QoDYbXxEiZyrpkWNNdZLiOOFb8NRmat3.wTCGrAbLhhMaSrj70HOC', 'admin@nexuscore.os')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',                  'NexusCore OS'),
('site_logo',                  ''),
('site_favicon',               ''),
('seo_title',                  'NexusCore OS — Portfolio & Marketplace'),
('seo_description',            'NexusCore OS is a modular CMS and marketplace for tech-enthusiasts.'),
('seo_keywords',               'nexuscore, cms, portfolio, marketplace, tech'),
('neon_color',                 '#00ff99'),
('shop_enabled',               '1'),
('chatbot_enabled',            '1'),
('chatbot_provider',           'auto'),
('chatbot_api_key',            ''),
('chatbot_auto_script',        'Hello! How can I assist you today?'),
('telegram_bot_token',         ''),
('telegram_chat_id',           ''),
('checkout_success_message',   'Thank you for your order! We will process it shortly and contact you via WhatsApp.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Sample products
INSERT INTO `products` (`name`, `slug`, `description`, `price`, `stock_type`, `stock_qty`, `category`, `is_active`) VALUES
('VPS Setup — Ubuntu 22.04 + Nginx', 'vps-setup-ubuntu-nginx', 'Full VPS setup: Ubuntu 22.04 LTS, Nginx, SSL, firewall, and basic hardening included.', 150000.00, 'unlimited', 0, 'Server Setup', 1),
('WordPress Managed Hosting Config', 'wordpress-managed-config', 'Managed WordPress installation with performance tuning, caching (Redis), and CDN setup.', 250000.00, 'unlimited', 0, 'Web Hosting', 1),
('Custom Bot Development', 'custom-bot-development', 'Telegram / Discord bot built to your specification. Price is per feature set.', 500000.00, 'finite', 10, 'Development', 1)
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Sample projects
INSERT INTO `projects` (`title`, `description`, `tech_stack`, `github_url`, `demo_url`, `sort_order`, `is_active`) VALUES
('NexusCore OS', 'A modular CMS and marketplace platform for tech-enthusiasts built with PHP & MySQL.', 'PHP,MySQL,Tailwind CSS,JavaScript', 'https://github.com/abbimaulana/NexusCore-OS', '#', 1, 1),
('TeleBot Commander', 'A Telegram bot framework with plugin architecture for automation tasks.', 'Python,Telegram API,SQLite', 'https://github.com/abbimaulana/telebot-commander', '#', 2, 1),
('VPS AutoDeploy', 'Bash scripting toolkit for automated VPS provisioning and deployment pipelines.', 'Bash,Nginx,Docker,CI/CD', 'https://github.com/abbimaulana/vps-autodeploy', '#', 3, 1)
ON DUPLICATE KEY UPDATE `title` = `title`;

-- Sample keyword-based chatbot responses
INSERT INTO `chatbot_responses` (`keywords`, `response_text`, `is_active`) VALUES
('harga,price,cost,berapa', 'Silakan lihat halaman Shop kami untuk daftar harga terbaru. Atau ketik nama layanan yang Anda butuhkan!', 1),
('hello,hi,halo,hey,hai', 'Halo! Selamat datang di NexusCore OS 👋 Ada yang bisa kami bantu?', 1),
('order,beli,buy,pesan', 'Untuk memesan, silakan kunjungi halaman Shop dan tambahkan produk ke keranjang. Kami akan menghubungi Anda setelah pembayaran dikonfirmasi.', 1),
('contact,kontak,hubungi', 'Anda bisa menghubungi kami melalui form Kontak di bawah halaman ini, atau via WhatsApp yang tersedia di halaman pesanan.', 1)
ON DUPLICATE KEY UPDATE `keywords` = `keywords`;
