<?php
declare(strict_types=1);

/**
 * Sync gallery media (including configurable variants) for all products
 * from one original category into test DB/media.
 *
 * Usage:
 *   php sync_category_gallery_from_original.php \
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
    return '/' . ltrim(substr($path, $pos + strlen('/catalog/product')), '/');
}

function graphQl(string $endpoint, string $query, array $variables = []): array
{
    $payload = json_encode(['query' => $query, 'variables' => $variables]);
    if ($payload === false) {
        throw new RuntimeException('GraphQL payload encode failed');
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 70,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'hyva-gallery-category-sync/1.0',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("GraphQL cURL error: {$err}");
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("GraphQL HTTP {$status}");
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('GraphQL JSON decode failed');
    }
    if (!empty($json['errors'])) {
        throw new RuntimeException('GraphQL errors: ' . json_encode($json['errors']));
    }
    return $json['data'] ?? [];
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
        throw new RuntimeException("mkdir failed: {$dir}");
    }

    $sourceUrl = 'https://shop.ftc-cashmere.com/pub/media/catalog/product' . $mediaValue;
    $ch = curl_init($sourceUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'hyva-gallery-category-sync/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status !== 200) {
        throw new RuntimeException("download failed {$sourceUrl} ({$status}) {$err}");
    }
    if (stripos(substr($body, 0, 250), '<!doctype html') !== false) {
        throw new RuntimeException("HTML instead of image {$sourceUrl}");
    }
    if (file_put_contents($target, $body) === false) {
        throw new RuntimeException("write failed {$target}");
    }
}

function upsertValue(PDO $pdo, string $table, array $row): void
{
    $cols = array_keys($row);
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
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

function setProductImageAttrs(PDO $pdo, int $entityId, string $firstMediaValue, array $attrMap): void
{
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

function ensureGallery(
    PDO $pdo,
    int $entityId,
    array $mediaValues,
    int $mediaAttributeId,
    string $mediaRoot
): int {
    $mediaValues = array_values(array_unique(array_filter(array_map('strval', $mediaValues))));
    if (!$mediaValues) {
        return 0;
    }

    $existingValueIds = [];
    $in = implode(',', array_fill(0, count($mediaValues), '?'));
    $st = $pdo->prepare("SELECT value_id, value FROM catalog_product_entity_media_gallery WHERE value IN ({$in})");
    $st->execute($mediaValues);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existingValueIds[(string) $r['value']] = (int) $r['value_id'];
    }

    $addedValues = 0;
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
            $addedValues++;
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
        $linkExists->execute(['value_id' => $valueId, 'entity_id' => $entityId]);
        if (!$linkExists->fetchColumn()) {
            $insLink->execute(['value_id' => $valueId, 'entity_id' => $entityId]);
        }

        $valExists->execute(['value_id' => $valueId, 'entity_id' => $entityId]);
        $recordId = (int) ($valExists->fetchColumn() ?: 0);
        if ($recordId > 0) {
            $updVal->execute(['position' => $pos, 'record_id' => $recordId]);
        } else {
            $insVal->execute(['value_id' => $valueId, 'entity_id' => $entityId, 'position' => $pos]);
        }
        $pos++;
    }

    return $addedValues;
}

$opts = getopt('', ['env:', 'category-id:', 'graphql::', 'base-path::']);
$envFile = optRequired($opts, 'env');
$categoryId = (int) optRequired($opts, 'category-id');
$graphql = (string) ($opts['graphql'] ?? 'https://shop.ftc-cashmere.com/graphql');
$basePath = (string) ($opts['base-path'] ?? dirname(__DIR__));

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

$mediaRoot = rtrim($basePath, '/') . '/pub/media/catalog/product';
if (!is_dir($mediaRoot)) {
    throw new RuntimeException("Media root not found: {$mediaRoot}");
}

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

$skuRows = $pdo->query('SELECT entity_id, sku FROM catalog_product_entity')->fetchAll();
$skuToEntity = [];
foreach ($skuRows as $r) {
    $skuToEntity[(string) $r['sku']] = (int) $r['entity_id'];
}

$categoryQuery = <<<'GQL'
query($id: Int!, $page: Int!, $size: Int!) {
  category(id: $id) {
    id
    products(pageSize: $size, currentPage: $page, sort: { position: ASC }) {
      page_info { current_page total_pages }
      items { sku }
    }
  }
}
GQL;

$page = 1;
$size = 200;
$totalPages = 1;
$parentSkus = [];
do {
    $data = graphQl($graphql, $categoryQuery, ['id' => $categoryId, 'page' => $page, 'size' => $size]);
    $cat = $data['category'] ?? null;
    if (!$cat || !isset($cat['products']['items'])) {
        throw new RuntimeException("Category {$categoryId} not found in original GraphQL");
    }
    foreach ($cat['products']['items'] as $it) {
        $sku = trim((string) ($it['sku'] ?? ''));
        if ($sku !== '') {
            $parentSkus[$sku] = true;
        }
    }
    $totalPages = (int) ($cat['products']['page_info']['total_pages'] ?? 1);
    $page++;
} while ($page <= $totalPages);

$detailQuery = <<<'GQL'
query($sku: String!) {
  products(filter: { sku: { eq: $sku } }) {
    items {
      sku
      __typename
      media_gallery { url }
      ... on ConfigurableProduct {
        variants {
          product {
            sku
            media_gallery { url }
          }
        }
      }
    }
  }
}
GQL;

$stats = [
    'category_id' => $categoryId,
    'parent_skus' => count($parentSkus),
    'detail_requests' => 0,
    'products_missing_on_test' => 0,
    'products_synced' => 0,
    'variants_synced' => 0,
    'new_media_values_inserted' => 0,
    'errors' => 0,
];
$missingOnTest = [];
$errors = [];

foreach (array_keys($parentSkus) as $parentSku) {
    $stats['detail_requests']++;
    try {
        $detail = graphQl($graphql, $detailQuery, ['sku' => $parentSku]);
        $items = $detail['products']['items'] ?? [];
        if (!is_array($items) || !$items) {
            continue;
        }
        $item = $items[0];

        $targetParentId = $skuToEntity[$parentSku] ?? 0;
        $parentValues = [];
        foreach (($item['media_gallery'] ?? []) as $m) {
            $v = parseMediaValueFromUrl((string) ($m['url'] ?? ''));
            if ($v !== '') {
                $parentValues[] = $v;
            }
        }
        if ($targetParentId > 0 && $parentValues) {
            $stats['new_media_values_inserted'] += ensureGallery(
                $pdo,
                $targetParentId,
                $parentValues,
                $mediaGalleryAttributeId,
                $mediaRoot
            );
            setProductImageAttrs($pdo, $targetParentId, $parentValues[0], $attrMap);
            $stats['products_synced']++;
        } elseif ($targetParentId <= 0) {
            $stats['products_missing_on_test']++;
            $missingOnTest[$parentSku] = true;
        }

        foreach (($item['variants'] ?? []) as $variant) {
            $child = $variant['product'] ?? null;
            if (!is_array($child)) {
                continue;
            }
            $childSku = trim((string) ($child['sku'] ?? ''));
            if ($childSku === '') {
                continue;
            }
            $childId = $skuToEntity[$childSku] ?? 0;
            if ($childId <= 0) {
                $stats['products_missing_on_test']++;
                $missingOnTest[$childSku] = true;
                continue;
            }
            $childValues = [];
            foreach (($child['media_gallery'] ?? []) as $m) {
                $v = parseMediaValueFromUrl((string) ($m['url'] ?? ''));
                if ($v !== '') {
                    $childValues[] = $v;
                }
            }
            if ($childValues) {
                $stats['new_media_values_inserted'] += ensureGallery(
                    $pdo,
                    $childId,
                    $childValues,
                    $mediaGalleryAttributeId,
                    $mediaRoot
                );
                setProductImageAttrs($pdo, $childId, $childValues[0], $attrMap);
                $stats['variants_synced']++;
            }
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        $errors[] = [
            'sku' => $parentSku,
            'error' => $e->getMessage(),
        ];
    }
}

$result = [
    'stats' => $stats,
    'missing_on_test_sample' => array_slice(array_keys($missingOnTest), 0, 100),
    'errors_count' => count($errors),
    'errors_sample' => array_slice($errors, 0, 20),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

