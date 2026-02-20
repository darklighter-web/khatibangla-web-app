<?php
/**
 * PURGE ALL CACHES — LiteSpeed, OPcache, PHP sessions
 * URL: khatibangla.com/purge.php
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
http_response_code(200);

$results = [];

// 1. Purge LiteSpeed Cache via header
header('X-LiteSpeed-Purge: *');
$results[] = '✅ Sent X-LiteSpeed-Purge: * header (purges all LiteSpeed cached pages)';

// 2. Try LiteSpeed cache purge via .htaccess tag
if (function_exists('litespeed_purge_all')) {
    litespeed_purge_all();
    $results[] = '✅ Called litespeed_purge_all()';
} else {
    $results[] = '⏭ litespeed_purge_all() not available (normal on non-LSAPI)';
}

// 3. Flush OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    $results[] = '✅ OPcache flushed';
} else {
    $results[] = '⏭ OPcache not available';
}

// 4. Delete LiteSpeed cache files if accessible
$lsCacheDirs = [
    dirname(__DIR__) . '/lscache',
    dirname(__DIR__) . '/.lscache', 
    __DIR__ . '/lscache',
    __DIR__ . '/.lscache',
    '/tmp/lshttpd/cache',
    sys_get_temp_dir() . '/lscache',
];
foreach ($lsCacheDirs as $dir) {
    if (is_dir($dir)) {
        $count = 0;
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $file) {
            if ($file->isFile()) { @unlink($file->getPathname()); $count++; }
            elseif ($file->isDir()) { @rmdir($file->getPathname()); }
        }
        $results[] = "✅ Cleared LiteSpeed cache dir: {$dir} ({$count} files)";
    }
}

// 5. Clear PHP session files (stale sessions with old security data)
$sessDir = __DIR__ . '/tmp/sessions';
if (is_dir($sessDir)) {
    $count = 0;
    foreach (glob($sessDir . '/sess_*') as $f) { @unlink($f); $count++; }
    $results[] = "✅ Cleared {$count} PHP session files";
}

// 6. Clear fraud/security caches in database
try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance();
    
    // Clear any cached 403/error responses
    $db->query("DELETE FROM site_settings WHERE setting_key LIKE 'fraud_cache_%'");
    $results[] = '✅ Cleared fraud check cache from database';
    
    // Clear ALL IP blocks
    $count = $db->fetch("SELECT COUNT(*) as c FROM security_ip_rules WHERE rule_type = 'block'");
    $db->query("DELETE FROM security_ip_rules WHERE rule_type = 'block'");
    $results[] = '✅ Cleared ' . intval($count['c'] ?? 0) . ' IP blocks from database';
    
    // Clear rate limits
    $db->query("DELETE FROM security_rate_limits");
    $results[] = '✅ Cleared rate limit counters';
    
} catch (\Throwable $e) {
    $results[] = '⚠ DB: ' . $e->getMessage();
}

// 7. Create a temporary .htaccess rule to force no-cache for HTML
$htFile = __DIR__ . '/.htaccess';
if (file_exists($htFile)) {
    $content = file_get_contents($htFile);
    if (strpos($content, 'no-store') === false) {
        // Add no-cache for HTML at the top of headers section
        $noCacheRule = "\n    # Force no-cache for HTML pages (prevents stale 403 from being served)\n    Header always set Cache-Control \"no-cache, no-store, must-revalidate\" \"expr=%{CONTENT_TYPE} =~ m#text/html#\"\n";
        $content = str_replace('Header always set X-Content-Type-Options "nosniff"', 'Header always set X-Content-Type-Options "nosniff"' . $noCacheRule, $content);
        file_put_contents($htFile, $content);
        $results[] = '✅ Added no-cache header for HTML pages to .htaccess';
    } else {
        $results[] = '⏭ .htaccess already has no-cache for HTML';
    }
}
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cache Purge Complete</title>
<style>
body { font-family: -apple-system, sans-serif; padding: 24px; background: #f0fdf4; }
h1 { font-size: 22px; margin-bottom: 16px; }
.r { padding: 8px 12px; margin: 4px 0; background: white; border-radius: 6px; font-size: 13px; border-left: 3px solid #22c55e; }
.box { background: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-top: 20px; }
.box h2 { font-size: 16px; margin-bottom: 8px; }
.box p { font-size: 13px; margin: 6px 0; }
.box b { color: #dc2626; }
a.btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 12px; }
</style>
</head><body>
<h1>🧹 All Caches Purged</h1>
<?php foreach ($results as $r): ?>
<div class="r"><?= $r ?></div>
<?php endforeach; ?>

<div class="box">
    <h2>📱 CRITICAL: iOS Safari Fix</h2>
    <p>Server caches are cleared. But <b>iOS Safari still has the old 403 cached locally</b>.</p>
    <p>On the iPhone/iPad:</p>
    <p><b>Step 1:</b> Go to Settings → Safari → Advanced → Website Data</p>
    <p><b>Step 2:</b> Search for "khatibangla" → Swipe left → Delete</p>
    <p><b>Step 3:</b> Go back to Settings → Safari → tap "Clear History and Website Data"</p>
    <p><b>Step 4:</b> Force-close Safari (swipe up from app switcher)</p>
    <p><b>Step 5:</b> Open Safari fresh and visit khatibangla.com</p>
    <p style="margin-top:12px;color:#6b7280;">If that still doesn't work, try opening in a <b>Private/Incognito tab</b> first — this bypasses all cached data.</p>
</div>

<a class="btn" href="/">← Try Homepage Now</a>
<a class="btn" href="/shop" style="background:#16a34a;">Try Shop Page</a>

</body></html>
