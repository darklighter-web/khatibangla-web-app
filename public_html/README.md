# E-Commerce Platform (Bangladeshi Market)

A complete, full-stack e-commerce platform built with PHP, MySQL, and Tailwind CSS  designed for the Bangladeshi market with Bengali (বাংা) UI, Cash on Delivery, and local courier integration.

## Quick Start

### Requirements
- PHP 8.0+, MySQL 5.7+, Apache/Nginx
- PDO MySQL extension enabled

### Installation
1. Import `database.sql` into your MySQL database
2. Copy `config/database.php` and update credentials
3. Point web root to the project folder
4. Access admin: `/admin` (default: admin / admin123)
5. Configure site settings from Admin → Settings

---

## Architecture

```
ecommerce/
├── admin/                    # Admin Panel
│   ├── api/                  # AJAX endpoints (actions, upload)
│   ├── includes/             # Auth, header, footer
│   ├── pages/                # 25 admin pages
   └ login.php
├─ api/                      # Frontend APIs (cart, order, wishlist, export)
─ config/                   # Database config
├── includes/                 # Shared functions, header, footer, product-card
├─ pages/                    # Frontend pages (11 pages)
├─ uploads/                  # User uploads (products, banners, logos)
├── index.php                 # Router
── .htaccess                 # URL rewriting + security
└── database.sql              # Full schema (40+ tables)
```

## Features

### 🛒 Frontend (11 pages)
- **Home** — Banners, featured products, categories, new arrivals
- **Product** — Gallery, variants, pricing, add-to-cart, reviews
- **Category** — Filterable product grid with sorting
- **Search** — Full-text search with sort options
- **Cart**  AJAX add/remove, coupon codes, checkout with COD
- **Customer Auth** — Login, register, password management
- **Account**  Orders, wishlist, addresses, profile editing
- **Order Tracking** — 5-step progress, shipment info, timeline
- **Order Success** — Confirmation with tracking link
- **Static Pages** — CMS-powered (About, Privacy, Terms, etc.)
- **404** — Custom error page (Bengali)

### ️ Admin Panel (25 pages)
| Section | Pages |
|---------|-------|
| **Core** | Dashboard (charts), Profile |
| **Sales** | Orders (list + filters), Order View (full detail), Manual Order Creation, Printable Invoice, Returns |
| **Catalog** | Products (CRUD + variants), Categories, Inventory (multi-warehouse) |
| **Customers** | Customer List (risk scoring), Customer Profile (block/notes/history) |
| **Shipping** | Courier Integration (Steadfast, Pathao, RedX, Paperfly) |
| **Finance** | Accounting (ledger + trends), Expenses, Reports & AI Insights |
| **Content** | Banners, CMS Pages, Notifications |
| **Team** | Employees (roles + permissions), Tasks |
| **Marketing** | Coupons (% or flat, limits, expiry) |
| **System** | Settings (22 color pickers, SEO, analytics, social) |

###  Security
- Prepared statements (SQL injection protection)
- Password hashing (bcrypt)
- CSRF token helpers
- IP & phone blocking
- Fraud detection / risk scoring
- Session-based auth
- .htaccess security headers

### 🇧 Bangladesh-Specific
- Bengali (বংা) frontend UI
- BDT () currency formatting
- Cash on Delivery (COD)
- Local courier APIs (Steadfast, Pathao, RedX, Paperfly)
- Dhaka / Outside Dhaka shipping zones
- Bangladeshi phone number formats (01XXXXXXXXX)

## Database Schema (40+ Tables)
Core: `products`, `categories`, `orders`, `order_items`, `customers`
Commerce: `product_variants`, `product_images`, `coupons`, `wishlists`
Shipping: `courier_providers`, `courier_shipments`, `shipping_zones`
Finance: `accounting_entries`, `expenses`, `expense_categories`
Admin: `admin_users`, `admin_roles`, `activity_log`, `notifications`, `tasks`
Content: `pages`, `banners`, `product_reviews`, `settings`
Fraud: `blocked_ips`, `blocked_phones`, `fraud_logs`, `incomplete_orders`
Inventory: `warehouses`, `warehouse_stock`

## Tech Stack
- **Backend**: PHP 8.0+ (no framework, clean MVC-like structure)
- **Database**: MySQL 5.7+
- **Frontend**: Tailwind CSS (CDN), vanilla JavaScript
- **Charts**: Chart.js 4
- **Icons**: Font Awesome 6
- **Fonts**: Hind Siliguri (Bengali), Inter (Admin)

## API Endpoints
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/cart.php` | POST | Add/update/remove cart items |
| `/api/order.php` | POST | Create order from checkout |
| `/api/wishlist.php` | POST | Toggle/add/remove wishlist items |
| `/api/export.php` | GET | CSV export (orders, products, customers) |
| `/admin/api/actions.php` | POST | Admin AJAX (status, stock, notifications) |
| `/admin/api/upload.php` | POST | File upload for products/banners |

# KHATIBANGLA.COM  COMPREHENSIVE BUG FIX GUIDE
# ================================================
# Date: Feb 15, 2026
# Issues: Product page broken, bundle not working, wrong product in checkout, store credit system

##  ROOT CAUSE ANALYSIS

The `$product` PHP variable in `pages/product.php` is OVERWRITTEN by the related products
foreach loop on line ~538:

```php
<?php foreach (array_slice($relatedProducts, 0, 4) as $product): ?>
```

After this loop, `$product` = the LAST related product (not the page's product).
All JavaScript below this line uses the WRONG product ID, price, and name.

This causes:
- ❌ Wrong PRODUCT_ID in JS → orders/bundles/cart target wrong product
- ❌ Wrong BASE_PRICE/REGULAR_PRICE → price display mismatch  
- ❌ Addon/variation clicks work visually but send wrong product to cart
- ❌ Bundle sends wrong product_id → "No bundle found" error
- ❌ Checkout shows wrong product name
- ❌ Mobile sticky bar shows wrong price (1,299 instead of ₿980)

##  FIX ORDER

1. Run `store-credit-upgrade.sql` in phpMyAdmin
2. Run `apply-fixes.php` on your server (then DELETE it)
3. Manually verify the $product fix was applied (see below)
4. Apply the footer.php store credit checkout patch
5. Apply the admin settings store credit editor patch
6. Test everything

## 🔧 FIX 1: $product Variable (CRITICAL)

### File: `pages/product.php`

**FIND** (around line 535-540):
```php
<?php foreach (array_slice($relatedProducts, 0, 4) as $product): ?>
    <?php include ROOT_PATH . 'includes/product-card.php'; ?>
<?php endforeach; ?>
```

**REPLACE WITH**:
```php
<?php $__mainProduct = $product; foreach (array_slice($relatedProducts, 0, 4) as $product): ?>
    <?php include ROOT_PATH . 'includes/product-card.php'; ?>
<?php endforeach; $product = $__mainProduct; ?>
```

### VERIFICATION:
After fix, visit any product page and check browser console:
- Open DevTools → Console
- Type: `PRODUCT_ID` — should match the actual product
- Type: `BASE_PRICE` — should match the displayed price

## 🔧 FIX 2: Bundle clear_first

### File: `pages/product.php`

**FIND** in addBundleToCart function:
```js
customer_upload: upload || null
        })
```

**REPLACE WITH**:
```js
customer_upload: upload || null,
            clear_first: true
        })
```

### File: `api/cart.php`

**FIND** in case 'add_bundle':
```php
case 'add_bundle':
            $productId = intval($input['product_id'] ?? 0);
```

**REPLACE WITH**:
```php
case 'add_bundle':
            if (!empty($input['clear_first'])) {
                clearCart();
            }
            $productId = intval($input['product_id'] ?? 0);
```

## 🔧 FIX 3: Bengali Product Name in Cart

### File: `includes/functions.php`

**FIND** in addToCart function:
```php
'name' => $product['name'],
```

**REPLACE WITH**:
```php
'name' => ($product['name_bn'] ?: $product['name']),
```

## 🔧 FIX 4: Store Credit System (1 credit = 0.75 tk)

### 4a. Database Migration
Run `database/store-credit-upgrade.sql` in phpMyAdmin.

### 4b. File: `includes/footer.php` — Checkout Credit UI

Replace the existing store credit HTML block in the checkout popup with:
```php
<?php 
$custCredit = 0;
$creditRate = floatval(getSetting('store_credit_rate', '0.75'));
$showStoreCredit = getSetting('store_credit_checkout', '1') === '1' && getSetting('store_credits_enabled', '1') === '1';
if ($showStoreCredit && isCustomerLoggedIn()) {
    $custCredit = getStoreCredit(getCustomerId());
}
if ($custCredit > 0 && $showStoreCredit): 
    $creditTkValue = round($custCredit * $creditRate);
?>
<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3">
    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer text-sm">
            <input type="checkbox" id="use-store-credit" class="rounded text-yellow-600" onchange="toggleStoreCredit()">
            <span class="text-yellow-700"><i class="fas fa-coins mr-1"></i>স্টোর ্রেডিট ব্যহার করুন</span>
        </label>
        <div class="text-right">
            <span class="text-xs text-yellow-600 font-semibold block"><?= number_format($custCredit, 0) ?> করেডিট</span>
            <span class="text-[10px] text-yellow-500">(= ৳<?= number_format($creditTkValue, 0) ?>)</span>
        </div>
    </div>
    <div id="credit-applied-row" class="hidden mt-2 flex justify-between text-yellow-700 text-sm">
        <span><i class="fas fa-coins mr-1"></i> স্টো ক্রেডিট:</span><span id="popup-credit" class="font-medium">-৳ 0</span>
    </div>
    <input type="hidden" id="store-credit-amount" name="store_credit_used" value="0">
    <input type="hidden" id="store-credit-max" value="<?= $custCredit ?>">
    <input type="hidden" id="credit-rate" value="<?= $creditRate ?>">
</div>
<?php endif; ?>
```

### 4c. File: `includes/footer.php` — JS updatePopupTotals

In the updatePopupTotals function, **FIND**:
```js
const creditMax = parseFloat(document.getElementById('store-credit-max')?.value || 0);
if (creditCheckbox && creditCheckbox.checked && creditMax > 0) {
    const beforeCredit = subtotal + shipping - couponDiscount;
    creditUsed = Math.min(creditMax, beforeCredit);
```

**REPLACE WITH**:
```js
const creditMax = parseFloat(document.getElementById('store-credit-max')?.value || 0);
const creditRate = parseFloat(document.getElementById('credit-rate')?.value || 0.75);
const creditTkValue = Math.round(creditMax * creditRate);
if (creditCheckbox && creditCheckbox.checked && creditMax > 0) {
    const beforeCredit = subtotal + shipping - couponDiscount;
    creditUsed = Math.min(creditTkValue, beforeCredit);
```

### 4d. File: `includes/functions.php` — createOrder credit deduction

In createOrder, **FIND**:
```php
$storeCreditUsed = min($requestedCredit, floatval($custRow['store_credit']), $total);
```

**REPLACE WITH**:
```php
$creditRate = floatval(getSetting('store_credit_rate', '0.75'));
$maxCreditTk = floatval($custRow['store_credit']) * $creditRate;
$storeCreditUsed = min($requestedCredit, $maxCreditTk, $total);
```

Also **FIND** the credit deduction line:
```php
addStoreCredit($order['customer_id'], -$storeCreditUsed, 'spend', 'order', $orderId
```

**ADD BEFORE IT**:
```php
// Convert tk back to credit units for deduction
$creditRate = floatval(getSetting('store_credit_rate', '0.75'));
$creditsToDeduct = $creditRate > 0 ? round($storeCreditUsed / $creditRate, 2) : $storeCreditUsed;
```

And change `-$storeCreditUsed` to `-$creditsToDeduct` in the addStoreCredit call.

### 4e. File: `admin/pages/settings.php` — Admin Store Credit Editor

Add the store credit settings card (from admin-credit-settings.php patch file)
to the Settings page. Also ensure these setting keys are included in the 
save handler:

```php
// In the settings save handler (POST section), add:
$creditSettings = ['store_credits_enabled', 'store_credit_rate', 'store_credit_checkout'];
foreach ($creditSettings as $key) {
    if (isset($_POST[$key])) {
        saveSetting($key, $_POST[$key]);
    }
}
```

## ✅ TESTING CHECKLIST

After applying all fixes:

1. [ ] Open Aviator OG product → Console shows PRODUCT_ID = 16, BASE_PRICE = 980
2. [ ] Click "অর্ডার করুন" → Checkout shows "এভিয়টর ও" (not Blood Pressure Monitor)
3. [ ] Click "বা্েল কিনুন" → Bundle products added to checkout correctly
4. [ ] Select variation (Golden/Black) → Price updates correctly
5. [ ] Select addon → Price adds correctly, can deselect by clicking again
6. [ ] Mobile sticky bar → Shows correct price
7. [ ] Login as user with credits → Checkout shows credit option with converted tk value
8. [ ] Check credit: 100 credits  0.75 = ৳75 discount applied
9. [ ] Admin → Settings → Store Credits → Can change rate, enable/disable checkout option
10. [ ] Place order with credit → Credit deducted in credit units, tk amount subtracted from total

17-02-2025 stead update//
# Steadfast Courier Full Integration
## Deploy Date: 2026-02-17

## What's Included

### New Files (copy to server)
- `public_html/api/steadfast.php`  Full Steadfast API class (create order, bulk, status check, balance, return requests, payments)
- `public_html/api/steadfast-actions.php` — AJAX endpoint for admin panel (upload, sync, balance, settings, webhook logs)
- `public_html/api/courier-webhook.php`  Enhanced webhook receiver (tracking_message, delivery_charge, DB logging)

### Updated Files (REPLACE existing)
- `public_html/admin/pages/courier.php`  Full Steadfast settings tab (API keys, login creds, webhook config, balance, stats, consignment lookup)
- `public_html/admin/pages/order-management.php` — Clickable consignment ID links, color-coded courier status, auto-migration for new columns
- `public_html/admin/pages/order-view.php` — Tracking card with upload button, sync status, portal links, customer tracking link

### SQL Migration (run in phpMyAdmin if auto-migration fails)
- `steadfast-migration.sql`

---

## Deployment Steps

### Step 1: Run SQL Migration
Open phpMyAdmin → Run `steadfast-migration.sql`
(Or skip this — `order-management.php` has auto-migration that creates columns on first load)

### Step 2: Upload via CyberPanel File Manager
Extract the ZIP maintaining the folder structure. Files go to:
```
public_html/
── api/
│   ├── steadfast.php          (NEW - replaces old)
│   ├── steadfast-actions.php  (NEW)
│   ── courier-webhook.php    (REPLACE)
└── admin/pages/
    ├── courier.php            (REPLACE)
    ├── order-management.php   (REPLACE)
    └── order-view.php         (REPLACE)
```

### Step 3: Configure in Admin Panel
1. Go to **Admin → Courier → Steadfast** tab
2. Enter your **API Key** and **Secret Key** from [portal.steadfast.com.bd/user/api](https://portal.steadfast.com.bd/user/api)
3. Click **Test Connection** — should show your balance
4. (Optional) Enter **Steadfast Login Email/Password** for delivery rating checks
5. (Optional) Set **Webhook Bearer Token** and configure it in [Steadfast Webhook Settings](https://portal.steadfast.com.bd/user/webhook/add)
6. Set **Default Shipping Note** if desired
7. Toggle **Active** and **Send Product Names** as needed
8. Click **Save Settings**

### Step 4: Configure Webhook in Steadfast Portal
1. Go to [portal.steadfast.com.bd → Webhook](https://portal.steadfast.com.bd/user/webhook/add)
2. Set Callback URL: `https://khatibangla.com/api/courier-webhook.php?courier=steadfast`
3. Set Auth Token (Bearer) to match what you entered in Step 3
4. Save

---

## Features

### Courier Settings Page (courier.php → Steadfast tab)
- ✅ API Key / Secret Key / Webhook Token fields
- ✅ Steadfast Login Credentials (email/password) for delivery rating
-  Default Shipping Note
- ✅ Active toggle + Send Product Names toggle
- ✅ Test Connection button
- ✅ Live Balance display with refresh
- ✅ Order statistics (total/shipped/delivered/cancelled/success rate)
- ✅ Webhook URL with copy button
- ✅ Consignment Lookup tool
- ✅ Recent Webhook Logs display
- ✅ Quick links to Steadfast portal
-  Bulk Sync All Orders button

### Order Management (order-management.php)
- ✅ Consignment ID displayed as clickable link (opens Steadfast portal)
- ✅ Tracking code shown with courier name
- ✅ Color-coded courier status (green=delivered, red=cancelled, yellow=hold, purple=in_review)
- ✅ Bulk upload to Steadfast (uses enhanced uploadOrder with product names, logging)
- ✅ Auto-migration for new DB columns on first load

### Order View (order-view.php)
- ✅ Clickable CID badge next to delivery method dropdown
- ✅ Full Tracking Card showing: Courier, CID, Tracking Code, Status
- ✅ Tracking message display (e.g. "Package arrived at sorting center")
-  Delivery charge and COD amount
-  Upload timestamp
- ✅ "Upload to Steadfast" button (for non-uploaded orders)
- ✅ "Sync Status" button (polls Steadfast API for latest status)
- ✅ "Open Portal" link (opens CID in Steadfast dashboard)
- ✅ "Customer Track" link (public tracking URL for customers)

### Webhook Auto-Sync (courier-webhook.php)
-  Handles both `delivery_status` and `tracking_update` notification types
- ✅ Auto-updates order status (delivered, cancelled, hold, partial_delivered)
- ✅ Stores tracking_message, delivery_charge, cod_amount
- ✅ Bearer token authentication
-  Prevents backward transitions (won't undo delivered/cancelled)
- ✅ Awards store credits on delivery
- ✅ Refunds credits on cancellation
- ✅ File-based + Database logging for debugging
- ✅ Status history logging

### API Class (steadfast.php)
-  All Steadfast API endpoints supported
- ✅ uploadOrder() — single order with product names, logging, status history
- ✅ bulkUploadOrders() — batch upload with individual fallback for small batches
- ✅ syncOrderStatus() — polls API and updates our DB
-  createReturnRequest() — submit return requests
- ✅ getPayments() — payment history
-  getBalance() — account balance
- ✅ portalUrl() / trackingUrl() — URL helpers

---

## Status Mapping (Steadfast → Our System)
| Steadfast Status | Our Status | Action |
|---|---|---|
| pending | (no change) | Logged only |
| in_review | (no change) | Logged only |
| delivered | delivered | Awards credits, sets delivered_at |
| delivered_approval_pending | delivered | Same as delivered |
| partial_delivered | partial_delivered | — |
| cancelled | pending_cancel | Refunds credits |
| cancelled_approval_pending | pending_cancel | Same as cancelled |
| hold | on_hold | — |
| unknown | (no change) | Logged only |


# Steadfast Courier Full Integration
## Deploy Date: 2026-02-17

## What's Included

### New Files (copy to server)
- `public_html/api/steadfast.php` — Full Steadfast API class (create order, bulk, status check, balance, return requests, payments)
- `public_html/api/steadfast-actions.php` — AJAX endpoint for admin panel (upload, sync, balance, settings, webhook logs)
- `public_html/api/courier-webhook.php` — Enhanced webhook receiver (tracking_message, delivery_charge, DB logging)

### Updated Files (REPLACE existing)
- `public_html/admin/pages/courier.php` — Full Steadfast settings tab (API keys, login creds, webhook config, balance, stats, consignment lookup)
- `public_html/admin/pages/order-management.php` — Clickable consignment ID links, color-coded courier status, auto-migration for new columns
- `public_html/admin/pages/order-view.php` — Tracking card with upload button, sync status, portal links, customer tracking link

### SQL Migration (run in phpMyAdmin if auto-migration fails)
- `steadfast-migration.sql`

---

## Deployment Steps

### Step 1: Run SQL Migration
Open phpMyAdmin → Run `steadfast-migration.sql`
(Or skip this — `order-management.php` has auto-migration that creates columns on first load)

### Step 2: Upload via CyberPanel File Manager
Extract the ZIP maintaining the folder structure. Files go to:
```
public_html/
├── api/
│   ├── steadfast.php          (NEW - replaces old)
│   ├── steadfast-actions.php  (NEW)
│   └── courier-webhook.php    (REPLACE)
└── admin/pages/
    ├─ courier.php            (REPLACE)
    ├── order-management.php   (REPLACE)
    └─ order-view.php         (REPLACE)
```

### Step 3: Configure in Admin Panel
1. Go to **Admin  Courier → Steadfast** tab
2. Enter your **API Key** and **Secret Key** from [portal.steadfast.com.bd/user/api](https://portal.steadfast.com.bd/user/api)
3. Click **Test Connection** — should show your balance
4. (Optional) Enter **Steadfast Login Email/Password** for delivery rating checks
5. (Optional) Set **Webhook Bearer Token** and configure it in [Steadfast Webhook Settings](https://portal.steadfast.com.bd/user/webhook/add)
6. Set **Default Shipping Note** if desired
7. Toggle **Active** and **Send Product Names** as needed
8. Click **Save Settings**

### Step 4: Configure Webhook in Steadfast Portal
1. Go to [portal.steadfast.com.bd → Webhook](https://portal.steadfast.com.bd/user/webhook/add)
2. Set Callback URL: `https://khatibangla.com/api/courier-webhook.php?courier=steadfast`
3. Set Auth Token (Bearer) to match what you entered in Step 3
4. Save

---

## Features

### Courier Settings Page (courier.php → Steadfast tab)
- ✅ API Key / Secret Key / Webhook Token fields
- ✅ Steadfast Login Credentials (email/password) for delivery rating
- ✅ Default Shipping Note
- ✅ Active toggle + Send Product Names toggle
- ✅ Test Connection button
-  Live Balance display with refresh
- ✅ Order statistics (total/shipped/delivered/cancelled/success rate)
- ✅ Webhook URL with copy button
- ✅ Consignment Lookup tool
- ✅ Recent Webhook Logs display
- ✅ Quick links to Steadfast portal
- ✅ Bulk Sync All Orders button

### Order Management (order-management.php)
- ✅ Consignment ID displayed as clickable link (opens Steadfast portal)
- ✅ Tracking code shown with courier name
- ✅ Color-coded courier status (green=delivered, red=cancelled, yellow=hold, purple=in_review)
- ✅ Bulk upload to Steadfast (uses enhanced uploadOrder with product names, logging)
- ✅ Auto-migration for new DB columns on first load

### Order View (order-view.php)
- ✅ Clickable CID badge next to delivery method dropdown
- ✅ Full Tracking Card showing: Courier, CID, Tracking Code, Status
- ✅ Tracking message display (e.g. "Package arrived at sorting center")
- ✅ Delivery charge and COD amount
- ✅ Upload timestamp
- ✅ "Upload to Steadfast" button (for non-uploaded orders)
- ✅ "Sync Status" button (polls Steadfast API for latest status)
- ✅ "Open Portal" link (opens CID in Steadfast dashboard)
- ✅ "Customer Track" link (public tracking URL for customers)

### Webhook Auto-Sync (courier-webhook.php)
- ✅ Handles both `delivery_status` and `tracking_update` notification types
- ✅ Auto-updates order status (delivered, cancelled, hold, partial_delivered)
- ✅ Stores tracking_message, delivery_charge, cod_amount
- ✅ Bearer token authentication
- ✅ Prevents backward transitions (won't undo delivered/cancelled)
- ✅ Awards store credits on delivery
- ✅ Refunds credits on cancellation
- ✅ File-based + Database logging for debugging
- ✅ Status history logging

### API Class (steadfast.php)
- ✅ All Steadfast API endpoints supported
- ✅ uploadOrder() — single order with product names, logging, status history
- ✅ bulkUploadOrders() — batch upload with individual fallback for small batches
- ✅ syncOrderStatus()  polls API and updates our DB
- ✅ createReturnRequest() — submit return requests
- ✅ getPayments() — payment history
- ✅ getBalance() — account balance
- ✅ portalUrl() / trackingUrl() — URL helpers

---

## Status Mapping (Steadfast → Our System)
| Steadfast Status | Our Status | Action |
|---|---|---|
| pending | (no change) | Logged only |
| in_review | (no change) | Logged only |
| delivered | delivered | Awards credits, sets delivered_at |
| delivered_approval_pending | delivered | Same as delivered |
| partial_delivered | partial_delivered | — |
| cancelled | pending_cancel | Refunds credits |
| cancelled_approval_pending | pending_cancel | Same as cancelled |
| hold | on_hold | — |
| unknown | (no change) | Logged only |

##blog update
# Blog System v2 — Installation Guide

## Files in this package

| File | Action | What Changed |
|------|--------|-------------|
| `blog-migration.sql` | Run in phpMyAdmin | Creates blog_posts + blog_categories tables |
| `public_html/admin/pages/blog.php` | NEW file | Full blog admin panel |
| `public_html/admin/pages/settings.php` | REPLACE | Added Timezone & Date/Time settings |
| `public_html/includes/functions.php` | REPLACE | Added timezone auto-initialization |
| `public_html/pages/blog.php` | NEW file | Blog listing page |
| `public_html/pages/blog-single.php` | NEW file | Single post with 4 templates |
| `public_html/index.php` | REPLACE | Blog routes already added |
| `public_html/admin/includes/header.php` | REPLACE | Blog Posts sidebar link |
| `public_html/includes/footer.php` | REPLACE | Blog footer link |

## Installation Steps

1. **Upload** all `public_html/` contents to your server's `public_html/` directory
2. **Run SQL** — Open phpMyAdmin, select your database, run `blog-migration.sql`
3. **Set Timezone** — Go to Admin → Settings → General tab → set your timezone
4. Tables auto-create on first admin visit if SQL wasn't run

## What was fixed (v2)

- **Link insertion** — Now uses `insertHTML` with proper anchor tag generation
- **Image picker** — Opens server Media Gallery (same as Media Gallery page) instead of URL prompt
- **Delete Block button** — Click any image/video/embed in editor, then press 🗑 Delete Block
- **Template designs** — Changed from ENUM to VARCHAR column; auto-fixes existing ENUM tables
- **Timezone** — New section in Settings → General with 40+ timezones + date/time format
- **PHP 8+ safe** — All `??` null coalescing, `COALESCE()` in SQL, `intval()` wrappers

## License
Private / Commercial Use
