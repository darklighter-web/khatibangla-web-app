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

<!-- Order Area Analytics -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-gray-700">📊 Top Order Areas (Last 90 Days)</h3><a href="<?= adminUrl('pages/courier.php?tab=area_map') ?>" class="text-sm text-blue-600 hover:underline">View Full Analytics →</a></div>
    <div id="dashAreaStats" class="text-sm text-gray-400">Loading area data...</div>
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
(async function(){try{const res=await fetch('<?= SITE_URL ?>/api/pathao-api.php?action=area_stats&days=90');const json=await res.json();const el=document.getElementById('dashAreaStats');if(json.data&&json.data.length>0){const mx=Math.max(...json.data.map(d=>parseInt(d.total_orders)));el.innerHTML='<div class="grid md:grid-cols-2 gap-x-6 gap-y-2">'+json.data.slice(0,10).map(a=>{const pct=Math.round((a.total_orders/mx)*100);const sp=a.total_orders>0?Math.round((a.delivered/a.total_orders)*100):0;return`<div class="flex items-center gap-3 py-1.5"><span class="w-28 text-sm text-gray-700 font-medium truncate">${a.area_name}</span><div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full rounded-full ${sp>=70?'bg-green-500':sp>=40?'bg-yellow-500':'bg-red-400'}" style="width:${pct}%"></div></div><span class="text-xs text-gray-500 w-8 text-right">${a.total_orders}</span><span class="text-xs font-medium w-10 text-right ${sp>=70?'text-green-600':sp>=40?'text-yellow-600':'text-red-600'}">${sp}%</span></div>`}).join('')+'</div>'}else{el.innerHTML='<p class="text-gray-400 text-center py-4">No area data yet</p>'}}catch(e){document.getElementById('dashAreaStats').innerHTML='<p class="text-gray-400 text-center py-4">Area analytics unavailable</p>'}})();
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
