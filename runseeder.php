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

    static $set_frk_chk = 'SET FOREIGN_KEY_CHECKS';

    // Disable foreign key checks temporarily to handle clean truncation safely
    $pdo->exec($set_frk_chk .'= 0;');

    echo "🚀 Starting precise bulk database seeding (50-500 records)..." . PHP_EOL;

    // Start a single database transaction to process queries at lightning speed
    $pdo->beginTransaction();

    $outputMessages = [];

    // ==========================================
    // 1. Seed Categories (category_master)
    // ==========================================
    $pdo->exec("TRUNCATE TABLE category_master;");
    $categories = ['Electronics', 'Clothing', 'Home & Kitchen', 'Books', 'Sports', 'Toys', 'Beauty', 'Automotive', 'Health', 'Grocery'];
    $catStmt = $pdo->prepare("INSERT INTO category_master (category_master_name, category_master_slug, category_master_is_active, category_master_is_deleted) VALUES (?, ?, 'Y', 'N')");
    
    foreach ($categories as $index => $catName) {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $catName))) . '-' . ($index + 1);
        $catStmt->execute([$catName, $slug]);
    }
    $outputMessages[] = "Inserted " . count($categories) . " categories into category_master.";

    // ==========================================
    // 2. Seed Users (user_master) - Bulk Inserted
    // ==========================================
    $pdo->exec("TRUNCATE TABLE user_master;");
    
    // CRITICAL SPEED FIX: Pre-hash the password ONCE outside the loop to avoid CPU throttling
    $sharedPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
    
    $userRows = [];
    $userSqlParams = [];
    for ($i = 1; $i <= 50; $i++) {
        $userRows[] = "(?, ?, ?, 'customer', 'Y', 'Y', 'N')";
        $userSqlParams[] = "Customer User {$i}";
        $userSqlParams[] = "customer{$i}@example.com";
        $userSqlParams[] = $sharedPasswordHash;
    }
    $pdo->prepare("INSERT INTO user_master (user_master_name, user_master_email, user_master_password, user_master_role, user_master_is_active, user_master_is_verified, user_master_is_deleted) VALUES " . implode(',', $userRows))
        ->execute($userSqlParams);
        
    $outputMessages[] = "Inserted 50 users into user_master.";

    // ==========================================
    // 3. Seed Products (product_master) - Bulk Inserted
    // ==========================================
    $pdo->exec("TRUNCATE TABLE product_master;");
    
    $productRows = [];
    $productSqlParams = [];
    for ($i = 1; $i <= 100; $i++) {
        $catId = random_int(1, count($categories));
        $prodName = "Product Item Model {$i}";
        $slug = "product-item-model-{$i}-" . uniqid('', false);
        $desc = "This is a detailed description for product item model {$i}.";
        $price = random_int(49900, 250000); // Minor units format (e.g. 49900 = $499.00)
        $stock = random_int(10, 200);
        $isFeatured = ($i <= 10) ? 'Y' : 'N';

        $productRows[] = "(?, ?, ?, ?, ?, ?, 'Y', ?, 'N')";
        array_push($productSqlParams, $catId, $prodName, $slug, $desc, $price, $stock, $isFeatured);
    }
    $pdo->prepare("INSERT INTO product_master (product_master_category_id, product_master_name, product_master_slug, product_master_description, product_master_price, product_master_stock, product_master_is_active, product_master_is_featured, product_master_is_deleted) VALUES " . implode(',', $productRows))
        ->execute($productSqlParams);

    $outputMessages[] = "Inserted 100 products into product_master.";

    // ==========================================
    // 4. Seed Orders (order_master) & Details (order_detail)
    // ==========================================
    $pdo->exec("TRUNCATE TABLE order_detail;");
    $pdo->exec("TRUNCATE TABLE order_master;");
    
    $statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
    
    $orderMasterStmt = $pdo->prepare("INSERT INTO order_master (order_master_user_id, order_master_total, order_master_status) VALUES (?, ?, ?)");
    $orderDetailStmt = $pdo->prepare("INSERT INTO order_detail (order_detail_order_id, order_detail_product_id, order_detail_quantity, order_detail_price) VALUES (?, ?, ?, ?)");
    $updateTotalStmt = $pdo->prepare("UPDATE order_master SET order_master_total = ? WHERE order_master_id = ?");

    for ($orderId = 1; $orderId <= 200; $orderId++) {
        // FIX: Connect only to seeded user IDs (1 to 50)
        $userId = random_int(1, 50);
        $status = $statuses[array_rand($statuses)];
        
        // Insert Header
        $orderMasterStmt->execute([$userId, 0, $status]);

        $itemsInOrder = random_int(1, 4);
        $orderTotal = 0;

        for ($item = 0; $item < $itemsInOrder; $item++) {
            // FIX: Connect only to valid product IDs (1 to 100)
            $productId = random_int(1, 100);
            $qty = random_int(1, 10);
            $unitPrice = random_int(999, 15000);
            
            $orderDetailStmt->execute([$orderId, $productId, $qty, $unitPrice]);
            $orderTotal += ($qty * $unitPrice);
        }

        // Update Header Total
        $updateTotalStmt->execute([$orderTotal, $orderId]);
    }
    
    $outputMessages[] = "Inserted 200 order headers into order_master.";
    $outputMessages[] = "Inserted order details and calculated header totals successfully.";

    // Save all actions permanently to the database engine
    $pdo->commit();

    // Re-enable foreign key constraints
    $pdo->exec($set_frk_chk .'= 1;');

    // ==========================================
    // Visual Output Display Layout
    // ==========================================
    echo PHP_EOL . "\033[1;36m┌────────────────────────────────────────────────────────┐\033[0m" . PHP_EOL;
    echo "\033[1;36m│ \033[1;32m✔\033[0m \033[1;37mSEED DATA COMPLETED SUCCESSFULLY                      \033[1;36m│\033[0m" . PHP_EOL;
    echo "\033[1;36m└────────────────────────────────────────────────────────┘\033[0m" . PHP_EOL;

    foreach ($outputMessages as $message) {
        echo "  \033[32m✔\033[0m \033[37m{$message}\033[0m" . PHP_EOL;
    }

    echo PHP_EOL . "\033[42m\033[30m SUCCESS \033[0m All database tables are seeded precisely using schema column mappings." . PHP_EOL . PHP_EOL;

} catch (\PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec($set_frk_chk .'= 1;');
    echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Docker DB Connection/Query Failure: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    exit(1);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec($set_frk_chk .'= 1;');
    echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Fatal Processing Error: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    exit(1);
}
