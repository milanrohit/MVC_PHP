CREATE TABLE IF NOT EXISTS order_master (
    order_master_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_master_user_id INT UNSIGNED NOT NULL COMMENT 'FK -> user_master.user_master_id',
    order_master_total INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'minor units',
    order_master_status ENUM(
        'pending',
        'paid',
        'shipped',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'pending' COMMENT 'pending = placed, unpaid | paid = payment done | shipped = on the way | delivered = received | cancelled = stopped',
    order_master_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_master_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_master_id),
    KEY idx_order_master_user_id (order_master_user_id),
    KEY idx_order_master_status_created (
        order_master_status,
        order_master_created_at
    ),
    CONSTRAINT fk_order_master_user FOREIGN KEY (order_master_user_id) REFERENCES user_master (user_master_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Customer orders (header)';