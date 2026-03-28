<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$opts = getopt('', ['env:', 'store-id::', 'prefix::', 'max-depth::']);
$envFile = (string) ($opts['env'] ?? '');
if ($envFile === '' || !is_file($envFile)) {
    fwrite(STDERR, "Usage: php list_categories.php --env=/path/to/env.php [--store-id=1] [--prefix=de-de/] [--max-depth=2]\n");
    exit(1);
}

$storeId = (int) ($opts['store-id'] ?? 1);
$prefix = (string) ($opts['prefix'] ?? 'de-de/');
$maxDepth = (int) ($opts['max-depth'] ?? 2);

$env = include $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!is_array($db)) {
    throw new RuntimeException('Invalid env.php DB config');
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

$sql = <<<SQL
SELECT ur.entity_id, ur.request_path, COUNT(ccp.product_id) AS product_count
FROM url_rewrite ur
LEFT JOIN catalog_category_product ccp
       ON ccp.category_id = ur.entity_id
WHERE ur.entity_type = 'category'
  AND ur.redirect_type = 0
  AND ur.request_path LIKE :prefix
GROUP BY ur.entity_id, ur.request_path
ORDER BY LENGTH(ur.request_path), ur.request_path
SQL;

if ($storeId >= 0) {
    $sql = str_replace(
        "WHERE ur.entity_type = 'category'\n  AND ur.redirect_type = 0",
        "WHERE ur.entity_type = 'category'\n  AND ur.store_id = :store_id\n  AND ur.redirect_type = 0",
        $sql
    );
}

$stmt = $pdo->prepare($sql);
$params = ['prefix' => $prefix . '%'];
if ($storeId >= 0) {
    $params['store_id'] = $storeId;
}
$stmt->execute($params);

foreach ($stmt->fetchAll() as $row) {
    $path = (string) $row['request_path'];
    $trimmed = preg_replace('#\.html$#', '', $path) ?: $path;
    $segments = explode('/', $trimmed);
    $depth = max(0, count($segments) - 1);
    if ($depth > $maxDepth) {
        continue;
    }
    echo sprintf(
        "%4d | depth=%d | products=%d | %s\n",
        (int) $row['entity_id'],
        $depth,
        (int) $row['product_count'],
        $path
    );
}
