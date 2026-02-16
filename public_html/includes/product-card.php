<?php
/**
 * Product Card Component — Modern, Mobile-First
 * Usage: include with $product variable set
 */
$imgUrl = getProductImage($product);
$price = getProductPrice($product);
$discount = getDiscountPercent($product);
$isOnSale = $product['sale_price'] && $product['sale_price'] > 0 && $product['sale_price'] < $product['regular_price'];
$btnAddLabel = getSetting('btn_add_to_cart_label', 'কার্টে যোগ করুন');
$btnArchiveOrder = getSetting('btn_archive_order_label', 'অর্ডার করুন');

$arShowButtons = getSetting('ar_show_card_buttons', '1') === '1';
$arShowOverlay = getSetting('ar_show_overlay', '1') === '1';
$arShowDiscount = getSetting('ar_show_discount_badge', '1') === '1';
$arShowOrderBtn = getSetting('ar_show_order_btn', '1') === '1';
$arCardStyle = getSetting('ar_card_style', 'standard');

$_hasVariants = false;
try {
    static $_variantCache = [];
    if (!isset($_variantCache[$product['id']])) {
        $_variantCache[$product['id']] = Database::getInstance()->fetch(
            "SELECT COUNT(*) as cnt FROM product_variants WHERE product_id = ? AND is_active = 1", 
            [$product['id']]
        )['cnt'] > 0;
    }
    $_hasVariants = $_variantCache[$product['id']];
} catch (\Throwable $e) {}
$_outOfStock = ($product['stock_status'] ?? '') === 'out_of_stock';
$_productUrl = url('product/' . $product['slug']);
$_productName = htmlspecialchars($product['name_bn'] ?: $product['name']);
?>

<div class="pc-card group">
    <!-- Image -->
    <a href="<?= $_productUrl ?>" class="pc-img-wrap">
        <img src="<?= $imgUrl ?>" alt="<?= $_productName ?>" loading="lazy"
             onerror="this.onerror=null;this.src='<?= asset('img/default-product.svg') ?>'">
        
        <?php if ($arShowDiscount && $isOnSale && $discount > 0): ?>
        <span class="pc-badge card-badge">-<?= $discount ?>%</span>
        <?php endif; ?>
        
        <?php if ($_outOfStock): ?>
        <div class="pc-soldout"><span>স্টক শেষ</span></div>
        <?php endif; ?>

        <?php if ($arShowOverlay && !$_outOfStock): ?>
        <div class="pc-hover-actions">
            <button onclick="event.preventDefault();event.stopPropagation();smartAddToCart(<?= $product['id'] ?>)" title="<?= $btnAddLabel ?>">
                <i class="fas fa-cart-plus"></i>
            </button>
            <button onclick="event.preventDefault();event.stopPropagation();smartOrder(<?= $product['id'] ?>)" title="<?= $btnArchiveOrder ?>">
                <i class="fas fa-shopping-bag"></i>
            </button>
        </div>
        <?php endif; ?>
    </a>
    
    <!-- Info -->
    <div class="pc-info">
        <a href="<?= $_productUrl ?>" class="pc-name card-name"><?= $_productName ?></a>
        
        <div class="pc-price">
            <span class="pc-price-now"><?= formatPrice($price) ?></span>
            <?php if ($isOnSale): ?>
            <span class="pc-price-was"><?= formatPrice($product['regular_price']) ?></span>
            <?php endif; ?>
        </div>
        
        <?php if ($arShowButtons && !$_outOfStock): ?>
        <?php if ($arCardStyle !== 'minimal'): ?>
        <div class="pc-actions">
            <button onclick="smartAddToCart(<?= $product['id'] ?>)" class="pc-btn-cart card-button">
                <i class="fas fa-cart-plus"></i>
                <span><?= $btnAddLabel ?></span>
            </button>
            <?php if ($arShowOrderBtn): ?>
            <button onclick="smartOrder(<?= $product['id'] ?>)" class="pc-btn-order card-button">
                <i class="fas fa-shopping-bag"></i>
                <span><?= $btnArchiveOrder ?></span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php elseif ($_outOfStock): ?>
        <div class="pc-actions">
            <span class="pc-btn-sold">স্টক শেষ</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($GLOBALS['_pcStylesPrinted'])): $GLOBALS['_pcStylesPrinted'] = true; ?>
<style>
/* ═══════════════════════════════════════
   PRODUCT CARD — Mobile-First Design
   ═══════════════════════════════════════ */

/* Card Container */
.pc-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    transition: box-shadow 0.25s ease, transform 0.25s ease;
    position: relative;
}

/* Image Area */
.pc-img-wrap {
    display: block;
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: #f9fafb;
    flex-shrink: 0;
}
.pc-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Discount Badge */
.pc-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: var(--sale-badge, #E53E3E);
    color: var(--sale-badge-text, #fff);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    line-height: 1.4;
    z-index: 2;
}

/* Sold Out */
.pc-soldout {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}
.pc-soldout span {
    background: #fff;
    color: #374151;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

/* Hover Quick Actions (Desktop) */
.pc-hover-actions {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 100%);
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    z-index: 4;
}
.pc-hover-actions button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff;
    color: #374151;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: background 0.2s, color 0.2s, transform 0.15s;
}
.pc-hover-actions button:first-child:hover { background: var(--btn-cart, #DD6B20); color: #fff; transform: scale(1.1); }
.pc-hover-actions button:last-child:hover { background: var(--btn-primary, #E53E3E); color: #fff; transform: scale(1.1); }

/* Info Section */
.pc-info {
    padding: 10px;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 4px;
}

/* Product Name */
.pc-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: var(--fs-card-name, 13px);
    font-weight: 600;
    line-height: 1.4;
    color: #1f2937;
    text-decoration: none;
    min-height: 2.8em;
    transition: color 0.2s;
}

/* Price Row */
.pc-price {
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 2px;
}
.pc-price-now {
    font-size: var(--fs-card-price, 16px);
    font-weight: 700;
    color: var(--primary, #E53E3E);
    letter-spacing: -0.02em;
}
.pc-price-was {
    font-size: var(--fs-card-old-price, 12px);
    color: #9ca3af;
    text-decoration: line-through;
}

/* Action Buttons */
.pc-actions {
    display: flex;
    gap: 6px;
    margin-top: auto;
    padding-top: 8px;
}

.pc-btn-cart,
.pc-btn-order {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 4px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: var(--fs-card-button, 13px);
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    transition: filter 0.2s, transform 0.1s;
    white-space: nowrap;
    overflow: hidden;
}
.pc-btn-cart {
    background: var(--btn-cart, #DD6B20);
    color: var(--btn-cart-text, #fff);
}
.pc-btn-order {
    background: var(--btn-primary, #E53E3E);
    color: var(--btn-primary-text, #fff);
}
.pc-btn-cart i,
.pc-btn-order i {
    font-size: 12px;
    flex-shrink: 0;
}
.pc-btn-cart span,
.pc-btn-order span {
    overflow: hidden;
    text-overflow: ellipsis;
}
.pc-btn-cart:active,
.pc-btn-order:active {
    transform: scale(0.96);
}
.pc-btn-sold {
    display: block;
    text-align: center;
    width: 100%;
    padding: 8px;
    border-radius: 8px;
    background: #f3f4f6;
    color: #9ca3af;
    font-size: 12px;
    font-weight: 600;
}

/* ═══ Tablet+ (≥640px) ═══ */
@media (min-width: 640px) {
    .pc-info {
        padding: 12px;
        gap: 5px;
    }
    .pc-name {
        font-size: 14px;
    }
    .pc-price-now {
        font-size: 17px;
    }
    .pc-price-was {
        font-size: 13px;
    }
    .pc-btn-cart,
    .pc-btn-order {
        padding: 9px 6px;
        font-size: 13px;
        border-radius: 9px;
    }
    .pc-actions {
        gap: 8px;
    }
}

/* ═══ Desktop (≥1024px) ═══ */
@media (min-width: 1024px) {
    .pc-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }
    .pc-card:hover .pc-img-wrap img {
        transform: scale(1.06);
    }
    .pc-card:hover .pc-hover-actions {
        opacity: 1;
        transform: translateY(0);
    }
    .pc-card:hover .pc-name {
        color: var(--primary, #E53E3E);
    }
    .pc-btn-cart:hover {
        filter: brightness(1.1);
    }
    .pc-btn-order:hover {
        filter: brightness(1.1);
    }
    .pc-info {
        padding: 14px;
    }
    .pc-name {
        font-size: 14px;
    }
    .pc-price-now {
        font-size: 18px;
    }
    .pc-btn-cart,
    .pc-btn-order {
        padding: 10px 8px;
        font-size: 13px;
        border-radius: 10px;
    }
}

/* ═══ Small phones (≤380px) — extra compact ═══ */
@media (max-width: 380px) {
    .pc-info { padding: 8px; }
    .pc-name { font-size: 12px; min-height: 2.6em; }
    .pc-price-now { font-size: 15px; }
    .pc-price-was { font-size: 11px; }
    .pc-btn-cart span,
    .pc-btn-order span { font-size: 11px; }
    .pc-btn-cart,
    .pc-btn-order { padding: 7px 3px; gap: 3px; }
    .pc-badge { font-size: 10px; padding: 1px 6px; }
}
</style>
<?php endif; ?>
