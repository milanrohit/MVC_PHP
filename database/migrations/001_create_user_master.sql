CREATE TABLE IF NOT EXISTS user_master (
    user_master_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_master_name VARCHAR(100) NOT NULL,
    user_master_email VARCHAR(190) NOT NULL COMMENT 'login id; stored lowercase by UserService; unique',
    user_master_password VARCHAR(255) NULL COMMENT 'password_hash() output only; NULL = no password set yet',
    user_master_role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer' COMMENT 'customer = shopper; admin = can open /admin panel',
    user_master_is_active ENUM('Y', 'N') NOT NULL DEFAULT 'Y' COMMENT 'Y = can log in; N = temporarily blocked by admin',
    user_master_is_verified ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Y = email confirmed; N = not confirmed yet',
    user_master_is_deleted ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'soft delete. Y = hidden everywhere, row kept for order history; N = normal',
    user_master_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_master_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    user_master_deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'set when is_deleted becomes Y',
    PRIMARY KEY (user_master_id),
    UNIQUE KEY uq_user_master_email (user_master_email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Customers and admins';
