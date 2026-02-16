<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();
$id = intval($_GET['id'] ?? 0);
$product = $id ? $db->fetch("SELECT * FROM products WHERE id = ?", [$id]) : null;
$pageTitle = $product ? 'Edit Product' : 'Add Product';

// ── AJAX: delete image ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_image') {
    $imgId = intval($_POST['image_id']);
    $img = $db->fetch("SELECT * FROM product_images WHERE id = ? AND product_id = ?", [$imgId, $id]);
    if ($img) {
        $path = UPLOAD_PATH . 'products/' . $img['image_path'];
        if (file_exists($path)) @unlink($path);
        $db->delete('product_images', 'id = ?', [$imgId]);
        if ($img['is_primary']) $db->query("UPDATE products SET featured_image = NULL WHERE id = ?", [$id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX: set primary ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_primary') {
    $imgId = intval($_POST['image_id']);
    $img = $db->fetch("SELECT * FROM product_images WHERE id = ? AND product_id = ?", [$imgId, $id]);
    if ($img) {
        $db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$id]);
        $db->query("UPDATE product_images SET is_primary = 1 WHERE id = ?", [$imgId]);
        $db->query("UPDATE products SET featured_image = ? WHERE id = ?", [$img['image_path'], $id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX: search products (for upsell/bundle picker) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'search_products') {
    header('Content-Type: application/json');
    $q = sanitize($_GET['q'] ?? '');
    $exclude = intval($_GET['exclude'] ?? 0);
    $results = $db->fetchAll(
        "SELECT id, name, name_bn, featured_image, regular_price, sale_price, stock_status 
         FROM products WHERE is_active = 1 AND id != ? AND (name LIKE ? OR name_bn LIKE ? OR sku LIKE ?) 
         ORDER BY name LIMIT 20",
        [$exclude, "%$q%", "%$q%", "%$q%"]
    );
    foreach ($results as &$r) {
        $r['image_url'] = $r['featured_image'] ? imgSrc('products', $r['featured_image']) : asset('img/default-product.svg');
        $r['display_price'] = $r['sale_price'] && $r['sale_price'] < $r['regular_price'] ? $r['sale_price'] : $r['regular_price'];
    }
    echo json_encode($results);
    exit;
}

// ── Main Save ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $slug = sanitize($_POST['slug']) ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['name'])));
    $existingSlug = $db->fetch("SELECT id FROM products WHERE slug = ? AND id != ?", [$slug, $id]);
    if ($existingSlug) $slug .= '-' . time();

    $data = [
        'name' => sanitize($_POST['name']),
        'name_bn' => sanitize($_POST['name_bn'] ?? ''),
        'slug' => $slug,
        'sku' => sanitize($_POST['sku'] ?? '') ?: generateProductSKU($id ?: null),
        'category_id' => intval($_POST['category_id']) ?: null,
        'short_description' => $_POST['short_description'] ?? '',
        'description' => $_POST['description'] ?? '',
        'regular_price' => floatval($_POST['regular_price']),
        'sale_price' => floatval($_POST['sale_price'] ?? 0) ?: null,
        'cost_price' => floatval($_POST['cost_price'] ?? 0) ?: null,
        'manage_stock' => isset($_POST['manage_stock']) ? 1 : 0,
        'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
        'low_stock_threshold' => intval($_POST['low_stock_threshold'] ?? 5),
        'stock_status' => $_POST['stock_status'] ?? 'in_stock',
        'weight' => floatval($_POST['weight'] ?? 0) ?: null,
        'tags' => sanitize($_POST['tags'] ?? ''),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_on_sale' => isset($_POST['is_on_sale']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'meta_title' => sanitize($_POST['meta_title'] ?? ''),
        'meta_description' => sanitize($_POST['meta_description'] ?? ''),
        'require_customer_upload' => isset($_POST['require_customer_upload']) ? 1 : 0,
        'customer_upload_label' => sanitize($_POST['customer_upload_label'] ?? ''),
        'customer_upload_required' => isset($_POST['customer_upload_required']) ? 1 : 0,
        'bundle_name' => sanitize($_POST['bundle_name'] ?? ''),
        'store_credit_enabled' => isset($_POST['store_credit_enabled']) ? 1 : 0,
        'store_credit_amount' => floatval($_POST['store_credit_amount'] ?? 0),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if (!empty($_FILES['featured_image']['name'])) {
        $upload = uploadFile($_FILES['featured_image'], 'products');
        if ($upload) $data['featured_image'] = basename($upload);
    }

    if ($id) { $db->update('products', $data, 'id = ?', [$id]); }
    else { $data['created_at'] = date('Y-m-d H:i:s'); $id = $db->insert('products', $data); }

    // Save featured to product_images
    if (!empty($data['featured_image']) && $id) {
        $imgPath = basename($data['featured_image']);
        $db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$id]);
        $db->insert('product_images', ['product_id' => $id, 'image_path' => $imgPath, 'is_primary' => 1, 'sort_order' => 0]);
    }

    // Gallery upload
    if (!empty($_FILES['gallery_images']['name'][0])) {
        $maxSort = $db->fetch("SELECT COALESCE(MAX(sort_order),0) as mx FROM product_images WHERE product_id = ?", [$id])['mx'];
        foreach ($_FILES['gallery_images']['name'] as $key => $name) {
            if (!$name) continue;
            $file = ['name'=>$name, 'type'=>$_FILES['gallery_images']['type'][$key], 'tmp_name'=>$_FILES['gallery_images']['tmp_name'][$key], 'error'=>$_FILES['gallery_images']['error'][$key], 'size'=>$_FILES['gallery_images']['size'][$key]];
            $path = uploadFile($file, 'products');
            if ($path) {
                $path = basename($path);
                $maxSort++;
                $hasPrimary = $db->fetch("SELECT COUNT(*) as c FROM product_images WHERE product_id=? AND is_primary=1", [$id])['c'];
                $db->insert('product_images', ['product_id'=>$id, 'image_path'=>$path, 'is_primary'=>$hasPrimary?0:1, 'sort_order'=>$maxSort]);
                if (!$hasPrimary) $db->query("UPDATE products SET featured_image=? WHERE id=?", [$path, $id]);
            }
        }
    }

    // Gallery from media library
    if (!empty($_POST['gallery_from_media'])) {
        $mediaPaths = array_filter(explode(',', $_POST['gallery_from_media']));
        $maxSort = $db->fetch("SELECT COALESCE(MAX(sort_order),0) as mx FROM product_images WHERE product_id = ?", [$id])['mx'];
        foreach ($mediaPaths as $mpath) {
            $mpath = trim($mpath);
            if (!$mpath) continue;
            // Normalize: remove "products/" prefix if present since we store relative to products/
            $imgPath = preg_replace('#^products/#', '', $mpath);
            $exists = $db->fetch("SELECT id FROM product_images WHERE product_id=? AND image_path=?", [$id, $imgPath]);
            if ($exists) continue;
            $maxSort++;
            $hasPrimary = $db->fetch("SELECT COUNT(*) as c FROM product_images WHERE product_id=? AND is_primary=1", [$id])['c'];
            $db->insert('product_images', ['product_id'=>$id, 'image_path'=>$imgPath, 'is_primary'=>$hasPrimary?0:1, 'sort_order'=>$maxSort]);
            if (!$hasPrimary) $db->query("UPDATE products SET featured_image=? WHERE id=?", [$imgPath, $id]);
        }
    }

    // ── Save Addons & Variations ──
    if (isset($_POST['opt_name'])) {
        $db->delete('product_variants', 'product_id = ?', [$id]);
        $productSku = $data['sku'] ?? generateProductSKU($id);
        $defaultIdx = intval($_POST['opt_default'] ?? -1);
        foreach ($_POST['opt_name'] as $vi => $vname) {
            if (empty($vname) || empty($_POST['opt_value'][$vi])) continue;
            $optType = $_POST['opt_type'][$vi] ?? 'addon';
            $varSku = sanitize($_POST['opt_sku'][$vi] ?? '');
            if (!$varSku) $varSku = generateVariantSKU($productSku, $_POST['opt_value'][$vi]);
            $db->insert('product_variants', [
                'product_id' => $id,
                'variant_name' => sanitize($vname),
                'variant_value' => sanitize($_POST['opt_value'][$vi]),
                'option_type' => $optType,
                'price_adjustment' => $optType === 'addon' ? floatval($_POST['opt_price'][$vi] ?? 0) : 0,
                'absolute_price' => $optType === 'variation' ? floatval($_POST['opt_abs_price'][$vi] ?? 0) : null,
                'stock_quantity' => intval($_POST['opt_stock'][$vi] ?? 0),
                'sku' => $varSku,
                'is_active' => 1,
                'is_default' => ($vi == $defaultIdx) ? 1 : 0,
            ]);
        }
    }

    // ── Save Upsells ──
    try {
        $db->delete('product_upsells', 'product_id = ?', [$id]);
        if (!empty($_POST['upsell_ids'])) {
            $upsellIds = array_filter(array_map('intval', explode(',', $_POST['upsell_ids'])));
            foreach ($upsellIds as $si => $uid) {
                if ($uid && $uid != $id) {
                    $db->insert('product_upsells', ['product_id' => $id, 'upsell_product_id' => $uid, 'sort_order' => $si]);
                }
            }
        }
    } catch (\Throwable $e) {}

    // ── Save Bundles ──
    try {
        $db->delete('product_bundles', 'product_id = ?', [$id]);
        if (isset($_POST['bundle_product_id'])) {
            foreach ($_POST['bundle_product_id'] as $bi => $bpid) {
                $bpid = intval($bpid);
                if (!$bpid || $bpid == $id) continue;
                $db->insert('product_bundles', [
                    'product_id' => $id,
                    'bundle_product_id' => $bpid,
                    'bundle_qty' => intval($_POST['bundle_qty'][$bi] ?? 1),
                    'discount_type' => $_POST['bundle_discount_type'][$bi] ?? 'fixed',
                    'discount_value' => floatval($_POST['bundle_discount_value'][$bi] ?? 0),
                    'sort_order' => $bi,
                    'is_active' => 1,
                ]);
            }
        }
    } catch (\Throwable $e) {}

    redirect(adminUrl('pages/products.php?msg=saved'));
}

// ── Load Data ──
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$variants = $id ? $db->fetchAll("SELECT * FROM product_variants WHERE product_id = ? ORDER BY option_type, id", [$id]) : [];
$galleryImages = $id ? $db->fetchAll("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC", [$id]) : [];

// Load upsells
$upsells = [];
try {
    if ($id) {
        $upsells = $db->fetchAll(
            "SELECT p.id, p.name, p.name_bn, p.featured_image, p.regular_price, p.sale_price 
             FROM product_upsells pu JOIN products p ON pu.upsell_product_id = p.id 
             WHERE pu.product_id = ? ORDER BY pu.sort_order", [$id]
        );
    }
} catch (\Throwable $e) {}

// Load bundles
$bundles = [];
try {
    if ($id) {
        $bundles = $db->fetchAll(
            "SELECT pb.*, p.name, p.name_bn, p.featured_image, p.regular_price, p.sale_price 
             FROM product_bundles pb JOIN products p ON pb.bundle_product_id = p.id 
             WHERE pb.product_id = ? ORDER BY pb.sort_order", [$id]
        );
    }
} catch (\Throwable $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= adminUrl('pages/products.php') ?>" class="p-2 rounded-lg hover:bg-gray-100"><i class="fas fa-arrow-left text-gray-500"></i></a>
    <h3 class="text-xl font-bold text-gray-800"><?= $pageTitle ?></h3>
    <?php if ($product): ?><a href="<?= url('product/' . $product['slug']) ?>" target="_blank" class="text-blue-500 text-sm hover:underline ml-2">↗ View</a><?php endif; ?>
</div>

<form method="POST" enctype="multipart/form-data" id="productForm">
<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Basic Info -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">📝 Basic Information</h4>
            <div class="grid md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Product Name *</label><input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Name (বাংলা)</label><input type="text" name="name_bn" value="<?= e($product['name_bn'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">URL Slug</label><input type="text" name="slug" value="<?= e($product['slug'] ?? '') ?>" placeholder="auto-generated" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">SKU</label><input type="text" name="sku" value="<?= e($product['sku'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tags (comma sep)</label><input type="text" name="tags" value="<?= e($product['tags'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label><textarea name="short_description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?= e($product['short_description'] ?? '') ?></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label><textarea name="description" rows="8" class="w-full px-3 py-2 border rounded-lg text-sm"><?= e($product['description'] ?? '') ?></textarea></div>
        </div>

        <!-- Pricing -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">💰 Pricing</h4>
            <div class="grid md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Regular Price (৳) *</label><input type="number" name="regular_price" value="<?= $product['regular_price'] ?? '' ?>" required step="0.01" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Sale Price (৳)</label><input type="number" name="sale_price" value="<?= $product['sale_price'] ?? '' ?>" step="0.01" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Cost Price (৳)</label><input type="number" name="cost_price" value="<?= $product['cost_price'] ?? '' ?>" step="0.01" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
        </div>

        <!-- Inventory -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">📦 Inventory</h4>
            <label class="flex items-center gap-2"><input type="checkbox" name="manage_stock" value="1" <?= ($product['manage_stock'] ?? 1) ? 'checked' : '' ?> class="rounded"><span class="text-sm">Track stock</span></label>
            <div class="grid md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Stock Qty</label><input type="number" name="stock_quantity" value="<?= $product['stock_quantity'] ?? 0 ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Low Stock Alert</label><input type="number" name="low_stock_threshold" value="<?= $product['low_stock_threshold'] ?? 5 ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Stock Status</label>
                    <select name="stock_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="in_stock" <?= ($product['stock_status']??'')=='in_stock'?'selected':'' ?>>In Stock</option>
                        <option value="out_of_stock" <?= ($product['stock_status']??'')=='out_of_stock'?'selected':'' ?>>Out of Stock</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Weight (g)</label><input type="number" name="weight" value="<?= $product['weight'] ?? '' ?>" step="0.01" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
        </div>

        <!-- ═══════ ADDONS & VARIATIONS ═══════ -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold text-gray-800">🎨 Addons & Variations</h4>
                <button type="button" onclick="addOption()" class="bg-purple-50 text-purple-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-purple-100">+ Add Option</button>
            </div>
            <p class="text-xs text-gray-400 mb-4">
                <strong>Addon</strong> = adds cost to base price (e.g. gift wrap +৳50) &nbsp;|&nbsp; 
                <strong>Variation</strong> = replaces base price entirely (e.g. Size L = ৳899)
            </p>
            <div id="optionsContainer" class="space-y-2">
                <?php foreach ($variants as $v): ?>
                <div class="option-row bg-gray-50 rounded-lg p-3">
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-2">
                            <label class="text-xs text-gray-500">Type</label>
                            <select name="opt_type[]" class="w-full px-2 py-1.5 border rounded text-xs opt-type-select" onchange="toggleOptionFields(this)">
                                <option value="addon" <?= ($v['option_type'] ?? 'addon') === 'addon' ? 'selected' : '' ?>>Addon</option>
                                <option value="variation" <?= ($v['option_type'] ?? '') === 'variation' ? 'selected' : '' ?>>Variation</option>
                            </select>
                        </div>
                        <div class="col-span-2"><label class="text-xs text-gray-500">Name</label><input type="text" name="opt_name[]" value="<?= e($v['variant_name']) ?>" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Color, Size..."></div>
                        <div class="col-span-2"><label class="text-xs text-gray-500">Value</label><input type="text" name="opt_value[]" value="<?= e($v['variant_value']) ?>" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Red, XL..."></div>
                        <div class="col-span-2 opt-addon-price <?= ($v['option_type'] ?? 'addon') !== 'addon' ? 'hidden' : '' ?>"><label class="text-xs text-gray-500">Price ±</label><input type="number" name="opt_price[]" value="<?= $v['price_adjustment'] ?>" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                        <div class="col-span-2 opt-var-price <?= ($v['option_type'] ?? 'addon') !== 'variation' ? 'hidden' : '' ?>"><label class="text-xs text-gray-500">Sell Price ৳</label><input type="number" name="opt_abs_price[]" value="<?= $v['absolute_price'] ?>" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                        <div class="col-span-1"><label class="text-xs text-gray-500">Stock</label><input type="number" name="opt_stock[]" value="<?= $v['stock_quantity'] ?>" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                        <div class="col-span-1"><label class="text-xs text-gray-500">SKU</label><input type="text" name="opt_sku[]" value="<?= e($v['sku'] ?? '') ?>" class="w-full px-2 py-1.5 border rounded text-sm"></div>
                        <div class="col-span-1 text-center pt-4">
                            <label class="text-xs text-gray-400 block mb-0.5">Default</label>
                            <input type="radio" name="opt_default" value="<?= $loop = $loop ?? 0; echo $loop++; ?>" <?= ($v['is_default'] ?? 0) ? 'checked' : '' ?> class="accent-blue-600">
                        </div>
                        <div class="col-span-1 text-center pt-5"><button type="button" onclick="this.closest('.option-row').remove()" class="text-red-400 hover:text-red-600 text-lg">✕</button></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($variants)): ?><p id="noOptMsg" class="text-sm text-gray-400 text-center py-4">No addons or variations yet.</p><?php endif; ?>
        </div>

        <!-- ═══════ UPSELL PRODUCTS ═══════ -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-1">🔥 Upsell Products</h4>
            <p class="text-xs text-gray-400 mb-4">Shown to customers during checkout as "You may also like"</p>
            <input type="hidden" name="upsell_ids" id="upsellIds" value="<?= implode(',', array_column($upsells, 'id')) ?>">
            <div class="relative mb-3">
                <input type="text" id="upsellSearch" placeholder="Search products to add..." class="w-full px-3 py-2 border rounded-lg text-sm pl-9" oninput="searchProducts(this.value, 'upsell')">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                <div id="upsellDropdown" class="hidden absolute z-10 w-full bg-white border rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto"></div>
            </div>
            <div id="upsellList" class="flex flex-wrap gap-2">
                <?php foreach ($upsells as $u): ?>
                <div class="upsell-tag flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5" data-id="<?= $u['id'] ?>">
                    <img src="<?= $u['featured_image'] ? imgSrc('products', $u['featured_image']) : asset('img/default-product.svg') ?>" class="w-8 h-8 rounded object-cover">
                    <span class="text-xs font-medium"><?= e($u['name_bn'] ?: $u['name']) ?></span>
                    <button type="button" onclick="removeUpsell(this)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ═══════ BUNDLE PRODUCTS ═══════ -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between mb-1">
                <h4 class="font-semibold text-gray-800">📦 Bundle Deal</h4>
                <button type="button" onclick="showBundleSearch()" class="bg-green-50 text-green-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-green-100">+ Add Product</button>
            </div>
            <p class="text-xs text-gray-400 mb-3">Create "Buy Together & Save" bundle deals</p>
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Bundle Name <span class="text-gray-400">(shown in cart & checkout)</span></label>
                <input type="text" name="bundle_name" value="<?= e($product['bundle_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. চিয়া সিড কম্বো প্যাক">
            </div>
            <div class="relative mb-3 hidden" id="bundleSearchWrap">
                <input type="text" id="bundleSearch" placeholder="Search products to bundle..." class="w-full px-3 py-2 border rounded-lg text-sm pl-9" oninput="searchProducts(this.value, 'bundle')">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                <div id="bundleDropdown" class="hidden absolute z-10 w-full bg-white border rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto"></div>
            </div>
            <div id="bundleContainer" class="space-y-2">
                <?php foreach ($bundles as $b):
                    $bSelling = ($b['sale_price'] && $b['sale_price'] > 0 && $b['sale_price'] < $b['regular_price']) 
                        ? floatval($b['sale_price']) : floatval($b['regular_price']);
                ?>
                <div class="bundle-row bg-green-50 rounded-lg p-3" data-price="<?= $bSelling ?>">
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <input type="hidden" name="bundle_product_id[]" value="<?= $b['bundle_product_id'] ?>">
                        <div class="col-span-4 flex items-center gap-2">
                            <img src="<?= $b['featured_image'] ? imgSrc('products', $b['featured_image']) : asset('img/default-product.svg') ?>" class="w-10 h-10 rounded object-cover">
                            <div class="min-w-0">
                                <span class="text-sm font-medium truncate block"><?= e($b['name_bn'] ?: $b['name']) ?></span>
                                <span class="text-[10px] text-gray-400">Current: ৳<?= number_format($bSelling) ?></span>
                            </div>
                        </div>
                        <div class="col-span-2"><label class="text-xs text-gray-500">Qty</label><input type="number" name="bundle_qty[]" value="<?= $b['bundle_qty'] ?>" min="1" class="w-full px-2 py-1.5 border rounded text-sm bd-calc" oninput="calcBundleRow(this)"></div>
                        <div class="col-span-2"><label class="text-xs text-gray-500">Discount Type</label>
                            <select name="bundle_discount_type[]" class="w-full px-2 py-1.5 border rounded text-xs bd-calc" onchange="calcBundleRow(this)">
                                <option value="fixed" <?= $b['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed ৳</option>
                                <option value="percentage" <?= $b['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percent %</option>
                            </select></div>
                        <div class="col-span-3"><label class="text-xs text-gray-500">Discount</label><input type="number" name="bundle_discount_value[]" value="<?= $b['discount_value'] ?>" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm bd-calc" oninput="calcBundleRow(this)"></div>
                        <div class="col-span-1 text-center"><button type="button" onclick="this.closest('.bundle-row').remove();calcBundleSummary()" class="text-red-400 hover:text-red-600">✕</button></div>
                    </div>
                    <div class="bd-preview mt-2 pt-2 border-t border-green-200 flex items-center justify-between text-xs"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($bundles)): ?><p id="noBundleMsg" class="text-sm text-gray-400 text-center py-4">No bundle products.</p><?php endif; ?>
            <div id="bundleSummary" class="hidden mt-3 p-3 bg-green-100 rounded-lg text-sm"></div>
        </div>

        <!-- Images -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">🖼️ Product Images</h4>
            <?php if (!empty($galleryImages)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Images <span class="text-xs text-gray-400">(hover for actions, ★ = featured)</span></label>
                <div class="flex gap-3 flex-wrap" id="currentImages">
                    <?php foreach ($galleryImages as $gi): ?>
                    <div class="relative group" id="img-<?= $gi['id'] ?>">
                        <img src="<?= imgSrc('products', $gi['image_path']) ?>" class="w-24 h-24 object-cover rounded-lg border-2 <?= $gi['is_primary'] ? 'border-blue-500' : 'border-gray-200' ?>" onerror="this.src='<?= asset('img/default-product.svg') ?>'">
                        <?php if ($gi['is_primary']): ?><span class="absolute -top-1 -left-1 bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded-full">★</span><?php endif; ?>
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center gap-1">
                            <button type="button" onclick="setPrimary(<?= $gi['id'] ?>)" class="p-1 bg-white rounded text-yellow-500 hover:text-yellow-600" title="Set featured">★</button>
                            <button type="button" onclick="delImg(<?= $gi['id'] ?>)" class="p-1 bg-white rounded text-red-500 hover:text-red-600" title="Delete">✕</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="flex gap-3">
                <div class="flex-1">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition cursor-pointer" onclick="document.getElementById('galleryInput').click()">
                        <p class="text-gray-400 text-sm">📷 Click to upload images</p>
                    </div>
                    <input type="file" name="gallery_images[]" accept="image/*" multiple class="hidden" id="galleryInput" onchange="previewUp(this)">
                </div>
                <button type="button" onclick="openMediaLibrary(onMediaFilesSelected, {multiple:true, folder:'products', uploadFolder:'products'})" 
                        class="px-4 py-2 border-2 border-dashed border-blue-300 rounded-lg text-sm text-blue-600 hover:bg-blue-50 hover:border-blue-400 transition font-medium self-stretch flex items-center gap-2">
                    <i class="fas fa-photo-video"></i> Media Library
                </button>
            </div>
            <input type="hidden" name="gallery_from_media" id="galleryFromMedia" value="">
            <div id="uploadPreviews" class="flex gap-2 flex-wrap"></div>
        </div>



        <!-- SEO -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">🔍 SEO</h4>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label><input type="text" name="meta_title" value="<?= e($product['meta_title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label><textarea name="meta_description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"><?= e($product['meta_description'] ?? '') ?></textarea></div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h4 class="font-semibold text-gray-800">Publish</h4>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?> class="rounded text-blue-600"><span class="text-sm">Active</span></label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?> class="rounded text-purple-600"><span class="text-sm">Featured</span></label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_on_sale" value="1" <?= ($product['is_on_sale'] ?? 0) ? 'checked' : '' ?> class="rounded text-green-600"><span class="text-sm">On Sale</span></label>
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700">✓ <?= $product ? 'Update' : 'Create' ?> Product</button>
        </div>

        <!-- Customer Upload Setting -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fas fa-cloud-upload-alt text-purple-500"></i>Customer Upload</h4>
            <p class="text-xs text-gray-400">Allow customers to upload an image/document (e.g. prescription, face photo) when ordering this product.</p>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="require_customer_upload" value="1" <?= ($product['require_customer_upload'] ?? 0) ? 'checked' : '' ?> class="rounded text-purple-600" id="custUploadToggle" onchange="document.getElementById('custUploadOptions').classList.toggle('hidden', !this.checked)">
                <span class="text-sm font-medium">Enable Customer Upload</span>
            </label>
            <div id="custUploadOptions" class="space-y-3 <?= ($product['require_customer_upload'] ?? 0) ? '' : 'hidden' ?>">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Upload Label (shown to customer)</label>
                    <input type="text" name="customer_upload_label" value="<?= e($product['customer_upload_label'] ?? 'আপনার ছবি/ডকুমেন্ট আপলোড করুন') ?>" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Upload your prescription">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="customer_upload_required" value="1" <?= ($product['customer_upload_required'] ?? 0) ? 'checked' : '' ?> class="rounded text-red-500">
                    <span class="text-xs text-gray-600">Make upload mandatory (cannot order without it)</span>
                </div>
            </div>
        </div>
        <!-- Store Credit Setting -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-3">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="fas fa-coins text-yellow-500"></i>Store Credit</h4>
            <p class="text-xs text-gray-400">Award store credits to registered customers when this product is delivered.</p>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="store_credit_enabled" value="1" <?= ($product['store_credit_enabled'] ?? 0) ? 'checked' : '' ?> class="rounded text-yellow-600" id="storeCreditToggle" onchange="document.getElementById('storeCreditOptions').classList.toggle('hidden', !this.checked)">
                <span class="text-sm font-medium">Enable Store Credit</span>
            </label>
            <div id="storeCreditOptions" class="<?= ($product['store_credit_enabled'] ?? 0) ? '' : 'hidden' ?>">
                <label class="block text-xs font-medium text-gray-600 mb-1">Credit Amount (৳) per unit</label>
                <input type="number" name="store_credit_amount" value="<?= e($product['store_credit_amount'] ?? '0') ?>" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="e.g. 50" step="0.01" min="0">
                <p class="text-xs text-gray-400 mt-1">Credited to customer's account after delivery</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-3">Category</h4>
            <select name="category_id" id="categorySelect" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php if ($product): ?>
        <div class="bg-gray-50 rounded-xl border p-5 text-xs text-gray-500 space-y-1">
            <p>Created: <?= date('M d, Y', strtotime($product['created_at'])) ?></p>
            <p>Views: <?= number_format($product['views']) ?> · Sales: <?= number_format($product['sales_count']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
</form>

<?php include __DIR__ . '/../includes/media-picker.php'; ?>

<script>
const SITE_URL='<?= SITE_URL ?>', ADMIN_URL='<?= ADMIN_URL ?>';
const PRODUCT_ID = <?= $id ?: 0 ?>;

// ── Option (Addon/Variation) Management ──
function addOption(type) {
    const m = document.getElementById('noOptMsg'); if (m) m.remove();
    const idx = document.querySelectorAll('.option-row').length;
    const html = `<div class="option-row bg-gray-50 rounded-lg p-3">
        <div class="grid grid-cols-12 gap-2 items-end">
            <div class="col-span-2"><label class="text-xs text-gray-500">Type</label>
                <select name="opt_type[]" class="w-full px-2 py-1.5 border rounded text-xs opt-type-select" onchange="toggleOptionFields(this)">
                    <option value="addon">Addon</option><option value="variation">Variation</option>
                </select></div>
            <div class="col-span-2"><label class="text-xs text-gray-500">Name</label><input type="text" name="opt_name[]" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Color, Size..."></div>
            <div class="col-span-2"><label class="text-xs text-gray-500">Value</label><input type="text" name="opt_value[]" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Red, XL..."></div>
            <div class="col-span-2 opt-addon-price"><label class="text-xs text-gray-500">Price ±</label><input type="number" name="opt_price[]" value="0" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm"></div>
            <div class="col-span-2 opt-var-price hidden"><label class="text-xs text-gray-500">Sell Price ৳</label><input type="number" name="opt_abs_price[]" value="0" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm"></div>
            <div class="col-span-1"><label class="text-xs text-gray-500">Stock</label><input type="number" name="opt_stock[]" value="0" class="w-full px-2 py-1.5 border rounded text-sm"></div>
            <div class="col-span-1"><label class="text-xs text-gray-500">SKU</label><input type="text" name="opt_sku[]" class="w-full px-2 py-1.5 border rounded text-sm" placeholder="Auto"></div>
            <div class="col-span-1 text-center pt-4"><label class="text-xs text-gray-400 block mb-0.5">Default</label><input type="radio" name="opt_default" value="${idx}" class="accent-blue-600"></div>
            <div class="col-span-1 text-center pt-5"><button type="button" onclick="this.closest('.option-row').remove()" class="text-red-400 hover:text-red-600 text-lg">✕</button></div>
        </div>
    </div>`;
    document.getElementById('optionsContainer').insertAdjacentHTML('beforeend', html);
}

function toggleOptionFields(sel) {
    const row = sel.closest('.option-row');
    const isVar = sel.value === 'variation';
    row.querySelector('.opt-addon-price').classList.toggle('hidden', isVar);
    row.querySelector('.opt-var-price').classList.toggle('hidden', !isVar);
}

// ── Product Search (Upsell & Bundle) ──
let searchTimer = null;
function searchProducts(q, target) {
    clearTimeout(searchTimer);
    if (q.length < 2) { document.getElementById(target + 'Dropdown').classList.add('hidden'); return; }
    searchTimer = setTimeout(() => {
        fetch(`${ADMIN_URL}/pages/product-form.php?ajax=search_products&q=${encodeURIComponent(q)}&exclude=${PRODUCT_ID}`)
        .then(r => r.json())
        .then(data => {
            const dd = document.getElementById(target + 'Dropdown');
            if (!data.length) { dd.innerHTML = '<div class="p-3 text-sm text-gray-400">No products found</div>'; dd.classList.remove('hidden'); return; }
            let html = '';
            data.forEach(p => {
                html += `<div class="flex items-center gap-3 p-2 hover:bg-gray-50 cursor-pointer" onclick="${target === 'upsell' ? `addUpsell(${p.id}, '${p.name.replace(/'/g,"\\'")}', '${p.image_url}')` : `addBundle(${p.id}, '${p.name.replace(/'/g,"\\'")}', '${p.image_url}', ${p.display_price})`}">
                    <img src="${p.image_url}" class="w-8 h-8 rounded object-cover">
                    <div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">${p.name_bn || p.name}</p><p class="text-xs text-gray-400">৳${Number(p.display_price).toLocaleString()}</p></div>
                    <i class="fas fa-plus text-blue-500 text-xs"></i>
                </div>`;
            });
            dd.innerHTML = html;
            dd.classList.remove('hidden');
        });
    }, 300);
}

// ── Upsells ──
function addUpsell(id, name, img) {
    const ids = document.getElementById('upsellIds').value.split(',').filter(Boolean).map(Number);
    if (ids.includes(id)) return;
    ids.push(id);
    document.getElementById('upsellIds').value = ids.join(',');
    document.getElementById('upsellList').insertAdjacentHTML('beforeend',
        `<div class="upsell-tag flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5" data-id="${id}">
            <img src="${img}" class="w-8 h-8 rounded object-cover"><span class="text-xs font-medium">${name}</span>
            <button type="button" onclick="removeUpsell(this)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
        </div>`);
    document.getElementById('upsellSearch').value = '';
    document.getElementById('upsellDropdown').classList.add('hidden');
}

function removeUpsell(btn) {
    const tag = btn.closest('.upsell-tag');
    const id = parseInt(tag.dataset.id);
    const ids = document.getElementById('upsellIds').value.split(',').filter(Boolean).map(Number).filter(x => x !== id);
    document.getElementById('upsellIds').value = ids.join(',');
    tag.remove();
}

// ── Bundles ──
function showBundleSearch() { document.getElementById('bundleSearchWrap').classList.toggle('hidden'); document.getElementById('bundleSearch').focus(); }

function addBundle(id, name, img, price) {
    const m = document.getElementById('noBundleMsg'); if (m) m.remove();
    document.getElementById('bundleContainer').insertAdjacentHTML('beforeend',
        `<div class="bundle-row bg-green-50 rounded-lg p-3" data-price="${price}">
            <div class="grid grid-cols-12 gap-2 items-center">
                <input type="hidden" name="bundle_product_id[]" value="${id}">
                <div class="col-span-4 flex items-center gap-2"><img src="${img}" class="w-10 h-10 rounded object-cover"><div class="min-w-0"><span class="text-sm font-medium truncate block">${name}</span><span class="text-[10px] text-gray-400">Current: ৳${Number(price).toLocaleString()}</span></div></div>
                <div class="col-span-2"><label class="text-xs text-gray-500">Qty</label><input type="number" name="bundle_qty[]" value="1" min="1" class="w-full px-2 py-1.5 border rounded text-sm bd-calc" oninput="calcBundleRow(this)"></div>
                <div class="col-span-2"><label class="text-xs text-gray-500">Discount Type</label>
                    <select name="bundle_discount_type[]" class="w-full px-2 py-1.5 border rounded text-xs bd-calc" onchange="calcBundleRow(this)"><option value="fixed">Fixed ৳</option><option value="percentage">Percent %</option></select></div>
                <div class="col-span-3"><label class="text-xs text-gray-500">Discount</label><input type="number" name="bundle_discount_value[]" value="0" step="0.01" class="w-full px-2 py-1.5 border rounded text-sm bd-calc" oninput="calcBundleRow(this)"></div>
                <div class="col-span-1 text-center"><button type="button" onclick="this.closest('.bundle-row').remove();calcBundleSummary()" class="text-red-400 hover:text-red-600">✕</button></div>
            </div>
            <div class="bd-preview mt-2 pt-2 border-t border-green-200 flex items-center justify-between text-xs"></div>
        </div>`);
    document.getElementById('bundleSearch').value = '';
    document.getElementById('bundleDropdown').classList.add('hidden');
    calcBundleSummary();
}

// ── Bundle Live Calculation ──
const MAIN_PRODUCT_PRICE = <?= json_encode(floatval($product['sale_price'] ?? 0) > 0 && floatval($product['sale_price'] ?? 0) < floatval($product['regular_price'] ?? 0) ? floatval($product['sale_price']) : floatval($product['regular_price'] ?? 0)) ?>;

function calcBundleRow(el) {
    const row = el.closest('.bundle-row');
    if (!row) return;
    const unitPrice = parseFloat(row.dataset.price) || 0;
    const qty = parseInt(row.querySelector('[name="bundle_qty[]"]').value) || 1;
    const discType = row.querySelector('[name="bundle_discount_type[]"]').value;
    const discVal = parseFloat(row.querySelector('[name="bundle_discount_value[]"]').value) || 0;
    
    let discount = 0;
    if (discType === 'percentage') {
        discount = (unitPrice * discVal) / 100;
    } else {
        discount = Math.min(discVal, unitPrice);
    }
    const finalUnit = Math.max(0, unitPrice - discount);
    const finalTotal = finalUnit * qty;
    const saved = discount * qty;
    
    const preview = row.querySelector('.bd-preview');
    if (preview) {
        if (discount > 0) {
            preview.innerHTML = `<span class="text-gray-500"><s>৳${unitPrice.toLocaleString()}</s> → <span class="text-green-700 font-bold">৳${Math.round(finalUnit).toLocaleString()}</span>/unit</span>
                <span class="font-bold text-green-700">Total: ৳${Math.round(finalTotal).toLocaleString()} <span class="text-red-500 text-[10px] ml-1">Save ৳${Math.round(saved).toLocaleString()}</span></span>`;
        } else {
            preview.innerHTML = `<span class="text-gray-500">৳${unitPrice.toLocaleString()}/unit</span><span class="text-gray-600 font-medium">Total: ৳${Math.round(finalTotal).toLocaleString()}</span>`;
        }
    }
    calcBundleSummary();
}

function calcBundleSummary() {
    const rows = document.querySelectorAll('.bundle-row');
    const summary = document.getElementById('bundleSummary');
    if (!rows.length) { summary?.classList.add('hidden'); return; }
    
    let separateTotal = MAIN_PRODUCT_PRICE;
    let bundleTotal = MAIN_PRODUCT_PRICE;
    
    rows.forEach(row => {
        const unitPrice = parseFloat(row.dataset.price) || 0;
        const qty = parseInt(row.querySelector('[name="bundle_qty[]"]')?.value) || 1;
        const discType = row.querySelector('[name="bundle_discount_type[]"]')?.value || 'fixed';
        const discVal = parseFloat(row.querySelector('[name="bundle_discount_value[]"]')?.value) || 0;
        
        let discount = 0;
        if (discType === 'percentage') {
            discount = (unitPrice * discVal) / 100;
        } else {
            discount = Math.min(discVal, unitPrice);
        }
        const finalUnit = Math.max(0, unitPrice - discount);
        
        separateTotal += unitPrice * qty;
        bundleTotal += finalUnit * qty;
    });
    
    const saved = separateTotal - bundleTotal;
    const pct = separateTotal > 0 ? ((saved / separateTotal) * 100) : 0;
    
    if (summary) {
        summary.classList.remove('hidden');
        summary.innerHTML = `<div class="flex items-center justify-between">
            <div>
                <span class="text-gray-600">Main product: ৳${Math.round(MAIN_PRODUCT_PRICE).toLocaleString()}</span>
                <span class="mx-2">+</span>
                <span class="text-gray-600">Bundle items: ৳${Math.round(bundleTotal - MAIN_PRODUCT_PRICE).toLocaleString()}</span>
            </div>
            <div class="text-right">
                ${saved > 0 ? `<span class="line-through text-gray-400 mr-2">৳${Math.round(separateTotal).toLocaleString()}</span>` : ''}
                <span class="font-bold text-green-800 text-base">৳${Math.round(bundleTotal).toLocaleString()}</span>
                ${saved > 0 ? `<span class="ml-2 px-1.5 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded">${pct >= 1 ? Math.round(pct) : pct.toFixed(1)}% OFF · Save ৳${Math.round(saved).toLocaleString()}</span>` : ''}
            </div>
        </div>`;
    }
}

// Init all existing bundle rows
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bundle-row').forEach(row => {
        const el = row.querySelector('.bd-calc');
        if (el) calcBundleRow(el);
    });
});

// ── Image Helpers ──
function delImg(id) { if(!confirm('Delete?'))return; fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete_image&image_id=${id}`}).then(r=>r.json()).then(d=>{if(d.success)document.getElementById('img-'+id).remove();}); }
function setPrimary(id) { fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=set_primary&image_id=${id}`}).then(r=>r.json()).then(d=>{if(d.success)location.reload();}); }
function previewUp(inp) { const c=document.getElementById('uploadPreviews'); Array.from(inp.files).forEach(f=>{const r=new FileReader();r.onload=e=>{c.insertAdjacentHTML('beforeend',`<div class="w-20 h-20 rounded-lg overflow-hidden border-2 border-blue-300"><img src="${e.target.result}" class="w-full h-full object-cover"></div>`);};r.readAsDataURL(f);}); }

// Media Library callback
function onMediaFilesSelected(files) {
    const existing = document.getElementById('galleryFromMedia').value;
    const paths = existing ? existing.split(',').filter(Boolean) : [];
    files.forEach(f => {
        if (!paths.includes(f.path)) paths.push(f.path);
        document.getElementById('uploadPreviews').insertAdjacentHTML('beforeend',
            `<div class="w-20 h-20 rounded-lg overflow-hidden border-2 border-green-300"><img src="${f.url}" class="w-full h-full object-cover"></div>`);
    });
    document.getElementById('galleryFromMedia').value = paths.join(',');
}

// Close dropdowns on click outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('#upsellSearch') && !e.target.closest('#upsellDropdown')) document.getElementById('upsellDropdown')?.classList.add('hidden');
    if (!e.target.closest('#bundleSearch') && !e.target.closest('#bundleDropdown')) document.getElementById('bundleDropdown')?.classList.add('hidden');
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
