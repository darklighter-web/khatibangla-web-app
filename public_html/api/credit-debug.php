<?php
/**
 * Credit System Diagnostic + Auto-Fix
 * Visit: https://khatibangla.com/api/credit-debug.php       (diagnose)
 * Visit: https://khatibangla.com/api/credit-debug.php?fix=1  (auto-fix)
 * DELETE THIS FILE AFTER DEBUGGING
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$db = Database::getInstance();
$r = [];

// 1. Session
$r['1_session'] = [
    'logged_in' => isCustomerLoggedIn(),
    'customer_id' => getCustomerId(),
    'customer_name' => getCustomerName(),
];

// 2. Settings
$r['2_settings'] = [
    'store_credits_enabled' => getSetting('store_credits_enabled', '1'),
    'store_credit_checkout' => getSetting('store_credit_checkout', '1'),
    'store_credit_conversion_rate' => getSetting('store_credit_conversion_rate', '0.75'),
];

// 3. Table checks
$r['3_tables'] = [];
foreach (['store_credit_transactions', 'customer_addresses'] as $t) {
    try {
        $db->fetch("SELECT 1 FROM {$t} LIMIT 1");
        $r['3_tables'][$t] = 'EXISTS ✓';
    } catch (\Throwable $e) {
        $r['3_tables'][$t] = 'MISSING ✗ — ' . $e->getMessage();
    }
}

// 4. Column checks
$r['4_columns'] = [];
$cols = [['orders','store_credit_used'],['customers','store_credit'],['products','store_credit_enabled'],['products','store_credit_amount']];
foreach ($cols as [$tbl, $col]) {
    try {
        $db->fetch("SELECT {$col} FROM {$tbl} LIMIT 1");
        $r['4_columns']["{$tbl}.{$col}"] = 'EXISTS ✓';
    } catch (\Throwable $e) {
        $r['4_columns']["{$tbl}.{$col}"] = 'MISSING ✗';
    }
}

// 5. Customer credit data (if logged in)
if (getCustomerId() > 0) {
    $cust = $db->fetch("SELECT id, name, phone, store_credit FROM customers WHERE id = ?", [getCustomerId()]);
    $r['5_customer'] = $cust ?: 'NOT FOUND';
    try {
        $txn = $db->fetch("SELECT COALESCE(SUM(amount),0) as bal, COUNT(*) as cnt FROM store_credit_transactions WHERE customer_id = ?", [getCustomerId()]);
        $r['5_customer']['txn_sum'] = $txn['bal'];
        $r['5_customer']['txn_count'] = $txn['cnt'];
    } catch (\Throwable $e) {
        $r['5_customer']['txn_error'] = $e->getMessage();
    }
}

// 6. Recent orders
try {
    $r['6_recent_orders'] = $db->fetchAll("SELECT id, order_number, total, store_credit_used, created_at FROM orders ORDER BY id DESC LIMIT 5");
} catch (\Throwable $e) {
    try {
        $r['6_recent_orders'] = $db->fetchAll("SELECT id, order_number, total, created_at FROM orders ORDER BY id DESC LIMIT 5");
        $r['6_recent_orders_NOTE'] = 'store_credit_used column MISSING';
    } catch (\Throwable $e2) {
        $r['6_recent_orders_error'] = $e2->getMessage();
    }
}

// 7. Credit debug log (file-based since error_log not configured)
$logFile = dirname(__DIR__) . '/tmp/credit-debug.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $r['7_credit_log'] = array_map('trim', array_slice($lines, -20));
    $r['7_credit_log_size'] = filesize($logFile) . ' bytes';
} else {
    $r['7_credit_log'] = 'No log file yet — place an order with credit to generate logs';
    $r['7_credit_log_path'] = $logFile;
}

// Also check PHP error log
try {
    $phpLog = ini_get('error_log');
    $r['7_php_error_log'] = $phpLog ?: 'NOT CONFIGURED';
} catch (\Throwable $e) {}

// 8. AUTO-FIX (add ?fix=1 to URL)
if (isset($_GET['fix'])) {
    $fixes = [];
    
    try {
        $db->query("CREATE TABLE IF NOT EXISTS store_credit_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            type ENUM('earn','spend','refund','admin_adjust','expire') NOT NULL,
            reference_type VARCHAR(30) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            balance_after DECIMAL(12,2) DEFAULT 0,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_type (type),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $fixes[] = '✓ store_credit_transactions table';
    } catch (\Throwable $e) {
        $fixes[] = '✗ store_credit_transactions: ' . $e->getMessage();
    }
    
    try {
        $db->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS store_credit DECIMAL(12,2) DEFAULT 0");
        $fixes[] = '✓ customers.store_credit';
    } catch (\Throwable $e) {
        $fixes[] = '✗ customers.store_credit: ' . $e->getMessage();
    }
    
    try {
        $db->fetch("SELECT store_credit_used FROM orders LIMIT 1");
        $fixes[] = '✓ orders.store_credit_used (already exists)';
    } catch (\Throwable $e) {
        try {
            $db->query("ALTER TABLE orders ADD COLUMN store_credit_used DECIMAL(12,2) DEFAULT 0 AFTER discount_amount");
            $fixes[] = '✓ orders.store_credit_used CREATED';
        } catch (\Throwable $e2) {
            $fixes[] = '✗ orders.store_credit_used: ' . $e2->getMessage();
        }
    }
    
    try {
        $db->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS store_credit_enabled TINYINT(1) DEFAULT 0");
        $db->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS store_credit_amount DECIMAL(12,2) DEFAULT 0");
        $fixes[] = '✓ products credit columns';
    } catch (\Throwable $e) {
        $fixes[] = '✗ products: ' . $e->getMessage();
    }
    
    try {
        $db->query("CREATE TABLE IF NOT EXISTS customer_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            label VARCHAR(50) DEFAULT 'Home',
            name VARCHAR(100),
            phone VARCHAR(20),
            address TEXT NOT NULL,
            city VARCHAR(50),
            area VARCHAR(100),
            postal_code VARCHAR(10),
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $fixes[] = '✓ customer_addresses table';
    } catch (\Throwable $e) {
        $fixes[] = '✗ customer_addresses: ' . $e->getMessage();
    }
    
    $r['8_FIXES_APPLIED'] = $fixes;
}

$r['php_version'] = PHP_VERSION;
$r['INSTRUCTIONS'] = 'Add ?fix=1 to URL to auto-create missing tables/columns. Delete this file after debugging.';

echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
