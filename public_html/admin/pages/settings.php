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
    
    // Handle custom font upload (.woff2, .woff, .ttf, .otf)
    if (!empty($_FILES['custom_font_file']['name'])) {
        $fontFile = $_FILES['custom_font_file'];
        $ext = strtolower(pathinfo($fontFile['name'], PATHINFO_EXTENSION));
        $allowedFontExts = ['woff2', 'woff', 'ttf', 'otf'];
        if (in_array($ext, $allowedFontExts) && $fontFile['size'] <= 5 * 1024 * 1024) {
            $fontsDir = ROOT_PATH . 'uploads/fonts';
            if (!is_dir($fontsDir)) mkdir($fontsDir, 0755, true);
            $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fontFile['name']);
            $destPath = $fontsDir . '/' . $safeName;
            if (move_uploaded_file($fontFile['tmp_name'], $destPath)) {
                $fontName = $_POST['custom_font_name'] ?? pathinfo($safeName, PATHINFO_FILENAME);
                $fontName = trim($fontName) ?: pathinfo($safeName, PATHINFO_FILENAME);
                // Load existing custom fonts
                $customFonts = json_decode(getSetting('custom_fonts', '[]'), true) ?: [];
                $customFonts[] = [
                    'name' => $fontName,
                    'file' => 'fonts/' . $safeName,
                    'format' => $ext,
                    'uploaded' => date('Y-m-d H:i:s'),
                ];
                updateSetting('custom_fonts', json_encode($customFonts, JSON_UNESCAPED_UNICODE));
            }
        }
    }
    
    // Handle custom font deletion
    if (!empty($_POST['delete_custom_font'])) {
        $delIdx = intval($_POST['delete_custom_font']);
        $customFonts = json_decode(getSetting('custom_fonts', '[]'), true) ?: [];
        if (isset($customFonts[$delIdx])) {
            $delFile = ROOT_PATH . 'uploads/' . $customFonts[$delIdx]['file'];
            if (file_exists($delFile)) @unlink($delFile);
            array_splice($customFonts, $delIdx, 1);
            updateSetting('custom_fonts', json_encode($customFonts, JSON_UNESCAPED_UNICODE));
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
        'email' => ['smtp_enabled'],
        'language' => ['show_lang_toggle'],
        'social' => ['fab_enabled','fab_call_enabled','fab_chat_enabled','fab_whatsapp_enabled','fab_messenger_enabled'],
    ];
    if (isset($checkboxMap[$section])) {
        foreach ($checkboxMap[$section] as $cb) {
            if (!isset($_POST[$cb])) updateSetting($cb, '0');
        }
    }
    
    try {
        $adminId = getAdminId();
        if ($adminId) {
            logActivity($adminId, 'update', 'settings', 0, "Updated {$section} settings");
        }
    } catch (Exception $e) {
        // Silently skip if admin ID doesn't match
    }
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
    return imgSrc('logos', $val);
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
                'fontsizes' => ['Typography', 'fa-text-height'],
                'language' => ['Language', 'fa-language'],
                'header' => ['Header & Nav', 'fa-bars'],
                'footer' => ['Footer', 'fa-window-minimize'],
                'shipping' => ['Shipping', 'fa-truck'],
                'social' => ['Social & Contact', 'fa-share-alt'],
                'tracking' => ['Tracking & Pixels', 'fa-chart-bar'],
                'checkout' => ['Checkout & Labels', 'fa-shopping-cart'],
                'registration' => ['Registration Fields', 'fa-user-plus'],
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

            <?php elseif ($tab === 'fontsizes'): ?>
            <!-- ═══════ FONT FAMILY PICKER ═══════ -->
            <?php
            $currentFont = $s['site_font_family'] ?? 'Hind Siliguri';
            $currentWeight = $s['site_font_weight'] ?? '400';
            $currentHeadingFont = $s['site_heading_font'] ?? '';
            $currentHeadingWeight = $s['site_heading_weight'] ?? '700';
            $customFonts = json_decode($s['custom_fonts'] ?? '[]', true) ?: [];

            // Font list: [name, google_url, category, bangla, weights[], buggy_weights]
            $fontList = [
                // ── Bangla ──
                ['Hind Siliguri', 'Hind+Siliguri:wght@300;400;500;600;700', 'bangla', true, [300,400,500,600,700], [400,500,600]],
                ['Noto Sans Bengali', 'Noto+Sans+Bengali:wght@300;400;500;600;700', 'bangla', true, [300,400,500,600,700], []],
                ['Noto Serif Bengali', 'Noto+Serif+Bengali:wght@300;400;500;600;700', 'bangla', true, [300,400,500,600,700], []],
                ['Baloo Da 2', 'Baloo+Da+2:wght@400;500;600;700;800', 'bangla', true, [400,500,600,700,800], []],
                ['Galada', 'Galada', 'bangla', true, [400], []],
                ['Atma', 'Atma:wght@300;400;500;600;700', 'bangla', true, [300,400,500,600,700], []],
                ['Mina', 'Mina:wght@400;700', 'bangla', true, [400,700], []],
                ['Anek Bangla', 'Anek+Bangla:wght@300;400;500;600;700', 'bangla', true, [300,400,500,600,700], []],
                ['Tiro Bangla', 'Tiro+Bangla:ital@0;1', 'bangla', true, [400], []],
                // ── Sans-Serif ──
                ['Inter', 'Inter:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Poppins', 'Poppins:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Roboto', 'Roboto:wght@300;400;500;700;900', 'sans', false, [300,400,500,700,900], []],
                ['Open Sans', 'Open+Sans:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Nunito', 'Nunito:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Nunito Sans', 'Nunito+Sans:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Lato', 'Lato:wght@300;400;700;900', 'sans', false, [300,400,700,900], []],
                ['Montserrat', 'Montserrat:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Raleway', 'Raleway:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Rubik', 'Rubik:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Work Sans', 'Work+Sans:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['DM Sans', 'DM+Sans:wght@400;500;700', 'sans', false, [400,500,700], []],
                ['Plus Jakarta Sans', 'Plus+Jakarta+Sans:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Outfit', 'Outfit:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Figtree', 'Figtree:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Manrope', 'Manrope:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Source Sans 3', 'Source+Sans+3:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Quicksand', 'Quicksand:wght@300;400;500;600;700', 'sans', false, [300,400,500,600,700], []],
                ['Lexend', 'Lexend:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Sora', 'Sora:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                ['Space Grotesk', 'Space+Grotesk:wght@300;400;500;600;700', 'sans', false, [300,400,500,600,700], []],
                ['Albert Sans', 'Albert+Sans:wght@300;400;500;600;700;800', 'sans', false, [300,400,500,600,700,800], []],
                // ── Serif ──
                ['Playfair Display', 'Playfair+Display:wght@400;500;600;700;800', 'serif', false, [400,500,600,700,800], []],
                ['Merriweather', 'Merriweather:wght@300;400;700;900', 'serif', false, [300,400,700,900], []],
                ['Lora', 'Lora:wght@400;500;600;700', 'serif', false, [400,500,600,700], []],
                ['PT Serif', 'PT+Serif:wght@400;700', 'serif', false, [400,700], []],
                ['Crimson Text', 'Crimson+Text:wght@400;600;700', 'serif', false, [400,600,700], []],
                ['Libre Baskerville', 'Libre+Baskerville:wght@400;700', 'serif', false, [400,700], []],
                ['Source Serif 4', 'Source+Serif+4:wght@300;400;500;600;700', 'serif', false, [300,400,500,600,700], []],
                ['DM Serif Display', 'DM+Serif+Display', 'serif', false, [400], []],
                // ── Display ──
                ['Oswald', 'Oswald:wght@300;400;500;600;700', 'display', false, [300,400,500,600,700], []],
                ['Bebas Neue', 'Bebas+Neue', 'display', false, [400], []],
                ['Anton', 'Anton', 'display', false, [400], []],
                ['Righteous', 'Righteous', 'display', false, [400], []],
                ['Archivo Black', 'Archivo+Black', 'display', false, [400], []],
                ['Barlow Condensed', 'Barlow+Condensed:wght@400;500;600;700;800', 'display', false, [400,500,600,700,800], []],
                // ── Rounded ──
                ['Comfortaa', 'Comfortaa:wght@300;400;500;600;700', 'rounded', false, [300,400,500,600,700], []],
                ['Varela Round', 'Varela+Round', 'rounded', false, [400], []],
                ['Fredoka', 'Fredoka:wght@300;400;500;600;700', 'rounded', false, [300,400,500,600,700], []],
                // ── Mono ──
                ['JetBrains Mono', 'JetBrains+Mono:wght@300;400;500;600;700', 'mono', false, [300,400,500,600,700], []],
                ['Fira Code', 'Fira+Code:wght@300;400;500;600;700', 'mono', false, [300,400,500,600,700], []],
                // ── System ──
                ['Arial', '', 'system', false, [400,700], []],
                ['Georgia', '', 'system', false, [400,700], []],
                ['Verdana', '', 'system', false, [400,700], []],
                ['Tahoma', '', 'system', false, [400,700], []],
                ['Segoe UI', '', 'system', false, [300,400,600,700], []],
                ['system-ui', '', 'system', false, [300,400,500,600,700], []],
            ];
            $catMeta = [
                'bangla'  => ['Bangla', 'fas fa-globe-asia', 'green'],
                'sans'    => ['Sans-Serif', 'fas fa-font', 'blue'],
                'serif'   => ['Serif', 'fas fa-feather-alt', 'purple'],
                'display' => ['Display', 'fas fa-heading', 'red'],
                'rounded' => ['Rounded', 'fas fa-circle', 'pink'],
                'mono'    => ['Monospace', 'fas fa-code', 'gray'],
                'system'  => ['System', 'fas fa-desktop', 'gray'],
                'custom'  => ['Custom', 'fas fa-upload', 'orange'],
            ];
            $weightLabels = [100=>'Thin',200=>'ExtraLight',300=>'Light',400=>'Regular',500=>'Medium',600=>'SemiBold',700=>'Bold',800=>'ExtraBold',900=>'Black'];

            // Merge custom fonts
            $allFonts = $fontList;
            foreach ($customFonts as $ci => $cf) {
                $allFonts[] = [$cf['name'], '__custom__' . $ci, 'custom', false, $cf['weights'] ?? [400,700], []];
            }
            ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-font mr-2 text-purple-500"></i>Font Family</h4>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Current:</span>
                        <strong class="text-xs text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full" id="currentFontLabel"><?= e($currentFont) ?> (<?= e($weightLabels[(int)$currentWeight] ?? $currentWeight) ?>)</strong>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="flex flex-wrap gap-1.5" id="fontCatFilters">
                    <button type="button" onclick="filterFonts('all')" class="fcat-btn active" data-cat="all">
                        <i class="fas fa-th-large mr-1"></i>All (<?= count($allFonts) ?>)
                    </button>
                    <?php foreach ($catMeta as $ck => $cv):
                        $cc = 0;
                        if ($ck === 'custom') { $cc = count($customFonts); }
                        else { foreach ($fontList as $fl) { if ($fl[2] === $ck) $cc++; } }
                        if ($cc === 0 && $ck !== 'custom') continue;
                    ?>
                    <button type="button" onclick="filterFonts('<?= $ck ?>')" class="fcat-btn" data-cat="<?= $ck ?>">
                        <i class="<?= $cv[1] ?> mr-1"></i><?= $cv[0] ?> (<?= $cc ?>)
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Search -->
                <input type="text" id="fontSearchInput" placeholder="Search fonts..." oninput="searchFonts(this.value)"
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">

                <!-- Hidden Inputs -->
                <input type="hidden" name="site_font_family" id="siteFontFamily" value="<?= e($currentFont) ?>">
                <input type="hidden" name="site_font_url" id="siteFontUrl" value="<?= e($s['site_font_url'] ?? '') ?>">
                <input type="hidden" name="site_font_weight" id="siteFontWeight" value="<?= e($currentWeight) ?>">

                <!-- Font Grid -->
                <div class="space-y-2 max-h-[540px] overflow-y-auto pr-1" id="fontGrid" style="scrollbar-width:thin">
                    <?php foreach ($allFonts as $fi => $font):
                        $fname = $font[0]; $furl = $font[1]; $fcat = $font[2]; $fbn = $font[3]; $fweights = $font[4]; $fbuggy = $font[5] ?? [];
                        $isSelected = ($currentFont === $fname);
                        $isCustom = (strpos($furl, '__custom__') === 0);
                        $fallback = in_array($fcat, ['serif','display']) ? 'serif' : ($fcat === 'mono' ? 'monospace' : 'sans-serif');
                    ?>
                    <div class="font-card border rounded-xl overflow-hidden cursor-pointer transition-all hover:border-purple-300 hover:shadow-sm <?= $isSelected ? 'ring-2 ring-purple-500 border-purple-400 bg-purple-50/50' : 'border-gray-200' ?>"
                         data-font="<?= e($fname) ?>" data-url="<?= e($furl) ?>" data-cat="<?= $fcat ?>"
                         data-weights='<?= json_encode($fweights) ?>'
                         data-buggy='<?= json_encode($fbuggy) ?>'
                         onclick="selectFont(this)">
                        <!-- Top Row -->
                        <div class="flex items-center justify-between px-3 pt-2.5 pb-1">
                            <div class="flex items-center gap-2">
                                <span class="font-card-check w-5 h-5 rounded-full border-2 flex items-center justify-center text-[10px] flex-shrink-0 transition <?= $isSelected ? 'bg-purple-500 border-purple-500 text-white' : 'border-gray-300' ?>">
                                    <?= $isSelected ? '<i class="fas fa-check"></i>' : '' ?>
                                </span>
                                <span class="text-sm font-semibold text-gray-800"><?= e($fname) ?></span>
                                <?php if ($fbn): ?><span class="text-[10px] px-1.5 py-0.5 bg-green-100 text-green-700 rounded font-medium">BN</span><?php endif; ?>
                                <?php if (!empty($fbuggy)): ?><span class="text-[10px] px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded font-medium" title="Bengali ১ broken at <?= implode('/', $fbuggy) ?>">⚠ Bug</span><?php endif; ?>
                                <?php if ($isCustom): ?><span class="text-[10px] px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded font-medium">Custom</span><?php endif; ?>
                            </div>
                            <span class="text-[10px] text-gray-400"><?= count($fweights) ?> weight<?= count($fweights) > 1 ? 's' : '' ?></span>
                        </div>
                        <!-- Preview -->
                        <div class="px-3 pb-1.5">
                            <p class="font-preview text-lg text-gray-600 truncate" data-gurl="<?= e($isCustom ? '' : $furl) ?>"
                               style="font-family:'<?= e($fname) ?>',<?= $fallback ?>">
                                <?= $fbn ? 'বাংলা ১২৩৪৫ প্রিভিউ — ' : '' ?>The quick brown fox jumps 0123
                            </p>
                        </div>
                        <!-- Weight Variations (shown when selected) -->
                        <div class="font-weights px-3 pb-2.5 flex flex-wrap gap-1 <?= $isSelected ? '' : 'hidden' ?>">
                            <?php foreach ($fweights as $w): ?>
                            <?php $isBuggyW = in_array($w, $fbuggy); ?>
                            <button type="button"
                                    class="wt-btn px-2 py-0.5 text-[11px] rounded border transition <?= ($isSelected && (int)$currentWeight === $w) ? 'bg-purple-600 text-white border-purple-600' : ($isBuggyW ? 'border-amber-300 text-amber-600 bg-amber-50' : 'border-gray-300 text-gray-600 hover:border-purple-400') ?>"
                                    style="font-family:'<?= e($fname) ?>',<?= $fallback ?>;font-weight:<?= $w ?>"
                                    data-w="<?= $w ?>"
                                    onclick="event.stopPropagation();selectWeight(this, <?= $w ?>)"
                                    <?= $isBuggyW ? 'title="⚠ Known bug: Bengali ১ renders incorrectly at this weight"' : '' ?>>
                                <?= $isBuggyW ? '<i class="fas fa-exclamation-triangle text-amber-500 mr-0.5"></i>' : '' ?><?= $weightLabels[$w] ?? $w ?> <small class="opacity-60">(<?= $w ?>)</small>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ═══════ CUSTOM FONT UPLOAD ═══════ -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-upload mr-2 text-orange-500"></i>Upload Custom Font</h4>
                <p class="text-xs text-gray-500">Upload .woff2, .woff, .ttf, or .otf files (max 5MB). After upload, your font appears in the "Custom" category above.</p>
                <div class="grid md:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Font Name</label>
                        <input type="text" name="custom_font_name" placeholder="e.g. My Brand Font"
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Font File</label>
                        <input type="file" name="custom_font_file" accept=".woff2,.woff,.ttf,.otf"
                               class="w-full px-3 py-1.5 border rounded-lg text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-orange-50 file:text-orange-700 file:font-medium file:cursor-pointer">
                    </div>
                    <button type="submit" name="action" value="upload_font" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-semibold hover:bg-orange-600 transition">
                        <i class="fas fa-upload mr-1"></i>Upload Font
                    </button>
                </div>
                <?php if (!empty($customFonts)): ?>
                <div class="border-t pt-3 mt-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase mb-2">Uploaded Fonts</h5>
                    <div class="space-y-2">
                        <?php foreach ($customFonts as $ci => $cf): ?>
                        <div class="flex items-center justify-between p-2.5 bg-orange-50 rounded-lg border border-orange-100">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-font text-orange-500"></i>
                                <div>
                                    <span class="text-sm font-semibold text-gray-800"><?= e($cf['name']) ?></span>
                                    <span class="text-xs text-gray-400 ml-2"><?= e($cf['file']) ?> &middot; <?= strtoupper($cf['format'] ?? 'woff2') ?></span>
                                </div>
                            </div>
                            <button type="submit" name="delete_custom_font" value="<?= $ci ?>"
                                    onclick="return confirm('Delete this font?')"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 rounded hover:bg-red-50">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══════ HEADING FONT (Optional) ═══════ -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <div class="flex items-center gap-2">
                    <i class="fas fa-heading text-indigo-400"></i>
                    <h4 class="font-semibold text-gray-800">Heading Font <span class="text-xs text-gray-400 font-normal">(optional — leave blank to use body font for headings)</span></h4>
                </div>
                <input type="hidden" name="site_heading_font" id="siteHeadingFont" value="<?= e($currentHeadingFont) ?>">
                <input type="hidden" name="site_heading_font_url" id="siteHeadingFontUrl" value="<?= e($s['site_heading_font_url'] ?? '') ?>">
                <input type="hidden" name="site_heading_weight" id="siteHeadingWeight" value="<?= e($currentHeadingWeight) ?>">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 max-h-52 overflow-y-auto" style="scrollbar-width:thin">
                    <div class="hfont-card border rounded-lg p-2.5 cursor-pointer text-center transition <?= empty($currentHeadingFont) ? 'ring-2 ring-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300' ?>"
                         data-font="" data-url="" onclick="selectHeadingFont(this)">
                        <span class="text-xs font-medium text-gray-500"><i class="fas fa-equals mr-1"></i>Same as body</span>
                    </div>
                    <?php foreach ($allFonts as $font):
                        $fname = $font[0]; $furl = $font[1]; $fcat = $font[2];
                        $isCustomH = (strpos($furl, '__custom__') === 0);
                        if ($fcat === 'system' && !$isCustomH) continue;
                        $fallback = in_array($fcat, ['serif','display']) ? 'serif' : 'sans-serif';
                        $isSelH = ($currentHeadingFont === $fname);
                    ?>
                    <div class="hfont-card border rounded-lg p-2.5 cursor-pointer text-center transition <?= $isSelH ? 'ring-2 ring-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300' ?>"
                         data-font="<?= e($fname) ?>" data-url="<?= e($isCustomH ? '' : $furl) ?>" onclick="selectHeadingFont(this)">
                        <span class="text-xs font-semibold" style="font-family:'<?= e($fname) ?>',<?= $fallback ?>"><?= e($fname) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ═══════ LIVE PREVIEW ═══════ -->
            <div id="buggyWeightWarn" class="hidden text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-3">
                <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide"><i class="fas fa-eye mr-1"></i>Live Preview</h5>
                <div class="border rounded-xl p-5 bg-gradient-to-br from-gray-50 to-white" id="fontLivePreview">
                    <h2 id="previewHeading" class="text-2xl font-bold text-gray-800 mb-2"
                        style="font-family:'<?= e($currentHeadingFont ?: $currentFont) ?>',sans-serif;font-weight:<?= e($currentHeadingWeight) ?>">
                        Premium Quality Products — প্রিমিয়াম মানের পণ্য
                    </h2>
                    <p id="previewBody" class="text-base text-gray-600 mb-3"
                       style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:<?= e($currentWeight) ?>">
                        আমাদের দোকানে সেরা মানের পণ্য পাবেন। The quick brown fox jumps over the lazy dog.
                    </p>
                    <p id="previewBnDigits" class="text-base text-gray-700 mb-3"
                       style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:<?= e($currentWeight) ?>">
                        <span class="text-xs text-gray-400 mr-2">Bengali Digits:</span>
                        ০ ১ ২ ৩ ৪ ৫ ৬ ৭ ৮ ৯ &nbsp; ১২৩৪৫ &nbsp; ৳১,২৯৯
                    </p>
                    <div class="flex gap-3 items-center flex-wrap">
                        <span id="previewPrice" class="text-xl font-bold text-red-600" style="font-family:'<?= e($currentFont) ?>',sans-serif">৳1,299</span>
                        <span class="text-sm text-gray-400 line-through" style="font-family:'<?= e($currentFont) ?>',sans-serif">৳1,999</span>
                        <button type="button" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" id="previewBtn"
                                style="background:var(--primary,#e53e3e);font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:<?= e($currentWeight) ?>">
                            অর্ডার করুন / Order Now
                        </button>
                    </div>
                </div>
                <!-- Weight preview row -->
                <div class="flex flex-wrap gap-2" id="weightPreviewRow">
                    <span class="text-xs text-gray-400">Weight preview:</span>
                    <span id="wp300" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:300">Light 300</span>
                    <span id="wp400" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:400">Regular 400</span>
                    <span id="wp500" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:500">Medium 500</span>
                    <span id="wp600" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:600">SemiBold 600</span>
                    <span id="wp700" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:700">Bold 700</span>
                    <span id="wp800" class="text-sm text-gray-600" style="font-family:'<?= e($currentFont) ?>',sans-serif;font-weight:800">ExtraBold 800</span>
                </div>
            </div>

            <!-- ═══════ TYPOGRAPHY CSS & JS ═══════ -->
            <style>
            .fcat-btn{background:#f9fafb;color:#6b7280;border:1.5px solid #e5e7eb;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:600;transition:all .2s;cursor:pointer}
            .fcat-btn.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
            .fcat-btn:hover:not(.active){background:#f3f0ff;border-color:#c4b5fd;color:#6d28d9}
            </style>
            <?php
            // Inject @font-face for custom fonts
            if (!empty($customFonts)):
            ?>
            <style>
            <?php foreach ($customFonts as $cf):
                $cfUrl = '<?= SITE_URL ?>/uploads/' . $cf['file'];
                $fmt = $cf['format'] ?? 'woff2';
                $fmtMap = ['woff2'=>'woff2','woff'=>'woff','ttf'=>'truetype','otf'=>'opentype'];
                $fmtStr = $fmtMap[$fmt] ?? 'woff2';
            ?>
            @font-face {
                font-family: '<?= e($cf['name']) ?>';
                src: url('<?= htmlspecialchars(SITE_URL . '/uploads/' . $cf['file']) ?>') format('<?= $fmtStr ?>');
                font-display: swap;
            }
            <?php endforeach; ?>
            </style>
            <?php endif; ?>
            <script>
            const _loadedFonts = new Set();
            function loadGFont(name, url) {
                if (!url || url.startsWith('__custom__') || _loadedFonts.has(name)) return;
                const lnk = document.createElement('link');
                lnk.rel = 'stylesheet';
                lnk.href = 'https://fonts.googleapis.com/css2?family=' + url + '&display=swap';
                document.head.appendChild(lnk);
                _loadedFonts.add(name);
            }
            // Lazy-load fonts on scroll
            const fObs = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) {
                    const p = e.target.querySelector('.font-preview');
                    if (p && p.dataset.gurl) loadGFont(e.target.dataset.font, p.dataset.gurl);
                    fObs.unobserve(e.target);
                }});
            }, { rootMargin: '200px' });
            document.querySelectorAll('.font-card').forEach(c => fObs.observe(c));

            function selectFont(card) {
                const font = card.dataset.font, url = card.dataset.url;
                if (url && !url.startsWith('__custom__')) loadGFont(font, url);
                document.getElementById('siteFontFamily').value = font;
                document.getElementById('siteFontUrl').value = (url && !url.startsWith('__custom__')) ? url : '';
                // Visual update
                document.querySelectorAll('.font-card').forEach(c => {
                    c.classList.remove('ring-2','ring-purple-500','border-purple-400','bg-purple-50/50');
                    c.classList.add('border-gray-200');
                    const ck = c.querySelector('.font-card-check');
                    ck.className = 'font-card-check w-5 h-5 rounded-full border-2 flex items-center justify-center text-[10px] flex-shrink-0 transition border-gray-300';
                    ck.innerHTML = '';
                    c.querySelector('.font-weights')?.classList.add('hidden');
                });
                card.classList.add('ring-2','ring-purple-500','border-purple-400','bg-purple-50/50');
                card.classList.remove('border-gray-200');
                const ck = card.querySelector('.font-card-check');
                ck.className = 'font-card-check w-5 h-5 rounded-full border-2 flex items-center justify-center text-[10px] flex-shrink-0 transition bg-purple-500 border-purple-500 text-white';
                ck.innerHTML = '<i class="fas fa-check"></i>';
                card.querySelector('.font-weights')?.classList.remove('hidden');
                // Auto-select 400 weight or first available
                const btn400 = card.querySelector('.wt-btn[data-w="400"]') || card.querySelector('.wt-btn');
                if (btn400) selectWeight(btn400, parseInt(btn400.dataset.w) || 400);
                updatePreview();
                updateLabel();
            }

            function selectWeight(btn, w) {
                if (!btn) return;
                const card = btn.closest('.font-card');
                // Reset all weight buttons
                card.querySelectorAll('.wt-btn').forEach(b => {
                    const buggy = JSON.parse(card.dataset.buggy || '[]');
                    const bw = parseInt(b.dataset.w);
                    if (buggy.includes(bw)) {
                        b.className = 'wt-btn px-2 py-0.5 text-[11px] rounded border transition border-amber-300 text-amber-600 bg-amber-50';
                    } else {
                        b.className = 'wt-btn px-2 py-0.5 text-[11px] rounded border transition border-gray-300 text-gray-600 hover:border-purple-400';
                    }
                });
                btn.classList.add('bg-purple-600','text-white','border-purple-600');
                btn.classList.remove('border-gray-300','text-gray-600','border-amber-300','text-amber-600','bg-amber-50');
                document.getElementById('siteFontWeight').value = w;
                // Show warning for buggy weights
                const buggyWeights = JSON.parse(card.dataset.buggy || '[]');
                const warnEl = document.getElementById('buggyWeightWarn');
                if (warnEl) {
                    if (buggyWeights.includes(w)) {
                        warnEl.classList.remove('hidden');
                        warnEl.textContent = '⚠ Known Google Fonts bug: Bengali digit "১" may render incorrectly at weight ' + w + ' in ' + card.dataset.font + '. Use weight 300 (Light) or 700 (Bold) instead, or switch to Noto Sans Bengali.';
                    } else {
                        warnEl.classList.add('hidden');
                    }
                }
                updatePreview();
                updateLabel();
            }

            const WL = {100:'Thin',200:'ExtraLight',300:'Light',400:'Regular',500:'Medium',600:'SemiBold',700:'Bold',800:'ExtraBold',900:'Black'};
            function updateLabel() {
                const f = document.getElementById('siteFontFamily').value;
                const w = document.getElementById('siteFontWeight').value;
                document.getElementById('currentFontLabel').textContent = f + ' (' + (WL[w]||w) + ')';
            }

            function updatePreview() {
                const f = document.getElementById('siteFontFamily').value;
                const w = document.getElementById('siteFontWeight').value;
                const hf = document.getElementById('siteHeadingFont').value || f;
                const hw = document.getElementById('siteHeadingWeight').value || '700';
                const ff = "'" + f + "', sans-serif";
                const hff = "'" + hf + "', sans-serif";
                const ph = document.getElementById('previewHeading');
                if (ph) { ph.style.fontFamily = hff; ph.style.fontWeight = hw; }
                ['previewBody','previewBnDigits','previewPrice','previewBtn'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) { el.style.fontFamily = ff; el.style.fontWeight = w; }
                });
                // Update weight preview row
                [300,400,500,600,700,800].forEach(ww => {
                    const el = document.getElementById('wp' + ww);
                    if (el) el.style.fontFamily = ff;
                });
            }

            function selectHeadingFont(card) {
                const font = card.dataset.font, url = card.dataset.url;
                if (url) loadGFont(font, url);
                document.getElementById('siteHeadingFont').value = font;
                document.getElementById('siteHeadingFontUrl').value = url || '';
                document.querySelectorAll('.hfont-card').forEach(c => {
                    c.classList.remove('ring-2','ring-indigo-500','bg-indigo-50');
                    c.classList.add('border-gray-200');
                });
                card.classList.add('ring-2','ring-indigo-500','bg-indigo-50');
                card.classList.remove('border-gray-200');
                updatePreview();
            }

            function filterFonts(cat) {
                document.querySelectorAll('.fcat-btn').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
                document.querySelectorAll('.font-card').forEach(c => {
                    c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
                });
            }
            function searchFonts(q) {
                q = q.toLowerCase().trim();
                document.querySelectorAll('.font-card').forEach(c => {
                    c.style.display = (!q || c.dataset.font.toLowerCase().includes(q) || c.dataset.cat.includes(q)) ? '' : 'none';
                });
            }
            </script>

            <!-- ═══════ FONT SIZES ═══════ -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-5">
                <div class="flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-text-height mr-2 text-indigo-500"></i>Website Font Sizes</h4>
                    <button type="button" onclick="resetFontSizes()" class="text-xs text-red-500 hover:text-red-700 font-medium"><i class="fas fa-undo mr-1"></i>Reset to Default</button>
                </div>
                <p class="text-xs text-gray-500">Control text sizes across your website. Values in pixels (px). Changes apply instantly after saving.</p>

                <?php
                $fontSizeGroups = [
                    'Header & Navigation' => [
                        'fs_announcement'     => ['Announcement Bar', '13', 'Top bar text (phone, hotline)'],
                        'fs_nav_menu'         => ['Navigation Menu', '14', 'Category/menu links in navbar'],
                        'fs_mobile_menu'      => ['Mobile Menu Items', '15', 'Side drawer menu links'],
                        'fs_search_input'     => ['Search Input', '14', 'Search box placeholder & text'],
                    ],
                    'Home Page Sections' => [
                        'fs_section_heading'  => ['Section Headings', '22', 'Sale, Featured, All Products titles'],
                        'fs_section_link'     => ['Section "View All" Link', '14', '"আরো দেখুন" type links'],
                        'fs_banner_title'     => ['Banner Title', '28', 'Hero slider overlay text'],
                        'fs_banner_subtitle'  => ['Banner Subtitle', '16', 'Hero slider subtitle text'],
                        'fs_category_name'    => ['Category Names', '12', 'Circle category labels'],
                        'fs_trust_title'      => ['Trust Badge Title', '14', 'Trust section headings'],
                        'fs_trust_subtitle'   => ['Trust Badge Subtitle', '12', 'Trust section descriptions'],
                    ],
                    'Product Card' => [
                        'fs_card_name'        => ['Product Name', '14', 'Product title on cards'],
                        'fs_card_price'       => ['Product Price', '16', 'Current price on cards'],
                        'fs_card_old_price'   => ['Old/Strike Price', '12', 'Crossed-out price on cards'],
                        'fs_card_badge'       => ['Discount Badge', '12', '"-20%" badge on cards'],
                        'fs_card_button'      => ['Card Buttons', '13', 'Add to Cart / Order buttons'],
                    ],
                    'Product Detail Page' => [
                        'fs_product_title'    => ['Product Title', '26', 'Main title on product page'],
                        'fs_product_price'    => ['Product Price', '30', 'Price on product page'],
                        'fs_product_desc'     => ['Description Text', '15', 'Product description body'],
                        'fs_order_button'     => ['Order Button', '16', 'COD Order / Cart button text'],
                    ],
                    'Footer' => [
                        'fs_footer_heading'   => ['Footer Headings', '20', 'Column titles in footer'],
                        'fs_footer_text'      => ['Footer Body Text', '14', 'Footer links, about text'],
                        'fs_footer_copyright' => ['Copyright Text', '14', 'Bottom bar copyright'],
                    ],
                    'Global / Body' => [
                        'fs_body'             => ['Body Base Font', '15', 'Default text size sitewide'],
                        'fs_button_global'    => ['Global Button Text', '14', 'Default button label size'],
                        'fs_price_global'     => ['Price Text (Global)', '16', 'Prices across entire site'],
                    ],
                ];
                foreach ($fontSizeGroups as $groupName => $fields): ?>
                <div class="border border-gray-100 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-100">
                        <h5 class="text-sm font-semibold text-gray-700"><?= $groupName ?></h5>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($fields as $key => $info):
                            $curVal = $s[$key] ?? $info[1];
                        ?>
                        <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-700"><?= $info[0] ?></label>
                                <span class="text-xs text-gray-400"><?= $info[2] ?></span>
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <button type="button" onclick="adjustFs('<?= $key ?>', -1)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 text-xs font-bold transition">−</button>
                                <input type="number" name="<?= $key ?>" id="fs_<?= $key ?>" value="<?= e($curVal) ?>" min="8" max="60"
                                       class="w-16 text-center border border-gray-200 rounded-lg py-1.5 text-sm font-mono font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-300 fs-input"
                                       data-default="<?= $info[1] ?>">
                                <button type="button" onclick="adjustFs('<?= $key ?>', 1)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 text-xs font-bold transition">+</button>
                                <span class="text-xs text-gray-400 w-6">px</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
            function adjustFs(key, delta) {
                const inp = document.getElementById('fs_' + key);
                if (!inp) return;
                let v = parseInt(inp.value) || 14;
                v = Math.max(8, Math.min(60, v + delta));
                inp.value = v;
                const def = parseInt(inp.dataset.default) || 14;
                inp.classList.toggle('ring-2', v !== def);
                inp.classList.toggle('ring-indigo-400', v !== def);
            }
            function resetFontSizes() {
                if (!confirm('Reset all font sizes to defaults?')) return;
                document.querySelectorAll('.fs-input').forEach(inp => {
                    inp.value = inp.dataset.default;
                    inp.classList.remove('ring-2', 'ring-indigo-400');
                });
            }
            </script>


            <?php elseif ($tab === 'language'): ?>
            <!-- ═══════ LANGUAGE TAB ═══════ -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-5">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-language mr-2 text-green-500"></i>Language Settings</h4>
                <p class="text-xs text-gray-500">Configure the Ban/Eng language toggle that appears in the header. Users can switch between Bangla and English on the frontend.</p>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Language</label>
                        <select name="default_language" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                            <option value="bn" <?= ($s['default_language'] ?? 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা (Bangla)</option>
                            <option value="en" <?= ($s['default_language'] ?? 'bn') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-3 mt-6 cursor-pointer">
                            <input type="checkbox" name="show_lang_toggle" value="1" <?= ($s['show_lang_toggle'] ?? '1') === '1' ? 'checked' : '' ?>
                                   class="w-4 h-4 text-blue-600 rounded">
                            <span class="text-sm font-medium text-gray-700">Show Ban/Eng toggle in header</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- English Labels -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-font mr-2 text-blue-500"></i>English Translations</h4>
                <p class="text-xs text-gray-500">When user switches to English, these labels replace the Bangla defaults. Leave blank to keep Bangla.</p>
                
                <?php
                $langGroups = [
                    'Header & Navigation' => [
                        'lang_search_placeholder' => ['Search Placeholder', 'পণ্য খুঁজুন...', 'Search products...'],
                        'lang_all_products'       => ['"All Products" Link', 'সকল পণ্য', 'All Products'],
                        'lang_login'              => ['Login Text', 'লগইন', 'Login'],
                        'lang_my_account'         => ['My Account', 'আমার একাউন্ট', 'My Account'],
                        'lang_wishlist'           => ['Wishlist', 'উইশলিস্ট', 'Wishlist'],
                        'lang_track_order'        => ['Track Order', 'অর্ডার ট্র্যাক', 'Track Order'],
                    ],
                    'Product & Cart' => [
                        'lang_add_to_cart'   => ['Add to Cart', 'কার্টে যোগ করুন', 'Add to Cart'],
                        'lang_order_now'     => ['Order Now', 'অর্ডার করুন', 'Order Now'],
                        'lang_out_of_stock'  => ['Out of Stock', 'স্টক শেষ', 'Out of Stock'],
                        'lang_discount'      => ['Discount Label', 'ছাড়', 'OFF'],
                        'lang_qty'           => ['Quantity', 'পরিমাণ', 'Quantity'],
                        'lang_description'   => ['Description Tab', 'বিবরণ', 'Description'],
                    ],
                    'Checkout & Order' => [
                        'lang_your_name'     => ['Name Field', 'আপনার নাম', 'Your Name'],
                        'lang_phone'         => ['Phone Field', 'মোবাইল নম্বর', 'Phone Number'],
                        'lang_address'       => ['Address Field', 'সম্পূর্ণ ঠিকানা', 'Full Address'],
                        'lang_place_order'   => ['Place Order Button', 'অর্ডার কনফার্ম করুন', 'Confirm Order'],
                        'lang_order_success' => ['Order Success', 'অর্ডার সফল হয়েছে!', 'Order placed successfully!'],
                        'lang_total'         => ['Total', 'মোট', 'Total'],
                        'lang_subtotal'      => ['Subtotal', 'সাবটোটাল', 'Subtotal'],
                        'lang_shipping'      => ['Shipping', 'ডেলিভারি চার্জ', 'Shipping Charge'],
                    ],
                    'Footer & Misc' => [
                        'lang_quick_links'   => ['Quick Links Heading', 'Quick Links', 'Quick Links'],
                        'lang_contact_us'    => ['Contact Us', 'যোগাযোগ', 'Contact Us'],
                        'lang_no_results'    => ['No Search Results', 'কোন পণ্য পাওয়া যায়নি', 'No products found'],
                        'lang_view_all'      => ['View All', 'সব দেখুন →', 'View All →'],
                    ],
                ];
                foreach ($langGroups as $gName => $fields): ?>
                <div class="border border-gray-100 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-100">
                        <h5 class="text-sm font-semibold text-gray-700"><?= $gName ?></h5>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($fields as $key => $info): ?>
                        <div class="grid grid-cols-3 gap-3 px-4 py-3 items-center">
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?= $info[0] ?></label>
                                <span class="text-xs text-gray-400">বাংলা: <?= $info[1] ?></span>
                            </div>
                            <input type="text" name="<?= $key ?>_bn" value="<?= e($s[$key . '_bn'] ?? $info[1]) ?>" 
                                   class="px-3 py-2 border rounded-lg text-sm" placeholder="বাংলা">
                            <input type="text" name="<?= $key ?>_en" value="<?= e($s[$key . '_en'] ?? $info[2]) ?>" 
                                   class="px-3 py-2 border rounded-lg text-sm" placeholder="English">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($tab === 'header'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-bullhorn mr-2 text-yellow-500"></i>Top Bar</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Announcement Text</label>
                    <input type="text" name="announcement_content" value="<?= e($s['announcement_content'] ?? $s['announcement_text'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Free delivery on orders over ৳1000!"></div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Header Phone</label>
                        <input type="text" name="header_phone" value="<?= e($s['header_phone'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hotline</label>
                        <input type="text" name="hotline_number" value="<?= e($s['hotline_number'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
            </div>

            <!-- Main Header Style -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-palette mr-2 text-indigo-500"></i>Header Style</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Header Background Style</label>
                        <select name="header_bg_style" class="w-full px-3 py-2.5 border rounded-lg text-sm" onchange="toggleHeaderStyle(this.value)">
                            <option value="solid" <?= ($s['header_bg_style'] ?? 'solid') === 'solid' ? 'selected' : '' ?>>Solid Color</option>
                            <option value="glass" <?= ($s['header_bg_style'] ?? 'solid') === 'glass' ? 'selected' : '' ?>>Glass Effect (Frosted)</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Logo Max Height (px)</label>
                        <input type="number" name="logo_max_height" value="<?= e($s['logo_max_height'] ?? '50') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
                <div id="header-glass-options" class="grid md:grid-cols-2 gap-4 <?= ($s['header_bg_style'] ?? 'solid') !== 'glass' ? 'hidden' : '' ?>">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Glass Opacity (0-100)</label>
                        <input type="number" name="header_glass_opacity" value="<?= e($s['header_glass_opacity'] ?? '85') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="0" max="100"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Glass Blur (px)</label>
                        <input type="number" name="header_glass_blur" value="<?= e($s['header_glass_blur'] ?? '12') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="0" max="50"></div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Header Height Desktop (px)</label>
                        <input type="number" name="header_height_desktop" value="<?= e($s['header_height_desktop'] ?? '80') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="50" max="120"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Header Height Mobile (px)</label>
                        <input type="number" name="header_height_mobile" value="<?= e($s['header_height_mobile'] ?? '64') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="40" max="100"></div>
                </div>
                <!-- Show/Hide Elements -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Header Elements</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_search" value="1" <?= ($s['header_show_search'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Search Bar</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_login" value="1" <?= ($s['header_show_login'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Login/Account</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_wishlist" value="1" <?= ($s['header_show_wishlist'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Wishlist</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_whatsapp" value="1" <?= ($s['header_show_whatsapp'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>WhatsApp</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_cart" value="1" <?= ($s['header_show_cart'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Cart Icon</span></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="header_show_lang" value="1" <?= ($s['header_show_lang'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Language Toggle</span></label>
                    </div>
                </div>
            </div>

            <!-- Category Nav Bar Style -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-bars mr-2 text-blue-500"></i>Category Navigation Bar</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nav Bar Background Style</label>
                        <select name="navbar_bg_style" class="w-full px-3 py-2.5 border rounded-lg text-sm" onchange="toggleNavStyle(this.value)">
                            <option value="solid" <?= ($s['navbar_bg_style'] ?? 'solid') === 'solid' ? 'selected' : '' ?>>Solid Color</option>
                            <option value="glass" <?= ($s['navbar_bg_style'] ?? 'solid') === 'glass' ? 'selected' : '' ?>>Glass Effect (Frosted)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Menu Alignment</label>
                        <select name="nav_menu_align" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                            <option value="left" <?= ($s['nav_menu_align'] ?? 'left') === 'left' ? 'selected' : '' ?>>Left</option>
                            <option value="center" <?= ($s['nav_menu_align'] ?? 'left') === 'center' ? 'selected' : '' ?>>Center</option>
                            <option value="right" <?= ($s['nav_menu_align'] ?? 'left') === 'right' ? 'selected' : '' ?>>Right</option>
                        </select>
                    </div>
                </div>
                <div id="nav-glass-options" class="grid md:grid-cols-2 gap-4 <?= ($s['navbar_bg_style'] ?? 'solid') !== 'glass' ? 'hidden' : '' ?>">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Glass Opacity (0-100)</label>
                        <input type="number" name="navbar_glass_opacity" value="<?= e($s['navbar_glass_opacity'] ?? '75') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="0" max="100"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Glass Blur (px)</label>
                        <input type="number" name="navbar_glass_blur" value="<?= e($s['navbar_glass_blur'] ?? '10') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" min="0" max="50"></div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Search Placeholder</label>
                        <input type="text" name="search_placeholder" value="<?= e($s['search_placeholder'] ?? 'পণ্য খুঁজুন...') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Categories in Nav</label>
                        <input type="number" name="nav_max_categories" value="<?= e($s['nav_max_categories'] ?? '8') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="nav_show_shop_link" value="1" <?= ($s['nav_show_shop_link'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Show "All Products" Link</span></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="nav_show_categories" value="1" <?= ($s['nav_show_categories'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-blue-600"><span>Show Categories</span></label>
                </div>
            </div>

            <!-- Mobile Product Page Sticky Bar -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-mobile-alt mr-2 text-green-500"></i>Mobile Product Page Design</h4>
                <p class="text-xs text-gray-500">Special mobile-optimized product page with a sticky buy bar replacing the navbar</p>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="mobile_product_sticky_bar" value="1" <?= ($s['mobile_product_sticky_bar'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded text-green-600" id="stickyBarToggle" onchange="document.getElementById('stickyBarOptions').classList.toggle('hidden', !this.checked)">
                    <span class="text-sm font-medium">Enable Sticky Buy Bar on Mobile Product Page</span>
                </label>
                <div id="stickyBarOptions" class="space-y-3 <?= ($s['mobile_product_sticky_bar'] ?? '0') === '1' ? '' : 'hidden' ?>">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="mobile_hide_nav_product" value="1" <?= ($s['mobile_hide_nav_product'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded text-green-600">
                        <span class="text-sm">Hide Navbar on Product Page (Mobile Only)</span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sticky Bar Background Style</label>
                        <select name="mobile_sticky_bg_style" class="w-full px-3 py-2.5 border rounded-lg text-sm" onchange="toggleStickyStyle(this.value)">
                            <option value="solid" <?= ($s['mobile_sticky_bg_style'] ?? 'solid') === 'solid' ? 'selected' : '' ?>>Solid Color</option>
                            <option value="glass" <?= ($s['mobile_sticky_bg_style'] ?? 'solid') === 'glass' ? 'selected' : '' ?>>Glass UI (Frosted)</option>
                        </select>
                    </div>
                    <div id="stickyBgColorWrap">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sticky Bar Background Color</label>
                        <input type="color" name="mobile_sticky_bg_color" value="<?= e($s['mobile_sticky_bg_color'] ?? '#ffffff') ?>" class="w-12 h-10 rounded border cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sticky Bar Text Color</label>
                        <input type="color" name="mobile_sticky_text_color" value="<?= e($s['mobile_sticky_text_color'] ?? '#1f2937') ?>" class="w-12 h-10 rounded border cursor-pointer">
                    </div>
                </div>
            </div>

            <script>
            function toggleHeaderStyle(v){ document.getElementById('header-glass-options').classList.toggle('hidden', v!=='glass'); }
            function toggleNavStyle(v){ document.getElementById('nav-glass-options').classList.toggle('hidden', v!=='glass'); }
            function toggleStickyStyle(v){ document.getElementById('stickyBgColorWrap').classList.toggle('hidden', v==='glass'); }
            </script>

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
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Inside Dhaka (৳)</label>
                        <input type="number" name="shipping_inside_dhaka" value="<?= e($s['shipping_inside_dhaka'] ?? '60') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Dhaka Subdivision (৳)</label>
                        <input type="number" name="shipping_dhaka_sub" value="<?= e($s['shipping_dhaka_sub'] ?? '100') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
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
                <p class="text-xs text-gray-500">Replaces the default chat bubble with a unified contact menu. Customers tap once to expand all your contact options.</p>

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
                            <div class="text-[10px] text-gray-400">Enter Facebook Page username or ID</div>
                        </div>
                    </div>
                    <div class="pl-12">
                        <input type="text" name="fab_messenger_id" value="<?= e($s['fab_messenger_id'] ?? '') ?>" placeholder="Page username or numeric ID" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>

                    <div class="p-3 rounded-lg bg-blue-50 border border-blue-100">
                        <p class="text-xs text-blue-600"><i class="fas fa-info-circle mr-1"></i> This replaces the standalone chat bubble with a single unified button. When only one option is enabled, it opens directly. With multiple options, it expands into a menu.</p>
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

            <!-- Order Now Button Behavior -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-shopping-cart mr-2 text-blue-500"></i>Order Now Button</h4>
                <p class="text-xs text-gray-500">Controls what happens when a customer clicks "অর্ডার করুন" (Order Now). When enabled, the cart is cleared first and only the selected product is shown in checkout. When disabled, the product is added to existing cart items.</p>
                <label class="flex items-center gap-2"><input type="hidden" name="order_now_clear_cart" value="0">
                    <input type="checkbox" name="order_now_clear_cart" value="1" class="rounded text-blue-600" <?= ($s['order_now_clear_cart'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">Clear cart on "Order Now" (show only selected product)</span></label>
            </div>

            <!-- Order Merge -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-object-group mr-2 text-purple-500"></i>Order Merging</h4>
                <p class="text-xs text-gray-500">When enabled, if the same customer (same phone + same address) places another order while their previous order is still pending, the new items will be merged into the existing order instead of creating a new one.</p>
                <label class="flex items-center gap-2"><input type="hidden" name="order_merge_enabled" value="0">
                    <input type="checkbox" name="order_merge_enabled" value="1" class="rounded text-purple-600" <?= ($s['order_merge_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">Enable Order Merging</span></label>
            </div>

            <!-- Store Credits -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-coins mr-2 text-yellow-500"></i>Store Credits</h4>
                <p class="text-xs text-gray-500">Registered customers can earn store credits when their orders are delivered. Credits are set per-product in the product editor.</p>
                <label class="flex items-center gap-2"><input type="hidden" name="store_credits_enabled" value="0">
                    <input type="checkbox" name="store_credits_enabled" value="1" class="rounded text-yellow-600" <?= ($s['store_credits_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">Enable Store Credits System</span></label>
                <label class="flex items-center gap-2"><input type="hidden" name="store_credit_checkout" value="0">
                    <input type="checkbox" name="store_credit_checkout" value="1" class="rounded text-yellow-600" <?= ($s['store_credit_checkout'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700">Allow customers to spend credits at checkout</span></label>
                <div class="border-t pt-3 mt-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-exchange-alt mr-1 text-yellow-500"></i> Credit Conversion Rate</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">1 Credit =</span>
                        <input type="number" name="store_credit_conversion_rate" step="0.01" min="0.01" max="1000" 
                            value="<?= e($s['store_credit_conversion_rate'] ?? '0.75') ?>" 
                            class="w-24 border rounded-lg px-3 py-2 text-sm text-center font-semibold">
                        <span class="text-sm text-gray-600">৳ (TK)</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Example: If rate is 0.75, a customer with 100 credits can use ৳75 at checkout.</p>
                </div>
            </div>

            <?php elseif ($tab === 'registration'): ?>
            <?php
            // Registration field definitions
            $regDefaults = [
                ['key'=>'name','label'=>'নাম','label_en'=>'Full Name','type'=>'text','enabled'=>true,'required'=>true,'placeholder'=>'আপনার নাম','icon'=>'fa-user','system'=>true],
                ['key'=>'phone','label'=>'ফোন নম্বর','label_en'=>'Phone Number','type'=>'tel','enabled'=>true,'required'=>true,'placeholder'=>'01XXXXXXXXX','icon'=>'fa-phone','system'=>true],
                ['key'=>'email','label'=>'ইমেইল','label_en'=>'Email','type'=>'email','enabled'=>true,'required'=>false,'placeholder'=>'email@example.com','icon'=>'fa-envelope','system'=>false],
                ['key'=>'password','label'=>'পাসওয়ার্ড','label_en'=>'Password','type'=>'password','enabled'=>true,'required'=>true,'placeholder'=>'কমপক্ষে ৬ অক্ষর','icon'=>'fa-lock','system'=>true],
                ['key'=>'confirm_password','label'=>'পাসওয়ার্ড নিশ্চিত করুন','label_en'=>'Confirm Password','type'=>'password','enabled'=>true,'required'=>true,'placeholder'=>'আবার পাসওয়ার্ড দিন','icon'=>'fa-lock','system'=>true],
                ['key'=>'address','label'=>'ঠিকানা','label_en'=>'Address','type'=>'textarea','enabled'=>false,'required'=>false,'placeholder'=>'বাসা/রোড নং, এলাকা, থানা','icon'=>'fa-map-marker-alt','system'=>false],
                ['key'=>'city','label'=>'শহর','label_en'=>'City','type'=>'text','enabled'=>false,'required'=>false,'placeholder'=>'শহরের নাম','icon'=>'fa-city','system'=>false],
                ['key'=>'district','label'=>'জেলা','label_en'=>'District','type'=>'text','enabled'=>false,'required'=>false,'placeholder'=>'জেলার নাম','icon'=>'fa-map','system'=>false],
                ['key'=>'alt_phone','label'=>'বিকল্প ফোন','label_en'=>'Alternative Phone','type'=>'tel','enabled'=>false,'required'=>false,'placeholder'=>'বিকল্প নম্বর','icon'=>'fa-phone-alt','system'=>false],
            ];
            $regJson = getSetting('registration_fields', '');
            $regFields = $regJson ? json_decode($regJson, true) : null;
            if (!$regFields) {
                $regFields = $regDefaults;
            } else {
                $seen = [];
                $regFields = array_values(array_filter($regFields, function($f) use (&$seen) { $k = $f['key'] ?? ''; if (isset($seen[$k])) return false; $seen[$k] = true; return true; }));
                $savedKeys = array_column($regFields, 'key');
                foreach ($regDefaults as $df) {
                    if (!in_array($df['key'], $savedKeys)) $regFields[] = $df;
                }
                foreach ($regFields as &$rf) {
                    $defMatch = array_filter($regDefaults, fn($d) => $d['key'] === $rf['key']);
                    if ($defMatch) { $def = reset($defMatch); foreach ($def as $dk => $dv) { if (!isset($rf[$dk])) $rf[$dk] = $dv; } }
                }
                unset($rf);
            }
            ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-semibold text-gray-800"><i class="fas fa-user-plus mr-2 text-blue-500"></i>Registration Form Fields</h4>
                        <p class="text-sm text-gray-500 mt-1">Customize which fields appear when customers create an account</p>
                    </div>
                    <button type="button" onclick="resetRegFields()" class="text-xs text-gray-500 hover:text-gray-700 bg-gray-100 px-3 py-1.5 rounded-lg"><i class="fas fa-undo mr-1"></i>Reset Default</button>
                </div>

                <div class="grid lg:grid-cols-5 gap-5">
                    <!-- Field List -->
                    <div class="lg:col-span-3">
                        <div id="regFieldList" class="space-y-2">
                            <?php foreach ($regFields as $rf): 
                                $isSys = $rf['system'] ?? false;
                            ?>
                            <div class="reg-field-item border rounded-xl p-3 flex items-center gap-3 transition <?= !($rf['enabled'] ?? true) ? 'opacity-50 bg-gray-50' : 'bg-white hover:shadow-sm' ?>"
                                 data-key="<?= $rf['key'] ?>" data-system="<?= $isSys ? '1' : '0' ?>">
                                <div class="drag-handle flex-shrink-0 text-gray-300 hover:text-gray-500 cursor-grab active:cursor-grabbing">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>
                                </div>
                                <div class="w-8 h-8 rounded-lg <?= ($rf['enabled'] ?? true) ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' ?> flex items-center justify-center flex-shrink-0">
                                    <i class="fas <?= $rf['icon'] ?? 'fa-input-text' ?> text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <input type="text" class="reg-label text-sm font-semibold text-gray-800 bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none px-0 py-0.5 w-full max-w-[180px]" value="<?= e($rf['label']) ?>">
                                        <?php if ($isSys): ?><span class="text-[10px] bg-purple-100 text-purple-600 px-1.5 py-0.5 rounded font-medium">CORE</span><?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] text-gray-400 uppercase"><?= $rf['key'] ?></span>
                                        <span class="text-[10px] text-gray-400">•</span>
                                        <input type="text" class="reg-placeholder text-[10px] text-gray-400 bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none px-0 py-0 max-w-[140px]" value="<?= e($rf['placeholder'] ?? '') ?>" placeholder="Placeholder...">
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5 flex-shrink-0">
                                    <?php if (!$isSys): ?>
                                    <label class="flex items-center gap-1 cursor-pointer" title="Required">
                                        <span class="text-[10px] text-gray-400">Required</span>
                                        <div class="relative">
                                            <input type="checkbox" class="reg-required sr-only" <?= ($rf['required'] ?? false) ? 'checked' : '' ?>>
                                            <div class="w-7 h-3.5 bg-gray-200 rounded-full toggle-track transition"></div>
                                            <div class="absolute left-0.5 top-0.5 w-2.5 h-2.5 bg-white rounded-full shadow toggle-dot transition"></div>
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer" title="Show">
                                        <span class="text-[10px] text-gray-400">Show</span>
                                        <div class="relative">
                                            <input type="checkbox" class="reg-enabled sr-only" <?= ($rf['enabled'] ?? true) ? 'checked' : '' ?>>
                                            <div class="w-7 h-3.5 bg-gray-200 rounded-full toggle-track transition"></div>
                                            <div class="absolute left-0.5 top-0.5 w-2.5 h-2.5 bg-white rounded-full shadow toggle-dot transition"></div>
                                        </div>
                                    </label>
                                    <?php else: ?>
                                    <span class="text-[10px] text-gray-400 italic">Always shown</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="lg:col-span-2">
                        <div class="sticky top-20">
                            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                                <div class="px-4 py-2.5 bg-gray-50 border-b">
                                    <h5 class="font-semibold text-gray-700 text-xs"><i class="fas fa-eye mr-1 text-green-500"></i>Registration Preview</h5>
                                </div>
                                <div id="regPreview" class="p-4 space-y-3 max-h-[500px] overflow-y-auto"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="registration_fields" id="regFieldsInput" value="">
            </div>

            <style>
            .reg-field-item .toggle-track { transition: background 0.2s; }
            .reg-enabled:checked ~ .toggle-track { background: #22c55e; }
            .reg-required:checked ~ .toggle-track { background: #f97316; }
            .reg-enabled:checked ~ .toggle-dot, .reg-required:checked ~ .toggle-dot { transform: translateX(14px); }
            .reg-field-item .toggle-dot { transition: transform 0.2s; }
            .reg-field-item.sortable-ghost { opacity: 0.3; background: #dbeafe !important; }
            .reg-field-item.sortable-chosen { box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10; }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
            <script>
            (function(){
                const list = document.getElementById('regFieldList');
                new Sortable(list, { handle: '.drag-handle', animation: 200, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', onEnd: updateRegPreview });

                list.addEventListener('change', function(e) {
                    if (e.target.classList.contains('reg-enabled')) {
                        const item = e.target.closest('.reg-field-item');
                        if (item) { item.classList.toggle('opacity-50', !e.target.checked); item.classList.toggle('bg-gray-50', !e.target.checked); item.classList.toggle('bg-white', e.target.checked); }
                    }
                    updateRegPreview();
                });
                list.addEventListener('input', updateRegPreview);

                function updateRegPreview() {
                    const preview = document.getElementById('regPreview');
                    let html = '';
                    list.querySelectorAll('.reg-field-item').forEach(item => {
                        const key = item.dataset.key;
                        const isSys = item.dataset.system === '1';
                        const enabled = isSys || (item.querySelector('.reg-enabled')?.checked ?? true);
                        if (!enabled) return;
                        const required = isSys || (item.querySelector('.reg-required')?.checked ?? false);
                        const label = item.querySelector('.reg-label')?.value || key;
                        const ph = item.querySelector('.reg-placeholder')?.value || '';
                        const star = required ? ' <span class="text-red-500">*</span>' : '';
                        const esc = s => { if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; };

                        if (key === 'address') {
                            html += '<div><label class="block text-xs font-medium text-gray-700 mb-0.5">'+esc(label)+star+'</label><textarea class="w-full border rounded-lg px-2.5 py-1.5 text-xs bg-gray-50" rows="2" placeholder="'+esc(ph)+'" disabled></textarea></div>';
                        } else {
                            const type = (key.includes('password')) ? 'password' : (key === 'email' ? 'email' : (key === 'phone' || key === 'alt_phone' ? 'tel' : 'text'));
                            html += '<div><label class="block text-xs font-medium text-gray-700 mb-0.5">'+esc(label)+star+'</label><input type="'+type+'" class="w-full border rounded-lg px-2.5 py-1.5 text-xs bg-gray-50" placeholder="'+esc(ph)+'" disabled></div>';
                        }
                    });
                    html += '<button class="w-full py-2 rounded-lg text-white font-bold text-xs bg-blue-600 opacity-80 cursor-default mt-2">রেজিস্ট্রেশন করুন</button>';
                    preview.innerHTML = html;
                    
                    // Also update hidden input for form save
                    serializeRegFields();
                }

                function serializeRegFields() {
                    const fields = [];
                    list.querySelectorAll('.reg-field-item').forEach(item => {
                        const isSys = item.dataset.system === '1';
                        fields.push({
                            key: item.dataset.key,
                            label: item.querySelector('.reg-label')?.value || '',
                            placeholder: item.querySelector('.reg-placeholder')?.value || '',
                            enabled: isSys || (item.querySelector('.reg-enabled')?.checked ?? true),
                            required: isSys || (item.querySelector('.reg-required')?.checked ?? false),
                        });
                    });
                    document.getElementById('regFieldsInput').value = JSON.stringify(fields);
                }

                window.resetRegFields = function() {
                    if (!confirm('Reset registration fields to default?')) return;
                    document.getElementById('regFieldsInput').value = JSON.stringify(<?= json_encode(array_map(fn($f) => ['key'=>$f['key'],'label'=>$f['label'],'placeholder'=>$f['placeholder'],'enabled'=>$f['enabled'],'required'=>$f['required']], $regDefaults), JSON_UNESCAPED_UNICODE) ?>);
                    document.querySelector('form').submit();
                };

                updateRegPreview();
            })();
            </script>

            <?php elseif ($tab === 'seo'): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-5">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-search mr-2 text-teal-500"></i>SEO & Meta Tags</h4>
                <p class="text-xs text-gray-500">These settings control how your site appears in Google, Facebook, and other platforms.</p>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Site Meta Title</label>
                        <input type="text" name="meta_title" value="<?= e($s['meta_title'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Your Site Name — Tagline">
                        <p class="text-xs text-gray-400 mt-1">Shows in Google search results & browser tab (50-60 chars recommended)</p></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Site Meta Description</label>
                        <textarea name="meta_description" rows="2" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Brief description of your business..."><?= e($s['meta_description'] ?? '') ?></textarea>
                        <p class="text-xs text-gray-400 mt-1">Shows below title in Google (150-160 chars recommended)</p></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Default Meta Keywords</label>
                    <input type="text" name="meta_keywords" value="<?= e($s['meta_keywords'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="keyword1, keyword2, keyword3">
                    <p class="text-xs text-gray-400 mt-1">Comma-separated keywords (less important for Google now, but used by some engines)</p></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fab fa-facebook mr-2 text-blue-500"></i>Open Graph (Social Sharing)</h4>
                <p class="text-xs text-gray-500">Controls how links look when shared on Facebook, WhatsApp, Messenger, etc.</p>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Default OG Image</label>
                    <input type="text" name="og_image" value="<?= e($s['og_image'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="https://khatibangla.com/uploads/og-image.jpg">
                    <p class="text-xs text-gray-400 mt-1">Used when sharing pages without their own image. Recommended: 1200×630px</p></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-check-circle mr-2 text-green-500"></i>Search Engine Verification</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Google Search Console</label>
                        <input type="text" name="google_site_verification" value="<?= e($s['google_site_verification'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Verification code from Google">
                        <p class="text-xs text-gray-400 mt-1">From <a href="https://search.google.com/search-console" target="_blank" class="text-blue-500 underline">Google Search Console</a> → Settings → Ownership verification → HTML tag</p></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Bing Webmaster</label>
                        <input type="text" name="bing_site_verification" value="<?= e($s['bing_site_verification'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="Verification code from Bing">
                        <p class="text-xs text-gray-400 mt-1">From <a href="https://www.bing.com/webmasters" target="_blank" class="text-blue-500 underline">Bing Webmaster Tools</a></p></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-robot mr-2 text-purple-500"></i>Robots & Sitemap</h4>
                <p class="text-xs text-gray-500">Your sitemap is auto-generated at <a href="<?= SITE_URL ?>/sitemap.xml" target="_blank" class="text-blue-500 underline"><?= SITE_URL ?>/sitemap.xml</a></p>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Custom Robots.txt</label>
                    <textarea name="robots_txt" rows="6" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="Leave empty for smart defaults"><?= e($s['robots_txt'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">Leave empty for auto-generated robots.txt. View at <a href="<?= SITE_URL ?>/robots.txt" target="_blank" class="text-blue-500 underline">/robots.txt</a></p></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-globe mr-2 text-indigo-500"></i>Social Media Profiles</h4>
                <p class="text-xs text-gray-500">Used in structured data (Schema.org) for Google Knowledge Panel</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-facebook text-blue-600 mr-1"></i>Facebook Page URL</label>
                        <input type="url" name="social_facebook" value="<?= e($s['social_facebook'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="https://facebook.com/yourpage"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-instagram text-pink-500 mr-1"></i>Instagram URL</label>
                        <input type="url" name="social_instagram" value="<?= e($s['social_instagram'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="https://instagram.com/yourpage"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-youtube text-red-600 mr-1"></i>YouTube Channel URL</label>
                        <input type="url" name="social_youtube" value="<?= e($s['social_youtube'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="https://youtube.com/@yourchannel"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-tiktok mr-1"></i>TikTok URL</label>
                        <input type="url" name="social_tiktok" value="<?= e($s['social_tiktok'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="https://tiktok.com/@yourpage"></div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-red-500"></i>Local Business Info (for Google)</h4>
                <p class="text-xs text-gray-500">Helps Google show your business in local search results</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                        <input type="text" name="site_address" value="<?= e($s['site_address'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="123 Main St, Dhaka, Bangladesh"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Business Email</label>
                        <input type="email" name="site_email" value="<?= e($s['site_email'] ?? '') ?>" class="w-full px-3 py-2.5 border rounded-lg text-sm" placeholder="info@khatibangla.com"></div>
                </div>
            </div>

            <?php elseif ($tab === 'advanced'): ?>
            <!-- Maintenance Mode -->
            <div class="bg-white rounded-xl shadow-sm border p-5 space-y-5">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-hard-hat mr-2 text-amber-500"></i>Maintenance Mode</h4>
                <p class="text-xs text-gray-500">ভিজিটররা মিনি-গেমসহ একটি মেইনটেন্যান্স পেজ দেখবে। অ্যাডমিনরা স্বাভাবিকভাবে সাইট দেখতে পারবেন।</p>
                
                <!-- Toggle -->
                <div class="flex items-center gap-3 p-3 rounded-lg <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'bg-amber-50 border border-amber-300' : 'bg-gray-50 border border-gray-200' ?>">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-amber-600 focus:ring-amber-500 w-5 h-5">
                        <span class="text-sm font-semibold <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'text-amber-700' : 'text-gray-700' ?>">
                            <?= ($s['maintenance_mode'] ?? '0') === '1' ? '🟡 সাইটটি মেইনটেন্যান্স মোডে আছে' : 'মেইনটেন্যান্স মোড চালু করুন' ?>
                        </span>
                    </label>
                </div>
                
                <?php if (($s['maintenance_mode'] ?? '0') === '1'): ?>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs text-amber-700"><strong>⚠️ সাইটটি এখন মেইনটেন্যান্স মোডে আছে।</strong> ভিজিটররা গেম পেজ দেখছে।</p>
                    <?php if (!empty($s['maintenance_bypass_key'])): ?>
                    <p class="text-xs text-amber-600 mt-1">শেয়ার লিংক: <code class="bg-white px-1 py-0.5 rounded text-xs select-all"><?= SITE_URL ?>?bypass=<?= e($s['maintenance_bypass_key']) ?></code></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Game Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">🎮 গেম সিলেক্ট করুন</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="maintenance_game" value="space" <?= ($s['maintenance_game'] ?? 'space') === 'space' ? 'checked' : '' ?> class="peer sr-only" onchange="updateMaintPreview()">
                            <div class="p-4 rounded-xl border-2 transition-all peer-checked:border-indigo-500 peer-checked:bg-indigo-50 border-gray-200 hover:border-gray-300 text-center">
                                <div class="text-3xl mb-1">🚀</div>
                                <div class="text-sm font-bold text-gray-800">স্পেস রানার</div>
                                <div class="text-[10px] text-gray-500 mt-1">ডার্ক থিম • মহাকাশ</div>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="maintenance_game" value="monkey" <?= ($s['maintenance_game'] ?? 'space') === 'monkey' ? 'checked' : '' ?> class="peer sr-only" onchange="updateMaintPreview()">
                            <div class="p-4 rounded-xl border-2 transition-all peer-checked:border-amber-500 peer-checked:bg-amber-50 border-gray-200 hover:border-gray-300 text-center">
                                <div class="text-3xl mb-1">🐒</div>
                                <div class="text-sm font-bold text-gray-800">বানানা জাম্প</div>
                                <div class="text-[10px] text-gray-500 mt-1">লাইট থিম • জঙ্গল</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Text Editor -->
                <div class="border-t pt-4 space-y-3">
                    <h5 class="text-sm font-semibold text-gray-700"><i class="fas fa-pen-fancy mr-1 text-gray-400"></i>টেক্সট কাস্টমাইজ</h5>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">শিরোনাম (ব্যাজ টেক্সট)</label>
                            <input type="text" name="maintenance_title" value="<?= e($s['maintenance_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="রক্ষণাবেক্ষণ চলছে" oninput="updateMaintPreview()">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">আনুমানিক সময়</label>
                            <input type="text" name="maintenance_eta" value="<?= e($s['maintenance_eta'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="যেমন: ৩০ মিনিট" oninput="updateMaintPreview()">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">মূল বার্তা</label>
                        <textarea name="maintenance_message" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="আমাদের সাইটটি আপডেট হচ্ছে। কিছুক্ষণের মধ্যেই ফিরে আসবে।" oninput="updateMaintPreview()"><?= e($s['maintenance_message'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Live Preview -->
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-semibold text-gray-700"><i class="fas fa-eye mr-1 text-gray-400"></i>প্রিভিউ</h5>
                        <a href="<?= SITE_URL ?>/maintenance-preview" target="_blank" class="text-xs text-blue-600 hover:underline"><i class="fas fa-external-link-alt mr-1"></i>ফুল প্রিভিউ</a>
                    </div>
                    <div id="maintPreview" class="rounded-xl overflow-hidden border" style="height:240px">
                        <div id="mpInner" style="transform:scale(0.5);transform-origin:top left;width:200%;height:200%">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                </div>

                <!-- Bypass Key -->
                <div class="border-t pt-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">🔑 বাইপাস কী (ঐচ্ছিক)</label>
                    <div class="flex gap-2">
                        <input type="text" name="maintenance_bypass_key" value="<?= e($s['maintenance_bypass_key'] ?? '') ?>" class="flex-1 px-3 py-2 border rounded-lg text-sm font-mono" placeholder="সিক্রেট কী" id="bypassKeyInput">
                        <button type="button" onclick="document.getElementById('bypassKeyInput').value=Math.random().toString(36).substr(2,10)" class="px-3 py-2 bg-gray-100 border rounded-lg text-xs font-medium hover:bg-gray-200 whitespace-nowrap">Generate</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><code class="bg-gray-100 px-1 rounded">?bypass=KEY</code> দিয়ে নির্দিষ্ট ব্যক্তিরা সাইট দেখতে পারবে</p>
                </div>
            </div>

            <script>
            function updateMaintPreview(){
                const game = document.querySelector('[name="maintenance_game"]:checked')?.value || 'space';
                const title = document.querySelector('[name="maintenance_title"]')?.value || 'রক্ষণাবেক্ষণ চলছে';
                const msg = document.querySelector('[name="maintenance_message"]')?.value || 'আমাদের সাইটটি আপডেট হচ্ছে। ততক্ষণ গেমটি উপভোগ করুন! 🎮';
                const eta = document.querySelector('[name="maintenance_eta"]')?.value || '';
                const isDark = game === 'space';
                const icon = isDark ? '🚀' : '🐒';
                const gameName = isDark ? 'স্পেস রানার' : 'বানানা জাম্প';
                const bg = isDark ? '#0b0f1a' : '#fef9ef';
                const txt = isDark ? '#e2e8f0' : '#3d2c1e';
                const sub = isDark ? '#94a3b8' : '#78716c';
                const badgeBg = isDark ? 'rgba(239,68,68,.15)' : 'rgba(245,158,11,.12)';
                const badgeBorder = isDark ? 'rgba(239,68,68,.3)' : 'rgba(245,158,11,.3)';
                const badgeColor = isDark ? '#fca5a5' : '#b45309';
                const boxBg = isDark ? 'rgba(255,255,255,.04)' : '#fff';
                const boxBorder = isDark ? 'rgba(255,255,255,.06)' : '#e7e5e4';
                const canvasBg = isDark ? '#080c16' : '#f0fdf4';

                document.getElementById('mpInner').innerHTML = `
                <div style="background:${bg};min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;font-family:Segoe UI,system-ui,sans-serif">
                    <div style="font-size:36px;font-weight:900;color:${txt};margin-bottom:8px"><?= htmlspecialchars($siteName) ?></div>
                    <div style="display:inline-block;padding:6px 18px;border-radius:20px;font-size:15px;font-weight:600;margin-bottom:16px;background:${badgeBg};border:1px solid ${badgeBorder};color:${badgeColor}">
                        ${isDark?'🔧':'🍌'} ${title}
                    </div>
                    <p style="font-size:17px;color:${sub};line-height:1.7;text-align:center;max-width:500px;margin-bottom:16px">${msg.replace(/\\n/g,'<br>')}</p>
                    ${eta ? '<div style="font-size:14px;color:'+sub+';margin-bottom:12px">🕐 আনুমানিক সময়: '+eta+'</div>' : ''}
                    <div style="background:${boxBg};border:1px solid ${boxBorder};border-radius:18px;padding:16px;width:100%;max-width:500px">
                        <div style="display:flex;justify-content:space-between;font-size:15px;color:${sub};margin-bottom:10px">
                            <span>${icon} স্কোর: <b style="color:${txt}">0</b></span>
                            <span>⚡ গতি: <b style="color:${txt}">1</b>x</span>
                            <span>🏆 সেরা: <b style="color:${txt}">0</b></span>
                        </div>
                        <div style="background:${canvasBg};border-radius:12px;height:120px;display:flex;align-items:center;justify-content:center;position:relative">
                            <div style="text-align:center">
                                <div style="font-size:60px;animation:none">${icon}</div>
                                <div style="font-size:17px;font-weight:700;color:${txt}">${gameName}</div>
                                <div style="font-size:13px;color:${sub};margin-top:4px">SPACE / TAP</div>
                            </div>
                        </div>
                    </div>
                    <div style="font-size:13px;color:${isDark?'#334155':'#d6d3d1'};margin-top:20px">শীঘ্রই ফিরে আসছি ❤️</div>
                </div>`;
            }
            document.addEventListener('DOMContentLoaded', updateMaintPreview);
            </script>

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
