<?php
/**
 * Admin Authentication & Helpers
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

function adminLogin($username, $password) {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT au.*, ar.role_name, ar.permissions FROM admin_users au JOIN admin_roles ar ON ar.id = au.role_id WHERE (au.username = ? OR au.email = ?) AND au.is_active = 1", [$username, $username]);
    
    if ($user && verifyPassword($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role_name'];
        $_SESSION['admin_permissions'] = json_decode($user['permissions'], true);
        
        $db->update('admin_users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        logActivity($user['id'], 'login', 'admin_users', $user['id']);
        return true;
    }
    return false;
}

function adminLogout() {
    if (isset($_SESSION['admin_id'])) {
        logActivity($_SESSION['admin_id'], 'logout');
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_permissions']);
    session_destroy();
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        // Check gate cookie before redirecting (hide admin panel existence)
        $secretKey = getSetting('admin_secret_key', 'menzio2026');
        $gateValid = isset($_COOKIE['_adm_gate']) && $_COOKIE['_adm_gate'] === hash('sha256', $secretKey . date('Ymd') . 'gate');
        
        if ($gateValid) {
            // Gate cookie valid — redirect to login normally
            redirect(adminUrl('login.php'));
        } else {
            // No gate cookie — show fake 404 to hide admin panel
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404 Not Found</title><style>body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f5f5;color:#333}div{text-align:center}h1{font-size:120px;font-weight:200;margin:0;color:#ddd}p{font-size:18px;color:#999}</style></head><body><div><h1>404</h1><p>The page you are looking for does not exist.</p></div></body></html>';
            exit;
        }
    }
}

function hasPermission($permission) {
    if (!isAdminLoggedIn()) return false;
    $perms = $_SESSION['admin_permissions'] ?? [];
    return in_array('all', $perms) || in_array($permission, $perms);
}

// ── Page-to-Permission Mapping ──
// Maps admin page filenames to required permission module
function getPagePermission($page) {
    $map = [
        // Always visible
        'dashboard' => null,
        'search' => null,
        'profile' => null,
        
        // Orders
        'web-orders' => 'orders', 'order-add' => 'orders', 'order-view' => 'orders',
        'approved-orders' => 'orders', 'orders' => 'orders', 'incomplete-orders' => 'orders',
        'order-management' => 'orders',
        
        // Products / Catalog
        'products' => 'products', 'product-form' => 'products',
        'categories' => 'categories', 'inventory' => 'inventory',
        'media' => 'products',
        
        // Customers
        'customers' => 'customers', 'customer-view' => 'customers', 'visitors' => 'customers',
        
        // Coupons
        'coupons' => 'coupons',
        
        // Shipping
        'courier' => 'courier', 'returns' => 'returns',
        
        // Finance
        'accounting' => 'accounting', 'expenses' => 'expenses', 'reports' => 'reports',
        
        // Content
        'page-builder' => 'cms_pages', 'shop-design' => 'settings',
        'checkout-fields' => 'settings', 'banners' => 'banners', 'cms-pages' => 'cms_pages',
        'landing-pages' => 'cms_pages', 'landing-page-builder' => 'cms_pages',
        
        // Support
        'live-chat' => 'orders', 'chat-settings' => 'settings',
        
        // Team
        'employees' => 'employees', 'tasks' => 'tasks',
        
        // System (super admin / settings)
        'settings' => 'settings', 'speed' => 'settings', 'security' => 'settings',
    ];
    return $map[$page] ?? null;
}

function canViewPage($page) {
    if (isSuperAdmin() && empty($_SESSION['view_as_role_id'])) return true; // Real super admin, not previewing
    $module = getPagePermission($page);
    if ($module === null) return true; // Pages with null = always visible
    // Check if user has ANY action in this module
    $perms = $_SESSION['admin_permissions'] ?? [];
    if (!is_array($perms)) $perms = []; // Safety: handle non-array
    if (in_array('all', $perms)) return true;
    foreach ($perms as $p) {
        // Match: exact module name "orders" OR action format "orders.view"
        if ($p === $module || strpos($p, $module . '.') === 0) return true;
    }
    return false;
}

// Guard: call at top of each admin page to block unauthorized access
function requirePermission($module, $action = 'view') {
    if (isSuperAdmin() && empty($_SESSION['view_as_role_id'])) return;
    if (!hasPermission($module . '.' . $action)) {
        $_SESSION['flash_error'] = '🚫 আপনার এই পেজে প্রবেশের অনুমতি নেই।';
        redirect(adminUrl('pages/dashboard.php'));
    }
}

function getAdminId() {
    return $_SESSION['admin_id'] ?? 0;
}

function isSuperAdmin() {
    return ($_SESSION['admin_role'] ?? '') === 'super_admin';
}

function getAdminName() {
    return $_SESSION['admin_name'] ?? 'Admin';
}

// Quick stats functions
function getDashboardStats() {
    $db = Database::getInstance();
    return [
        'total_orders' => $db->count('orders'),
        'pending_orders' => $db->count('orders', "order_status IN ('pending','processing')"),
        'approved_orders' => $db->count('orders', "order_status NOT IN ('pending','processing','cancelled')"),
        'confirmed_orders' => $db->count('orders', "order_status = 'confirmed'"),
        'processing_orders' => $db->count('orders', "order_status = 'processing'"),
        'shipped_orders' => $db->count('orders', "order_status = 'shipped'"),
        'delivered_orders' => $db->count('orders', "order_status = 'delivered'"),
        'cancelled_orders' => $db->count('orders', "order_status = 'cancelled'"),
        'returned_orders' => $db->count('orders', "order_status = 'returned'"),
        'today_orders' => $db->count('orders', "DATE(created_at) = CURDATE()"),
        'today_revenue' => $db->fetch("SELECT COALESCE(SUM(total), 0) as rev FROM orders WHERE DATE(created_at) = CURDATE() AND order_status NOT IN ('cancelled','returned')")['rev'],
        'month_revenue' => $db->fetch("SELECT COALESCE(SUM(total), 0) as rev FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND order_status NOT IN ('cancelled','returned')")['rev'],
        'total_products' => $db->count('products'),
        'low_stock' => $db->count('products', "stock_quantity <= low_stock_threshold AND manage_stock = 1"),
        'total_customers' => $db->count('customers'),
        'blocked_customers' => $db->count('customers', "is_blocked = 1"),
        'incomplete_orders' => (function() use ($db) { try { return $db->count('incomplete_orders', "is_recovered = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"); } catch (\Throwable $e) { try { return $db->count('incomplete_orders', "recovered = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"); } catch (\Throwable $e2) { return 0; } } })(),
        'fake_orders' => $db->count('orders', "is_fake = 1"),
        'unread_notifications' => $db->count('notifications', "is_read = 0"),
        'pending_tasks' => $db->count('tasks', "status IN ('pending','in_progress')"),
        'chat_waiting' => (function() use ($db) { try { return $db->count('chat_conversations', "status IN ('waiting','active')"); } catch (\Throwable $e) { return 0; } })(),
    ];
}

function getRecentOrders($limit = 10) {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT ?", [$limit]);
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'processing' => 'bg-indigo-100 text-indigo-800',
        'ready_to_ship' => 'bg-violet-100 text-violet-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'returned' => 'bg-orange-100 text-orange-800',
        'on_hold' => 'bg-gray-100 text-gray-800',
        'no_response' => 'bg-rose-100 text-rose-800',
        'good_but_no_response' => 'bg-teal-100 text-teal-800',
        'advance_payment' => 'bg-emerald-100 text-emerald-800',
        'incomplete' => 'bg-amber-100 text-amber-800',
        'pending_return' => 'bg-amber-100 text-amber-800',
        'pending_cancel' => 'bg-pink-100 text-pink-800',
        'partial_delivered' => 'bg-cyan-100 text-cyan-800',
        'lost' => 'bg-stone-100 text-stone-800',
    ];
    return $badges[$status] ?? 'bg-gray-100 text-gray-800';
}

function getOrderStatusLabel($status) {
    $labels = [
        'pending' => 'Processing',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'ready_to_ship' => 'RTS',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
        'on_hold' => 'On Hold',
        'no_response' => 'No Response',
        'good_but_no_response' => 'Good But No Response',
        'advance_payment' => 'Advance Payment',
        'incomplete' => 'Incomplete',
        'pending_return' => 'Pending Return',
        'pending_cancel' => 'Pending Cancel',
        'partial_delivered' => 'Partial Delivered',
        'lost' => 'Lost',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function timeAgo($datetime) {
    if (empty($datetime)) return '';
    $now = time();
    $ago = strtotime($datetime);
    if (!$ago) return '';
    $diff = $now - $ago;
    if ($diff < 60)           return 'just now';
    if ($diff < 3600)         return floor($diff / 60) . ' min ago';
    if ($diff < 86400)        return 'about ' . floor($diff / 3600) . ' hours ago';
    if ($diff < 172800)       return 'yesterday';
    if ($diff < 604800)       return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000)      return floor($diff / 604800) . ' weeks ago';
    return date('d M Y', $ago);
}

function getCustomerRating($phone) {
    if (empty($phone)) return 0;
    $db = Database::getInstance();
    $phoneLike = '%' . substr(preg_replace('/[^0-9]/', '', $phone), -10) . '%';
    try {
        $sr = $db->fetch("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                   SUM(CASE WHEN order_status='returned' THEN 1 ELSE 0 END) as returned
            FROM orders WHERE customer_phone LIKE ?
        ", [$phoneLike]);
        $total = intval($sr['total']);
        $delivered = intval($sr['delivered']);
        $cancelled = intval($sr['cancelled']);
        $returned = intval($sr['returned']);
        // Rating: base 50 + 10 per delivered - 15 per cancel - 20 per return, capped 0-150
        $rating = 50 + ($delivered * 10) - ($cancelled * 15) - ($returned * 20);
        return max(0, min(150, $rating));
    } catch (Exception $e) {
        return 0;
    }
}
