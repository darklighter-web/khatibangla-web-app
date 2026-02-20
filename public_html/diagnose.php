<?php
/**
 * DEEP DIAGNOSTIC — Loads each include one-by-one to find the blocker
 * URL: khatibangla.com/diagnose.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
http_response_code(200);

$results = [];
$failed = false;

function test($label, $callback) {
    global $results, $failed;
    if ($failed) { $results[] = ['skip', $label, 'Skipped (previous step failed)']; return; }
    try {
        ob_start();
        $r = $callback();
        $output = ob_get_clean();
        if ($r === false) {
            $results[] = ['fail', $label, $output ?: 'Returned false'];
            $failed = true;
        } else {
            $results[] = ['pass', $label, $output ?: 'OK'];
        }
    } catch (\Throwable $e) {
        ob_end_clean();
        $results[] = ['fail', $label, $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()];
        $failed = true;
    }
}

// ── Test 1: Session ──
test('session.php', function() {
    require_once __DIR__ . '/includes/session.php';
    return session_status() === PHP_SESSION_ACTIVE ? true : false;
});

// ── Test 2: Database/Config ──
test('config/database.php', function() {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance();
    $r = $db->fetch("SELECT 1 as test");
    return ($r['test'] ?? 0) == 1;
});

// ── Test 3: Functions ──
test('functions.php', function() {
    require_once __DIR__ . '/includes/functions.php';
    return function_exists('getProducts');
});

// ── Test 4: Security — check what it WOULD do ──
test('security.php (loaded)', function() {
    // Check if SecurityGuard class already exists (from a previous include)
    if (class_exists('SecurityGuard')) return 'Already loaded';
    require_once __DIR__ . '/includes/security.php';
    return class_exists('SecurityGuard');
});

test('security.php (run check)', function() {
    $guard = $GLOBALS['_securityGuard'] ?? null;
    if (!$guard) return 'No guard instance';
    // Check what the guard detected
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $isAdmin = strpos($uri, '/admin/') !== false;
    $isApi = strpos($uri, '/api/') !== false;
    $isPublic = !$isAdmin && !$isApi;
    return "isPublic={$isPublic}, isAdmin={$isAdmin}, isApi={$isApi}";
});

// ── Test 5: Check CSP header in DB ──
test('CSP header check', function() {
    $db = Database::getInstance();
    $csp = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'sec_content_security_policy'");
    $val = $csp['setting_value'] ?? '';
    if (empty($val)) return 'No CSP configured (good)';
    return 'CSP SET: ' . $val;
});

// ── Test 6: Check ALL security settings ──
test('Security settings', function() {
    $db = Database::getInstance();
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'sec_%'");
    $out = '';
    foreach ($rows as $r) {
        $out .= $r['setting_key'] . '=' . $r['setting_value'] . ' | ';
    }
    return $out ?: 'No security settings found';
});

// ── Test 7: Check IP blocks ──
test('IP block check', function() {
    $db = Database::getInstance();
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_REAL_IP'] 
        ?? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) 
        ?: $_SERVER['REMOTE_ADDR'];
    
    $block = $db->fetch("SELECT * FROM security_ip_rules WHERE ip_address = ?", [$ip]);
    if ($block) return "BLOCKED: reason={$block['reason']}, type={$block['rule_type']}, expires={$block['expires_at']}";
    
    $total = $db->fetch("SELECT COUNT(*) as c FROM security_ip_rules WHERE rule_type = 'block'");
    return "Your IP ({$ip}) is NOT blocked. Total blocks in DB: " . intval($total['c'] ?? 0);
});

// ── Test 8: Queue system ──
test('queue.php', function() {
    require_once __DIR__ . '/includes/queue.php';
    return 'Loaded (did not block)';
});

// ── Test 9: Tracker ──
test('tracker.php', function() {
    require_once __DIR__ . '/includes/tracker.php';
    return function_exists('trackVisitor') ? 'OK' : false;
});

// ── Test 10: Check .htaccess ──
test('.htaccess check', function() {
    $htaccess = __DIR__ . '/.htaccess';
    if (!file_exists($htaccess)) return 'No root .htaccess file exists';
    $content = file_get_contents($htaccess);
    $lines = substr_count($content, "\n") + 1;
    // Check for blocking rules
    $flags = [];
    if (stripos($content, 'deny from') !== false) $flags[] = 'HAS deny rules';
    if (stripos($content, 'Require all denied') !== false) $flags[] = 'HAS Require denied';
    if (stripos($content, 'RewriteRule') !== false) $flags[] = 'HAS rewrites';
    if (stripos($content, 'ModSecurity') !== false) $flags[] = 'HAS ModSecurity';
    if (stripos($content, 'SecRule') !== false) $flags[] = 'HAS SecRule';
    if (stripos($content, 'block') !== false) $flags[] = 'Contains "block"';
    return "{$lines} lines. Flags: " . (empty($flags) ? 'none' : implode(', ', $flags));
});

// ── Test 11: Check response headers being sent ──
test('Response headers', function() {
    $headers = headers_list();
    return implode(' | ', $headers) ?: 'No custom headers';
});

// ── Test 12: Check OPcache ──
test('OPcache status', function() {
    if (!function_exists('opcache_get_status')) return 'OPcache not available';
    $status = opcache_get_status(false);
    if (!$status) return 'OPcache disabled';
    return 'OPcache ON — cached scripts: ' . ($status['opcache_statistics']['num_cached_scripts'] ?? '?') . 
           '. Security.php cached: ' . (isset($status['scripts'][__DIR__.'/includes/security.php']) ? 'YES (may serve old version!)' : 'no');
});

// ── Test 13: Actually try to render home page ──
test('Render home page', function() {
    // Simulate what index.php does
    $requestUri = '/';
    ob_start();
    $page = 'home';
    if (file_exists(__DIR__ . '/pages/home.php')) {
        include __DIR__ . '/pages/home.php';
        $output = ob_get_clean();
        return 'Rendered OK (' . strlen($output) . ' bytes)';
    }
    ob_end_clean();
    return 'home.php not found';
});

// ── Output ──
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deep Diagnostic</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, sans-serif; padding: 20px; background: #f8fafc; font-size: 14px; }
h1 { font-size: 20px; margin-bottom: 16px; }
.test { padding: 10px 14px; margin: 6px 0; border-radius: 8px; border-left: 4px solid; }
.pass { background: #f0fdf4; border-color: #22c55e; }
.fail { background: #fef2f2; border-color: #ef4444; }
.skip { background: #f5f5f5; border-color: #9ca3af; }
.label { font-weight: 700; }
.detail { color: #4b5563; font-size: 12px; margin-top: 4px; word-break: break-all; }
.env { background: white; border-radius: 8px; padding: 14px; margin-top: 20px; font-size: 12px; }
.env p { margin: 4px 0; }
.env .k { font-weight: 600; color: #6b7280; display: inline-block; width: 160px; }
.action { margin-top: 20px; }
.action a { display: inline-block; padding: 10px 20px; background: #dc2626; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 4px; }
.action a.green { background: #16a34a; }
</style>
</head><body>
<h1>🔬 Deep Diagnostic — iOS Access Debug</h1>

<?php foreach ($results as [$status, $label, $detail]): ?>
<div class="test <?= $status ?>">
    <span class="label"><?= $status === 'pass' ? '✅' : ($status === 'fail' ? '❌' : '⏭') ?> <?= htmlspecialchars($label) ?></span>
    <div class="detail"><?= htmlspecialchars($detail) ?></div>
</div>
<?php endforeach; ?>

<div class="env">
    <h2 style="margin-bottom:10px;">📋 Environment</h2>
    <p><span class="k">Your IP:</span> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '') ?></p>
    <p><span class="k">Forwarded IP:</span> <?= htmlspecialchars($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? 'none') ?></p>
    <p><span class="k">CF-Connecting-IP:</span> <?= htmlspecialchars($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'not Cloudflare') ?></p>
    <p><span class="k">User Agent:</span> <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'empty!') ?></p>
    <p><span class="k">Protocol:</span> <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'HTTPS ✅' : 'HTTP ⚠️' ?></p>
    <p><span class="k">Server:</span> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '') ?></p>
    <p><span class="k">PHP Version:</span> <?= phpversion() ?></p>
    <p><span class="k">Request URI:</span> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></p>
    <p><span class="k">HTTP Host:</span> <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?></p>
    <p><span class="k">Document Root:</span> <?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '') ?></p>
    <p><span class="k">.htaccess exists:</span> <?= file_exists(__DIR__ . '/.htaccess') ? 'YES (' . filesize(__DIR__ . '/.htaccess') . ' bytes)' : 'NO' ?></p>
    <p><span class="k">security.php size:</span> <?= file_exists(__DIR__ . '/includes/security.php') ? filesize(__DIR__ . '/includes/security.php') . ' bytes, modified ' . date('Y-m-d H:i:s', filemtime(__DIR__ . '/includes/security.php')) : 'NOT FOUND' ?></p>
    <p><span class="k">Session ID:</span> <?= session_id() ?: 'none' ?></p>
    <p><span class="k">Response Code:</span> <?= http_response_code() ?></p>
</div>

<?php if (file_exists(__DIR__ . '/.htaccess')): ?>
<div class="env" style="margin-top:12px;">
    <h2 style="margin-bottom:10px;">📄 .htaccess Contents</h2>
    <pre style="white-space:pre-wrap;font-size:11px;color:#374151;"><?= htmlspecialchars(file_get_contents(__DIR__ . '/.htaccess')) ?></pre>
</div>
<?php endif; ?>

<div class="action">
    <h2 style="margin:12px 0 8px;">🔧 Quick Actions</h2>
    <a href="?action=flush_opcache" class="green">Flush OPcache</a>
    <a href="?action=clear_blocks">Clear ALL IP Blocks</a>
    <a href="?action=clear_csp" class="green">Clear CSP Header</a>
    <a href="?action=disable_security">Disable Security Firewall</a>
    <a href="?action=show_htaccess" class="green">Show .htaccess</a>
</div>

<?php
// Handle actions
$action = $_GET['action'] ?? '';
if ($action) {
    echo '<div class="env" style="margin-top:12px;border:2px solid #3b82f6;"><h2 style="margin-bottom:10px;">⚡ Action Result</h2>';
    
    try {
        $db = Database::getInstance();
        
        switch ($action) {
            case 'flush_opcache':
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                    echo '<p style="color:#16a34a;font-weight:700;">✅ OPcache flushed! Old cached PHP files cleared.</p>';
                } else {
                    echo '<p>OPcache not available on this server.</p>';
                }
                break;
                
            case 'clear_blocks':
                $count = $db->fetch("SELECT COUNT(*) as c FROM security_ip_rules WHERE rule_type = 'block'");
                $db->query("DELETE FROM security_ip_rules WHERE rule_type = 'block'");
                echo '<p style="color:#16a34a;font-weight:700;">✅ Cleared ' . intval($count['c'] ?? 0) . ' IP blocks!</p>';
                break;
                
            case 'clear_csp':
                $db->query("DELETE FROM site_settings WHERE setting_key = 'sec_content_security_policy'");
                echo '<p style="color:#16a34a;font-weight:700;">✅ CSP header cleared!</p>';
                break;
                
            case 'disable_security':
                // Set firewall_enabled = 0
                $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = 'sec_firewall_enabled'");
                if ($exists) {
                    $db->query("UPDATE site_settings SET setting_value = '0' WHERE setting_key = 'sec_firewall_enabled'");
                } else {
                    $db->insert('site_settings', ['setting_key' => 'sec_firewall_enabled', 'setting_value' => '0']);
                }
                echo '<p style="color:#16a34a;font-weight:700;">✅ Security firewall DISABLED. All visitors can access all pages.</p>';
                echo '<p style="color:#6b7280;">Re-enable from Admin → Security when confirmed working.</p>';
                break;
                
            case 'show_htaccess':
                $htFile = __DIR__ . '/.htaccess';
                if (file_exists($htFile)) {
                    echo '<pre style="white-space:pre-wrap;font-size:11px;">' . htmlspecialchars(file_get_contents($htFile)) . '</pre>';
                } else {
                    echo '<p>No .htaccess file in document root.</p>';
                    // Check parent dirs
                    $dir = dirname(__DIR__);
                    for ($i = 0; $i < 3; $i++) {
                        if (file_exists($dir . '/.htaccess')) {
                            echo '<p>Found .htaccess at: ' . $dir . '</p>';
                            echo '<pre style="white-space:pre-wrap;font-size:11px;">' . htmlspecialchars(file_get_contents($dir . '/.htaccess')) . '</pre>';
                        }
                        $dir = dirname($dir);
                    }
                }
                break;
        }
    } catch (\Throwable $e) {
        echo '<p style="color:#dc2626;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';
}
?>

<div class="env" style="margin-top:12px;background:#fffbeb;border:1px solid #f59e0b;">
    <h2 style="margin-bottom:8px;">📱 iOS Safari Cache Fix</h2>
    <p>If the diagnostic shows everything green but the site still shows "Access Denied":</p>
    <p style="margin-top:8px;"><b>1.</b> On iPhone, go to Settings → Safari → Clear History and Website Data</p>
    <p><b>2.</b> Or: Settings → Safari → Advanced → Website Data → find khatibangla.com → Delete</p>
    <p><b>3.</b> Then try opening the site again in Safari</p>
</div>

</body></html>
