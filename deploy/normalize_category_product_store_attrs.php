<?php
declare(strict_types=1);

/**
 * Remove store-scoped attribute overrides for products in one category
 * (+ configurable children), so storefront uses canonical store_id=0 values.
 *
 * Usage:
 *   php normalize_category_product_store_attrs.php \
 *     --env=/home/vibeadd/vibeadd.com/hyvatestproject/app/etc/env.php \
 *     --category-id=27 \
 *     --attributes=name,url_key
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function requiredOption(array $opts, string $name): string
{
    if (!isset($opts[$name]) || trim((string) $opts[$name]) === '') {
        fwrite(STDERR, "Missing required option --{$name}\n");
        exit(1);
    }
    return (string) $opts[$name];
}

$opts = getopt('', ['env:', 'category-id:', 'attributes::', 'dry-run::']);
$envFile = requiredOption($opts, 'env');
$categoryId = (int) requiredOption($opts, 'category-id');
$attributeCodes = array_values(array_filter(array_map(
    static fn($x) => trim((string) $x),
    explode(',', (string) ($opts['attributes'] ?? 'name,url_key'))
)));
$dryRun = isset($opts['dry-run']);

if (!is_file($envFile)) {
    throw new RuntimeException("env.php not found: {$envFile}");
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

if (!$attributeCodes) {
    throw new RuntimeException('No attributes provided');
}

$attrIn = implode(',', array_fill(0, count($attributeCodes), '?'));
$attrStmt = $pdo->prepare(
    "SELECT attribute_id, attribute_code
       FROM eav_attribute
      WHERE entity_type_id = (
              SELECT entity_type_id
                FROM eav_entity_type
               WHERE entity_type_code = 'catalog_product'
               LIMIT 1
            )
        AND attribute_code IN ({$attrIn})"
);
$attrStmt->execute($attributeCodes);
$attributeRows = $attrStmt->fetchAll();

$attributeIds = [];
foreach ($attributeRows as $row) {
    $attributeIds[(string) $row['attribute_code']] = (int) $row['attribute_id'];
}

$missingAttributes = array_values(array_diff($attributeCodes, array_keys($attributeIds)));
if ($missingAttributes) {
    throw new RuntimeException('Unknown attributes: ' . implode(', ', $missingAttributes));
}

$productIds = [];

$baseProductsStmt = $pdo->prepare(
    'SELECT product_id
       FROM catalog_category_product
      WHERE category_id = :category_id'
);
$baseProductsStmt->execute(['category_id' => $categoryId]);
foreach ($baseProductsStmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $productIds[(int) $id] = true;
}

$childrenStmt = $pdo->prepare(
    "SELECT sl.product_id
       FROM catalog_category_product ccp
       JOIN catalog_product_entity p
         ON p.entity_id = ccp.product_id
        AND p.type_id = 'configurable'
       JOIN catalog_product_super_link sl
         ON sl.parent_id = p.entity_id
      WHERE ccp.category_id = :category_id"
);
$childrenStmt->execute(['category_id' => $categoryId]);
foreach ($childrenStmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $productIds[(int) $id] = true;
}

$productIds = array_keys($productIds);
if (!$productIds) {
    throw new RuntimeException("No products found in category {$categoryId}");
}

$idChunks = array_chunk($productIds, 500);
$attrIdList = array_values($attributeIds);
$deleted = 0;
$matched = 0;

foreach ($idChunks as $chunk) {
    $prodIn = implode(',', array_fill(0, count($chunk), '?'));
    $attrInLocal = implode(',', array_fill(0, count($attrIdList), '?'));

    $countSql = "SELECT COUNT(*)
                   FROM catalog_product_entity_varchar
                  WHERE store_id <> 0
                    AND entity_id IN ({$prodIn})
                    AND attribute_id IN ({$attrInLocal})";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(array_merge($chunk, $attrIdList));
    $matched += (int) $countStmt->fetchColumn();

    if ($dryRun) {
        continue;
    }

    $delSql = "DELETE FROM catalog_product_entity_varchar
                WHERE store_id <> 0
                  AND entity_id IN ({$prodIn})
                  AND attribute_id IN ({$attrInLocal})";
    $delStmt = $pdo->prepare($delSql);
    $delStmt->execute(array_merge($chunk, $attrIdList));
    $deleted += $delStmt->rowCount();
}

$result = [
    'category_id' => $categoryId,
    'products_total' => count($productIds),
    'attributes' => array_values($attributeCodes),
    'matched_store_rows' => $matched,
    'deleted_store_rows' => $deleted,
    'dry_run' => $dryRun,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
