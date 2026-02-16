<?php
/**
 * Secret Admin Entry Point
 * 
 * Access: https://khatibangla.com/admin-panel.php?key=YOUR_SECRET
 * 
 * This file replaces direct /admin/ access.
 * - Sets a secure gate cookie
 * - Redirects to /admin/login.php
 * - The admin folder shows 404 without this cookie
 * 
 * To change this URL: rename this file and update admin_entry_path setting
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$secretKey = getSetting('admin_secret_key', 'menzio2026');
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $secretKey) {
    // Show fake 404 - same as admin/login.php
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title><style>body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f5f5;color:#333}div{text-align:center}h1{font-size:120px;font-weight:200;margin:0;color:#ddd}p{font-size:18px;color:#999}</style></head><body><div><h1>404</h1><p>The page you are looking for does not exist.</p></div></body></html>';
    exit;
}

// Valid key — set gate cookie for 24h
$isSecure = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    ($_SERVER['SERVER_PORT'] ?? 0) == 443 ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
);

$cookieValue = hash('sha256', $secretKey . date('Ymd') . 'gate');
setcookie('_adm_gate', $cookieValue, [
    'expires' => time() + 86400,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Log admin access
try {
    $db = Database::getInstance();
    $db->insert('security_logs', [
        'event_type' => 'admin_gate_access',
        'severity' => 'low',
        'ip_address' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'payload' => 'Admin gate accessed successfully',
        'blocked' => 0,
    ]);
} catch (\Throwable $e) {}

// Redirect to admin (clean URL, no key visible)
header('Location: /admin/login.php');
exit;
