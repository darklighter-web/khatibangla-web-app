<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Accounting';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

$month = $_GET['month'] ?? date('Y-m');
$type = $_GET['type'] ?? 'all';

$where = "DATE_FORMAT(ae.entry_date, '%Y-%m') = ?";
$params = [$month];
if ($type !== 'all') {
    $where .= " AND ae.entry_type = ?";
    $params[] = $type;
}

$entries = $db->fetchAll("SELECT ae.* FROM accounting_entries ae WHERE $where ORDER BY ae.entry_date DESC, ae.id DESC", $params);

// Monthly summary
$income = $db->fetch("SELECT COALESCE(SUM(amount),0) as total FROM accounting_entries WHERE entry_type = 'income' AND DATE_FORMAT(entry_date, '%Y-%m') = ?", [$month])['total'];
$expense = $db->fetch("SELECT COALESCE(SUM(amount),0) as total FROM accounting_entries WHERE entry_type = 'expense' AND DATE_FORMAT(entry_date, '%Y-%m') = ?", [$month])['total'];
$refund = $db->fetch("SELECT COALESCE(SUM(amount),0) as total FROM accounting_entries WHERE entry_type = 'refund' AND DATE_FORMAT(entry_date, '%Y-%m') = ?", [$month])['total'];
$netProfit = $income - $expense - $refund;

// Last 6 months trend
$trendData = $db->fetchAll("SELECT DATE_FORMAT(entry_date, '%Y-%m') as month, entry_type, SUM(amount) as total FROM accounting_entries WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month, entry_type ORDER BY month");
$months = [];
foreach ($trendData as $td) {
    $months[$td['month']][$td['entry_type']] = $td['total'];
}

// Handle manual entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_entry') {
        $db->insert('accounting_entries', [
            'entry_type' => $_POST['entry_type'],
            'amount' => floatval($_POST['amount']),
            'description' => sanitize($_POST['description']),
            'entry_date' => $_POST['entry_date'],
            'reference_type' => sanitize($_POST['reference_type']) ?: null,
            'reference_id' => intval($_POST['reference_id']) ?: null,
        ]);
        redirect(adminUrl('pages/accounting.php?month=' . $month . '&msg=added'));
    }
    if ($action === 'delete') {
        $db->delete('accounting_entries', 'id = ?', [intval($_POST['entry_id'])]);
        redirect(adminUrl('pages/accounting.php?month=' . $month . '&msg=deleted'));
    }
}

require_once __DIR__ . '/../includes/header.php';
$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">Entry <?= $msg ?>.</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Income</p>
        <p class="text-2xl font-bold text-green-600">৳<?= number_format($income) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Expenses</p>
        <p class="text-2xl font-bold text-red-600">৳<?= number_format($expense) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Refunds</p>
        <p class="text-2xl font-bold text-orange-600">৳<?= number_format($refund) ?></p>
    </div>
    <div class="bg-white rounded-xl border p-4">
        <p class="text-xs text-gray-500 mb-1">Net Profit</p>
        <p class="text-2xl font-bold <?= $netProfit >= 0 ? 'text-green-600' : 'text-red-600' ?>">৳<?= number_format($netProfit) ?></p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Trend Chart + Add Entry -->
    <div class="lg:col-span-1 space-y-4">
        <!-- 6-Month Trend -->
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">6-Month Trend</h3>
            <canvas id="trendChart" height="200"></canvas>
        </div>
        <!-- Quick Entry -->
        <div class="bg-white rounded-xl border shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Manual Entry</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="add_entry">
                <div>
                    <label class="block text-sm font-medium mb-1">Type *</label>
                    <select name="entry_type" required class="border rounded-lg px-3 py-2 text-sm w-full">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                        <option value="refund">Refund</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date *</label>
                        <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <input type="text" name="description" class="border rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Ref Type</label>
                        <input type="text" name="reference_type" placeholder="order, manual" class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Ref ID</label>
                        <input type="number" name="reference_id" class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                </div>
                <button class="w-full bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700">Add Entry</button>
            </form>
        </div>
    </div>

    <!-- Entries Ledger -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border shadow-sm">
            <div class="p-4 border-b flex flex-wrap gap-3 items-center">
                <form class="flex gap-3 items-center">
                    <input type="month" name="month" value="<?= $month ?>" class="border rounded-lg px-3 py-2 text-sm">
                    <select name="type" class="border rounded-lg px-3 py-2 text-sm">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expenses</option>
                        <option value="refund" <?= $type === 'refund' ? 'selected' : '' ?>>Refunds</option>
                    </select>
                    <button class="bg-gray-100 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Description</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600">Reference</th>
                            <th class="text-right px-4 py-3 font-medium text-gray-600">Amount</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($entries as $entry): ?>
                        <?php $typeBadge = ['income' => 'bg-green-100 text-green-700', 'expense' => 'bg-red-100 text-red-700', 'refund' => 'bg-orange-100 text-orange-700']; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500"><?= date('d M Y', strtotime($entry['entry_date'])) ?></td>
                            <td class="px-4 py-3"><span class="<?= $typeBadge[$entry['entry_type']] ?> px-2 py-0.5 rounded-full text-xs"><?= ucfirst($entry['entry_type']) ?></span></td>
                            <td class="px-4 py-3"><?= e($entry['description'] ?: '-') ?></td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                <?php if ($entry['reference_type']): ?>
                                <?= e($entry['reference_type']) ?>#<?= $entry['reference_id'] ?>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?= $entry['entry_type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $entry['entry_type'] === 'income' ? '+' : '-' ?>৳<?= number_format($entry['amount']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                    <button class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($entries)): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No entries this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const trendMonths = <?= json_encode(array_keys($months)) ?>;
const trendIncome = trendMonths.map(m => <?= json_encode(array_map(fn($m) => $m['income'] ?? 0, $months)) ?>[m] || 0);
const trendExpense = trendMonths.map(m => <?= json_encode(array_map(fn($m) => $m['expense'] ?? 0, $months)) ?>[m] || 0);

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendMonths.map(m => { const d = new Date(m+'-01'); return d.toLocaleDateString('en',{month:'short',year:'2-digit'}); }),
        datasets: [
            { label: 'Income', data: trendIncome, backgroundColor: '#22c55e' },
            { label: 'Expense', data: trendExpense, backgroundColor: '#ef4444' }
        ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
