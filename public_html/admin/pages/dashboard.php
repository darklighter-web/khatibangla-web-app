<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$adminId = getAdminId();

// Determine if user can see admin metrics (super_admin always, or dashboard.view perm)
$canSeeMetrics = isSuperAdmin() || hasPermission('dashboard.view');

// Auto-close stale sessions (active > 16 hours)
try { $db->query("UPDATE employee_sessions SET clock_out = DATE_ADD(clock_in, INTERVAL 8 HOUR), hours_worked = 8, status = 'auto_closed' WHERE status = 'active' AND clock_in < DATE_SUB(NOW(), INTERVAL 16 HOUR)"); } catch (\Throwable $e) {}

// ── Employee Session Data (ALL users see their own) ──
$activeSession = null;
$todaySessions = [];
$todayHours = 0;
try {
    $activeSession = $db->fetch("SELECT * FROM employee_sessions WHERE admin_user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$adminId]);
    $todaySessions = $db->fetchAll("SELECT * FROM employee_sessions WHERE admin_user_id = ? AND DATE(clock_in) = CURDATE() ORDER BY clock_in", [$adminId]);
    foreach ($todaySessions as $s) {
        $todayHours += ($s['status'] === 'active') ? (time() - strtotime($s['clock_in'])) / 3600 : floatval($s['hours_worked']);
    }
} catch (\Throwable $e) {}

// Today's activities for this user
$todayActivities = [];
try { $todayActivities = $db->fetchAll("SELECT action, entity_type, entity_id, created_at FROM activity_logs WHERE admin_user_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 30", [$adminId]); } catch (\Throwable $e) {}

// ── Team Attendance (admin/super only) ──
$teamAttendance = [];
$teamLeaves = [];
if ($canSeeMetrics) {
    try {
        $teamAttendance = $db->fetchAll(
            "SELECT au.id, au.full_name, au.avatar, ar.role_name,
                es.clock_in, es.status as session_status,
                (SELECT SUM(CASE WHEN s2.status='active' THEN TIMESTAMPDIFF(SECOND, s2.clock_in, NOW())/3600 ELSE s2.hours_worked END) FROM employee_sessions s2 WHERE s2.admin_user_id = au.id AND DATE(s2.clock_in) = CURDATE()) as today_hours,
                (SELECT COUNT(*) FROM activity_logs al WHERE al.admin_user_id = au.id AND DATE(al.created_at) = CURDATE()) as today_actions
             FROM admin_users au
             LEFT JOIN admin_roles ar ON ar.id = au.role_id
             LEFT JOIN employee_sessions es ON es.admin_user_id = au.id AND es.status = 'active'
             WHERE au.is_active = 1 ORDER BY es.status DESC, au.full_name"
        );
    } catch (\Throwable $e) {}
    try { $teamLeaves = $db->fetchAll("SELECT el.*, au.full_name FROM employee_leaves el JOIN admin_users au ON au.id = el.admin_user_id WHERE el.leave_date >= CURDATE() ORDER BY el.leave_date LIMIT 10"); } catch (\Throwable $e) {}
}

// Action label helper
function actionLabel($action) {
    $map = [
        'login'=>'🔑 লগইন','logout'=>'🚪 লগআউট','clock_in'=>'⏰ ক্লক ইন','clock_out'=>'⏰ ক্লক আউট',
        'order_created'=>'📦 অর্ডার তৈরি','order_status_changed'=>'🔄 স্ট্যাটাস পরিবর্তন',
        'product_created'=>'➕ পণ্য তৈরি','product_updated'=>'✏️ পণ্য আপডেট',
        'employee_saved'=>'👤 কর্মী সেভ','role_saved'=>'🛡️ রোল সেভ',
    ];
    return $map[$action] ?? '📝 ' . str_replace('_', ' ', $action);
}
?>

<?php if (!isSuperAdmin()): ?>
<!-- ═══════════════════════════════════════════════ -->
<!-- EMPLOYEE SESSION PANEL (non-super-admin users) -->
<!-- ═══════════════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-1">🕐 আজকের সেশন</h3>
            <p class="text-xs text-gray-400"><?= date('l, d F Y') ?></p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($activeSession): ?>
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span></span>
                    <span class="text-sm text-green-700 font-medium">ক্লক ইন: <?= date('h:i A', strtotime($activeSession['clock_in'])) ?></span>
                </div>
                <span id="liveTimer" class="text-lg font-bold text-green-800 font-mono" data-start="<?= strtotime($activeSession['clock_in']) ?>">00:00:00</span>
                <button onclick="clockOut()" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-bold hover:bg-red-600 transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg> ক্লক আউট
                </button>
            <?php else: ?>
                <span class="text-sm text-gray-500">সেশন চলছে না</span>
                <button onclick="clockIn()" class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-bold hover:bg-green-600 transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg> ক্লক ইন
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($todaySessions)): ?>
    <div class="mt-4 pt-4 border-t">
        <div class="grid grid-cols-3 gap-4 text-center mb-3">
            <div class="bg-blue-50 rounded-lg p-3"><p class="text-xl font-bold text-blue-700"><?= count($todaySessions) ?></p><p class="text-[10px] text-blue-500 uppercase">Sessions</p></div>
            <div class="bg-green-50 rounded-lg p-3"><p class="text-xl font-bold text-green-700"><?= number_format($todayHours, 1) ?>h</p><p class="text-[10px] text-green-500 uppercase">Hours</p></div>
            <div class="bg-purple-50 rounded-lg p-3"><p class="text-xl font-bold text-purple-700"><?= count($todayActivities) ?></p><p class="text-[10px] text-purple-500 uppercase">Actions</p></div>
        </div>
        <div class="space-y-1.5 max-h-40 overflow-y-auto">
            <?php foreach ($todaySessions as $s):
                $isAct = $s['status'] === 'active';
                $elapsed = $isAct ? (time() - strtotime($s['clock_in'])) / 3600 : floatval($s['hours_worked']);
            ?>
            <div class="flex items-center justify-between text-xs bg-gray-50 rounded-lg px-3 py-2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full <?= $isAct ? 'bg-green-500 animate-pulse' : ($s['status']==='auto_closed' ? 'bg-yellow-500' : 'bg-gray-400') ?>"></span>
                    <span class="text-gray-600"><?= date('h:i A', strtotime($s['clock_in'])) ?></span>
                    <span class="text-gray-400">→</span>
                    <span class="text-gray-600"><?= $s['clock_out'] ? date('h:i A', strtotime($s['clock_out'])) : 'চলমান...' ?></span>
                </div>
                <span class="font-medium <?= $isAct ? 'text-green-600' : 'text-gray-500' ?>"><?= number_format($elapsed, 1) ?>h</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($todayActivities)): ?>
    <details class="mt-3">
        <summary class="text-xs text-blue-600 cursor-pointer hover:text-blue-800 font-medium">আজকের কার্যক্রম দেখুন (<?= count($todayActivities) ?>)</summary>
        <div class="mt-2 space-y-1 max-h-48 overflow-y-auto">
            <?php foreach ($todayActivities as $act): ?>
            <div class="flex items-center justify-between text-[11px] px-2 py-1.5 rounded bg-gray-50">
                <span class="text-gray-700"><?= actionLabel($act['action']) ?><?= $act['entity_type'] ? ' <span class="text-gray-400">(' . e($act['entity_type']) . ($act['entity_id'] ? ' #' . $act['entity_id'] : '') . ')</span>' : '' ?></span>
                <span class="text-gray-400"><?= date('h:i A', strtotime($act['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>


<?php if ($canSeeMetrics): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ADMIN/SUPER ADMIN METRICS -->
<!-- ═══════════════════════════════════════════ -->
<?php
$recentOrders = getRecentOrders(10);
$chartData = $db->fetchAll("SELECT DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(total),0) as revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND order_status NOT IN ('cancelled','returned') GROUP BY DATE(created_at) ORDER BY date");
$topProducts = $db->fetchAll("SELECT p.name, p.sales_count FROM products p WHERE p.is_active = 1 ORDER BY p.sales_count DESC LIMIT 5");
$totalDelivered = $db->count('orders', "order_status = 'delivered'");
$totalShipped = $db->count('orders', "order_status IN ('shipped','delivered','returned')");
$deliveryRate = $totalShipped > 0 ? round(($totalDelivered / $totalShipped) * 100, 1) : 0;
$visitorStats = ['today'=>0,'today_unique'=>0,'today_orders'=>0,'conv_rate'=>0,'mobile_pct'=>0];
try {
    $vs = $db->fetch("SELECT COUNT(*) as v, COUNT(DISTINCT device_ip) as u, SUM(order_placed) as o FROM visitor_logs WHERE DATE(created_at) = CURDATE()");
    $visitorStats['today']=$vs['v']??0; $visitorStats['today_unique']=$vs['u']??0; $visitorStats['today_orders']=$vs['o']??0;
    $visitorStats['conv_rate']=$visitorStats['today']>0?round(($visitorStats['today_orders']/$visitorStats['today'])*100,1):0;
    $mob=$db->fetch("SELECT COUNT(*) as m FROM visitor_logs WHERE DATE(created_at) = CURDATE() AND device_type = 'mobile'");
    $visitorStats['mobile_pct']=$visitorStats['today']>0?round(($mob['m']/$visitorStats['today'])*100):0;
} catch (Exception $e) {}
$abandonedStats = ['today'=>0,'week'=>0,'lost_value'=>0];
try {
    $abandonedStats['today']=$db->fetch("SELECT COUNT(*) as c FROM incomplete_orders WHERE recovered = 0 AND DATE(created_at) = CURDATE()")['c']??0;
    $aw=$db->fetch("SELECT COUNT(*) as c, SUM(cart_total) as v FROM incomplete_orders WHERE recovered = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $abandonedStats['week']=$aw['c']??0; $abandonedStats['lost_value']=$aw['v']??0;
} catch (Exception $e) {}
?>

<!-- Team Attendance Overview -->
<?php if (!empty($teamAttendance)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700">👥 টিম উপস্থিতি — আজ</h3>
        <a href="<?= adminUrl('pages/employees.php?tab=performance') ?>" class="text-xs text-blue-600 hover:underline">Full Report →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($teamAttendance as $ta):
            $isOnline = !empty($ta['session_status']) && $ta['session_status'] === 'active';
            $hrs = round(floatval($ta['today_hours'] ?? 0), 1);
            $onLeave = false;
            foreach ($teamLeaves as $lv) { if ($lv['admin_user_id'] == $ta['id'] && $lv['leave_date'] == date('Y-m-d')) { $onLeave = true; break; } }
        ?>
        <div class="flex items-center gap-3 p-3 rounded-lg <?= $onLeave ? 'bg-orange-50 border border-orange-200' : ($isOnline ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-100') ?>">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-sm font-bold"><?= strtoupper(substr($ta['full_name'] ?? '?', 0, 1)) ?></div>
                <?php if ($isOnline): ?><span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                <?php elseif ($onLeave): ?><span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-orange-500 border-2 border-white rounded-full"></span><?php endif; ?>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-gray-800 truncate"><?= e($ta['full_name']) ?></p>
                <p class="text-[10px] <?= $onLeave ? 'text-orange-600' : ($isOnline ? 'text-green-600' : 'text-gray-400') ?>">
                    <?= $onLeave ? '🏖️ ছুটিতে' : ($isOnline ? '🟢 অনলাইন · '.$hrs.'h' : ($hrs > 0 ? '⏸️ '.$hrs.'h done' : '⚫ অফলাইন')) ?>
                </p>
                <?php if (intval($ta['today_actions']) > 0): ?><p class="text-[10px] text-gray-400"><?= $ta['today_actions'] ?> actions</p><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($teamLeaves)): ?>
    <div class="mt-3 pt-3 border-t">
        <p class="text-xs text-gray-500 mb-2">📅 আসন্ন ছুটি:</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($teamLeaves as $lv): ?>
            <span class="inline-flex items-center gap-1 text-[11px] bg-orange-50 text-orange-700 px-2 py-1 rounded-full border border-orange-200">
                <?= e($lv['full_name']) ?> — <?= date('d M', strtotime($lv['leave_date'])) ?>
                (<?= ucfirst($lv['leave_type'] ?? 'casual') ?>)
                <?php if (isSuperAdmin()): ?><button onclick="removeLeave(<?= $lv['id'] ?>)" class="ml-0.5 text-orange-400 hover:text-red-500 font-bold">×</button><?php endif; ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isSuperAdmin()): ?>
    <div class="mt-3 pt-3 border-t flex flex-wrap items-center gap-2">
        <select id="leaveUser" class="text-xs border rounded-lg px-2 py-1.5">
            <?php foreach ($teamAttendance as $ta): ?><option value="<?= $ta['id'] ?>"><?= e($ta['full_name']) ?></option><?php endforeach; ?>
        </select>
        <input type="date" id="leaveDate" value="<?= date('Y-m-d') ?>" class="text-xs border rounded-lg px-2 py-1.5">
        <select id="leaveType" class="text-xs border rounded-lg px-2 py-1.5">
            <option value="casual">Casual</option><option value="sick">Sick</option><option value="annual">Annual</option><option value="other">Other</option>
        </select>
        <input type="text" id="leaveReason" placeholder="কারণ (ঐচ্ছিক)" class="text-xs border rounded-lg px-2 py-1.5 w-40">
        <button onclick="markLeave()" class="text-xs bg-orange-500 text-white px-3 py-1.5 rounded-lg hover:bg-orange-600 font-medium">📅 ছুটি দিন</button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $statCards = [
        ['Today Orders', $stats['today_orders'], '৳'.number_format($stats['today_revenue']), 'bg-blue-500', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>'],
        ['Pending', $stats['pending_orders'], 'Needs attention', 'bg-yellow-500', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        ['Confirmed', $stats['confirmed_orders'], 'Ready to process', 'bg-indigo-500', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        ['Month Revenue', '৳'.number_format($stats['month_revenue']), date('F Y'), 'bg-green-500', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 <?= $sc[3] ?> rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $sc[4] ?></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $sc[1] ?></p>
        <p class="text-xs text-gray-500 mt-1"><?= $sc[0] ?> · <?= $sc[2] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Order Status Pipeline -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Order Pipeline</h3>
    <div class="grid grid-cols-4 md:grid-cols-8 gap-3 text-center">
        <?php
        $pipeline = [
            ['Pending',$stats['pending_orders'],'text-yellow-600 bg-yellow-50'],
            ['Confirmed',$stats['confirmed_orders'],'text-blue-600 bg-blue-50'],
            ['Processing',$stats['processing_orders'],'text-indigo-600 bg-indigo-50'],
            ['Shipped',$stats['shipped_orders'],'text-purple-600 bg-purple-50'],
            ['Delivered',$stats['delivered_orders'],'text-green-600 bg-green-50'],
            ['Cancelled',$stats['cancelled_orders'],'text-red-600 bg-red-50'],
            ['Returned',$stats['returned_orders'],'text-orange-600 bg-orange-50'],
            ['Fake',$stats['fake_orders'],'text-gray-600 bg-gray-50'],
        ];
        foreach ($pipeline as $p): ?>
        <div class="<?= $p[2] ?> rounded-lg p-3"><p class="text-xl font-bold"><?= $p[1] ?></p><p class="text-xs mt-1"><?= $p[0] ?></p></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Last 7 Days Sales</h3>
        <canvas id="salesChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Delivery Success Meter</h3>
        <div class="flex items-center justify-center py-4">
            <div class="relative w-40 h-40">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="42" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                    <circle cx="50" cy="50" r="42" fill="none" stroke="<?= $deliveryRate >= 80 ? '#10b981' : ($deliveryRate >= 50 ? '#f59e0b' : '#ef4444') ?>" stroke-width="10" stroke-dasharray="<?= 2.64 * $deliveryRate ?> 264" stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-bold text-gray-800"><?= $deliveryRate ?>%</span>
                    <span class="text-xs text-gray-500">Success Rate</span>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-4 text-center text-sm">
            <div><p class="font-semibold text-green-600"><?= $totalDelivered ?></p><p class="text-xs text-gray-500">Delivered</p></div>
            <div><p class="font-semibold text-purple-600"><?= $totalShipped ?></p><p class="text-xs text-gray-500">Shipped</p></div>
            <div><p class="font-semibold text-orange-600"><?= $stats['returned_orders'] ?></p><p class="text-xs text-gray-500">Returned</p></div>
        </div>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-gray-700">👁 Today's Visitors</h3><a href="<?= adminUrl('pages/visitors.php') ?>" class="text-xs text-blue-600 hover:underline">Details →</a></div>
        <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Total Visits</span><span class="font-semibold text-blue-600"><?= number_format($visitorStats['today']) ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Unique IPs</span><span class="font-semibold"><?= number_format($visitorStats['today_unique']) ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Conversion</span><span class="font-semibold text-green-600"><?= $visitorStats['conv_rate'] ?>%</span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Mobile</span><span class="font-semibold"><?= $visitorStats['mobile_pct'] ?>% 📱</span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-gray-700">🛒 Abandoned Carts</h3><a href="<?= adminUrl('pages/incomplete-orders.php') ?>" class="text-xs text-red-600 hover:underline">View All →</a></div>
        <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Today</span><span class="font-semibold text-red-600"><?= $abandonedStats['today'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">This Week</span><span class="font-semibold text-orange-600"><?= $abandonedStats['week'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-red-600">Lost Revenue</span><span class="font-semibold text-red-600">৳<?= number_format($abandonedStats['lost_value']) ?></span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Inventory Alerts</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Total Products</span><span class="font-semibold"><?= $stats['total_products'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-red-600">Low Stock</span><span class="font-semibold text-red-600"><?= $stats['low_stock'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Total Customers</span><span class="font-semibold"><?= $stats['total_customers'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Blocked</span><span class="font-semibold text-red-600"><?= $stats['blocked_customers'] ?></span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Fraud Prevention</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Fake Orders</span><span class="font-semibold text-red-600"><?= $stats['fake_orders'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Blocked Customers</span><span class="font-semibold"><?= $stats['blocked_customers'] ?></span></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600">Incomplete Orders</span><span class="font-semibold text-yellow-600"><?= $stats['incomplete_orders'] ?></span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Top Products</h3>
        <div class="space-y-3">
            <?php foreach (array_slice($topProducts, 0, 5) as $tp): ?>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-600 truncate mr-2"><?= e($tp['name']) ?></span><span class="text-xs font-medium text-green-600 whitespace-nowrap"><?= $tp['sales_count'] ?> sold</span></div>
            <?php endforeach; if (empty($topProducts)): ?><p class="text-sm text-gray-400">No sales data yet</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Area Analytics — Visual Dashboard -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center"><span class="text-white text-sm">📊</span></div>
            <div>
                <h3 class="text-sm font-bold text-gray-800">Delivery Area Analytics</h3>
                <p class="text-[11px] text-gray-400" id="areaSubtitle">Loading...</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <select id="dashAreaDays" onchange="loadDashAreaAnalytics()" class="px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 bg-gray-50 focus:ring-1 focus:ring-indigo-300">
                <option value="30">30 days</option>
                <option value="90" selected>90 days</option>
                <option value="180">180 days</option>
                <option value="365">1 year</option>
            </select>
            <a href="<?= adminUrl('pages/courier.php?tab=area_map') ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">Full Report →</a>
        </div>
    </div>

    <!-- Summary Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5" id="areaSummaryCards">
        <div class="bg-gray-50 rounded-lg p-3 animate-pulse"><div class="h-4 bg-gray-200 rounded w-16 mb-2"></div><div class="h-6 bg-gray-200 rounded w-10"></div></div>
        <div class="bg-gray-50 rounded-lg p-3 animate-pulse"><div class="h-4 bg-gray-200 rounded w-16 mb-2"></div><div class="h-6 bg-gray-200 rounded w-10"></div></div>
        <div class="bg-gray-50 rounded-lg p-3 animate-pulse"><div class="h-4 bg-gray-200 rounded w-16 mb-2"></div><div class="h-6 bg-gray-200 rounded w-10"></div></div>
        <div class="bg-gray-50 rounded-lg p-3 animate-pulse"><div class="h-4 bg-gray-200 rounded w-16 mb-2"></div><div class="h-6 bg-gray-200 rounded w-10"></div></div>
    </div>

    <!-- Charts Row -->
    <div class="grid md:grid-cols-5 gap-5 mb-5">
        <!-- Doughnut Chart: Order Distribution -->
        <div class="md:col-span-2 flex flex-col items-center">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-3">Order Distribution</p>
            <div class="relative" style="width:200px;height:200px;">
                <canvas id="areaDoughnutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-bold text-gray-800" id="doughnutCenter">-</span>
                    <span class="text-[10px] text-gray-400">total orders</span>
                </div>
            </div>
        </div>
        <!-- Horizontal Bar Chart: Success Rate by Area -->
        <div class="md:col-span-3">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-3">Success Rate by Area</p>
            <div style="height:220px;">
                <canvas id="areaBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Area Detail List -->
    <div id="dashAreaList" class="text-sm text-gray-400">
        <div class="grid md:grid-cols-2 gap-x-6 gap-y-1">
            <div class="animate-pulse py-2"><div class="h-3 bg-gray-100 rounded w-full"></div></div>
            <div class="animate-pulse py-2"><div class="h-3 bg-gray-100 rounded w-full"></div></div>
            <div class="animate-pulse py-2"><div class="h-3 bg-gray-100 rounded w-3/4"></div></div>
            <div class="animate-pulse py-2"><div class="h-3 bg-gray-100 rounded w-3/4"></div></div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-gray-700">Recent Orders</h3><a href="<?= adminUrl('pages/order-management.php') ?>" class="text-sm text-blue-600 hover:underline">View All</a></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 border-b"><th class="pb-3 font-medium">Order</th><th class="pb-3 font-medium">Customer</th><th class="pb-3 font-medium">Phone</th><th class="pb-3 font-medium">Total</th><th class="pb-3 font-medium">Status</th><th class="pb-3 font-medium">Date</th></tr></thead>
            <tbody class="divide-y">
                <?php foreach ($recentOrders as $order): ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-3"><a href="<?= adminUrl('pages/order-view.php?id=' . $order['id']) ?>" class="text-blue-600 font-medium hover:underline">#<?= e($order['order_number']) ?></a></td>
                    <td class="py-3"><?= e($order['customer_name']) ?></td>
                    <td class="py-3"><?= e($order['customer_phone']) ?></td>
                    <td class="py-3 font-medium">৳<?= number_format($order['total']) ?></td>
                    <td class="py-3"><span class="px-2 py-1 text-xs rounded-full font-medium <?= getOrderStatusBadge($order['order_status']) ?>"><?= getOrderStatusLabel($order['order_status']) ?></span></td>
                    <td class="py-3 text-gray-500"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></td>
                </tr>
                <?php endforeach; if (empty($recentOrders)): ?><tr><td colspan="6" class="py-8 text-center text-gray-400">No orders yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const chartData = <?= json_encode($chartData) ?>;
const labels = chartData.map(d => new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric'}));
new Chart(document.getElementById('salesChart'), {
    type:'bar', data:{labels,datasets:[
        {label:'Revenue (৳)',data:chartData.map(d=>d.revenue),backgroundColor:'rgba(59,130,246,0.5)',borderColor:'rgb(59,130,246)',borderWidth:1,borderRadius:4},
        {label:'Orders',data:chartData.map(d=>d.orders),type:'line',borderColor:'rgb(16,185,129)',borderWidth:2,fill:false,yAxisID:'y1',tension:0.4,pointRadius:4}
    ]}, options:{responsive:true,interaction:{intersect:false,mode:'index'},plugins:{legend:{display:true,position:'bottom'}},scales:{y:{beginAtZero:true,title:{display:true,text:'Revenue'}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Orders'}}}}
});
let _doughnutChart = null, _barChart = null;
const _areaColors = ['#6366f1','#8b5cf6','#a78bfa','#c4b5fd','#818cf8','#7c3aed','#4f46e5','#6d28d9','#5b21b6','#4338ca','#a5b4fc','#c7d2fe','#ddd6fe','#ede9fe','#e0e7ff'];

async function loadDashAreaAnalytics(){
    const days = document.getElementById('dashAreaDays')?.value || 90;
    try {
        const res = await fetch('<?= SITE_URL ?>/api/pathao-api.php?action=area_stats&days='+days);
        const json = await res.json();
        const data = json.data || [];

        // Subtitle
        document.getElementById('areaSubtitle').textContent = data.length > 0
            ? data.length + ' areas · Last ' + days + ' days'
            : 'No area data available';

        if (!data.length) {
            document.getElementById('areaSummaryCards').innerHTML = '<div class="col-span-4 text-center py-6 text-gray-400">No area data yet. Select delivery areas in order pages to populate analytics.</div>';
            document.getElementById('dashAreaList').innerHTML = '';
            if (_doughnutChart) { _doughnutChart.destroy(); _doughnutChart = null; }
            if (_barChart) { _barChart.destroy(); _barChart = null; }
            return;
        }

        // Calculate summary stats
        const totalOrders = data.reduce((s,a) => s + parseInt(a.total_orders), 0);
        const totalDelivered = data.reduce((s,a) => s + parseInt(a.delivered), 0);
        const totalFailed = data.reduce((s,a) => s + parseInt(a.failed), 0);
        const totalRevenue = data.reduce((s,a) => s + parseFloat(a.revenue||0), 0);
        const overallSuccess = totalOrders > 0 ? Math.round((totalDelivered/totalOrders)*100) : 0;

        // Best & worst area (min 2 orders)
        const qualified = data.filter(a => parseInt(a.total_orders) >= 2);
        let bestArea = '-', worstArea = '-', bestRate = 0, worstRate = 100;
        qualified.forEach(a => {
            const rate = parseInt(a.total_orders) > 0 ? (parseInt(a.delivered)/parseInt(a.total_orders))*100 : 0;
            if (rate >= bestRate) { bestRate = rate; bestArea = a.area_name; }
            if (rate <= worstRate) { worstRate = rate; worstArea = a.area_name; }
        });

        // Summary cards
        document.getElementById('areaSummaryCards').innerHTML = `
            <div class="bg-indigo-50 rounded-lg p-3 border border-indigo-100">
                <p class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wider">Total Orders</p>
                <p class="text-xl font-bold text-indigo-700 mt-0.5">${totalOrders.toLocaleString()}</p>
                <p class="text-[10px] text-indigo-400 mt-0.5">৳${Number(totalRevenue).toLocaleString('en',{maximumFractionDigits:0})} revenue</p>
            </div>
            <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                <p class="text-[10px] font-semibold text-green-500 uppercase tracking-wider">Success Rate</p>
                <p class="text-xl font-bold text-green-700 mt-0.5">${overallSuccess}%</p>
                <p class="text-[10px] text-green-400 mt-0.5">${totalDelivered.toLocaleString()} delivered</p>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3 border border-emerald-100">
                <p class="text-[10px] font-semibold text-emerald-500 uppercase tracking-wider">Best Area</p>
                <p class="text-sm font-bold text-emerald-700 mt-0.5 truncate" title="${bestArea}">${bestArea}</p>
                <p class="text-[10px] text-emerald-400 mt-0.5">${Math.round(bestRate)}% success</p>
            </div>
            <div class="bg-red-50 rounded-lg p-3 border border-red-100">
                <p class="text-[10px] font-semibold text-red-400 uppercase tracking-wider">Highest Risk</p>
                <p class="text-sm font-bold text-red-700 mt-0.5 truncate" title="${worstArea}">${worstArea}</p>
                <p class="text-[10px] text-red-400 mt-0.5">${Math.round(worstRate)}% success</p>
            </div>
        `;

        // ── Doughnut Chart ──
        const top8 = data.slice(0, 8);
        const otherOrders = data.slice(8).reduce((s,a) => s + parseInt(a.total_orders), 0);
        const dLabels = top8.map(a => a.area_name);
        const dData = top8.map(a => parseInt(a.total_orders));
        if (otherOrders > 0) { dLabels.push('Others'); dData.push(otherOrders); }

        document.getElementById('doughnutCenter').textContent = totalOrders.toLocaleString();

        if (_doughnutChart) _doughnutChart.destroy();
        _doughnutChart = new Chart(document.getElementById('areaDoughnutChart'), {
            type: 'doughnut',
            data: {
                labels: dLabels,
                datasets: [{
                    data: dData,
                    backgroundColor: _areaColors.slice(0, dLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const pct = totalOrders > 0 ? Math.round((ctx.parsed / totalOrders) * 100) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' orders (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // ── Horizontal Bar Chart: Success vs Fail ──
        const barTop = data.slice(0, 10);
        const barLabels = barTop.map(a => a.area_name.length > 16 ? a.area_name.slice(0,14)+'…' : a.area_name);
        const barDelivered = barTop.map(a => parseInt(a.delivered));
        const barFailed = barTop.map(a => parseInt(a.failed));
        const barPending = barTop.map(a => parseInt(a.total_orders) - parseInt(a.delivered) - parseInt(a.failed));

        if (_barChart) _barChart.destroy();
        _barChart = new Chart(document.getElementById('areaBarChart'), {
            type: 'bar',
            data: {
                labels: barLabels,
                datasets: [
                    { label: 'Delivered', data: barDelivered, backgroundColor: 'rgba(34,197,94,0.75)', borderRadius: 3 },
                    { label: 'In Transit', data: barPending, backgroundColor: 'rgba(234,179,8,0.5)', borderRadius: 3 },
                    { label: 'Failed', data: barFailed, backgroundColor: 'rgba(239,68,68,0.65)', borderRadius: 3 }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, padding: 12, font: { size: 10 } } },
                    tooltip: { mode: 'index' }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 }, autoSkip: false } }
                }
            }
        });

        // ── Area Detail List (below charts) ──
        const mx = Math.max(...data.map(d => parseInt(d.total_orders)));
        document.getElementById('dashAreaList').innerHTML = `
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-3">All Areas</p>
            <div class="grid md:grid-cols-2 gap-x-6 gap-y-1">${data.slice(0,12).map((a,i) => {
                const pct = Math.round((parseInt(a.total_orders)/mx)*100);
                const sp = parseInt(a.total_orders)>0 ? Math.round((parseInt(a.delivered)/parseInt(a.total_orders))*100) : 0;
                const fp = parseInt(a.total_orders)>0 ? Math.round((parseInt(a.failed)/parseInt(a.total_orders))*100) : 0;
                const clr = sp>=70 ? 'green' : sp>=40 ? 'yellow' : 'red';
                return `<div class="flex items-center gap-2 py-1.5 group hover:bg-gray-50 rounded-md px-1 transition">
                    <span class="w-5 text-[10px] text-gray-300 font-mono">${i+1}</span>
                    <span class="w-28 text-xs text-gray-700 font-medium truncate" title="${a.area_name}">${a.area_name}</span>
                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden flex">
                        <div class="h-full bg-green-500 rounded-l-full" style="width:${sp*pct/100}%"></div>
                        <div class="h-full bg-red-400" style="width:${fp*pct/100}%"></div>
                    </div>
                    <span class="text-[10px] text-gray-500 w-8 text-right font-medium">${a.total_orders}</span>
                    <span class="text-[10px] font-bold w-9 text-right text-${clr}-600">${sp}%</span>
                    <span class="text-[10px] text-gray-400 w-16 text-right hidden group-hover:inline">৳${Number(a.revenue||0).toLocaleString('en',{maximumFractionDigits:0})}</span>
                </div>`;
            }).join('')}</div>
        `;

    } catch(e) {
        console.error('Area analytics error:', e);
        document.getElementById('areaSummaryCards').innerHTML = '<div class="col-span-4 text-center py-4 text-gray-400">Area analytics unavailable</div>';
        document.getElementById('dashAreaList').innerHTML = '';
    }
}
loadDashAreaAnalytics();
</script>

<?php else: ?>
<!-- ═══════════════════════════════════════════ -->
<!-- EMPLOYEE LIMITED VIEW (no dashboard.view) -->
<!-- ═══════════════════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
    <div class="w-16 h-16 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-3">
        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-1">স্বাগতম, <?= e(getAdminName()) ?>!</h3>
    <p class="text-sm text-gray-500 mb-4">আপনার অনুমতি অনুযায়ী সাইডবার থেকে কাজের প্যানেলে যান।</p>
    <div class="flex flex-wrap justify-center gap-3">
        <?php if (canViewPage('order-management')): ?><a href="<?= adminUrl('pages/order-management.php') ?>" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg text-sm font-medium hover:bg-blue-100 transition">📦 অর্ডার</a><?php endif; ?>
        <?php if (canViewPage('products')): ?><a href="<?= adminUrl('pages/products.php') ?>" class="px-4 py-2 bg-purple-50 text-purple-600 rounded-lg text-sm font-medium hover:bg-purple-100 transition">📋 পণ্য</a><?php endif; ?>
        <?php if (canViewPage('customers')): ?><a href="<?= adminUrl('pages/customers.php') ?>" class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-100 transition">👥 কাস্টমার</a><?php endif; ?>
        <?php if (canViewPage('tasks')): ?><a href="<?= adminUrl('pages/tasks.php') ?>" class="px-4 py-2 bg-green-50 text-green-600 rounded-lg text-sm font-medium hover:bg-green-100 transition">✅ টাস্ক</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Session JS (always loaded) -->
<script>
const SESSION_API = '<?= SITE_URL ?>/api/employee-session.php';
const timerEl = document.getElementById('liveTimer');
if (timerEl) {
    const startTs = parseInt(timerEl.dataset.start) * 1000;
    setInterval(() => {
        const d = Date.now() - startTs;
        const h = String(Math.floor(d/3600000)).padStart(2,'0');
        const m = String(Math.floor((d%3600000)/60000)).padStart(2,'0');
        const s = String(Math.floor((d%60000)/1000)).padStart(2,'0');
        timerEl.textContent = h+':'+m+':'+s;
    }, 1000);
}
function clockIn() {
    fetch(SESSION_API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=clock_in'})
    .then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message)});
}
function clockOut() {
    const notes = prompt('আজকের কাজের সারসংক্ষেপ (ঐচ্ছিক):') || '';
    fetch(SESSION_API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=clock_out&notes='+encodeURIComponent(notes)})
    .then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message)});
}
function markLeave() {
    const u=document.getElementById('leaveUser').value, d=document.getElementById('leaveDate').value, t=document.getElementById('leaveType').value, r=document.getElementById('leaveReason')?.value||'';
    fetch(SESSION_API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=mark_leave&user_id=${u}&leave_date=${d}&leave_type=${t}&reason=${encodeURIComponent(r)}`})
    .then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message)});
}
function removeLeave(id) {
    if(!confirm('ছুটি মুছে ফেলবেন?'))return;
    fetch(SESSION_API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=remove_leave&id='+id})
    .then(r=>r.json()).then(d=>{if(d.success)location.reload()});
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
