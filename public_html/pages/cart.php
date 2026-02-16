<?php
/**
 * Cart Page
 */
$pageTitle = 'Shopping Cart | ' . getSetting('site_name');
include ROOT_PATH . 'includes/header.php';
$cart = getCart();
$cartTotal = getCartTotal();
$btnCheckoutLabel = getSetting('btn_checkout_label', 'চেকআউট');
$btnContinueLabel = getSetting('btn_continue_shopping', 'শপিং চালিয়ে যান');
?>

<main class="max-w-5xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fas fa-shopping-bag" style="color:var(--primary)"></i> শপিং কার্ট
    </h1>
    
    <?php if (empty($cart)): ?>
    <div class="text-center py-16 bg-white rounded-2xl shadow-sm border">
        <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="fas fa-shopping-bag text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-600 mb-2">আপনার কার্ট খালি আছে</h3>
        <a href="<?= url() ?>" class="inline-block mt-3 px-6 py-2.5 rounded-xl btn-primary font-medium"><?= $btnContinueLabel ?></a>
    </div>
    <?php else: ?>
    
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Cart Items -->
        <div class="lg:col-span-2 space-y-3" id="cart-items">
            <?php foreach ($cart as $key => $item): ?>
            <div class="cart-item bg-white rounded-xl p-4 shadow-sm border flex gap-4 items-center" data-key="<?= $key ?>">
                <img src="<?= $item['image'] ?>" alt="" class="w-20 h-20 rounded-lg object-cover border flex-shrink-0"
                     onerror="this.src='<?= asset('img/default-product.svg') ?>'">
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($item['name']) ?></h3>
                    <?php if (!empty($item['variant_name'])): ?>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($item['variant_name']) ?></p>
                    <?php endif; ?>
                    <p class="text-sm mt-1" style="color:var(--primary)"><?= formatPrice($item['price']) ?></p>
                    <?php if (!empty($item['regular_price']) && $item['regular_price'] > $item['price']): ?>
                    <p class="text-xs text-gray-400 line-through"><?= formatPrice($item['regular_price']) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center gap-2">
                    <div class="inline-flex items-center border rounded-lg overflow-hidden">
                        <button onclick="updateCartQty('<?= $key ?>', -1)" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 text-gray-500">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span class="w-10 h-8 flex items-center justify-center font-medium text-sm cart-qty"><?= $item['quantity'] ?></span>
                        <button onclick="updateCartQty('<?= $key ?>', 1)" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 text-gray-500">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                    
                    <span class="font-bold text-gray-800 w-24 text-right cart-item-total"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                    
                    <button onclick="removeCartItem('<?= $key ?>')" class="p-2 text-gray-400 hover:text-red-500 transition">
                        <i class="fas fa-trash-alt text-sm"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Cart Summary -->
        <div>
            <div class="bg-white rounded-xl p-5 shadow-sm border sticky top-24">
                <h3 class="font-bold text-lg mb-4">অর্ডার সারাংশ</h3>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">সাবটোটাল:</span><span class="font-medium" id="cart-subtotal"><?= formatPrice($cartTotal) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-600">ডেলিভারি:</span><span class="font-medium text-gray-500">চেকআউটে নির্ধারিত হবে</span></div>
                </div>
                
                <div class="border-t mt-3 pt-3 flex justify-between text-lg font-bold">
                    <span>মোট:</span>
                    <span style="color:var(--primary)" id="cart-total"><?= formatPrice($cartTotal) ?></span>
                </div>
                
                <button onclick="openCheckoutPopup()" 
                        class="w-full mt-4 py-3 rounded-xl btn-primary font-bold text-base transition transform active:scale-[0.98]">
                    <i class="fas fa-check-circle mr-2"></i> <?= $btnCheckoutLabel ?>
                </button>
                
                <a href="<?= url() ?>" class="block text-center mt-3 text-sm text-gray-500 hover:text-red-600 transition">
                    <i class="fas fa-arrow-left mr-1"></i> <?= $btnContinueLabel ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Cart-specific JS (qty/remove only — checkout handled by footer popup) -->
<script>
function updateCartQty(key, delta) {
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', key: key, delta: delta })
    })
    .then(r => r.json())
    .then(data => {
        if (data.removed) {
            document.querySelector(`.cart-item[data-key="${key}"]`)?.remove();
        } else if (data.success) {
            const item = document.querySelector(`.cart-item[data-key="${key}"]`);
            if (item) {
                item.querySelector('.cart-qty').textContent = data.quantity;
                item.querySelector('.cart-item-total').textContent = CURRENCY + ' ' + Number(data.item_total).toLocaleString();
            }
        }
        const sub = document.getElementById('cart-subtotal');
        const tot = document.getElementById('cart-total');
        if (sub) sub.textContent = CURRENCY + ' ' + Number(data.cart_total).toLocaleString();
        if (tot) tot.textContent = CURRENCY + ' ' + Number(data.cart_total).toLocaleString();
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
        if (data.cart_count === 0) location.reload();
    });
}

function removeCartItem(key) {
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', key: key })
    })
    .then(r => r.json())
    .then(data => {
        document.querySelector(`.cart-item[data-key="${key}"]`)?.remove();
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
        if (data.cart_count === 0) location.reload();
        else {
            const sub = document.getElementById('cart-subtotal');
            const tot = document.getElementById('cart-total');
            if (sub) sub.textContent = CURRENCY + ' ' + Number(data.cart_total).toLocaleString();
            if (tot) tot.textContent = CURRENCY + ' ' + Number(data.cart_total).toLocaleString();
        }
    });
}
</script>

<!-- Track incomplete on page unload during checkout -->
<script>
window.addEventListener('beforeunload', function() {
    const popup = document.getElementById('checkout-popup');
    if (popup && !popup.classList.contains('hidden')) {
        try {
            const form = document.getElementById('checkout-form');
            navigator.sendBeacon(SITE_URL + '/api/track.php', new URLSearchParams({
                action: 'track_incomplete',
                step: 'abandoned',
                total: '<?= $cartTotal ?>',
                phone: form?.querySelector('[name=phone]')?.value || '',
                name: form?.querySelector('[name=name]')?.value || '',
                address: form?.querySelector('[name=address]')?.value || ''
            }));
        } catch(e) {}
    }
});
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>
