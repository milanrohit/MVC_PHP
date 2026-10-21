<?php

declare(strict_types=1);

// 1. FIXED: Set the correct uppercase App and folder path matching composer rules
namespace App\Support;

use RuntimeException;
use Throwable;
use App\Helpers\SqlHelper as SqlHelper;
use App\Helpers\ConstantHelper as ConstantHelper;
use App\Helpers\StringHelper as StringHelper;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for environment testing data instantiation.
 */
final class DatabaseSeeder
{
    public static function run(): void
    {
        $isCli = PHP_SAPI === 'cli';
        $log = static function (string $msg) use ($isCli): void {
            echo $isCli ? "\033[1;32m[Seeder] {$msg}\033[0m\n" : "<p style='color: green;'>[Seeder] {$msg}</p>";
        };

        $log('Starting relational framework seed mapping...');

        SqlHelper::transactional(static function () use ($log): void {
            SqlHelper::delete('order_detail', '1=1');
            SqlHelper::delete('order_master', '1=1');
            SqlHelper::delete('product_master', '1=1');
            SqlHelper::delete('category_master', '1=1');
            SqlHelper::delete('user_master', '1=1');
            $log('Truncated all existing sample row definitions cleanly.');

            $userIds = self::seedUsers();
            $log(sprintf('Seeded %d users.', count($userIds)));

            $categoryIds = self::seedCategories();
            $log(sprintf('Seeded %d categories.', count($categoryIds)));

            $productIdsWithPrices = self::seedProducts($categoryIds);
            $log(sprintf('Seeded %d items.', count($productIdsWithPrices)));

            $orderCount = self::seedOrdersAndDetails($userIds, $productIdsWithPrices);
            $log(sprintf('Successfully injected %d customer orders.', $orderCount));
        });

        $log('Database schema testing configuration process complete.');
    }

    private static function seedUsers(): array
    {
        $users = [
            [
                'user_master_name'        => 'System Administrator',
                'user_master_email'       => 'admin@mvc-app.com',
                'user_master_password'    => password_hash('AdminSecure2026!', ConstantHelper::HASH_ARGON2ID),
                'user_master_role'        => 'admin',
                'user_master_is_active'   => 'Y',
                'user_master_is_verified' => 'Y',
            ],
            [
                'user_master_name'        => 'John Doe Customer',
                'user_master_email'       => 'john.doe@gmail.com',
                'user_master_password'    => password_hash('CustomerPass123!', ConstantHelper::HASH_ARGON2ID),
                'user_master_role'        => 'customer',
                'user_master_is_active'   => 'Y',
                'user_master_is_verified' => 'Y',
            ]
        ];

        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = SqlHelper::insert('user_master', $user);
        }
        return $userIds;
    }

    private static function seedCategories(): array
    {
        $categories = ['Electronics', 'Home & Living', 'Fitness & Apparel'];
        $categoryIds = [];
        foreach ($categories as $catName) {
            $categoryIds[] = SqlHelper::insert('category_master', [
                'category_master_name'      => $catName,
                'category_master_slug'      => StringHelper::slug($catName),
                'category_master_is_active' => 'Y',
            ]);
        }
        return $categoryIds;
    }

    private static function seedProducts(array $categoryIds): array
    {
        $productBlueprints = [
            'Pro Wireless Headphones'    => 24900,
            'Mechanical Gaming Keyboard' => 12950,
            'Ergonomic Desk Chair'       => 49900,
        ];

        $productIdsWithPrices = [];
        $i = 0;
        foreach ($productBlueprints as $name => $price) {
            $catId = $categoryIds[$i % count($categoryIds)];
            $productId = SqlHelper::insert('product_master', [
                'product_master_category_id' => $catId,
                'product_master_name'        => $name,
                'product_master_slug'        => StringHelper::slug($name),
                'product_master_description' => "High-performance evaluation model for {$name}.",
                'product_master_price'       => $price,
                'product_master_stock'       => 50,
                'product_master_is_active'   => 'Y',
                'product_master_is_featured' => 'N',
            ]);

            $productIdsWithPrices[] = ['id' => $productId, 'price' => $price];
            $i++;
        }
        return $productIdsWithPrices;
    }

    private static function seedOrdersAndDetails(array $userIds, array $productIdsWithPrices): int
    {
        $orderCount = 0;
        foreach ($userIds as $userId) {
            $calculatedTotal = 0;
            $itemsToInsert = [];

            foreach ($productIdsWithPrices as $product) {
                $qty = 1;
                $linePrice = $product['price'] * $qty;
                $calculatedTotal += $linePrice;

                $itemsToInsert[] = [
                    'product_id' => $product['id'],
                    'quantity'   => $qty,
                    'price'      => $product['price']
                ];
            }

            $orderId = SqlHelper::insert('order_master', [
                'order_master_user_id' => $userId,
                'order_master_total'   => $calculatedTotal,
                'order_master_status'  => 'paid',
            ]);

            foreach ($itemsToInsert as $item) {
                SqlHelper::insert('order_detail', [
                    'order_detail_order_id'   => $orderId,
                    'order_detail_product_id' => $item['product_id'],
                    'order_detail_quantity'   => $item['quantity'],
                    'order_detail_price'      => $item['price'],
                ]);
            }
            $orderCount++;
        }
        return $orderCount;
    }
}
