<?php
/**
 * Courier Lookup API — Fetches REAL data from:
 *   1) Our own DB (per-courier stats)
 *   2) Pathao / Steadfast APIs (live courier status)
 *
 * GET /admin/api/courier-lookup.php?phone=01XXXXXXXXX
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');

if (strlen($phone) < 10) {
    echo json_encode(['error' => 'Invalid phone number']);
    exit;
}

$phoneLike = '%' . substr($phone, -10) . '%';

/* ─── 1. Our DB orders ─── */
$orders = $db->fetchAll(
    "SELECT id, order_number, order_status, courier_name, courier_tracking_id, courier_consignment_id,
            courier_status, total, created_at
     FROM orders
     WHERE customer_phone LIKE ?
       AND order_status NOT IN ('incomplete', 'pending')
     ORDER BY created_at DESC",
    [$phoneLike]
);

$courierOrders = ['Pathao' => [], 'Steadfast' => [], 'CarryBee' => [], 'Other' => []];
foreach ($orders as $o) {
    $cn = $o['courier_name'] ?? '';
    if (stripos($cn, 'pathao') !== false)       $courierOrders['Pathao'][] = $o;
    elseif (stripos($cn, 'steadfast') !== false) $courierOrders['Steadfast'][] = $o;
    elseif (stripos($cn, 'carrybee') !== false)  $courierOrders['CarryBee'][] = $o;
    else                                         $courierOrders['Other'][] = $o;
}

/* ─── Status mappings ─── */
$pathaoDelivered  = ['Delivered', 'Payment_Invoice'];
$pathaoReturn     = ['Return', 'Returned', 'Return_Ongoing', 'Exchange'];
$pathaoCancelled  = ['Cancelled'];
$sfDelivered      = ['delivered', 'delivered_approval_pending'];
$sfCancelled      = ['cancelled', 'cancelled_approval_pending'];

/* ─── Load courier API classes ─── */
$pathaoApi = null; $sfApi = null;
$pf = __DIR__ . '/../../api/pathao.php';
$sf = __DIR__ . '/../../api/steadfast.php';
if (file_exists($pf)) { try { require_once $pf; $pathaoApi = new PathaoAPI(); } catch (\Throwable $e) {} }
if (file_exists($sf)) { try { require_once $sf; $sfApi = new SteadfastAPI(); } catch (\Throwable $e) {} }

/* Helper: classify status */
function classifyStatus($status) {
    global $pathaoDelivered, $pathaoReturn, $pathaoCancelled, $sfDelivered, $sfCancelled;
    if (in_array($status, $pathaoDelivered) || in_array($status, $sfDelivered) || $status === 'delivered') return 'delivered';
    if (in_array($status, $pathaoCancelled) || in_array($status, $sfCancelled) || $status === 'cancelled') return 'cancelled';
    if (in_array($status, $pathaoReturn) || in_array($status, ['returned', 'pending_return'])) return 'returned';
    return 'pending';
}

/* ─── 2. Query courier APIs for live status ─── */
function processOrders($orders, $api, $type, $db) {
    $stats = ['total' => count($orders), 'success' => 0, 'cancelled' => 0, 'returned' => 0, 'pending' => 0, 'rate' => 0, 'api_checked' => 0, 'details' => []];
    foreach ($orders as $o) {
        $cid = $o['courier_consignment_id'] ?: $o['courier_tracking_id'];
        $apiStatus = null;

        if ($cid && $api) {
            $configured = false;
            try { $configured = $api->isConfigured(); } catch (\Throwable $e) {}
            if ($configured) {
                try {
                    if ($type === 'pathao') {
                        $resp = $api->getOrderDetails($cid);
                        $apiStatus = $resp['data']['order_status'] ?? $resp['order_status'] ?? null;
                    } else {
                        $resp = $api->getStatusByCid($cid);
                        $apiStatus = $resp['delivery_status'] ?? null;
                    }
                    $stats['api_checked']++;
                    if ($apiStatus && $apiStatus !== $o['courier_status']) {
                        try { $db->update('orders', ['courier_status' => $apiStatus], 'id = ?', [$o['id']]); } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {}
                usleep(100000);
            }
        }

        $status = $apiStatus ?: $o['courier_status'] ?: $o['order_status'];
        $cls = classifyStatus($status);
        if ($cls === 'delivered') $stats['success']++;
        elseif ($cls === 'cancelled') $stats['cancelled']++;
        elseif ($cls === 'returned') $stats['returned']++;
        else $stats['pending']++;

        $stats['details'][] = ['order' => $o['order_number'], 'our_status' => $o['order_status'], 'courier_status' => $apiStatus ?: $o['courier_status'], 'total' => $o['total']];
    }
    if ($stats['total'] > 0) $stats['rate'] = round(($stats['success'] / $stats['total']) * 100);
    return $stats;
}

$result = [];
$result['Pathao']    = processOrders($courierOrders['Pathao'], $pathaoApi, 'pathao', $db);
$result['Steadfast'] = processOrders($courierOrders['Steadfast'], $sfApi, 'steadfast', $db);

// CarryBee: local only
$cbStats = ['total' => count($courierOrders['CarryBee']), 'success' => 0, 'cancelled' => 0, 'returned' => 0, 'pending' => 0, 'rate' => 0, 'api_checked' => 0];
foreach ($courierOrders['CarryBee'] as $o) {
    $cls = classifyStatus($o['courier_status'] ?: $o['order_status']);
    if ($cls === 'delivered') $cbStats['success']++;
    elseif ($cls === 'cancelled') $cbStats['cancelled']++;
    elseif ($cls === 'returned') $cbStats['returned']++;
    else $cbStats['pending']++;
}
if ($cbStats['total'] > 0) $cbStats['rate'] = round(($cbStats['success'] / $cbStats['total']) * 100);
$result['CarryBee'] = $cbStats;

/* ─── Overall from OUR data ─── */
$allTotal     = array_sum(array_column($result, 'total')) + count($courierOrders['Other']);
$allSuccess   = array_sum(array_column($result, 'success'));
$allCancelled = array_sum(array_column($result, 'cancelled'));
$allReturned  = array_sum(array_column($result, 'returned'));
$overallRate  = $allTotal > 0 ? round(($allSuccess / $allTotal) * 100) : 0;

/* ─── Our Record ─── */
$ourRecord = ['is_new' => true, 'total_orders' => $allTotal, 'web_orders' => 0, 'web_cancels' => 0, 'first_order' => null, 'total_spent' => 0];
try {
    $ci = $db->fetch(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN channel IN ('web','WEB','website') THEN 1 ELSE 0 END) as web_orders,
                SUM(CASE WHEN channel IN ('web','WEB','website') AND order_status='cancelled' THEN 1 ELSE 0 END) as web_cancels,
                MIN(created_at) as first_order,
                SUM(CASE WHEN order_status='delivered' THEN total ELSE 0 END) as total_spent
         FROM orders WHERE customer_phone LIKE ?", [$phoneLike]
    );
    if ($ci) {
        $ourRecord['total_orders'] = intval($ci['total']);
        $ourRecord['web_orders']   = intval($ci['web_orders']);
        $ourRecord['web_cancels']  = intval($ci['web_cancels']);
        $ourRecord['first_order']  = $ci['first_order'];
        $ourRecord['total_spent']  = floatval($ci['total_spent']);
        $ourRecord['is_new']       = intval($ci['total']) <= 1;
    }
} catch (\Throwable $e) {}

/* ─── Output ─── */
echo json_encode([
    'phone'   => $phone,
    'overall' => [
        'total' => $allTotal, 'success' => $allSuccess,
        'cancelled' => $allCancelled, 'returned' => $allReturned, 'rate' => $overallRate,
    ],
    'couriers'      => $result,
    'our_record'    => $ourRecord,
    'api_available' => [
        'pathao'    => $pathaoApi && method_exists($pathaoApi, 'isConfigured') && $pathaoApi->isConfigured(),
        'steadfast' => $sfApi && method_exists($sfApi, 'isConfigured') && $sfApi->isConfigured(),
    ],
]);
