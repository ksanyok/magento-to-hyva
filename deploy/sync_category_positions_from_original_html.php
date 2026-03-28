<?php
declare(strict_types=1);

/**
 * Sync category product order by crawling original storefront HTML pages.
 *
 * Usage:
 *   php sync_category_positions_from_original_html.php \
 *     --env=/home/vibeadd/vibeadd.com/hyvatestproject/app/etc/env.php \
 *     --category-id=162 \
 *     --original-url=https://shop.ftc-cashmere.com/de-de/damen/kaschmir-bekleidung.html
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function requiredOption(array $options, string $name): string
{
    if (!isset($options[$name]) || trim((string) $options[$name]) === '') {
        fwrite(STDERR, "Missing required option --{$name}\n");
        exit(1);
    }
    return (string) $options[$name];
}

function fetchHtml(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'hyva-category-order-sync/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($body)) {
        throw new RuntimeException("HTTP request failed for {$url}: {$err}");
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP {$status} for {$url}");
    }
    return $body;
}

/**
 * Extract product URLs from category listing HTML.
 *
 * @return string[]
 */
function extractProductUrls(string $html): array
{
    $urls = [];
    $chunks = preg_split('/<li[^>]*class="[^"]*product-item[^"]*"[^>]*>/i', $html) ?: [];
    $pattern = '/href="((?:https?:\/\/[^"]+)?\/de-de\/[0-9]{5}-[0-9]{4}[^"\s<]*\.html)"/i';

    foreach (array_slice($chunks, 1) as $chunk) {
        if (preg_match($pattern, $chunk, $m) === 1) {
            $url = (string) $m[1];
            $url = preg_replace('#^https?://[^/]+#', '', $url) ?: $url;
            $urls[] = $url;
        }
    }

    return $urls;
}

/**
 * @return string[] Ordered SKU list
 */
function crawlOrderedSkus(string $baseUrl, int $maxPages = 50): array
{
    $ordered = [];
    $seenSkus = [];
    $prevPageUrls = [];

    for ($page = 1; $page <= $maxPages; $page++) {
        $url = $baseUrl;
        if ($page > 1) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'p=' . $page;
        }

        $html = fetchHtml($url);
        $urls = extractProductUrls($html);
        if (!$urls) {
            break;
        }

        // Stop if pagination loops.
        if ($page > 1 && $urls === $prevPageUrls) {
            break;
        }
        $prevPageUrls = $urls;

        foreach ($urls as $u) {
            if (preg_match('#/de-de/([0-9]{5}-[0-9]{4})#', $u, $m) !== 1) {
                continue;
            }
            $sku = (string) $m[1];
            if (isset($seenSkus[$sku])) {
                continue;
            }
            $seenSkus[$sku] = true;
            $ordered[] = $sku;
        }
    }

    return $ordered;
}

$opts = getopt('', ['env:', 'category-id:', 'original-url:']);
$envFile = requiredOption($opts, 'env');
$categoryId = (int) requiredOption($opts, 'category-id');
$originalUrl = requiredOption($opts, 'original-url');

if (!is_file($envFile)) {
    fwrite(STDERR, "env.php not found: {$envFile}\n");
    exit(1);
}

$env = include $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!is_array($db)) {
    throw new RuntimeException('DB config missing in env.php');
}

$orderedSkus = crawlOrderedSkus($originalUrl, 60);
if (!$orderedSkus) {
    throw new RuntimeException('No SKUs extracted from original HTML');
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

$skuToId = [];
$inChunks = array_chunk($orderedSkus, 400);
foreach ($inChunks as $chunk) {
    $in = implode(',', array_fill(0, count($chunk), '?'));
    $st = $pdo->prepare("SELECT sku, entity_id FROM catalog_product_entity WHERE sku IN ({$in})");
    $st->execute($chunk);
    foreach ($st->fetchAll() as $r) {
        $skuToId[(string) $r['sku']] = (int) $r['entity_id'];
    }
}

$missingSkus = [];
foreach ($orderedSkus as $sku) {
    if (!isset($skuToId[$sku])) {
        $missingSkus[] = $sku;
    }
}

$catRowsStmt = $pdo->prepare(
    'SELECT entity_id, product_id, position FROM catalog_category_product WHERE category_id = :category_id ORDER BY position, entity_id'
);
$catRowsStmt->execute(['category_id' => $categoryId]);
$catRows = $catRowsStmt->fetchAll();

$rowsByProduct = [];
foreach ($catRows as $row) {
    $pid = (int) $row['product_id'];
    $rowsByProduct[$pid][] = [
        'entity_id' => (int) $row['entity_id'],
        'position' => (int) $row['position'],
    ];
}

$updateStmt = $pdo->prepare('UPDATE catalog_category_product SET position = :position WHERE entity_id = :entity_id');
$insertStmt = $pdo->prepare(
    'INSERT INTO catalog_category_product (category_id, product_id, position) VALUES (:category_id, :product_id, :position)'
);

$deleteIds = [];
$expectedIds = [];
$position = 1;
$updated = 0;
$inserted = 0;

foreach ($orderedSkus as $sku) {
    if (!isset($skuToId[$sku])) {
        continue;
    }
    $pid = $skuToId[$sku];
    $expectedIds[$pid] = true;
    if (!empty($rowsByProduct[$pid])) {
        $keep = array_shift($rowsByProduct[$pid]);
        $updateStmt->execute([
            'position' => $position,
            'entity_id' => $keep['entity_id'],
        ]);
        $updated++;
        foreach ($rowsByProduct[$pid] as $dup) {
            $deleteIds[] = (int) $dup['entity_id'];
        }
        $rowsByProduct[$pid] = [];
    } else {
        $insertStmt->execute([
            'category_id' => $categoryId,
            'product_id' => $pid,
            'position' => $position,
        ]);
        $inserted++;
    }
    $position++;
}

// Delete products not present in original ordered list.
foreach ($rowsByProduct as $pid => $rows) {
    if (isset($expectedIds[(int) $pid])) {
        continue;
    }
    foreach ($rows as $row) {
        $deleteIds[] = (int) $row['entity_id'];
    }
}

$deleted = 0;
if ($deleteIds) {
    $deleteIds = array_values(array_unique($deleteIds));
    foreach (array_chunk($deleteIds, 800) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $del = $pdo->prepare("DELETE FROM catalog_category_product WHERE entity_id IN ({$in})");
        $del->execute($chunk);
        $deleted += count($chunk);
    }
}

$result = [
    'category_id' => $categoryId,
    'ordered_skus_from_original_html' => count($orderedSkus),
    'missing_skus_on_test' => $missingSkus,
    'rows_updated' => $updated,
    'rows_inserted' => $inserted,
    'rows_deleted' => $deleted,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
