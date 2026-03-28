<?php
declare(strict_types=1);

/**
 * Export missing simple variant data from source Magento DB.
 *
 * Usage:
 *   php export_missing_variants.php \
 *     --env=/path/to/app/etc/env.php \
 *     --input=/tmp/missing_variant_skus_to_import.json \
 *     --output=/tmp/missing_variants_export.json
 */

function getRequiredOption(array $options, string $key): string
{
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }

    return (string) $options[$key];
}

$options = getopt('', ['env:', 'input:', 'output:']);
$envFile = getRequiredOption($options, 'env');
$inputFile = getRequiredOption($options, 'input');
$outputFile = getRequiredOption($options, 'output');

if (!is_file($envFile)) {
    fwrite(STDERR, "env.php not found: {$envFile}\n");
    exit(1);
}
if (!is_file($inputFile)) {
    fwrite(STDERR, "Input JSON not found: {$inputFile}\n");
    exit(1);
}

$env = include $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!$db || empty($db['host']) || empty($db['dbname']) || empty($db['username'])) {
    fwrite(STDERR, "Invalid DB configuration in env.php\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db['host'], $db['dbname']),
    (string) $db['username'],
    (string) ($db['password'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$skus = json_decode((string) file_get_contents($inputFile), true);
if (!is_array($skus)) {
    fwrite(STDERR, "Invalid input JSON: {$inputFile}\n");
    exit(1);
}

$eavTables = [
    'catalog_product_entity_int',
    'catalog_product_entity_varchar',
    'catalog_product_entity_text',
    'catalog_product_entity_decimal',
    'catalog_product_entity_datetime',
];

$productStmt = $pdo->prepare(
    'SELECT * FROM catalog_product_entity WHERE sku = :sku LIMIT 1'
);
$parentSkuStmt = $pdo->prepare(
    'SELECT p.sku
       FROM catalog_product_super_link sl
       JOIN catalog_product_entity p ON p.entity_id = sl.parent_id
      WHERE sl.product_id = :entity_id
      LIMIT 1'
);
$websiteStmt = $pdo->prepare(
    'SELECT * FROM catalog_product_website WHERE product_id = :entity_id'
);
$stockItemStmt = $pdo->prepare(
    'SELECT * FROM cataloginventory_stock_item WHERE product_id = :entity_id LIMIT 1'
);
$sourceItemStmt = $pdo->prepare(
    'SELECT * FROM inventory_source_item WHERE sku = :sku'
);

$eavStmts = [];
foreach ($eavTables as $table) {
    $eavStmts[$table] = $pdo->prepare(
        "SELECT * FROM {$table} WHERE entity_id = :entity_id"
    );
}

$result = [
    'meta' => [
        'exported_at' => date('c'),
        'source_env' => $envFile,
        'input_count' => count($skus),
        'eav_tables' => $eavTables,
    ],
    'products' => [],
    'missing_in_source' => [],
];

foreach ($skus as $rawSku) {
    $sku = trim((string) $rawSku);
    if ($sku === '') {
        continue;
    }

    $productStmt->execute(['sku' => $sku]);
    $product = $productStmt->fetch();
    if (!$product) {
        $result['missing_in_source'][] = $sku;
        continue;
    }

    $entityId = (int) $product['entity_id'];

    $parentSkuStmt->execute(['entity_id' => $entityId]);
    $parentSku = (string) ($parentSkuStmt->fetchColumn() ?: '');

    $websiteStmt->execute(['entity_id' => $entityId]);
    $websites = $websiteStmt->fetchAll();

    $stockItemStmt->execute(['entity_id' => $entityId]);
    $stockItem = $stockItemStmt->fetch() ?: null;

    $sourceItemStmt->execute(['sku' => $sku]);
    $sourceItems = $sourceItemStmt->fetchAll();

    $eavData = [];
    foreach ($eavTables as $table) {
        $eavStmts[$table]->execute(['entity_id' => $entityId]);
        $eavData[$table] = $eavStmts[$table]->fetchAll();
    }

    $result['products'][$sku] = [
        'product' => $product,
        'parent_sku' => $parentSku,
        'websites' => $websites,
        'stock_item' => $stockItem,
        'source_items' => $sourceItems,
        'eav' => $eavData,
    ];
}

$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "Failed to encode export JSON\n");
    exit(1);
}

if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "Failed to write output file: {$outputFile}\n");
    exit(1);
}

echo "Export done\n";
echo "Input SKUs: " . count($skus) . PHP_EOL;
echo "Exported products: " . count($result['products']) . PHP_EOL;
echo "Missing in source: " . count($result['missing_in_source']) . PHP_EOL;
echo "Output: {$outputFile}" . PHP_EOL;
