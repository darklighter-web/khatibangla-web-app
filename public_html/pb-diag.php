<?php
/**
 * Progress Bar Diagnostic — deploy to site root, visit /pb-diag.php
 * Delete after debugging.
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
$db = Database::getInstance();
?>
<!DOCTYPE html><html><head><title>PB Diagnostic</title><style>body{font:14px/1.6 monospace;max-width:900px;margin:20px auto;padding:20px;background:#1a1a2e;color:#e0e0e0}.ok{color:#0f0}.err{color:#f44}.warn{color:#ff0}pre{background:#111;padding:10px;border-radius:8px;overflow-x:auto;border:1px solid #333}h2{color:#7ef;border-bottom:1px solid #333;padding-bottom:5px;margin-top:25px}</style></head><body>
<h1>🔧 Progress Bar Diagnostic</h1>

<h2>1. Table Exists?</h2>
<?php
try {
    $tables = $db->fetchAll("SHOW TABLES LIKE 'checkout_progress_bars'");
    if (count($tables) > 0) {
        echo '<span class="ok">✅ Table checkout_progress_bars EXISTS</span>';
    } else {
        echo '<span class="err">❌ Table checkout_progress_bars MISSING</span>';
        echo '<br>Run: CREATE TABLE checkout_progress_bars (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), template INT DEFAULT 1, tiers JSON, config JSON, is_active TINYINT DEFAULT 0)';
    }
} catch (Throwable $e) {
    echo '<span class="err">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>2. Table Columns</h2>
<?php
try {
    $cols = $db->fetchAll("DESCRIBE checkout_progress_bars");
    echo '<pre>';
    foreach ($cols as $c) {
        $mark = in_array($c['Field'], ['config']) ? ' ← needed for v2' : '';
        echo $c['Field'] . ' (' . $c['Type'] . ')' . $mark . "\n";
    }
    echo '</pre>';
    $colNames = array_column($cols, 'Field');
    if (in_array('config', $colNames)) {
        echo '<span class="ok">✅ config column exists</span>';
    } else {
        echo '<span class="err">❌ config column MISSING — run: ALTER TABLE checkout_progress_bars ADD COLUMN config JSON DEFAULT NULL AFTER tiers</span>';
    }
} catch (Throwable $e) {
    echo '<span class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>3. All Progress Bars</h2>
<?php
try {
    $bars = $db->fetchAll("SELECT * FROM checkout_progress_bars ORDER BY is_active DESC, id DESC");
    if (empty($bars)) {
        echo '<span class="warn">⚠️ No bars found — create one in admin first</span>';
    } else {
        echo '<span class="ok">✅ Found ' . count($bars) . ' bar(s)</span>';
        foreach ($bars as $b) {
            $active = $b['is_active'] ? '🟢 ACTIVE' : '⚪';
            $tiers = json_decode($b['tiers'] ?? '[]', true);
            $config = json_decode($b['config'] ?? '{}', true);
            echo "<pre>$active ID:{$b['id']} Name:{$b['name']} Template:{$b['template']}\n";
            echo "Tiers: " . count($tiers) . " — " . json_encode($tiers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            echo "Config: " . json_encode($config, JSON_PRETTY_PRINT) . "</pre>";
        }
    }
} catch (Throwable $e) {
    echo '<span class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>4. Active Bar Check</h2>
<?php
try {
    $active = $db->fetch("SELECT id, name, template, is_active FROM checkout_progress_bars WHERE is_active = 1 LIMIT 1");
    if ($active) {
        echo '<span class="ok">✅ Active bar: ID=' . $active['id'] . ' "' . $active['name'] . '" (Template ' . $active['template'] . ')</span>';
    } else {
        echo '<span class="err">❌ NO active bar! Select one in admin using the radio button.</span>';
    }
} catch (Throwable $e) {
    echo '<span class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>5. Checkout Fields — progress_bar enabled?</h2>
<?php
try {
    $cfJson = getSetting('checkout_fields', '');
    if (!$cfJson) {
        echo '<span class="warn">⚠️ No saved checkout_fields — defaults will be used (progress_bar enabled by default)</span>';
    } else {
        $fields = json_decode($cfJson, true);
        $found = false;
        foreach ($fields as $f) {
            if (($f['key'] ?? '') === 'progress_bar') {
                $found = true;
                if (!empty($f['enabled'])) {
                    echo '<span class="ok">✅ progress_bar field is ENABLED in checkout fields</span>';
                } else {
                    echo '<span class="err">❌ progress_bar field is DISABLED in checkout fields. Enable it in Checkout Field Manager.</span>';
                }
                break;
            }
        }
        if (!$found) {
            echo '<span class="warn">⚠️ progress_bar key not in saved fields — it will auto-merge on next page load</span>';
        }
    }
} catch (Throwable $e) {
    echo '<span class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>6. Legacy Setting (progress_bar_enabled)</h2>
<?php
try {
    $legacy = getSetting('progress_bar_enabled', 'NOT_SET');
    echo "Value: <code>$legacy</code>";
    if ($legacy === '1') echo ' <span class="ok">✅</span>';
    elseif ($legacy === '0') echo ' <span class="warn">⚠️ Set to 0 — but v2 ignores this, uses checkout fields instead</span>';
    else echo ' <span class="warn">⚠️ Not set</span>';
} catch (Throwable $e) {
    echo '<span class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>7. API Test (admin_save simulation)</h2>
<?php
echo '<span class="ok">API endpoint: ' . (defined('SITE_URL') ? SITE_URL : '???') . '/api/progress-bar.php</span>';
echo '<br>Admin session: ' . (!empty($_SESSION['admin_id']) ? '<span class="ok">✅ Logged in as admin</span>' : '<span class="err">❌ NOT logged in as admin — API save will fail</span>');
?>

<h2>8. PHP File Check</h2>
<?php
$files = [
    '/api/progress-bar.php',
    '/admin/pages/progress-bars.php',
    '/includes/footer.php',
    '/includes/functions.php',
    '/pages/cart.php',
];
foreach ($files as $f) {
    $path = __DIR__ . $f;
    if (file_exists($path)) {
        $size = filesize($path);
        $mod = date('Y-m-d H:i:s', filemtime($path));
        echo "<span class='ok'>✅ $f</span> ({$size} bytes, modified $mod)<br>";
    } else {
        echo "<span class='err'>❌ $f MISSING</span><br>";
    }
}
?>

<h2>9. JS Constant Test</h2>
<?php
$__progressBar = null;
try {
    $__progressBar = $db->fetch("SELECT * FROM checkout_progress_bars WHERE is_active = 1 LIMIT 1");
    if ($__progressBar) {
        $__progressBar['tiers'] = json_decode($__progressBar['tiers'] ?? '[]', true) ?: [];
        $__progressBar['config'] = json_decode($__progressBar['config'] ?? '{}', true) ?: [];
    }
} catch (Throwable $e) {}

$jsConst = json_encode([
    'enabled' => !empty($__progressBar) && !empty($__progressBar['tiers']),
    'template' => intval($__progressBar['template'] ?? 1),
    'tiers' => $__progressBar['tiers'] ?? [],
    'config' => $__progressBar['config'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo '<pre>PROGRESS_BAR = ' . htmlspecialchars($jsConst) . '</pre>';
if (!empty($__progressBar) && !empty($__progressBar['tiers'])) {
    echo '<span class="ok">✅ Would render on checkout</span>';
} else {
    echo '<span class="err">❌ Would NOT render — enabled=false or no tiers</span>';
}
?>

<p style="margin-top:30px;color:#666">Delete this file after debugging: <code>rm pb-diag.php</code></p>
</body></html>
