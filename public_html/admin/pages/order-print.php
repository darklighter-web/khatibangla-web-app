<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

// Support single or bulk IDs
$ids = [];
if (!empty($_GET['id'])) {
    $ids = [intval($_GET['id'])];
} elseif (!empty($_GET['ids'])) {
    $ids = array_map('intval', explode(',', $_GET['ids']));
}
if (empty($ids)) redirect(adminUrl('pages/order-management.php'));

$template = $_GET['template'] ?? getSetting('invoice_template', 'standard');
$validTemplates = ['standard', 'compact', 'sticker', 'picking'];
if (!in_array($template, $validTemplates)) $template = 'standard';

// Fetch all orders
$ph = implode(',', array_fill(0, count($ids), '?'));
$orders = $db->fetchAll("SELECT * FROM orders WHERE id IN ({$ph}) ORDER BY id DESC", $ids);
$allItems = [];
foreach ($orders as $o) {
    $allItems[$o['id']] = $db->fetchAll("SELECT oi.*, p.sku, p.featured_image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?", [$o['id']]);
}

$siteName = getSetting('site_name', 'E-Commerce');
$sitePhone = getSetting('contact_phone', '');
$siteAddress = getSetting('footer_address', '');
$siteLogo = getSetting('site_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $template === 'sticker' ? 'Shipping Labels' : 'Invoice' ?> - <?= count($orders) > 1 ? count($orders) . ' Orders' : '#' . ($orders[0]['order_number'] ?? '') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #333; }
        @media print { .no-print { display: none !important; } .page-break { page-break-after: always; } body { padding: 0; } }
        .no-print { background: #f3f4f6; padding: 12px 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 10; }
        .no-print button, .no-print a { padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; border: 1px solid #d1d5db; background: white; color: #374151; }
        .no-print button:hover, .no-print a:hover { background: #f9fafb; }
        .no-print .active { background: #2563eb; color: white; border-color: #2563eb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

<?php if ($template === 'standard'): ?>
        /* ===== STANDARD INVOICE ===== */
        .invoice { max-width: 800px; margin: 0 auto; padding: 30px; }
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; border-bottom: 3px solid #2563eb; padding-bottom: 18px; }
        .inv-logo h1 { font-size: 22px; color: #2563eb; }
        .inv-logo p { font-size: 11px; color: #666; margin-top: 3px; }
        .inv-info { text-align: right; }
        .inv-info h2 { font-size: 18px; color: #333; margin-bottom: 4px; }
        .inv-info p { font-size: 12px; color: #666; }
        .inv-addresses { display: flex; justify-content: space-between; margin-bottom: 22px; }
        .inv-addresses > div { width: 48%; }
        .inv-addresses h3 { font-size: 10px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 6px; }
        .inv-addresses p { font-size: 13px; line-height: 1.6; }
        .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .inv-table thead th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #666; }
        .inv-table tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        .inv-totals { display: flex; justify-content: flex-end; }
        .inv-totals table { width: 280px; }
        .inv-totals td { padding: 5px 12px; font-size: 13px; }
        .inv-totals .grand td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 10px; }
        .inv-footer { margin-top: 30px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; padding-top: 15px; }
        .inv-note { margin-top: 15px; background: #f9fafb; padding: 10px 14px; border-radius: 6px; font-size: 12px; }

<?php elseif ($template === 'compact'): ?>
        /* ===== COMPACT INVOICE ===== */
        .invoice { max-width: 400px; margin: 10px auto; padding: 15px; border: 1px solid #ddd; font-size: 12px; }
        .comp-header { text-align: center; border-bottom: 2px dashed #999; padding-bottom: 10px; margin-bottom: 10px; }
        .comp-header h2 { font-size: 16px; }
        .comp-header p { font-size: 11px; color: #666; }
        .comp-meta { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; color: #555; }
        .comp-customer { border: 1px solid #eee; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; line-height: 1.5; }
        .comp-items { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 12px; }
        .comp-items th { text-align: left; border-bottom: 1px solid #ccc; padding: 4px 6px; font-size: 10px; color: #666; }
        .comp-items td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; }
        .comp-total { text-align: right; font-weight: bold; font-size: 16px; margin: 10px 0; }
        .comp-footer { text-align: center; font-size: 10px; color: #999; border-top: 1px dashed #ccc; padding-top: 8px; }

<?php elseif ($template === 'sticker'): ?>
        /* ===== SHIPPING STICKER ===== */
        .sticker-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; }
        .sticker { width: 380px; height: 260px; border: 2px solid #000; padding: 12px; font-size: 12px; page-break-inside: avoid; position: relative; }
        .sticker-header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px; }
        .sticker-header h3 { font-size: 14px; }
        .sticker-body p { line-height: 1.5; }
        .sticker-body strong { font-size: 15px; }
        .sticker-items { font-size: 11px; color: #444; margin-top: 6px; border-top: 1px dashed #999; padding-top: 4px; }
        .sticker-total { position: absolute; bottom: 12px; right: 12px; font-size: 20px; font-weight: bold; }
        .sticker-cod { position: absolute; top: 10px; right: 12px; background: #000; color: white; padding: 2px 10px; font-size: 11px; font-weight: bold; }

<?php elseif ($template === 'picking'): ?>
        /* ===== PICKING SHEET ===== */
        .picking { max-width: 800px; margin: 0 auto; padding: 20px; }
        .pick-header { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .pick-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .pick-table th, .pick-table td { border: 1px solid #ccc; padding: 8px 10px; font-size: 12px; }
        .pick-table th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .pick-check { width: 30px; height: 30px; border: 2px solid #999; display: inline-block; }
<?php endif; ?>
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="no-print">
        <button onclick="window.print()" style="background:#2563eb;color:white;border-color:#2563eb;">🖨 Print</button>
        <a href="<?= adminUrl('pages/order-management.php') ?>">← Back</a>
        <span style="color:#666;font-size:12px;">|</span>
        <span style="font-size:12px;color:#666;">Template:</span>
        <?php 
        $idParam = count($ids) === 1 ? 'id=' . $ids[0] : 'ids=' . implode(',', $ids);
        $labels = ['standard'=>'📄 Invoice','compact'=>'📋 Compact','sticker'=>'🏷 Sticker','picking'=>'📦 Picking'];
        foreach ($labels as $t => $label): ?>
        <a href="?<?= $idParam ?>&template=<?= $t ?>" class="<?= $template === $t ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <span style="margin-left:auto;font-size:12px;color:#888;"><?= count($orders) ?> order(s)</span>
    </div>

<?php foreach ($orders as $idx => $order):
    $items = $allItems[$order['id']] ?? [];
    $discount = floatval($order['discount'] ?? $order['discount_amount'] ?? 0);
?>

<?php if ($template === 'standard'): ?>
    <!-- ===== STANDARD INVOICE ===== -->
    <div class="invoice <?= $idx < count($orders) - 1 ? 'page-break' : '' ?>">
        <div class="inv-header">
            <div class="inv-logo">
                <h1><?= e($siteName) ?></h1>
                <?php if ($sitePhone): ?><p>📞 <?= e($sitePhone) ?></p><?php endif; ?>
                <?php if ($siteAddress): ?><p>📍 <?= e($siteAddress) ?></p><?php endif; ?>
            </div>
            <div class="inv-info">
                <h2>INVOICE</h2>
                <p><strong>#<?= e($order['order_number']) ?></strong></p>
                <p>Date: <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                <p>Payment: <?= strtoupper($order['payment_method'] ?? 'COD') ?></p>
            </div>
        </div>
        <div class="inv-addresses">
            <div>
                <h3>Customer</h3>
                <p><strong><?= e($order['customer_name']) ?></strong></p>
                <p>📞 <?= e($order['customer_phone']) ?></p>
                <?php if (!empty($order['customer_email'])): ?><p>✉ <?= e($order['customer_email']) ?></p><?php endif; ?>
            </div>
            <div>
                <h3>Shipping Address</h3>
                <p><?= e($order['customer_address'] ?? '') ?></p>
                <p><?= !empty($order['customer_city']) ? e($order['customer_city']) : '' ?><?= !empty($order['customer_district']) ? ', ' . e($order['customer_district']) : '' ?></p>
            </div>
        </div>
        <table class="inv-table">
            <thead><tr><th>#</th><th>Item</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= e($item['product_name']) ?></strong><?php if (!empty($item['variant_name'])): ?><br><span style="color:#666;font-size:11px"><?= e($item['variant_name']) ?></span><?php endif; ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right">৳<?= number_format($item['price']) ?></td>
                    <td class="text-right">৳<?= number_format($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="inv-totals"><table>
            <tr><td>Subtotal</td><td class="text-right">৳<?= number_format($order['subtotal']) ?></td></tr>
            <tr><td>Shipping</td><td class="text-right">৳<?= number_format($order['shipping_cost']) ?></td></tr>
            <?php if ($discount > 0): ?><tr><td>Discount</td><td class="text-right" style="color:#dc2626">-৳<?= number_format($discount) ?></td></tr><?php endif; ?>
            <tr class="grand"><td>Total</td><td class="text-right">৳<?= number_format($order['total']) ?></td></tr>
        </table></div>
        <?php if (!empty($order['notes'])): ?>
        <div class="inv-note"><strong>Note:</strong> <?= e($order['notes']) ?></div>
        <?php endif; ?>
        <div class="inv-footer"><p>Thank you for your order! | <?= e($siteName) ?></p></div>
    </div>

<?php elseif ($template === 'compact'): ?>
    <!-- ===== COMPACT / THERMAL ===== -->
    <div class="invoice <?= $idx < count($orders) - 1 ? 'page-break' : '' ?>">
        <div class="comp-header">
            <h2><?= e($siteName) ?></h2>
            <p><?= e($sitePhone) ?></p>
        </div>
        <div class="comp-meta">
            <span>#<?= e($order['order_number']) ?></span>
            <span><?= date('d/m/Y h:i A', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="comp-customer">
            <strong><?= e($order['customer_name']) ?></strong><br>
            📞 <?= e($order['customer_phone']) ?><br>
            📍 <?= e($order['customer_address'] ?? '') ?>
        </div>
        <table class="comp-items">
            <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr><td><?= e($item['product_name']) ?><?= !empty($item['variant_name']) ? ' (' . e($item['variant_name']) . ')' : '' ?></td><td class="text-center"><?= $item['quantity'] ?></td><td class="text-right">৳<?= number_format($item['subtotal']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align:right;font-size:11px;color:#666;">Shipping: ৳<?= number_format($order['shipping_cost']) ?><?= $discount > 0 ? ' | Discount: -৳' . number_format($discount) : '' ?></div>
        <div class="comp-total">Total: ৳<?= number_format($order['total']) ?></div>
        <?php if (!empty($order['notes'])): ?><div style="font-size:11px;color:#666;margin-bottom:6px;">Note: <?= e($order['notes']) ?></div><?php endif; ?>
        <div class="comp-footer">Payment: <?= strtoupper($order['payment_method'] ?? 'COD') ?> | Thank you!</div>
    </div>

<?php elseif ($template === 'sticker'): ?>
    <!-- ===== SHIPPING STICKER ===== -->
    <div class="sticker">
        <div class="sticker-cod"><?= strtoupper($order['payment_method'] ?? 'COD') ?></div>
        <div class="sticker-header">
            <h3><?= e($siteName) ?></h3>
            <span>#<?= e($order['order_number']) ?></span>
        </div>
        <div class="sticker-body">
            <p><strong><?= e($order['customer_name']) ?></strong></p>
            <p>📞 <?= e($order['customer_phone']) ?></p>
            <p>📍 <?= e(mb_strimwidth($order['customer_address'] ?? '', 0, 100, '...')) ?></p>
            <p><?= e($order['customer_city'] ?? '') ?><?= !empty($order['customer_district']) ? ', ' . e($order['customer_district']) : '' ?></p>
            <div class="sticker-items">
                <?php foreach ($items as $item): ?>
                <?= e($item['product_name']) ?> × <?= $item['quantity'] ?><?= !empty($item['variant_name']) ? ' (' . e($item['variant_name']) . ')' : '' ?><br>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sticker-total">৳<?= number_format($order['total']) ?></div>
    </div>

<?php elseif ($template === 'picking'): ?>
    <!-- ===== PICKING SHEET ===== -->
    <div class="picking <?= $idx < count($orders) - 1 ? 'page-break' : '' ?>">
        <div class="pick-header">
            <div>
                <h2 style="font-size:16px;">PICKING SHEET</h2>
                <p style="font-size:12px;color:#666;">Generated: <?= date('d M Y, h:i A') ?></p>
            </div>
            <div style="text-align:right;">
                <p><strong>#<?= e($order['order_number']) ?></strong></p>
                <p style="font-size:12px;"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:12px;padding:10px;background:#f9fafb;border-radius:6px;">
            <div><strong><?= e($order['customer_name']) ?></strong> | <?= e($order['customer_phone']) ?></div>
            <div><strong><?= strtoupper($order['payment_method'] ?? 'COD') ?></strong> | ৳<?= number_format($order['total']) ?></div>
        </div>
        <table class="pick-table">
            <thead><tr><th style="width:40px">✓</th><th>Product</th><th>SKU</th><th>Variant</th><th class="text-center">Qty</th><th>Location</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="text-center"><div class="pick-check"></div></td>
                <td><strong><?= e($item['product_name']) ?></strong></td>
                <td><?= e($item['sku'] ?? '-') ?></td>
                <td><?= !empty($item['variant_name']) ? e($item['variant_name']) : '-' ?></td>
                <td class="text-center" style="font-size:16px;font-weight:bold;"><?= $item['quantity'] ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:15px;display:flex;justify-content:space-between;font-size:11px;">
            <div>📍 <?= e(mb_strimwidth($order['customer_address'] ?? '', 0, 80, '...')) ?></div>
            <div>Packed by: _____________ | Checked by: _____________</div>
        </div>
    </div>
<?php endif; ?>

<?php endforeach; ?>

<?php if ($template === 'sticker'): ?>
</div><!-- close sticker-grid wrapper if needed -->
<?php endif; ?>

</body>
</html>
