<?php
declare(strict_types=1);

/**
 * Sync missing product gallery entries/files into Hyva test DB/media.
 *
 * Usage:
 *   php sync_missing_gallery.php /path/to/missing-images.json [base_path]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run in CLI mode.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php sync_missing_gallery.php <missing-images.json> [base_path]\n");
    exit(1);
}

$inputPath = $argv[1];
$basePath = $argv[2] ?? '/home/vibeadd/vibeadd.com/hyvatestproject';
$envPath = rtrim($basePath, '/') . '/app/etc/env.php';
$mediaRoot = rtrim($basePath, '/') . '/pub/media/catalog/product';

if (!is_file($inputPath)) {
    fwrite(STDERR, "Input file not found: {$inputPath}\n");
    exit(1);
}

if (!is_file($envPath)) {
    fwrite(STDERR, "env.php not found: {$envPath}\n");
    exit(1);
}

$inputData = json_decode((string) file_get_contents($inputPath), true);
if (!is_array($inputData)) {
    fwrite(STDERR, "Invalid JSON in {$inputPath}\n");
    exit(1);
}

$env = include $envPath;
$db = $env['db']['connection']['default'] ?? null;
if (!is_array($db)) {
    fwrite(STDERR, "Unable to read DB credentials from env.php\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $db['host'] ?? 'localhost',
    $db['dbname'] ?? ''
);

$pdo = new PDO(
    $dsn,
    (string) ($db['username'] ?? ''),
    (string) ($db['password'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/**
 * @param string[] $items
 * @return string
 */
function placeholders(array $items): string
{
    return implode(',', array_fill(0, count($items), '?'));
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, array<string, mixed>>
 */
function uniqueRows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $sku = trim((string) ($row['sku'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));
        if ($sku === '' || $value === '') {
            continue;
        }
        if ($value[0] !== '/') {
            $value = '/' . $value;
        }
        $key = $sku . '|' . $value;
        $out[$key] = [
            'parent_sku' => (string) ($row['parent_sku'] ?? ''),
            'sku' => $sku,
            'value' => $value,
            'position' => (int) ($row['position'] ?? 0),
            'disabled' => (int) ($row['disabled'] ?? 0),
            'source_url' => (string) ($row['source_url'] ?? ''),
        ];
    }
    return $out;
}

/**
 * @param array<string, array<string, mixed>> $rows
 * @return array<int, string>
 */
function collectUnique(array $rows, string $key): array
{
    $set = [];
    foreach ($rows as $row) {
        $val = (string) ($row[$key] ?? '');
        if ($val !== '') {
            $set[$val] = true;
        }
    }
    return array_keys($set);
}

/**
 * Download file if missing from target path.
 */
function ensureImageFile(string $value, string $sourceUrl, string $mediaRoot): array
{
    $result = [
        'ok' => false,
        'created' => false,
        'error' => '',
    ];

    $target = $mediaRoot . $value;
    if (is_file($target) && filesize($target) > 0) {
        $result['ok'] = true;
        return $result;
    }

    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        $result['error'] = 'mkdir failed: ' . $targetDir;
        return $result;
    }

    $urls = [];
    $urls[] = 'https://shop.ftc-cashmere.com/pub/media/catalog/product' . $value;
    if ($sourceUrl !== '' && !in_array($sourceUrl, $urls, true)) {
        $urls[] = $sourceUrl;
    }

    foreach ($urls as $url) {
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'hyva-gallery-sync/1.0',
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $httpCode !== 200) {
            $result['error'] = "download failed ({$httpCode}) {$url} {$curlErr}";
            continue;
        }

        // Very small payloads are often HTML error pages.
        if (strlen($body) < 200) {
            $result['error'] = "download suspiciously small payload for {$url}";
            continue;
        }

        if (stripos(substr($body, 0, 200), '<!doctype html') !== false) {
            $result['error'] = "download returned HTML for {$url}";
            continue;
        }

        if (file_put_contents($target, $body) === false) {
            $result['error'] = 'write failed: ' . $target;
            continue;
        }

        $result['ok'] = true;
        $result['created'] = true;
        $result['error'] = '';
        return $result;
    }

    return $result;
}

$rowsByKey = uniqueRows($inputData);
$rows = array_values($rowsByKey);

if (count($rows) === 0) {
    fwrite(STDOUT, "No rows to process.\n");
    exit(0);
}

$skus = collectUnique($rowsByKey, 'sku');
$values = collectUnique($rowsByKey, 'value');

// Resolve entity IDs for SKUs.
$skuToEntityId = [];
foreach (array_chunk($skus, 500) as $chunk) {
    $sql = 'SELECT sku, entity_id FROM catalog_product_entity WHERE sku IN (' . placeholders($chunk) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($chunk);
    foreach ($stmt->fetchAll() as $item) {
        $skuToEntityId[(string) $item['sku']] = (int) $item['entity_id'];
    }
}

// Resolve media_gallery attribute_id.
$stmtAttr = $pdo->query(
    "SELECT ea.attribute_id
     FROM eav_attribute ea
     JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
     WHERE et.entity_type_code = 'catalog_product'
       AND ea.attribute_code = 'media_gallery'
     LIMIT 1"
);
$attributeId = (int) ($stmtAttr->fetchColumn() ?: 0);
if ($attributeId <= 0) {
    throw new RuntimeException('Unable to resolve media_gallery attribute_id');
}

// Resolve existing value_id by media value.
$valueToId = [];
foreach (array_chunk($values, 500) as $chunk) {
    $sql = 'SELECT value_id, value FROM catalog_product_entity_media_gallery WHERE value IN (' . placeholders($chunk) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($chunk);
    foreach ($stmt->fetchAll() as $item) {
        $valueToId[(string) $item['value']] = (int) $item['value_id'];
    }
}

$insertMediaStmt = $pdo->prepare(
    "INSERT INTO catalog_product_entity_media_gallery (attribute_id, value, media_type)
     VALUES (:attribute_id, :value, 'image')"
);
$insertLinkStmt = $pdo->prepare(
    "INSERT IGNORE INTO catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id)
     VALUES (:value_id, :entity_id)"
);
$selectValueRowStmt = $pdo->prepare(
    "SELECT record_id
     FROM catalog_product_entity_media_gallery_value
     WHERE value_id = :value_id
       AND entity_id = :entity_id
       AND store_id = 0
     LIMIT 1"
);
$insertValueStmt = $pdo->prepare(
    "INSERT INTO catalog_product_entity_media_gallery_value (value_id, store_id, entity_id, label, position, disabled)
     VALUES (:value_id, 0, :entity_id, NULL, :position, :disabled)"
);
$updateValueStmt = $pdo->prepare(
    "UPDATE catalog_product_entity_media_gallery_value
     SET position = :position,
         disabled = :disabled
     WHERE record_id = :record_id"
);

$downloadByValue = [];
$stats = [
    'rows_total' => count($rows),
    'rows_processed' => 0,
    'rows_skipped_no_sku' => 0,
    'rows_skipped_no_entity' => 0,
    'rows_failed' => 0,
    'values_inserted' => 0,
    'links_inserted' => 0,
    'links_existing' => 0,
    'value_rows_inserted' => 0,
    'value_rows_updated' => 0,
    'files_downloaded' => 0,
    'files_existing' => 0,
    'files_failed' => 0,
];
$fileErrors = [];
$rowErrors = [];

foreach ($rows as $row) {
    $sku = (string) $row['sku'];
    $value = (string) $row['value'];
    $position = (int) $row['position'];
    $disabled = (int) $row['disabled'];
    $sourceUrl = (string) $row['source_url'];

    if ($sku === '') {
        $stats['rows_skipped_no_sku']++;
        continue;
    }

    $entityId = $skuToEntityId[$sku] ?? 0;
    if ($entityId <= 0) {
        $stats['rows_skipped_no_entity']++;
        continue;
    }

    if (!isset($downloadByValue[$value])) {
        $download = ensureImageFile($value, $sourceUrl, $mediaRoot);
        $downloadByValue[$value] = $download;
        if ($download['ok'] === true) {
            if ($download['created'] === true) {
                $stats['files_downloaded']++;
            } else {
                $stats['files_existing']++;
            }
        } else {
            $stats['files_failed']++;
            $fileErrors[] = [
                'value' => $value,
                'source_url' => $sourceUrl,
                'error' => $download['error'],
            ];
        }
    }

    try {
        // Always write DB rows even if file already exists/fails to download now.
        if (!isset($valueToId[$value])) {
            $insertMediaStmt->execute([
                ':attribute_id' => $attributeId,
                ':value' => $value,
            ]);
            $valueToId[$value] = (int) $pdo->lastInsertId();
            $stats['values_inserted']++;
        }

        $valueId = $valueToId[$value];

        $insertLinkStmt->execute([
            ':value_id' => $valueId,
            ':entity_id' => $entityId,
        ]);
        if ($insertLinkStmt->rowCount() > 0) {
            $stats['links_inserted']++;
        } else {
            $stats['links_existing']++;
        }

        $selectValueRowStmt->execute([
            ':value_id' => $valueId,
            ':entity_id' => $entityId,
        ]);
        $recordId = (int) ($selectValueRowStmt->fetchColumn() ?: 0);

        if ($recordId > 0) {
            $updateValueStmt->execute([
                ':record_id' => $recordId,
                ':position' => $position,
                ':disabled' => $disabled,
            ]);
            $stats['value_rows_updated']++;
        } else {
            $insertValueStmt->execute([
                ':value_id' => $valueId,
                ':entity_id' => $entityId,
                ':position' => $position,
                ':disabled' => $disabled,
            ]);
            $stats['value_rows_inserted']++;
        }

        $stats['rows_processed']++;
    } catch (Throwable $e) {
        $stats['rows_failed']++;
        if (count($rowErrors) < 100) {
            $rowErrors[] = [
                'sku' => $sku,
                'entity_id' => $entityId,
                'value' => $value,
                'position' => $position,
                'disabled' => $disabled,
                'error' => $e->getMessage(),
            ];
        }
        continue;
    }
}

$report = [
    'stats' => $stats,
    'file_errors_count' => count($fileErrors),
    'file_errors_sample' => array_slice($fileErrors, 0, 25),
    'row_errors_count' => count($rowErrors),
    'row_errors_sample' => array_slice($rowErrors, 0, 25),
];

fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
