<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Order Details';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(adminUrl('pages/order-management.php'));
$order = $db->fetch("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) redirect(adminUrl('pages/order-management.php'));

/* ─── POST Actions ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_order' || $action === 'confirm_order') {
        $name         = sanitize($_POST['customer_name']    ?? $order['customer_name']);
        $phone        = sanitize($_POST['customer_phone']   ?? $order['customer_phone']);
        $address      = sanitize($_POST['customer_address'] ?? $order['customer_address']);
        $shippingNote = sanitize($_POST['shipping_note']    ?? '');
        $deliveryMethod = sanitize($_POST['delivery_method'] ?? 'Pathao Courier');
        $adminNotes   = isset($_POST['admin_notes']) && $_POST['admin_notes'] !== ''
                        ? sanitize($_POST['admin_notes']) : ($order['admin_notes'] ?? '');
        $channel      = sanitize($_POST['channel']  ?? $order['channel']  ?? 'website');
        $isPreorder   = !empty($_POST['is_preorder']) ? 1 : 0;

        $productIds   = $_POST['item_product_id']   ?? [];
        $productNames = $_POST['item_product_name']  ?? [];
        $variantNames = $_POST['item_variant_name']  ?? [];
        $qtys         = $_POST['item_qty']           ?? [];
        $prices       = $_POST['item_price']         ?? [];

        if (!empty($productIds)) {
            $db->delete('order_items', 'order_id = ?', [$id]);
            $subtotal = 0;
            foreach ($productIds as $i => $pid) {
                $qty   = max(1, intval($qtys[$i] ?? 1));
                $price = floatval($prices[$i] ?? 0);
                $line  = $price * $qty;
                $subtotal += $line;
                $db->insert('order_items', [
                    'order_id' => $id, 'product_id' => intval($pid) ?: null,
                    'product_name' => sanitize($productNames[$i] ?? 'Product'),
                    'variant_name' => sanitize($variantNames[$i] ?? '') ?: null,
                    'quantity' => $qty, 'price' => $price, 'subtotal' => $line,
                ]);
            }
        } else {
            $subtotal = floatval($order['subtotal']);
        }

        $discount     = floatval($_POST['discount_amount'] ?? $order['discount_amount'] ?? 0);
        $advance      = floatval($_POST['advance_amount']  ?? 0);
        $shippingCost = floatval($_POST['shipping_cost']   ?? $order['shipping_cost'] ?? 0);
        $total        = $subtotal + $shippingCost - $discount;

        $updateData = [
            'customer_name' => $name, 'customer_phone' => $phone, 'customer_address' => $address,
            'shipping_method' => $deliveryMethod, 'courier_name' => $deliveryMethod,
            'admin_notes' => $adminNotes, 'subtotal' => $subtotal, 'shipping_cost' => $shippingCost,
            'discount_amount' => $discount, 'advance_amount' => $advance, 'total' => $total,
            'notes' => $shippingNote ?: ($order['notes'] ?? ''),
            'channel' => $channel, 'is_preorder' => $isPreorder,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($action === 'confirm_order' && in_array($order['order_status'], ['pending','processing'])) {
            $updateData['order_status'] = 'confirmed';
            $db->insert('order_status_history', ['order_id'=>$id,'status'=>'confirmed','changed_by'=>getAdminId(),'note'=>'Order confirmed']);
            logActivity(getAdminId(), 'confirm_order', 'orders', $id, 'Confirmed order');
        }

        $db->update('orders', $updateData, 'id = ?', [$id]);
        logActivity(getAdminId(), 'update', 'orders', $id);
        redirect(adminUrl("pages/order-view.php?id={$id}&msg=" . ($action === 'confirm_order' ? 'confirmed' : 'updated')));
    }

    if ($action === 'update_status') {
        $newStatus = sanitize($_POST['status']);
        $notes     = sanitize($_POST['notes'] ?? '');
        $db->update('orders', ['order_status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $db->insert('order_status_history', ['order_id'=>$id,'status'=>$newStatus,'changed_by'=>getAdminId(),'note'=>$notes]);
        logActivity(getAdminId(), 'update_status', 'orders', $id, "Changed to {$newStatus}");
        if ($newStatus === 'delivered')  { try { awardOrderCredits($id); } catch (\Throwable $e) {} }
        if ($newStatus === 'cancelled')  { try { refundOrderCreditsOnCancel($id); } catch (\Throwable $e) {} }
        redirect(adminUrl("pages/order-view.php?id={$id}&msg=status_updated"));
    }

    if ($action === 'mark_fake') {
        $db->update('orders', ['is_fake'=>1,'order_status'=>'cancelled'], 'id = ?', [$id]);
        try { refundOrderCreditsOnCancel($id); } catch (\Throwable $e) {}
        $ex = $db->fetch("SELECT id FROM blocked_phones WHERE phone = ?", [$order['customer_phone']]);
        if (!$ex) $db->insert('blocked_phones', ['phone'=>$order['customer_phone'],'reason'=>'Fake order #'.$order['order_number'],'blocked_by'=>getAdminId()]);
        logActivity(getAdminId(), 'mark_fake', 'orders', $id);
        redirect(adminUrl("pages/order-view.php?id={$id}&msg=marked_fake"));
    }

    if ($action === 'add_note') {
        $note = sanitize($_POST['note_text'] ?? '');
        if ($note) {
            $ex = $order['admin_notes'] ?? '';
            $new = $ex ? $ex."\n---\n".date('d M h:i A').": ".$note : date('d M h:i A').": ".$note;
            $db->update('orders', ['admin_notes'=>$new], 'id = ?', [$id]);
            logActivity(getAdminId(), 'add_note', 'orders', $id, null, $note);
            $db->insert('order_status_history', ['order_id'=>$id,'status'=>$order['order_status'],'changed_by'=>getAdminId(),'note'=>'Note: '.mb_strimwidth($note,0,100,'...')]);
        }
        redirect(adminUrl("pages/order-view.php?id={$id}&msg=note_added"));
    }
}

/* ─── Reload data ─── */
$order    = $db->fetch("SELECT * FROM orders WHERE id = ?", [$id]);
$items    = $db->fetchAll("SELECT oi.*, p.slug, p.featured_image, p.sku, p.stock_quantity FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?", [$id]);
$history  = $db->fetchAll("SELECT osh.*, au.full_name as changed_by_name FROM order_status_history osh LEFT JOIN admin_users au ON au.id = osh.changed_by WHERE osh.order_id = ? ORDER BY osh.created_at DESC", [$id]);
$customer = $order['customer_id'] ? $db->fetch("SELECT * FROM customers WHERE id = ?", [$order['customer_id']]) : null;

$activityLogs = [];
try { $activityLogs = $db->fetchAll("SELECT al.*, au.full_name as admin_name FROM activity_logs al LEFT JOIN admin_users au ON au.id = al.admin_user_id WHERE al.entity_type='orders' AND al.entity_id=? ORDER BY al.created_at DESC LIMIT 30", [$id]); } catch (\Throwable $e) {}
try { $lv = $db->fetch("SELECT id FROM activity_logs WHERE admin_user_id=? AND entity_type='orders' AND entity_id=? AND action='view_order' AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)", [getAdminId(), $id]); if (!$lv) logActivity(getAdminId(), 'view_order', 'orders', $id); } catch (\Throwable $e) {}

/* Success rates */
$sr = ['total'=>0,'delivered'=>0,'cancelled'=>0,'returned'=>0,'rate'=>0,'total_spent'=>0];
$ph = '%'.substr(preg_replace('/[^0-9]/','',$order['customer_phone']),-10).'%';
try {
    $r = $db->fetch("SELECT COUNT(*) as total, SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled, SUM(CASE WHEN order_status='returned' THEN 1 ELSE 0 END) as returned, SUM(total) as total_spent FROM orders WHERE customer_phone LIKE ?", [$ph]);
    if ($r) $sr = ['total'=>intval($r['total']),'delivered'=>intval($r['delivered']),'cancelled'=>intval($r['cancelled']),'returned'=>intval($r['returned']),'rate'=>$r['total']>0?round($r['delivered']/$r['total']*100):0,'total_spent'=>floatval($r['total_spent']??0)];
} catch (\Throwable $e) {}

$courierRates = [];
foreach (['Pathao','RedX','Steadfast'] as $cn) {
    try {
        $cr = $db->fetch("SELECT COUNT(*) as total, SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled FROM orders WHERE customer_phone LIKE ? AND (LOWER(courier_name) LIKE ? OR LOWER(shipping_method) LIKE ?)", [$ph, strtolower($cn).'%', '%'.strtolower($cn).'%']);
        $courierRates[$cn] = ['total'=>intval($cr['total']??0),'delivered'=>intval($cr['delivered']??0),'cancelled'=>intval($cr['cancelled']??0),'rate'=>($cr['total']??0)>0?round($cr['delivered']/$cr['total']*100):0];
    } catch (\Throwable $e) { $courierRates[$cn] = ['total'=>0,'delivered'=>0,'cancelled'=>0,'rate'=>0]; }
}

$webCancels = 0;
try { $wc = $db->fetch("SELECT COUNT(*) as cnt FROM orders WHERE customer_phone LIKE ? AND channel='website' AND order_status='cancelled'", [$ph]); $webCancels = intval($wc['cnt']??0); } catch (\Throwable $e) {}

$createdAgo  = timeAgo($order['created_at']);
$updatedAgo  = timeAgo($order['updated_at'] ?? $order['created_at']);
$isPending   = in_array($order['order_status'], ['pending','processing']);
$visitorLog  = null;
try { if (!empty($order['visitor_id'])) $visitorLog = $db->fetch("SELECT * FROM visitor_logs WHERE id = ?", [$order['visitor_id']]); elseif (!empty($order['ip_address'])) $visitorLog = $db->fetch("SELECT * FROM visitor_logs WHERE device_ip=? AND created_at >= DATE_SUB(?, INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1", [$order['ip_address'], $order['created_at']]); } catch (\Throwable $e) {}
$orderTags   = [];
try { $orderTags = $db->fetchAll("SELECT * FROM order_tags WHERE order_id = ?", [$id]); } catch (\Throwable $e) {}
$custCredit  = 0;
if ($order['customer_id']) { try { $custCredit = getStoreCredit($order['customer_id']); } catch (\Throwable $e) {} }

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
    <?= ['status_updated'=>'✓ Status updated.','updated'=>'✓ Order saved.','marked_fake'=>'⚠ Marked as fake.','confirmed'=>'✅ Order confirmed.','note_added'=>'✓ Note added.'][$_GET['msg']] ?? '✓ Done.' ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════ HEADER BAR ══════════════════════════ -->
<div class="flex flex-wrap items-center gap-3 mb-4">
    <a href="<?= adminUrl('pages/order-management.php'.($isPending?'?status=processing':'')) ?>" class="p-1.5 rounded hover:bg-gray-100 transition">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h2 class="text-base font-bold text-gray-800"><?= $isPending ? 'Web Order Details' : 'Order Details' ?></h2>
    <div class="flex items-center gap-1.5 ml-auto flex-wrap text-xs">
        <span class="text-gray-500">Created <b><?= $createdAgo ?></b></span>
        <span class="text-gray-500">Updated <b><?= $updatedAgo ?></b></span>
        <span class="text-gray-500">Status</span>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= getOrderStatusBadge($order['order_status']) ?>"><?= strtoupper(getOrderStatusLabel($order['order_status'])) ?></span>
        <span class="text-gray-500">Source</span>
        <span class="bg-gray-100 px-2 py-0.5 rounded text-[10px] font-medium"><?= strtoupper($order['channel']??'WEB')==='WEBSITE'?'WEB':strtoupper($order['channel']??'WEB') ?></span>
    </div>
</div>

<!-- ══════════════════════════ MAIN LAYOUT ══════════════════════════ -->
<form method="POST" id="orderForm">
<div class="flex flex-col lg:flex-row gap-5">

    <!-- ────────── LEFT COLUMN ────────── -->
    <div class="flex-1 min-w-0 space-y-4">

        <!-- ▸ Rate Cards -->
        <div id="courierCards" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <?php
            $cards = ['Overall'=>$sr]+$courierRates;
            $barCol = ['Overall'=>'#10b981','Pathao'=>'#3b82f6','RedX'=>'#ef4444','Steadfast'=>'#8b5cf6'];
            foreach ($cards as $label => $data):
                $rate = $data['rate']??0;
                $rcl  = $rate>=70?'color:#16a34a':($rate>=40?'color:#ca8a04':'color:#dc2626');
            ?>
            <div class="bg-white border border-gray-200 rounded-lg p-3" id="card-<?= strtolower($label) ?>">
                <div class="text-sm font-semibold text-gray-800 mb-1"><?= $label ?></div>
                <div class="text-xs font-bold mb-1" style="<?= $rcl ?>" data-rate>Success Rate: <?= $rate ?>%</div>
                <div class="text-[11px] text-gray-500 leading-relaxed" data-total>Total: <?= $data['total'] ?></div>
                <div class="text-[11px] text-gray-500 leading-relaxed" data-success>Success: <?= $data['delivered'] ?></div>
                <div class="text-[11px] text-gray-500 leading-relaxed" data-cancelled>Cancelled: <?= $data['cancelled'] ?></div>
                <div class="h-1 bg-gray-100 rounded-full mt-2"><div class="h-full rounded-full transition-all" data-bar style="width:<?= min(100,$rate) ?>%;background:<?= $barCol[$label]??'#6b7280' ?>"></div></div>
            </div>
            <?php endforeach; ?>

            <div class="bg-white border border-gray-200 rounded-lg p-3" id="card-ourrecord">
                <div class="text-sm font-semibold text-gray-800 mb-1">Our Record</div>
                <div class="text-xs font-bold text-green-600 mb-1" data-custtype><?= $sr['total']<=1?'New Customer':'Returning Customer' ?></div>
                <div class="text-[11px] text-gray-500 leading-relaxed" data-webcancel>Web Order Cancel: <?= $webCancels ?></div>
                <div class="text-[11px] text-gray-500 leading-relaxed" data-totalspent>Total Spent: ৳<?= number_format($sr['total_spent']??0) ?></div>
                <button type="button" onclick="fetchCourierData()" id="fillInfoBtn" class="w-full mt-2 py-1.5 bg-emerald-500 text-white rounded-md text-xs font-semibold hover:bg-emerald-600 transition">Fill Info</button>
            </div>
        </div>

        <!-- ▸ Store Credit Banner -->
        <?php if ($custCredit > 0): ?>
        <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 text-sm">
            <i class="fas fa-coins text-yellow-500"></i>
            <span class="text-yellow-700">Store Credit: <b><?= number_format($custCredit) ?> credits</b> <span class="text-xs">(৳<?= number_format($custCredit * floatval(getSetting('store_credit_conversion_rate','0.75')?:0.75)) ?>)</span></span>
            <?php if ($order['customer_id']): ?><a href="<?= adminUrl('pages/customer-view.php?id='.$order['customer_id'].'&section=credits') ?>" class="ml-auto text-xs text-yellow-600 hover:underline">Manage →</a><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ▸ Customer Info Card -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Mobile Number</label>
                    <div class="flex items-center gap-2">
                        <input type="text" name="customer_phone" value="<?= e($order['customer_phone']) ?>" class="flex-1 px-3 py-2 border border-gray-200 rounded-md text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none">
                        <a href="tel:<?= e($order['customer_phone']) ?>" class="text-green-600 hover:text-green-700 text-sm"><i class="fas fa-phone"></i></a>
                        <a href="https://wa.me/88<?= preg_replace('/[^0-9]/','',$order['customer_phone']) ?>" target="_blank" class="text-green-600 hover:text-green-700"><i class="fab fa-whatsapp text-base"></i></a>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Name</label>
                    <input type="text" name="customer_name" value="<?= e($order['customer_name']) ?>" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Delivery Method</label>
                    <select name="delivery_method" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none">
                        <?php foreach(['Pathao Courier','Steadfast','CarryBee','RedX','SA Paribahan','Sundarban','Self Delivery','Store Pickup'] as $dm): ?>
                        <option value="<?= $dm ?>" <?= ($order['shipping_method']??$order['courier_name']??'')===$dm?'selected':'' ?>><?= $dm ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($order['courier_status'])): ?><div class="text-[10px] text-indigo-600 mt-1">📡 <?= e($order['courier_status']) ?></div><?php endif; ?>
                    <?php if (!empty($order['courier_consignment_id']) || !empty($order['pathao_consignment_id'])):
                        $__ovCn = strtolower($order['courier_name'] ?: ($order['shipping_method'] ?? ''));
                        $__ovCid = $order['courier_consignment_id'] ?: ($order['pathao_consignment_id'] ?? '');
                        $__ovTid = $order['courier_tracking_id'] ?: $__ovCid;
                        if (strpos($__ovCn, 'steadfast') !== false) {
                            $__ovLink = 'https://portal.steadfast.com.bd/find-consignment?consignment_id=' . urlencode($__ovCid);
                        } elseif (strpos($__ovCn, 'pathao') !== false) {
                            $__ovLink = 'https://merchant.pathao.com/courier/consignments/' . urlencode($__ovCid);
                        } else { $__ovLink = '#'; }
                    ?>
                    <a href="<?= $__ovLink ?>" target="_blank" class="inline-flex items-center gap-1 mt-1 px-2 py-1 bg-green-50 border border-green-200 rounded text-xs text-green-700 hover:bg-green-100 transition font-mono">
                        📦 <?= e($__ovTid) ?> <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ▸ Address / Shipping Note / Extra Card -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Address</label>
                    <textarea name="customer_address" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none resize-none"><?= e($order['customer_address']) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Shipping Note <button type="button" onclick="document.querySelector('[name=shipping_note]').rows=4" class="text-blue-500 text-[10px] ml-1">+</button></label>
                    <textarea name="shipping_note" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-100 outline-none resize-none" placeholder="***No Exchange or Return***"><?= e($order['notes']??'') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">Extra Options</label>
                    <div class="flex items-center gap-1.5 mb-2">
                        <button type="button" onclick="navigator.clipboard.writeText('<?= e($order['order_number']) ?>')" class="p-2 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 text-xs" title="Copy"><i class="far fa-copy"></i></button>
                        <a href="<?= adminUrl('pages/order-print.php?id='.$id) ?>" target="_blank" class="p-2 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 text-xs" title="Print"><i class="fas fa-print"></i></a>
                        <a href="<?= adminUrl('pages/order-print.php?id='.$id.'&template=sticker') ?>" target="_blank" class="p-2 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 text-xs" title="Sticker"><i class="fas fa-tag"></i></a>
                        <?php if (!$order['is_fake']): ?><button type="button" onclick="if(confirm('Mark fake? Phone will be blocked.')){document.getElementById('fakeForm').submit()}" class="p-2 border border-red-200 rounded-md text-red-400 hover:bg-red-50 text-xs" title="Fake"><i class="fas fa-ban"></i></button><?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <select name="channel" class="flex-1 px-2 py-1.5 border border-gray-200 rounded-md text-xs">
                            <?php foreach(['website'=>'🌐 WEB','facebook'=>'📘 Facebook','phone'=>'📞 Phone','whatsapp'=>'💬 WhatsApp','instagram'=>'📷 Instagram','other'=>'📌 Other'] as $cv=>$cl): ?>
                            <option value="<?= $cv ?>" <?= ($order['channel']??'website')===$cv?'selected':'' ?>><?= $cl ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer shrink-0">Preorder
                            <div class="relative"><input type="checkbox" name="is_preorder" value="1" <?= !empty($order['is_preorder'])?'checked':'' ?> class="sr-only peer"><div class="w-8 h-4 bg-gray-200 rounded-full peer-checked:bg-blue-500 transition"></div><div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition peer-checked:translate-x-4 shadow"></div></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ▸ Pathao City/Zone/Area Card -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                <p class="text-xs text-blue-600 flex-1">📍 এড্রেস নিচের এই Filed গুলো অটোমেটিক ফিল হবে, যদি না হয় তাহলে সিলেক্ট করে নিন</p>
                <button type="button" onclick="autoDetectLocation()" class="p-1 text-gray-400 hover:text-blue-600"><i class="fas fa-sync-alt text-xs"></i></button>
                <button type="button" onclick="document.getElementById('pCityId').value='';document.getElementById('pZoneId').innerHTML='<option>Select Zone</option>';document.getElementById('pAreaId').innerHTML='<option>Select Area</option>'" class="p-1 text-gray-400 hover:text-red-500"><i class="fas fa-trash text-xs"></i></button>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs font-semibold text-gray-700 mb-1">City</label><select id="pCityId" onchange="loadZones(this.value)" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm"><option value="">Select City</option></select></div>
                <div><label class="block text-xs font-semibold text-gray-700 mb-1">Zone</label><select id="pZoneId" onchange="loadAreas(this.value)" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" disabled><option value="">Select Zone</option></select></div>
                <div><label class="block text-xs font-semibold text-gray-700 mb-1">Area</label><select id="pAreaId" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" disabled><option value="">Select Area</option></select></div>
            </div>
            <div id="autoDetectResult" class="mt-2 text-xs hidden"></div>
        </div>

        
        <!-- ▸ Courier Tracking Card (Pathao + Steadfast + Any courier) -->
        <?php 
        // Resolve consignment ID from ALL possible columns
        $__cid = $order['courier_consignment_id'] ?? '';
        $__pathaoCid = $order['pathao_consignment_id'] ?? '';
        $__tid = $order['courier_tracking_id'] ?? '';
        $__courierName = strtolower($order['courier_name'] ?: ($order['shipping_method'] ?? ''));
        
        // Normalize: pick best CID available
        if (empty($__cid) && !empty($__pathaoCid)) $__cid = $__pathaoCid;
        if (empty($__tid)) $__tid = $__cid;
        
        $__hasCid = !empty($__cid);
        $__isSf = strpos($__courierName, 'steadfast') !== false;
        $__isPathao = strpos($__courierName, 'pathao') !== false;
        $__canUpload = in_array($order['order_status'], ['processing','confirmed','ready_to_ship','approved']);
        $__isShipped = in_array($order['order_status'], ['shipped','on_hold','pending_return','pending_cancel','partial_delivered','delivered']);
        
        // Build portal link
        if ($__isSf && $__hasCid) {
            $__portalLink = 'https://portal.steadfast.com.bd/find-consignment?consignment_id=' . urlencode($__cid);
            $__portalName = 'Steadfast Portal';
            $__trackLink = 'https://steadfast.com.bd/t/' . urlencode($__tid);
        } elseif ($__isPathao && $__hasCid) {
            $__portalLink = 'https://merchant.pathao.com/courier/consignments/' . urlencode($__cid);
            $__portalName = 'Pathao Portal';
            $__trackLink = '';
        } else {
            $__portalLink = '';
            $__portalName = '';
            $__trackLink = '';
        }
        ?>
        <div id="sf-tracking-card" class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-gray-800">📦 Courier Tracking</h4>
                <div class="flex items-center gap-2">
                    <?php if ($__hasCid): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-200">✓ Uploaded</span>
                    <button type="button" onclick="courierSync(<?= $order['id'] ?>)" id="syncBtn" class="text-xs px-2 py-1 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">🔄 Sync</button>
                    <?php elseif ($__canUpload): ?>
                    <button type="button" onclick="uploadToCourier(<?= $order['id'] ?>)" id="sfUploadBtn" class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">🚀 Upload to Steadfast</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($__hasCid): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div>
                    <span class="text-gray-500 block">Courier</span>
                    <div class="font-semibold text-gray-800"><?= e(!empty($order['courier_name']) ? $order['courier_name'] : (!empty($order['shipping_method']) ? $order['shipping_method'] : 'Unknown')) ?></div>
                </div>
                <div>
                    <span class="text-gray-500 block">Consignment ID</span>
                    <?php if ($__portalLink): ?>
                    <a href="<?= $__portalLink ?>" target="_blank" class="font-mono font-semibold text-blue-600 hover:underline block"><?= e($__cid) ?> ↗</a>
                    <?php else: ?>
                    <div class="font-mono font-semibold text-gray-800"><?= e($__cid) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-gray-500 block">Tracking Code</span>
                    <div class="font-mono font-semibold text-gray-800"><?= e($__tid) ?></div>
                </div>
                <div>
                    <span class="text-gray-500 block">Courier Status</span>
                    <?php 
                    $__cStat = $order['courier_status'] ?? 'unknown';
                    $__cStatColor = 'text-gray-600';
                    if (in_array($__cStat, ['delivered','delivered_approval_pending','Delivered','payment_invoice','Payment_Invoice'])) $__cStatColor = 'text-green-600';
                    elseif (in_array($__cStat, ['cancelled','cancelled_approval_pending','Cancelled','pickup_cancelled','pending_cancel'])) $__cStatColor = 'text-red-600';
                    elseif (in_array($__cStat, ['return','Return','Returned','Return_Ongoing','paid_return','exchange','Exchange','pending_return'])) $__cStatColor = 'text-orange-600';
                    elseif (in_array($__cStat, ['hold','Hold','on_hold','delivery_failed','pickup_failed'])) $__cStatColor = 'text-yellow-600';
                    elseif (in_array($__cStat, ['partial_delivery','partial_delivered','Partial_Delivered','partial_delivered_approval_pending'])) $__cStatColor = 'text-cyan-600';
                    elseif (in_array($__cStat, ['in_review','Picked','In_Transit','At_Transit','Delivery_Ongoing','in_transit','at_the_sorting_hub','assigned_for_delivery','received_at_last_mile_hub','pickup','assigned_for_pickup'])) $__cStatColor = 'text-blue-600';
                    elseif (in_array($__cStat, ['order_created','order_updated','pickup_requested'])) $__cStatColor = 'text-indigo-600';
                    ?>
                    <div class="font-semibold <?= $__cStatColor ?>" id="courierStatusVal"><?= e($__cStat) ?></div>
                </div>
            </div>
            
            <!-- Live-fetched data placeholder -->
            <div id="courierLiveData" class="hidden mt-2"></div>
            
            <?php if (!empty($order['courier_tracking_message'])): ?>
            <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-700" id="trackingMsg">📍 <?= e($order['courier_tracking_message']) ?></div>
            <?php else: ?>
            <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-700 hidden" id="trackingMsg"></div>
            <?php endif; ?>
            
            <?php if (!empty($order['courier_delivery_charge']) && floatval($order['courier_delivery_charge']) > 0): ?>
            <div class="mt-2 text-xs text-gray-500" id="courierCharges">Delivery Charge: ৳<?= number_format(floatval($order['courier_delivery_charge'])) ?> | COD: ৳<?= number_format(floatval($order['courier_cod_amount'] ?? 0)) ?></div>
            <?php else: ?>
            <div class="mt-2 text-xs text-gray-500 hidden" id="courierCharges"></div>
            <?php endif; ?>
            
            <?php if (!empty($order['courier_uploaded_at'])): ?>
            <div class="mt-1 text-[10px] text-gray-400">Uploaded: <?= date('d M Y, h:i A', strtotime($order['courier_uploaded_at'])) ?></div>
            <?php endif; ?>
            
            <div class="flex flex-wrap gap-2 mt-3">
                <?php if ($__portalLink): ?>
                <a href="<?= $__portalLink ?>" target="_blank" class="text-xs px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">🔗 <?= $__portalName ?></a>
                <?php endif; ?>
                <?php if ($__trackLink): ?>
                <a href="<?= $__trackLink ?>" target="_blank" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">📱 Customer Track</a>
                <?php endif; ?>
            </div>
            
            <?php elseif ($__isShipped): ?>
            <div class="text-xs text-gray-500">
                <span class="block mb-1">Courier: <b><?= e(!empty($order['courier_name']) ? $order['courier_name'] : (!empty($order['shipping_method']) ? $order['shipping_method'] : 'Unknown')) ?></b></span>
                <span class="text-gray-400">No consignment ID stored yet. Status will auto-update via webhook.</span>
            </div>
            
            <?php elseif ($__canUpload): ?>
            <p class="text-xs text-gray-500">Order ready to upload. Select delivery method above, then click "Upload to Steadfast".</p>
            
            <?php else: ?>
            <p class="text-xs text-gray-400">Courier tracking will appear here after upload.</p>
            <?php endif; ?>
        </div>

        <!-- ▸ Products: Ordered + Add -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Ordered Products -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h4 class="text-sm font-bold text-gray-800">Ordered Products <span id="itemCount" class="bg-blue-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-1"><?= count($items) ?></span></h4>
                </div>
                <div id="orderedItems" class="divide-y divide-gray-100 max-h-[450px] overflow-y-auto">
                    <?php foreach ($items as $idx => $item): ?>
                    <div class="p-3 item-row">
                        <input type="hidden" name="item_product_id[]" value="<?= $item['product_id'] ?>">
                        <input type="hidden" name="item_product_name[]" value="<?= e($item['product_name']) ?>">
                        <input type="hidden" name="item_variant_name[]" value="<?= e($item['variant_name']??'') ?>">
                        <div class="flex gap-3">
                            <div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden shrink-0">
                                <?php if (!empty($item['featured_image'])): ?><img src="<?= imgSrc('products',$item['featured_image']) ?>" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='📦'">
                                <?php else: ?><div class="w-full h-full flex items-center justify-center text-xl">📦</div><?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between gap-1">
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-gray-800"><?= e($item['sku']??'') ?></div>
                                        <div class="text-xs text-gray-600 truncate"><?= e($item['product_name']) ?></div>
                                        <?php if ($item['variant_name']): ?><div class="text-[10px] text-indigo-600"><?= e($item['variant_name']) ?></div><?php endif; ?>
                                        <?php if (!empty($item['customer_upload'])): ?><a href="<?= SITE_URL ?>/uploads/customer-uploads/<?= e($item['customer_upload']) ?>" target="_blank" class="inline-block mt-0.5 px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px]"><i class="fas fa-paperclip mr-0.5"></i>Upload</a><?php endif; ?>
                                    </div>
                                    <button type="button" onclick="removeItem(this)" class="text-red-400 hover:text-red-600 shrink-0 p-0.5"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">৳<?= number_format($item['price']) ?>  Stock: <?= $item['stock_quantity']??'—' ?></div>
                                <div class="flex items-center gap-2 mt-2 text-xs flex-wrap">
                                    <span class="text-gray-500">Qty</span>
                                    <div class="flex items-center"><button type="button" onclick="changeQty(this,-1)" class="w-7 h-7 border border-gray-200 rounded-l text-gray-500 hover:bg-gray-50 font-bold">−</button><input type="number" name="item_qty[]" value="<?= $item['quantity'] ?>" min="1" class="w-10 h-7 border-t border-b border-gray-200 text-center text-sm item-qty" oninput="calcTotals()"><button type="button" onclick="changeQty(this,1)" class="w-7 h-7 border border-gray-200 rounded-r text-gray-500 hover:bg-gray-50 font-bold">+</button></div>
                                    <span class="text-gray-500 ml-1">Price</span>
                                    <input type="number" name="item_price[]" value="<?= $item['price'] ?>" min="0" step="1" class="w-20 h-7 border border-gray-200 rounded text-center text-sm item-price" oninput="calcTotals()">
                                    <span class="text-gray-500 ml-auto">Total</span>
                                    <span class="item-line-total font-bold"><?= number_format($item['subtotal'],2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?><div id="noItemsMsg" class="p-8 text-center text-gray-400"><div class="text-2xl mb-1">📦</div><div class="text-sm">No products yet</div></div><?php endif; ?>
                </div>
            </div>

            <!-- Add Products -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50">
                    <h4 class="text-sm font-bold text-gray-800">Click To Add Products</h4>
                </div>
                <div class="p-3">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div><label class="block text-xs font-semibold text-gray-700 mb-1">Code/sku</label><input type="text" id="searchSku" placeholder="Type to Search.." class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" oninput="searchProducts()"></div>
                        <div><label class="block text-xs font-semibold text-gray-700 mb-1">Name</label><input type="text" id="searchName" placeholder="Type to Search.." class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" oninput="searchProducts()"></div>
                    </div>
                    <div id="productResults" class="divide-y divide-gray-100 max-h-[350px] overflow-y-auto border border-gray-200 rounded-md">
                        <div class="py-4 text-center text-gray-400 text-sm">Type to search products...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attribution text -->
        <?php if ($visitorLog): ?>
        <div class="text-[11px] text-gray-400 leading-relaxed">
            <?php if (!empty($visitorLog['device_type'])): ?>Wc order attribution device type: <?= e(ucfirst($visitorLog['device_type'])) ?><?php endif; ?>
            <?php if (!empty($visitorLog['referrer'])): ?> Wc order attribution referrer: <?= e(mb_strimwidth($visitorLog['referrer'],0,60,'...')) ?><?php endif; ?>
            <?php if (!empty($visitorLog['utm_source'])): ?><br>Wc order attribution session entry: <?= e($visitorLog['utm_source']) ?><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ▸ Totals Card -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">
                <div><label class="block text-sm font-semibold text-gray-800 mb-1">Discount</label><input type="number" name="discount_amount" id="discountInput" value="<?= floatval($order['discount_amount']??0) ?>" min="0" step="1" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" oninput="calcTotals()"></div>
                <div><label class="block text-sm font-semibold text-gray-800 mb-1">Advance</label><input type="number" name="advance_amount" id="advanceInput" value="<?= floatval($order['advance_amount']??0) ?>" min="0" step="1" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" oninput="calcTotals()"></div>
                <div><label class="block text-sm font-semibold text-gray-800 mb-1">Sub Total</label><div id="subtotalDisplay" class="px-3 py-2 border border-gray-200 rounded-md text-sm bg-gray-50"><?= number_format($order['subtotal']) ?></div></div>
                <div><label class="block text-sm font-semibold text-gray-800 mb-1">DeliveryCharge</label><input type="number" name="shipping_cost" id="shippingInput" value="<?= floatval($order['shipping_cost']??0) ?>" min="0" step="1" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm" oninput="calcTotals()"></div>
                <div><label class="block text-sm font-semibold text-emerald-600 mb-1 italic">Grand Total</label><div id="grandTotalDisplay" class="px-3 py-2 border border-emerald-300 rounded-md text-sm bg-emerald-50 font-bold text-emerald-700"><?= number_format($order['total']) ?></div></div>
            </div>
        </div>

        <!-- ▸ Action Button -->
        <div class="pb-2">
            <?php if ($isPending): ?>
            <button type="submit" name="action" value="confirm_order" class="w-full bg-emerald-500 text-white py-3.5 rounded-xl text-base font-bold hover:bg-emerald-600 transition shadow">Create Order (<span id="confirmTotal"><?= number_format($order['total'],2) ?></span>)</button>
            <button type="submit" name="action" value="save_order" class="w-full mt-2 bg-gray-100 text-gray-600 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition">💾 Save Without Confirming</button>
            <?php else: ?>
            <button type="submit" name="action" value="save_order" class="w-full bg-emerald-500 text-white py-3 rounded-xl text-base font-bold hover:bg-emerald-600 transition shadow">💾 Save Changes (৳<span id="saveTotal"><?= number_format($order['total']) ?></span>)</button>
            <?php endif; ?>
        </div>

    </div><!-- END LEFT -->

    <!-- ────────── RIGHT SIDEBAR ────────── -->
    <div class="w-full lg:w-[280px] xl:w-[300px] shrink-0 space-y-4">

        <!-- Order Summary -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-800">Order Summary</span>
                <span class="text-[10px] text-gray-400">#<?= e($order['order_number']) ?></span>
            </div>
            <div class="p-4 text-xs space-y-1.5">
                <div class="flex justify-between"><span class="text-gray-500">Date</span><span><?= date('M d, Y, h:i A', strtotime($order['created_at'])) ?></span></div>
                <div class="flex justify-between items-center"><span class="text-gray-500">Status</span><span class="font-bold px-2 py-0.5 rounded text-[10px] <?= getOrderStatusBadge($order['order_status']) ?>"><?= strtoupper(getOrderStatusLabel($order['order_status'])) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Payment</span><span class="uppercase"><?= e($order['payment_method']) ?></span></div>
                <div class="flex justify-between items-center"><span class="text-gray-500">Source</span><span><?php $ch=$order['channel']??'website'; if($ch==='facebook') echo '<i class="fab fa-facebook text-blue-600 mr-0.5"></i>Facebook'; elseif($ch==='whatsapp') echo '<i class="fab fa-whatsapp text-green-600 mr-0.5"></i>WhatsApp'; else echo ucfirst($ch==='website'?'Web':$ch); ?></span></div>
                <div class="border-t border-gray-100 my-1.5"></div>
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span><?= number_format($order['subtotal']) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Delivery</span><span><?= number_format($order['shipping_cost']) ?></span></div>
                <?php if (floatval($order['discount_amount']??0)>0): ?><div class="flex justify-between"><span class="text-gray-500">Discount</span><span class="text-red-600">-<?= number_format($order['discount_amount']) ?></span></div><?php endif; ?>
                <?php if (floatval($order['advance_amount']??0)>0): ?><div class="flex justify-between"><span class="text-gray-500">Advance</span><span class="text-blue-600"><?= number_format($order['advance_amount']) ?></span></div><?php endif; ?>
                <?php $scUsed=floatval($order['store_credit_used']??0); if($scUsed>0): ?><div class="flex justify-between text-yellow-600"><span><i class="fas fa-coins mr-0.5"></i>Credit</span><span>-৳<?= number_format($scUsed) ?></span></div><?php endif; ?>
                <div class="flex justify-between font-bold text-sm pt-1 border-t border-gray-100"><span>Total</span><span><?= number_format($order['total']) ?></span></div>
                <?php
                $creditRate = floatval(getSetting('store_credit_conversion_rate','0.75')?:0.75);
                $creditEarned = $db->fetch("SELECT amount FROM store_credit_transactions WHERE reference_type='order' AND reference_id=? AND type='earn'", [$order['id']]);
                if ($creditEarned): ?><div class="flex justify-between text-yellow-700 bg-yellow-50 rounded px-2 py-1 mt-1 text-[10px]"><span><i class="fas fa-coins mr-0.5"></i>Earned</span><span class="font-bold">+<?= number_format($creditEarned['amount']) ?> credits</span></div><?php endif; ?>
            </div>
        </div>

        <!-- IP / Mobile -->
        <div class="bg-white border border-gray-200 rounded-lg p-3 text-xs space-y-1.5">
            <div class="flex items-center justify-between"><span class="text-gray-500">IP: <?= e($order['ip_address']??'N/A') ?></span><?php if($order['ip_address']):?><span class="text-red-500 text-[10px] cursor-pointer">🔒 Block</span><?php endif;?></div>
            <div class="flex items-center justify-between"><span class="text-gray-500">Mobile: <?= e($order['customer_phone']) ?></span><span class="text-red-500 text-[10px] cursor-pointer">🔒 Block</span></div>
        </div>

        <!-- Order Items compact -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-700">Order Items</div>
            <div class="p-3 space-y-2">
                <?php foreach ($items as $it): ?>
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-gray-100 rounded overflow-hidden shrink-0"><?php if(!empty($it['featured_image'])):?><img src="<?= imgSrc('products',$it['featured_image']) ?>" class="w-full h-full object-cover"><?php else:?><div class="w-full h-full flex items-center justify-center text-[10px]">📦</div><?php endif;?></div>
                    <div class="flex-1 min-w-0"><div class="text-[10px] text-blue-600 font-medium truncate"><?= e($it['sku']??'') ?></div><div class="text-[10px] text-gray-500">৳<?= number_format($it['price']) ?></div></div>
                    <span class="text-[10px] text-gray-400 shrink-0"><?= $it['quantity'] ?>x</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Tags -->
        <div class="bg-white border border-gray-200 rounded-lg p-3">
            <div class="text-xs font-semibold text-gray-700 mb-2">Order Tags</div>
            <div class="flex flex-wrap gap-1 mb-1.5"><?php foreach($orderTags as $tg):?><span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= e($tg['tag_name']) ?> <button type="button" onclick="removeTag('<?= e($tg['tag_name']) ?>')" class="text-gray-400 hover:text-red-500 ml-0.5">×</button></span><?php endforeach;?></div>
            <button type="button" onclick="addTagPrompt()" class="text-xs text-gray-500 hover:text-blue-600">+ Add Tag</button>
        </div>

        <!-- Order Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-3 space-y-2">
            <div class="text-xs font-semibold text-gray-700">Order Actions</div>
            <select id="statusSelect" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm">
                <?php foreach(['processing','confirmed','shipped','delivered','pending_return','pending_cancel','partial_delivered','cancelled','returned','on_hold','no_response','good_but_no_response','advance_payment','lost'] as $s): ?>
                <option value="<?= $s ?>" <?= ($order['order_status']===$s||($s==='processing'&&$order['order_status']==='pending'))?'selected':'' ?>><?= getOrderStatusLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex items-center justify-between">
                <button type="button" onclick="updateStatus()" class="px-4 py-1.5 bg-emerald-500 text-white rounded-md text-xs font-semibold hover:bg-emerald-600 transition">Update</button>
                <a href="<?= adminUrl('pages/order-management.php') ?>" class="text-xs text-gray-500 hover:text-gray-700">← Back to List</a>
            </div>
        </div>

        <!-- Note -->
        <div class="bg-white border border-gray-200 rounded-lg p-3">
            <div class="text-xs font-semibold text-gray-700 mb-1.5">Note</div>
            <textarea id="noteText" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm resize-none mb-2" placeholder="Add a note..."><?= e($order['admin_notes']??'') ?></textarea>
            <button type="button" onclick="addNote()" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-200 transition">Add Note</button>
        </div>

        <!-- SMS -->
        <div class="flex gap-2">
            <button type="button" onclick="sendSMS('reminder')" class="flex-1 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 transition">Send Reminder SMS</button>
            <button type="button" onclick="sendSMS('advance')" class="flex-1 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 transition">Send Advance SMS</button>
        </div>

        <!-- Attribution -->
        <?php if ($visitorLog || !empty($order['channel'])): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-3">
            <div class="text-xs font-semibold text-gray-700 mb-1">🔍 Attribution</div>
            <div class="text-[10px] text-gray-400 mb-2">Track where this order came from</div>
            <?php $src=ucfirst($order['channel']??'website'); $isPaid=!empty($visitorLog['utm_medium'])&&$visitorLog['utm_medium']==='paid'; ?>
            <div class="flex items-center gap-1.5 mb-2">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium <?= ($order['channel']??'')==='facebook'?'bg-blue-100 text-blue-700':'bg-gray-100 text-gray-700' ?>"><?php if(($order['channel']??'')==='facebook'):?><i class="fab fa-facebook text-blue-600"></i><?php endif;?> <?= e($src) ?><?php if($isPaid):?> (paid)<?php endif;?></span>
                <?php if(!empty($visitorLog['utm_source'])):?><span class="text-[10px] text-gray-400">utm</span><?php endif;?>
            </div>
            <?php if(!empty($visitorLog['utm_campaign'])):?><div class="text-[10px] text-gray-500 mb-1">🎯 Campaign</div><div class="text-[10px] text-gray-400 pl-3 truncate"><?= e($visitorLog['utm_campaign']) ?></div><?php endif;?>
            <?php if($visitorLog):?>
            <div class="border-t border-gray-100 pt-2 mt-2 space-y-1">
                <div class="text-[10px] font-semibold text-gray-600">Session Info</div>
                <div class="text-[10px] text-gray-500"><i class="fas fa-<?= ($visitorLog['device_type']??'')==='Mobile'?'mobile-alt':'desktop' ?> text-gray-400 mr-1"></i><?= e(ucfirst($visitorLog['device_type']??'Unknown')) ?></div>
                <?php if(!empty($visitorLog['referrer'])):?><div class="text-[10px] text-gray-500"><i class="fas fa-external-link-alt text-gray-400 mr-1"></i><a href="<?= e($visitorLog['referrer']) ?>" target="_blank" class="text-blue-500 hover:underline"><?= e(mb_strimwidth($visitorLog['referrer'],0,35,'...')) ?></a></div><?php endif;?>
                <?php if(!empty($visitorLog['landing_page'])):?><div class="text-[9px] text-gray-400 break-all mt-1">Entry URL: <?= e(mb_strimwidth($visitorLog['landing_page'],0,60,'...')) ?></div><?php endif;?>
            </div>
            <?php endif;?>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-700">Activity Log</div>
            <div class="p-3 max-h-[300px] overflow-y-auto">
                <?php
                $allAct = [];
                foreach ($history as $h) $allAct[] = ['time'=>$h['created_at'],'user'=>$h['changed_by_name']??'System','text'=>'Status → '.getOrderStatusLabel($h['status']).($h['note']?': '.$h['note']:'')];
                foreach ($activityLogs as $al) {
                    $t = match($al['action']){ 'update'=>'Order updated','confirm_order'=>'Order confirmed','mark_fake'=>'Marked as FAKE','update_status'=>$al['new_values']?:'Status changed','view_order'=>'ORDER_VIEWED','add_note'=>'Note: '.($al['new_values']?:''),'send_sms'=>'SMS sent', default=>$al['action'] };
                    $allAct[] = ['time'=>$al['created_at'],'user'=>$al['admin_name']??'System','text'=>$t];
                }
                usort($allAct, fn($a,$b)=>strtotime($b['time'])-strtotime($a['time']));
                $seen=[];
                $allAct = array_filter($allAct, function($a) use(&$seen){ $k=substr($a['time'],0,16).$a['text']; if(isset($seen[$k]))return false; $seen[$k]=true; return true; });
                if(empty($allAct)):?><div class="text-xs text-gray-400 text-center py-2">No activity yet</div>
                <?php else: foreach(array_slice($allAct,0,20) as $a):?>
                <div class="py-1.5 border-b border-dashed border-gray-100 last:border-0">
                    <div class="flex items-center gap-1"><span class="text-[10px] text-gray-400"><?= timeAgo($a['time']) ?></span><span class="text-[10px] bg-blue-50 text-blue-600 px-1 py-0.5 rounded font-medium"><?= e($a['user']) ?></span></div>
                    <div class="text-[11px] text-gray-600 mt-0.5"><?= e($a['text']) ?></div>
                </div>
                <?php endforeach; endif;?>
            </div>
        </div>

    </div><!-- END RIGHT -->

</div><!-- END FLEX -->
</form>

<!-- Hidden Forms -->
<form id="fakeForm" method="POST"><input type="hidden" name="action" value="mark_fake"></form>
<form id="statusForm" method="POST"><input type="hidden" name="action" value="update_status"><input type="hidden" name="status" id="statusVal"><input type="hidden" name="notes" id="statusNote"></form>
<form id="noteForm" method="POST"><input type="hidden" name="action" value="add_note"><input type="hidden" name="note_text" id="noteVal"></form>

<script>
const PAPI='<?= SITE_URL ?>/api/pathao-api.php',SAPI='<?= SITE_URL ?>/api/search.php?admin=1';
let searchTimer=null;

function fetchCourierData(){
    const phone='<?= e(preg_replace('/[^0-9]/','', $order['customer_phone'])) ?>';
    if(phone.length<10)return;
    const btn=document.getElementById('fillInfoBtn');
    btn.textContent='Fetching...';btn.disabled=true;
    fetch('<?= adminUrl("api/courier-lookup.php") ?>?phone='+phone).then(r=>r.json()).then(d=>{
        if(d.error){btn.textContent='❌ '+d.error;return;}
        updCard('overall',d.overall);
        ['Pathao','RedX','Steadfast'].forEach(n=>{if(d.couriers?.[n])updCard(n.toLowerCase(),d.couriers[n]);});
        const orc=document.getElementById('card-ourrecord'),or=d.our_record;
        if(orc&&or){
            orc.querySelector('[data-custtype]').textContent=or.is_new?'New Customer':'Returning Customer';
            orc.querySelector('[data-custtype]').style.color=or.is_new?'#2563eb':'#16a34a';
            orc.querySelector('[data-webcancel]').textContent='Web Order Cancel: '+or.web_cancels;
            orc.querySelector('[data-totalspent]').textContent='Total Spent: ৳'+Number(or.total_spent).toLocaleString();
        }
        btn.textContent='✅ Updated';btn.className='w-full mt-2 py-1.5 bg-green-100 text-green-700 rounded-md text-xs font-semibold';
    }).catch(()=>{btn.textContent='❌ Error';});
}
function updCard(id,data){
    const c=document.getElementById('card-'+id);if(!c||!data)return;
    const rate=data.rate,rc=rate>=70?'#16a34a':rate>=40?'#ca8a04':'#dc2626';
    const re=c.querySelector('[data-rate]');re.textContent='Success Rate: '+rate+'%';re.style.color=rc;
    c.querySelector('[data-total]').textContent='Total: '+data.total;
    c.querySelector('[data-success]').textContent='Success: '+data.success;
    c.querySelector('[data-cancelled]').textContent='Cancelled: '+data.cancelled;
    c.querySelector('[data-bar]').style.width=Math.min(100,rate)+'%';
    if(data.api_checked>0)re.innerHTML+=' <span style="color:#22c55e;font-size:9px">✓API</span>';
}
<?php if(!empty($order['customer_phone'])):?>
document.addEventListener('DOMContentLoaded',()=>setTimeout(fetchCourierData,500));
<?php endif;?>

function searchProducts(){
    clearTimeout(searchTimer);
    const q=document.getElementById('searchSku').value.trim()||document.getElementById('searchName').value.trim();
    if(q.length<2){document.getElementById('productResults').innerHTML='<div class="py-4 text-center text-gray-400 text-sm">Type to search...</div>';return;}
    searchTimer=setTimeout(async()=>{
        try{
            const r=await(await fetch(SAPI+'&q='+encodeURIComponent(q))).json();
            if(!r.results?.length){document.getElementById('productResults').innerHTML='<div class="py-3 text-center text-gray-400 text-sm">No products found</div>';return;}
            let h='';
            r.results.forEach(p=>{
                h+=`<div class="flex items-center gap-3 p-2.5 hover:bg-blue-50 cursor-pointer transition" onclick="addProduct(${p.id},'${esc(p.name)}',${p.price},'${esc(p.image)}','${esc(p.sku||'')}')">
                    <img src="${p.image}" class="w-12 h-12 rounded object-cover border border-gray-200" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 40 40%22><text y=%22.9em%22 font-size=%2230%22>📦</text></svg>'">
                    <div class="flex-1 min-w-0"><div class="text-sm font-medium text-gray-800 truncate">${esc(p.name)}</div><div class="text-[10px] text-blue-600 font-bold">${p.sku?'SKU: '+esc(p.sku):''}</div><div class="text-[10px] text-gray-500">Price: ৳${p.price.toLocaleString()} · Stock: ${p.stock_quantity??0}</div></div>
                    <span class="text-yellow-400 text-lg shrink-0">★</span></div>`;
            });
            document.getElementById('productResults').innerHTML=h;
        }catch(e){}
    },300);
}
function addProduct(id,name,price,image,sku){
    const c=document.getElementById('orderedItems'),n=document.getElementById('noItemsMsg');if(n)n.remove();
    const d=document.createElement('div');d.className='p-3 item-row border-t border-gray-100';
    d.innerHTML=`<input type="hidden" name="item_product_id[]" value="${id}"><input type="hidden" name="item_product_name[]" value="${esc(name)}"><input type="hidden" name="item_variant_name[]" value="">
        <div class="flex gap-3"><div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden shrink-0"><img src="${image}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='📦'"></div>
        <div class="flex-1 min-w-0"><div class="flex justify-between gap-1"><div class="min-w-0"><div class="text-sm font-bold text-gray-800">${sku?esc(sku):''}</div><div class="text-xs text-gray-600 truncate">${esc(name)}</div></div><button type="button" onclick="removeItem(this)" class="text-red-400 hover:text-red-600 shrink-0 p-0.5"><i class="fas fa-trash text-xs"></i></button></div>
        <div class="text-[11px] text-gray-400 mt-0.5">৳${price.toLocaleString()}</div>
        <div class="flex items-center gap-2 mt-2 text-xs flex-wrap"><span class="text-gray-500">Qty</span><div class="flex items-center"><button type="button" onclick="changeQty(this,-1)" class="w-7 h-7 border border-gray-200 rounded-l text-gray-500 hover:bg-gray-50 font-bold">−</button><input type="number" name="item_qty[]" value="1" min="1" class="w-10 h-7 border-t border-b border-gray-200 text-center text-sm item-qty" oninput="calcTotals()"><button type="button" onclick="changeQty(this,1)" class="w-7 h-7 border border-gray-200 rounded-r text-gray-500 hover:bg-gray-50 font-bold">+</button></div><span class="text-gray-500 ml-1">Price</span><input type="number" name="item_price[]" value="${price}" min="0" step="1" class="w-20 h-7 border border-gray-200 rounded text-center text-sm item-price" oninput="calcTotals()"><span class="text-gray-500 ml-auto">Total</span><span class="item-line-total font-bold">${price.toFixed(2)}</span></div></div></div>`;
    c.appendChild(d);calcTotals();
}
function removeItem(b){b.closest('.item-row').remove();calcTotals();}
function changeQty(b,d){const i=b.closest('.item-row').querySelector('.item-qty');i.value=Math.max(1,parseInt(i.value||1)+d);calcTotals();}
function calcTotals(){
    let sub=0,cnt=0;
    document.querySelectorAll('.item-row').forEach(r=>{
        const q=parseInt(r.querySelector('.item-qty')?.value||1),p=parseFloat(r.querySelector('.item-price')?.value||0),l=q*p;
        const lt=r.querySelector('.item-line-total');if(lt)lt.textContent=l.toFixed(2);sub+=l;cnt++;
    });
    const disc=parseFloat(document.getElementById('discountInput').value||0),ship=parseFloat(document.getElementById('shippingInput').value||0),grand=sub+ship-disc;
    document.getElementById('subtotalDisplay').textContent=sub.toLocaleString();
    document.getElementById('grandTotalDisplay').textContent=grand.toLocaleString();
    document.getElementById('itemCount').textContent=cnt;
    const ct=document.getElementById('confirmTotal');if(ct)ct.textContent=grand.toFixed(2);
    const st=document.getElementById('saveTotal');if(st)st.textContent=grand.toLocaleString();
}

function updateStatus(){const s=document.getElementById('statusSelect').value,n=prompt('Note (optional):')||'';document.getElementById('statusVal').value=s;document.getElementById('statusNote').value=n;document.getElementById('statusForm').submit();}
function addNote(){const t=document.getElementById('noteText').value.trim();if(!t)return;document.getElementById('noteVal').value=t;document.getElementById('noteForm').submit();}
function sendSMS(type){const ph='<?=e($order['customer_phone'])?>';if(!confirm('Send '+type+' SMS to '+ph+'?'))return;fetch('<?=adminUrl("api/actions.php")?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=send_sms&type='+type+'&order_id=<?=$id?>&phone='+encodeURIComponent(ph)}).then(r=>r.json()).then(d=>{alert(d.success?'SMS sent!':(d.error||'Failed'));if(d.success)location.reload();}).catch(e=>alert(e.message));}
function addTagPrompt(){const t=prompt('Tag name:');if(!t)return;fetch('<?=adminUrl("api/actions.php")?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=add_tag&order_id=<?=$id?>&tag='+encodeURIComponent(t)}).then(()=>location.reload());}
function removeTag(t){fetch('<?=adminUrl("api/actions.php")?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=remove_tag&order_id=<?=$id?>&tag='+encodeURIComponent(t)}).then(()=>location.reload());}

async function loadPathaCities(){try{const j=await(await fetch(PAPI+'?action=get_cities')).json();(j.data?.data||j.data||[]).forEach(c=>{const o=document.createElement('option');o.value=c.city_id;o.textContent=c.city_name;document.getElementById('pCityId').appendChild(o);});}catch(e){}}
async function loadZones(cid){const s=document.getElementById('pZoneId');s.innerHTML='<option>Loading...</option>';s.disabled=true;document.getElementById('pAreaId').innerHTML='<option>Select Area</option>';document.getElementById('pAreaId').disabled=true;if(!cid)return;try{const j=await(await fetch(PAPI+'?action=get_zones&city_id='+cid)).json();s.innerHTML='<option value="">Select Zone</option>';(j.data?.data||j.data||[]).forEach(z=>{const o=document.createElement('option');o.value=z.zone_id;o.textContent=z.zone_name;s.appendChild(o);});s.disabled=false;}catch(e){}}
async function loadAreas(zid){const s=document.getElementById('pAreaId');s.innerHTML='<option>Loading...</option>';s.disabled=true;if(!zid)return;try{const j=await(await fetch(PAPI+'?action=get_areas&zone_id='+zid)).json();s.innerHTML='<option value="">Select Area</option>';(j.data?.data||j.data||[]).forEach(a=>{const o=document.createElement('option');o.value=a.area_id;o.textContent=a.area_name;s.appendChild(o);});s.disabled=false;}catch(e){}}
loadPathaCities();

async function autoDetectLocation(){
    const addr='<?=addslashes($order['customer_address']??'')?>'.toLowerCase(),res=document.getElementById('autoDetectResult');
    res.classList.remove('hidden');res.className='mt-2 text-xs bg-yellow-50 p-2 rounded';res.textContent='🔍 Detecting...';
    try{
        const j=await(await fetch(PAPI+'?action=get_cities')).json(),cities=j.data?.data||j.data||[];
        const dkw=['dhaka','ঢাকা','mirpur','uttara','dhanmondi','gulshan','motijheel','banani','mohammadpur','farmgate','badda','rampura','khilgaon'];
        let mc=dkw.some(k=>addr.includes(k))?cities.find(c=>c.city_name.toLowerCase()==='dhaka'):null;
        if(!mc)for(const c of cities)if(addr.includes(c.city_name.toLowerCase())){mc=c;break;}
        if(mc){
            document.getElementById('pCityId').value=mc.city_id;await loadZones(mc.city_id);
            const zj=await(await fetch(PAPI+'?action=get_zones&city_id='+mc.city_id)).json(),mz=(zj.data?.data||zj.data||[]).find(z=>addr.includes(z.zone_name.toLowerCase()));
            if(mz){document.getElementById('pZoneId').value=mz.zone_id;await loadAreas(mz.zone_id);}
            res.className='mt-2 text-xs bg-green-50 text-green-700 p-2 rounded';res.textContent='✅ '+mc.city_name+(mz?' → '+mz.zone_name:' — select zone manually');
        }else{res.className='mt-2 text-xs bg-orange-50 text-orange-700 p-2 rounded';res.textContent='⚠ Could not detect.';}
    }catch(e){res.className='mt-2 text-xs bg-red-50 text-red-700 p-2 rounded';res.textContent=e.message;}
}
function esc(s){return s?s.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;'):''}
calcTotals();
</script>
<script>
/* ── Courier Tracking: Upload / Sync / Auto-fetch ── */
var COURIER_API = '<?= SITE_URL ?>/api/steadfast-actions.php';
var PATHAO_API  = '<?= SITE_URL ?>/api/pathao-api.php';

function uploadToCourier(orderId) {
    if (!confirm('Upload this order to Steadfast?')) return;
    var btn = document.getElementById('sfUploadBtn');
    if(btn){btn.disabled=true;btn.textContent='⏳ Uploading...';}
    fetch(COURIER_API, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'upload_order', order_id:orderId})})
    .then(function(r){return r.json()}).then(function(d) {
        if (d.success) { location.reload(); }
        else { alert('❌ ' + (d.message || d.error || 'Upload failed')); if(btn){btn.disabled=false;btn.textContent='🚀 Upload to Steadfast';} }
    }).catch(function(e) { alert('Error: ' + e.message); if(btn){btn.disabled=false;btn.textContent='🚀 Upload to Steadfast';} });
}

function courierSync(orderId) {
    var btn = document.getElementById('syncBtn');
    if(btn){var orig=btn.textContent; btn.textContent='⏳...';}
    fetch(COURIER_API, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'sync_courier', order_id:orderId})})
    .then(function(r){return r.json()}).then(function(d) {
        if (d.success) { 
            // Update UI inline without reload
            courierUpdateUI(d);
        } else { 
            alert('❌ ' + (d.error || 'Sync failed')); 
        }
        if(btn) btn.textContent='🔄 Sync';
    }).catch(function(e) { alert('Error: ' + e.message); if(btn) btn.textContent='🔄 Sync'; });
}

function courierUpdateUI(d) {
    var el;
    // Update courier status
    if (d.courier_status) {
        el = document.getElementById('courierStatusVal');
        if (el) { el.textContent = d.courier_status; }
    }
    // Update tracking message
    if (d.tracking_message) {
        el = document.getElementById('trackingMsg');
        if (el) { el.textContent = '📍 ' + d.tracking_message; el.classList.remove('hidden'); }
    }
    // Update charges
    if (d.delivery_charge && parseFloat(d.delivery_charge) > 0) {
        el = document.getElementById('courierCharges');
        if (el) { el.textContent = 'Delivery Charge: ৳' + Number(d.delivery_charge).toLocaleString() + ' | COD: ৳' + Number(d.cod_amount||0).toLocaleString(); el.classList.remove('hidden'); }
    }
    // Update live data section
    el = document.getElementById('courierLiveData');
    if (el && d.live_status) {
        el.innerHTML = '<div class="text-[10px] text-gray-500">Live: <b>' + (d.live_status||'') + '</b> — synced just now</div>';
        el.classList.remove('hidden');
    }
}

// ── Auto-fetch courier status on page load ──
<?php if ($__hasCid && $__isShipped): ?>
(function(){
    setTimeout(function(){
        fetch(COURIER_API, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'sync_courier', order_id:<?= intval($order['id']) ?>})})
        .then(function(r){return r.json()}).then(function(d){
            if(d.success) courierUpdateUI(d);
        }).catch(function(){});
    }, 500);
})();
<?php endif; ?>
</script>
<?php include __DIR__ . '/../includes/phone-checker-widget.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
