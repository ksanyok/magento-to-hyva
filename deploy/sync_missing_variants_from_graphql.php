<?php
declare(strict_types=1);

/**
 * Create missing child variants on target Magento instance by using
 * live original GraphQL as source-of-truth for variant SKU/attributes/prices.
 *
 * Usage:
 *   php sync_missing_variants_from_graphql.php \
 *     --env=/path/to/app/etc/env.php \
 *     --input=/tmp/orig_vs_hyva_missing_images.unique.json \
 *     --graphql=https://shop.ftc-cashmere.com/graphql
 */

function requiredOpt(array $opts, string $key): string
{
    if (!isset($opts[$key]) || trim((string) $opts[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }
    return (string) $opts[$key];
}

function dbFromEnv(string $envFile): PDO
{
    if (!is_file($envFile)) {
        fwrite(STDERR, "env.php not found: {$envFile}\n");
        exit(1);
    }
    $env = include $envFile;
    $db = $env['db']['connection']['default'] ?? null;
    if (!$db || empty($db['host']) || empty($db['dbname']) || empty($db['username'])) {
        fwrite(STDERR, "Invalid DB config in env.php\n");
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
    if (!$row) {
        return;
    }
    $cols = array_keys($row);
    $sql = sprintf(
        'INSERT %sINTO `%s` (%s) VALUES (%s)',
        $ignore ? 'IGNORE ' : '',
        $table,
        implode(',', array_map(static fn($c) => "`{$c}`", $cols)),
        implode(',', array_map(static fn($c) => ':' . $c, $cols))
    );
    $st = $pdo->prepare($sql);
    foreach ($row as $k => $v) {
        $st->bindValue(':' . $k, $v);
    }
    $st->execute();
}

function upsertValue(PDO $pdo, string $table, array $row, string $valueColumn = 'value'): void
{
    $cols = array_keys($row);
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE `%s` = VALUES(`%s`)',
        $table,
        implode(',', array_map(static fn($c) => "`{$c}`", $cols)),
        implode(',', array_map(static fn($c) => ':' . $c, $cols)),
        $valueColumn,
        $valueColumn
    );
    $st = $pdo->prepare($sql);
    foreach ($row as $k => $v) {
        $st->bindValue(':' . $k, $v);
    }
    $st->execute();
}

function firstMediaPathFromUrl(?string $url): string
{
    if (!$url) {
        return '';
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    $needle = '/catalog/product/';
    $pos = strpos($path, $needle);
    if ($pos === false) {
        return '';
    }
    $rel = substr($path, $pos + strlen('/catalog/product'));
    $rel = '/' . ltrim($rel, '/');
    return $rel;
}

function fetchGraphqlParent(string $endpoint, string $parentSku): ?array
{
    $query = <<<'GQL'
query($sku: String!) {
  products(filter: { sku: { eq: $sku } }) {
    items {
      sku
      ... on ConfigurableProduct {
        variants {
          attributes {
            code
            value_index
            label
          }
          product {
            sku
            name
            media_gallery {
              url
            }
            price_range {
              minimum_price {
                regular_price {
                  value
                  currency
                }
                final_price {
                  value
                  currency
                }
              }
            }
          }
        }
      }
    }
  }
}
GQL;

    $payload = json_encode([
        'query' => $query,
        'variables' => ['sku' => $parentSku],
    ]);
    if ($payload === false) {
        return null;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $status < 200 || $status >= 300) {
        return null;
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !empty($json['errors'])) {
        return null;
    }
    $items = $json['data']['products']['items'] ?? [];
    if (!is_array($items) || empty($items)) {
        return null;
    }
    return $items[0];
}

$opts = getopt('', ['env:', 'input:', 'graphql::']);
$envFile = requiredOpt($opts, 'env');
$inputFile = requiredOpt($opts, 'input');
$graphql = (string) ($opts['graphql'] ?? 'https://shop.ftc-cashmere.com/graphql');

if (!is_file($inputFile)) {
    fwrite(STDERR, "Input JSON not found: {$inputFile}\n");
    exit(1);
}

$rows = json_decode((string) file_get_contents($inputFile), true);
if (!is_array($rows)) {
    fwrite(STDERR, "Invalid input JSON: {$inputFile}\n");
    exit(1);
}

$pdo = dbFromEnv($envFile);

$findBySkuStmt = $pdo->prepare('SELECT entity_id, type_id, sku FROM catalog_product_entity WHERE sku = :sku LIMIT 1');
$childrenStmt = $pdo->prepare(
    'SELECT c.entity_id, c.sku
       FROM catalog_product_super_link sl
       JOIN catalog_product_entity c ON c.entity_id = sl.product_id
      WHERE sl.parent_id = :parent_id
      ORDER BY c.entity_id ASC'
);
$entityStmt = $pdo->prepare('SELECT * FROM catalog_product_entity WHERE entity_id = :entity_id LIMIT 1');
$websiteStmt = $pdo->prepare('SELECT * FROM catalog_product_website WHERE product_id = :entity_id');
$stockStmt = $pdo->prepare('SELECT * FROM cataloginventory_stock_item WHERE product_id = :entity_id LIMIT 1');
$sourceBySkuStmt = $pdo->prepare('SELECT * FROM inventory_source_item WHERE sku = :sku');

$eavTables = [
    'catalog_product_entity_int',
    'catalog_product_entity_varchar',
    'catalog_product_entity_text',
    'catalog_product_entity_decimal',
    'catalog_product_entity_datetime',
];
$eavPk = [
    'catalog_product_entity_int' => 'value_id',
    'catalog_product_entity_varchar' => 'value_id',
    'catalog_product_entity_text' => 'value_id',
    'catalog_product_entity_decimal' => 'value_id',
    'catalog_product_entity_datetime' => 'value_id',
];
$eavSelect = [];
foreach ($eavTables as $t) {
    $eavSelect[$t] = $pdo->prepare("SELECT * FROM {$t} WHERE entity_id = :entity_id");
}

$attrMap = [];
$attrRows = $pdo->query(
    "SELECT attribute_id, attribute_code, backend_type
       FROM eav_attribute
      WHERE entity_type_id = (
            SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product' LIMIT 1
      )"
)->fetchAll();
foreach ($attrRows as $r) {
    $attrMap[(string) $r['attribute_code']] = [
        'id' => (int) $r['attribute_id'],
        'backend_type' => (string) $r['backend_type'],
    ];
}

$missingSkuSet = [];
foreach ($rows as $r) {
    if (!is_array($r) || empty($r['sku'])) {
        continue;
    }
    $sku = trim((string) $r['sku']);
    if ($sku === '') {
        continue;
    }
    $findBySkuStmt->execute(['sku' => $sku]);
    if (!$findBySkuStmt->fetch()) {
        $missingSkuSet[$sku] = true;
    }
}
$missingSkus = array_keys($missingSkuSet);
sort($missingSkus);

$byParent = [];
foreach ($missingSkus as $sku) {
    $parentSku = explode('_', $sku, 2)[0];
    $byParent[$parentSku][] = $sku;
}

$stats = [
    'input_missing_skus' => count($missingSkus),
    'parents' => count($byParent),
    'created' => 0,
    'skipped_exists' => 0,
    'skipped_no_parent' => 0,
    'skipped_parent_not_configurable' => 0,
    'skipped_no_template' => 0,
    'skipped_no_graphql_parent' => 0,
    'skipped_no_graphql_variant' => 0,
    'errors' => 0,
];
$errors = [];

foreach ($byParent as $parentSku => $childSkus) {
    $findBySkuStmt->execute(['sku' => $parentSku]);
    $parentRow = $findBySkuStmt->fetch();
    if (!$parentRow) {
        $stats['skipped_no_parent'] += count($childSkus);
        continue;
    }
    if (($parentRow['type_id'] ?? '') !== 'configurable') {
        $stats['skipped_parent_not_configurable'] += count($childSkus);
        continue;
    }
    $parentId = (int) $parentRow['entity_id'];

    $childrenStmt->execute(['parent_id' => $parentId]);
    $existingChildren = $childrenStmt->fetchAll();
    if (!$existingChildren) {
        $stats['skipped_no_template'] += count($childSkus);
        continue;
    }
    $templateChild = $existingChildren[0];
    $templateId = (int) $templateChild['entity_id'];
    $templateSku = (string) $templateChild['sku'];

    $entityStmt->execute(['entity_id' => $templateId]);
    $templateProduct = $entityStmt->fetch();
    if (!$templateProduct) {
        $stats['skipped_no_template'] += count($childSkus);
        continue;
    }

    $templateEav = [];
    foreach ($eavTables as $t) {
        $eavSelect[$t]->execute(['entity_id' => $templateId]);
        $templateEav[$t] = $eavSelect[$t]->fetchAll();
    }
    $websiteStmt->execute(['entity_id' => $templateId]);
    $templateWebsites = $websiteStmt->fetchAll();
    $stockStmt->execute(['entity_id' => $templateId]);
    $templateStock = $stockStmt->fetch() ?: null;
    $sourceBySkuStmt->execute(['sku' => $templateSku]);
    $templateSources = $sourceBySkuStmt->fetchAll();

    $gqlParent = fetchGraphqlParent($graphql, $parentSku);
    if (!$gqlParent) {
        $stats['skipped_no_graphql_parent'] += count($childSkus);
        continue;
    }
    $variantMap = [];
    foreach (($gqlParent['variants'] ?? []) as $variant) {
        $vSku = (string) ($variant['product']['sku'] ?? '');
        if ($vSku !== '') {
            $variantMap[$vSku] = $variant;
        }
    }

    foreach ($childSkus as $newSku) {
        $findBySkuStmt->execute(['sku' => $newSku]);
        if ($findBySkuStmt->fetch()) {
            $stats['skipped_exists']++;
            continue;
        }

        $variant = $variantMap[$newSku] ?? null;
        if (!$variant) {
            $stats['skipped_no_graphql_variant']++;
            continue;
        }

        try {
            $pdo->beginTransaction();

            $newProduct = $templateProduct;
            unset($newProduct['entity_id']);
            $newProduct['sku'] = $newSku;
            insertRow($pdo, 'catalog_product_entity', $newProduct, false);
            $newId = (int) $pdo->lastInsertId();

            foreach ($eavTables as $t) {
                foreach (($templateEav[$t] ?? []) as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    unset($r[$eavPk[$t]]);
                    $r['entity_id'] = $newId;
                    insertRow($pdo, $t, $r, true);
                }
            }

            foreach ($templateWebsites as $wr) {
                if (!is_array($wr)) {
                    continue;
                }
                $wr['product_id'] = $newId;
                insertRow($pdo, 'catalog_product_website', $wr, true);
            }

            if (is_array($templateStock)) {
                $st = $templateStock;
                unset($st['item_id']);
                $st['product_id'] = $newId;
                insertRow($pdo, 'cataloginventory_stock_item', $st, true);
                insertRow($pdo, 'cataloginventory_stock_status', [
                    'product_id' => $newId,
                    'website_id' => (int) ($st['website_id'] ?? 1),
                    'stock_id' => (int) ($st['stock_id'] ?? 1),
                    'qty' => (float) ($st['qty'] ?? 0),
                    'stock_status' => (int) ($st['is_in_stock'] ?? 0),
                ], true);
            }

            if ($templateSources) {
                foreach ($templateSources as $sr) {
                    if (!is_array($sr)) {
                        continue;
                    }
                    unset($sr['source_item_id']);
                    $sr['sku'] = $newSku;
                    insertRow($pdo, 'inventory_source_item', $sr, true);
                }
            } elseif (is_array($templateStock)) {
                insertRow($pdo, 'inventory_source_item', [
                    'source_code' => 'default',
                    'sku' => $newSku,
                    'quantity' => (float) ($templateStock['qty'] ?? 0),
                    'status' => (int) ($templateStock['is_in_stock'] ?? 0),
                ], true);
            }

            insertRow($pdo, 'catalog_product_super_link', [
                'product_id' => $newId,
                'parent_id' => $parentId,
            ], true);
            insertRow($pdo, 'catalog_product_relation', [
                'parent_id' => $parentId,
                'child_id' => $newId,
            ], true);

            $name = trim((string) ($variant['product']['name'] ?? ''));
            $regular = (float) ($variant['product']['price_range']['minimum_price']['regular_price']['value'] ?? 0);
            $final = (float) ($variant['product']['price_range']['minimum_price']['final_price']['value'] ?? 0);
            $firstMedia = firstMediaPathFromUrl($variant['product']['media_gallery'][0]['url'] ?? '');

            if ($name !== '' && isset($attrMap['name'])) {
                upsertValue($pdo, 'catalog_product_entity_varchar', [
                    'attribute_id' => $attrMap['name']['id'],
                    'store_id' => 0,
                    'entity_id' => $newId,
                    'value' => $name,
                ]);
            }

            if ($regular > 0 && isset($attrMap['price'])) {
                upsertValue($pdo, 'catalog_product_entity_decimal', [
                    'attribute_id' => $attrMap['price']['id'],
                    'store_id' => 0,
                    'entity_id' => $newId,
                    'value' => $regular,
                ]);
            }

            if ($final > 0 && $regular > 0 && $final < $regular && isset($attrMap['special_price'])) {
                upsertValue($pdo, 'catalog_product_entity_decimal', [
                    'attribute_id' => $attrMap['special_price']['id'],
                    'store_id' => 0,
                    'entity_id' => $newId,
                    'value' => $final,
                ]);
            } else {
                foreach (['special_price' => 'catalog_product_entity_decimal', 'special_from_date' => 'catalog_product_entity_datetime', 'special_to_date' => 'catalog_product_entity_datetime'] as $code => $table) {
                    if (!isset($attrMap[$code])) {
                        continue;
                    }
                    $del = $pdo->prepare("DELETE FROM {$table} WHERE entity_id = :entity_id AND attribute_id = :attribute_id");
                    $del->execute([
                        'entity_id' => $newId,
                        'attribute_id' => $attrMap[$code]['id'],
                    ]);
                }
            }

            foreach (($variant['attributes'] ?? []) as $a) {
                $code = (string) ($a['code'] ?? '');
                $valueIndex = (int) ($a['value_index'] ?? 0);
                if ($code === '' || $valueIndex <= 0 || !isset($attrMap[$code])) {
                    continue;
                }
                $backend = (string) $attrMap[$code]['backend_type'];
                $attributeId = (int) $attrMap[$code]['id'];

                if ($backend === 'int') {
                    upsertValue($pdo, 'catalog_product_entity_int', [
                        'attribute_id' => $attributeId,
                        'store_id' => 0,
                        'entity_id' => $newId,
                        'value' => $valueIndex,
                    ]);
                } elseif ($backend === 'varchar') {
                    upsertValue($pdo, 'catalog_product_entity_varchar', [
                        'attribute_id' => $attributeId,
                        'store_id' => 0,
                        'entity_id' => $newId,
                        'value' => (string) $valueIndex,
                    ]);
                } elseif ($backend === 'decimal') {
                    upsertValue($pdo, 'catalog_product_entity_decimal', [
                        'attribute_id' => $attributeId,
                        'store_id' => 0,
                        'entity_id' => $newId,
                        'value' => (float) $valueIndex,
                    ]);
                }
            }

            if ($firstMedia !== '') {
                foreach (['image', 'small_image', 'thumbnail', 'swatch_image'] as $imageCode) {
                    if (!isset($attrMap[$imageCode])) {
                        continue;
                    }
                    upsertValue($pdo, 'catalog_product_entity_varchar', [
                        'attribute_id' => $attrMap[$imageCode]['id'],
                        'store_id' => 0,
                        'entity_id' => $newId,
                        'value' => $firstMedia,
                    ]);
                }
            }

            $pdo->commit();
            $stats['created']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $stats['errors']++;
            $errors[$newSku] = $e->getMessage();
        }
    }
}

echo json_encode(
    [
        'stats' => $stats,
        'errors_count' => count($errors),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

if ($errors) {
    $errorFile = '/tmp/sync_missing_variants_from_graphql.errors.json';
    file_put_contents($errorFile, json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "Errors file: {$errorFile}" . PHP_EOL;
}
