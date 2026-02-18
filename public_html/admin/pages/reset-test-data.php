<?php
/**
 * Super Admin — Reset Test Data
 * Clears all order data for testing. SUPER ADMIN ONLY.
 * Place in: public_html/admin/pages/reset-test-data.php
 */
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Reset Test Data';
require_once __DIR__ . '/../includes/auth.php';

// SUPER ADMIN ONLY
if (!isSuperAdmin()) {
    echo '<div style="padding:40px;text-align:center;font-size:18px;color:#dc2626;">🚫 Access Denied — Super Admin only</div>';
    exit;
}

$db = Database::getInstance();
$msg = '';
$msgType = '';

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_data') {
    $confirm = trim($_POST['confirm_text'] ?? '');
    if ($confirm !== 'DELETE ALL ORDERS') {
        $msg = 'Confirmation text did not match. Type exactly: DELETE ALL ORDERS';
        $msgType = 'error';
    } else {
        $selected = $_POST['tables'] ?? [];
        $results = [];
        $errors = [];

        // Disable FK checks
        try { $db->query("SET FOREIGN_KEY_CHECKS = 0"); } catch (\Throwable $e) {}

        // Orders + order items (core)
        if (in_array('orders', $selected)) {
            try {
                $cnt = $db->fetch("SELECT COUNT(*) as c FROM orders")['c'] ?? 0;
                $db->query("TRUNCATE TABLE order_items");
                $db->query("TRUNCATE TABLE orders");
                $results[] = "✅ Orders: {$cnt} orders + all order items cleared";
            } catch (\Throwable $e) {
                // TRUNCATE may fail with FK — try DELETE
                try {
                    $db->query("DELETE FROM order_items");
                    $db->query("DELETE FROM orders");
                    $db->query("ALTER TABLE orders AUTO_INCREMENT = 1");
                    $db->query("ALTER TABLE order_items AUTO_INCREMENT = 1");
                    $results[] = "✅ Orders + items cleared (via DELETE)";
                } catch (\Throwable $e2) { $errors[] = "❌ Orders: " . $e2->getMessage(); }
            }
        }

        // Order status history
        if (in_array('order_history', $selected)) {
            try {
                $db->query("TRUNCATE TABLE order_status_history");
                $results[] = "✅ Order status history cleared";
            } catch (\Throwable $e) {
                try { $db->query("DELETE FROM order_status_history"); $results[] = "✅ Order status history cleared"; }
                catch (\Throwable $e2) { $errors[] = "❌ Status history: " . $e2->getMessage(); }
            }
        }

        // Courier uploads log
        if (in_array('courier_uploads', $selected)) {
            try {
                $db->query("TRUNCATE TABLE courier_uploads");
                $results[] = "✅ Courier uploads log cleared";
            } catch (\Throwable $e) { $errors[] = "⚠️ courier_uploads: " . $e->getMessage(); }
        }

        // Courier webhook log
        if (in_array('webhook_logs', $selected)) {
            try {
                $db->query("TRUNCATE TABLE courier_webhook_log");
                $results[] = "✅ Webhook logs cleared";
            } catch (\Throwable $e) { $errors[] = "⚠️ webhook_logs: " . $e->getMessage(); }
        }

        // Pathao API logs
        if (in_array('api_logs', $selected)) {
            try {
                $db->query("TRUNCATE TABLE pathao_api_logs");
                $results[] = "✅ Pathao API logs cleared";
            } catch (\Throwable $e) { $errors[] = "⚠️ pathao_api_logs: " . $e->getMessage(); }
        }

        // Shipments
        if (in_array('shipments', $selected)) {
            try {
                $db->query("TRUNCATE TABLE shipments");
                $results[] = "✅ Shipments cleared";
            } catch (\Throwable $e) { $errors[] = "⚠️ shipments: " . $e->getMessage(); }
        }

        // Store credit transactions
        if (in_array('credits', $selected)) {
            try {
                $db->query("TRUNCATE TABLE store_credit_transactions");
                // Reset customer credit balances
                $db->query("UPDATE customers SET store_credit = 0 WHERE store_credit > 0");
                $results[] = "✅ Store credit transactions cleared + balances reset";
            } catch (\Throwable $e) { $errors[] = "⚠️ credits: " . $e->getMessage(); }
        }

        // Customers
        if (in_array('customers', $selected)) {
            try {
                $cnt = $db->fetch("SELECT COUNT(*) as c FROM customers")['c'] ?? 0;
                $db->query("TRUNCATE TABLE customers");
                $results[] = "✅ Customers: {$cnt} records cleared";
            } catch (\Throwable $e) {
                try { $db->query("DELETE FROM customers"); $db->query("ALTER TABLE customers AUTO_INCREMENT = 1"); $results[] = "✅ Customers cleared"; }
                catch (\Throwable $e2) { $errors[] = "❌ Customers: " . $e2->getMessage(); }
            }
        }

        // Blocked phones
        if (in_array('blocked', $selected)) {
            try {
                $db->query("TRUNCATE TABLE blocked_phones");
                $results[] = "✅ Blocked phones list cleared";
            } catch (\Throwable $e) { $errors[] = "⚠️ blocked_phones: " . $e->getMessage(); }
        }

        // Re-enable FK checks
        try { $db->query("SET FOREIGN_KEY_CHECKS = 1"); } catch (\Throwable $e) {}

        // Log this action
        try { logActivity($_SESSION['admin_id'] ?? 0, 'reset_test_data', 'system', 0, 'Cleared: ' . implode(', ', $selected)); } catch (\Throwable $e) {}

        $msg = implode("\n", array_merge($results, $errors));
        $msgType = empty($errors) ? 'success' : 'warning';
    }
}

// Get current counts
$counts = [];
$tables = [
    'orders' => 'Orders',
    'order_items' => 'Order Items',
    'order_status_history' => 'Status History',
    'courier_uploads' => 'Courier Uploads',
    'courier_webhook_log' => 'Webhook Logs',
    'pathao_api_logs' => 'API Logs',
    'shipments' => 'Shipments',
    'store_credit_transactions' => 'Credit Transactions',
    'customers' => 'Customers',
    'blocked_phones' => 'Blocked Phones',
];
foreach ($tables as $t => $label) {
    try { $r = $db->fetch("SELECT COUNT(*) as c FROM {$t}"); $counts[$t] = intval($r['c'] ?? 0); }
    catch (\Throwable $e) { $counts[$t] = -1; }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto py-8 px-4">
    <div class="bg-red-50 border-2 border-red-300 rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold text-red-800 mb-2">🗑️ Reset Test Data</h1>
        <p class="text-red-600 text-sm">This will <b>permanently delete</b> selected data. Use only during testing.</p>
        <p class="text-red-500 text-xs mt-1">Logged in as: <b><?= e($_SESSION['admin_name'] ?? 'Unknown') ?></b> (Super Admin)</p>
    </div>

    <?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-xl border <?= $msgType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($msgType === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800') ?>">
        <pre class="text-sm whitespace-pre-wrap"><?= e($msg) ?></pre>
    </div>
    <?php endif; ?>

    <!-- Current Data Overview -->
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h2 class="font-bold text-gray-800 mb-4">📊 Current Data</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <?php foreach ($tables as $t => $label): ?>
            <div class="text-center p-3 bg-gray-50 rounded-lg border">
                <p class="text-2xl font-bold <?= $counts[$t] > 0 ? 'text-blue-600' : ($counts[$t] < 0 ? 'text-gray-300' : 'text-gray-400') ?>">
                    <?= $counts[$t] >= 0 ? number_format($counts[$t]) : '—' ?>
                </p>
                <p class="text-[10px] text-gray-500 mt-1"><?= $label ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reset Form -->
    <form method="POST" onsubmit="return confirmReset()" class="bg-white rounded-xl shadow-sm border p-6">
        <input type="hidden" name="action" value="reset_data">
        <h2 class="font-bold text-gray-800 mb-4">⚡ Select What to Clear</h2>

        <div class="space-y-3 mb-6">
            <!-- Quick select -->
            <div class="flex gap-2 mb-4">
                <button type="button" onclick="toggleAll(true)" class="px-3 py-1.5 bg-gray-100 rounded-lg text-xs font-medium hover:bg-gray-200">Select All</button>
                <button type="button" onclick="toggleAll(false)" class="px-3 py-1.5 bg-gray-100 rounded-lg text-xs font-medium hover:bg-gray-200">Deselect All</button>
                <button type="button" onclick="selectGroup('orders','order_history','courier_uploads','shipments')" class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100">Orders Only</button>
                <button type="button" onclick="selectGroup('webhook_logs','api_logs')" class="px-3 py-1.5 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-100">Logs Only</button>
            </div>

            <label class="flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100">
                <input type="checkbox" name="tables[]" value="orders" class="tbl-cb w-4 h-4 accent-red-600">
                <div class="flex-1">
                    <span class="font-semibold text-red-800">Orders + Order Items</span>
                    <span class="text-xs text-red-500 ml-2">(<?= number_format($counts['orders']) ?> orders, <?= number_format($counts['order_items']) ?> items)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-orange-50 border border-orange-200 rounded-lg cursor-pointer hover:bg-orange-100">
                <input type="checkbox" name="tables[]" value="order_history" class="tbl-cb w-4 h-4 accent-orange-600">
                <div class="flex-1">
                    <span class="font-semibold text-orange-800">Order Status History</span>
                    <span class="text-xs text-orange-500 ml-2">(<?= number_format($counts['order_status_history']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg cursor-pointer hover:bg-yellow-100">
                <input type="checkbox" name="tables[]" value="courier_uploads" class="tbl-cb w-4 h-4 accent-yellow-600">
                <div class="flex-1">
                    <span class="font-semibold text-yellow-800">Courier Uploads Log</span>
                    <span class="text-xs text-yellow-500 ml-2">(<?= number_format($counts['courier_uploads']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg cursor-pointer hover:bg-yellow-100">
                <input type="checkbox" name="tables[]" value="shipments" class="tbl-cb w-4 h-4 accent-yellow-600">
                <div class="flex-1">
                    <span class="font-semibold text-yellow-800">Shipments</span>
                    <span class="text-xs text-yellow-500 ml-2">(<?= number_format($counts['shipments']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-lg cursor-pointer hover:bg-purple-100">
                <input type="checkbox" name="tables[]" value="webhook_logs" class="tbl-cb w-4 h-4 accent-purple-600">
                <div class="flex-1">
                    <span class="font-semibold text-purple-800">Webhook Logs</span>
                    <span class="text-xs text-purple-500 ml-2">(<?= number_format($counts['courier_webhook_log']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-lg cursor-pointer hover:bg-purple-100">
                <input type="checkbox" name="tables[]" value="api_logs" class="tbl-cb w-4 h-4 accent-purple-600">
                <div class="flex-1">
                    <span class="font-semibold text-purple-800">Pathao API Logs</span>
                    <span class="text-xs text-purple-500 ml-2">(<?= number_format($counts['pathao_api_logs']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100">
                <input type="checkbox" name="tables[]" value="credits" class="tbl-cb w-4 h-4 accent-green-600">
                <div class="flex-1">
                    <span class="font-semibold text-green-800">Store Credit Transactions</span>
                    <span class="text-xs text-green-500 ml-2">(<?= number_format($counts['store_credit_transactions']) ?> records — also resets balances)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg cursor-pointer hover:bg-blue-100">
                <input type="checkbox" name="tables[]" value="customers" class="tbl-cb w-4 h-4 accent-blue-600">
                <div class="flex-1">
                    <span class="font-semibold text-blue-800">Customers</span>
                    <span class="text-xs text-blue-500 ml-2">(<?= number_format($counts['customers']) ?> records)</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="tables[]" value="blocked" class="tbl-cb w-4 h-4 accent-gray-600">
                <div class="flex-1">
                    <span class="font-semibold text-gray-800">Blocked Phones List</span>
                    <span class="text-xs text-gray-500 ml-2">(<?= number_format($counts['blocked_phones']) ?> records)</span>
                </div>
            </label>
        </div>

        <!-- Confirmation -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
            <label class="block text-sm font-bold text-red-800 mb-2">⚠️ Type <code class="bg-red-100 px-2 py-0.5 rounded font-mono">DELETE ALL ORDERS</code> to confirm:</label>
            <input type="text" name="confirm_text" id="confirmInput" placeholder="Type here..." autocomplete="off"
                   class="w-full px-4 py-3 border-2 border-red-300 rounded-lg font-mono text-lg focus:border-red-500 focus:ring-2 focus:ring-red-200">
        </div>

        <button type="submit" id="resetBtn" disabled
                class="w-full py-4 bg-red-600 text-white rounded-xl font-bold text-lg hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
            🗑️ Permanently Delete Selected Data
        </button>

        <p class="text-center text-xs text-gray-400 mt-3">
            This action is logged and cannot be undone.
            <a href="<?= adminUrl('pages/order-management.php') ?>" class="text-blue-500 underline ml-1">← Back to Orders</a>
        </p>
    </form>
</div>

<script>
document.getElementById('confirmInput').addEventListener('input', function() {
    document.getElementById('resetBtn').disabled = this.value.trim() !== 'DELETE ALL ORDERS';
});

function confirmReset() {
    const checked = document.querySelectorAll('.tbl-cb:checked');
    if (checked.length === 0) { alert('Select at least one data group to clear.'); return false; }
    const names = [...checked].map(c => c.closest('label').querySelector('.font-semibold').textContent).join(', ');
    return confirm(`⚠️ FINAL WARNING\n\nYou are about to permanently delete:\n${names}\n\nThis CANNOT be undone. Continue?`);
}

function toggleAll(state) {
    document.querySelectorAll('.tbl-cb').forEach(cb => cb.checked = state);
}

function selectGroup(...values) {
    document.querySelectorAll('.tbl-cb').forEach(cb => cb.checked = values.includes(cb.value));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
