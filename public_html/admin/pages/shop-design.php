<?php
/**
 * Shop Design — Customize single product page & archive layouts
 */
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Shop Design';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verifyCSRFToken();
    $section = $_POST['section'] ?? '';
    $skipFields = ['action', 'section', CSRF_TOKEN_NAME];
    
    foreach ($_POST as $key => $value) {
        if (in_array($key, $skipFields)) continue;
        $val = sanitize($value);
        $db->query(
            "INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group) 
             VALUES (?, ?, 'text', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $val, $section, $val]
        );
    }
    
    // Handle checkbox fields (unchecked = not sent)
    $checkboxFields = [
        'product_page' => [
            'sp_show_order_btn', 'sp_show_cart_btn', 'sp_show_buynow_btn', 
            'sp_show_call_btn', 'sp_show_whatsapp_btn', 'sp_show_stock_status',
            'sp_show_discount_badge', 'sp_show_related', 'sp_show_bundles',
            'sp_show_tabs', 'sp_show_share', 'sp_show_qty_selector',
        ],
        'archive_page' => [
            'ar_show_card_buttons', 'ar_show_overlay', 'ar_show_discount_badge',
            'ar_show_sort', 'ar_show_order_btn',
        ],
    ];
    
    if (isset($checkboxFields[$section])) {
        foreach ($checkboxFields[$section] as $field) {
            if (!isset($_POST[$field])) {
                $db->query(
                    "INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group) 
                     VALUES (?, '0', 'text', ?) ON DUPLICATE KEY UPDATE setting_value = '0'",
                    [$field, $section]
                );
            }
        }
    }
    
    logActivity(getAdminId(), 'update', 'settings', 0, "Updated {$section} shop design");
    redirect(adminUrl("pages/shop-design.php?tab={$section}&msg=saved"));
}

$tab = $_GET['tab'] ?? 'product_page';
$s = [];
$allSettings = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
foreach ($allSettings as $row) $s[$row['setting_key']] = $row['setting_value'];

// Helper
function checked($s, $key, $default = '1') {
    return (($s[$key] ?? $default) === '1') ? 'checked' : '';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Shop Design</h1>
            <p class="text-sm text-gray-500">Customize product pages and archive layouts</p>
        </div>
        <?php if (isset($_GET['msg'])): ?>
        <div class="bg-green-50 text-green-700 px-4 py-2 rounded-lg text-sm font-medium animate-pulse">
            <i class="fas fa-check-circle mr-1"></i> Settings saved!
        </div>
        <?php endif; ?>
    </div>

    <div class="flex gap-6">
        <!-- Tabs -->
        <div class="w-56 flex-shrink-0">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <?php 
                $tabs = [
                    'product_page' => ['Single Product', 'fa-box-open'],
                    'archive_page' => ['Product Archive', 'fa-th-large'],
                ];
                foreach ($tabs as $tkey => $tdata): ?>
                <a href="?tab=<?= $tkey ?>" class="flex items-center gap-3 px-4 py-3.5 text-sm font-medium border-b last:border-b-0 transition
                    <?= $tab === $tkey ? 'bg-blue-50 text-blue-700 border-l-2 border-l-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas <?= $tdata[1] ?> w-5 text-center"></i>
                    <?= $tdata[0] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1">
            <form method="POST">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="section" value="<?= $tab ?>">

                <?php if ($tab === 'product_page'): ?>
                <!-- ═══════════════════════ SINGLE PRODUCT PAGE ═══════════════════════ -->
                
                <!-- Button Visibility -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-hand-pointer text-blue-500"></i> Button Visibility
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php
                        $buttons = [
                            'sp_show_order_btn' => ['Order (COD) Button', '1'],
                            'sp_show_cart_btn' => ['Add to Cart Button', '1'],
                            'sp_show_buynow_btn' => ['Buy Now Button', '1'],
                            'sp_show_call_btn' => ['Call Button', '1'],
                            'sp_show_whatsapp_btn' => ['WhatsApp Button', '1'],
                            'sp_show_qty_selector' => ['Quantity Selector', '1'],
                        ];
                        foreach ($buttons as $key => $info): ?>
                        <label class="flex items-center gap-3 p-3 rounded-lg border hover:bg-gray-50 cursor-pointer transition">
                            <input type="hidden" name="<?= $key ?>" value="0">
                            <input type="checkbox" name="<?= $key ?>" value="1" class="w-4 h-4 text-blue-600 rounded" <?= checked($s, $key, $info[1]) ?>>
                            <span class="text-sm text-gray-700"><?= $info[0] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Button Labels -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-tag text-green-500"></i> Button Labels
                    </h4>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Order Button Text</label>
                            <input type="text" name="btn_order_cod_label" value="<?= e($s['btn_order_cod_label'] ?? 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Add to Cart Text</label>
                            <input type="text" name="btn_add_to_cart_label" value="<?= e($s['btn_add_to_cart_label'] ?? 'কার্টে যোগ করুন') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Buy Now Text</label>
                            <input type="text" name="btn_buy_now_label" value="<?= e($s['btn_buy_now_label'] ?? 'এখনই কিনুন') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Call Button Text</label>
                            <input type="text" name="btn_call_label" value="<?= e($s['btn_call_label'] ?? 'কল করুন') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">WhatsApp Button Text</label>
                            <input type="text" name="btn_whatsapp_label" value="<?= e($s['btn_whatsapp_label'] ?? 'WhatsApp') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Archive Card Order Text</label>
                            <input type="text" name="btn_archive_order_label" value="<?= e($s['btn_archive_order_label'] ?? 'অর্ডার') ?>" 
                                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Page Sections -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-puzzle-piece text-purple-500"></i> Page Sections
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php
                        $sections = [
                            'sp_show_stock_status' => ['Stock Status Badge', '1'],
                            'sp_show_discount_badge' => ['Discount Badge', '1'],
                            'sp_show_related' => ['Related Products', '1'],
                            'sp_show_bundles' => ['Bundle Deals', '1'],
                            'sp_show_tabs' => ['Description/Review Tabs', '1'],
                            'sp_show_share' => ['Social Share Buttons', '1'],
                        ];
                        foreach ($sections as $key => $info): ?>
                        <label class="flex items-center gap-3 p-3 rounded-lg border hover:bg-gray-50 cursor-pointer transition">
                            <input type="hidden" name="<?= $key ?>" value="0">
                            <input type="checkbox" name="<?= $key ?>" value="1" class="w-4 h-4 text-blue-600 rounded" <?= checked($s, $key, $info[1]) ?>>
                            <span class="text-sm text-gray-700"><?= $info[0] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Button Layout -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-columns text-orange-500"></i> Button Layout
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php $layout = $s['sp_button_layout'] ?? 'standard'; ?>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $layout === 'standard' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="sp_button_layout" value="standard" class="sr-only" <?= $layout === 'standard' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="mb-2 space-y-1.5">
                                    <div class="h-8 bg-red-200 rounded-lg"></div>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <div class="h-7 bg-orange-200 rounded-lg"></div>
                                        <div class="h-7 bg-gray-200 rounded-lg border"></div>
                                    </div>
                                </div>
                                <p class="text-xs font-medium text-gray-700">Standard</p>
                                <p class="text-[10px] text-gray-400">Order + Cart/Buy</p>
                            </div>
                        </label>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $layout === 'two_buttons' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="sp_button_layout" value="two_buttons" class="sr-only" <?= $layout === 'two_buttons' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="mb-2 space-y-1.5">
                                    <div class="h-8 bg-red-200 rounded-lg"></div>
                                    <div class="h-8 bg-orange-200 rounded-lg"></div>
                                </div>
                                <p class="text-xs font-medium text-gray-700">Two Full</p>
                                <p class="text-[10px] text-gray-400">Order + Cart only</p>
                            </div>
                        </label>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $layout === 'order_only' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="sp_button_layout" value="order_only" class="sr-only" <?= $layout === 'order_only' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="mb-2 space-y-1.5">
                                    <div class="h-10 bg-red-200 rounded-lg"></div>
                                </div>
                                <p class="text-xs font-medium text-gray-700">Order Only</p>
                                <p class="text-[10px] text-gray-400">Single CTA</p>
                            </div>
                        </label>
                    </div>
                </div>

                <?php elseif ($tab === 'archive_page'): ?>
                <!-- ═══════════════════════ ARCHIVE / SHOP PAGE ═══════════════════════ -->
                
                <!-- Grid Layout -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-th text-blue-500"></i> Grid Layout
                    </h4>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Desktop Columns (lg+)</label>
                            <select name="ar_grid_cols_desktop" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                                <?php $cols = $s['ar_grid_cols_desktop'] ?? '5';
                                foreach (['3' => '3 Columns', '4' => '4 Columns', '5' => '5 Columns', '6' => '6 Columns'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $cols === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Tablet Columns (md)</label>
                            <select name="ar_grid_cols_tablet" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                                <?php $cols = $s['ar_grid_cols_tablet'] ?? '4';
                                foreach (['2' => '2 Columns', '3' => '3 Columns', '4' => '4 Columns'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $cols === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Mobile Columns</label>
                            <select name="ar_grid_cols_mobile" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                                <?php $cols = $s['ar_grid_cols_mobile'] ?? '2';
                                foreach (['1' => '1 Column', '2' => '2 Columns'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $cols === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Products Per Page</label>
                            <select name="ar_products_per_page" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                                <?php $pp = $s['ar_products_per_page'] ?? '20';
                                foreach (['12', '16', '20', '24', '30', '40'] as $v): ?>
                                <option value="<?= $v ?>" <?= $pp === $v ? 'selected' : '' ?>><?= $v ?> Products</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card Features -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-layer-group text-green-500"></i> Card Features
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php
                        $cardFeatures = [
                            'ar_show_card_buttons' => ['Card Action Buttons', '1'],
                            'ar_show_order_btn' => ['Order Button on Card', '1'],
                            'ar_show_overlay' => ['Hover Overlay (Desktop)', '1'],
                            'ar_show_discount_badge' => ['Discount Badge', '1'],
                            'ar_show_sort' => ['Sort Dropdown', '1'],
                        ];
                        foreach ($cardFeatures as $key => $info): ?>
                        <label class="flex items-center gap-3 p-3 rounded-lg border hover:bg-gray-50 cursor-pointer transition">
                            <input type="hidden" name="<?= $key ?>" value="0">
                            <input type="checkbox" name="<?= $key ?>" value="1" class="w-4 h-4 text-blue-600 rounded" <?= checked($s, $key, $info[1]) ?>>
                            <span class="text-sm text-gray-700"><?= $info[0] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Card Style -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-palette text-purple-500"></i> Card Style
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php $cardStyle = $s['ar_card_style'] ?? 'standard'; ?>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $cardStyle === 'standard' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="ar_card_style" value="standard" class="sr-only" <?= $cardStyle === 'standard' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="w-full aspect-square bg-gray-100 rounded-lg mb-2"></div>
                                <div class="h-3 bg-gray-200 rounded w-3/4 mx-auto mb-1.5"></div>
                                <div class="h-3 bg-red-200 rounded w-1/2 mx-auto mb-2"></div>
                                <div class="grid grid-cols-2 gap-1">
                                    <div class="h-5 bg-orange-200 rounded"></div>
                                    <div class="h-5 bg-red-200 rounded"></div>
                                </div>
                                <p class="text-xs font-medium text-gray-700 mt-2">Standard</p>
                            </div>
                        </label>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $cardStyle === 'minimal' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="ar_card_style" value="minimal" class="sr-only" <?= $cardStyle === 'minimal' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="w-full aspect-square bg-gray-100 rounded-lg mb-2"></div>
                                <div class="h-3 bg-gray-200 rounded w-3/4 mx-auto mb-1.5"></div>
                                <div class="h-3 bg-red-200 rounded w-1/2 mx-auto"></div>
                                <p class="text-xs font-medium text-gray-700 mt-2">Minimal</p>
                                <p class="text-[10px] text-gray-400">No buttons</p>
                            </div>
                        </label>
                        <label class="relative border-2 rounded-xl p-4 cursor-pointer transition hover:border-blue-300 <?= $cardStyle === 'detailed' ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <input type="radio" name="ar_card_style" value="detailed" class="sr-only" <?= $cardStyle === 'detailed' ? 'checked' : '' ?>>
                            <div class="text-center">
                                <div class="w-full aspect-square bg-gray-100 rounded-lg mb-2"></div>
                                <div class="h-2 bg-gray-200 rounded w-full mb-1"></div>
                                <div class="h-2 bg-gray-200 rounded w-5/6 mb-1.5"></div>
                                <div class="h-3 bg-red-200 rounded w-1/2 mx-auto mb-2"></div>
                                <div class="h-6 bg-red-200 rounded w-full"></div>
                                <p class="text-xs font-medium text-gray-700 mt-2">Detailed</p>
                                <p class="text-[10px] text-gray-400">Full-width order</p>
                            </div>
                        </label>
                    </div>
                </div>

                <?php endif; ?>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-8 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Visual feedback for radio card selection
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const name = this.name;
        document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
            r.closest('label').classList.remove('border-blue-500', 'bg-blue-50');
        });
        this.closest('label').classList.add('border-blue-500', 'bg-blue-50');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
