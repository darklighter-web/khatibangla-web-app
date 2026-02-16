<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Create Order';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['price'] ?? [];

        if (empty($productIds)) {
            redirect(adminUrl('pages/order-add.php?msg=no_items'));
        }

        // Calculate totals
        $subtotal = 0;
        $itemCount = 0;
        $orderItems = [];
        for ($i = 0; $i < count($productIds); $i++) {
            if ($productIds[$i] && $quantities[$i] > 0) {
                $product = $db->fetch("SELECT * FROM products WHERE id = ?", [$productIds[$i]]);
                if (!$product) continue;
                $price = floatval($prices[$i]) ?: getProductPrice($product);
                $qty = intval($quantities[$i]);
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;
                $itemCount += $qty;
                $orderItems[] = [
                    'product' => $product,
                    'price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $lineTotal,
                ];
            }
        }

        $shipping = floatval($_POST['shipping_cost']);
        $discount = floatval($_POST['discount'] ?? 0);
        $total = $subtotal + $shipping - $discount;

        // Find or create customer
        $phone = sanitize($_POST['customer_phone']);
        $customer = $db->fetch("SELECT * FROM customers WHERE phone = ?", [$phone]);
        if (!$customer) {
            $db->insert('customers', [
                'name' => sanitize($_POST['customer_name']),
                'phone' => $phone,
                'email' => sanitize($_POST['customer_email']),
            ]);
            $customerId = $db->lastInsertId();
        } else {
            $customerId = $customer['id'];
            $db->update('customers', ['name' => sanitize($_POST['customer_name'])], 'id = ?', [$customerId]);
        }

        // Create order
        $orderNumber = generateOrderNumber();
        $db->insert('orders', [
            'order_number' => $orderNumber,
            'customer_id' => $customerId,
            'customer_name' => sanitize($_POST['customer_name']),
            'customer_phone' => $phone,
            'customer_email' => sanitize($_POST['customer_email']),
            'customer_address' => sanitize($_POST['shipping_address']),
            'customer_city' => sanitize($_POST['shipping_city'] ?? $_POST['city'] ?? ''),
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'discount_amount' => $discount,
            'total' => $total,
            'payment_method' => 'cod',
            'order_status' => $_POST['order_status'] ?? 'confirmed',
            'channel' => 'phone',
            'admin_notes' => sanitize($_POST['admin_notes'] ?? ''),
            'ip_address' => getClientIP(),
        ]);
        $orderId = $db->lastInsertId();

        // Insert items
        foreach ($orderItems as $oi) {
            $db->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $oi['product']['id'],
                'product_name' => $oi['product']['name'],
                'price' => $oi['price'],
                'quantity' => $oi['quantity'],
                'subtotal' => $oi['subtotal'],
            ]);
        }

        // Status history
        $db->insert('order_status_history', [
            'order_id' => $orderId,
            'status' => $_POST['order_status'] ?? 'confirmed',
            'note' => 'Order created manually by admin',
            'changed_by' => getAdminId(),
        ]);

        // Accounting entry
        $db->insert('accounting_entries', [
            'entry_type' => 'income',
            'amount' => $total,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'description' => "Order $orderNumber (manual)",
            'entry_date' => date('Y-m-d'),
        ]);

        logActivity(getAdminId(), 'create', 'orders', $orderId);
        redirect(adminUrl("pages/order-view.php?id=$orderId&msg=created"));
    }
}

$products = $db->fetchAll("SELECT id, name, sku, regular_price, sale_price, is_on_sale, stock_quantity FROM products WHERE is_active = 1 ORDER BY name");
$productsJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'name' => $p['name'],
    'sku' => $p['sku'],
    'price' => getProductPrice($p),
    'stock' => $p['stock_quantity'],
], $products));

require_once __DIR__ . '/../includes/header.php';
$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg === 'no_items'): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">Please add at least one item.</div>
<?php endif; ?>

<div class="mb-4">
    <a href="<?= adminUrl('pages/order-management.php') ?>" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Back to Orders</a>
</div>

<form method="POST">
    <input type="hidden" name="action" value="create_order">

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Items -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <h3 class="font-semibold text-gray-800 mb-4">Order Items</h3>
                <div id="items-container">
                    <div class="item-row grid grid-cols-12 gap-3 mb-3 items-end">
                        <div class="col-span-5">
                            <label class="block text-xs font-medium mb-1">Product</label>
                            <select name="product_id[]" onchange="updatePrice(this)" required class="product-select border rounded-lg px-3 py-2 text-sm w-full">
                                <option value="">Select product</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= getProductPrice($p) ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium mb-1">Price</label>
                            <input type="number" name="price[]" step="0.01" min="0" class="price-input border rounded-lg px-3 py-2 text-sm w-full" oninput="calcTotals()">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium mb-1">Qty</label>
                            <input type="number" name="quantity[]" value="1" min="1" class="qty-input border rounded-lg px-3 py-2 text-sm w-full" oninput="calcTotals()">
                        </div>
                        <div class="col-span-2 text-right">
                            <label class="block text-xs font-medium mb-1">Line Total</label>
                            <p class="line-total font-semibold text-sm py-2">৳0</p>
                        </div>
                        <div class="col-span-1">
                            <button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600 py-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addRow()" class="mt-3 text-blue-600 hover:text-blue-800 text-sm font-medium">+ Add Item</button>
            </div>

            <!-- Totals -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <div class="max-w-xs ml-auto space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span id="subtotal" class="font-semibold">৳0</span>
                    </div>
                    <div class="flex justify-between text-sm items-center">
                        <span class="text-gray-600">Shipping</span>
                        <input type="number" name="shipping_cost" id="shippingInput" value="80" min="0" step="0.01" class="border rounded-lg px-2 py-1 text-sm w-24 text-right" oninput="calcTotals()">
                    </div>
                    <div class="flex justify-between text-sm items-center">
                        <span class="text-gray-600">Discount</span>
                        <input type="number" name="discount" id="discountInput" value="0" min="0" step="0.01" class="border rounded-lg px-2 py-1 text-sm w-24 text-right" oninput="calcTotals()">
                    </div>
                    <hr>
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span id="grandTotal">৳80</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer + Order Info -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Customer Info</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name *</label>
                        <input type="text" name="customer_name" required class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone *</label>
                        <input type="text" name="customer_phone" required class="border rounded-lg px-3 py-2 text-sm w-full" placeholder="01XXXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input type="email" name="customer_email" class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Shipping</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Address *</label>
                        <textarea name="shipping_address" rows="2" required class="border rounded-lg px-3 py-2 text-sm w-full"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">City *</label>
                        <select name="shipping_city" required class="border rounded-lg px-3 py-2 text-sm w-full">
                            <option value="Dhaka">Dhaka</option>
                            <option value="Outside Dhaka">Outside Dhaka</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Area</label>
                        <input type="text" name="shipping_area" class="border rounded-lg px-3 py-2 text-sm w-full">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Order Settings</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="order_status" class="border rounded-lg px-3 py-2 text-sm w-full">
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Admin Note</label>
                        <textarea name="admin_notes" rows="2" class="border rounded-lg px-3 py-2 text-sm w-full" placeholder="Internal note..."></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-semibold hover:bg-blue-700">Create Order</button>
        </div>
    </div>
</form>

<script>
const products = <?= $productsJson ?>;

function updatePrice(sel) {
    const row = sel.closest('.item-row');
    const opt = sel.options[sel.selectedIndex];
    const price = opt.dataset.price || 0;
    row.querySelector('.price-input').value = price;
    calcTotals();
}

function addRow() {
    const container = document.getElementById('items-container');
    const first = container.querySelector('.item-row');
    const clone = first.cloneNode(true);
    clone.querySelector('.product-select').value = '';
    clone.querySelector('.price-input').value = '';
    clone.querySelector('.qty-input').value = '1';
    clone.querySelector('.line-total').textContent = '৳0';
    container.appendChild(clone);
}

function removeRow(btn) {
    const container = document.getElementById('items-container');
    if (container.querySelectorAll('.item-row').length > 1) {
        btn.closest('.item-row').remove();
        calcTotals();
    }
}

function calcTotals() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        const line = price * qty;
        row.querySelector('.line-total').textContent = '৳' + line.toLocaleString();
        subtotal += line;
    });
    const shipping = parseFloat(document.getElementById('shippingInput').value) || 0;
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    document.getElementById('subtotal').textContent = '৳' + subtotal.toLocaleString();
    document.getElementById('grandTotal').textContent = '৳' + (subtotal + shipping - discount).toLocaleString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
