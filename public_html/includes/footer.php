<?php
$footerBg = getSetting('footer_bg_color', '#1A202C');
$footerText = getSetting('footer_text_color', '#E2E8F0');
$footerAbout = getSetting('footer_about');
$copyright = getSetting('footer_copyright');
$fbUrl = getSetting('social_facebook');
$igUrl = getSetting('social_instagram');
$ytUrl = getSetting('social_youtube');
$ttUrl = getSetting('social_tiktok');
$footerPages = Database::getInstance()->fetchAll("SELECT title, slug FROM pages WHERE is_active = 1 LIMIT 6");
?>

<!-- Footer -->
<footer style="background-color:<?= $footerBg ?>;color:<?= $footerText ?>">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- About Column -->
            <div>
                <?php if ($siteLogo): ?>
                <img src="<?= uploadUrl($siteLogo) ?>" alt="<?= $siteName ?>" class="h-12 mb-4 brightness-0 invert">
                <?php else: ?>
                <h3 class="text-xl font-bold mb-4 footer-heading"><?= $siteName ?></h3>
                <?php endif; ?>
                <p class="text-sm opacity-80 leading-relaxed footer-text"><?= htmlspecialchars($footerAbout) ?></p>
                
                <!-- Social Icons -->
                <div class="flex gap-3 mt-4">
                    <?php if ($fbUrl): ?>
                    <a href="<?= $fbUrl ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($igUrl): ?>
                    <a href="<?= $igUrl ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($ytUrl): ?>
                    <a href="<?= $ytUrl ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($ttUrl): ?>
                    <a href="<?= $ttUrl ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="text-base font-semibold mb-4 uppercase tracking-wide footer-heading">Quick Links</h4>
                <ul class="space-y-2 text-sm opacity-80 footer-text">
                    <?php foreach ($footerPages as $pg): ?>
                    <li><a href="<?= url('page/' . $pg['slug']) ?>" class="hover:opacity-100 hover:underline transition"><?= htmlspecialchars($pg['title']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?= url('track-order') ?>" class="hover:opacity-100 hover:underline transition">Track My Order</a></li>
                </ul>
            </div>
            
            <!-- Categories -->
            <div>
                <h4 class="text-base font-semibold mb-4 uppercase tracking-wide footer-heading">Categories</h4>
                <ul class="space-y-2 text-sm opacity-80 footer-text">
                    <?php foreach (array_slice($categories, 0, 8) as $cat): ?>
                    <li><a href="<?= url('category/' . $cat['slug']) ?>" class="hover:opacity-100 hover:underline transition"><?= htmlspecialchars($cat['name_bn'] ?: $cat['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Contact -->
            <div>
                <h4 class="text-base font-semibold mb-4 uppercase tracking-wide footer-heading">Contact Us</h4>
                <ul class="space-y-3 text-sm opacity-80 footer-text">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-map-marker-alt mt-1 text-red-400"></i>
                        <span><?= htmlspecialchars(getSetting('site_address')) ?></span>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fas fa-phone-alt text-green-400"></i>
                        <a href="tel:<?= $sitePhone ?>" class="hover:underline"><?= $sitePhone ?></a>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fas fa-headset text-blue-400"></i>
                        <a href="tel:<?= $siteHotline ?>" class="hover:underline"><?= $siteHotline ?></a>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fab fa-whatsapp text-green-400"></i>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $siteWhatsapp) ?>" class="hover:underline"><?= $siteWhatsapp ?></a>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fas fa-envelope text-yellow-400"></i>
                        <a href="mailto:<?= getSetting('site_email') ?>" class="hover:underline"><?= getSetting('site_email') ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Bottom Bar -->
    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm opacity-70 footer-copyright">
            <p><?= htmlspecialchars($copyright) ?></p>
            <div class="flex items-center gap-3">
                <img src="<?= asset('img/payment-cod.svg') ?>" alt="COD" class="h-6 opacity-70" onerror="this.style.display='none'">
                <img src="<?= asset('img/payment-bkash.svg') ?>" alt="bKash" class="h-6 opacity-70" onerror="this.style.display='none'">
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Nav -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-40">
    <div class="flex items-center justify-around py-2">
        <a href="<?= url() ?>" class="flex flex-col items-center gap-0.5 text-xs" style="color:var(--primary)">
            <i class="fas fa-home text-lg"></i>
            <span>Home</span>
        </a>
        <a href="<?= url('shop') ?>" class="flex flex-col items-center gap-0.5 text-xs text-gray-500">
            <i class="fas fa-th-large text-lg"></i>
            <span>Shop</span>
        </a>
        <a href="tel:<?= $sitePhone ?>" class="flex flex-col items-center gap-0.5 text-xs text-gray-500">
            <i class="fas fa-phone-alt text-lg"></i>
            <span>Call</span>
        </a>
        <a href="<?= url('cart') ?>" class="flex flex-col items-center gap-0.5 text-xs text-gray-500 relative">
            <i class="fas fa-shopping-bag text-lg"></i>
            <span>Cart</span>
            <?php if ($cartCount > 0): ?>
            <span class="absolute -top-1 right-2 w-4 h-4 flex items-center justify-center text-[10px] font-bold rounded-full sale-badge cart-count"><?= $cartCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- Ajax Checkout Popup Modal -->
<div id="checkout-popup" class="popup-overlay hidden fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center popup-hide" onclick="closeCheckoutPopup(event)">
    <div class="popup-content bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="sticky top-0 bg-white border-b px-5 py-3 flex items-center justify-between rounded-t-2xl z-10">
            <h3 class="text-lg font-bold">অর্ডার করুন</h3>
            <button onclick="closeCheckoutPopup()" class="p-1.5 hover:bg-gray-100 rounded-full"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        
        <form id="checkout-form" class="p-5 space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCSRFToken() ?>">
            
            <?php
            // Load checkout field config
            $checkoutFieldsJson = getSetting('checkout_fields', '');
            $checkoutFields = $checkoutFieldsJson ? json_decode($checkoutFieldsJson, true) : null;
            if (!$checkoutFields) {
                // Default field order
                $checkoutFields = [
                    ['key'=>'cart_summary','label'=>'পণ্যের তালিকা','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'name','label'=>'আপনার নাম','type'=>'text','enabled'=>true,'required'=>true,'placeholder'=>'সম্পূর্ণ নাম লিখুন'],
                    ['key'=>'phone','label'=>'মোবাইল নম্বর','type'=>'tel','enabled'=>true,'required'=>true,'placeholder'=>'01XXXXXXXXX'],
                    ['key'=>'email','label'=>'ইমেইল','type'=>'email','enabled'=>false,'required'=>false,'placeholder'=>'your@email.com'],
                    ['key'=>'address','label'=>'সম্পূর্ণ ঠিকানা','type'=>'textarea','enabled'=>true,'required'=>true,'placeholder'=>'বাসা/রোড নং, এলাকা, থানা, জেলা'],
                    ['key'=>'shipping_area','label'=>'ডেলিভারি এরিয়া','type'=>'radio','enabled'=>true,'required'=>true,'placeholder'=>''],
                    ['key'=>'coupon','label'=>'কুপন কোড আছে?','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>'কুপন কোড লিখুন'],
                    ['key'=>'store_credit','label'=>'স্টোর ক্রেডিট ব্যবহার করুন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'upsells','label'=>'এটাও নিতে পারেন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'order_total','label'=>'অর্ডার সামারি','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'notes','label'=>'অতিরিক্ত নোট','type'=>'text','enabled'=>false,'required'=>false,'placeholder'=>'বিশেষ কোনো নির্দেশনা থাকলে লিখুন'],
                ];
            } else {
                // Deduplicate by key (keep first occurrence only)
                $_seen = [];
                $checkoutFields = array_values(array_filter($checkoutFields, function($f) use (&$_seen) {
                    $k = $f['key'] ?? '';
                    if (isset($_seen[$k])) return false;
                    $_seen[$k] = true;
                    return true;
                }));
                
                // Merge in any new default fields that aren't in saved config
                $_defaultFields = [
                    ['key'=>'cart_summary','label'=>'পণ্যের তালিকা','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'name','label'=>'আপনার নাম','type'=>'text','enabled'=>true,'required'=>true,'placeholder'=>'সম্পূর্ণ নাম লিখুন'],
                    ['key'=>'phone','label'=>'মোবাইল নম্বর','type'=>'tel','enabled'=>true,'required'=>true,'placeholder'=>'01XXXXXXXXX'],
                    ['key'=>'email','label'=>'ইমেইল','type'=>'email','enabled'=>false,'required'=>false,'placeholder'=>'your@email.com'],
                    ['key'=>'address','label'=>'সম্পূর্ণ ঠিকানা','type'=>'textarea','enabled'=>true,'required'=>true,'placeholder'=>'বাসা/রোড নং, এলাকা, থানা, জেলা'],
                    ['key'=>'shipping_area','label'=>'ডেলিভারি এরিয়া','type'=>'radio','enabled'=>true,'required'=>true,'placeholder'=>''],
                    ['key'=>'coupon','label'=>'কুপন কোড আছে?','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>'কুপন কোড লিখুন'],
                    ['key'=>'store_credit','label'=>'স্টোর ক্রেডিট ব্যবহার করুন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'upsells','label'=>'এটাও নিতে পারেন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'order_total','label'=>'অর্ডার সামারি','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
                    ['key'=>'notes','label'=>'অতিরিক্ত নোট','type'=>'text','enabled'=>false,'required'=>false,'placeholder'=>'বিশেষ কোনো নির্দেশনা থাকলে লিখুন'],
                ];
                $savedKeys = array_column($checkoutFields, 'key');
                foreach ($_defaultFields as $df) {
                    if (!in_array($df['key'], $savedKeys)) {
                        // Insert new field before order_total if possible
                        $otIdx = array_search('order_total', $savedKeys);
                        if ($otIdx !== false) {
                            array_splice($checkoutFields, $otIdx, 0, [$df]);
                            $savedKeys = array_column($checkoutFields, 'key');
                        } else {
                            $checkoutFields[] = $df;
                        }
                    }
                }
            }
            
            // Pre-compute store credit data (needed by both store_credit and order_total fields)
            $storeCreditsEnabled = getSetting('store_credits_enabled', '1') !== '0';
            $storeCreditCheckout = getSetting('store_credit_checkout', '1') !== '0';
            $creditConversionRate = floatval(getSetting('store_credit_conversion_rate', '0.75'));
            if ($creditConversionRate <= 0) $creditConversionRate = 0.75;
            $custCredit = 0;
            $custCreditTk = 0;
            if ($storeCreditsEnabled && $storeCreditCheckout && isCustomerLoggedIn()) {
                $_cid = getCustomerId();
                $custCredit = getStoreCredit($_cid);
                // Sync from transactions if column is stale
                try {
                    $_txnRow = Database::getInstance()->fetch("SELECT COALESCE(SUM(amount), 0) as bal FROM store_credit_transactions WHERE customer_id = ?", [$_cid]);
                    $_txnBal = max(0, floatval($_txnRow['bal'] ?? 0));
                    if (abs($custCredit - $_txnBal) > 0.01) {
                        $custCredit = $_txnBal;
                        try { Database::getInstance()->query("UPDATE customers SET store_credit = ? WHERE id = ?", [$custCredit, $_cid]); } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {}
                $custCreditTk = round($custCredit * $creditConversionRate, 2);
            }
            
            foreach ($checkoutFields as $cf):
                if (!($cf['enabled'] ?? true)) continue;
                $key = $cf['key'];
                $label = $cf['label'] ?? '';
                $placeholder = $cf['placeholder'] ?? '';
                $required = $cf['required'] ?? false;
                $reqAttr = $required ? 'required' : '';
                $star = $required ? ' *' : '';
                
                if ($key === 'cart_summary'):
            ?>
            <!-- Product Summary in Popup -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?></label>
                <div id="popup-cart-summary" class="bg-gray-50 rounded-xl p-4 space-y-2">
                    <!-- Filled by JS -->
                </div>
            </div>
            <?php elseif ($key === 'name'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?><?= $star ?></label>
                <input type="text" name="name" <?= $reqAttr ?> placeholder="<?= e($placeholder) ?>"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none transition">
            </div>
            <?php elseif ($key === 'phone'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?><?= $star ?></label>
                <input type="tel" name="phone" <?= $reqAttr ?> placeholder="<?= e($placeholder) ?>" pattern="01[0-9]{9}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none transition">
            </div>
            <?php elseif ($key === 'email'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?><?= $star ?></label>
                <input type="email" name="email" <?= $reqAttr ?> placeholder="<?= e($placeholder) ?>"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none transition">
            </div>
            <?php elseif ($key === 'address'): ?>
            <?php
            // Saved addresses for logged-in customers
            $savedAddresses = [];
            if (isCustomerLoggedIn() && getCustomerId() > 0) {
                try {
                    $savedAddresses = $db->fetchAll("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC LIMIT 10", [getCustomerId()]);
                } catch (\Throwable $e) {}
            }
            if (!empty($savedAddresses)):
            ?>
            <div class="mb-2">
                <label class="block text-xs font-medium text-gray-500 mb-1"><i class="fas fa-bookmark mr-1"></i>সেভ করা ঠিকানা থেকে বাছুন</label>
                <select id="saved-address-select" onchange="fillSavedAddress(this.value)" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:ring-2 focus:ring-blue-200 outline-none">
                    <option value="">-- নতুন ঠিকানা লিখুন --</option>
                    <?php foreach ($savedAddresses as $sa): ?>
                    <option value='<?= htmlspecialchars(json_encode(['name'=>$sa['name'],'phone'=>$sa['phone'],'address'=>$sa['address'],'city'=>$sa['city'],'area'=>$sa['area']]), ENT_QUOTES) ?>'>
                        <?= e($sa['label'] ?: $sa['name']) ?> — <?= e(mb_substr($sa['address'], 0, 40)) ?>...
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?><?= $star ?></label>
                <textarea name="address" <?= $reqAttr ?> placeholder="<?= e($placeholder) ?>" rows="2"
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none transition"></textarea>
            </div>
            <?php elseif ($key === 'shipping_area'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= e($label) ?><?= $star ?></label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="flex items-center gap-2 border-2 rounded-xl p-2.5 cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50 transition">
                        <input type="radio" name="shipping_area" value="inside_dhaka" class="accent-red-500">
                        <div>
                            <span class="text-sm font-medium">ঢাকার ভিতরে</span>
                            <span class="block text-xs text-gray-500"><?= formatPrice(getSetting('shipping_inside_dhaka', 70)) ?></span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 border-2 rounded-xl p-2.5 cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50 transition">
                        <input type="radio" name="shipping_area" value="dhaka_sub" class="accent-red-500">
                        <div>
                            <span class="text-sm font-medium">ঢাকা উপশহর</span>
                            <span class="block text-xs text-gray-500"><?= formatPrice(getSetting('shipping_dhaka_sub', 100)) ?></span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 border-2 rounded-xl p-2.5 cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50 transition">
                        <input type="radio" name="shipping_area" value="outside_dhaka" checked class="accent-red-500">
                        <div>
                            <span class="text-sm font-medium">ঢাকার বাইরে</span>
                            <span class="block text-xs text-gray-500"><?= formatPrice(getSetting('shipping_outside_dhaka', 130)) ?></span>
                        </div>
                    </label>
                </div>
            </div>
            <?php elseif ($key === 'coupon'): ?>
            <!-- Coupon Code (Collapsible) -->
            <div class="border rounded-xl overflow-hidden">
                <button type="button" onclick="toggleCoupon()" class="w-full flex items-center justify-between px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition">
                    <span><i class="fas fa-tag mr-1.5 text-orange-500"></i> <?= e($label) ?></span>
                    <i id="coupon-chevron" class="fas fa-chevron-down text-xs transition-transform"></i>
                </button>
                <div id="coupon-section" class="hidden px-4 pb-3">
                    <div class="flex gap-2">
                        <input type="text" id="coupon-input" name="coupon_code" placeholder="<?= e($placeholder ?: 'কুপন কোড লিখুন') ?>" 
                               class="flex-1 border rounded-lg px-3 py-2 text-sm uppercase focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
                        <button type="button" onclick="applyCoupon()" id="coupon-apply-btn"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600 transition whitespace-nowrap">
                            প্রয়োগ
                        </button>
                    </div>
                    <div id="coupon-status" class="hidden mt-2 text-sm px-1"></div>
                </div>
            </div>
            <?php elseif ($key === 'store_credit'): ?>
            <!-- Store Credit Option (only shown to customers with >= 1 credit) -->
            <?php if ($custCredit >= 1): ?>
            <div id="store-credit-row" class="flex items-center justify-between bg-yellow-50 rounded-xl px-4 py-3 border border-yellow-200">
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                    <input type="checkbox" id="use-store-credit" class="rounded text-yellow-600 focus:ring-yellow-400" onchange="toggleStoreCredit()">
                    <div>
                        <span class="text-yellow-800 font-medium"><i class="fas fa-coins mr-1"></i><?= e($label) ?></span>
                        <span class="block text-[11px] text-yellow-600 mt-0.5">আপনার <?= number_format($custCredit, 0) ?> ক্রেডিট = ৳<?= number_format($custCreditTk, 0) ?> সমপরিমাণ</span>
                    </div>
                </label>
                <div class="text-right flex-shrink-0">
                    <span class="text-sm text-yellow-700 font-bold block">৳<?= number_format($custCreditTk, 0) ?></span>
                    <span class="text-[10px] text-yellow-500"><?= number_format($custCredit, 0) ?> credits</span>
                </div>
            </div>
            <?php endif; ?>
            <?php elseif ($key === 'upsells'): ?>
            <!-- Upsell Products -->
            <div id="upsell-section" class="hidden" data-count="<?= intval($cf['upsell_count'] ?? 4) ?>">
                <p class="text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-fire text-orange-500 mr-1"></i> <?= e($label) ?></p>
                <div id="upsell-products" class="space-y-2 max-h-48 overflow-y-auto"></div>
            </div>
            <?php elseif ($key === 'order_total'): ?>
            <!-- Order Total -->
            <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
                <div id="original-price-row" class="hidden flex justify-between text-gray-400">
                    <span>মূল মূল্য:</span><span id="popup-original" class="font-medium line-through">৳ 0</span>
                </div>
                <div id="product-discount-row" class="hidden flex justify-between text-green-600">
                    <span><i class="fas fa-gift mr-1"></i> বান্ডেল ছাড়:</span><span id="popup-product-discount" class="font-medium">-৳ 0</span>
                </div>
                <div class="flex justify-between"><span>সাবটোটাল:</span><span id="popup-subtotal" class="font-medium">৳ 0</span></div>
                <div id="coupon-discount-row" class="hidden flex justify-between text-green-600">
                    <span><i class="fas fa-tag mr-1"></i> কুপন ছাড়:</span><span id="popup-discount" class="font-medium">-৳ 0</span>
                </div>
                <?php if ($custCredit >= 1): ?>
                <div id="credit-applied-row" class="hidden flex justify-between text-yellow-700">
                    <span><i class="fas fa-coins mr-1"></i> স্টোর ক্রেডিট:</span><span id="popup-credit" class="font-medium">-৳ 0</span>
                </div>
                <?php endif; ?>
                <input type="hidden" id="store-credit-amount" name="store_credit_used" value="0">
                <input type="hidden" id="store-credit-max" value="<?= $custCreditTk ?>">
                <input type="hidden" id="store-credit-credits" value="<?= $custCredit ?>">
                <input type="hidden" id="store-credit-rate" value="<?= $creditConversionRate ?>">
                <div class="flex justify-between"><span>ডেলিভারি চার্জ:</span><span id="popup-shipping" class="font-medium">৳ 0</span></div>
                <div class="flex justify-between text-base font-bold border-t pt-2 mt-2">
                    <span>মোট:</span><span id="popup-total" style="color:var(--primary)">৳ 0</span>
                </div>
                <div id="total-savings-row" class="hidden flex justify-between text-green-700 bg-green-50 rounded-lg px-3 py-1.5 -mx-1 mt-1">
                    <span class="font-semibold"><i class="fas fa-piggy-bank mr-1"></i> মোট সেভ:</span><span id="popup-total-savings" class="font-bold">৳ 0</span>
                </div>
            </div>
            <?php elseif ($key === 'notes'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?></label>
                <input type="text" name="notes" placeholder="<?= e($placeholder) ?>"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none transition">
            </div>
            <?php endif; endforeach; ?>
            
            <div id="checkout-error" class="hidden bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                <span id="checkout-error-msg"></span>
            </div>
            
            <button type="submit" id="checkout-submit-btn" 
                    class="cod-order-btn w-full py-3.5 rounded-xl text-white font-bold text-base transition transform active:scale-[0.98]"
                    style="background-color:var(--btn-primary)">
                <i class="fas fa-check-circle mr-2"></i>
                <?= getSetting('order_button_text', getSetting('btn_order_cod_label', 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন')) ?>
            </button>
        </form>
        
        <!-- Order Success View -->
        <div id="checkout-success" class="hidden p-8 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                <i class="fas fa-check text-3xl text-green-500"></i>
            </div>
            <h3 class="text-xl font-bold text-green-700 mb-2">অর্ডার সফল হয়েছে!</h3>
            <div id="success-merge-msg" class="hidden mb-3 mx-auto max-w-xs bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2.5 rounded-xl text-sm">
                <i class="fas fa-link mr-1"></i> আপনার পণ্যগুলো পূর্ববর্তী অর্ডারে যোগ হয়েছে!
            </div>
            <p class="text-gray-600 mb-1">আপনার অর্ডার নম্বর: 
                <strong id="success-order-number" class="text-lg"></strong>
                <button type="button" id="copy-order-btn" onclick="copyOrderNumber()" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 ml-1 transition" title="Copy">
                    <i class="fas fa-copy text-xs"></i>
                </button>
            </p>
            <p class="text-gray-500 text-sm mb-6">শীঘ্রই আমাদের টিম আপনার সাথে যোগাযোগ করবে।</p>
            <div class="flex gap-3 justify-center">
                <a href="<?= url() ?>" class="px-6 py-2.5 rounded-xl btn-primary font-medium">
                    <i class="fas fa-home mr-1"></i> হোমপেজে যান
                </a>
                <button onclick="closeCheckoutPopup()" class="px-6 py-2.5 rounded-xl border-2 border-gray-300 font-medium hover:bg-gray-50 transition">
                    বন্ধ করুন
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
const SITE_URL = '<?= SITE_URL ?>';
const CURRENCY = '<?= getSetting('currency_symbol', '৳') ?>';
const SHIPPING_INSIDE = Number(<?= (int)getSetting('shipping_inside_dhaka', 70) ?>) || 70;
const SHIPPING_DHAKA_SUB = Number(<?= (int)getSetting('shipping_dhaka_sub', 100) ?>) || 100;
const SHIPPING_OUTSIDE = Number(<?= (int)getSetting('shipping_outside_dhaka', 130) ?>) || 130;
const FREE_SHIPPING_MIN = Number(<?= (int)getSetting('free_shipping_minimum', 5000) ?>) || 0;
const ORDER_NOW_CLEAR_CART = <?= getSetting('order_now_clear_cart', '1') === '1' ? 'true' : 'false' ?>;

// Mobile Menu
function toggleMobileMenu() {
    document.getElementById('mobile-menu')?.classList.toggle('hidden');
    document.body.classList.toggle('overflow-hidden');
}

// Toast
function showToast(msg, duration = 3000, type = 'error') {
    const t = document.getElementById('toast');
    if (!t) return;
    document.getElementById('toast-message').textContent = msg;
    // Apply type styling
    t.classList.remove('bg-green-500', 'bg-red-500', 'bg-yellow-500');
    t.classList.add(type === 'success' ? 'bg-green-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-red-500');
    // Update icon
    const icon = document.getElementById('toast-icon');
    if (icon) {
        icon.className = type === 'success' ? 'fas fa-check-circle' : type === 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-times-circle';
    }
    t.classList.remove('hidden', 'translate-x-full');
    setTimeout(() => { t.classList.add('translate-x-full'); setTimeout(() => t.classList.add('hidden'), 300); }, duration);
}

function showCreditNotification(credits, tkAmount) {
    // Remove existing notification if any
    document.getElementById('credit-notify')?.remove();
    
    const el = document.createElement('div');
    el.id = 'credit-notify';
    el.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;transform:translateY(-100%);transition:transform 0.4s cubic-bezier(0.16,1,0.3,1)';
    el.innerHTML = `
        <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:14px 20px;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 20px rgba(0,0,0,0.15)">
            <div style="background:rgba(255,255,255,0.25);border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas fa-coins" style="color:#fff;font-size:16px"></i>
            </div>
            <div style="color:#fff;font-size:14px;line-height:1.3">
                <strong>${Number(credits).toLocaleString()} ক্রেডিট পয়েন্ট</strong> ব্যবহার হয়েছে
                <span style="display:block;font-size:12px;opacity:0.9">৳${Number(tkAmount).toLocaleString()} আপনার অর্ডার থেকে বাদ দেওয়া হয়েছে</span>
            </div>
            <button onclick="this.closest('#credit-notify').style.transform='translateY(-100%)';setTimeout(()=>document.getElementById('credit-notify')?.remove(),400)" 
                    style="background:none;border:none;color:rgba(255,255,255,0.8);font-size:18px;cursor:pointer;padding:4px 8px;margin-left:8px">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    document.body.appendChild(el);
    
    // Slide in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => { el.style.transform = 'translateY(0)'; });
    });
    
    // Auto-dismiss after 5s
    setTimeout(() => {
        if (document.getElementById('credit-notify')) {
            el.style.transform = 'translateY(-100%)';
            setTimeout(() => el.remove(), 400);
        }
    }, 5000);
}

// ═══════════════════════════════════════════
// VARIANT PICKER - Show before cart/checkout
// ═══════════════════════════════════════════

// Cache variant data to avoid repeat fetches
const _variantCache = {};

function fetchVariants(productId) {
    if (_variantCache[productId]) return Promise.resolve(_variantCache[productId]);
    return fetch(SITE_URL + '/api/cart.php?action=get_variants&product_id=' + productId)
        .then(r => r.json())
        .then(data => {
            _variantCache[productId] = data;
            return data;
        });
}

// Smart Add to Cart — checks variants first
function smartAddToCart(productId, qty) {
    fetchVariants(productId).then(data => {
        if (data.has_variants) {
            showVariantPicker(productId, data, 'cart', qty);
        } else {
            addToCartAjax(productId, qty || 1);
        }
    });
}

// Smart Order — checks variants first, then opens checkout
function smartOrder(productId, qty) {
    fetchVariants(productId).then(data => {
        if (data.has_variants) {
            showVariantPicker(productId, data, 'order', qty);
        } else {
            openCheckoutPopup(productId, qty || 1);
        }
    });
}

function showVariantPicker(productId, data, mode, qty) {
    const popup = document.getElementById('variant-picker-popup');
    const product = data.product;
    const addonGroups = data.addon_groups || {};
    const variationGroups = data.variation_groups || {};
    
    // Product info
    document.getElementById('vp-product-image').src = product.image;
    document.getElementById('vp-product-name').textContent = product.name;
    document.getElementById('vp-base-price').textContent = CURRENCY + ' ' + Number(product.price).toLocaleString();
    document.getElementById('vp-current-price').textContent = CURRENCY + ' ' + Number(product.price).toLocaleString();
    
    if (product.regular_price > product.price) {
        document.getElementById('vp-regular-price').textContent = CURRENCY + ' ' + Number(product.regular_price).toLocaleString();
        document.getElementById('vp-regular-price').classList.remove('hidden');
    } else {
        document.getElementById('vp-regular-price').classList.add('hidden');
    }
    
    // Build variant options
    let html = '';
    
    // Variations first (replace price)
    for (const [groupName, variants] of Object.entries(variationGroups)) {
        html += `<div class="mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">${groupName} <span class="text-xs font-normal text-purple-500">(ভ্যারিয়েশন)</span></label>
            <div class="flex flex-wrap gap-2">`;
        variants.forEach((v, i) => {
            const priceTag = v.absolute_price ? ` (${CURRENCY}${Number(v.absolute_price).toLocaleString()})` : '';
            const stockTag = v.stock_quantity <= 0 ? ' <span class="text-red-400 text-xs">(স্টক শেষ)</span>' : '';
            const disabled = v.stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer';
            html += `<label class="inline-flex items-center border-2 rounded-xl px-4 py-2.5 ${disabled} has-[:checked]:border-red-500 has-[:checked]:bg-red-50 hover:border-gray-400 transition">
                <input type="radio" name="vp_group_${groupName}" value="${v.id}" 
                       data-type="variation" data-abs="${v.absolute_price || 0}" data-adj="0" data-stock="${v.stock_quantity}"
                       class="hidden vp-radio" ${((v.is_default == 1) || (i === 0 && !variants.some(x=>x.is_default==1))) && v.stock_quantity > 0 ? 'checked' : ''} ${v.stock_quantity <= 0 ? 'disabled' : ''}
                       onchange="updateVariantPickerPrice()">
                <span class="text-sm font-medium">${v.variant_value}${priceTag}${stockTag}</span>
            </label>`;
        });
        html += `</div></div>`;
    }
    
    // Addons (add to price) — NOT pre-selected, toggleable
    for (const [groupName, variants] of Object.entries(addonGroups)) {
        html += `<div class="mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">${groupName} <span class="text-xs font-normal text-blue-500">(অ্যাডঅন)</span></label>
            <div class="flex flex-wrap gap-2">`;
        variants.forEach((v, i) => {
            const priceTag = v.price_adjustment > 0 ? ` (+${CURRENCY}${Number(v.price_adjustment).toLocaleString()})` : 
                             v.price_adjustment < 0 ? ` (${CURRENCY}${Number(v.price_adjustment).toLocaleString()})` : '';
            const stockTag = v.stock_quantity <= 0 ? ' <span class="text-red-400 text-xs">(স্টক শেষ)</span>' : '';
            const disabled = v.stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer';
            html += `<label class="inline-flex items-center border-2 rounded-xl px-4 py-2.5 ${disabled} has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 hover:border-gray-400 transition" onclick="toggleAddon(event)">
                <input type="checkbox" name="vp_addon_${groupName}" value="${v.id}" 
                       data-type="addon" data-abs="0" data-adj="${v.price_adjustment}" data-stock="${v.stock_quantity}"
                       class="hidden vp-radio" ${v.stock_quantity <= 0 ? 'disabled' : ''}
                       onchange="updateVariantPickerPrice()">
                <span class="text-sm font-medium">${v.variant_value}${priceTag}${stockTag}</span>
            </label>`;
        });
        html += `</div></div>`;
    }
    
    document.getElementById('vp-options').innerHTML = html;
    document.getElementById('vp-qty').value = qty || 1;
    
    popup.dataset.productId = productId;
    popup.dataset.mode = mode;
    popup.dataset.basePrice = product.price;
    
    updateVariantPickerPrice();
    
    popup.classList.remove('hidden');
    setTimeout(() => { popup.classList.remove('popup-hide'); popup.classList.add('popup-show'); }, 10);
    document.body.classList.add('overflow-hidden');
}

function updateVariantPickerPrice() {
    const popup = document.getElementById('variant-picker-popup');
    let basePrice = parseFloat(popup.dataset.basePrice) || 0;
    let finalPrice = basePrice;
    let hasVariation = false;
    
    document.querySelectorAll('.vp-radio:checked').forEach(r => {
        const type = r.dataset.type;
        if (type === 'variation') {
            finalPrice = parseFloat(r.dataset.abs) || 0;
            hasVariation = true;
        }
    });
    
    if (!hasVariation) finalPrice = basePrice;
    
    document.querySelectorAll('.vp-radio:checked').forEach(r => {
        if (r.dataset.type === 'addon') {
            finalPrice += parseFloat(r.dataset.adj) || 0;
        }
    });
    
    const qty = parseInt(document.getElementById('vp-qty')?.value) || 1;
    document.getElementById('vp-current-price').textContent = CURRENCY + ' ' + Number(finalPrice * qty).toLocaleString();
}

// Toggle addon checkbox on tap
function toggleAddon(e) {
    const cb = e.currentTarget.querySelector('input[type="checkbox"]');
    if (!cb || cb.disabled) return;
    // The label click already toggles - no extra action needed
}

function changeVpQty(d) {
    const inp = document.getElementById('vp-qty');
    inp.value = Math.max(1, Math.min(99, parseInt(inp.value) + d));
    updateVariantPickerPrice(); // Update price on qty change
}

function closeVariantPicker(e) {
    if (e && e.target !== e.currentTarget) return;
    const popup = document.getElementById('variant-picker-popup');
    popup.classList.remove('popup-show');
    popup.classList.add('popup-hide');
    setTimeout(() => { popup.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }, 300);
}

function confirmVariantPicker() {
    const popup = document.getElementById('variant-picker-popup');
    const productId = parseInt(popup.dataset.productId);
    const mode = popup.dataset.mode;
    const qty = parseInt(document.getElementById('vp-qty').value) || 1;
    
    // Get all selected variants
    const checked = document.querySelectorAll('.vp-radio:checked');
    if (checked.length === 0) {
        showToast('অনুগ্রহ করে একটি অপশন নির্বাচন করুন');
        return;
    }
    
    // Check stock on all selected
    let outOfStock = false;
    checked.forEach(r => { if (parseInt(r.dataset.stock) <= 0) outOfStock = true; });
    if (outOfStock) { showToast('নির্বাচিত অপশন স্টকে নেই'); return; }
    
    // Collect all selected variant IDs
    const variantIds = [];
    checked.forEach(r => variantIds.push(parseInt(r.value)));
    
    closeVariantPicker();
    
    if (mode === 'cart') {
        addToCartAjax(productId, qty, variantIds.join(','));
    } else {
        openCheckoutPopup(productId, qty, variantIds.join(','));
    }
}

// ═══════════════════════════════════════════
// CART AJAX
// ═══════════════════════════════════════════

function addToCartAjax(productId, qty, variantId, customerUpload) {
    qty = qty || 1;
    const body = { action: 'add', product_id: productId, quantity: qty };
    if (variantId) body.variant_id = variantId;
    if (customerUpload) body.customer_upload = customerUpload;
    
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
            openSlideCart();
        } else {
            showToast(data.message || 'কার্টে যোগ করতে সমস্যা হয়েছে');
        }
    });
}

// ── Slide Cart Drawer ──
let slideCartTimer = null;
function openSlideCart() {
    // Don't show slide cart if checkout popup is already open
    const checkoutPopup = document.getElementById('checkout-popup');
    if (checkoutPopup && !checkoutPopup.classList.contains('hidden')) return;
    
    fetch(SITE_URL + '/api/cart.php?action=get')
    .then(r => r.json())
    .then(data => {
        if (!data.items || data.items.length === 0) return;
        
        let html = '';
        data.items.forEach(item => {
            const isBundle = item.is_bundle || false;
            const variantTag = item.variant_name ? `<span class="text-xs text-gray-400 block">${item.variant_name}</span>` : '';
            const bundleTag = isBundle ? `<span class="inline-flex items-center gap-1 text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded font-medium"><i class="fas fa-gift"></i>বান্ডেল</span> ` : '';
            html += `<div class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0">
                <img src="${item.image}" class="w-14 h-14 rounded-lg object-cover border flex-shrink-0" 
                     onerror="this.src='${SITE_URL}/assets/img/default-product.svg'" alt="">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${bundleTag}${item.name}</p>
                    ${variantTag}
                    <p class="text-xs text-gray-400 mt-0.5">${item.quantity} × ${CURRENCY} ${Number(item.price).toLocaleString()}</p>
                </div>
                <span class="text-sm font-bold whitespace-nowrap" style="color:var(--primary)">${CURRENCY} ${(item.price * item.quantity).toLocaleString()}</span>
            </div>`;
        });
        
        document.getElementById('slide-cart-items').innerHTML = html;
        document.getElementById('slide-cart-total').textContent = CURRENCY + ' ' + Number(data.total).toLocaleString();
        document.getElementById('slide-cart-count').textContent = data.count;
        
        const drawer = document.getElementById('slide-cart-drawer');
        const panel = document.getElementById('slide-cart-panel');
        drawer.classList.remove('hidden');
        requestAnimationFrame(() => {
            drawer.querySelector('.slide-cart-overlay').classList.add('opacity-100');
            panel.classList.remove('translate-x-full');
        });
        
        clearTimeout(slideCartTimer);
        slideCartTimer = setTimeout(() => closeSlideCart(), 2500);
    });
}

function closeSlideCart() {
    clearTimeout(slideCartTimer);
    const drawer = document.getElementById('slide-cart-drawer');
    if (!drawer) return;
    const panel = document.getElementById('slide-cart-panel');
    drawer.querySelector('.slide-cart-overlay')?.classList.remove('opacity-100');
    panel?.classList.add('translate-x-full');
    setTimeout(() => drawer.classList.add('hidden'), 300);
}

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('slide-cart-panel');
    if (panel) {
        panel.addEventListener('mouseenter', () => clearTimeout(slideCartTimer));
        panel.addEventListener('mouseleave', () => { slideCartTimer = setTimeout(() => closeSlideCart(), 1500); });
        panel.addEventListener('touchstart', () => clearTimeout(slideCartTimer));
    }
});

// ═══════════════════════════════════════════
// CHECKOUT POPUP
// ═══════════════════════════════════════════

function openCheckoutPopup(productId, qty, variantId, customerUpload) {
    if (productId) {
        const body = { action: 'add', product_id: productId, quantity: qty || 1 };
        if (ORDER_NOW_CLEAR_CART) body.clear_first = true;
        if (variantId) body.variant_id = variantId;
        if (customerUpload) body.customer_upload = customerUpload;
        
        fetch(SITE_URL + '/api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
            }
            showCheckoutModal();
        });
    } else {
        showCheckoutModal();
    }
}

function showCheckoutModal() {
    fetch(SITE_URL + '/api/cart.php?action=get')
    .then(r => r.json())
    .then(data => {
        if (!data.items || data.items.length === 0) {
            showToast('কার্ট খালি আছে!');
            return;
        }
        
        let html = '';
        data.items.forEach(item => {
            const isBundle = item.is_bundle || false;
            const salePrice = parseFloat(item.price) || 0;
            const qty = parseInt(item.quantity) || 1;
            
            // Only bundles get discount display in checkout
            // Regular products: sale price IS the listed price, not a "discount"
            const bundleSeparate = isBundle ? (parseFloat(item.bundle_separate || item.regular_price) || salePrice) : salePrice;
            const bundleSavings = isBundle ? (parseFloat(item.bundle_savings) || Math.max(0, bundleSeparate - salePrice)) : 0;
            const bundleDiscPct = isBundle && bundleSeparate > 0 && bundleSavings > 0
                ? (parseInt(item.bundle_discount_pct) || Math.round((bundleSavings / bundleSeparate) * 100))
                : 0;
            
            const variantTag = item.variant_name ? `<span class="text-xs text-gray-400 block truncate">${item.variant_name}</span>` : '';
            const bundleTag = isBundle ? `<span class="inline-flex items-center gap-1 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-medium"><i class="fas fa-gift"></i>বান্ডেল</span> ` : '';
            
            // Price display: bundles show strikethrough of separate price, regular items just show price
            const priceDisplay = isBundle && bundleSavings > 0
                ? `<span class="text-xs text-gray-400 line-through mr-1">${CURRENCY}${Number(bundleSeparate).toLocaleString()}</span>${CURRENCY}${Number(salePrice).toLocaleString()}`
                : `${CURRENCY}${Number(salePrice).toLocaleString()}`;
            const discBadge = isBundle && bundleDiscPct > 0 ? `<span class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-bold ml-1">${bundleDiscPct}% OFF</span>` : '';
            
            const lineTotal = salePrice * qty;
            const lineSaved = bundleSavings * qty;
            
            html += `<div class="flex items-center gap-2.5 checkout-cart-item" data-key="${item.key}" data-price="${salePrice}" data-bundle-savings="${bundleSavings}" data-is-bundle="${isBundle ? 1 : 0}">
                <img src="${item.image}" class="w-11 h-11 rounded-lg object-cover border flex-shrink-0" alt="">
                <div class="flex-1 min-w-0 overflow-hidden">
                    <p class="text-sm font-medium leading-tight" style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;word-break:break-all">${bundleTag}${item.name}${discBadge}</p>
                    ${variantTag}
                    <div class="flex items-center gap-1.5 mt-1">
                        <button type="button" onclick="checkoutQty('${item.key}',-1)" class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold flex-shrink-0">−</button>
                        <span class="text-sm font-semibold item-qty" data-key="${item.key}">${qty}</span>
                        <button type="button" onclick="checkoutQty('${item.key}',1)" class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold flex-shrink-0">+</button>
                        <span class="text-xs text-gray-400 ml-0.5">× ${priceDisplay}</span>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 flex flex-col items-end gap-0.5 ml-1">
                    <span class="text-sm font-semibold whitespace-nowrap item-total" data-key="${item.key}">${CURRENCY}${lineTotal.toLocaleString()}</span>
                    ${lineSaved > 0 ? `<span class="text-[10px] text-green-600 font-medium whitespace-nowrap item-save-tag">সেভ ${CURRENCY}${lineSaved.toLocaleString()}</span>` : ''}
                    <button type="button" onclick="checkoutRemove('${item.key}')" class="text-red-400 hover:text-red-600 text-xs px-1"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>`;
        });
        document.getElementById('popup-cart-summary').innerHTML = html;
        
        // Reset coupon state
        _appliedCoupon = null;
        document.getElementById('coupon-input').value = '';
        document.getElementById('coupon-status').classList.add('hidden');
        document.getElementById('coupon-discount-row').classList.add('hidden');
        document.getElementById('coupon-section').classList.add('hidden');
        document.getElementById('checkout-error')?.classList.add('hidden');
        
        // Reset credit state
        const _creditCb = document.getElementById('use-store-credit');
        if (_creditCb) _creditCb.checked = false;
        const _creditInput = document.getElementById('store-credit-amount');
        if (_creditInput) _creditInput.value = '0';
        document.getElementById('credit-applied-row')?.classList.add('hidden');
        
        // Debug: log credit hidden input values
        console.log('[CHECKOUT OPEN] credit max TK=' + (document.getElementById('store-credit-max')?.value || 'N/A') + 
            ', credits=' + (document.getElementById('store-credit-credits')?.value || 'N/A') +
            ', rate=' + (document.getElementById('store-credit-rate')?.value || 'N/A') +
            ', checkbox exists=' + (!!_creditCb));
        
        updatePopupTotals(data.total);
        
        // Load upsells for products in cart
        loadUpsells(data.items.map(i => i.product_id));
        
        const popup = document.getElementById('checkout-popup');
        popup.classList.remove('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('checkout-success').classList.add('hidden');
        setTimeout(() => { popup.classList.remove('popup-hide'); popup.classList.add('popup-show'); }, 10);
        document.body.classList.add('overflow-hidden');
        
        // Autofill from saved info
        autofillCheckout();
        
        // Track incomplete order
        trackIncomplete('cart', data);
    });
}

// ═══════════════════════════════════════════
// COUPON SYSTEM
// ═══════════════════════════════════════════
let _appliedCoupon = null;

function toggleCoupon() {
    const sec = document.getElementById('coupon-section');
    const chev = document.getElementById('coupon-chevron');
    sec.classList.toggle('hidden');
    chev.style.transform = sec.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function applyCoupon() {
    const code = document.getElementById('coupon-input').value.trim();
    if (!code) { showToast('কুপন কোড দিন'); return; }
    
    const btn = document.getElementById('coupon-apply-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    const subtotal = parseInt(document.getElementById('popup-subtotal').textContent.replace(/[^\d]/g, '')) || 0;
    
    fetch(SITE_URL + '/api/coupon.php?action=validate&code=' + encodeURIComponent(code) + '&subtotal=' + subtotal)
    .then(r => r.json())
    .then(data => {
        const status = document.getElementById('coupon-status');
        status.classList.remove('hidden');
        
        if (data.success) {
            _appliedCoupon = data;
            status.innerHTML = `<div class="flex items-center justify-between bg-green-50 rounded-lg p-2">
                <span class="text-green-700"><i class="fas fa-check-circle mr-1"></i> ${data.message}</span>
                <button type="button" onclick="removeCoupon()" class="text-red-400 hover:text-red-600 text-xs ml-2">✕ বাতিল</button>
            </div>`;
            document.getElementById('coupon-input').readOnly = true;
            updatePopupTotals(subtotal);
        } else {
            _appliedCoupon = null;
            status.innerHTML = `<p class="text-red-500"><i class="fas fa-times-circle mr-1"></i> ${data.message}</p>`;
        }
        
        btn.disabled = false;
        btn.textContent = 'প্রয়োগ';
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'প্রয়োগ';
    });
}

function removeCoupon() {
    _appliedCoupon = null;
    document.getElementById('coupon-input').value = '';
    document.getElementById('coupon-input').readOnly = false;
    document.getElementById('coupon-status').classList.add('hidden');
    document.getElementById('coupon-discount-row').classList.add('hidden');
    const subtotal = parseInt(document.getElementById('popup-subtotal').textContent.replace(/[^\d]/g, '')) || 0;
    updatePopupTotals(subtotal);
}

// ── Checkout Cart Qty Controls (Issue #3) ──
function checkoutQty(key, delta) {
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'update', key: key, delta: delta})
    }).then(r=>r.json()).then(data => {
        if (data.removed) {
            document.querySelector(`.checkout-cart-item[data-key="${key}"]`)?.remove();
            if (!document.querySelector('.checkout-cart-item')) { closeCheckoutPopup(); showToast('কার্ট খালি!'); return; }
        } else if (data.success) {
            const qtyEl = document.querySelector(`.item-qty[data-key="${key}"]`);
            const totalEl = document.querySelector(`.item-total[data-key="${key}"]`);
            const row = document.querySelector(`.checkout-cart-item[data-key="${key}"]`);
            if (qtyEl) qtyEl.textContent = data.quantity;
            if (totalEl) totalEl.textContent = CURRENCY + ' ' + Number(data.item_total).toLocaleString();
            // Update per-item bundle savings
            if (row) {
                const bundleSave = parseFloat(row.dataset.bundleSavings) || 0;
                const saved = bundleSave * data.quantity;
                const saveEl = row.querySelector('.item-save-tag');
                if (saveEl && saved > 0) saveEl.textContent = 'সেভ ' + CURRENCY + saved.toLocaleString();
            }
        }
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
        updatePopupTotals(data.cart_total);
    });
}
function checkoutRemove(key) {
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'remove', key: key})
    }).then(r=>r.json()).then(data => {
        document.querySelector(`.checkout-cart-item[data-key="${key}"]`)?.remove();
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.cart_count);
        if (!document.querySelector('.checkout-cart-item')) { closeCheckoutPopup(); showToast('কার্ট খালি!'); return; }
        updatePopupTotals(data.cart_total);
    });
}

// ── Autofill from localStorage (Issue #5) ──
function autofillCheckout() {
    try {
        const saved = JSON.parse(localStorage.getItem('_checkout_info') || '{}');
        const form = document.getElementById('checkout-form');
        if (!form) return;
        ['name','phone','address'].forEach(f => {
            const inp = form.querySelector(`[name="${f}"]`);
            if (inp && !inp.value && saved[f]) inp.value = saved[f];
        });
        if (saved.shipping_area) {
            const radio = form.querySelector(`[name="shipping_area"][value="${saved.shipping_area}"]`);
            if (radio) radio.checked = true;
        }
    } catch(e) {}
}

function fillSavedAddress(jsonStr) {
    if (!jsonStr) return;
    try {
        const addr = JSON.parse(jsonStr);
        const form = document.getElementById('checkout-form');
        if (!form) return;
        if (addr.name) { const el = form.querySelector('[name="name"]'); if (el) el.value = addr.name; }
        if (addr.phone) { const el = form.querySelector('[name="phone"]'); if (el) el.value = addr.phone; }
        if (addr.address) { const el = form.querySelector('[name="address"]'); if (el) el.value = addr.address; }
        // Auto-detect shipping area from city
        if (addr.city) {
            const cityLower = addr.city.toLowerCase();
            let area = 'outside_dhaka';
            if (cityLower === 'dhaka' || cityLower.includes('ঢাকা')) area = 'inside_dhaka';
            const radio = form.querySelector(`[name="shipping_area"][value="${area}"]`);
            if (radio) { radio.checked = true; updatePopupTotals(getCurrentSubtotal()); }
        }
    } catch(e) { console.error('fillSavedAddress error', e); }
}

function getCurrentSubtotal() {
    let sub = 0;
    document.querySelectorAll('.checkout-cart-item').forEach(el => {
        const p = parseFloat(el.dataset.price) || 0;
        const q = parseInt(el.querySelector('.item-qty')?.textContent) || 1;
        sub += p * q;
    });
    return sub;
}
function saveCheckoutInfo() {
    try {
        const form = document.getElementById('checkout-form');
        if (!form) return;
        const info = {};
        ['name','phone','address'].forEach(f => {
            const inp = form.querySelector(`[name="${f}"]`);
            if (inp && inp.value) info[f] = inp.value;
        });
        const area = form.querySelector('[name="shipping_area"]:checked');
        if (area) info.shipping_area = area.value;
        localStorage.setItem('_checkout_info', JSON.stringify(info));
    } catch(e) {}
}

// ── Copy Order Number (Issue #7) ──
function copyOrderNumber() {
    const num = document.getElementById('success-order-number')?.textContent;
    if (num) {
        navigator.clipboard.writeText(num).then(() => {
            const btn = document.getElementById('copy-order-btn');
            if (btn) { btn.innerHTML = '<i class="fas fa-check text-green-500"></i>'; setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500); }
        });
    }
}

function updatePopupTotals(subtotal) {
    const area = document.querySelector('input[name="shipping_area"]:checked')?.value || 'outside_dhaka';
    let shipping = area === 'inside_dhaka' ? SHIPPING_INSIDE : (area === 'dhaka_sub' ? SHIPPING_DHAKA_SUB : SHIPPING_OUTSIDE);
    if (subtotal >= FREE_SHIPPING_MIN) shipping = 0;
    
    // Calculate ONLY bundle-specific discounts (not sale price differences)
    let totalBundleSavings = 0;
    document.querySelectorAll('.checkout-cart-item').forEach(el => {
        const bundleSave = parseFloat(el.dataset.bundleSavings) || 0;
        const qty = parseInt(el.querySelector('.item-qty')?.textContent) || 1;
        if (bundleSave > 0) totalBundleSavings += bundleSave * qty;
    });
    
    // Show bundle discount row only if there are bundle savings
    if (totalBundleSavings > 0) {
        document.getElementById('product-discount-row').classList.remove('hidden');
        document.getElementById('popup-product-discount').textContent = '-' + CURRENCY + ' ' + Number(Math.round(totalBundleSavings)).toLocaleString();
        // Show original (pre-bundle-discount) subtotal
        document.getElementById('original-price-row').classList.remove('hidden');
        document.getElementById('popup-original').textContent = CURRENCY + ' ' + Number(Math.round(subtotal + totalBundleSavings)).toLocaleString();
    } else {
        document.getElementById('original-price-row').classList.add('hidden');
        document.getElementById('product-discount-row').classList.add('hidden');
    }
    
    // Coupon discount
    let couponDiscount = 0;
    if (_appliedCoupon) {
        if (_appliedCoupon.free_shipping) {
            shipping = 0;
            couponDiscount = 0;
        } else {
            couponDiscount = _appliedCoupon.discount || 0;
        }
        document.getElementById('coupon-discount-row').classList.remove('hidden');
        document.getElementById('popup-discount').textContent = '-' + CURRENCY + ' ' + Number(couponDiscount).toLocaleString();
    } else {
        document.getElementById('coupon-discount-row').classList.add('hidden');
    }
    
    // Total savings = bundle discount + coupon discount
    const totalSavings = Math.round(totalBundleSavings) + couponDiscount;
    if (totalSavings > 0) {
        document.getElementById('total-savings-row').classList.remove('hidden');
        document.getElementById('popup-total-savings').textContent = CURRENCY + ' ' + Number(totalSavings).toLocaleString();
    } else {
        document.getElementById('total-savings-row').classList.add('hidden');
    }
    
    document.getElementById('popup-subtotal').textContent = CURRENCY + ' ' + Number(subtotal).toLocaleString();
    document.getElementById('popup-shipping').textContent = CURRENCY + ' ' + Number(shipping).toLocaleString();
    
    // Store credit deduction (credit → TK conversion)
    let creditUsed = 0;
    const creditCheckbox = document.getElementById('use-store-credit');
    const creditMaxTk = parseFloat(document.getElementById('store-credit-max')?.value || 0);
    const creditTotal = parseFloat(document.getElementById('store-credit-credits')?.value || 0);
    const creditRate = parseFloat(document.getElementById('store-credit-rate')?.value || 0.75);
    console.log('[CREDIT CALC] checkbox=' + (creditCheckbox ? creditCheckbox.checked : 'N/A') + ', maxTk=' + creditMaxTk + ', credits=' + creditTotal + ', rate=' + creditRate);
    if (creditCheckbox && creditCheckbox.checked && creditMaxTk > 0) {
        const beforeCredit = subtotal + shipping - couponDiscount;
        creditUsed = Math.min(creditMaxTk, beforeCredit); // Can't exceed total, capped at TK equivalent
        creditUsed = Math.max(0, Math.round(creditUsed));
        const creditsBeingUsed = creditRate > 0 ? Math.ceil(creditUsed / creditRate) : 0;
        document.getElementById('credit-applied-row')?.classList.remove('hidden');
        const creditEl = document.getElementById('popup-credit');
        if (creditEl) creditEl.textContent = '-' + CURRENCY + ' ' + Number(creditUsed).toLocaleString() + ' (' + creditsBeingUsed + ' ক্রেডিট)';
        document.getElementById('store-credit-amount').value = creditUsed;
    } else {
        document.getElementById('credit-applied-row')?.classList.add('hidden');
        const scInput = document.getElementById('store-credit-amount');
        if (scInput) scInput.value = 0;
    }
    
    document.getElementById('popup-total').textContent = CURRENCY + ' ' + Number(subtotal + shipping - couponDiscount - creditUsed).toLocaleString();
}

function toggleStoreCredit() {
    const cb = document.getElementById('use-store-credit');
    console.log('[CREDIT TOGGLE] checked=' + (cb ? cb.checked : 'N/A'));
    // Recalculate totals
    let subtotal = 0;
    document.querySelectorAll('.checkout-cart-item').forEach(el => {
        const price = parseFloat(el.dataset.price) || 0;
        const qty = parseInt(el.querySelector('.item-qty')?.textContent) || 1;
        subtotal += price * qty;
    });
    updatePopupTotals(subtotal);
}

// ═══════════════════════════════════════════
// UPSELL PRODUCTS IN CHECKOUT
// ═══════════════════════════════════════════
function loadUpsells(productIds) {
    const sec = document.getElementById('upsell-section');
    const cont = document.getElementById('upsell-products');
    if (!sec || !cont) return; // Upsells not in checkout fields — skip silently
    sec.classList.add('hidden');
    cont.innerHTML = '';
    
    if (!productIds || !productIds.length) return;
    
    const limit = parseInt(sec.dataset.count) || 4;
    const ids = [...new Set(productIds)].join(',');
    fetch(SITE_URL + '/api/cart.php?action=get_upsells&product_ids=' + ids + '&limit=' + limit)
    .then(r => r.json())
    .then(data => {
        if (!data.products || !data.products.length) return;
        
        // Filter out products already in cart
        const cartIds = productIds.map(Number);
        const filtered = data.products.filter(p => !cartIds.includes(p.id));
        if (!filtered.length) return;
        
        let html = '';
        filtered.forEach(p => {
            html += `<div class="flex items-center gap-3 bg-orange-50 rounded-lg p-2.5 border border-orange-100">
                <img src="${p.image}" class="w-12 h-12 rounded-lg object-cover border flex-shrink-0" alt="">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium" style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden">${p.name}</p>
                    <p class="text-sm font-bold" style="color:var(--primary)">${CURRENCY}${Number(p.price).toLocaleString()}</p>
                </div>
                <button type="button" onclick="addUpsellToCart(${p.id}, this)" 
                        class="px-3 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600 transition whitespace-nowrap flex-shrink-0">
                    <i class="fas fa-plus mr-1"></i>যোগ করুন
                </button>
            </div>`;
        });
        cont.innerHTML = html;
        sec.classList.remove('hidden');
    })
    .catch(err => { console.log('Upsell load error:', err); });
}

function addUpsellToCart(productId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    // Check variants first
    fetchVariants(productId).then(data => {
        if (data.has_variants) {
            closeCheckoutPopup();
            showVariantPicker(productId, data, 'cart');
        } else {
            // Add directly to cart API (don't use addToCartAjax which opens slide cart)
            fetch(SITE_URL + '/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = d.cart_count);
                    btn.innerHTML = '<i class="fas fa-times mr-1"></i>সরান';
                    btn.classList.replace('bg-orange-500', 'bg-red-500');
                    btn.classList.replace('hover:bg-orange-600', 'hover:bg-red-600');
                    btn.disabled = false;
                    btn.onclick = function() { removeUpsellFromCart(productId, this); };
                    refreshCheckoutCart();
                } else {
                    btn.innerHTML = '<i class="fas fa-plus mr-1"></i>যোগ করুন';
                    btn.disabled = false;
                    showToast(d.message || 'সমস্যা হয়েছে');
                }
            })
            .catch(() => { btn.innerHTML = '<i class="fas fa-plus mr-1"></i>যোগ করুন'; btn.disabled = false; });
        }
    });
}

function removeUpsellFromCart(productId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(SITE_URL + '/api/cart.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'remove_by_product', product_id: productId})
    }).then(r => r.json()).then(d => {
        btn.innerHTML = '<i class="fas fa-plus mr-1"></i>যোগ করুন';
        btn.classList.replace('bg-red-500', 'bg-orange-500');
        btn.classList.replace('hover:bg-red-600', 'hover:bg-orange-600');
        btn.disabled = false;
        btn.onclick = function() { addUpsellToCart(productId, this); };
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = d.cart_count);
        refreshCheckoutCart();
    }).catch(() => { btn.disabled = false; });
}

function refreshCheckoutCart() {
    fetch(SITE_URL + '/api/cart.php?action=get').then(r=>r.json()).then(d => {
        if (!d.items || !d.items.length) { closeCheckoutPopup(); showToast('কার্ট খালি!'); return; }
        let html = '';
        d.items.forEach(item => {
            const variantTag = item.variant_name ? `<span class="text-xs text-gray-400 block truncate">${item.variant_name}</span>` : '';
            html += `<div class="flex items-center gap-2.5 checkout-cart-item" data-key="${item.key}" data-price="${item.price}">
                <img src="${item.image}" class="w-11 h-11 rounded-lg object-cover border flex-shrink-0" alt="">
                <div class="flex-1 min-w-0 overflow-hidden">
                    <p class="text-sm font-medium leading-tight" style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;word-break:break-all">${item.name}</p>
                    ${variantTag}
                    <div class="flex items-center gap-1.5 mt-1">
                        <button type="button" onclick="checkoutQty('${item.key}',-1)" class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold flex-shrink-0">−</button>
                        <span class="text-sm font-semibold item-qty" data-key="${item.key}">${item.quantity}</span>
                        <button type="button" onclick="checkoutQty('${item.key}',1)" class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold flex-shrink-0">+</button>
                        <span class="text-xs text-gray-400 ml-0.5">× ${CURRENCY}${Number(item.price).toLocaleString()}</span>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 flex flex-col items-end gap-0.5 ml-1">
                    <span class="text-sm font-semibold whitespace-nowrap item-total" data-key="${item.key}">${CURRENCY}${(item.price * item.quantity).toLocaleString()}</span>
                    <button type="button" onclick="checkoutRemove('${item.key}')" class="text-red-400 hover:text-red-600 text-xs px-1"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>`;
        });
        document.getElementById('popup-cart-summary').innerHTML = html;
        updatePopupTotals(d.total);
        document.querySelectorAll('.cart-count').forEach(el => el.textContent = d.count);
    });
}

document.querySelectorAll('input[name="shipping_area"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const sub = parseInt(document.getElementById('popup-subtotal').textContent.replace(/[^\d]/g, '')) || 0;
        updatePopupTotals(sub);
    });
});

// Also send coupon code with order form
function getCheckoutFormData() {
    const form = document.getElementById('checkout-form');
    const formData = new FormData(form);
    if (_appliedCoupon) {
        formData.set('coupon_code', _appliedCoupon.code);
    }
    // Ensure store credit value is explicitly set from current state
    const creditCb = document.getElementById('use-store-credit');
    const creditInput = document.getElementById('store-credit-amount');
    if (creditCb && creditCb.checked && creditInput && parseFloat(creditInput.value) > 0) {
        formData.set('store_credit_used', creditInput.value);
        console.log('[CREDIT] FormData set store_credit_used=' + creditInput.value);
    } else {
        formData.set('store_credit_used', '0');
    }
    return formData;
}

function closeCheckoutPopup(e) {
    if (e && e.target !== e.currentTarget) return;
    const popup = document.getElementById('checkout-popup');
    popup.classList.remove('popup-show');
    popup.classList.add('popup-hide');
    setTimeout(() => { popup.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }, 300);
}

// Submit Order
document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('checkout-submit-btn');
    const errBox = document.getElementById('checkout-error');
    const errMsg = document.getElementById('checkout-error-msg');
    const btnLabel = btn.innerHTML;
    
    // Hide previous error
    errBox?.classList.add('hidden');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> অপেক্ষা করুন...';
    
    const formData = getCheckoutFormData();
    
    // Explicitly capture credit value (in case FormData missed it)
    const _scInput = document.getElementById('store-credit-amount');
    const _scCb = document.getElementById('use-store-credit');
    if (_scInput) {
        const creditVal = _scInput.value;
        formData.set('store_credit_used', creditVal);
        console.log('[CREDIT] Sending store_credit_used=' + creditVal + ' (checkbox=' + (_scCb ? _scCb.checked : 'N/A') + ')');
    }
    
    // Save info for autofill next time
    saveCheckoutInfo();
    
    fetch(SITE_URL + '/api/order.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text()) // Get text first to handle non-JSON
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            // Server returned non-JSON (PHP error)
            console.error('Order API returned non-JSON:', text.substring(0, 500));
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            console.log('[ORDER SUCCESS] credit_used_tk=' + (data.credit_used_tk || 0) + ', credits_deducted=' + (data.credits_deducted || 0));
            document.getElementById('checkout-form').classList.add('hidden');
            document.getElementById('checkout-success').classList.remove('hidden');
            document.getElementById('success-order-number').textContent = data.order_number;
            
            // Show credit deduction notification
            if (data.credit_used_tk > 0 && data.credits_deducted > 0) {
                showCreditNotification(data.credits_deducted, data.credit_used_tk);
            }
            // Show merged order message
            const mergeMsg = document.getElementById('success-merge-msg');
            if (data.merged && mergeMsg) {
                mergeMsg.classList.remove('hidden');
            } else if (mergeMsg) {
                mergeMsg.classList.add('hidden');
            }
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = '0');
            try { sessionStorage.removeItem('_incomplete_tracked'); } catch(e) {}
        } else {
            let msg = data.message || 'অর্ডার করতে সমস্যা হয়েছে!';
            // Show debug info if available
            if (data.debug) {
                console.error('Order API debug:', data.debug);
                msg += '\n\n⚠ Debug: ' + data.debug;
            }
            if (errBox && errMsg) {
                errMsg.textContent = msg;
                errBox.classList.remove('hidden');
                errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            showToast(data.message || 'অর্ডার করতে সমস্যা হয়েছে!');
            btn.disabled = false;
            btn.innerHTML = btnLabel;
        }
    })
    .catch(err => {
        const msg = 'সার্ভার সমস্যা! আবার চেষ্টা করুন।';
        if (errBox && errMsg) {
            errMsg.textContent = msg;
            errBox.classList.remove('hidden');
            errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        showToast(msg);
        btn.disabled = false;
        btn.innerHTML = btnLabel;
        console.error('Order submit error:', err);
    });
});
// ── Incomplete Order Tracking (Issue #8) ──
function trackIncomplete(step, data) {
    try {
        const form = document.getElementById('checkout-form');
        const name = form?.querySelector('[name="name"]')?.value || '';
        const phone = form?.querySelector('[name="phone"]')?.value || '';
        const address = form?.querySelector('[name="address"]')?.value || '';
        const cartItems = document.querySelectorAll('.checkout-cart-item');
        let cartJson = '[]';
        let total = 0;
        try {
            const items = [];
            cartItems.forEach(el => {
                const qty = parseInt(el.querySelector('.item-qty')?.textContent) || 1;
                const price = parseFloat(el.dataset.price) || 0;
                items.push({key: el.dataset.key, qty, price});
                total += qty * price;
            });
            cartJson = JSON.stringify(items);
        } catch(e) {}
        if (data && data.total) total = data.total;
        
        const body = new FormData();
        body.append('action', 'track_incomplete');
        body.append('step', step);
        body.append('cart', cartJson);
        body.append('total', total);
        body.append('name', name);
        body.append('phone', phone);
        body.append('address', address);
        fetch(SITE_URL + '/api/track.php', {method: 'POST', body: body});
    } catch(e) {}
}

// Track when customer fills info
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkout-form');
    if (form) {
        let infoTracked = false;
        form.querySelectorAll('input, textarea').forEach(inp => {
            inp.addEventListener('blur', () => {
                const phone = form.querySelector('[name="phone"]')?.value;
                if (phone && phone.length >= 11 && !infoTracked) {
                    infoTracked = true;
                    trackIncomplete('info', null);
                }
            });
        });
    }
});

// ── Enhanced Visitor Data Beacon (Issue #4) ──
(function() {
    if (sessionStorage.getItem('_vd_sent')) return;
    sessionStorage.setItem('_vd_sent', '1');
    setTimeout(() => {
        const params = new URLSearchParams(window.location.search);
        const body = new FormData();
        body.append('action', 'track_visitor_data');
        body.append('screen_width', screen.width);
        body.append('screen_height', screen.height);
        body.append('language', navigator.language || '');
        try { body.append('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone); } catch(e) {}
        try { body.append('connection_type', navigator.connection?.effectiveType || ''); } catch(e) {}
        body.append('is_touch', 'ontouchstart' in window ? 1 : 0);
        body.append('platform', navigator.platform || '');
        body.append('color_depth', screen.colorDepth || 0);
        body.append('cookies_enabled', navigator.cookieEnabled ? 1 : 0);
        if (params.get('utm_source')) body.append('utm_source', params.get('utm_source'));
        if (params.get('utm_medium')) body.append('utm_medium', params.get('utm_medium'));
        if (params.get('utm_campaign')) body.append('utm_campaign', params.get('utm_campaign'));
        fetch(SITE_URL + '/api/track.php', {method: 'POST', body: body});
    }, 2000);
})();

</script>

<!-- Variant Picker Popup -->
<div id="variant-picker-popup" class="popup-overlay hidden fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center popup-hide" onclick="closeVariantPicker(event)">
    <div class="popup-content bg-white w-full sm:max-w-md sm:rounded-2xl rounded-t-2xl max-h-[85vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="sticky top-0 bg-white border-b px-5 py-3 flex items-center justify-between rounded-t-2xl z-10">
            <h3 class="text-lg font-bold">অপশন নির্বাচন করুন</h3>
            <button onclick="closeVariantPicker()" class="p-1.5 hover:bg-gray-100 rounded-full"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        
        <div class="p-5 space-y-4">
            <!-- Product Info -->
            <div class="flex items-center gap-4 pb-4 border-b">
                <img id="vp-product-image" src="" class="w-20 h-20 rounded-xl object-cover border" alt="">
                <div class="flex-1 min-w-0">
                    <h4 id="vp-product-name" class="font-semibold text-gray-800 truncate"></h4>
                    <div class="flex items-center gap-2 mt-1">
                        <span id="vp-current-price" class="text-xl font-bold" style="color:var(--primary)"></span>
                        <span id="vp-regular-price" class="text-sm text-gray-400 line-through hidden"></span>
                    </div>
                    <span id="vp-base-price" class="hidden"></span>
                </div>
            </div>
            
            <!-- Variant Options -->
            <div id="vp-options"></div>
            
            <!-- Quantity -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">পরিমাণ</label>
                <div class="inline-flex items-center border-2 rounded-xl overflow-hidden">
                    <button onclick="changeVpQty(-1)" class="w-10 h-10 flex items-center justify-center hover:bg-gray-100 text-gray-600"><i class="fas fa-minus text-sm"></i></button>
                    <input type="number" id="vp-qty" value="1" min="1" max="99" class="w-14 h-10 text-center border-x font-semibold focus:outline-none">
                    <button onclick="changeVpQty(1)" class="w-10 h-10 flex items-center justify-center hover:bg-gray-100 text-gray-600"><i class="fas fa-plus text-sm"></i></button>
                </div>
            </div>
            
            <!-- Confirm Button -->
            <button onclick="confirmVariantPicker()" 
                    class="w-full py-3.5 rounded-xl text-white font-bold text-base transition transform active:scale-[0.98]"
                    style="background:var(--btn-primary)">
                <i class="fas fa-check-circle mr-2"></i> নিশ্চিত করুন
            </button>
        </div>
    </div>
</div>

<!-- Slide Cart Drawer -->
<div id="slide-cart-drawer" class="fixed inset-0 z-[60] hidden">
    <div class="slide-cart-overlay absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300" onclick="closeSlideCart()"></div>
    <div id="slide-cart-panel" class="absolute top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-out flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b bg-gradient-to-r from-blue-600 to-blue-700">
            <div class="flex items-center gap-2 text-white">
                <i class="fas fa-shopping-bag text-lg"></i>
                <h3 class="font-bold text-lg">কার্ট</h3>
                <span id="slide-cart-count" class="bg-white/20 text-white text-xs font-bold px-2 py-0.5 rounded-full">0</span>
            </div>
            <button onclick="closeSlideCart()" class="w-8 h-8 flex items-center justify-center rounded-full text-white/80 hover:text-white hover:bg-white/10 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Success Badge -->
        <div class="bg-green-50 border-b border-green-100 px-5 py-2.5 flex items-center gap-2">
            <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-check text-white text-xs"></i>
            </div>
            <span class="text-sm font-medium text-green-700">পণ্যটি কার্টে যোগ হয়েছে!</span>
        </div>
        
        <!-- Items -->
        <div id="slide-cart-items" class="flex-1 overflow-y-auto px-5 py-2"></div>
        
        <!-- Footer -->
        <div class="border-t p-5 bg-gray-50">
            <div class="flex justify-between items-center mb-4">
                <span class="font-medium text-gray-600">মোট:</span>
                <span id="slide-cart-total" class="text-xl font-bold" style="color:var(--primary)">৳ 0</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="<?= url('cart') ?>" class="py-2.5 rounded-xl border-2 border-gray-300 text-center font-medium text-sm text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-eye mr-1"></i> কার্ট দেখুন
                </a>
                <button onclick="closeSlideCart();openCheckoutPopup()" class="py-2.5 rounded-xl btn-primary text-center font-medium text-sm transition">
                    <i class="fas fa-check-circle mr-1"></i> অর্ডার করুন
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// ═══════════════════════════════════════════
// LIVE CHAT WIDGET
// ═══════════════════════════════════════════
$_chatEnabled = getSetting('chat_enabled', '0');
if ($_chatEnabled === '1'):
    $_chatColor = getSetting('chat_bubble_color', '#3b82f6');
    $_chatPos = getSetting('chat_bubble_position', 'bottom-right');
    $_chatRequireInfo = getSetting('chat_require_info', '0');
    $_chatBotName = getSetting('chat_bot_name', 'Support');
    $_chatHeadingName = getSetting('chat_heading_name', '') ?: getSetting('site_name', 'Support');
    $_posStyles = [
        'bottom-right' => 'bottom:20px;right:20px',
        'bottom-left' => 'bottom:20px;left:20px',
        'top-right' => 'top:20px;right:20px',
        'top-left' => 'top:20px;left:20px',
    ];
    $_windowPos = [
        'bottom-right' => 'bottom:80px;right:20px',
        'bottom-left' => 'bottom:80px;left:20px',
        'top-right' => 'top:80px;right:20px',
        'top-left' => 'top:80px;left:20px',
    ];
    $_bubblePos = $_posStyles[$_chatPos] ?? $_posStyles['bottom-right'];
    $_winPos = $_windowPos[$_chatPos] ?? $_windowPos['bottom-right'];
    $_isBottom = strpos($_chatPos, 'bottom') !== false;
?>

<!-- Chat Product Carousel CSS -->
<style>
.chat-carousel{display:flex;gap:10px;overflow-x:auto;padding:6px 2px 8px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch}
.chat-carousel::-webkit-scrollbar{height:3px}.chat-carousel::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:10px}
.chat-pcard{flex:0 0 140px;scroll-snap-align:start;background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;cursor:pointer;transition:box-shadow .2s}
.chat-pcard:hover{box-shadow:0 4px 12px rgba(0,0,0,.1)}
.chat-pcard-img{position:relative;width:100%;aspect-ratio:1;overflow:hidden;background:#f9fafb}
.chat-pcard-img img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
.chat-pcard:hover .chat-pcard-img img{transform:scale(1.05)}
.chat-pcard-badge{position:absolute;top:4px;left:4px;background:#ef4444;color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:6px}
.chat-pcard-info{padding:8px}
.chat-pcard-name{font-size:11px;font-weight:600;color:#1f2937;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height:28px}
.chat-pcard-price{margin-top:4px;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.chat-pcard-now{font-size:13px;font-weight:700;color:var(--primary,#3b82f6)}
.chat-pcard-was{font-size:10px;color:#9ca3af;text-decoration:line-through}
.chat-pcard-btns{display:flex;gap:4px;margin-top:6px}
.chat-pcard-cart{width:30px;height:28px;border-radius:8px;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;background:#fff;color:#6b7280;font-size:11px;cursor:pointer;transition:all .15s}
.chat-pcard-cart:hover{background:var(--primary,#3b82f6);color:#fff;border-color:var(--primary,#3b82f6)}
.chat-pcard-order{flex:1;height:28px;border-radius:8px;border:none;background:var(--primary,#3b82f6);color:#fff;font-size:11px;font-weight:600;cursor:pointer;transition:opacity .15s}
.chat-pcard-order:hover{opacity:.85}
</style>

<!-- Chat Bubble -->
<div id="chatBubble" onclick="toggleChatWidget()" class="fixed z-[60] cursor-pointer shadow-lg hover:shadow-xl transition-all hover:scale-105" style="<?= $_bubblePos ?>;<?= $_isBottom ? 'bottom:80px' : '' ?>">
    <div class="w-14 h-14 rounded-full flex items-center justify-center relative" style="background:<?= e($_chatColor) ?>">
        <svg id="chatBubbleIcon" class="w-6 h-6 text-white transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <svg id="chatBubbleClose" class="w-6 h-6 text-white hidden transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span id="chatUnreadBadge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center animate-pulse">0</span>
    </div>
</div>

<!-- Chat Window -->
<div id="chatWindow" class="fixed z-[60] hidden" style="<?= $_winPos ?>">
    <div class="bg-white rounded-2xl shadow-2xl border w-[360px] max-w-[calc(100vw-30px)] flex flex-col overflow-hidden" style="height:500px;max-height:calc(100vh-120px)">
        <!-- Header -->
        <div class="px-4 py-3 flex items-center justify-between flex-shrink-0 text-white rounded-t-2xl" style="background:<?= e($_chatColor) ?>">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm"><?= e($_chatHeadingName) ?></h4>
                    <p class="text-[10px] text-white/70"><span class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block mr-1"></span>Online now</p>
                </div>
            </div>
            <button onclick="toggleChatWidget()" class="p-1 hover:bg-white/20 rounded-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <?php if ($_chatRequireInfo === '1'): ?>
        <!-- Info Collection (for guests) -->
        <div id="chatInfoForm" class="<?= isCustomerLoggedIn() ? 'hidden' : '' ?> p-5 flex-1 flex flex-col justify-center">
            <div class="text-center mb-4">
                <div class="w-14 h-14 rounded-full mx-auto mb-3 flex items-center justify-center" style="background:<?= e($_chatColor) ?>20">
                    <svg class="w-7 h-7" style="color:<?= e($_chatColor) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h4 class="font-bold text-gray-800">চ্যাট শুরু করুন</h4>
                <p class="text-xs text-gray-500 mt-1">আপনার তথ্য দিন, আমরা সাহায্য করব</p>
            </div>
            <div class="space-y-3">
                <input type="text" id="chatGuestName" placeholder="আপনার নাম" class="w-full border rounded-xl px-4 py-2.5 text-sm">
                <input type="tel" id="chatGuestPhone" placeholder="মোবাইল নম্বর" class="w-full border rounded-xl px-4 py-2.5 text-sm">
                <button onclick="startChatWithInfo()" class="w-full py-2.5 rounded-xl text-white font-medium text-sm" style="background:<?= e($_chatColor) ?>">
                    চ্যাট শুরু করুন →
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <div id="chatMsgArea" class="<?= ($_chatRequireInfo === '1' && !isCustomerLoggedIn()) ? 'hidden' : '' ?> flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50"></div>

        <!-- Input -->
        <div id="chatInputArea" class="<?= ($_chatRequireInfo === '1' && !isCustomerLoggedIn()) ? 'hidden' : '' ?> px-3 py-2 border-t bg-white flex-shrink-0">
            <div class="flex items-end gap-2">
                <textarea id="chatUserInput" rows="1" placeholder="মেসেজ লিখুন..." 
                    class="flex-1 border rounded-xl px-3 py-2 text-sm resize-none max-h-20 focus:ring-1 focus:ring-blue-400 outline-none"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMsg()}"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,80)+'px'"></textarea>
                <button onclick="sendChatMsg()" class="p-2 rounded-xl text-white flex-shrink-0 transition hover:opacity-90" style="background:<?= e($_chatColor) ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
            <p class="text-[10px] text-gray-400 text-center mt-1">Powered by <?= e(getSetting('site_name', 'Store')) ?></p>
        </div>
    </div>
</div>

<script>
(function(){
    const CHAT_API = '<?= SITE_URL ?>/api/chat.php';
    let chatConvoId = null;
    let chatLastId = 0;
    let chatPollTimer = null;
    let chatOpen = false;

    window.toggleChatWidget = function() {
        const win = document.getElementById('chatWindow');
        const iconOpen = document.getElementById('chatBubbleIcon');
        const iconClose = document.getElementById('chatBubbleClose');
        chatOpen = !chatOpen;
        if (chatOpen) {
            win.classList.remove('hidden');
            iconOpen.classList.add('hidden'); iconClose.classList.remove('hidden');
            document.getElementById('chatUnreadBadge').classList.add('hidden');
            if (!chatConvoId) initChat();
            else document.getElementById('chatUserInput')?.focus();
        } else {
            win.classList.add('hidden');
            iconOpen.classList.remove('hidden'); iconClose.classList.add('hidden');
        }
    };

    window.startChatWithInfo = function() {
        document.getElementById('chatInfoForm')?.classList.add('hidden');
        document.getElementById('chatMsgArea')?.classList.remove('hidden');
        document.getElementById('chatInputArea')?.classList.remove('hidden');
        initChat();
    };

    async function initChat() {
        const fd = new FormData();
        fd.append('action', 'init');
        fd.append('page_url', location.href);
        try {
            const r = await fetch(CHAT_API, {method:'POST', body:fd});
            const d = await r.json();
            if (d.success) {
                chatConvoId = d.conversation_id;
                renderChatMessages(d.messages || []);
                startChatPoll();
                // Send visitor info if collected
                const nameEl = document.getElementById('chatGuestName');
                const phoneEl = document.getElementById('chatGuestPhone');
                if (nameEl?.value || phoneEl?.value) {
                    const infoFd = new FormData();
                    infoFd.append('action', 'send');
                    infoFd.append('conversation_id', chatConvoId);
                    infoFd.append('message', (nameEl?.value||'Guest') + ' started a chat');
                    infoFd.append('visitor_name', nameEl?.value || '');
                    infoFd.append('visitor_phone', phoneEl?.value || '');
                    // just update info, don't actually send visible message
                }
                document.getElementById('chatUserInput')?.focus();
            }
        } catch(e) { console.error('Chat init error:', e); }
    }

    function renderChatMessages(msgs) {
        const el = document.getElementById('chatMsgArea');
        let html = '';
        msgs.forEach(m => { html += chatMsgHTML(m); chatLastId = Math.max(chatLastId, parseInt(m.id)||0); });
        el.innerHTML = html;
        el.scrollTop = el.scrollHeight;
    }

    function chatMsgHTML(m) {
        const isUser = m.sender_type === 'user';
        const isBot = m.sender_type === 'bot';
        const isAdmin = m.sender_type === 'admin';
        const time = new Date(m.created_at).toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit',hour12:true});
        
        if (m.sender_type === 'system') {
            return `<div class="text-center"><span class="text-[10px] text-gray-400 bg-gray-200 px-2 py-0.5 rounded-full">${escChat(m.message)}</span></div>`;
        }
        
        if (isUser) {
            return `<div class="flex justify-end"><div class="max-w-[80%]">
                <div class="rounded-2xl rounded-br-sm px-3.5 py-2 text-sm text-white whitespace-pre-wrap" style="background:<?= e($_chatColor) ?>">${escChat(m.message)}</div>
                <p class="text-[10px] text-gray-400 text-right mt-0.5">${time}</p>
            </div></div>`;
        }
        
        const label = isBot ? '🤖 ' + (m.sender_name||'Bot') : '👤 ' + (m.sender_name||'Support');
        const bgClass = isBot ? 'bg-amber-50 border border-amber-100' : 'bg-white border';
        return `<div class="flex justify-start"><div class="max-w-[80%]">
            <p class="text-[10px] ${isBot?'text-amber-600':'text-blue-600'} mb-0.5">${escChat(label)}</p>
            <div class="rounded-2xl rounded-bl-sm px-3.5 py-2 text-sm text-gray-800 ${bgClass} whitespace-pre-wrap">${escChat(m.message)}</div>
            <p class="text-[10px] text-gray-400 mt-0.5">${time}</p>
        </div></div>`;
    }

    window.sendChatMsg = async function() {
        const inp = document.getElementById('chatUserInput');
        const msg = inp.value.trim();
        if (!msg || !chatConvoId) return;
        inp.value = ''; inp.style.height = 'auto';

        const el = document.getElementById('chatMsgArea');
        el.innerHTML += chatMsgHTML({sender_type:'user', message:msg, created_at:new Date().toISOString()});
        el.scrollTop = el.scrollHeight;

        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('conversation_id', chatConvoId);
        fd.append('message', msg);
        const vName = document.getElementById('chatGuestName')?.value;
        const vPhone = document.getElementById('chatGuestPhone')?.value;
        if (vName) fd.append('visitor_name', vName);
        if (vPhone) fd.append('visitor_phone', vPhone);

        try {
            const r = await fetch(CHAT_API, {method:'POST', body:fd});
            const d = await r.json();
            // Track user message ID to prevent poll duplicates
            if (d.user_message_id) chatLastId = Math.max(chatLastId, d.user_message_id);
            // Bot text reply
            if (d.bot_reply) {
                setTimeout(() => {
                    el.innerHTML += chatMsgHTML(d.bot_reply);
                    el.scrollTop = el.scrollHeight;
                    if (d.bot_reply.id) chatLastId = Math.max(chatLastId, parseInt(d.bot_reply.id));
                }, 600);
            }
            // Product carousel
            if (d.products && d.products.length > 0) {
                setTimeout(() => {
                    el.innerHTML += chatProductCarousel(d.products);
                    el.scrollTop = el.scrollHeight;
                }, d.bot_reply ? 1200 : 600);
            }
        } catch(e) { console.error(e); }
    };

    function chatProductCarousel(products) {
        const defaultImg = '<?= asset("img/default-product.svg") ?>';
        let cards = '';
        products.forEach(p => {
            const img = p.image || defaultImg;
            const hasDiscount = p.sale_price > 0 && p.sale_price < p.regular_price;
            const discountPct = hasDiscount ? Math.round((1 - p.sale_price / p.regular_price) * 100) : 0;
            const displayPrice = hasDiscount ? p.sale_price : p.regular_price;
            cards += `<div class="chat-pcard" onclick="window.open('${escChat(p.url)}','_blank')">
                <div class="chat-pcard-img">
                    <img src="${escChat(img)}" alt="${escChat(p.name)}" onerror="this.src='${defaultImg}'">
                    ${hasDiscount ? `<span class="chat-pcard-badge">-${discountPct}%</span>` : ''}
                </div>
                <div class="chat-pcard-info">
                    <p class="chat-pcard-name">${escChat(p.name)}</p>
                    <div class="chat-pcard-price">
                        <span class="chat-pcard-now">৳${Number(displayPrice).toLocaleString()}</span>
                        ${hasDiscount ? `<span class="chat-pcard-was">৳${Number(p.regular_price).toLocaleString()}</span>` : ''}
                    </div>
                    <div class="chat-pcard-btns">
                        <button onclick="event.stopPropagation();smartAddToCart(${p.id})" class="chat-pcard-cart" title="কার্টে যোগ করুন">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                        <button onclick="event.stopPropagation();smartOrder(${p.id})" class="chat-pcard-order">অর্ডার</button>
                    </div>
                </div>
            </div>`;
        });
        return `<div class="flex justify-start w-full"><div class="w-full">
            <p class="text-[10px] text-amber-600 mb-1">🛍️ পণ্য দেখুন (${products.length}টি)</p>
            <div class="chat-carousel">${cards}</div>
            <p class="text-[10px] text-gray-400 mt-1">← স্ক্রল করুন →</p>
        </div></div>`;
    }

    function startChatPoll() {
        clearInterval(chatPollTimer);
        chatPollTimer = setInterval(pollChatMessages, 4000);
    }

    async function pollChatMessages() {
        if (!chatConvoId) return;
        try {
            const r = await fetch(CHAT_API + `?action=poll&conversation_id=${chatConvoId}&after_id=${chatLastId}`);
            const d = await r.json();
            if (d.messages?.length) {
                const el = document.getElementById('chatMsgArea');
                const wasBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 50;
                d.messages.forEach(m => {
                    el.innerHTML += chatMsgHTML(m);
                    chatLastId = Math.max(chatLastId, parseInt(m.id)||0);
                });
                if (wasBottom || chatOpen) el.scrollTop = el.scrollHeight;
                if (!chatOpen) {
                    const badge = document.getElementById('chatUnreadBadge');
                    const current = parseInt(badge.textContent) || 0;
                    badge.textContent = current + d.messages.length;
                    badge.classList.remove('hidden');
                }
            }
        } catch(e) {}
    }

    function escChat(s) { if (!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML.replace(/\n/g,'<br>'); }

    // Auto-init if returning customer
    <?php if (isCustomerLoggedIn()): ?>
    // Pre-init for logged-in customers
    setTimeout(() => { if (!chatConvoId) initChat(); }, 2000);
    <?php endif; ?>
})();
</script>
<?php endif; // chat_enabled ?>

<!-- Padding for mobile bottom nav -->
<div class="lg:hidden h-16"></div>
</body>
</html>
