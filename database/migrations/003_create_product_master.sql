CREATE TABLE IF NOT EXISTS product_master (
    product_master_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_master_category_id INT UNSIGNED NOT NULL COMMENT 'FK -> category_master.category_master_id',
    product_master_name VARCHAR(150) NOT NULL,
    product_master_slug VARCHAR(170) NOT NULL COMMENT 'url-friendly name, unique',
    product_master_description TEXT NULL,
    product_master_price INT UNSIGNED NOT NULL COMMENT 'minor units: 79900 = 799.00',
    product_master_stock INT UNSIGNED NOT NULL DEFAULT 0,
    product_master_is_active ENUM('Y', 'N') NOT NULL DEFAULT 'Y' COMMENT 'Y = can be bought; N = hidden from shop',
    product_master_is_featured ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Y = shown on home page',
    product_master_is_deleted ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'soft delete. Y = removed, kept for old orders',
    product_master_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    product_master_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_master_id),
    UNIQUE KEY uq_product_master_slug (product_master_slug),
    KEY idx_product_master_category_id (product_master_category_id),
    CONSTRAINT fk_product_master_category FOREIGN KEY (product_master_category_id) REFERENCES category_master (category_master_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Sellable products';