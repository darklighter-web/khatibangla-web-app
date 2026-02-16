<?php
/**
 * Unified Courier Webhook Endpoint
 * Receives status updates from Pathao, Steadfast, CarryBee
 * 
 * URLs to configure in courier dashboards:
 *   Pathao:    https://khatibangla.com/api/courier-webhook.php?courier=pathao
 *   Steadfast: https://khatibangla.com/api/courier-webhook.php?courier=steadfast
 *   CarryBee:  https://khatibangla.com/api/courier-webhook.php?courier=carrybee
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$courier = strtolower($_GET['courier'] ?? '');
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true) ?: $_POST;

// Log every webhook hit
_webhookLog($courier, $rawPayload);

if (empty($payload)) {
    // Pathao sends webhook_integration test event
    echo json_encode(['status' => 200, 'message' => 'Webhook active']);
    exit;
}

$db = Database::getInstance();

// ── Courier Status → Our Status Mapping ──
// Key principle: courier returns/cancels → pending_return/pending_cancel (staff confirms manually)
$statusMap = [
    'pathao' => [
        'Pending'           => null,        // No change, already shipped
        'Picked'            => null,
        'In_Transit'        => null,
        'At_Transit'        => null,
        'Delivery_Ongoing'  => null,
        'Delivered'         => 'delivered',
        'Partial_Delivered'  => 'partial_delivered',
        'Return'            => 'pending_return',
        'Return_Ongoing'    => 'pending_return',
        'Returned'          => 'pending_return', // NOT auto-returned, staff confirms
        'Exchange'          => 'pending_return',
        'Hold'              => 'on_hold',
        'Cancelled'         => 'pending_cancel',
        'Payment_Invoice'   => 'delivered',
    ],
    'steadfast' => [
        'pending'                              => null,
        'in_review'                            => null,
        'delivered'                            => 'delivered',
        'delivered_approval_pending'            => 'delivered',
        'partial_delivered'                    => 'partial_delivered',
        'partial_delivered_approval_pending'    => 'partial_delivered',
        'cancelled'                            => 'pending_cancel',
        'cancelled_approval_pending'           => 'pending_cancel',
        'hold'                                 => 'on_hold',
        'unknown'                              => null,
        'unknown_approval_pending'             => null,
    ],
    'carrybee' => [
        'delivered'        => 'delivered',
        'returned'         => 'pending_return',
        'partial'          => 'partial_delivered',
        'cancelled'        => 'pending_cancel',
        'lost'             => 'lost',
        'hold'             => 'on_hold',
        // Add more as CarryBee API docs become available
    ],
];

try {
    switch ($courier) {
        case 'pathao':
            $result = handlePathao($db, $payload, $statusMap['pathao']);
            break;
        case 'steadfast':
            $result = handleSteadfast($db, $payload, $statusMap['steadfast']);
            break;
        case 'carrybee':
            $result = handleCarryBee($db, $payload, $statusMap['carrybee']);
            break;
        default:
            echo json_encode(['status' => 400, 'message' => 'Unknown courier']);
            exit;
    }
    echo json_encode(['status' => 200, 'message' => $result]);
} catch (\Throwable $e) {
    _webhookLog($courier, 'ERROR: ' . $e->getMessage());
    echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
}
exit;

// ── Pathao Webhook Handler ──
function handlePathao($db, $payload, $map) {
    // Pathao webhook fields: event, order_status, consignment_id, merchant_order_id, delivery_fee
    $event = $payload['event'] ?? '';
    
    // Test event
    if ($event === 'webhook_integration') return 'Webhook integration confirmed';
    
    $consignmentId = $payload['consignment_id'] ?? '';
    $merchantOrderId = $payload['merchant_order_id'] ?? '';
    $courierStatus = $payload['order_status'] ?? $event ?? '';
    
    if (empty($consignmentId) && empty($merchantOrderId)) return 'No identifier';
    
    // Find order
    $order = null;
    if ($consignmentId) {
        $order = $db->fetch("SELECT * FROM orders WHERE pathao_consignment_id = ? OR courier_tracking_id = ?", [$consignmentId, $consignmentId]);
    }
    if (!$order && $merchantOrderId) {
        $order = $db->fetch("SELECT * FROM orders WHERE order_number = ?", [$merchantOrderId]);
    }
    if (!$order) return 'Order not found';
    
    return processStatusUpdate($db, $order, $courierStatus, $map, 'Pathao', $payload);
}

// ── Steadfast Webhook Handler ──
function handleSteadfast($db, $payload, $map) {
    // Steadfast webhook fields: consignment_id, invoice, status, cod_amount, updated_at
    $consignmentId = $payload['consignment_id'] ?? '';
    $invoice = $payload['invoice'] ?? '';
    $courierStatus = $payload['status'] ?? '';
    
    if (empty($consignmentId) && empty($invoice)) return 'No identifier';
    
    // Verify bearer token if configured
    $webhookToken = getSetting('steadfast_webhook_token', '');
    if ($webhookToken) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader !== 'Bearer ' . $webhookToken) {
            http_response_code(401);
            return 'Unauthorized';
        }
    }
    
    $order = null;
    if ($invoice) {
        $order = $db->fetch("SELECT * FROM orders WHERE order_number = ?", [$invoice]);
    }
    if (!$order && $consignmentId) {
        $order = $db->fetch("SELECT * FROM orders WHERE courier_tracking_id = ?", [$consignmentId]);
    }
    if (!$order) return 'Order not found';
    
    return processStatusUpdate($db, $order, $courierStatus, $map, 'Steadfast', $payload);
}

// ── CarryBee Webhook Handler ──
function handleCarryBee($db, $payload, $map) {
    $trackingId = $payload['tracking_id'] ?? $payload['consignment_id'] ?? '';
    $invoice = $payload['invoice'] ?? $payload['order_id'] ?? '';
    $courierStatus = $payload['status'] ?? $payload['delivery_status'] ?? '';
    
    if (empty($trackingId) && empty($invoice)) return 'No identifier';
    
    $order = null;
    if ($invoice) {
        $order = $db->fetch("SELECT * FROM orders WHERE order_number = ?", [$invoice]);
    }
    if (!$order && $trackingId) {
        $order = $db->fetch("SELECT * FROM orders WHERE courier_tracking_id = ?", [$trackingId]);
    }
    if (!$order) return 'Order not found';
    
    return processStatusUpdate($db, $order, $courierStatus, $map, 'CarryBee', $payload);
}

// ── Core Status Update Logic ──
function processStatusUpdate($db, $order, $courierStatus, $map, $courierName, $payload) {
    $newStatus = $map[$courierStatus] ?? null;
    
    if (!$newStatus) {
        // Log but don't change status
        _webhookLog($courierName, "No mapping for status '{$courierStatus}' on order #{$order['order_number']}");
        // Still save the courier status for reference
        try {
            $db->update('orders', [
                'courier_status' => $courierStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$order['id']]);
        } catch (\Throwable $e) {}
        return "Status '{$courierStatus}' logged (no state change)";
    }
    
    // Prevent backward transitions for terminal states
    $currentStatus = $order['order_status'];
    $terminalStatuses = ['delivered', 'returned', 'cancelled'];
    if (in_array($currentStatus, $terminalStatuses)) {
        _webhookLog($courierName, "Blocked: #{$order['order_number']} already '{$currentStatus}', courier says '{$courierStatus}'");
        return "Order already in terminal state '{$currentStatus}'";
    }
    
    // Apply the status change
    $updateData = [
        'order_status' => $newStatus,
        'courier_status' => $courierStatus,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    // Set delivered_at timestamp
    if ($newStatus === 'delivered') {
        $updateData['delivered_at'] = date('Y-m-d H:i:s');
    }
    
    $db->update('orders', $updateData, 'id = ?', [$order['id']]);
    
    // Log status history
    try {
        $db->insert('order_status_history', [
            'order_id' => $order['id'],
            'status' => $newStatus,
            'note' => "{$courierName} webhook: {$courierStatus}",
        ]);
    } catch (\Throwable $e) {}
    
    // Trigger credit actions
    if ($newStatus === 'delivered') {
        try { awardOrderCredits($order['id']); } catch (\Throwable $e) {}
    }
    
    _webhookLog($courierName, "#{$order['order_number']}: {$currentStatus} → {$newStatus} (courier: {$courierStatus})");
    return "Updated #{$order['order_number']} to {$newStatus}";
}

// ── Logging ──
function _webhookLog($courier, $data) {
    try {
        $dir = dirname(__DIR__) . '/tmp';
        @mkdir($dir, 0755, true);
        $line = date('Y-m-d H:i:s') . " [{$courier}] {$data}\n";
        @file_put_contents($dir . '/webhook.log', $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {}
}
