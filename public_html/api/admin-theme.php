<?php
/**
 * Admin Theme Toggle API
 * Saves theme preference (light/dark) to admin_users table
 */
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$theme = $_POST['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'])) $theme = 'light';

try {
    $db = Database::getInstance();
    // Try updating; if column doesn't exist, add it first
    try {
        $db->query("UPDATE admin_users SET admin_theme = ? WHERE id = ?", [$theme, $_SESSION['admin_id']]);
    } catch (\Throwable $e) {
        $db->query("ALTER TABLE admin_users ADD COLUMN admin_theme VARCHAR(20) DEFAULT 'light'");
        $db->query("UPDATE admin_users SET admin_theme = ? WHERE id = ?", [$theme, $_SESSION['admin_id']]);
    }
    echo json_encode(['success' => true, 'theme' => $theme]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
