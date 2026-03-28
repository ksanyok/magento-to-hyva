<?php
declare(strict_types=1);

/**
 * Sync one category on Hyvä test with original live site by SKU set/order.
 * Also auto-creates missing configurable parent products (+variants) from
 * original GraphQL by cloning local template products.
 *
 * Usage:
 *   php sync_category_from_original.php \
 *     --env=/home/vibeadd/vibeadd.com/hyvatestproject/app/etc/env.php \
 *     --category-id=162 \
 *     --graphql=https://shop.ftc-cashmere.com/graphql \
 *     --base-path=/home/vibeadd/vibeadd.com/hyvatestproject
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function optRequired(array $opts, string $key): string
{
    if (!isset($opts[$key]) || trim((string) $opts[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }
    return (string) $opts[$key];
}

function parseMediaValueFromUrl(?string $url): string
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
    $value = '/' . ltrim(substr($path, $pos + strlen('/catalog/product')), '/');
    return $value;
}

function graphQl(string $endpoint, string $query, array $variables = []): array
{
    $payload = json_encode([
        'query' => $query,
        'variables' => $variables,
    ]);
    if ($payload === false) {
        throw new RuntimeException('Failed to encode GraphQL payload');
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'hyva-category-sync/1.0',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("GraphQL cURL error: {$err}");
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("GraphQL HTTP {$status}: " . substr($raw, 0, 500));
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("GraphQL JSON decode failed");
    }
    if (!empty($json['errors'])) {
        throw new RuntimeException("GraphQL errors: " . json_encode($json['errors']));
    }
    return $json['data'] ?? [];
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

function removeKeys(array $row, array $keys): array
{
    foreach ($keys as $k) {
        unset($row[$k]);
    }
    return $row;
}

function ensureImageFile(string $mediaValue, string $mediaRoot): void
{
    if ($mediaValue === '') {
        return;
    }

    $target = rtrim($mediaRoot, '/') . $mediaValue;
    if (is_file($target) && filesize($target) > 0) {
        return;
    }

    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create directory: {$dir}");
    }

    $sourceUrl = 'https://shop.ftc-cashmere.com/pub/media/catalog/product' . $mediaValue;
    $ch = curl_init($sourceUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 50,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'hyva-category-sync/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status !== 200) {
        throw new RuntimeException("Failed to download {$sourceUrl} ({$status}) {$err}");
    }
    if (stripos(substr($body, 0, 250), '<!doctype html') !== false) {
        throw new RuntimeException("HTML returned instead of image: {$sourceUrl}");
    }
    if (file_put_contents($target, $body) === false) {
        throw new RuntimeException("Failed to write image file: {$target}");
    }
}

function ensureGallery(
    PDO $pdo,
    int $entityId,
    array $mediaValues,
    int $mediaAttributeId,
    string $mediaRoot
): void {
    $mediaValues = array_values(array_unique(array_filter(array_map('strval', $mediaValues))));
    if (!$mediaValues) {
        return;
    }

    $existingValueIds = [];
    $in = implode(',', array_fill(0, count($mediaValues), '?'));
    $st = $pdo->prepare("SELECT value_id, value FROM catalog_product_entity_media_gallery WHERE value IN ({$in})");
    $st->execute($mediaValues);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existingValueIds[(string) $r['value']] = (int) $r['value_id'];
    }

    foreach ($mediaValues as $value) {
        ensureImageFile($value, $mediaRoot);
        if (!isset($existingValueIds[$value])) {
            $ins = $pdo->prepare(
                'INSERT INTO catalog_product_entity_media_gallery (attribute_id, value) VALUES (:attribute_id, :value)'
            );
            $ins->execute([
                'attribute_id' => $mediaAttributeId,
                'value' => $value,
            ]);
            $existingValueIds[$value] = (int) $pdo->lastInsertId();
        }
    }

    $linkExists = $pdo->prepare(
        'SELECT 1 FROM catalog_product_entity_media_gallery_value_to_entity WHERE value_id = :value_id AND entity_id = :entity_id LIMIT 1'
    );
    $valExists = $pdo->prepare(
        'SELECT record_id FROM catalog_product_entity_media_gallery_value WHERE value_id = :value_id AND entity_id = :entity_id AND store_id = 0 LIMIT 1'
    );
    $insLink = $pdo->prepare(
        'INSERT INTO catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id) VALUES (:value_id, :entity_id)'
    );
    $insVal = $pdo->prepare(
        'INSERT INTO catalog_product_entity_media_gallery_value (value_id, store_id, entity_id, label, position, disabled)
         VALUES (:value_id, 0, :entity_id, NULL, :position, 0)'
    );
    $updVal = $pdo->prepare(
        'UPDATE catalog_product_entity_media_gallery_value SET position = :position, disabled = 0 WHERE record_id = :record_id'
    );

    $pos = 1;
    foreach ($mediaValues as $value) {
        $valueId = $existingValueIds[$value];

        $linkExists->execute([
            'value_id' => $valueId,
            'entity_id' => $entityId,
        ]);
        if (!$linkExists->fetchColumn()) {
            $insLink->execute([
                'value_id' => $valueId,
                'entity_id' => $entityId,
            ]);
        }

        $valExists->execute([
            'value_id' => $valueId,
            'entity_id' => $entityId,
        ]);
        $recordId = (int) ($valExists->fetchColumn() ?: 0);
        if ($recordId > 0) {
            $updVal->execute([
                'position' => $pos,
                'record_id' => $recordId,
            ]);
        } else {
            $insVal->execute([
                'value_id' => $valueId,
                'entity_id' => $entityId,
                'position' => $pos,
            ]);
        }
        $pos++;
    }
}

function setProductImageAttrs(
    PDO $pdo,
    int $entityId,
    string $firstMediaValue,
    array $attrMap
): void {
    if ($firstMediaValue === '') {
        return;
    }

    foreach (['image', 'small_image', 'thumbnail', 'swatch_image'] as $code) {
        if (!isset($attrMap[$code]) || $attrMap[$code]['backend_type'] !== 'varchar') {
            continue;
        }
        upsertValue($pdo, 'catalog_product_entity_varchar', [
            'attribute_id' => $attrMap[$code]['id'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $firstMediaValue,
        ]);
    }
}

function setProductNameUrlKey(
    PDO $pdo,
    int $entityId,
    ?string $name,
    ?string $urlKey,
    array $attrMap
): void {
    $name = trim((string) $name);
    $urlKey = trim((string) $urlKey);

    if ($name !== '' && isset($attrMap['name']) && $attrMap['name']['backend_type'] === 'varchar') {
        upsertValue($pdo, 'catalog_product_entity_varchar', [
            'attribute_id' => $attrMap['name']['id'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $name,
        ]);
    }
    if ($urlKey !== '' && isset($attrMap['url_key']) && $attrMap['url_key']['backend_type'] === 'varchar') {
        upsertValue($pdo, 'catalog_product_entity_varchar', [
            'attribute_id' => $attrMap['url_key']['id'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $urlKey,
        ]);
    }
}

function setProductPrice(
    PDO $pdo,
    int $entityId,
    float $regular,
    float $final,
    array $attrMap
): void {
    if ($regular > 0 && isset($attrMap['price']) && $attrMap['price']['backend_type'] === 'decimal') {
        upsertValue($pdo, 'catalog_product_entity_decimal', [
            'attribute_id' => $attrMap['price']['id'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $regular,
        ]);
    }

    if (!isset($attrMap['special_price']) || $attrMap['special_price']['backend_type'] !== 'decimal') {
        return;
    }

    if ($final > 0 && $regular > 0 && $final < $regular) {
        upsertValue($pdo, 'catalog_product_entity_decimal', [
            'attribute_id' => $attrMap['special_price']['id'],
            'store_id' => 0,
            'entity_id' => $entityId,
            'value' => $final,
        ]);
        return;
    }

    $del = $pdo->prepare(
        'DELETE FROM catalog_product_entity_decimal WHERE entity_id = :entity_id AND attribute_id = :attribute_id'
    );
    $del->execute([
        'entity_id' => $entityId,
        'attribute_id' => $attrMap['special_price']['id'],
    ]);
}

function setVariantAttributeValues(
    PDO $pdo,
    int $entityId,
    array $variantAttributes,
    array $attrMap
): void {
    foreach ($variantAttributes as $attr) {
        if (!is_array($attr)) {
            continue;
        }
        $code = trim((string) ($attr['code'] ?? ''));
        $valueIndex = (int) ($attr['value_index'] ?? 0);
        if ($code === '' || $valueIndex <= 0 || !isset($attrMap[$code])) {
            continue;
        }

        $meta = $attrMap[$code];
        $attrId = (int) $meta['id'];
        $backend = (string) $meta['backend_type'];

        if ($backend === 'int') {
            upsertValue($pdo, 'catalog_product_entity_int', [
                'attribute_id' => $attrId,
                'store_id' => 0,
                'entity_id' => $entityId,
                'value' => $valueIndex,
            ]);
        } elseif ($backend === 'varchar') {
            upsertValue($pdo, 'catalog_product_entity_varchar', [
                'attribute_id' => $attrId,
                'store_id' => 0,
                'entity_id' => $entityId,
                'value' => (string) $valueIndex,
            ]);
        } elseif ($backend === 'decimal') {
            upsertValue($pdo, 'catalog_product_entity_decimal', [
                'attribute_id' => $attrId,
                'store_id' => 0,
                'entity_id' => $entityId,
                'value' => (float) $valueIndex,
            ]);
        }
    }
}

$opts = getopt('', ['env:', 'category-id:', 'graphql::', 'base-path::']);
$envFile = optRequired($opts, 'env');
$categoryId = (int) optRequired($opts, 'category-id');
$graphqlEndpoint = (string) ($opts['graphql'] ?? 'https://shop.ftc-cashmere.com/graphql');
$basePath = (string) ($opts['base-path'] ?? dirname(dirname($envFile)));
$mediaRoot = rtrim($basePath, '/') . '/pub/media/catalog/product';

if (!is_file($envFile)) {
    fwrite(STDERR, "env.php not found: {$envFile}\n");
    exit(1);
}

$env = include $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!is_array($db)) {
    fwrite(STDERR, "DB config missing in env.php\n");
    exit(1);
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

$attrRows = $pdo->query(
    "SELECT ea.attribute_id, ea.attribute_code, ea.backend_type
       FROM eav_attribute ea
       JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
      WHERE et.entity_type_code = 'catalog_product'"
)->fetchAll();

$attrMap = [];
foreach ($attrRows as $r) {
    $attrMap[(string) $r['attribute_code']] = [
        'id' => (int) $r['attribute_id'],
        'backend_type' => (string) $r['backend_type'],
    ];
}

if (!isset($attrMap['media_gallery'])) {
    throw new RuntimeException('media_gallery attribute not found');
}
$mediaGalleryAttributeId = (int) $attrMap['media_gallery']['id'];

// 1) Fetch expected SKU order from original category.
$categoryQuery = <<<'GQL'
query($id: Int!, $page: Int!, $size: Int!) {
  category(id: $id) {
    id
    name
    products(pageSize: $size, currentPage: $page, sort: { position: ASC }) {
      total_count
      page_info {
        current_page
        total_pages
      }
      items {
        sku
        __typename
      }
    }
  }
}
GQL;

$expected = [];
$expectedType = [];
$page = 1;
$size = 200;
$totalPages = 1;
do {
    $data = graphQl($graphqlEndpoint, $categoryQuery, [
        'id' => $categoryId,
        'page' => $page,
        'size' => $size,
    ]);
    $cat = $data['category'] ?? null;
    if (!$cat || !isset($cat['products']['items'])) {
        throw new RuntimeException("Category {$categoryId} not found in original GraphQL");
    }
    foreach ($cat['products']['items'] as $it) {
        $sku = trim((string) ($it['sku'] ?? ''));
        if ($sku === '') {
            continue;
        }
        if (!isset($expected[$sku])) {
            $expected[$sku] = count($expected) + 1;
            $expectedType[$sku] = (string) ($it['__typename'] ?? '');
        }
    }
    $totalPages = (int) ($cat['products']['page_info']['total_pages'] ?? 1);
    $page++;
} while ($page <= $totalPages);

if (!$expected) {
    throw new RuntimeException("No products returned by original category {$categoryId}");
}

// 2) Existing test SKU map.
$existingSkuRows = $pdo->query('SELECT entity_id, sku, type_id FROM catalog_product_entity')->fetchAll();
$skuToEntity = [];
$skuToType = [];
foreach ($existingSkuRows as $r) {
    $skuToEntity[(string) $r['sku']] = (int) $r['entity_id'];
    $skuToType[(string) $r['sku']] = (string) $r['type_id'];
}

$missingSkus = [];
foreach (array_keys($expected) as $sku) {
    if (!isset($skuToEntity[$sku])) {
        $missingSkus[] = $sku;
    }
}

// Build template pool from current category configurable products.
$templateParentsStmt = $pdo->prepare(
    "SELECT DISTINCT p.entity_id, p.sku
       FROM catalog_category_product ccp
       JOIN catalog_product_entity p ON p.entity_id = ccp.product_id
      WHERE ccp.category_id = :category_id
        AND p.type_id = 'configurable'
      ORDER BY p.entity_id"
);
$templateParentsStmt->execute(['category_id' => $categoryId]);
$templateParents = $templateParentsStmt->fetchAll();
if (!$templateParents) {
    throw new RuntimeException("No configurable templates found in category {$categoryId}");
}

$parentOptionCodesStmt = $pdo->prepare(
    "SELECT ea.attribute_code
       FROM catalog_product_super_attribute psa
       JOIN eav_attribute ea ON ea.attribute_id = psa.attribute_id
      WHERE psa.product_id = :product_id
      ORDER BY psa.position, psa.attribute_id"
);
$firstChildStmt = $pdo->prepare(
    'SELECT product_id FROM catalog_product_super_link WHERE parent_id = :parent_id ORDER BY product_id LIMIT 1'
);

$templatePool = [];
foreach ($templateParents as $tp) {
    $pid = (int) $tp['entity_id'];
    $parentOptionCodesStmt->execute(['product_id' => $pid]);
    $codes = array_map(static fn($x) => (string) $x['attribute_code'], $parentOptionCodesStmt->fetchAll());
    if (!$codes) {
        continue;
    }
    $firstChildStmt->execute(['parent_id' => $pid]);
    $childId = (int) ($firstChildStmt->fetchColumn() ?: 0);
    if ($childId <= 0) {
        continue;
    }
    sort($codes);
    $key = implode('|', $codes);
    if (!isset($templatePool[$key])) {
        $templatePool[$key] = [
            'parent_id' => $pid,
            'parent_sku' => (string) $tp['sku'],
            'first_child_id' => $childId,
        ];
    }
}

if (!$templatePool) {
    throw new RuntimeException("No valid configurable template pool in category {$categoryId}");
}

$defaultTemplate = reset($templatePool);

// Template caches.
$entityStmt = $pdo->prepare('SELECT * FROM catalog_product_entity WHERE entity_id = :entity_id LIMIT 1');
$websiteStmt = $pdo->prepare('SELECT * FROM catalog_product_website WHERE product_id = :entity_id');
$stockStmt = $pdo->prepare('SELECT * FROM cataloginventory_stock_item WHERE product_id = :entity_id LIMIT 1');
$sourceStmt = $pdo->prepare('SELECT * FROM inventory_source_item WHERE sku = :sku');
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
$eavStmt = [];
foreach ($eavTables as $t) {
    $eavStmt[$t] = $pdo->prepare("SELECT * FROM {$t} WHERE entity_id = :entity_id");
}
$superAttrStmt = $pdo->prepare('SELECT * FROM catalog_product_super_attribute WHERE product_id = :product_id ORDER BY position, product_super_attribute_id');
$superAttrLabelStmt = $pdo->prepare('SELECT * FROM catalog_product_super_attribute_label WHERE product_super_attribute_id = :product_super_attribute_id');

$templateDataCache = [];
$loadTemplateData = static function (int $entityId) use (
    &$templateDataCache,
    $entityStmt,
    $websiteStmt,
    $stockStmt,
    $sourceStmt,
    $eavTables,
    $eavStmt,
    $superAttrStmt,
    $superAttrLabelStmt
): array {
    if (isset($templateDataCache[$entityId])) {
        return $templateDataCache[$entityId];
    }

    $entityStmt->execute(['entity_id' => $entityId]);
    $product = $entityStmt->fetch();
    if (!$product) {
        throw new RuntimeException("Template entity {$entityId} not found");
    }

    $eav = [];
    foreach ($eavTables as $t) {
        $eavStmt[$t]->execute(['entity_id' => $entityId]);
        $eav[$t] = $eavStmt[$t]->fetchAll();
    }

    $websiteStmt->execute(['entity_id' => $entityId]);
    $websites = $websiteStmt->fetchAll();

    $stockStmt->execute(['entity_id' => $entityId]);
    $stock = $stockStmt->fetch() ?: null;

    $sourceStmt->execute(['sku' => (string) $product['sku']]);
    $sources = $sourceStmt->fetchAll();

    $superAttrStmt->execute(['product_id' => $entityId]);
    $superAttrs = $superAttrStmt->fetchAll();
    $superLabels = [];
    foreach ($superAttrs as $sa) {
        $oldId = (int) $sa['product_super_attribute_id'];
        $superAttrLabelStmt->execute(['product_super_attribute_id' => $oldId]);
        $superLabels[$oldId] = $superAttrLabelStmt->fetchAll();
    }

    $templateDataCache[$entityId] = [
        'product' => $product,
        'eav' => $eav,
        'websites' => $websites,
        'stock' => $stock,
        'sources' => $sources,
        'super_attrs' => $superAttrs,
        'super_labels' => $superLabels,
    ];

    return $templateDataCache[$entityId];
};

$findBySkuStmt = $pdo->prepare('SELECT entity_id, type_id FROM catalog_product_entity WHERE sku = :sku LIMIT 1');
$linkExistsStmt = $pdo->prepare('SELECT 1 FROM catalog_product_super_link WHERE parent_id = :parent_id AND product_id = :product_id LIMIT 1');
$relationExistsStmt = $pdo->prepare('SELECT 1 FROM catalog_product_relation WHERE parent_id = :parent_id AND child_id = :child_id LIMIT 1');
$insertSuperLinkStmt = $pdo->prepare('INSERT INTO catalog_product_super_link (product_id, parent_id) VALUES (:product_id, :parent_id)');
$insertRelationStmt = $pdo->prepare('INSERT INTO catalog_product_relation (parent_id, child_id) VALUES (:parent_id, :child_id)');

$productDetailQuery = <<<'GQL'
query($sku: String!) {
  products(filter: { sku: { eq: $sku } }) {
    items {
      __typename
      sku
      name
      url_key
      media_gallery { url }
      price_range {
        minimum_price {
          regular_price { value }
          final_price { value }
        }
      }
      ... on ConfigurableProduct {
        configurable_options {
          attribute_code
          label
        }
        variants {
          attributes {
            code
            value_index
            label
          }
          product {
            sku
            name
            url_key
            media_gallery { url }
            price_range {
              minimum_price {
                regular_price { value }
                final_price { value }
              }
            }
          }
        }
      }
    }
  }
}
GQL;

$stats = [
    'expected_skus' => count($expected),
    'missing_skus_before' => count($missingSkus),
    'missing_configurables_created' => 0,
    'missing_variants_created' => 0,
    'missing_variants_reused' => 0,
    'category_rows_inserted' => 0,
    'category_rows_updated' => 0,
    'category_rows_deleted' => 0,
    'errors' => 0,
];
$errors = [];

foreach ($missingSkus as $missingSku) {
    $expectedTypename = $expectedType[$missingSku] ?? '';
    if ($expectedTypename !== 'ConfigurableProduct') {
        $errors[$missingSku] = "Missing non-configurable SKU is not supported by this sync script";
        $stats['errors']++;
        continue;
    }

    try {
        $detailData = graphQl($graphqlEndpoint, $productDetailQuery, ['sku' => $missingSku]);
        $items = $detailData['products']['items'] ?? [];
        if (!$items) {
            throw new RuntimeException("Product {$missingSku} not found in original GraphQL");
        }
        $parentDetail = $items[0];
        if (($parentDetail['__typename'] ?? '') !== 'ConfigurableProduct') {
            throw new RuntimeException("Product {$missingSku} is not configurable on original");
        }

        $optionCodes = [];
        foreach (($parentDetail['configurable_options'] ?? []) as $opt) {
            $c = trim((string) ($opt['attribute_code'] ?? ''));
            if ($c !== '') {
                $optionCodes[] = $c;
            }
        }
        sort($optionCodes);
        $optionKey = implode('|', $optionCodes);
        $template = $templatePool[$optionKey] ?? $defaultTemplate;

        $parentTemplateData = $loadTemplateData((int) $template['parent_id']);
        $childTemplateData = $loadTemplateData((int) $template['first_child_id']);

        $pdo->beginTransaction();

        // Create configurable parent.
        $newParentProduct = removeKeys($parentTemplateData['product'], ['entity_id']);
        $newParentProduct['sku'] = $missingSku;
        insertRow($pdo, 'catalog_product_entity', $newParentProduct, false);
        $newParentId = (int) $pdo->lastInsertId();

        foreach ($eavTables as $t) {
            foreach (($parentTemplateData['eav'][$t] ?? []) as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $r = removeKeys($r, [$eavPk[$t]]);
                $r['entity_id'] = $newParentId;
                insertRow($pdo, $t, $r, true);
            }
        }

        foreach (($parentTemplateData['websites'] ?? []) as $wr) {
            if (!is_array($wr)) {
                continue;
            }
            $wr['product_id'] = $newParentId;
            insertRow($pdo, 'catalog_product_website', $wr, true);
        }

        if (is_array($parentTemplateData['stock'])) {
            $st = removeKeys($parentTemplateData['stock'], ['item_id']);
            $st['product_id'] = $newParentId;
            insertRow($pdo, 'cataloginventory_stock_item', $st, true);
            insertRow($pdo, 'cataloginventory_stock_status', [
                'product_id' => $newParentId,
                'website_id' => (int) ($st['website_id'] ?? 1),
                'stock_id' => (int) ($st['stock_id'] ?? 1),
                'qty' => (float) ($st['qty'] ?? 0),
                'stock_status' => (int) ($st['is_in_stock'] ?? 0),
            ], true);
        }

        if (!empty($parentTemplateData['sources'])) {
            foreach ($parentTemplateData['sources'] as $sr) {
                if (!is_array($sr)) {
                    continue;
                }
                $sr = removeKeys($sr, ['source_item_id']);
                $sr['sku'] = $missingSku;
                insertRow($pdo, 'inventory_source_item', $sr, true);
            }
        }

        // Clone configurable options (super attributes + labels).
        $superIdMap = [];
        foreach (($parentTemplateData['super_attrs'] ?? []) as $sa) {
            if (!is_array($sa)) {
                continue;
            }
            $oldSuperId = (int) $sa['product_super_attribute_id'];
            $row = removeKeys($sa, ['product_super_attribute_id']);
            $row['product_id'] = $newParentId;
            insertRow($pdo, 'catalog_product_super_attribute', $row, false);
            $newSuperId = (int) $pdo->lastInsertId();
            $superIdMap[$oldSuperId] = $newSuperId;

            foreach (($parentTemplateData['super_labels'][$oldSuperId] ?? []) as $labelRow) {
                if (!is_array($labelRow)) {
                    continue;
                }
                $label = removeKeys($labelRow, ['value_id']);
                $label['product_super_attribute_id'] = $newSuperId;
                insertRow($pdo, 'catalog_product_super_attribute_label', $label, true);
            }
        }

        setProductNameUrlKey(
            $pdo,
            $newParentId,
            (string) ($parentDetail['name'] ?? ''),
            (string) ($parentDetail['url_key'] ?? ''),
            $attrMap
        );

        $pRegular = (float) ($parentDetail['price_range']['minimum_price']['regular_price']['value'] ?? 0);
        $pFinal = (float) ($parentDetail['price_range']['minimum_price']['final_price']['value'] ?? 0);
        setProductPrice($pdo, $newParentId, $pRegular, $pFinal, $attrMap);

        $parentMediaValues = [];
        foreach (($parentDetail['media_gallery'] ?? []) as $mg) {
            $v = parseMediaValueFromUrl($mg['url'] ?? '');
            if ($v !== '') {
                $parentMediaValues[] = $v;
            }
        }
        $parentMediaValues = array_values(array_unique($parentMediaValues));
        if ($parentMediaValues) {
            setProductImageAttrs($pdo, $newParentId, $parentMediaValues[0], $attrMap);
            ensureGallery($pdo, $newParentId, $parentMediaValues, $mediaGalleryAttributeId, $mediaRoot);
        }

        // Create/reuse variants.
        foreach (($parentDetail['variants'] ?? []) as $variant) {
            $vProduct = $variant['product'] ?? [];
            $vSku = trim((string) ($vProduct['sku'] ?? ''));
            if ($vSku === '') {
                continue;
            }

            $findBySkuStmt->execute(['sku' => $vSku]);
            $existingChild = $findBySkuStmt->fetch();
            if ($existingChild) {
                $childId = (int) $existingChild['entity_id'];
                $stats['missing_variants_reused']++;
            } else {
                $newChild = removeKeys($childTemplateData['product'], ['entity_id']);
                $newChild['sku'] = $vSku;
                $newChild['type_id'] = 'simple';
                insertRow($pdo, 'catalog_product_entity', $newChild, false);
                $childId = (int) $pdo->lastInsertId();

                foreach ($eavTables as $t) {
                    foreach (($childTemplateData['eav'][$t] ?? []) as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $r = removeKeys($r, [$eavPk[$t]]);
                        $r['entity_id'] = $childId;
                        insertRow($pdo, $t, $r, true);
                    }
                }

                foreach (($childTemplateData['websites'] ?? []) as $wr) {
                    if (!is_array($wr)) {
                        continue;
                    }
                    $wr['product_id'] = $childId;
                    insertRow($pdo, 'catalog_product_website', $wr, true);
                }

                if (is_array($childTemplateData['stock'])) {
                    $st = removeKeys($childTemplateData['stock'], ['item_id']);
                    $st['product_id'] = $childId;
                    insertRow($pdo, 'cataloginventory_stock_item', $st, true);
                    insertRow($pdo, 'cataloginventory_stock_status', [
                        'product_id' => $childId,
                        'website_id' => (int) ($st['website_id'] ?? 1),
                        'stock_id' => (int) ($st['stock_id'] ?? 1),
                        'qty' => (float) ($st['qty'] ?? 0),
                        'stock_status' => (int) ($st['is_in_stock'] ?? 0),
                    ], true);
                }

                if (!empty($childTemplateData['sources'])) {
                    foreach ($childTemplateData['sources'] as $sr) {
                        if (!is_array($sr)) {
                            continue;
                        }
                        $sr = removeKeys($sr, ['source_item_id']);
                        $sr['sku'] = $vSku;
                        insertRow($pdo, 'inventory_source_item', $sr, true);
                    }
                }

                $stats['missing_variants_created']++;
            }

            // Link child to new parent.
            $linkExistsStmt->execute([
                'parent_id' => $newParentId,
                'product_id' => $childId,
            ]);
            if (!$linkExistsStmt->fetchColumn()) {
                $insertSuperLinkStmt->execute([
                    'parent_id' => $newParentId,
                    'product_id' => $childId,
                ]);
            }

            $relationExistsStmt->execute([
                'parent_id' => $newParentId,
                'child_id' => $childId,
            ]);
            if (!$relationExistsStmt->fetchColumn()) {
                $insertRelationStmt->execute([
                    'parent_id' => $newParentId,
                    'child_id' => $childId,
                ]);
            }

            setProductNameUrlKey(
                $pdo,
                $childId,
                (string) ($vProduct['name'] ?? ''),
                (string) ($vProduct['url_key'] ?? ''),
                $attrMap
            );

            $vRegular = (float) ($vProduct['price_range']['minimum_price']['regular_price']['value'] ?? 0);
            $vFinal = (float) ($vProduct['price_range']['minimum_price']['final_price']['value'] ?? 0);
            setProductPrice($pdo, $childId, $vRegular, $vFinal, $attrMap);

            setVariantAttributeValues($pdo, $childId, $variant['attributes'] ?? [], $attrMap);

            $childMediaValues = [];
            foreach (($vProduct['media_gallery'] ?? []) as $mg) {
                $mv = parseMediaValueFromUrl($mg['url'] ?? '');
                if ($mv !== '') {
                    $childMediaValues[] = $mv;
                }
            }
            $childMediaValues = array_values(array_unique($childMediaValues));
            if ($childMediaValues) {
                setProductImageAttrs($pdo, $childId, $childMediaValues[0], $attrMap);
                ensureGallery($pdo, $childId, $childMediaValues, $mediaGalleryAttributeId, $mediaRoot);
            }
        }

        $pdo->commit();
        $stats['missing_configurables_created']++;
        $skuToEntity[$missingSku] = $newParentId;
        $skuToType[$missingSku] = 'configurable';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $stats['errors']++;
        $errors[$missingSku] = $e->getMessage();
    }
}

// Refresh sku map for final category sync.
$existingSkuRows = $pdo->query('SELECT entity_id, sku FROM catalog_product_entity')->fetchAll();
$skuToEntity = [];
foreach ($existingSkuRows as $r) {
    $skuToEntity[(string) $r['sku']] = (int) $r['entity_id'];
}

$expectedProductIds = [];
$missingAfter = [];
foreach (array_keys($expected) as $sku) {
    if (!isset($skuToEntity[$sku])) {
        $missingAfter[] = $sku;
        continue;
    }
    $expectedProductIds[] = $skuToEntity[$sku];
}

// 3) Sync category rows (SKU set + order).
$catRowsStmt = $pdo->prepare(
    'SELECT entity_id, product_id FROM catalog_category_product WHERE category_id = :category_id ORDER BY entity_id'
);
$catRowsStmt->execute(['category_id' => $categoryId]);
$catRows = $catRowsStmt->fetchAll();

$rowsByProduct = [];
foreach ($catRows as $r) {
    $pid = (int) $r['product_id'];
    $rowsByProduct[$pid][] = (int) $r['entity_id'];
}

$updateRowStmt = $pdo->prepare(
    'UPDATE catalog_category_product SET position = :position WHERE entity_id = :entity_id'
);
$insertRowStmt = $pdo->prepare(
    'INSERT INTO catalog_category_product (category_id, product_id, position) VALUES (:category_id, :product_id, :position)'
);
$deleteRowsStmt = $pdo->prepare(
    'DELETE FROM catalog_category_product WHERE entity_id IN (%s)'
);

$deleteIds = [];
$position = 1;
foreach (array_keys($expected) as $sku) {
    if (!isset($skuToEntity[$sku])) {
        continue;
    }
    $pid = $skuToEntity[$sku];
    if (!empty($rowsByProduct[$pid])) {
        $keep = array_shift($rowsByProduct[$pid]);
        $updateRowStmt->execute([
            'position' => $position,
            'entity_id' => $keep,
        ]);
        $stats['category_rows_updated']++;
        foreach ($rowsByProduct[$pid] as $dup) {
            $deleteIds[] = (int) $dup;
        }
        $rowsByProduct[$pid] = [];
    } else {
        $insertRowStmt->execute([
            'category_id' => $categoryId,
            'product_id' => $pid,
            'position' => $position,
        ]);
        $stats['category_rows_inserted']++;
    }
    $position++;
}

// Delete products not expected + duplicates.
$expectedSet = array_flip($expectedProductIds);
foreach ($rowsByProduct as $pid => $rowIds) {
    if (isset($expectedSet[(int) $pid])) {
        continue;
    }
    foreach ($rowIds as $rid) {
        $deleteIds[] = (int) $rid;
    }
}

if ($deleteIds) {
    $deleteIds = array_values(array_unique($deleteIds));
    foreach (array_chunk($deleteIds, 800) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM catalog_category_product WHERE entity_id IN ({$in})");
        $stmt->execute($chunk);
        $stats['category_rows_deleted'] += count($chunk);
    }
}

$result = [
    'category_id' => $categoryId,
    'stats' => $stats,
    'missing_after_sync' => $missingAfter,
    'errors_count' => count($errors),
    'errors_sample' => array_slice($errors, 0, 30, true),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($errors) {
    $errFile = '/tmp/sync_category_from_original.errors.json';
    file_put_contents($errFile, json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "Errors file: {$errFile}" . PHP_EOL;
}
