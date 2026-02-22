<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Order Management';
require_once __DIR__ . '/../includes/auth.php';

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) return '';
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60).'m ago';
        if ($diff < 86400) return floor($diff/3600).'h ago';
        if ($diff < 604800) return floor($diff/86400).'d ago';
        return date('d M', strtotime($datetime));
    }
}

$db = Database::getInstance();

// ── POST Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $orderId = intval($_POST['order_id']);
        $newStatus = sanitize($_POST['status']);
        $db->update('orders', ['order_status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$orderId]);
        try { $db->insert('order_status_history', ['order_id' => $orderId, 'status' => $newStatus, 'changed_by' => getAdminId(), 'note' => sanitize($_POST['notes'] ?? '')]); } catch (Exception $e) {}
        if ($newStatus === 'delivered') { try { awardOrderCredits($orderId); } catch (\Throwable $e) {} }
        if (in_array($newStatus, ['cancelled', 'returned'])) { try { refundOrderCreditsOnCancel($orderId); } catch (\Throwable $e) {} }
        if ($newStatus === 'delivered') { try { $db->update('orders', ['delivered_at' => date('Y-m-d H:i:s')], 'id = ? AND delivered_at IS NULL', [$orderId]); } catch (\Throwable $e) {} }
        redirect(adminUrl('pages/order-management.php?' . http_build_query(array_diff_key($_GET, ['msg'=>''])) . '&msg=updated'));
    }
    
    if ($action === 'bulk_status') {
        $ids = $_POST['order_ids'] ?? []; $status = sanitize($_POST['bulk_status']);
        foreach ($ids as $id) {
            $db->update('orders', ['order_status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [intval($id)]);
            try { $db->insert('order_status_history', ['order_id' => intval($id), 'status' => $status, 'changed_by' => getAdminId()]); } catch (Exception $e) {}
            if ($status === 'delivered') { try { awardOrderCredits(intval($id)); } catch (\Throwable $e) {} try { $db->update('orders', ['delivered_at' => date('Y-m-d H:i:s')], 'id = ? AND delivered_at IS NULL', [intval($id)]); } catch (\Throwable $e) {} }
            if (in_array($status, ['cancelled', 'returned'])) { try { refundOrderCreditsOnCancel(intval($id)); } catch (\Throwable $e) {} }
        }
        redirect(adminUrl('pages/order-management.php?msg=bulk_updated'));
    }
    
    if ($action === 'bulk_courier') {
        @ob_clean(); header('Content-Type: application/json');
        $ids = $_POST['order_ids'] ?? []; $courierName = sanitize($_POST['courier_name'] ?? '');
        $results = ['success'=>0,'failed'=>0,'errors'=>[]];
        foreach ($ids as $oid) {
            $oid = intval($oid); $o = $db->fetch("SELECT * FROM orders WHERE id = ?", [$oid]);
            if (!$o) { $results['failed']++; continue; }
            if (strtolower($courierName) === 'pathao') {
                $pathaoFile = __DIR__ . '/../../api/pathao.php';
                if (!file_exists($pathaoFile)) { $results['failed']++; $results['errors'][] = "#{$o['order_number']}: Pathao API not configured"; continue; }
                try {
                    require_once $pathaoFile; $pathao = new PathaoAPI();
                    $resp = $pathao->createOrder(['store_id'=>$pathao->setting('store_id'),'merchant_order_id'=>$o['order_number'],'recipient_name'=>$o['customer_name'],'recipient_phone'=>$o['customer_phone'],'recipient_address'=>$o['customer_address'],'recipient_city'=>intval($o['pathao_city_id']??0),'recipient_zone'=>intval($o['pathao_zone_id']??0),'recipient_area'=>intval($o['pathao_area_id']??0),'delivery_type'=>48,'item_type'=>2,'item_quantity'=>1,'item_weight'=>0.5,'amount_to_collect'=>($o['payment_method']==='cod')?$o['total']:0,'item_description'=>'Order #'.$o['order_number'],'special_instruction'=>$o['notes']??'']);
                    if (!empty($resp['data']['consignment_id'])) {
                        $db->update('orders', ['courier_consignment_id'=>$resp['data']['consignment_id'],'courier_name'=>'Pathao','courier_tracking_id'=>$resp['data']['consignment_id'],'order_status'=>'shipped','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$oid]);
                        try { $db->insert('courier_uploads', ['order_id'=>$oid,'courier_provider'=>'pathao','consignment_id'=>$resp['data']['consignment_id'],'status'=>'uploaded','response_data'=>json_encode($resp),'created_by'=>getAdminId()]); } catch(Exception $e){}
                        $results['success']++;
                    } else { $results['failed']++; $results['errors'][]="#{$o['order_number']}: ".($resp['message']??'Failed'); }
                } catch(\Throwable $e) { $results['failed']++; $results['errors'][]="#{$o['order_number']}: ".$e->getMessage(); }
            } elseif (strtolower($courierName) === 'steadfast') {
                $sfFile = __DIR__ . '/../../api/steadfast.php';
                if (file_exists($sfFile)) {
                    try {
                        require_once $sfFile; $sf = new SteadfastAPI();
                        $result = $sf->uploadOrder($oid);
                        if ($result['success']) {
                            $results['success']++;
                        } else { $results['failed']++; $results['errors'][]="#{$o['order_number']}: ".($result['message']??'Failed'); }
                    } catch(\Throwable $e) { $results['failed']++; $results['errors'][]="#{$o['order_number']}: ".$e->getMessage(); }
                } else { $results['failed']++; $results['errors'][]="#{$o['order_number']}: Steadfast API not configured"; }
            } elseif (strtolower($courierName) === 'carrybee') {
                // CarryBee - manual upload (no API yet) - just mark shipped with courier name
                $db->update('orders', ['courier_name'=>'CarryBee','order_status'=>'shipped','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$oid]);
                $results['success']++;
            } else {
                $db->update('orders', ['courier_name'=>$courierName,'order_status'=>'shipped','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$oid]);
                $results['success']++;
            }
        }
        echo json_encode($results); exit;
    }
    
    if ($action === 'add_tag') {
        header('Content-Type: application/json');
        $orderId = intval($_POST['order_id']); $tag = trim(sanitize($_POST['tag']));
        if ($tag && $orderId) { try { $db->query("INSERT IGNORE INTO order_tags (order_id, tag_name, created_by) VALUES (?, ?, ?)", [$orderId, $tag, getAdminId()]); } catch(Exception $e) {} }
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'remove_tag') {
        header('Content-Type: application/json');
        try { $db->delete('order_tags', 'order_id = ? AND tag_name = ?', [intval($_POST['order_id']), trim(sanitize($_POST['tag']))]); } catch(Exception $e) {}
        echo json_encode(['success'=>true]); exit;
    }
}

// ── Auto-migrate: convert any remaining 'pending' orders to 'processing' ──
try { $db->query("UPDATE orders SET order_status = 'processing' WHERE order_status = 'pending'"); } catch(\Throwable $e) {}
// Expand ENUM to include all courier-driven statuses
try { $db->query("ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending','processing','confirmed','shipped','delivered','cancelled','returned','on_hold','no_response','good_but_no_response','advance_payment','incomplete','pending_return','pending_cancel','partial_delivered','lost') DEFAULT 'processing'"); } catch(\Throwable $e) {}
// Add courier_status column for raw courier API status
try { $db->query("ALTER TABLE orders ADD COLUMN courier_status VARCHAR(100) DEFAULT NULL AFTER courier_tracking_id"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN courier_tracking_message TEXT DEFAULT NULL AFTER courier_status"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN courier_delivery_charge DECIMAL(10,2) DEFAULT NULL AFTER courier_tracking_message"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN courier_cod_amount DECIMAL(10,2) DEFAULT NULL AFTER courier_delivery_charge"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN courier_uploaded_at DATETIME DEFAULT NULL AFTER courier_cod_amount"); } catch(\Throwable $e) {}
try { $db->query("CREATE TABLE IF NOT EXISTS courier_webhook_log (id INT AUTO_INCREMENT PRIMARY KEY, courier VARCHAR(50), payload TEXT, result VARCHAR(255), ip_address VARCHAR(45), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(courier), INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN pathao_consignment_id VARCHAR(100) DEFAULT NULL AFTER courier_consignment_id"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD INDEX idx_pathao_cid (pathao_consignment_id)"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN is_preorder TINYINT(1) DEFAULT 0 AFTER is_fake"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN advance_amount DECIMAL(12,2) DEFAULT 0 AFTER discount_amount"); } catch(\Throwable $e) {}
try { $db->query("ALTER TABLE orders ADD COLUMN advance_amount DECIMAL(12,2) DEFAULT 0 AFTER discount_amount"); } catch(\Throwable $e) {}

// ── Order Flow Definition ──
// Main flow: Processing → Confirmed → Shipped → Delivered
// Courier-driven (auto-updated by webhooks): pending_return, pending_cancel, partial_delivered, lost
// Manual side statuses: cancelled, returned, on_hold, no_response, good_but_no_response, advance_payment
$mainFlow = ['processing', 'confirmed', 'shipped', 'delivered'];
$courierStatuses = ['pending_return', 'pending_cancel', 'partial_delivered', 'lost'];
$sideStatuses = ['cancelled', 'returned', 'on_hold', 'no_response', 'good_but_no_response', 'advance_payment'];
$allStatuses = array_merge($mainFlow, $courierStatuses, $sideStatuses);

// Next logical action for each status
$nextAction = [
    'processing' => ['status' => 'confirmed', 'label' => 'Confirm', 'icon' => '✅', 'color' => 'blue'],
    'confirmed'  => ['status' => 'shipped',   'label' => 'Ship',    'icon' => '🚚', 'color' => 'purple'],
    'shipped'    => ['status' => 'delivered',  'label' => 'Deliver', 'icon' => '📦', 'color' => 'green'],
    'pending_return' => ['status' => 'returned', 'label' => 'Confirm Return', 'icon' => '↩', 'color' => 'orange'],
    'pending_cancel' => ['status' => 'cancelled', 'label' => 'Confirm Cancel', 'icon' => '✗', 'color' => 'red'],
    'partial_delivered' => ['status' => 'delivered', 'label' => 'Mark Delivered', 'icon' => '📦', 'color' => 'green'],
];

// Status counts
$statusCounts = [];
foreach ($allStatuses as $s) { try { $statusCounts[$s] = $db->count('orders', 'order_status = ?', [$s]); } catch(Exception $e) { $statusCounts[$s] = 0; } }
// Include legacy pending in processing count
try { $pendingCount = $db->count('orders', "order_status = 'pending'"); $statusCounts['processing'] += $pendingCount; } catch(\Throwable $e) {}
$totalOrders = array_sum($statusCounts);

// ── Filters ──
$status=$_GET['status']??''; $search=$_GET['search']??''; $dateFrom=$_GET['date_from']??''; $dateTo=$_GET['date_to']??'';
$channel=$_GET['channel']??''; $courier=$_GET['courier']??''; $assignedTo=$_GET['assigned']??'';
$page = max(1, intval($_GET['page'] ?? 1));

$where = '1=1'; $params = [];
if ($status) {
    if ($status === 'processing') {
        $where .= " AND o.order_status IN ('processing','pending')"; // Catch legacy pending too
    } else {
        $where .= " AND o.order_status = ?"; $params[] = $status;
    }
}
if ($search) { $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR CAST(o.id AS CHAR) = ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%",$search]); }
if ($dateFrom) { $where .= " AND DATE(o.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND DATE(o.created_at) <= ?"; $params[] = $dateTo; }
if ($channel) { $where .= " AND o.channel = ?"; $params[] = $channel; }
if ($courier) { $where .= " AND o.courier_name = ?"; $params[] = $courier; }
if ($assignedTo) { $where .= " AND o.assigned_to = ?"; $params[] = intval($assignedTo); }

$total = $db->fetch("SELECT COUNT(*) as cnt FROM orders o WHERE {$where}", $params)['cnt'];
$limit = ADMIN_ITEMS_PER_PAGE; $offset = ($page-1)*$limit; $totalPages = ceil($total/$limit);

// Column sorting
$sortCol = $_GET['sort'] ?? 'created_at';
$sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedSorts = ['created_at'=>'o.created_at','order_number'=>'o.order_number','total'=>'o.total','customer_name'=>'o.customer_name','channel'=>'o.channel','updated_at'=>'o.updated_at'];
$orderBy = ($allowedSorts[$sortCol] ?? 'o.created_at') . ' ' . $sortDir;

$orders = $db->fetchAll("SELECT o.*, au.full_name as assigned_name FROM orders o LEFT JOIN admin_users au ON au.id = o.assigned_to WHERE {$where} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}", $params);

// Pre-fetch customer success rates
$successRates=[]; $previousOrders=[];
$phones = array_unique(array_filter(array_column($orders, 'customer_phone')));
foreach ($phones as $phone) {
    $pl = '%'.substr(preg_replace('/[^0-9]/','',$phone),-10).'%';
    try {
        $sr = $db->fetch("SELECT COUNT(*) as total, SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled, SUM(total) as total_spent FROM orders WHERE customer_phone LIKE ?", [$pl]);
        $t=intval($sr['total']); $d=intval($sr['delivered']); $c=intval($sr['cancelled']);
        $rate=$t>0?round(($d/$t)*100):0;
        $successRates[$phone]=['total'=>$t,'delivered'=>$d,'cancelled'=>$c,'rate'=>$rate,'total_spent'=>$sr['total_spent']??0];
        $previousOrders[$phone] = $db->fetchAll("SELECT id, order_number, order_status, total, created_at FROM orders WHERE customer_phone LIKE ? ORDER BY created_at DESC LIMIT 5", [$pl]);
    } catch(Exception $e) { $successRates[$phone]=['total'=>0,'delivered'=>0,'cancelled'=>0,'rate'=>0,'total_spent'=>0]; $previousOrders[$phone]=[]; }
}

// Pre-fetch items + tags
$orderIds = array_column($orders, 'id'); $orderItems=[]; $orderTags=[];
if (!empty($orderIds)) {
    $ph = implode(',', array_fill(0, count($orderIds), '?'));
    $items = $db->fetchAll("SELECT oi.order_id, oi.product_name, oi.quantity, oi.price, oi.variant_name, p.featured_image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ({$ph})", $orderIds);
    foreach ($items as $item) { $orderItems[$item['order_id']][] = $item; }
    try { $tags = $db->fetchAll("SELECT order_id, tag_name FROM order_tags WHERE order_id IN ({$ph})", $orderIds);
        foreach ($tags as $t) { $orderTags[$t['order_id']][] = $t; }
    } catch(Exception $e) {}
}

$defaultCourier = getSetting('default_courier', 'pathao');
$adminUsers = $db->fetchAll("SELECT id, full_name FROM admin_users WHERE is_active = 1 ORDER BY full_name");
$incompleteCount = 0;
try { $incompleteCount = $db->fetch("SELECT COUNT(*) as cnt FROM incomplete_orders WHERE recovered = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")['cnt']; } catch(Exception $e){}

// Today's summary
$todaySummary = $db->fetch("SELECT COUNT(*) as total, SUM(total) as revenue, SUM(CASE WHEN order_status IN ('processing','pending') THEN 1 ELSE 0 END) as processing, SUM(CASE WHEN order_status='confirmed' THEN 1 ELSE 0 END) as confirmed, SUM(CASE WHEN order_status='shipped' THEN 1 ELSE 0 END) as shipped, SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled, SUM(CASE WHEN order_status='pending_return' THEN 1 ELSE 0 END) as pending_return, SUM(CASE WHEN order_status='pending_cancel' THEN 1 ELSE 0 END) as pending_cancel FROM orders WHERE DATE(created_at) = CURDATE()");

// Tab config
$tabConfig = [
    'processing' => ['icon'=>'⚙','color'=>'yellow','label'=>'PROCESSING'],
    'confirmed'  => ['icon'=>'✅','color'=>'blue','label'=>'CONFIRMED'],
    'shipped'    => ['icon'=>'🚚','color'=>'purple','label'=>'SHIPPED'],
    'delivered'  => ['icon'=>'📦','color'=>'green','label'=>'DELIVERED'],
    'pending_return'=>['icon'=>'🔄','color'=>'amber','label'=>'PENDING RETURN'],
    'pending_cancel'=>['icon'=>'⏳','color'=>'pink','label'=>'PENDING CANCEL'],
    'partial_delivered'=>['icon'=>'📦½','color'=>'cyan','label'=>'PARTIAL'],
    'lost'       => ['icon'=>'❌','color'=>'stone','label'=>'LOST'],
    'cancelled'  => ['icon'=>'✗','color'=>'red','label'=>'CANCELLED'],
    'returned'   => ['icon'=>'↩','color'=>'orange','label'=>'RETURNED'],
    'on_hold'    => ['icon'=>'⏸','color'=>'gray','label'=>'ON HOLD'],
    'no_response'=> ['icon'=>'📵','color'=>'rose','label'=>'NO RESPONSE'],
    'good_but_no_response'=>['icon'=>'📱','color'=>'teal','label'=>'GOOD NO RESP'],
    'advance_payment'=>['icon'=>'💰','color'=>'emerald','label'=>'ADVANCE'],
];

require_once __DIR__ . '/../includes/header.php';

// Sort link helper
function sortUrl($col) {
    global $sortCol, $sortDir;
    $p = $_GET;
    $p['sort'] = $col;
    $p['dir'] = ($sortCol === $col && $sortDir === 'ASC') ? 'desc' : 'asc';
    return '?' . http_build_query($p);
}
function sortIcon($col) {
    global $sortCol, $sortDir;
    if ($sortCol !== $col) return '<span class="text-gray-300 ml-0.5">↕</span>';
    return '<span class="text-blue-500 ml-0.5">' . ($sortDir === 'ASC' ? '↑' : '↓') . '</span>';
}
?>
<style>
.om-table th,.om-table td{padding:6px 10px;white-space:nowrap;vertical-align:top;border-bottom:1px solid #f0f0f0;font-size:12px}
.om-table th{background:#f8f9fb;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.3px;font-size:10px;position:sticky;top:0;z-index:2;border-bottom:2px solid #e2e8f0;user-select:none}
.om-table th a{color:inherit;text-decoration:none}
.om-table tr:hover{background:#f8fafc}
.om-table .cust-name{font-weight:600;color:#1e293b;font-size:12px}
.om-table .cust-phone{font-size:11px;color:#64748b;font-family:monospace}
.om-table .cust-addr{font-size:10px;color:#94a3b8}
.om-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
.rate-badge{display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:4px}
.tag-badge{display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:12px;white-space:nowrap}
.dot-menu{width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;cursor:pointer;color:#94a3b8;font-size:14px;line-height:1}
.dot-menu:hover{background:#f1f5f9;color:#475569}
.prod-thumb{width:28px;height:28px;border-radius:4px;object-fit:cover;border:1px solid #e5e7eb;flex-shrink:0}
.status-dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:3px}
</style>

<?php if (isset($_GET['msg'])): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">✓ <?= $_GET['msg'] === 'updated' ? 'Status updated.' : ($_GET['msg'] === 'bulk_updated' ? 'Bulk update completed.' : 'Action completed.') ?></div><?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-3">
    <div class="bg-white rounded-lg border p-2.5 text-center">
        <p class="text-xl font-bold text-gray-800"><?= intval($todaySummary['total'] ?? 0) ?></p>
        <p class="text-[9px] text-gray-400 uppercase tracking-wider">Today</p>
    </div>
    <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-2.5 text-center cursor-pointer hover:shadow-sm" onclick="location='?status=processing'">
        <p class="text-xl font-bold text-yellow-600"><?= $statusCounts['processing'] ?></p>
        <p class="text-[9px] text-yellow-600 uppercase tracking-wider">Processing</p>
    </div>
    <div class="bg-blue-50 rounded-lg border border-blue-200 p-2.5 text-center cursor-pointer hover:shadow-sm" onclick="location='?status=confirmed'">
        <p class="text-xl font-bold text-blue-600"><?= $statusCounts['confirmed'] ?></p>
        <p class="text-[9px] text-blue-600 uppercase tracking-wider">Confirmed</p>
    </div>
    <?php if (($statusCounts['ready_to_ship'] ?? 0) > 0): ?>
    <div class="bg-violet-50 rounded-lg border border-violet-200 p-2.5 text-center cursor-pointer hover:shadow-sm" onclick="location='?status=ready_to_ship'">
        <p class="text-xl font-bold text-violet-600"><?= $statusCounts['ready_to_ship'] ?? 0 ?></p>
        <p class="text-[9px] text-violet-600 uppercase tracking-wider">RTS</p>
    </div>
    <?php endif; ?>
    <div class="bg-purple-50 rounded-lg border border-purple-200 p-2.5 text-center cursor-pointer hover:shadow-sm" onclick="location='?status=shipped'">
        <p class="text-xl font-bold text-purple-600"><?= $statusCounts['shipped'] ?></p>
        <p class="text-[9px] text-purple-600 uppercase tracking-wider">Shipped</p>
    </div>
    <div class="bg-green-50 rounded-lg border border-green-200 p-2.5 text-center cursor-pointer hover:shadow-sm" onclick="location='?status=delivered'">
        <p class="text-xl font-bold text-green-600"><?= $statusCounts['delivered'] ?></p>
        <p class="text-[9px] text-green-600 uppercase tracking-wider">Delivered</p>
    </div>
    <div class="bg-white rounded-lg border p-2.5 text-center">
        <p class="text-xl font-bold text-gray-800">৳<?= number_format($todaySummary['revenue'] ?? 0) ?></p>
        <p class="text-[9px] text-gray-400 uppercase tracking-wider">Revenue</p>
    </div>
</div>

<!-- Status Tabs -->
<div class="bg-white rounded-lg border mb-3 overflow-hidden">
    <div class="overflow-x-auto">
        <div class="flex items-center min-w-max border-b">
            <a href="<?= adminUrl('pages/order-management.php') ?>" class="px-4 py-2.5 text-xs font-medium border-b-2 transition <?= !$status ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                ALL <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= !$status ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-500' ?>"><?= number_format($totalOrders) ?></span>
            </a>
            <?php 
            $allTabStatuses = ['processing', 'confirmed', 'ready_to_ship', 'shipped', 'delivered', 'pending_return', 'returned', 'partial_delivered', 'cancelled', 'pending_cancel', 'on_hold', 'no_response', 'good_but_no_response', 'advance_payment', 'lost'];
            foreach ($allTabStatuses as $s):
                $tc = $tabConfig[$s] ?? ['icon'=>'','color'=>'gray','label'=>ucwords(str_replace('_',' ',$s))];
                $cnt = $statusCounts[$s] ?? 0;
                if ($cnt === 0 && !in_array($s, ['processing','confirmed','ready_to_ship','shipped','delivered','cancelled','returned'])) continue;
                $isActive = $status === $s;
            ?>
            <a href="?status=<?= $s ?>" class="px-3 py-2.5 text-xs font-medium whitespace-nowrap border-b-2 transition <?= $isActive ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                <?= $tc['label'] ?> <span class="px-1.5 py-0.5 rounded text-[10px] <?= $isActive ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-500' ?>"><?= number_format($cnt) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Search & Toolbar -->
<div class="bg-white rounded-lg border p-2.5 mb-3 flex flex-wrap items-center gap-2">
    <form method="GET" class="flex flex-wrap items-center gap-2 flex-1">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search order, name, phone..." class="w-full pl-8 pr-3 py-1.5 border rounded text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="button" onclick="document.getElementById('advFilters').classList.toggle('hidden')" class="border text-gray-500 px-2.5 py-1.5 rounded text-xs hover:bg-gray-50">Filters</button>
    </form>
    <a href="<?= adminUrl('pages/order-add.php') ?>" class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-medium hover:bg-blue-700">+ New Order</a>
    <button type="button" onclick="fcCheck('')" class="border border-blue-200 text-blue-600 px-2.5 py-1.5 rounded text-xs hover:bg-blue-50 font-medium">🔍 Check</button>
    <div class="relative" id="actionsWrap">
        <button type="button" onclick="document.getElementById('actionsMenu').classList.toggle('hidden')" class="border text-gray-500 px-2.5 py-1.5 rounded text-xs hover:bg-gray-50">⋮ Actions</button>
        <div id="actionsMenu" class="hidden absolute right-0 top-full mt-1 w-56 bg-white rounded-lg shadow-xl border z-50 py-1 max-h-[70vh] overflow-y-auto">
            <div class="px-3 py-1.5 flex items-center justify-between"><span id="selC" class="text-[10px] text-gray-400">0 selected</span><button type="button" onclick="document.getElementById('selectAll').checked=true;toggleAll(document.getElementById('selectAll'))" class="text-[10px] text-blue-600">Select All</button></div><hr class="my-0.5">
            <p class="px-3 py-1 text-[10px] font-bold text-gray-400 uppercase">Print</p>
            <button type="button" onclick="bPrint('standard')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">📄 Invoice</button>
            <button type="button" onclick="bPrint('sticker')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">🏷 Sticker</button>
            <button type="button" onclick="bPrint('picking')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">📦 Picking List</button>
            <button type="button" onclick="bPrint('compact')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">📋 Sheet</button>
            <hr class="my-0.5"><p class="px-3 py-1 text-[10px] font-bold text-gray-400 uppercase">Status</p>
            <button type="button" onclick="bStatus('confirmed')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">✅ Confirm</button>
            <button type="button" onclick="bStatus('ready_to_ship')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">📦 Ready to Ship</button>
            <button type="button" onclick="bStatus('shipped')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">🚚 Ship</button>
            <button type="button" onclick="bStatus('delivered')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50">📦 Deliver</button>
            <button type="button" onclick="bStatus('returned')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 text-orange-600">↩ Return</button>
            <button type="button" onclick="bStatus('cancelled')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 text-red-600">✗ Cancel</button>
            <hr class="my-0.5"><p class="px-3 py-1 text-[10px] font-bold text-gray-400 uppercase">Courier</p>
            <button type="button" onclick="bCourier('Pathao')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50"><span class="inline-block w-3.5 h-3.5 bg-red-500 text-white rounded text-[9px] text-center mr-1 font-bold leading-[14px]">P</span>Pathao</button>
            <button type="button" onclick="bCourier('Steadfast')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50"><span class="inline-block w-3.5 h-3.5 bg-blue-500 text-white rounded text-[9px] text-center mr-1 font-bold leading-[14px]">S</span>Steadfast</button>
            <button type="button" onclick="bCourier('RedX')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50"><span class="inline-block w-3.5 h-3.5 bg-orange-500 text-white rounded text-[9px] text-center mr-1 font-bold leading-[14px]">R</span>RedX</button>
            <hr class="my-0.5">
            <button type="button" onclick="syncCourier()" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 text-indigo-600">🔄 Sync Courier</button>
            <a href="<?= SITE_URL ?>/api/export.php?type=orders<?= $status?'&status='.$status:'' ?>" class="block px-3 py-1.5 text-xs hover:bg-gray-50">📊 Export Excel</a>
        </div>
    </div>
</div>

<!-- Advanced Filters (hidden) -->
<div id="advFilters" class="<?= ($dateFrom||$dateTo||$channel||$assignedTo)?'':'hidden' ?> bg-white rounded-lg border p-3 mb-3">
    <form method="GET" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="px-2.5 py-1.5 border rounded text-xs">
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="px-2.5 py-1.5 border rounded text-xs">
        <select name="channel" class="px-2.5 py-1.5 border rounded text-xs"><option value="">All Channels</option><?php foreach(['website','facebook','phone','whatsapp','instagram','landing_page'] as $ch): ?><option value="<?= $ch ?>" <?= $channel===$ch?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$ch)) ?></option><?php endforeach; ?></select>
        <select name="assigned" class="px-2.5 py-1.5 border rounded text-xs"><option value="">All Staff</option><?php foreach($adminUsers as $au): ?><option value="<?= $au['id'] ?>" <?= $assignedTo==$au['id']?'selected':'' ?>><?= e($au['full_name']) ?></option><?php endforeach; ?></select>
        <div class="flex gap-2"><button class="flex-1 bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-medium">Apply</button><a href="<?= adminUrl('pages/order-management.php') ?>" class="bg-gray-100 text-gray-500 px-3 py-1.5 rounded text-xs">✕</a></div>
    </form>
</div>

<!-- Courier Upload Progress -->
<div id="cProg" class="hidden mb-3 bg-white rounded-lg border p-3">
    <div class="flex items-center justify-between mb-1.5"><span class="text-xs font-medium" id="cProgL">Uploading...</span><span id="cProgC" class="text-[10px] text-gray-400"></span></div>
    <div class="w-full bg-gray-200 rounded-full h-1.5"><div id="cProgB" class="bg-blue-600 h-1.5 rounded-full transition-all" style="width:0%"></div></div>
    <div id="cErr" class="mt-1.5 text-[10px] text-red-600 hidden"></div>
</div>

<!-- Orders Table -->
<form method="POST" id="bulkForm"><input type="hidden" name="action" value="bulk_status">
<div class="om-wrap">
    <table class="om-table w-full">
        <thead>
            <tr>
                <th style="width:30px"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                <th><a href="<?= sortUrl('created_at') ?>">Date <?= sortIcon('created_at') ?></a></th>
                <th><a href="<?= sortUrl('order_number') ?>">Invoice <?= sortIcon('order_number') ?></a></th>
                <th>Customer</th>
                <th>Note</th>
                <th>Products</th>
                <th>Tags</th>
                <th><a href="<?= sortUrl('total') ?>">Total <?= sortIcon('total') ?></a></th>
                <th>Upload</th>
                <th>User</th>
                <th><a href="<?= sortUrl('channel') ?>">Source <?= sortIcon('channel') ?></a></th>
                <th>Shipping Note</th>
                <th style="width:40px">Actions</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($orders as $order):
    $sr=$successRates[$order['customer_phone']]??['total'=>0,'delivered'=>0,'rate'=>0,'cancelled'=>0,'total_spent'=>0];
    $prevO=$previousOrders[$order['customer_phone']]??[];
    $oItems=$orderItems[$order['id']]??[]; $tags=$orderTags[$order['id']]??[];
    $rC=$sr['rate']>=80?'text-green-600':($sr['rate']>=50?'text-yellow-600':'text-red-500');
    $ph=preg_replace('/[^0-9]/','',$order['customer_phone']);
    $oStatus = $order['order_status'] === 'pending' ? 'processing' : $order['order_status'];
    $nxt = $nextAction[$oStatus] ?? null;
    $creditUsed = floatval($order['store_credit_used'] ?? 0);
    
    // Status color mapping
    $statusColors = [
        'processing'=>'bg-yellow-100 text-yellow-700','confirmed'=>'bg-blue-100 text-blue-700',
        'ready_to_ship'=>'bg-violet-100 text-violet-700','shipped'=>'bg-purple-100 text-purple-700',
        'delivered'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700',
        'returned'=>'bg-orange-100 text-orange-700','pending_return'=>'bg-amber-100 text-amber-700',
        'pending_cancel'=>'bg-pink-100 text-pink-700','partial_delivered'=>'bg-cyan-100 text-cyan-700',
        'on_hold'=>'bg-gray-100 text-gray-700','no_response'=>'bg-rose-100 text-rose-700',
        'good_but_no_response'=>'bg-teal-100 text-teal-700','advance_payment'=>'bg-emerald-100 text-emerald-700',
    ];
    $statusDots = [
        'processing'=>'#eab308','confirmed'=>'#3b82f6','ready_to_ship'=>'#8b5cf6',
        'shipped'=>'#9333ea','delivered'=>'#22c55e','cancelled'=>'#ef4444',
        'returned'=>'#f97316','pending_return'=>'#f59e0b','on_hold'=>'#6b7280',
    ];
    $sDot = $statusDots[$oStatus] ?? '#94a3b8';
    $sBadge = $statusColors[$oStatus] ?? 'bg-gray-100 text-gray-600';
    
    // Courier tracking
    $__cn = strtolower($order['courier_name'] ?? ($order['shipping_method'] ?? ''));
    $__cid = $order['courier_consignment_id'] ?? ($order['pathao_consignment_id'] ?? '');
    $__tid = $order['courier_tracking_id'] ?? $__cid;
    $__link = '';
    if ($__cid) {
        if (strpos($__cn, 'steadfast') !== false) $__link = 'https://steadfast.com.bd/user/consignment/' . urlencode($__cid);
        elseif (strpos($__cn, 'pathao') !== false) $__link = 'https://merchant.pathao.com/courier/orders/' . urlencode($__cid);
        elseif (strpos($__cn, 'redx') !== false) $__link = 'https://redx.com.bd/track-parcel/?trackingId=' . urlencode($__tid);
    }
    
    // Source/channel display
    $channelMap = ['website'=>'WEB','facebook'=>'FACEBOOK','phone'=>'PHONE','whatsapp'=>'WHATSAPP','instagram'=>'INSTAGRAM','landing_page'=>'LP'];
    $srcLabel = $channelMap[$order['channel'] ?? ''] ?? strtoupper($order['channel'] ?? '—');
?>
<tr>
    <!-- Checkbox -->
    <td><input type="checkbox" name="order_ids[]" value="<?= $order['id'] ?>" class="order-check" onchange="updateBulk()"></td>
    
    <!-- Date -->
    <td>
        <div style="font-size:11px;font-weight:500;color:#334155"><?= date('d/m/Y,', strtotime($order['created_at'])) ?></div>
        <div style="font-size:10px;color:#64748b"><?= date('h:i a', strtotime($order['created_at'])) ?></div>
        <div style="font-size:9px;color:#94a3b8">Updated <?= timeAgo($order['updated_at']?:$order['created_at']) ?></div>
    </td>
    
    <!-- Invoice -->
    <td>
        <div style="display:flex;align-items:center;gap:4px">
            <a href="<?= adminUrl('pages/order-view.php?id='.$order['id']) ?>" style="font-weight:700;color:#0f172a;font-size:12px;text-decoration:none" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'"><?= e($order['order_number']) ?></a>
            <span class="dot-menu" onclick="toggleRowMenu(this,<?= $order['id'] ?>)">⋮</span>
        </div>
    </td>
    
    <!-- Customer -->
    <td style="min-width:160px">
        <div style="display:flex;align-items:center;gap:5px">
            <span style="color:#94a3b8;font-size:13px">👤</span>
            <span class="cust-name"><?= e($order['customer_name']) ?></span>
        </div>
        <div style="margin-top:1px;display:flex;align-items:center;gap:3px">
            <span class="cust-phone"><?= e($order['customer_phone']) ?></span>
            <span class="rate-badge" style="background:<?= $sr['rate']>=70?'#dcfce7':($sr['rate']>=40?'#fef9c3':'#fee2e2') ?>;color:<?= $sr['rate']>=70?'#166534':($sr['rate']>=40?'#854d0e':'#991b1b') ?>"><?= $sr['rate'] ?>%</span>
        </div>
        <div class="cust-addr">📍 <?= e(mb_strimwidth($order['customer_address'],0,40,'...')) ?></div>
    </td>
    
    <!-- Note -->
    <td style="max-width:180px;white-space:normal">
        <?php if(!empty($order['notes'])): ?>
        <div style="font-size:11px;color:#475569;line-height:1.4"><?= e(mb_strimwidth($order['notes'],0,120,'...')) ?></div>
        <?php else: ?>
        <span style="color:#d1d5db">—</span>
        <?php endif; ?>
    </td>
    
    <!-- Products -->
    <td style="min-width:160px">
        <div style="display:inline-flex;align-items:center;gap:3px;margin-bottom:3px">
            <span class="status-dot" style="background:<?= $sDot ?>"></span>
            <span class="tag-badge" style="background:<?= $sDot ?>22;color:<?= $sDot ?>;font-size:9px;padding:1px 6px"><?= strtoupper(str_replace('_',' ',$oStatus)) ?></span>
        </div>
        <?php foreach(array_slice($oItems,0,2) as $item): ?>
        <div style="display:flex;align-items:center;gap:5px;margin-top:2px">
            <img src="<?= !empty($item['featured_image'])?imgSrc('products',$item['featured_image']):'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 40 40%22><rect fill=%22%23f1f5f9%22 width=%2240%22 height=%2240%22/><text y=%22.75em%22 x=%22.15em%22 font-size=%2224%22>📦</text></svg>' ?>" class="prod-thumb" loading="lazy">
            <div>
                <div style="font-size:11px;color:#334155;font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis"><?= e($item['product_name']) ?></div>
                <div style="font-size:9px;color:#94a3b8"><?php if(!empty($item['variant_name'])): ?><?= e($item['variant_name']) ?> · <?php endif; ?>Qty: <?= intval($item['quantity']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(count($oItems)>2): ?><div style="font-size:9px;color:#94a3b8;margin-top:2px">+<?= count($oItems)-2 ?> more</div><?php endif; ?>
    </td>
    
    <!-- Tags -->
    <td>
        <?php
        // Show order tags as colored badges
        $tagColors = ['REPEAT'=>'bg-orange-100 text-orange-700','URGENT'=>'bg-red-100 text-red-700','VIP'=>'bg-purple-100 text-purple-700','GIFT'=>'bg-pink-100 text-pink-700','COD VERIFIED'=>'bg-green-100 text-green-700','ADVANCE PAID'=>'bg-emerald-100 text-emerald-700','FOLLOW UP'=>'bg-blue-100 text-blue-700'];
        if ($sr['total'] > 1): ?>
            <span class="tag-badge" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;margin-bottom:2px">Repeat Customer</span><br>
        <?php endif;
        foreach(array_slice($tags,0,3) as $tag):
            $tc2 = $tagColors[$tag['tag_name']] ?? 'bg-gray-100 text-gray-600';
        ?>
            <span class="tag-badge <?= $tc2 ?>" style="margin-bottom:2px"><?= e($tag['tag_name']) ?></span><br>
        <?php endforeach; ?>
        <button onclick="addTag(<?= $order['id'] ?>)" style="font-size:9px;color:#93c5fd;border:none;background:none;cursor:pointer;padding:0">+tag</button>
    </td>
    
    <!-- Total -->
    <td style="text-align:right">
        <div style="font-weight:700;color:#0f172a;font-size:12px"><?= number_format($order['total'],2) ?></div>
        <?php if ($creditUsed > 0): ?><div style="font-size:9px;color:#ca8a04">-৳<?= number_format($creditUsed) ?></div><?php endif; ?>
    </td>
    
    <!-- Upload (Courier Tracking) -->
    <td>
        <?php if($__link && $__cid): ?>
            <a href="<?= $__link ?>" target="_blank" style="font-size:11px;color:#2563eb;text-decoration:none;font-family:monospace;font-weight:500" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= e($__tid) ?></a>
            <?php if(!empty($order['courier_status'])):
                $__cs = $order['courier_status'];
                $__csc = '#94a3b8';
                if (in_array($__cs, ['delivered','delivered_approval_pending'])) $__csc = '#22c55e';
                elseif (in_array($__cs, ['cancelled','cancelled_approval_pending'])) $__csc = '#ef4444';
                elseif ($__cs === 'hold') $__csc = '#eab308';
            ?>
                <div style="font-size:9px;color:<?= $__csc ?>;margin-top:1px"><?= e($__cs) ?></div>
            <?php endif; ?>
        <?php elseif($__cid): ?>
            <span style="font-size:11px;color:#64748b;font-family:monospace"><?= e($__cid) ?></span>
        <?php else: ?>
            <span style="color:#d1d5db">—</span>
        <?php endif; ?>
    </td>
    
    <!-- User -->
    <td>
        <span style="font-size:11px;color:#475569"><?= e($order['assigned_name'] ?? '—') ?></span>
    </td>
    
    <!-- Source -->
    <td>
        <span style="font-size:10px;font-weight:600;color:#64748b"><?= $srcLabel ?></span>
    </td>
    
    <!-- Shipping Note -->
    <td style="max-width:160px;white-space:normal">
        <?php
        $shipNote = '';
        if (!empty($order['courier_name'])) $shipNote .= $order['courier_name'];
        if (!empty($order['shipping_notes'])) $shipNote = $order['shipping_notes'];
        // Use notes if contains shipping keywords
        if (!$shipNote && !empty($order['notes'])) {
            $n = $order['notes'];
            if (preg_match('/(exchange|delivery|urgent|fragile|call before)/i', $n)) $shipNote = $n;
        }
        if ($shipNote): ?>
            <div style="font-size:10px;color:#475569;line-height:1.3"><?= e(mb_strimwidth($shipNote,0,80,'...')) ?></div>
        <?php else: ?>
            <span style="color:#d1d5db">—</span>
        <?php endif; ?>
    </td>
    
    <!-- Actions -->
    <td style="text-align:center">
        <span class="dot-menu" onclick="toggleRowMenu(this,<?= $order['id'] ?>)">⋮</span>
    </td>
</tr>
<?php endforeach; ?>
<?php if(empty($orders)): ?>
<tr><td colspan="13" style="text-align:center;padding:40px 20px;color:#94a3b8"><div style="font-size:28px;margin-bottom:8px">📦</div>No orders found</td></tr>
<?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-3 px-1">
    <p class="text-xs text-gray-500">Page <strong><?= $page ?></strong> of <?= $totalPages ?> · <?= number_format($total) ?> orders</p>
    <div class="flex gap-1">
        <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="px-2.5 py-1 text-xs rounded bg-white border hover:bg-gray-50">←</a><?php endif; ?>
        <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="px-2.5 py-1 text-xs rounded <?= $i===$page?'bg-blue-600 text-white border-blue-600':'bg-white border hover:bg-gray-50' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$totalPages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="px-2.5 py-1 text-xs rounded bg-white border hover:bg-gray-50">→</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>
</form>

<!-- Row Action Menu (reusable popup) -->
<div id="rowMenu" class="hidden fixed z-50 w-44 bg-white rounded-lg shadow-xl border py-1" style="font-size:12px">
    <a id="rmOpen" href="#" class="block px-3 py-1.5 hover:bg-gray-50">📋 Open Order</a>
    <a id="rmPrint" href="#" target="_blank" class="block px-3 py-1.5 hover:bg-gray-50">🖨 Print Invoice</a>
    <hr class="my-0.5">
    <button id="rmConfirm" class="w-full text-left px-3 py-1.5 hover:bg-gray-50 text-blue-600" type="button">✅ Confirm</button>
    <button id="rmShip" class="w-full text-left px-3 py-1.5 hover:bg-gray-50 text-purple-600" type="button">🚚 Ship</button>
    <button id="rmDeliver" class="w-full text-left px-3 py-1.5 hover:bg-gray-50 text-green-600" type="button">📦 Deliver</button>
    <hr class="my-0.5">
    <button id="rmCancel" class="w-full text-left px-3 py-1.5 hover:bg-gray-50 text-red-600" type="button">✗ Cancel</button>
</div>

<!-- Tag Modal -->
<div id="tagModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center">
    <div class="bg-white rounded-lg p-4 w-72 shadow-2xl">
        <h3 class="font-bold text-gray-800 text-sm mb-2">Add Tag</h3><input type="hidden" id="tagOId">
        <div class="flex flex-wrap gap-1.5 mb-2"><?php foreach(['REPEAT','URGENT','VIP','GIFT','FOLLOW UP','COD VERIFIED','ADVANCE PAID'] as $p): ?><button onclick="subTag('<?= $p ?>')" class="text-[10px] bg-gray-100 hover:bg-blue-100 px-2 py-1 rounded"><?= $p ?></button><?php endforeach; ?></div>
        <div class="flex gap-1.5"><input type="text" id="tagIn" placeholder="Custom..." class="flex-1 px-2.5 py-1.5 border rounded text-xs" onkeydown="if(event.key==='Enter')subTag(this.value)"><button onclick="subTag(document.getElementById('tagIn').value)" class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs">Add</button></div>
        <button onclick="document.getElementById('tagModal').classList.add('hidden')" class="mt-1.5 text-[10px] text-gray-400 w-full text-center">Cancel</button>
    </div>
</div>

<script>
const defC='<?= e($defaultCourier) ?>';
function toggleAll(el){document.querySelectorAll('.order-check').forEach(c=>c.checked=el.checked);updateBulk()}
function updateBulk(){const n=document.querySelectorAll('.order-check:checked').length;document.getElementById('selC').textContent=n+' selected'}
function getIds(){return Array.from(document.querySelectorAll('.order-check:checked')).map(c=>c.value)}

function bPrint(t){const ids=getIds();if(!ids.length){alert('Select orders');return}window.open('<?= adminUrl('pages/order-print.php') ?>?ids='+ids.join(',')+'&template='+t,'_blank');document.getElementById('actionsMenu').classList.add('hidden')}
function bStatus(s){const ids=getIds();if(!ids.length){alert('Select orders');return}if(!confirm('Change '+ids.length+' orders to "'+s+'"?'))return;const f=document.getElementById('bulkForm');let h=f.querySelector('[name=bulk_status]');if(!h){h=document.createElement('input');h.type='hidden';h.name='bulk_status';f.appendChild(h)}h.value=s;f.submit()}
function bCourier(c){const ids=getIds();if(!ids.length){alert('Select orders');return}if(!confirm('Upload '+ids.length+' to '+c+'?'))return;document.getElementById('actionsMenu').classList.add('hidden');doCourier(ids,c)}

function doCourier(ids,c){
    const p=document.getElementById('cProg');p.classList.remove('hidden');
    document.getElementById('cProgL').textContent='Uploading '+ids.length+' to '+c+'...';
    document.getElementById('cProgB').style.width='30%';
    const fd=new FormData();fd.append('action','bulk_courier');fd.append('courier_name',c);ids.forEach(i=>fd.append('order_ids[]',i));
    fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.text()).then(txt=>{
        document.getElementById('cProgB').style.width='100%';
        try{const d=JSON.parse(txt);document.getElementById('cProgL').textContent='✓ '+d.success+' uploaded, '+d.failed+' failed';
            if(d.errors?.length){const e=document.getElementById('cErr');e.classList.remove('hidden');e.innerHTML=d.errors.join('<br>')}
            setTimeout(()=>location.reload(),2000);
        }catch(e){document.getElementById('cProgL').textContent='Error';document.getElementById('cErr').classList.remove('hidden');document.getElementById('cErr').textContent=txt.substring(0,200)}
    }).catch(e=>{document.getElementById('cProgL').textContent='Error: '+e.message});
}

function addTag(id){document.getElementById('tagOId').value=id;document.getElementById('tagIn').value='';document.getElementById('tagModal').classList.remove('hidden');document.getElementById('tagIn').focus()}
function subTag(t){t=t.trim();if(!t)return;const id=document.getElementById('tagOId').value;fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=add_tag&order_id='+id+'&tag='+encodeURIComponent(t)}).then(()=>{document.getElementById('tagModal').classList.add('hidden');location.reload()})}

// Row context menu
function toggleRowMenu(el, orderId) {
    const rm = document.getElementById('rowMenu');
    if (rm._open === orderId) { rm.classList.add('hidden'); rm._open = null; return; }
    const r = el.getBoundingClientRect();
    rm.style.top = (r.bottom + window.scrollY + 2) + 'px';
    rm.style.left = Math.min(r.left, window.innerWidth - 190) + 'px';
    rm.classList.remove('hidden');
    rm._open = orderId;
    document.getElementById('rmOpen').href = '<?= adminUrl('pages/order-view.php?id=') ?>' + orderId;
    document.getElementById('rmPrint').href = '<?= adminUrl('pages/order-print.php?ids=') ?>' + orderId + '&template=standard';
    ['Confirm','Ship','Deliver','Cancel'].forEach(a => {
        const btn = document.getElementById('rm'+a);
        btn.onclick = () => { if(confirm(a+' this order?')){
            const fd=new FormData();fd.append('action','update_status');fd.append('order_id',orderId);fd.append('status',{Confirm:'confirmed',Ship:'shipped',Deliver:'delivered',Cancel:'cancelled'}[a]);
            fetch(location.pathname,{method:'POST',body:fd}).then(()=>location.reload());
        }};
    });
}
document.addEventListener('click', e => {
    const rm = document.getElementById('rowMenu');
    if (rm && !rm.contains(e.target) && !e.target.classList.contains('dot-menu')) { rm.classList.add('hidden'); rm._open = null; }
    const w = document.getElementById('actionsWrap');
    if (w && !w.contains(e.target)) document.getElementById('actionsMenu').classList.add('hidden');
});

function syncCourier(){
    document.getElementById('actionsMenu').classList.add('hidden');
    const p=document.getElementById('cProg');p.classList.remove('hidden');
    document.getElementById('cProgL').textContent='🔄 Syncing courier statuses...';
    document.getElementById('cProgB').style.width='30%';
    fetch('<?= SITE_URL ?>/api/courier-sync.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({limit:50})})
    .then(r=>r.json()).then(d=>{
        document.getElementById('cProgB').style.width='100%';
        document.getElementById('cProgL').textContent='✓ Synced '+d.total+' orders: '+d.updated+' updated, '+d.errors+' errors';
        if(d.details?.length){const e=document.getElementById('cErr');e.classList.remove('hidden');e.innerHTML=d.details.slice(0,5).join('<br>')}
        setTimeout(()=>location.reload(),2500);
    }).catch(e=>{document.getElementById('cProgL').textContent='Sync error: '+e.message});
}

/* ─── Fraud Check Popup ─── */
function fcCheck(phone) {
    if (!phone) { phone = prompt('Enter phone number (01XXXXXXXXX):'); if (!phone) return; }
    phone = phone.replace(/\D/g, '');
    if (phone.length < 10) { alert('Invalid phone number'); return; }
    let m = document.getElementById('fcModal');
    if (!m) {
        m = document.createElement('div'); m.id = 'fcModal';
        m.innerHTML = `<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:center;justify-content:center" onclick="if(event.target===this)this.parentElement.style.display='none'">
            <div style="background:#fff;border-radius:12px;max-width:700px;width:95%;max-height:85vh;overflow-y:auto;box-shadow:0 25px 50px rgba(0,0,0,.25)">
                <div style="padding:12px 16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
                    <div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">🔍</span><b style="font-size:13px">Customer Fraud Check</b></div>
                    <div style="display:flex;gap:6px;align-items:center">
                        <input id="fcPhone" type="tel" placeholder="01XXXXXXXXX" style="border:2px solid #e5e7eb;border-radius:6px;padding:4px 10px;width:140px;font-family:monospace;font-size:12px">
                        <button onclick="fcRun()" style="background:#3b82f6;color:#fff;border:none;border-radius:6px;padding:4px 12px;font-size:11px;cursor:pointer;font-weight:600">Check</button>
                        <button onclick="this.closest('#fcModal').style.display='none'" style="background:none;border:none;font-size:18px;cursor:pointer;color:#999">✕</button>
                    </div>
                </div>
                <div id="fcBody" style="padding:12px 16px"></div>
            </div></div>`;
        document.body.appendChild(m);
    }
    m.style.display = 'block';
    document.getElementById('fcPhone').value = phone;
    fcRun();
}
function fcRun() {
    const phone = document.getElementById('fcPhone').value.trim().replace(/\D/g, '');
    if (!phone || phone.length < 10) return;
    const body = document.getElementById('fcBody');
    body.innerHTML = '<div style="text-align:center;padding:30px;color:#9ca3af"><div style="font-size:20px;margin-bottom:6px">⏳</div>Checking...</div>';
    fetch('<?= SITE_URL ?>/api/fraud-checker.php?phone=' + encodeURIComponent(phone))
    .then(r => r.json()).then(j => {
        if (!j.success) { body.innerHTML = '<div style="padding:16px;color:#dc2626">❌ ' + (j.error||'Error') + '</div>'; return; }
        const p=j.pathao||{}, s=j.steadfast||{}, r=j.redx||{}, l=j.local||{}, co=j.combined||{};
        const risk=co.risk||'new';
        const riskBg={low:'#dcfce7',medium:'#fef9c3',high:'#fee2e2',new:'#dbeafe',blocked:'#fee2e2'}[risk]||'#f3f4f6';
        const riskTxt={low:'#166534',medium:'#854d0e',high:'#991b1b',new:'#1e40af',blocked:'#991b1b'}[risk]||'#374151';
        function apiCard(name, data, color) {
            if (data.error) return `<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px;flex:1;min-width:120px"><div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:2px">${name}</div><div style="font-size:10px;color:#ef4444">❌ ${data.error.substring(0,50)}</div></div>`;
            if (data.show_count===false && data.customer_rating) {
                const labels={excellent_customer:'⭐ Excellent',good_customer:'✅ Good',moderate_customer:'⚠️ Moderate',risky_customer:'🚫 Risky'};
                return `<div style="background:${color}11;border:1px solid ${color}33;border-radius:8px;padding:8px;flex:1;min-width:120px"><div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:2px">${name}</div><div style="font-size:13px;font-weight:700;color:${color}">${labels[data.customer_rating]||data.customer_rating}</div></div>`;
            }
            const total=data.total||0, success=data.success||0, rate=total>0?Math.round(success/total*100):0;
            return `<div style="background:${color}11;border:1px solid ${color}33;border-radius:8px;padding:8px;flex:1;min-width:120px"><div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:2px">${name}</div><div style="font-size:16px;font-weight:800;color:${color}">${rate}%</div><div style="font-size:10px;color:#6b7280">✅${success} ❌${(data.cancel||0)} (${total})</div></div>`;
        }
        body.innerHTML = `
        <div style="background:${riskBg};border-radius:8px;padding:10px 14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">
            <div><span style="font-size:14px;font-weight:800;color:#1f2937">📱 ${j.phone}</span>
            ${l.total>0?`<span style="font-size:10px;color:#6b7280;margin-left:6px">${l.total} orders · ৳${Number(l.total_spent||0).toLocaleString()}</span>`:''}</div>
            <span style="background:${riskBg};color:${riskTxt};padding:3px 12px;border-radius:20px;font-size:11px;font-weight:800;border:2px solid ${riskTxt}33">${co.risk_label||'Unknown'}</span>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
            ${apiCard('Pathao',p,'#3b82f6')} ${apiCard('Steadfast',s,'#8b5cf6')} ${apiCard('RedX',r,'#ef4444')}
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px;flex:1;min-width:120px">
                <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:2px">Local DB</div>
                <div style="font-size:10px;color:#6b7280">✅${l.delivered||0} ❌${l.cancelled||0} 🔄${l.returned||0}</div>
            </div>
        </div>`;
    }).catch(e => { body.innerHTML = '<div style="padding:16px;color:#dc2626">❌ ' + e.message + '</div>'; });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
