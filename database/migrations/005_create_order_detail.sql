CREATE TABLE IF NOT EXISTS order_detail (
    order_detail_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_detail_order_id INT UNSIGNED NOT NULL COMMENT 'FK -> order_master.order_master_id',
    order_detail_product_id INT UNSIGNED NOT NULL COMMENT 'FK -> product_master.product_master_id',
    order_detail_quantity INT UNSIGNED NOT NULL DEFAULT 1,
    order_detail_price INT UNSIGNED NOT NULL COMMENT 'unit price at purchase time (minor units); not affected by later price changes',
    PRIMARY KEY (order_detail_id),
    KEY idx_order_detail_order_id (order_detail_order_id),
    KEY idx_order_detail_product_id (order_detail_product_id),
    CONSTRAINT fk_order_detail_order FOREIGN KEY (order_detail_order_id) REFERENCES order_master (order_master_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_detail_product FOREIGN KEY (order_detail_product_id) REFERENCES product_master (product_master_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Order lines (items in an order)';