CREATE TABLE IF NOT EXISTS category_master (
    category_master_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_master_name        VARCHAR(100) NOT NULL,
    category_master_slug        VARCHAR(120) NOT NULL COMMENT 'url-friendly name, unique',
    category_master_is_active   ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Y = shown in shop; N = hidden',
    category_master_is_deleted  ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'soft delete. Y = removed',
    category_master_created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_master_id),
    UNIQUE KEY uq_category_master_slug (category_master_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Product categories';