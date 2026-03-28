<?php
declare(strict_types=1);

/**
 * Force salable MSI/source rows for all products in a category (+ configurable children).
 *
 * Usage:
 *   php fix_category_msi_salable.php \
 *     --env=/home/vibeadd/vibeadd.com/hyvatestproject/app/etc/env.php \
 *     --category-id=27 \
 *     --source-code=austria \
 *     --qty=100
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function requiredOpt(array $opts, string $key): string
{
    if (!isset($opts[$key]) || trim((string) $opts[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }
    return (string) $opts[$key];
}

$opts = getopt('', ['env:', 'category-id:', 'source-code::', 'qty::', 'dry-run::']);
$envFile = requiredOpt($opts, 'env');
$categoryId = (int) requiredOpt($opts, 'category-id');
$sourceCode = (string) ($opts['source-code'] ?? 'austria');
$qty = (float) ($opts['qty'] ?? 100);
$dryRun = isset($opts['dry-run']);

if (!is_file($envFile)) {
    fwrite(STDERR, "env.php not found: {$envFile}\n");
    exit(1);
}

$env = include $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!is_array($db)) {
    throw new RuntimeException('DB config missing in env.php');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', (string) $db['host'], (string) $db['dbname']),
    (string) $db['username'],
    (string) ($db['password'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$sourceExists = $pdo->prepare('SELECT 1 FROM inventory_source WHERE source_code = :source_code LIMIT 1');
$sourceExists->execute(['source_code' => $sourceCode]);
if (!$sourceExists->fetchColumn()) {
    throw new RuntimeException("Source does not exist: {$sourceCode}");
}

$allProducts = [];

$baseStmt = $pdo->prepare(
    'SELECT p.entity_id, p.sku
       FROM catalog_category_product ccp
       JOIN catalog_product_entity p ON p.entity_id = ccp.product_id
      WHERE ccp.category_id = :category_id'
);
$baseStmt->execute(['category_id' => $categoryId]);
foreach ($baseStmt->fetchAll() as $r) {
    $allProducts[(int) $r['entity_id']] = (string) $r['sku'];
}

$childStmt = $pdo->prepare(
    "SELECT c.entity_id, c.sku
       FROM catalog_category_product ccp
       JOIN catalog_product_entity p ON p.entity_id = ccp.product_id AND p.type_id = 'configurable'
       JOIN catalog_product_super_link sl ON sl.parent_id = p.entity_id
       JOIN catalog_product_entity c ON c.entity_id = sl.product_id
      WHERE ccp.category_id = :category_id"
);
$childStmt->execute(['category_id' => $categoryId]);
foreach ($childStmt->fetchAll() as $r) {
    $allProducts[(int) $r['entity_id']] = (string) $r['sku'];
}

if (!$allProducts) {
    throw new RuntimeException("No products found in category {$categoryId}");
}

$websiteStmt = $pdo->prepare(
    'SELECT website_id FROM catalog_product_website WHERE product_id = :product_id ORDER BY website_id'
);

$upsertSource = $pdo->prepare(
    'INSERT INTO inventory_source_item (source_code, sku, quantity, status)
     VALUES (:source_code, :sku, :quantity, 1)
     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), status = 1'
);

$upsertStockItem = $pdo->prepare(
    'INSERT INTO cataloginventory_stock_item
        (product_id, website_id, stock_id, qty, is_in_stock, manage_stock, use_config_manage_stock)
     VALUES
        (:product_id, 0, 1, :qty, 1, 1, 1)
     ON DUPLICATE KEY UPDATE
        qty = VALUES(qty),
        is_in_stock = 1,
        manage_stock = 1,
        use_config_manage_stock = 1'
);

$upsertStockStatus = $pdo->prepare(
    'INSERT INTO cataloginventory_stock_status
        (product_id, website_id, stock_id, qty, stock_status)
     VALUES
        (:product_id, :website_id, 1, :qty, 1)
     ON DUPLICATE KEY UPDATE
        qty = VALUES(qty),
        stock_status = 1'
);

$updatedSource = 0;
$updatedStockItem = 0;
$updatedStockStatus = 0;

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    foreach ($allProducts as $productId => $sku) {
        if (!$dryRun) {
            $upsertSource->execute([
                'source_code' => $sourceCode,
                'sku' => $sku,
                'quantity' => $qty,
            ]);
        }
        $updatedSource++;

        if (!$dryRun) {
            $upsertStockItem->execute([
                'product_id' => $productId,
                'qty' => $qty,
            ]);
        }
        $updatedStockItem++;

        $websiteStmt->execute(['product_id' => $productId]);
        $websiteIds = array_values(array_unique(array_map('intval', $websiteStmt->fetchAll(PDO::FETCH_COLUMN))));
        if (!$websiteIds) {
            $websiteIds = [0];
        }

        foreach ($websiteIds as $websiteId) {
            if (!$dryRun) {
                $upsertStockStatus->execute([
                    'product_id' => $productId,
                    'website_id' => $websiteId,
                    'qty' => $qty,
                ]);
            }
            $updatedStockStatus++;
        }
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$result = [
    'category_id' => $categoryId,
    'products_total' => count($allProducts),
    'source_code' => $sourceCode,
    'qty' => $qty,
    'dry_run' => $dryRun,
    'source_rows_upserted' => $updatedSource,
    'stock_item_rows_upserted' => $updatedStockItem,
    'stock_status_rows_upserted' => $updatedStockStatus,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
