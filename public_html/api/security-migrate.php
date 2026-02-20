<?php
/**
 * Security Migration — Run once after deploying security.php v2
 * Adds auto_blocked column and clears all existing IP blocks
 * 
 * Access: /api/security-migrate.php (admin only, one-time)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized — login to admin first']);
    exit;
}

$db = Database::getInstance();
$results = [];

// 1. Add auto_blocked column if it doesn't exist
try {
    $cols = $db->fetchAll("SHOW COLUMNS FROM security_ip_rules LIKE 'auto_blocked'");
    if (empty($cols)) {
        $db->query("ALTER TABLE security_ip_rules ADD COLUMN auto_blocked TINYINT(1) DEFAULT 0 AFTER reason");
        $results[] = '✅ Added auto_blocked column to security_ip_rules';
    } else {
        $results[] = '⏭ auto_blocked column already exists';
    }
} catch (\Throwable $e) {
    $results[] = '⚠ Could not add column: ' . $e->getMessage();
}

// 2. Mark all existing blocks as auto_blocked (so public pages skip them)
try {
    $updated = $db->query("UPDATE security_ip_rules SET auto_blocked = 1 WHERE rule_type = 'block' AND expires_at IS NOT NULL");
    $results[] = "✅ Marked existing timed blocks as auto_blocked";
} catch (\Throwable $e) {
    $results[] = '⚠ Could not update blocks: ' . $e->getMessage();
}

// 3. Clear all expired blocks
try {
    $db->query("DELETE FROM security_ip_rules WHERE rule_type = 'block' AND expires_at IS NOT NULL AND expires_at < NOW()");
    $results[] = '✅ Cleared expired IP blocks';
} catch (\Throwable $e) {
    $results[] = '⚠ ' . $e->getMessage();
}

// 4. Show current block count
try {
    $count = $db->fetch("SELECT COUNT(*) as cnt FROM security_ip_rules WHERE rule_type = 'block'");
    $results[] = '📊 Active IP blocks remaining: ' . intval($count['cnt'] ?? 0);
    
    // List them
    $blocks = $db->fetchAll("SELECT ip_address, reason, auto_blocked, expires_at FROM security_ip_rules WHERE rule_type = 'block' ORDER BY expires_at DESC LIMIT 20");
    foreach ($blocks as $b) {
        $results[] = "  → {$b['ip_address']}: {$b['reason']} (auto={$b['auto_blocked']}, expires={$b['expires_at']})";
    }
} catch (\Throwable $e) {}

// 5. Option to clear ALL blocks (pass ?clear=all)
if (($_GET['clear'] ?? '') === 'all') {
    try {
        $db->query("DELETE FROM security_ip_rules WHERE rule_type = 'block'");
        $results[] = '🧹 CLEARED ALL IP BLOCKS — all visitors can now access the site';
    } catch (\Throwable $e) {
        $results[] = '⚠ Could not clear: ' . $e->getMessage();
    }
}

// 6. Update rate limit defaults if they're too aggressive
try {
    $rl = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'sec_rate_limit_requests'");
    if ($rl && intval($rl['setting_value'] ?? 0) < 120) {
        $db->query("UPDATE site_settings SET setting_value = '120' WHERE setting_key = 'sec_rate_limit_requests'");
        $results[] = '✅ Updated rate limit from ' . ($rl['setting_value'] ?? '?') . ' to 120 req/min';
    }
} catch (\Throwable $e) {}

echo json_encode([
    'success' => true,
    'message' => 'Security migration complete',
    'results' => $results,
    'next_step' => 'Visit /api/security-migrate.php?clear=all to clear ALL IP blocks if iOS users still cannot access',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
