<?php

declare(strict_types=1);

require_once '/var/www/html/vendor/autoload.php';

use App\Helpers\SqlHelper;

/**
 * Generates a URL-friendly slug from a category name.
 * (Extracted to lower cognitive complexity).
 */
function generateCategorySlug(string $catName, int $index): string
{
    $cleaned = (string) preg_replace('/[^A-Za-z0-9-]+/', '-', $catName);
    return strtolower(trim($cleaned)) . '-' . ($index + 1);
}

/**
 * Seeds category master records.
 */
function seedCategories(\PDO $pdo, array $categories): void
{
    // PHP 8+ Fix: Use DELETE FROM instead of TRUNCATE to protect active transactions
    $pdo->exec("DELETE FROM category_master;");
    $catStmt = $pdo->prepare("INSERT INTO category_master (category_master_name, category_master_slug, category_master_is_active, category_master_is_deleted) VALUES (?, ?, 'Y', 'N')");
    
    foreach ($categories as $index => $catName) {
        $slug = generateCategorySlug($catName, $index);
        $catStmt->execute([$catName, $slug]);
    }
}

/**
 * Seeds user master records using high-speed bulk inserts.
 */
function seedUsers(\PDO $pdo): void
{
    $pdo->exec("DELETE FROM user_master;");
    $sharedPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
    
    $userRows = [];
    $userSqlParams = [];
    for ($i = 1; $i <= 50; $i++) {
        $userRows[] = "(?, ?, ?, 'customer', 'Y', 'Y', 'N')";
        array_push($userSqlParams, "Customer User {$i}", "customer{$i}@example.com", $sharedPasswordHash);
    }
    
    $pdo->prepare("INSERT INTO user_master (user_master_name, user_master_email, user_master_password, user_master_role, user_master_is_active, user_master_is_verified, user_master_is_deleted) VALUES " . implode(',', $userRows))
        ->execute($userSqlParams);
}

/**
 * Seeds product master records using high-speed bulk inserts.
 */
function seedProducts(\PDO $pdo, int $categoryCount): void
{
    $pdo->exec("DELETE FROM product_master;");
    
    $productRows = [];
    $productSqlParams = [];
    for ($i = 1; $i <= 100; $i++) {
        $catId = random_int(1, $categoryCount);
        $slug = "product-item-model-{$i}-" . uniqid('', false);
        $desc = "This is a detailed description for product item model {$i}.";
        $price = random_int(49900, 250000);
        $stock = random_int(10, 200);
        $isFeatured = ($i <= 10) ? 'Y' : 'N';

        $productRows[] = "(?, ?, ?, ?, ?, ?, 'Y', ?, 'N')";
        array_push($productSqlParams, $catId, "Product Item Model {$i}", $slug, $desc, $price, $stock, $isFeatured);
    }
    
    $pdo->prepare("INSERT INTO product_master (product_master_category_id, product_master_name, product_master_slug, product_master_description, product_master_price, product_master_stock, product_master_is_active, product_master_is_featured, product_master_is_deleted) VALUES " . implode(',', $productRows))
        ->execute($productSqlParams);
}

/**
 * Seeds complex transactional orders and line-item details.
 */
function seedOrdersAndDetails(\PDO $pdo): void
{
    $pdo->exec("DELETE FROM order_detail;");
    $pdo->exec("DELETE FROM order_master;");
    
    $statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
    $orderMasterStmt = $pdo->prepare("INSERT INTO order_master (order_master_user_id, order_master_total, order_master_status) VALUES (?, ?, ?)");
    $orderDetailStmt = $pdo->prepare("INSERT INTO order_detail (order_detail_order_id, order_detail_product_id, order_detail_quantity, order_detail_price) VALUES (?, ?, ?, ?)");
    $updateTotalStmt = $pdo->prepare("UPDATE order_master SET order_master_total = ? WHERE order_master_id = ?");

    for ($orderId = 1; $orderId <= 200; $orderId++) {
        $userId = random_int(1, 50);
        $status = $statuses[array_rand($statuses)];
        $orderMasterStmt->execute([$userId, 0, $status]);

        $itemsInOrder = random_int(1, 4);
        $orderTotal = 0;

        for ($item = 0; $item < $itemsInOrder; $item++) {
            $productId = random_int(1, 100);
            $qty = random_int(1, 10);
            $unitPrice = random_int(999, 15000);
            
            $orderDetailStmt->execute([$orderId, $productId, $qty, $unitPrice]);
            $orderTotal += ($qty * $unitPrice);
        }

        $updateTotalStmt->execute([$orderTotal, $orderId]);
    }
}

/**
 * Main wrapper orchestration routine.
 */
function runDatabaseSeeder(): void
{
    $dbHost = 'db';
    $dbName = 'mvc_app';
    $dbUser = 'root';
    $dbPass = 'secret';

    $setFkCheck0 = 'SET FOREIGN_KEY_CHECKS = 0;';
    $setFkCheck1 = 'SET FOREIGN_KEY_CHECKS = 1;';
    
    $pdo = null;

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
        $pdo->exec($setFkCheck0);

        echo "🚀 Starting precise bulk database seeding (50-500 records)..." . PHP_EOL;

        $pdo->beginTransaction();

        $categories = ['Electronics', 'Clothing', 'Home & Kitchen', 'Books', 'Sports', 'Toys', 'Beauty', 'Automotive', 'Health', 'Grocery'];
        
        // Execute cleanly isolated sub-functions
        seedCategories($pdo, $categories);
        seedUsers($pdo);
        seedProducts($pdo, count($categories));
        seedOrdersAndDetails($pdo);

        $pdo->commit();
        $pdo->exec($setFkCheck1);

        // Beautiful Visual Layout Output
        echo PHP_EOL . "\033[1;36m┌────────────────────────────────────────────────────────┐\033[0m" . PHP_EOL;
        echo "\033[1;36m│ \033[1;32m✔\033[0m \033[1;37mSEED DATA COMPLETED SUCCESSFULLY                      \033[1;36m│\033[0m" . PHP_EOL;
        echo "\033[1;36m└────────────────────────────────────────────────────────┘\033[0m" . PHP_EOL;
        echo "  \033[32m✔\033[0m \033[37mInserted " . count($categories) . " categories into category_master.\033[0m" . PHP_EOL;
        echo "  \033[32m✔\033[0m \033[37mInserted 50 users into user_master.\033[0m" . PHP_EOL;
        echo "  \033[32m✔\033[0m \033[37mInserted 100 products into product_master.\033[0m" . PHP_EOL;
        echo "  \033[32m✔\033[0m \033[37mInserted 200 order headers into order_master.\033[0m" . PHP_EOL;
        echo "  \033[32m✔\033[0m \033[37mInserted order details and calculated header totals successfully.\033[0m" . PHP_EOL;
        echo PHP_EOL . "\033[42m\033[30m SUCCESS \033[0m All database tables are seeded precisely using schema column mappings." . PHP_EOL . PHP_EOL;

    } catch (\Throwable $e) {
        // Safe validation fallback structures
        if ($pdo !== null) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec($setFkCheck1);
        }

        echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Seeding Processing Failure: " . $e->getMessage() . PHP_EOL . PHP_EOL;
        exit(1);
    }
}

// Run the script
runDatabaseSeeder();
