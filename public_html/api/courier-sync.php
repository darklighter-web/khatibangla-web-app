<?php
/**
 * Courier Status Sync (Polling)
 * Called from admin Order Management "Sync Courier Status" button
 * Polls Pathao & Steadfast APIs for all shipped orders and updates statuses
 * 
 * Also usable as a cron job: php /path/to/courier-sync.php
 * Or via URL: https://khatibangla.com/api/courier-sync.php?key=YOUR_CRON_KEY
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Allow cron access with a secret key, or admin session
$isCron = php_sapi_name() === 'cli' || ($_GET['key'] ?? '') === getSetting('courier_sync_key', '');
if (!$isCron) {
    // Check admin session
    require_once __DIR__ . '/../includes/session.php';
    if (empty($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$db = Database::getInstance();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$limit = min(100, max(10, intval($input['limit'] ?? $_GET['limit'] ?? 50)));

// Status mapping (same as webhook)
$pathaoMap = [
    'Pending'=>null, 'Picked'=>null, 'In_Transit'=>null, 'At_Transit'=>null,
    'Delivery_Ongoing'=>null, 'Delivered'=>'delivered', 'Partial_Delivered'=>'partial_delivered',
    'Return'=>'pending_return', 'Return_Ongoing'=>'pending_return', 'Returned'=>'pending_return',
    'Exchange'=>'pending_return', 'Hold'=>'on_hold', 'Cancelled'=>'pending_cancel',
    'Payment_Invoice'=>'delivered',
];
$steadfastMap = [
    'pending'=>null, 'in_review'=>null, 'delivered'=>'delivered',
    'delivered_approval_pending'=>'delivered', 'partial_delivered'=>'partial_delivered',
    'partial_delivered_approval_pending'=>'partial_delivered',
    'cancelled'=>'pending_cancel', 'cancelled_approval_pending'=>'pending_cancel',
    'hold'=>'on_hold', 'unknown'=>null, 'unknown_approval_pending'=>null,
];

$results = ['total' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'details' => []];

// Get all shipped orders with courier tracking (not yet delivered/cancelled/returned)
$activeStatuses = "'shipped','on_hold','pending_return','pending_cancel','partial_delivered'";
$orders = $db->fetchAll(
    "SELECT id, order_number, order_status, courier_name, courier_tracking_id, courier_consignment_id, pathao_consignment_id 
     FROM orders 
     WHERE order_status IN ({$activeStatuses}) 
       AND (courier_tracking_id IS NOT NULL AND courier_tracking_id != '' 
            OR pathao_consignment_id IS NOT NULL AND pathao_consignment_id != '')
     ORDER BY updated_at ASC 
     LIMIT {$limit}"
);

$results['total'] = count($orders);

// Load API classes
$pathao = null; $steadfast = null;
$pathaoFile = __DIR__ . '/pathao.php';
$steadfastFile = __DIR__ . '/steadfast.php';

if (file_exists($pathaoFile)) {
    require_once $pathaoFile;
    $pathao = new PathaoAPI();
}
if (file_exists($steadfastFile)) {
    require_once $steadfastFile;
    $steadfast = new SteadfastAPI();
}

foreach ($orders as $order) {
    try {
        $courierName = strtolower($order['courier_name'] ?? '');
        $consignmentId = $order['pathao_consignment_id'] ?: $order['courier_consignment_id'] ?: $order['courier_tracking_id'];
        
        if (empty($consignmentId)) {
            $results['skipped']++;
            continue;
        }
        
        $courierStatus = null;
        $newStatus = null;
        
        if (strpos($courierName, 'pathao') !== false && $pathao && $pathao->isConfigured()) {
            // Poll Pathao API
            $resp = $pathao->getOrderDetails($consignmentId);
            $courierStatus = $resp['data']['order_status'] ?? $resp['order_status'] ?? null;
            if ($courierStatus) {
                $newStatus = $pathaoMap[$courierStatus] ?? null;
            }
        } elseif (strpos($courierName, 'steadfast') !== false && $steadfast && $steadfast->isConfigured()) {
            // Poll Steadfast API
            $resp = $steadfast->getStatusByCid($consignmentId);
            $courierStatus = $resp['delivery_status'] ?? null;
            if ($courierStatus) {
                $newStatus = $steadfastMap[$courierStatus] ?? null;
            }
        } else {
            $results['skipped']++;
            continue;
        }
        
        if (!$courierStatus) {
            $results['skipped']++;
            continue;
        }
        
        // Always update courier_status column with raw value
        $updateData = ['courier_status' => $courierStatus, 'updated_at' => date('Y-m-d H:i:s')];
        
        if ($newStatus && $newStatus !== $order['order_status']) {
            // Don't allow backward transitions from terminal states
            $terminalStatuses = ['delivered', 'returned', 'cancelled'];
            if (in_array($order['order_status'], $terminalStatuses)) {
                $results['skipped']++;
                $results['details'][] = "#{$order['order_number']}: Already {$order['order_status']}, courier says {$courierStatus}";
                continue;
            }
            
            $updateData['order_status'] = $newStatus;
            
            if ($newStatus === 'delivered') {
                $updateData['delivered_at'] = date('Y-m-d H:i:s');
            }
            
            $db->update('orders', $updateData, 'id = ?', [$order['id']]);
            
            // Log status history
            try {
                $db->insert('order_status_history', [
                    'order_id' => $order['id'],
                    'status' => $newStatus,
                    'note' => "Auto-sync: {$courierName} → {$courierStatus}",
                ]);
            } catch (\Throwable $e) {}
            
            // Trigger credit actions
            if ($newStatus === 'delivered') {
                try { awardOrderCredits($order['id']); } catch (\Throwable $e) {}
            }
            
            $results['updated']++;
            $results['details'][] = "#{$order['order_number']}: {$order['order_status']} → {$newStatus} ({$courierStatus})";
        } else {
            // Just update the raw courier status
            $db->update('orders', $updateData, 'id = ?', [$order['id']]);
            $results['skipped']++;
        }
        
        // Throttle to avoid rate limits (100ms between requests)
        usleep(100000);
        
    } catch (\Throwable $e) {
        $results['errors']++;
        $results['details'][] = "#{$order['order_number']}: Error - " . $e->getMessage();
    }
}

// Log sync run
try {
    $dir = dirname(__DIR__) . '/tmp';
    @mkdir($dir, 0755, true);
    $line = date('Y-m-d H:i:s') . " SYNC: total={$results['total']} updated={$results['updated']} errors={$results['errors']} skipped={$results['skipped']}\n";
    @file_put_contents($dir . '/courier-sync.log', $line, FILE_APPEND | LOCK_EX);
} catch (\Throwable $e) {}

echo json_encode($results);
