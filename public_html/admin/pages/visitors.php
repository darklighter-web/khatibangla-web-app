<?php
/**
 * Admin - Visitor Analytics (Feature #8)
 */
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Visitor Analytics';
require_once __DIR__ . '/../includes/auth.php';
$db = Database::getInstance();

$period = $_GET['period'] ?? '7d';
$periodMap = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90];
$days = $periodMap[$period] ?? 7;
$dateFilter = $days === 0 ? "DATE(v.created_at) = CURDATE()" : "v.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";

// Summary stats
$visitors_count = 0; $unique_ips = 0; $orders_count = 0; $conversion = 0; $pages_avg = 0;
try {
    $s = $db->fetch("SELECT 
        COUNT(*) as visitors,
        COUNT(DISTINCT device_ip) as unique_ips,
        COALESCE(SUM(order_placed), 0) as orders,
        COALESCE(AVG(pages_viewed), 0) as pages_avg
        FROM visitor_logs v WHERE {$dateFilter}");
    if ($s && is_array($s)) {
        $visitors_count = intval($s['visitors'] ?? 0);
        $unique_ips = intval($s['unique_ips'] ?? 0);
        $orders_count = intval($s['orders'] ?? 0);
        $conversion = $visitors_count > 0 ? round(($orders_count / $visitors_count) * 100, 1) : 0;
        $pages_avg = round(floatval($s['pages_avg'] ?? 0), 1);
    }
} catch (\Throwable $e) {}

// Device breakdown
$deviceStats = [];
try { $deviceStats = $db->fetchAll("SELECT device_type, COUNT(*) as cnt FROM visitor_logs v WHERE {$dateFilter} GROUP BY device_type ORDER BY cnt DESC"); } catch (\Throwable $e) {}

// Browser breakdown
$browserStats = [];
try { $browserStats = $db->fetchAll("SELECT browser, COUNT(*) as cnt FROM visitor_logs v WHERE {$dateFilter} AND browser IS NOT NULL GROUP BY browser ORDER BY cnt DESC LIMIT 8"); } catch (\Throwable $e) {}

// OS breakdown
$osStats = [];
try { $osStats = $db->fetchAll("SELECT os, COUNT(*) as cnt FROM visitor_logs v WHERE {$dateFilter} AND os IS NOT NULL GROUP BY os ORDER BY cnt DESC LIMIT 8"); } catch (\Throwable $e) {}

// Top referrers
$referrers = [];
try { $referrers = $db->fetchAll("SELECT referrer, COUNT(*) as cnt FROM visitor_logs v WHERE {$dateFilter} AND referrer IS NOT NULL AND referrer != '' GROUP BY referrer ORDER BY cnt DESC LIMIT 10"); } catch (\Throwable $e) {}

// Top landing pages
$landingPages = [];
try { $landingPages = $db->fetchAll("SELECT landing_page, COUNT(*) as cnt, COALESCE(SUM(order_placed),0) as orders FROM visitor_logs v WHERE {$dateFilter} GROUP BY landing_page ORDER BY cnt DESC LIMIT 10"); } catch (\Throwable $e) {}

// Daily traffic (last 14 days)
$dailyTraffic = [];
try { $dailyTraffic = $db->fetchAll("SELECT DATE(created_at) as day, COUNT(*) as visitors, COALESCE(SUM(order_placed),0) as orders FROM visitor_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY day"); } catch (\Throwable $e) {}

// Conversion funnel
$funnel = ['visited' => 0, 'carted' => 0, 'checkout' => 0, 'ordered' => 0];
try {
    $funnel['visited'] = intval($db->fetch("SELECT COUNT(*) as c FROM visitor_logs v WHERE {$dateFilter}")['c'] ?? 0);
    $funnel['carted'] = intval($db->fetch("SELECT COUNT(*) as c FROM visitor_logs v WHERE {$dateFilter} AND cart_items > 0")['c'] ?? 0);
} catch (\Throwable $e) {}
try {
    $funnel['checkout'] = intval($db->fetch("SELECT COUNT(*) as c FROM incomplete_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . max(1, $days) . " DAY)")['c'] ?? 0);
} catch (\Throwable $e) {}
try {
    $funnel['ordered'] = intval($db->fetch("SELECT COUNT(*) as c FROM visitor_logs v WHERE {$dateFilter} AND order_placed = 1")['c'] ?? 0);
} catch (\Throwable $e) {}

// Recent visitors
$search = $_GET['search'] ?? '';
$vWhere = $dateFilter;
$vParams = [];
if ($search) { $vWhere .= " AND (v.device_ip LIKE ? OR v.network_ip LIKE ? OR v.customer_phone LIKE ? OR v.browser LIKE ?)"; $vParams = ["%$search%", "%$search%", "%$search%", "%$search%"]; }
$page = max(1, intval($_GET['p'] ?? 1));
$visitors = [];
try {
    $visitors = $db->fetchAll("SELECT v.* FROM visitor_logs v WHERE {$vWhere} ORDER BY v.created_at DESC LIMIT 50 OFFSET " . (($page-1)*50), $vParams);
} catch (\Throwable $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Period Tabs -->
<div class="flex gap-2 mb-6">
    <?php foreach (['today' => 'Today', '7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days'] as $k => $v): ?>
    <a href="?period=<?= $k ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $period === $k ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Total Visitors</p>
        <p class="text-2xl font-bold text-blue-600"><?= number_format($visitors_count) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Unique IPs</p>
        <p class="text-2xl font-bold text-indigo-600"><?= number_format($unique_ips) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Orders Placed</p>
        <p class="text-2xl font-bold text-green-600"><?= number_format($orders_count) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Conversion Rate</p>
        <p class="text-2xl font-bold text-purple-600"><?= $conversion ?>%</p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Avg Pages/Visit</p>
        <p class="text-2xl font-bold text-orange-600"><?= $pages_avg ?></p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <!-- Traffic Chart -->
    <div class="lg:col-span-2 bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-4">📈 Daily Traffic (14 Days)</h4>
        <div class="h-48 flex items-end gap-1">
            <?php 
            $maxV = max(array_column($dailyTraffic, 'visitors') ?: [1]);
            foreach ($dailyTraffic as $dt): 
                $h = $maxV > 0 ? round(($dt['visitors'] / $maxV) * 180) : 0;
                $oH = $maxV > 0 ? round(($dt['orders'] / $maxV) * 180) : 0;
            ?>
            <div class="flex-1 flex flex-col items-center gap-0.5">
                <span class="text-xs text-gray-400"><?= $dt['visitors'] ?></span>
                <div class="w-full flex flex-col-reverse">
                    <div class="w-full bg-blue-200 rounded-t" style="height:<?= max(2, $h) ?>px" title="<?= $dt['visitors'] ?> visitors"></div>
                    <div class="w-full bg-green-400 rounded-t" style="height:<?= max(0, $oH) ?>px" title="<?= $dt['orders'] ?> orders"></div>
                </div>
                <span class="text-xs text-gray-400 -rotate-45 origin-top-left mt-1"><?= date('d/m', strtotime($dt['day'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($dailyTraffic)): ?><p class="text-gray-400 text-sm m-auto">No data yet</p><?php endif; ?>
        </div>
        <div class="flex gap-4 mt-3 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-200 rounded"></span> Visitors</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-400 rounded"></span> Orders</span>
        </div>
    </div>

    <!-- Conversion Funnel -->
    <div class="bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-4">🔄 Conversion Funnel</h4>
        <div class="space-y-3">
            <?php 
            $funnelItems = [
                ['label' => 'Visited Site', 'count' => $funnel['visited'], 'color' => 'blue'],
                ['label' => 'Added to Cart', 'count' => $funnel['carted'], 'color' => 'yellow'],
                ['label' => 'Started Checkout', 'count' => $funnel['checkout'], 'color' => 'purple'],
                ['label' => 'Placed Order', 'count' => $funnel['ordered'], 'color' => 'green'],
            ];
            $maxF = max($funnel['visited'], 1);
            foreach ($funnelItems as $fi):
                $pct = round(($fi['count'] / $maxF) * 100);
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600"><?= $fi['label'] ?></span>
                    <span class="font-bold"><?= number_format($fi['count']) ?> <span class="text-gray-400 font-normal">(<?= $pct ?>%)</span></span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3"><div class="bg-<?= $fi['color'] ?>-500 h-3 rounded-full transition-all" style="width:<?= max(2, $pct) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-4 gap-6 mb-6">
    <!-- Device Types -->
    <div class="bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-3">📱 Devices</h4>
        <div class="space-y-2">
            <?php 
            $dIcons = ['mobile' => '📱', 'desktop' => '🖥', 'tablet' => '📟', 'bot' => '🤖'];
            $totalD = array_sum(array_column($deviceStats, 'cnt')) ?: 1;
            foreach ($deviceStats as $ds): 
                $pct = round(($ds['cnt'] / $totalD) * 100);
            ?>
            <div class="flex items-center justify-between">
                <span class="text-sm"><?= $dIcons[$ds['device_type']] ?? '❓' ?> <?= ucfirst($ds['device_type'] ?? 'Unknown') ?></span>
                <span class="text-sm font-medium"><?= $ds['cnt'] ?> <span class="text-gray-400">(<?= $pct ?>%)</span></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Browsers -->
    <div class="bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-3">🌐 Browsers</h4>
        <div class="space-y-2">
            <?php foreach ($browserStats as $bs): ?>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600"><?= e($bs['browser'] ?? 'Unknown') ?></span>
                <span class="text-sm font-medium"><?= $bs['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($browserStats)): ?><p class="text-xs text-gray-400">No data</p><?php endif; ?>
        </div>
    </div>

    <!-- OS -->
    <div class="bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-3">💻 Operating Systems</h4>
        <div class="space-y-2">
            <?php foreach ($osStats as $os): ?>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600"><?= e($os['os'] ?? 'Unknown') ?></span>
                <span class="text-sm font-medium"><?= $os['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($osStats)): ?><p class="text-xs text-gray-400">No data</p><?php endif; ?>
        </div>
    </div>

    <!-- Top Referrers -->
    <div class="bg-white rounded-xl border p-5">
        <h4 class="font-semibold text-gray-800 mb-3">🔗 Top Referrers</h4>
        <div class="space-y-2">
            <?php foreach (array_slice($referrers, 0, 6) as $ref): 
                $domain = parse_url($ref['referrer'], PHP_URL_HOST) ?? $ref['referrer'];
            ?>
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-600 truncate max-w-[120px]" title="<?= e($ref['referrer']) ?>"><?= e($domain) ?></span>
                <span class="text-sm font-medium"><?= $ref['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($referrers)): ?><p class="text-xs text-gray-400">No referrer data</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Landing Pages -->
<div class="bg-white rounded-xl border p-5 mb-6">
    <h4 class="font-semibold text-gray-800 mb-3">📄 Top Landing Pages</h4>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="text-left px-4 py-2 font-medium text-gray-600">Page</th>
                <th class="text-center px-4 py-2 font-medium text-gray-600">Visitors</th>
                <th class="text-center px-4 py-2 font-medium text-gray-600">Orders</th>
                <th class="text-center px-4 py-2 font-medium text-gray-600">Conv. Rate</th>
            </tr></thead>
            <tbody class="divide-y">
                <?php foreach ($landingPages as $lp): 
                    $cr = $lp['cnt'] > 0 ? round(($lp['orders'] / $lp['cnt']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-blue-600 font-mono text-xs"><?= e($lp['landing_page'] ?? '/') ?></td>
                    <td class="px-4 py-2 text-center"><?= number_format($lp['cnt']) ?></td>
                    <td class="px-4 py-2 text-center text-green-600"><?= $lp['orders'] ?></td>
                    <td class="px-4 py-2 text-center"><?= $cr ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visitor Logs Table -->
<div class="bg-white rounded-xl border shadow-sm overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between">
        <h4 class="font-semibold text-gray-800">🔍 Visitor Logs</h4>
        <form class="flex gap-2">
            <input type="hidden" name="period" value="<?= e($period) ?>">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search IP, phone, browser..." class="border rounded-lg px-3 py-1.5 text-sm w-60">
            <button class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm">Search</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Time</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Device IP</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Network IP</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Browser</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">OS</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Device</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Pages</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Cart</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Customer</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Order</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Referrer</th>
            </tr></thead>
            <tbody class="divide-y">
                <?php foreach ($visitors as $v): ?>
                <tr class="hover:bg-gray-50 <?= $v['order_placed'] ? 'bg-green-50/30' : '' ?>">
                    <td class="px-3 py-2 whitespace-nowrap"><p class="text-xs text-gray-700"><?= date('d/m H:i', strtotime($v['created_at'])) ?></p></td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-600"><?= e($v['device_ip']) ?></td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-400"><?= e($v['network_ip'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-xs"><?= e($v['browser'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-xs"><?= e($v['os'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-xs"><?php
                        $dIcon = ['mobile' => '📱', 'desktop' => '🖥', 'tablet' => '📟', 'bot' => '🤖'];
                        echo ($dIcon[$v['device_type']] ?? '') . ' ' . ucfirst($v['device_type'] ?? '');
                    ?></td>
                    <td class="px-3 py-2 text-center text-xs font-medium"><?= $v['pages_viewed'] ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $v['cart_items'] > 0 ? '🛒 ' . $v['cart_items'] : '-' ?></td>
                    <td class="px-3 py-2 text-xs"><?= $v['customer_phone'] ? e($v['customer_phone']) : '-' ?></td>
                    <td class="px-3 py-2 text-xs"><?= $v['order_placed'] ? '<span class="text-green-600 font-bold">✅</span>' : '-' ?></td>
                    <td class="px-3 py-2 text-xs text-gray-400 truncate max-w-[120px]" title="<?= e($v['referrer'] ?? '') ?>"><?= $v['referrer'] ? e(parse_url($v['referrer'], PHP_URL_HOST)) : 'Direct' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($visitors)): ?>
                <tr><td colspan="11" class="px-4 py-12 text-center text-gray-400">
                    <div class="text-4xl mb-2">📊</div>
                    <p>No visitor data yet. Tracking starts after deploying tracker.php.</p>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
