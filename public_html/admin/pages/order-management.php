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
                        $resp = $sf->createOrder(['invoice'=>$o['order_number'],'recipient_name'=>$o['customer_name'],'recipient_phone'=>$o['customer_phone'],'recipient_address'=>$o['customer_address'],'cod_amount'=>($o['payment_method']==='cod')?$o['total']:0,'note'=>$o['notes']??'']);
                        if (!empty($resp['consignment']['consignment_id'])) {
                            $db->update('orders', ['courier_name'=>'Steadfast','courier_tracking_id'=>$resp['consignment']['tracking_code']??$resp['consignment']['consignment_id'],'courier_consignment_id'=>$resp['consignment']['consignment_id'],'order_status'=>'shipped','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$oid]);
                            $results['success']++;
                        } else { $results['failed']++; $results['errors'][]="#{$o['order_number']}: ".($resp['message']??'Failed'); }
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
$orders = $db->fetchAll("SELECT o.*, au.full_name as assigned_name FROM orders o LEFT JOIN admin_users au ON au.id = o.assigned_to WHERE {$where} ORDER BY o.created_at DESC LIMIT {$limit} OFFSET {$offset}", $params);

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
?>

<?php if (isset($_GET['msg'])): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">✓ <?= $_GET['msg'] === 'updated' ? 'Status updated.' : ($_GET['msg'] === 'bulk_updated' ? 'Bulk update completed.' : 'Action completed.') ?></div><?php endif; ?>

<!-- Today's Summary -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 text-center">
        <p class="text-2xl font-bold text-gray-800"><?= intval($todaySummary['total'] ?? 0) ?></p>
        <p class="text-[10px] text-gray-400 uppercase tracking-wider">Today's Orders</p>
    </div>
    <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-3 text-center cursor-pointer hover:shadow" onclick="location='?status=processing'">
        <p class="text-2xl font-bold text-yellow-600"><?= $statusCounts['processing'] ?></p>
        <p class="text-[10px] text-yellow-600 uppercase tracking-wider">⚙ Processing</p>
    </div>
    <div class="bg-blue-50 rounded-xl border border-blue-200 p-3 text-center cursor-pointer hover:shadow" onclick="location='?status=confirmed'">
        <p class="text-2xl font-bold text-blue-600"><?= $statusCounts['confirmed'] ?></p>
        <p class="text-[10px] text-blue-600 uppercase tracking-wider">✅ Confirmed</p>
    </div>
    <div class="bg-purple-50 rounded-xl border border-purple-200 p-3 text-center cursor-pointer hover:shadow" onclick="location='?status=shipped'">
        <p class="text-2xl font-bold text-purple-600"><?= $statusCounts['shipped'] ?></p>
        <p class="text-[10px] text-purple-600 uppercase tracking-wider">🚚 Shipped</p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center cursor-pointer hover:shadow" onclick="location='?status=delivered'">
        <p class="text-2xl font-bold text-green-600"><?= $statusCounts['delivered'] ?></p>
        <p class="text-[10px] text-green-600 uppercase tracking-wider">📦 Delivered</p>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
        <p class="text-2xl font-bold text-gray-800">৳<?= number_format($todaySummary['revenue'] ?? 0) ?></p>
        <p class="text-[10px] text-gray-400 uppercase tracking-wider">Today Revenue</p>
    </div>
</div>

<!-- All Status Tabs - Single Horizontal Bar -->
<div class="bg-white rounded-xl border mb-4 overflow-hidden">
    <div class="overflow-x-auto">
        <div class="flex items-center min-w-max border-b">
            <?php 
            // All statuses in the order shown in reference
            $allTabStatuses = ['processing', 'confirmed', 'shipped', 'delivered', 'pending_return', 'returned', 'partial_delivered', 'cancelled', 'pending_cancel', 'on_hold', 'no_response', 'good_but_no_response', 'advance_payment', 'lost'];
            foreach ($allTabStatuses as $s):
                $tc = $tabConfig[$s] ?? ['icon'=>'','color'=>'gray','label'=>ucwords(str_replace('_',' ',$s))];
                $cnt = $statusCounts[$s] ?? 0;
                $isActive = $status === $s;
            ?>
            <a href="?status=<?= $s ?>" class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition <?= $isActive ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <?= $tc['label'] ?> <span class="px-1.5 py-0.5 rounded text-xs <?= $isActive ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-500' ?>"><?= number_format($cnt) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Quick filter row -->
    <div class="flex items-center gap-2 px-4 py-2 bg-gray-50/50">
        <a href="<?= adminUrl('pages/order-management.php') ?>" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium <?= !$status ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border hover:bg-gray-100' ?>">
            All <span class="ml-1 px-1.5 rounded text-[10px] <?= !$status?'bg-gray-600 text-white':'bg-gray-200' ?>"><?= number_format($totalOrders) ?></span>
        </a>
        <?php if ($incompleteCount > 0): ?>
        <a href="<?= adminUrl('pages/incomplete-orders.php') ?>" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 border border-red-200">
            🛒 Incomplete <span class="bg-red-100 px-1.5 rounded text-[10px]"><?= $incompleteCount ?></span>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Actions Bar -->
<div class="bg-white rounded-xl shadow-sm border p-3 mb-4">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="relative flex-1 min-w-[200px]">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by order #, name, phone..." class="w-full pl-9 pr-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <a href="<?= adminUrl('pages/order-add.php') ?>" class="bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-green-700">⊕ New</a>
        <button type="button" onclick="document.getElementById('advFilters').classList.toggle('hidden')" class="border text-gray-600 px-3 py-2 rounded-lg text-sm hover:bg-gray-50">🔍 Filters</button>
        <div class="relative" id="actionsWrap">
            <button type="button" onclick="document.getElementById('actionsMenu').classList.toggle('hidden')" class="border text-gray-600 px-3 py-2 rounded-lg text-sm hover:bg-gray-50">⋮ Actions</button>
            <div id="actionsMenu" class="hidden absolute right-0 top-full mt-1 w-64 bg-white rounded-xl shadow-2xl border z-50 py-1">
                <div class="px-3 py-1.5 flex items-center justify-between"><span id="selC" class="text-xs text-gray-500">0 selected</span><button type="button" onclick="document.getElementById('selectAll').checked=true;toggleAll(document.getElementById('selectAll'))" class="text-xs text-blue-600">✓ Select All</button></div><hr class="my-1">
                <p class="px-3 py-1 text-xs font-semibold text-gray-400">📄 PRINT</p>
                <button type="button" onclick="bPrint('standard')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">📄 Invoice</button>
                <button type="button" onclick="bPrint('sticker')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">🏷 Sticker</button>
                <button type="button" onclick="bPrint('picking')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">📦 Picking</button>
                <button type="button" onclick="bPrint('compact')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">📋 Sheet</button>
                <hr class="my-1"><p class="px-3 py-1 text-xs font-semibold text-gray-400">✎ STATUS</p>
                <button type="button" onclick="bStatus('confirmed')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">✅ Confirm</button>
                <button type="button" onclick="bStatus('shipped')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">🚚 Ship</button>
                <button type="button" onclick="bStatus('delivered')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50">📦 Deliver</button>
                <button type="button" onclick="bStatus('returned')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50 text-orange-600">↩ Confirm Return</button>
                <button type="button" onclick="bStatus('cancelled')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50 text-red-600">✗ Cancel</button>
                <hr class="my-1"><p class="px-3 py-1 text-xs font-semibold text-gray-400">🚛 COURIER</p>
                <button type="button" onclick="bCourier('Pathao')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50"><span class="inline-block w-4 h-4 bg-red-500 text-white rounded text-xs text-center mr-1 font-bold leading-4">P</span> Pathao</button>
                <button type="button" onclick="bCourier('Steadfast')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50"><span class="inline-block w-4 h-4 bg-blue-500 text-white rounded text-xs text-center mr-1 font-bold leading-4">S</span> Steadfast</button>
                <button type="button" onclick="bCourier('CarryBee')" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50"><span class="inline-block w-4 h-4 bg-green-500 text-white rounded text-xs text-center mr-1 font-bold leading-4">C</span> CarryBee</button>
                <hr class="my-1">
                <button type="button" onclick="syncCourier()" class="w-full text-left px-4 py-1.5 text-sm hover:bg-gray-50 text-indigo-600">🔄 Sync Courier Status</button>
                <hr class="my-1">
                <a href="<?= SITE_URL ?>/api/export.php?type=orders<?= $status?'&status='.$status:'' ?>" class="block px-4 py-1.5 text-sm hover:bg-gray-50">📊 Export Excel</a>
            </div>
        </div>
    </form>
    <div id="advFilters" class="<?= ($dateFrom||$dateTo||$channel||$assignedTo)?'':'hidden' ?> mt-3 pt-3 border-t">
        <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" placeholder="From" class="px-3 py-2 border rounded-lg text-sm">
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" placeholder="To" class="px-3 py-2 border rounded-lg text-sm">
            <select name="channel" class="px-3 py-2 border rounded-lg text-sm"><option value="">All Channels</option><?php foreach(['website','facebook','phone','whatsapp'] as $ch): ?><option value="<?= $ch ?>" <?= $channel===$ch?'selected':'' ?>><?= ucfirst($ch) ?></option><?php endforeach; ?></select>
            <select name="assigned" class="px-3 py-2 border rounded-lg text-sm"><option value="">All Staff</option><?php foreach($adminUsers as $au): ?><option value="<?= $au['id'] ?>" <?= $assignedTo==$au['id']?'selected':'' ?>><?= e($au['full_name']) ?></option><?php endforeach; ?></select>
            <div class="flex gap-2"><button class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Apply</button><a href="<?= adminUrl('pages/order-management.php') ?>" class="bg-gray-100 text-gray-600 px-3 py-2 rounded-lg text-sm">✕</a></div>
        </form>
    </div>
</div>

<!-- Courier Progress -->
<div id="cProg" class="hidden mb-4 bg-white rounded-xl border p-4">
    <div class="flex items-center justify-between mb-2"><span class="text-sm font-medium" id="cProgL">Uploading...</span><span id="cProgC" class="text-xs text-gray-500"></span></div>
    <div class="w-full bg-gray-200 rounded-full h-2"><div id="cProgB" class="bg-blue-600 h-2 rounded-full transition-all" style="width:0%"></div></div>
    <div id="cErr" class="mt-2 text-xs text-red-600 hidden"></div>
</div>

<!-- Orders Table -->
<form method="POST" id="bulkForm"><input type="hidden" name="action" value="bulk_status">
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between">
        <p class="text-sm text-gray-500"><strong class="text-gray-800"><?= number_format($total) ?></strong> orders <?= $status ? '('.($tabConfig[$status]['label'] ?? ucfirst($status)).')' : '' ?></p>
        <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>Flow:</span>
            <span class="text-yellow-600 font-medium">Processing</span><span>→</span>
            <span class="text-blue-600 font-medium">Confirmed</span><span>→</span>
            <span class="text-purple-600 font-medium">Shipped</span><span>→</span>
            <span class="text-green-600 font-medium">Delivered</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-3 w-8"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" class="rounded"></th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Date</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Invoice</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Customer</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Items</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Total</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Status</th>
                <th class="px-3 py-3 text-left text-gray-600 text-xs font-medium">Courier</th>
                <th class="px-3 py-3 text-center text-gray-600 text-xs font-medium">Action</th>
            </tr></thead>
            <tbody class="divide-y">
<?php foreach ($orders as $order):
    $sr=$successRates[$order['customer_phone']]??['total'=>0,'delivered'=>0,'rate'=>0,'cancelled'=>0,'total_spent'=>0];
    $prevO=$previousOrders[$order['customer_phone']]??[];
    $oItems=$orderItems[$order['id']]??[]; $tags=$orderTags[$order['id']]??[];
    $rC=$sr['rate']>=80?'text-green-600':($sr['rate']>=50?'text-yellow-600':'text-red-500');
    $ph=preg_replace('/[^0-9]/','',$order['customer_phone']);
    $oStatus = $order['order_status'] === 'pending' ? 'processing' : $order['order_status'];
    $nxt = $nextAction[$oStatus] ?? null;
    $creditUsed = floatval($order['store_credit_used'] ?? 0);
?>
<tr class="hover:bg-gray-50">
    <td class="px-3 py-2.5"><input type="checkbox" name="order_ids[]" value="<?= $order['id'] ?>" class="order-check rounded" onchange="updateBulk()"></td>
    <td class="px-3 py-2.5 whitespace-nowrap">
        <p class="text-xs font-medium text-gray-700"><?= date('d/m/y', strtotime($order['created_at'])) ?></p>
        <p class="text-[10px] text-gray-400"><?= date('h:i A', strtotime($order['created_at'])) ?></p>
        <p class="text-[10px] text-gray-400"><?= timeAgo($order['updated_at']?:$order['created_at']) ?></p>
    </td>
    <td class="px-3 py-2.5">
        <a href="<?= adminUrl('pages/order-view.php?id='.$order['id']) ?>" class="font-mono text-sm font-semibold text-gray-800 hover:text-blue-600"><?= e($order['order_number']) ?></a>
        <?php if (!empty($order['notes'])): ?><p class="text-[10px] text-orange-600 font-medium mt-0.5 max-w-[120px] truncate" title="<?= e($order['notes']) ?>">📝 <?= e(mb_strimwidth($order['notes'],0,30,'...')) ?></p><?php endif; ?>
    </td>
    <td class="px-3 py-2.5">
        <div class="min-w-[180px]">
            <p class="text-sm text-gray-800 font-medium"><?= e($order['customer_name']) ?></p>
            <div class="flex items-center gap-1.5 mt-0.5">
                <span class="text-xs text-gray-500"><?= e($order['customer_phone']) ?></span>
                <span class="font-bold text-[10px] <?= $rC ?>"><?= $sr['rate'] ?>%</span>
                <a href="tel:<?= $ph ?>" class="text-green-500 hover:text-green-700"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></a>
                <a href="https://wa.me/88<?= $ph ?>" target="_blank" class="text-green-600"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg></a>
            </div>
            <p class="text-[10px] text-gray-400 truncate max-w-[180px]">📍 <?= e(mb_strimwidth($order['customer_address'],0,35,'...')) ?></p>
            <?php if (count($prevO) > 1): ?>
            <div class="mt-0.5"><span class="text-[10px] text-blue-600 cursor-pointer hover:underline" onclick="this.nextElementSibling.classList.toggle('hidden')">📦 <?= $sr['total'] ?> orders · ৳<?= number_format($sr['total_spent']) ?></span>
                <div class="hidden absolute z-30 bg-white border rounded-lg shadow-xl p-2 w-52 max-h-36 overflow-y-auto">
                    <?php foreach($prevO as $po): if($po['id']==$order['id']) continue; ?>
                    <a href="<?= adminUrl('pages/order-view.php?id='.$po['id']) ?>" class="flex items-center justify-between px-2 py-1 hover:bg-gray-50 rounded text-xs">
                        <span class="font-mono">#<?= e($po['order_number']) ?></span>
                        <span class="<?= getOrderStatusBadge($po['order_status']) ?> px-1 py-0.5 rounded"><?= getOrderStatusLabel($po['order_status']) ?></span>
                        <span>৳<?= number_format($po['total']) ?></span>
                    </a><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </td>
    <td class="px-3 py-2.5">
        <div class="flex items-center gap-1">
            <?php foreach(array_slice($oItems,0,2) as $item): ?>
            <div class="w-9 h-9 rounded-lg border bg-gray-50 overflow-hidden flex-shrink-0" title="<?= e($item['product_name']) ?>"><img src="<?= !empty($item['featured_image'])?imgSrc('products',$item['featured_image']):'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 40 40%22><text y=%22.9em%22 font-size=%2230%22>📦</text></svg>' ?>" class="w-full h-full object-cover" loading="lazy"></div>
            <?php endforeach; ?>
            <?php if(count($oItems)>2): ?><span class="text-xs text-gray-400">+<?= count($oItems)-2 ?></span><?php endif; ?>
        </div>
        <?php foreach($oItems as $it): if(!empty($it['variant_name'])): ?><p class="text-[10px] text-indigo-600 mt-0.5"><?= e($it['variant_name']) ?></p><?php break; endif; endforeach; ?>
    </td>
    <td class="px-3 py-2.5">
        <p class="font-bold text-gray-800">৳<?= number_format($order['total']) ?></p>
        <?php if ($creditUsed > 0): ?><p class="text-[10px] text-yellow-600"><i class="fas fa-coins"></i> -৳<?= number_format($creditUsed) ?></p><?php endif; ?>
        <p class="text-[10px] text-gray-400">+৳<?= number_format($order['shipping_cost']??0) ?> ship</p>
    </td>
    <td class="px-3 py-2.5">
        <span class="text-xs px-2 py-1 rounded-full font-medium <?= getOrderStatusBadge($oStatus) ?>"><?= getOrderStatusLabel($oStatus) ?></span>
        <?php if(!empty($tags)): ?><div class="flex flex-wrap gap-0.5 mt-1"><?php foreach(array_slice($tags,0,2) as $tag): ?><span class="text-[10px] bg-gray-100 text-gray-600 px-1 py-0.5 rounded"><?= e($tag['tag_name']) ?></span><?php endforeach; ?></div><?php endif; ?>
        <button onclick="addTag(<?= $order['id'] ?>)" class="text-[10px] text-blue-400 hover:text-blue-600 mt-0.5">+tag</button>
    </td>
    <td class="px-3 py-2.5">
        <?php if(!empty($order['courier_consignment_id'])): ?><span class="text-xs text-green-600 font-medium">✓ Pathao</span>
        <?php elseif(!empty($order['courier_name'])): ?><span class="text-xs text-blue-600"><?= e($order['courier_name']) ?></span>
        <?php else: ?><span class="text-[10px] text-gray-400">—</span><?php endif; ?>
        <?php if(!empty($order['courier_status'])): ?><p class="text-[10px] text-gray-400 mt-0.5" title="Courier API status"><?= e($order['courier_status']) ?></p><?php endif; ?>
    </td>
    <td class="px-3 py-2.5 text-center">
        <div class="flex items-center gap-1 justify-center">
            <?php if ($nxt): ?>
            <form method="POST" class="inline" onsubmit="return confirm('<?= $nxt['label'] ?> this order?')">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <input type="hidden" name="status" value="<?= $nxt['status'] ?>">
                <button class="px-2 py-1 rounded text-xs font-medium bg-<?= $nxt['color'] ?>-100 text-<?= $nxt['color'] ?>-700 hover:bg-<?= $nxt['color'] ?>-200 transition" title="<?= $nxt['label'] ?>">
                    <?= $nxt['icon'] ?> <?= $nxt['label'] ?>
                </button>
            </form>
            <?php endif; ?>
            <a href="<?= adminUrl('pages/order-view.php?id='.$order['id']) ?>" class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200">Open</a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if(empty($orders)): ?><tr><td colspan="9" class="px-4 py-12 text-center text-gray-400"><div class="text-4xl mb-2">📦</div><p>No orders found</p></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between px-4 py-3 border-t">
        <p class="text-sm text-gray-500">Page <?= $page ?>/<?= $totalPages ?></p>
        <div class="flex gap-1">
            <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200">←</a><?php endif; ?>
            <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="px-3 py-1.5 text-sm rounded <?= $i===$page?'bg-blue-600 text-white':'bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a><?php endfor; ?>
            <?php if($page<$totalPages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200">→</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div></form>

<!-- Tag Modal -->
<div id="tagModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center">
    <div class="bg-white rounded-xl p-5 w-80 shadow-2xl">
        <h3 class="font-bold text-gray-800 mb-3">Add Tag</h3><input type="hidden" id="tagOId">
        <div class="flex flex-wrap gap-2 mb-3"><?php foreach(['REPEAT','URGENT','VIP','GIFT','FOLLOW UP','COD VERIFIED','ADVANCE PAID'] as $p): ?><button onclick="subTag('<?= $p ?>')" class="text-xs bg-gray-100 hover:bg-blue-100 px-2 py-1 rounded"><?= $p ?></button><?php endforeach; ?></div>
        <div class="flex gap-2"><input type="text" id="tagIn" placeholder="Custom..." class="flex-1 px-3 py-2 border rounded-lg text-sm" onkeydown="if(event.key==='Enter')subTag(this.value)"><button onclick="subTag(document.getElementById('tagIn').value)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Add</button></div>
        <button onclick="document.getElementById('tagModal').classList.add('hidden')" class="mt-2 text-xs text-gray-400 w-full text-center">Cancel</button>
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

document.addEventListener('click',e=>{const w=document.getElementById('actionsWrap');if(w&&!w.contains(e.target))document.getElementById('actionsMenu').classList.add('hidden')});

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
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
