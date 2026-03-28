<?php
declare(strict_types=1);

/**
 * Import missing simple variants into target Magento DB.
 *
 * Usage:
 *   php import_missing_variants.php \
 *     --env=/path/to/app/etc/env.php \
 *     --input=/tmp/missing_variants_export.json
 */

function getRequiredOption(array $options, string $key): string
{
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }

    return (string) $options[$key];
}

function getPdoFromEnv(string $envFile): PDO
{
    if (!is_file($envFile)) {
        fwrite(STDERR, "env.php not found: {$envFile}\n");
        exit(1);
    }

    $env = include $envFile;
    $db = $env['db']['connection']['default'] ?? null;
    if (!$db || empty($db['host']) || empty($db['dbname']) || empty($db['username'])) {
        fwrite(STDERR, "Invalid DB configuration in env.php\n");
        exit(1);
    }

    return new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db['host'], $db['dbname']),
        (string) $db['username'],
        (string) ($db['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function insertRow(PDO $pdo, string $table, array $row, bool $ignore = false): void
{
    if (empty($row)) {
        return;
    }

    $columns = array_keys($row);
    $quotedColumns = array_map(
        static fn(string $col): string => "`{$col}`",
        $columns
    );
    $placeholders = array_map(
        static fn(string $col): string => ':' . $col,
        $columns
    );

    $sql = sprintf(
        'INSERT %sINTO `%s` (%s) VALUES (%s)',
        $ignore ? 'IGNORE ' : '',
        $table,
        implode(',', $quotedColumns),
        implode(',', $placeholders)
    );

    $stmt = $pdo->prepare($sql);
    foreach ($row as $col => $value) {
        $stmt->bindValue(':' . $col, $value);
    }
    $stmt->execute();
}

function removeKeys(array $row, array $keysToUnset): array
{
    foreach ($keysToUnset as $key) {
        unset($row[$key]);
    }
    return $row;
}

$options = getopt('', ['env:', 'input:']);
$envFile = getRequiredOption($options, 'env');
$inputFile = getRequiredOption($options, 'input');

if (!is_file($inputFile)) {
    fwrite(STDERR, "Input JSON not found: {$inputFile}\n");
    exit(1);
}

$payload = json_decode((string) file_get_contents($inputFile), true);
if (!is_array($payload) || !isset($payload['products']) || !is_array($payload['products'])) {
    fwrite(STDERR, "Invalid input JSON structure: {$inputFile}\n");
    exit(1);
}

$pdo = getPdoFromEnv($envFile);

$findBySkuStmt = $pdo->prepare(
    'SELECT entity_id, type_id FROM catalog_product_entity WHERE sku = :sku LIMIT 1'
);

$stats = [
    'input_products' => count($payload['products']),
    'imported' => 0,
    'skipped_exists' => 0,
    'skipped_no_parent' => 0,
    'skipped_parent_not_configurable' => 0,
    'skipped_invalid_source' => 0,
    'errors' => 0,
];

$errorSkus = [];

foreach ($payload['products'] as $sku => $productData) {
    $sku = trim((string) $sku);
    if ($sku === '') {
        continue;
    }

    try {
        $findBySkuStmt->execute(['sku' => $sku]);
        $existing = $findBySkuStmt->fetch();
        if ($existing) {
            $stats['skipped_exists']++;
            continue;
        }

        $sourceProduct = $productData['product'] ?? null;
        if (!is_array($sourceProduct) || empty($sourceProduct['sku'])) {
            $stats['skipped_invalid_source']++;
            continue;
        }

        $parentSku = trim((string) ($productData['parent_sku'] ?? ''));
        if ($parentSku === '') {
            $stats['skipped_no_parent']++;
            continue;
        }

        $findBySkuStmt->execute(['sku' => $parentSku]);
        $parent = $findBySkuStmt->fetch();
        if (!$parent) {
            $stats['skipped_no_parent']++;
            continue;
        }
        if (($parent['type_id'] ?? '') !== 'configurable') {
            $stats['skipped_parent_not_configurable']++;
            continue;
        }

        $parentId = (int) $parent['entity_id'];

        $pdo->beginTransaction();

        $newProduct = removeKeys($sourceProduct, ['entity_id']);
        $newProduct['sku'] = $sku;
        insertRow($pdo, 'catalog_product_entity', $newProduct, false);
        $newEntityId = (int) $pdo->lastInsertId();

        $eavTables = [
            'catalog_product_entity_int',
            'catalog_product_entity_varchar',
            'catalog_product_entity_text',
            'catalog_product_entity_decimal',
            'catalog_product_entity_datetime',
        ];
        $pkByTable = [
            'catalog_product_entity_int' => 'value_id',
            'catalog_product_entity_varchar' => 'value_id',
            'catalog_product_entity_text' => 'value_id',
            'catalog_product_entity_decimal' => 'value_id',
            'catalog_product_entity_datetime' => 'value_id',
        ];

        foreach ($eavTables as $table) {
            $rows = $productData['eav'][$table] ?? [];
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row = removeKeys($row, [$pkByTable[$table]]);
                $row['entity_id'] = $newEntityId;
                insertRow($pdo, $table, $row, true);
            }
        }

        $websites = $productData['websites'] ?? [];
        if (is_array($websites)) {
            foreach ($websites as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['product_id'] = $newEntityId;
                insertRow($pdo, 'catalog_product_website', $row, true);
            }
        }

        $stockItem = $productData['stock_item'] ?? null;
        if (is_array($stockItem)) {
            $stockItem = removeKeys($stockItem, ['item_id']);
            $stockItem['product_id'] = $newEntityId;
            insertRow($pdo, 'cataloginventory_stock_item', $stockItem, true);

            $stockStatusRow = [
                'product_id' => $newEntityId,
                'website_id' => (int) ($stockItem['website_id'] ?? 1),
                'stock_id' => (int) ($stockItem['stock_id'] ?? 1),
                'qty' => (float) ($stockItem['qty'] ?? 0),
                'stock_status' => (int) ($stockItem['is_in_stock'] ?? 0),
            ];
            insertRow($pdo, 'cataloginventory_stock_status', $stockStatusRow, true);
        }

        $sourceItems = $productData['source_items'] ?? [];
        if (is_array($sourceItems) && !empty($sourceItems)) {
            foreach ($sourceItems as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row = removeKeys($row, ['source_item_id']);
                $row['sku'] = $sku;
                insertRow($pdo, 'inventory_source_item', $row, true);
            }
        } elseif (is_array($stockItem)) {
            $defaultSource = [
                'source_code' => 'default',
                'sku' => $sku,
                'quantity' => (float) ($stockItem['qty'] ?? 0),
                'status' => (int) ($stockItem['is_in_stock'] ?? 0),
            ];
            insertRow($pdo, 'inventory_source_item', $defaultSource, true);
        }

        insertRow($pdo, 'catalog_product_super_link', [
            'product_id' => $newEntityId,
            'parent_id' => $parentId,
        ], true);

        insertRow($pdo, 'catalog_product_relation', [
            'parent_id' => $parentId,
            'child_id' => $newEntityId,
        ], true);

        $pdo->commit();
        $stats['imported']++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $stats['errors']++;
        $errorSkus[$sku] = $e->getMessage();
    }
}

echo "Import done\n";
foreach ($stats as $k => $v) {
    echo "{$k}: {$v}\n";
}

if (!empty($errorSkus)) {
    $errorFile = '/tmp/import_missing_variants.errors.json';
    file_put_contents(
        $errorFile,
        json_encode($errorSkus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    echo "Error details: {$errorFile}\n";
}
