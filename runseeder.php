<?php

declare(strict_types=1);

require_once '/var/www/html/vendor/autoload.php';

use App\Helpers\SqlHelper;

$dbHost = 'db';
$dbName = 'mvc_app';
$dbUser = 'root';
$dbPass = 'secret';

try {
    $pdo = new \PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_TIMEOUT            => 5
        ]
    );

    SqlHelper::setConnection($pdo);

    // Disable foreign key checks temporarily to handle clean truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    echo "🚀 Starting precise bulk database seeding (50-500 records)...\n\n";

    // 1. Seed Categories (category_master)
    $pdo->exec("TRUNCATE TABLE category_master;");
    $categories = ['Electronics', 'Clothing', 'Home & Kitchen', 'Books', 'Sports', 'Toys', 'Beauty', 'Automotive', 'Health', 'Grocery'];
    $catStmt = $pdo->prepare("INSERT INTO category_master (category_master_name, category_master_slug, category_master_is_active, category_master_is_deleted) VALUES (?, ?, 'Y', 'N')");
    
    foreach ($categories as $index => $catName) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $catName))) . '-' . ($index + 1);
        $catStmt->execute([$catName, $slug]);
    }
    echo "✔ Inserted " . count($categories) . " categories into category_master[cite: 8].\n";

    // 2. Seed Users (user_master) - 50 Records
    $pdo->exec("TRUNCATE TABLE user_master;");
    $userStmt = $pdo->prepare("INSERT INTO user_master (user_master_name, user_master_email, user_master_password, user_master_role, user_master_is_active, user_master_is_verified, user_master_is_deleted) VALUES (?, ?, ?, 'customer', 'Y', 'Y', 'N')");
    
    for ($i = 1; $i <= 50; $i++) {
        $userStmt->execute([
            "Customer User {$i}",
            "customer{$i}@example.com",
            password_hash('password123', PASSWORD_BCRYPT)
        ]);
    }
    echo "✔ Inserted 50 users into user_master[cite: 7].\n";

    // 3. Seed Products (product_master) - 100 Records
    $pdo->exec("TRUNCATE TABLE product_master;");
    $prodStmt = $pdo->prepare("INSERT INTO product_master (product_master_category_id, product_master_name, product_master_slug, product_master_description, product_master_price, product_master_stock, product_master_is_active, product_master_is_featured, product_master_is_deleted) VALUES (?, ?, ?, ?, ?, ?, 'Y', ?, 'N')");
    
    for ($i = 1; $i <= 100; $i++) {
        $catId = rand(1, count($categories));
        $prodName = "Product Item Model {$i}";
        $slug = "product-item-model-{$i}-" . uniqid();
        $desc = "This is a detailed description for product item model {$i}.";
        $price = rand(49900, 250000); // Minor units (e.g., 49900 = 499.00)[cite: 9]
        $stock = rand(10, 200);
        $isFeatured = ($i <= 10) ? 'Y' : 'N'; // First 10 featured on home page[cite: 9]

        $prodStmt->execute([$catId, $prodName, $slug, $desc, $price, $stock, $isFeatured]);
    }
    echo "✔ Inserted 100 products into product_master[cite: 9].\n";

    // 4. Seed Orders (order_master) - 200 Records
    $pdo->exec("TRUNCATE TABLE order_detail;");
    $pdo->exec("TRUNCATE TABLE order_master;");
    
    $statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
    $orderStmt = $pdo->prepare("INSERT INTO order_master (order_master_user_id, order_master_total, order_master_status) VALUES (?, ?, ?)");
    
    for ($i = 1; $i <= 200; $i++) {
        $userId = rand(1, 50); // Links to user_master_id 1-50[cite: 7, 10]
        $status = $statuses[array_rand($statuses)];
        $orderStmt->execute([$userId, 0, $status]); // Total will be updated in next loop[cite: 10]
    }
    echo "✔ Inserted 200 order headers into order_master[cite: 10].\n";

    // 5. Seed Order Details (order_detail) - ~400 Line Items with calculated totals
    $detailStmt = $pdo->prepare("INSERT INTO order_detail (order_detail_order_id, order_detail_product_id, order_detail_quantity, order_detail_price) VALUES (?, ?, ?, ?)");
    $updateTotalStmt = $pdo->prepare("UPDATE order_master SET order_master_total = ? WHERE order_master_id = ?");

    for ($orderId = 1; $orderId <= 200; $orderId++) {
        $itemsInOrder = rand(1, 4); 
        $orderTotal = 0;

        for ($item = 0; $item < $itemsInOrder; $item++) {
            $productId = rand(1, 100);
            $qty = rand(1, 3);
            $unitPrice = rand(999, 15000); // Minor units price snapshot[cite: 11]
            
            $detailStmt->execute([$orderId, $productId, $qty, $unitPrice]);
            $orderTotal += ($qty * $unitPrice);
        }

        // Update the order master header total price
        $updateTotalStmt->execute([$orderTotal, $orderId]);
    }
    echo "✔ Inserted order details and calculated header totals successfully[cite: 10, 11].\n";

    // Re-enable foreign key constraints
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "\n\033[1;32m🎉 Success! All framework database tables are seeded precisely using schema column mappings.\033[0m\n";

} catch (\PDOException $e) {
    echo "\033[1;31mDocker DB Connection/Query Failure: " . $e->getMessage() . "\033[0m\n";
    exit(1);
} catch (\Throwable $e) {
    echo "\033[1;31mFatal Processing Error: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}