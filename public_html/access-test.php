<?php
/**
 * Access Diagnostic — NO security middleware, NO session, NO database
 * If iOS Safari can see this page, the issue is in PHP code.
 * If iOS Safari CANNOT see this page, the issue is hosting/server level (WAF, .htaccess, Cloudflare).
 * 
 * URL: yoursite.com/access-test.php
 */

// Deliberately NO includes — pure standalone PHP
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 30px; background: #f0fdf4; }
        .card { background: white; border-radius: 12px; padding: 24px; max-width: 600px; margin: 20px auto; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .ok { color: #16a34a; font-size: 24px; font-weight: 700; }
        .info { margin-top: 16px; font-size: 14px; color: #374151; line-height: 1.8; }
        .label { font-weight: 600; color: #6b7280; display: inline-block; width: 120px; }
        h2 { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <p class="ok">✅ ACCESS OK — This page loaded successfully</p>
        <div class="info">
            <h2>Device Info</h2>
            <p><span class="label">IP:</span> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></p>
            <p><span class="label">Forwarded:</span> <?= htmlspecialchars($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? 'none') ?></p>
            <p><span class="label">User Agent:</span> <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'empty') ?></p>
            <p><span class="label">Protocol:</span> <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'HTTPS' : 'HTTP' ?></p>
            <p><span class="label">CF-Ray:</span> <?= htmlspecialchars($_SERVER['HTTP_CF_RAY'] ?? 'not behind Cloudflare') ?></p>
            <p><span class="label">Server:</span> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?></p>
            <p><span class="label">Time:</span> <?= date('Y-m-d H:i:s T') ?></p>
        </div>
        <div class="info" style="margin-top:20px; padding-top:16px; border-top:1px solid #e5e7eb;">
            <h2>Diagnosis</h2>
            <p>If you can see this on iOS Safari → the block is in PHP security code (fixable)</p>
            <p>If you CANNOT see this on iOS Safari → the block is at server level (hosting WAF, Cloudflare, .htaccess)</p>
        </div>
    </div>
<?php
// Also try to check DB for IP blocks (won't crash if DB not available)
try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance();
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) ?: $_SERVER['REMOTE_ADDR'];
    
    $blocks = $db->fetchAll(
        "SELECT ip_address, rule_type, reason, expires_at, hit_count FROM security_ip_rules WHERE rule_type = 'block' AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY hit_count DESC LIMIT 20"
    );
    
    $myBlock = $db->fetch(
        "SELECT * FROM security_ip_rules WHERE ip_address = ? AND rule_type = 'block'",
        [$ip]
    );
    
    echo '<div class="card">';
    echo '<h2>🔍 IP Block Check</h2>';
    echo '<div class="info">';
    echo '<p><span class="label">Your IP:</span> <b>' . htmlspecialchars($ip) . '</b></p>';
    
    if ($myBlock) {
        echo '<p style="color:#dc2626;font-weight:700;font-size:18px;margin:12px 0;">⚠️ YOUR IP IS BLOCKED IN DATABASE</p>';
        echo '<p><span class="label">Reason:</span> ' . htmlspecialchars($myBlock['reason'] ?? '') . '</p>';
        echo '<p><span class="label">Expires:</span> ' . htmlspecialchars($myBlock['expires_at'] ?? 'PERMANENT') . '</p>';
        echo '<p><span class="label">Hit count:</span> ' . intval($myBlock['hit_count'] ?? 0) . '</p>';
        echo '<p style="margin-top:12px"><a href="?unblock=1" style="color:white;background:#dc2626;padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;">🔓 UNBLOCK MY IP NOW</a></p>';
    } else {
        echo '<p style="color:#16a34a;font-weight:600;">✅ Your IP is NOT blocked in the database</p>';
        echo '<p style="color:#6b7280;">If you still see "Access Denied" on other pages, the block is from the hosting server, not from PHP.</p>';
    }
    
    // Unblock action
    if (isset($_GET['unblock'])) {
        $db->query("DELETE FROM security_ip_rules WHERE ip_address = ? AND rule_type = 'block'", [$ip]);
        echo '<p style="color:#16a34a;font-weight:700;margin-top:12px;">✅ UNBLOCKED! Refresh the main site now.</p>';
    }
    
    // Clear all action
    if (isset($_GET['clear_all'])) {
        $db->query("DELETE FROM security_ip_rules WHERE rule_type = 'block' AND expires_at IS NOT NULL");
        echo '<p style="color:#16a34a;font-weight:700;margin-top:12px;">✅ ALL temporary blocks cleared!</p>';
    }
    
    echo '<p style="margin-top:16px;"><b>All active blocks (' . count($blocks) . '):</b></p>';
    if (empty($blocks)) {
        echo '<p style="color:#16a34a;">No active IP blocks found.</p>';
    } else {
        echo '<table style="width:100%;font-size:12px;margin-top:8px;border-collapse:collapse;">';
        echo '<tr style="background:#f3f4f6;"><th style="padding:6px;text-align:left;">IP</th><th style="padding:6px;">Reason</th><th style="padding:6px;">Expires</th><th style="padding:6px;">Hits</th></tr>';
        foreach ($blocks as $b) {
            $isMe = ($b['ip_address'] === $ip) ? 'background:#fef2f2;font-weight:700;' : '';
            echo '<tr style="border-top:1px solid #e5e7eb;' . $isMe . '">';
            echo '<td style="padding:6px;font-family:monospace;">' . htmlspecialchars($b['ip_address']) . ($b['ip_address'] === $ip ? ' ← YOU' : '') . '</td>';
            echo '<td style="padding:6px;">' . htmlspecialchars($b['reason'] ?? '') . '</td>';
            echo '<td style="padding:6px;">' . htmlspecialchars($b['expires_at'] ?? 'Permanent') . '</td>';
            echo '<td style="padding:6px;text-align:center;">' . intval($b['hit_count']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p style="margin-top:12px;"><a href="?clear_all=1" style="color:white;background:#f59e0b;padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;">🧹 Clear ALL temporary blocks</a></p>';
    }
    echo '</div></div>';
    
} catch (\Throwable $e) {
    echo '<div class="card"><p style="color:#6b7280;">Could not check database: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
}
?>
</body>
</html>
