<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    
    // Handle file uploads (traditional)
    $fileFields = ['site_logo', 'site_favicon', 'footer_logo'];
    foreach ($fileFields as $ff) {
        if (!empty($_FILES[$ff]['name'])) {
            $upload = uploadFile($_FILES[$ff], 'logos');
            if ($upload) updateSetting($ff, $upload);
        }
    }
    
    // Handle media-library selected images
    $mediaFields = ['site_logo_media', 'site_favicon_media', 'footer_logo_media'];
    foreach ($mediaFields as $mf) {
        $realKey = str_replace('_media', '', $mf);
        if (!empty($_POST[$mf])) {
            updateSetting($realKey, $_POST[$mf]);
        }
    }
    
    // Build footer links JSON from arrays
    for ($col = 1; $col <= 2; $col++) {
        $labels = $_POST["footer_links_col{$col}_label"] ?? [];
        $urls = $_POST["footer_links_col{$col}_url"] ?? [];
        $links = [];
        foreach ($labels as $i => $label) {
            if (trim($label) || trim($urls[$i] ?? '')) {
                $links[] = ['label' => trim($label), 'url' => trim($urls[$i] ?? '')];
            }
        }
        updateSetting("footer_links_col{$col}", json_encode($links, JSON_UNESCAPED_UNICODE));
        unset($_POST["footer_links_col{$col}_label"], $_POST["footer_links_col{$col}_url"]);
    }
    
    // Save all text/color/other settings
    $skipFields = ['section', 'action', 'site_logo', 'site_favicon', 'footer_logo',
                   'site_logo_media', 'site_favicon_media', 'footer_logo_media'];
    foreach ($_POST as $key => $val) {
        if (in_array($key, $skipFields)) continue;
        if (is_array($val)) {
            updateSetting($key, json_encode($val, JSON_UNESCAPED_UNICODE));
        } else {
            updateSetting($key, $val);
        }
    }
    
    // Handle checkbox fields
    $checkboxMap = [
        'general' => ['maintenance_mode'],
        'shipping' => ['auto_detect_location'],
        'checkout' => ['checkout_note_enabled'],
        'social' => ['fab_enabled','fab_call_enabled','fab_chat_enabled','fab_whatsapp_enabled','fab_messenger_enabled'],
    ];
    if (isset($checkboxMap[$section])) {
        foreach ($checkboxMap[$section] as $cb) {
            if (!isset($_POST[$cb])) updateSetting($cb, '0');
        }
    }
    
    logActivity(getAdminId(), 'update', 'settings', 0, "Updated {$section} settings");
    redirect(adminUrl("pages/settings.php?tab={$section}&msg=saved"));
}

$tab = $_GET['tab'] ?? 'general';
$allSettings = $db->fetchAll("SELECT * FROM site_settings");
$s = [];
foreach ($allSettings as $row) { $s[$row['setting_key']] = $row['setting_value']; }

function settingImgUrl($s, $key) {
    $val = $s[$key] ?? '';
    if (!$val) return '';
    if (strpos($val, '/') !== false) return uploadUrl($val);
    return uploadUrl('logos/' . $val);
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
    <i class="fas fa-check-circle mr-1"></i> Settings saved successfully.
</div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row gap-6">
    <div class="lg:w-56 flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <?php
            $tabs = [
                'general' => ['General', 'fa-cog'],
                'colors' => ['Colors & Design', 'fa-palette'],
                'header' => ['Header & Nav', 'fa-bars'],
                'footer' => ['Footer', 'fa-window-minimize'],
                'shipping' => ['Shipping', 'fa-truck'],
                'social' => ['Social & Contact', 'fa-share-alt'],
                'tracking' => ['Tracking & Pixels', 'fa-chart-bar'],
                'checkout' => ['Checkout & Labels', 'fa-shopping-cart'],
                'seo' => ['SEO & Meta', 'fa-search'],
                'advanced' => ['Advanced', 'fa-tools'],
                'email' => ['Email / SMTP', 'fa-envelope'],
            ];
            foreach ($tabs as $tkey => $tdata): ?>
            <a href="?tab=<?= $tkey ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-medium border-b last:border-b-0 transition
                <?= $tab === $tkey ? 'bg-blue-50 text-blue-700 border-l-2 border-l-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                <i class="fas <?= $tdata[1] ?> w-4 text-center"></i> <?= $tdata[0] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex-1">
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="section" value="<?= $tab ?>">

            <?php if ($tab === 'general'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-store mr-2 text-blue-500"></i>Store Information</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Site Name *</label>
                        <input type="text" name="site_name" value="<?= e($s['site_name'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" required></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tagline</label>
                        <input type="text" name="site_tagline" value="<?= e($s['site_tagline'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Site Description</label>
                    <textarea name="site_description" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['site_description'] ?? '') ?></textarea></div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="<?= e($s['currency_symbol'] ?? '৳') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm max-w-[100px]"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Site Phone</label>
                        <input type="text" name="site_phone" value="<?= e($s['site_phone'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                        <input type="text" name="site_whatsapp" value="<?= e($s['site_whatsapp'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-image mr-2 text-green-500"></i>Logo & Favicon</h4>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site Logo</label>
                        <div class="border-2 border-dashed rounded-xl p-4 text-center">
                            <?php $logoUrl = settingImgUrl($s, 'site_logo'); ?>
                            <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" class="h-16 mx-auto mb-2" id="logo-preview-img">
                            <?php else: ?><div class="text-gray-400 py-4" id="logo-placeholder"><i class="fas fa-image text-3xl mb-2 block"></i>No logo</div>
                                <img src="" class="h-16 mx-auto mb-2 hidden" id="logo-preview-img"><?php endif; ?>
                            <input type="hidden" name="site_logo_media" id="site_logo_media" value="">
                            <div class="flex gap-2 justify-center mt-2">
                                <button type="button" onclick="pickSettingImage('site_logo')" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs hover:bg-blue-600"><i class="fas fa-photo-video mr-1"></i>Media Library</button>
                                <label class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200 cursor-pointer"><i class="fas fa-upload mr-1"></i>Upload
                                    <input type="file" name="site_logo" accept="image/*" class="hidden" onchange="previewFile(this,'logo-preview-img','logo-placeholder')"></label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Favicon</label>
                        <div class="border-2 border-dashed rounded-xl p-4 text-center">
                            <?php $favUrl = settingImgUrl($s, 'site_favicon'); ?>
                            <?php if ($favUrl): ?><img src="<?= $favUrl ?>" class="w-12 h-12 mx-auto mb-2" id="favicon-preview-img">
                            <?php else: ?><div class="text-gray-400 py-4" id="favicon-placeholder"><i class="fas fa-image text-3xl mb-2 block"></i>No favicon</div>
                                <img src="" class="w-12 h-12 mx-auto mb-2 hidden" id="favicon-preview-img"><?php endif; ?>
                            <input type="hidden" name="site_favicon_media" id="site_favicon_media" value="">
                            <div class="flex gap-2 justify-center mt-2">
                                <button type="button" onclick="pickSettingImage('site_favicon')" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs hover:bg-blue-600"><i class="fas fa-photo-video mr-1"></i>Media Library</button>
                                <label class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200 cursor-pointer"><i class="fas fa-upload mr-1"></i>Upload
                                    <input type="file" name="site_favicon" accept="image/*" class="hidden" onchange="previewFile(this,'favicon-preview-img','favicon-placeholder')"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-wrench mr-2 text-orange-500"></i>Store Controls</h4>
                <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="hidden" name="maintenance_mode" value="0">
                    <input type="checkbox" name="maintenance_mode" value="1" class="w-4 h-4 rounded text-red-600" <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <div><span class="text-sm font-medium text-gray-700">Maintenance Mode</span>
                        <p class="text-xs text-gray-400">Visitors see a "coming soon" page</p></div>
                </label>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Maintenance Message</label>
                    <textarea name="maintenance_message" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['maintenance_message'] ?? 'আমরা শীঘ্রই ফিরে আসছি!') ?></textarea></div>
            </div>

            <?php elseif ($tab === 'colors'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-palette mr-2 text-purple-500"></i>Color Customization</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <?php $colorFields = [
                        'primary_color'=>['Primary','#2563eb'],'secondary_color'=>['Secondary','#1e40af'],
                        'topbar_bg_color'=>['Top Bar BG','#1e293b'],'topbar_text_color'=>['Top Bar Text','#ffffff'],
                        'navbar_bg_color'=>['Navbar BG','#ffffff'],'navbar_text_color'=>['Navbar Text','#1f2937'],
                        'category_bar_bg'=>['Category Bar BG','#f8fafc'],'category_bar_text'=>['Category Bar Text','#374151'],
                        'button_bg_color'=>['Button BG','#2563eb'],'button_text_color'=>['Button Text','#ffffff'],
                        'button_hover_color'=>['Button Hover','#1d4ed8'],'sale_badge_bg'=>['Sale Badge BG','#ef4444'],
                        'sale_badge_text'=>['Sale Badge Text','#ffffff'],'price_color'=>['Price','#dc2626'],
                        'old_price_color'=>['Old Price','#9ca3af'],'footer_bg_color'=>['Footer BG','#111827'],
                        'footer_text_color'=>['Footer Text','#d1d5db'],'footer_heading_color'=>['Footer Heading','#ffffff'],
                        'mobile_nav_bg'=>['Mobile Nav BG','#ffffff'],'mobile_nav_active'=>['Mobile Nav Active','#2563eb'],
                        'announcement_bg'=>['Announcement BG','#fef3c7'],'announcement_text'=>['Announcement Text','#92400e'],
                    ];
                    foreach ($colorFields as $key => $cf): ?>
                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                        <input type="color" name="<?= $key ?>" value="<?= e($s[$key] ?? $cf[1]) ?>" class="w-10 h-10 rounded cursor-pointer border-0 p-0" onchange="this.nextElementSibling.querySelector('code').textContent=this.value">
                        <div><label class="block text-sm font-medium text-gray-700"><?= $cf[0] ?></label>
                            <code class="text-xs text-gray-400"><?= e($s[$key] ?? $cf[1]) ?></code></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php elseif ($tab === 'header'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-bullhorn mr-2 text-yellow-500"></i>Top Bar</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Announcement Text</label>
                    <input type="text" name="announcement_content" value="<?= e($s['announcement_content'] ?? $s['announcement_text'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="🎉 Free delivery on orders over ৳1000!"></div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Header Phone</label>
                        <input type="text" name="header_phone" value="<?= e($s['header_phone'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hotline</label>
                        <input type="text" name="hotline_number" value="<?= e($s['hotline_number'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-search mr-2 text-blue-500"></i>Search & Navigation</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Search Placeholder</label>
                    <input type="text" name="search_placeholder" value="<?= e($s['search_placeholder'] ?? 'পণ্য খুঁজুন...') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Categories in Nav</label>
                        <input type="number" name="nav_max_categories" value="<?= e($s['nav_max_categories'] ?? '8') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Logo Max Height (px)</label>
                        <input type="number" name="logo_max_height" value="<?= e($s['logo_max_height'] ?? '50') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
            </div>

            <?php elseif ($tab === 'footer'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-info-circle mr-2 text-blue-500"></i>Footer Content</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Footer About Text</label>
                    <textarea name="footer_about" rows="3" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['footer_about'] ?? '') ?></textarea></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Footer Logo</label>
                    <div class="border-2 border-dashed rounded-xl p-4 text-center">
                        <?php $fLogoUrl = settingImgUrl($s, 'footer_logo'); ?>
                        <?php if ($fLogoUrl): ?><img src="<?= $fLogoUrl ?>" class="h-12 mx-auto mb-2" id="flogo-preview-img">
                        <?php else: ?><div class="text-gray-400 py-2" id="flogo-placeholder"><i class="fas fa-image text-2xl"></i></div>
                            <img src="" class="h-12 mx-auto mb-2 hidden" id="flogo-preview-img"><?php endif; ?>
                        <input type="hidden" name="footer_logo_media" id="footer_logo_media" value="">
                        <div class="flex gap-2 justify-center mt-2">
                            <button type="button" onclick="pickSettingImage('footer_logo')" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs hover:bg-blue-600"><i class="fas fa-photo-video mr-1"></i>Media Library</button>
                            <label class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200 cursor-pointer"><i class="fas fa-upload mr-1"></i>Upload
                                <input type="file" name="footer_logo" accept="image/*" class="hidden" onchange="previewFile(this,'flogo-preview-img','flogo-placeholder')"></label>
                        </div>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Copyright Text</label>
                    <input type="text" name="copyright_text" value="<?= e($s['copyright_text'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Footer Address</label>
                    <textarea name="footer_address" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['footer_address'] ?? '') ?></textarea></div>
            </div>

            <?php for ($col = 1; $col <= 2; $col++):
                $links = json_decode($s["footer_links_col{$col}"] ?? '[]', true) ?: [];
            ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-link mr-2 text-green-500"></i>Footer Links Column <?= $col ?></h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Column Title</label>
                    <input type="text" name="footer_links_col<?= $col ?>_title" value="<?= e($s["footer_links_col{$col}_title"] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div id="footer-links-<?= $col ?>" class="space-y-2">
                    <?php foreach ($links as $link): ?>
                    <div class="footer-link-row flex gap-2 items-center">
                        <input type="text" name="footer_links_col<?= $col ?>_label[]" value="<?= e($link['label'] ?? '') ?>" placeholder="Link Text" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <input type="text" name="footer_links_col<?= $col ?>_url[]" value="<?= e($link['url'] ?? '') ?>" placeholder="/page or https://..." class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <button type="button" onclick="this.closest('.footer-link-row').remove()" class="text-red-400 hover:text-red-600 px-2"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="addFooterLink(<?= $col ?>)" class="text-blue-600 text-sm font-medium hover:underline"><i class="fas fa-plus mr-1"></i>Add Link</button>
            </div>
            <?php endfor; ?>

            <?php elseif ($tab === 'shipping'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-truck mr-2 text-blue-500"></i>Delivery Charges</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Inside Dhaka (৳)</label>
                        <input type="number" name="shipping_inside_dhaka" value="<?= e($s['shipping_inside_dhaka'] ?? '60') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Outside Dhaka (৳)</label>
                        <input type="number" name="shipping_outside_dhaka" value="<?= e($s['shipping_outside_dhaka'] ?? '120') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Free Shipping Minimum (৳)</label>
                        <input type="number" name="free_shipping_minimum" value="<?= e($s['free_shipping_minimum'] ?? '0') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="0 = disabled"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Estimated Delivery</label>
                        <input type="text" name="estimated_delivery" value="<?= e($s['estimated_delivery'] ?? '2-5 days') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-shipping-fast mr-2 text-green-500"></i>Courier & Invoice</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Default Courier</label>
                        <select name="default_courier" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                            <?php foreach (['pathao'=>'Pathao','steadfast'=>'Steadfast','redx'=>'RedX','personal'=>'Personal'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($s['default_courier'] ?? 'pathao') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Invoice Template</label>
                        <select name="invoice_template" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                            <?php foreach (['standard'=>'📄 Standard','compact'=>'📋 Compact','sticker'=>'🏷 Sticker','picking'=>'📦 Picking'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($s['invoice_template'] ?? 'standard') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <label class="flex items-center gap-2"><input type="hidden" name="auto_detect_location" value="0">
                    <input type="checkbox" name="auto_detect_location" value="1" class="rounded" <?= ($s['auto_detect_location'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700">Auto-detect courier location from customer address</span></label>
            </div>

            <?php elseif ($tab === 'social'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-share-alt mr-2 text-pink-500"></i>Social & Contact</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <?php foreach (['contact_phone'=>'Contact Phone','contact_email'=>'Contact Email','whatsapp_number'=>'WhatsApp','facebook_url'=>'Facebook URL','instagram_url'=>'Instagram URL','youtube_url'=>'YouTube URL','tiktok_url'=>'TikTok URL','twitter_url'=>'Twitter/X URL'] as $key => $label): ?>
                    <div><label class="block text-xs font-medium text-gray-500 mb-0.5"><?= $label ?></label>
                        <input type="text" name="<?= $key ?>" value="<?= e($s[$key] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Floating Contact Button -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-headset mr-2 text-blue-500"></i>Floating Contact Button</h4>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="fab_enabled" value="1" class="sr-only peer" <?= ($s['fab_enabled'] ?? '0') === '1' ? 'checked' : '' ?> onchange="document.getElementById('fabOptions').classList.toggle('hidden',!this.checked)">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <p class="text-xs text-gray-500">Show a floating contact button on all pages with expandable options</p>

                <div id="fabOptions" class="space-y-3 <?= ($s['fab_enabled'] ?? '0') !== '1' ? 'hidden' : '' ?>">
                    <div class="grid md:grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-500 mb-0.5">Button Color</label>
                            <input type="color" name="fab_color" value="<?= e($s['fab_color'] ?? '#3b82f6') ?>" class="h-9 w-full rounded-lg border cursor-pointer"></div>
                        <div><label class="block text-xs font-medium text-gray-500 mb-0.5">Position</label>
                            <select name="fab_position" class="w-full px-3 py-2 border rounded-lg text-sm">
                                <option value="right" <?= ($s['fab_position'] ?? 'right') === 'right' ? 'selected' : '' ?>>Bottom Right</option>
                                <option value="left" <?= ($s['fab_position'] ?? '') === 'left' ? 'selected' : '' ?>>Bottom Left</option>
                            </select></div>
                    </div>

                    <!-- Call -->
                    <div class="flex items-center gap-3 p-3 rounded-lg border bg-gray-50">
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="fab_call_enabled" value="1" class="sr-only peer" <?= ($s['fab_call_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                        <i class="fas fa-phone-alt text-green-500 w-5 text-center"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700">Call</div>
                            <div class="text-[10px] text-gray-400">Uses Contact Phone from above</div>
                        </div>
                        <div class="text-xs text-gray-400 truncate max-w-[120px]"><?= e($s['contact_phone'] ?? 'Not set') ?></div>
                    </div>

                    <!-- Chat -->
                    <div class="flex items-center gap-3 p-3 rounded-lg border bg-gray-50">
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="fab_chat_enabled" value="1" class="sr-only peer" <?= ($s['fab_chat_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
                        </label>
                        <i class="fas fa-comments text-blue-500 w-5 text-center"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700">Live Chat</div>
                            <div class="text-[10px] text-gray-400">Opens your site's built-in chat widget</div>
                        </div>
                        <div class="text-xs <?= ($s['chat_enabled'] ?? '0') === '1' ? 'text-green-500' : 'text-red-400' ?>"><?= ($s['chat_enabled'] ?? '0') === '1' ? 'Chat ON' : 'Chat OFF' ?></div>
                    </div>

                    <!-- WhatsApp -->
                    <div class="flex items-center gap-3 p-3 rounded-lg border bg-gray-50">
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="fab_whatsapp_enabled" value="1" class="sr-only peer" <?= ($s['fab_whatsapp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                        <i class="fab fa-whatsapp text-green-500 w-5 text-center text-lg"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700">WhatsApp</div>
                            <div class="text-[10px] text-gray-400">Uses WhatsApp number from above</div>
                        </div>
                        <div class="text-xs text-gray-400 truncate max-w-[120px]"><?= e($s['whatsapp_number'] ?? 'Not set') ?></div>
                    </div>

                    <!-- Messenger -->
                    <div class="flex items-center gap-3 p-3 rounded-lg border bg-gray-50">
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="fab_messenger_enabled" value="1" class="sr-only peer" <?= ($s['fab_messenger_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                        <i class="fab fa-facebook-messenger text-blue-600 w-5 text-center text-lg"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700">Messenger</div>
                            <div class="text-[10px] text-gray-400">Enter your Facebook Page ID or username</div>
                        </div>
                    </div>
                    <div class="pl-12">
                        <input type="text" name="fab_messenger_id" value="<?= e($s['fab_messenger_id'] ?? '') ?>" placeholder="PageName or Page ID (e.g. khatibangla)" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>

                    <div class="p-3 rounded-lg bg-blue-50 border border-blue-100">
                        <p class="text-xs text-blue-600"><i class="fas fa-info-circle mr-1"></i> The button appears on all pages. It replaces the standalone WhatsApp/Chat bubbles with a single unified contact menu. When only one option is enabled, it opens directly without expanding.</p>
                    </div>
                </div>
            </div>

            <?php elseif ($tab === 'tracking'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-chart-line mr-2 text-indigo-500"></i>Analytics & Pixels</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Google Analytics ID</label>
                        <input type="text" name="google_analytics_id" value="<?= e($s['google_analytics_id'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="G-XXXXXXXXXX"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Facebook Pixel ID</label>
                        <input type="text" name="facebook_pixel_id" value="<?= e($s['facebook_pixel_id'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TikTok Pixel ID</label>
                        <input type="text" name="tiktok_pixel_id" value="<?= e($s['tiktok_pixel_id'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Google Tag Manager</label>
                        <input type="text" name="gtm_id" value="<?= e($s['gtm_id'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="GTM-XXXXXXX"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Custom Header Code</label>
                    <textarea name="custom_header_code" rows="4" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono"><?= e($s['custom_header_code'] ?? '') ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Custom Footer Code</label>
                    <textarea name="custom_footer_code" rows="4" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono"><?= e($s['custom_footer_code'] ?? '') ?></textarea></div>
            </div>

            <?php elseif ($tab === 'checkout'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-cash-register mr-2 text-green-500"></i>Checkout Labels</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Order Button Text</label>
                        <input type="text" name="btn_order_cod_label" value="<?= e($s['btn_order_cod_label'] ?? 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Add to Cart Text</label>
                        <input type="text" name="btn_add_to_cart_label" value="<?= e($s['btn_add_to_cart_label'] ?? 'কার্টে যোগ করুন') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Buy Now Text</label>
                        <input type="text" name="btn_buy_now_label" value="<?= e($s['btn_buy_now_label'] ?? 'এখনই কিনুন') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Popup Title</label>
                        <input type="text" name="checkout_popup_title" value="<?= e($s['checkout_popup_title'] ?? 'আপনার অর্ডার সম্পন্ন করুন') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Order Success Message</label>
                    <textarea name="order_success_message" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['order_success_message'] ?? 'আপনার অর্ডার সফলভাবে সম্পন্ন হয়েছে!') ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">COD Note</label>
                    <textarea name="cod_note" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm"><?= e($s['cod_note'] ?? '') ?></textarea></div>
                <label class="flex items-center gap-2"><input type="hidden" name="checkout_note_enabled" value="0">
                    <input type="checkbox" name="checkout_note_enabled" value="1" class="rounded" <?= ($s['checkout_note_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700">Allow customer order notes</span></label>
            </div>

            <?php elseif ($tab === 'seo'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-search mr-2 text-teal-500"></i>SEO & Open Graph</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Default Meta Keywords</label>
                    <input type="text" name="meta_keywords" value="<?= e($s['meta_keywords'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">OG Image URL</label>
                    <input type="text" name="og_image" value="<?= e($s['og_image'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Robots.txt Content</label>
                    <textarea name="robots_txt" rows="4" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono"><?= e($s['robots_txt'] ?? "User-agent: *\nAllow: /") ?></textarea></div>
            </div>

            <?php elseif ($tab === 'advanced'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-tools mr-2 text-gray-500"></i>Advanced Settings</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                        <input type="number" name="items_per_page" value="<?= e($s['items_per_page'] ?? '20') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">SKU Prefix</label>
                        <input type="text" name="sku_prefix" value="<?= e($s['sku_prefix'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Auto = first 3 letters of site name"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Order Number Prefix</label>
                        <input type="text" name="order_prefix" value="<?= e($s['order_prefix'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="e.g. ORD-"></div>
                </div>
            </div>
            <?php elseif ($tab === 'email'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-5">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-envelope mr-2 text-blue-500"></i>Email / SMTP Settings</h4>
                <p class="text-sm text-gray-500">Configure email for password reset, order notifications, etc. By default uses PHP mail(). Enable SMTP for better deliverability.</p>
                
                <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="smtp_enabled" value="0">
                        <input type="checkbox" name="smtp_enabled" value="1" <?= ($s['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="document.getElementById('smtp-fields').style.display=this.checked?'block':'none'">
                        <span class="text-sm font-medium text-blue-700">Enable SMTP</span>
                    </label>
                    <span class="text-xs text-blue-500">(Unchecked = uses PHP mail() which works on cPanel by default)</span>
                </div>
                
                <div id="smtp-fields" style="display:<?= ($s['smtp_enabled'] ?? '0') === '1' ? 'block' : 'none' ?>">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host *</label>
                            <input type="text" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="mail.yourdomain.com"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port *</label>
                            <input type="number" name="smtp_port" value="<?= e($s['smtp_port'] ?? '587') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="587"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username *</label>
                            <input type="text" name="smtp_username" value="<?= e($s['smtp_username'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="email@yourdomain.com"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password *</label>
                            <input type="password" name="smtp_password" value="<?= e($s['smtp_password'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="••••••"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                            <select name="smtp_encryption" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                                <option value="tls" <?= ($s['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                                <option value="ssl" <?= ($s['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                                <option value="none" <?= ($s['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (Port 25)</option>
                            </select></div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h5 class="font-medium text-gray-700 text-sm mb-3">Sender Info</h5>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                            <input type="email" name="smtp_from_email" value="<?= e($s['smtp_from_email'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="noreply@yourdomain.com"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                            <input type="text" name="smtp_from_name" value="<?= e($s['smtp_from_name'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="<?= e($s['site_name'] ?? 'MyShop') ?>"></div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h5 class="font-medium text-gray-700 text-sm mb-3">Test Email</h5>
                    <div class="flex gap-2">
                        <input type="email" id="test-email" class="flex-1 px-3 py-2.5 border rounded-lg text-sm" placeholder="your@email.com">
                        <button type="button" onclick="sendTestEmail()" class="bg-green-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700">
                            <i class="fas fa-paper-plane mr-1"></i>Send Test
                        </button>
                        <button type="button" onclick="runDiagnose()" class="bg-gray-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-700">
                            <i class="fas fa-stethoscope mr-1"></i>Diagnose
                        </button>
                    </div>
                    <div id="test-result" class="mt-2 text-sm hidden"></div>
                    <div id="diagnose-result" class="mt-3 hidden"></div>
                </div>
            </div>
            
            <script>
            function sendTestEmail() {
                const email = document.getElementById('test-email').value;
                if (!email) { alert('Enter a test email address'); return; }
                const r = document.getElementById('test-result');
                r.className = 'mt-2 text-sm text-gray-500';
                r.textContent = 'Sending...';
                r.classList.remove('hidden');
                
                const fd = new FormData();
                fd.append('action', 'test_email');
                fd.append('email', email);
                fetch('<?= adminUrl("api/email-test.php") ?>', {method:'POST', body: fd})
                    .then(res => res.json())
                    .then(d => {
                        r.className = 'mt-2 text-sm ' + (d.success ? 'text-green-600' : 'text-red-600');
                        r.textContent = d.message;
                    })
                    .catch(e => { r.className = 'mt-2 text-sm text-red-600'; r.textContent = 'Error: ' + e.message; });
            }
            function runDiagnose() {
                const r = document.getElementById('diagnose-result');
                r.classList.remove('hidden');
                r.innerHTML = '<p class="text-gray-500 text-sm">Running diagnostics...</p>';
                
                const fd = new FormData();
                fd.append('action', 'diagnose');
                fetch('<?= adminUrl("api/email-test.php") ?>', {method:'POST', body: fd})
                    .then(res => res.json())
                    .then(d => {
                        if (d.diagnostics) {
                            let html = '<div class="bg-gray-50 border rounded-lg p-4 text-xs font-mono space-y-1">';
                            html += '<p class="font-semibold text-gray-700 text-sm mb-2">📋 Email Diagnostics</p>';
                            for (const [k, v] of Object.entries(d.diagnostics)) {
                                const label = k.replace(/_/g, ' ').replace(/^port /, '');
                                const color = String(v).includes('✅') ? 'text-green-700' : String(v).includes('❌') ? 'text-red-600' : 'text-gray-600';
                                html += '<div class="flex gap-2"><span class="text-gray-500 w-40 flex-shrink-0">' + label + ':</span><span class="' + color + '">' + v + '</span></div>';
                            }
                            html += '</div>';
                            r.innerHTML = html;
                        }
                    })
                    .catch(e => { r.innerHTML = '<p class="text-red-600 text-sm">Error: ' + e.message + '</p>'; });
            }
            </script>

            <?php endif; ?>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-8 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
                    <i class="fas fa-save mr-2"></i>Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/media-picker.php'; ?>

<script>
function pickSettingImage(field) {
    openMediaLibrary(function(files) {
        if (files.length) {
            const file = files[0];
            document.getElementById(field + '_media').value = file.path;
            const map = {site_logo:'logo',site_favicon:'favicon',footer_logo:'flogo'};
            const prefix = map[field] || field;
            const img = document.getElementById(prefix + '-preview-img');
            const ph = document.getElementById(prefix + '-placeholder');
            if (img) { img.src = file.url; img.classList.remove('hidden'); }
            if (ph) ph.classList.add('hidden');
        }
    }, {multiple: false, folder: 'logos'});
}
function previewFile(input, imgId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            const ph = document.getElementById(placeholderId);
            if (img) { img.src = e.target.result; img.classList.remove('hidden'); }
            if (ph) ph.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function addFooterLink(col) {
    const html = `<div class="footer-link-row flex gap-2 items-center">
        <input type="text" name="footer_links_col${col}_label[]" placeholder="Link Text" class="flex-1 px-3 py-2 border rounded-lg text-sm">
        <input type="text" name="footer_links_col${col}_url[]" placeholder="/page or https://..." class="flex-1 px-3 py-2 border rounded-lg text-sm">
        <button type="button" onclick="this.closest('.footer-link-row').remove()" class="text-red-400 hover:text-red-600 px-2"><i class="fas fa-trash"></i></button>
    </div>`;
    document.getElementById('footer-links-' + col).insertAdjacentHTML('beforeend', html);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
