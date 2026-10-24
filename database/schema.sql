-- Smart Shop product catalog schema
-- MySQL 8.0+ / MariaDB 10.5+
-- This file creates structure only; it does not import the entire WooCommerce catalog.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS smart_shop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE smart_shop;

CREATE TABLE IF NOT EXISTS brands (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_brand_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NULL,
  source_url VARCHAR(1024) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brands_source_id (source_brand_id),
  KEY idx_brands_name (name),
  KEY idx_brands_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_category_id BIGINT UNSIGNED NULL,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NULL,
  source_url VARCHAR(1024) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_source_id (source_category_id),
  KEY idx_categories_parent (parent_id),
  KEY idx_categories_name (name),
  KEY idx_categories_slug (slug),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_product_id BIGINT UNSIGNED NOT NULL COMMENT 'Original product ID from WooCommerce/source store',
  parent_source_product_id BIGINT UNSIGNED NULL,
  name VARCHAR(500) NOT NULL,
  slug VARCHAR(255) NULL,
  product_type VARCHAR(50) NOT NULL DEFAULT 'simple',
  sku VARCHAR(191) NULL,
  brand_id BIGINT UNSIGNED NULL,
  short_description MEDIUMTEXT NULL,
  description LONGTEXT NULL,
  regular_price DECIMAL(15,2) NULL,
  sale_price DECIMAL(15,2) NULL,
  current_price DECIMAL(15,2) NULL,
  currency_code VARCHAR(10) NOT NULL DEFAULT 'IRT',
  currency_symbol VARCHAR(20) NOT NULL DEFAULT 'تومان',
  on_sale TINYINT(1) NOT NULL DEFAULT 0,
  average_rating DECIMAL(3,2) NULL,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  stock_status ENUM('in_stock','out_of_stock','on_backorder','unknown') NOT NULL DEFAULT 'unknown',
  stock_quantity INT NULL,
  is_purchasable TINYINT(1) NOT NULL DEFAULT 0,
  is_password_protected TINYINT(1) NOT NULL DEFAULT 0,
  product_url VARCHAR(1024) NULL,
  source_api_url VARCHAR(1024) NULL,
  weight VARCHAR(100) NULL,
  dimensions_json JSON NULL,
  extra_data JSON NULL COMMENT 'Optional source fields not yet mapped into dedicated columns',
  source_updated_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_source_product_id (source_product_id),
  KEY idx_products_name (name(191)),
  KEY idx_products_slug (slug),
  KEY idx_products_sku (sku),
  KEY idx_products_price (current_price),
  KEY idx_products_stock (stock_status),
  KEY idx_products_brand (brand_id),
  FULLTEXT KEY ft_products_search (name, short_description, description),
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
  CONSTRAINT chk_products_rating CHECK (average_rating IS NULL OR (average_rating >= 0 AND average_rating <= 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  source_image_id BIGINT UNSIGNED NULL,
  image_url VARCHAR(2048) NOT NULL,
  thumbnail_url VARCHAR(2048) NULL,
  alt_text VARCHAR(500) NULL,
  image_name VARCHAR(255) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_images_product_order (product_id, sort_order),
  KEY idx_product_images_source_id (source_image_id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY idx_product_categories_category (category_id, product_id),
  CONSTRAINT fk_product_categories_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_attributes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  attribute_name VARCHAR(191) NOT NULL,
  attribute_value TEXT NOT NULL,
  attribute_slug VARCHAR(191) NULL,
  source_attribute_id BIGINT UNSIGNED NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_product_attributes_product (product_id),
  KEY idx_product_attributes_name (attribute_name),
  KEY idx_product_attributes_slug (attribute_slug),
  CONSTRAINT fk_product_attributes_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_tag_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_tags_source_id (source_tag_id),
  KEY idx_product_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_tag_map (
  product_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, tag_id),
  CONSTRAINT fk_product_tag_map_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_tag_map_tag FOREIGN KEY (tag_id) REFERENCES product_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prices are stored as DECIMAL, never floating-point.
-- current_price should be the source's final/current price; stock_quantity can remain NULL if source does not provide it.
