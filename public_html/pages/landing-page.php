<?php
/**
 * Landing Page Frontend - Uses site's real checkout system
 * Orders go through regular createOrder() flow with real order IDs
 */
$db = Database::getInstance();
$slug = $pageSlug ?? '';
$isPreview = !empty($lpPreview) || !empty($_GET['preview']);

if ($isPreview) {
    $page = $db->fetch("SELECT * FROM landing_pages WHERE slug = ?", [$slug]);
    if ($page && empty($_SESSION['admin_id'])) {
        $tok = $_GET['preview'] ?? $lpPreview ?? '';
        if ($tok !== '1' && $tok !== md5('lp_preview_'.$page['id'].'_'.date('Ymd'))) $page = null;
    }
} else {
    $page = $db->fetch("SELECT * FROM landing_pages WHERE slug = ? AND status = 'active'", [$slug]);
}
if (!$page) { http_response_code(404); echo '<h1 style="text-align:center;padding:60px;font-family:sans-serif">Page not found</h1>'; return; }

$sections = json_decode($page['sections'] ?? '[]', true);
$settings = json_decode($page['settings'] ?? '{}', true);
$pageId = $page['id'];

// A/B testing
$variant = 'A';
if ($page['ab_test_enabled'] && !empty($page['ab_variant_b'])) {
    $variant = (crc32(session_id()) % 2 === 0) ? 'A' : 'B';
    if ($variant === 'B') { $b = json_decode($page['ab_variant_b'], true); if ($b) $sections = $b; }
}

$pc = $settings['primary_color'] ?? '#e53e3e';
$sc = $settings['secondary_color'] ?? '#1e293b';
$fH = $settings['font_heading'] ?? 'Poppins';
$fB = $settings['font_body'] ?? 'Inter';
$fc = $settings['floating_cta'] ?? ['enabled'=>true];
$wa = $settings['whatsapp'] ?? ['enabled'=>false];
$defaultProduct = intval($settings['default_product'] ?? -1);
$pca = $settings['product_click_action'] ?? 'regular_checkout';
$checkoutMode = $settings['checkout_mode'] ?? 'landing';
$showHeader = $settings['show_header'] ?? true;
$showFooter = $settings['show_footer'] ?? true;
$redirectEnabled = !empty($settings['redirect_enabled']);
$redirectUrl = $settings['redirect_url'] ?? '';

// Collect & enrich products
$allProducts = [];
foreach ($sections as $sec) {
    if ($sec['type'] === 'products' && ($sec['enabled'] ?? true)) {
        foreach (($sec['content']['products'] ?? []) as $p) {
            if (!empty($p['real_product_id'])) {
                $rp = $db->fetch("SELECT id, name, name_bn, slug, regular_price, sale_price, is_on_sale, featured_image FROM products WHERE id = ? AND is_active = 1", [intval($p['real_product_id'])]);
                if ($rp) {
                    if (empty($p['name']) || $p['name'] === 'নতুন পণ্য') $p['name'] = $rp['name_bn'] ?: $rp['name'];
                    $p['price'] = ($rp['is_on_sale'] && $rp['sale_price'] > 0 && $rp['sale_price'] < $rp['regular_price']) ? floatval($rp['sale_price']) : floatval($rp['regular_price']);
                    if ($rp['is_on_sale'] && $rp['sale_price'] > 0) $p['compare_price'] = floatval($rp['regular_price']);
                    if (empty($p['image']) && !empty($rp['featured_image'])) $p['image'] = SITE_URL.'/uploads/products/'.basename($rp['featured_image']);
                    if (empty($p['product_link'])) $p['product_link'] = SITE_URL.'/'.($rp['slug'] ?? 'product/'.$rp['id']);
                }
            }
            $allProducts[] = $p;
        }
    }
}

// Set meta for header.php
$pageTitle = $page['seo_title'] ?: $page['title'];
$pageDescription = $page['seo_description'] ?? '';

// Include site header (DOCTYPE, head, body, nav, all CSS/JS)
if ($showHeader !== false) {
    include ROOT_PATH . 'includes/header.php';
} else {
    // Minimal head without nav — session.php already loaded by index.php
    $siteName = getSetting('site_name', 'Shop');
    $primaryColor = getSetting('primary_color', '#e53e3e');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?: $siteName) ?></title>
<?php if (!empty($pageDescription)): ?><meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fH) ?>:wght@400;600;700;800;900&family=<?= urlencode($fB) ?>:wght@300;400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>var SITE_URL='<?= SITE_URL ?>';</script>
<style>*{margin:0;padding:0;box-sizing:border-box}body{background:#f8fafc;-webkit-font-smoothing:antialiased}</style>
</head>
<body>
<?php } ?>
?>

<?php if ($isPreview): ?>
<div id="previewBar" style="background:#1e293b;color:#fff;text-align:center;padding:8px 16px;font-size:13px;font-weight:600;position:sticky;top:0;z-index:9999;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap">
    <a href="<?= SITE_URL ?>/admin/pages/landing-page-builder.php?id=<?= $pageId ?>" style="color:#fbbf24;text-decoration:none;font-size:12px">← বিল্ডার</a>
    <span style="opacity:.3">|</span>
    <span style="font-size:11px;opacity:.7">Preview:</span>
    <button onclick="setPreviewMode('mobile')" class="pv-btn" data-mode="mobile" style="padding:4px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:transparent;color:#fff;font-size:11px;cursor:pointer;font-family:inherit">📱 Mobile</button>
    <button onclick="setPreviewMode('tablet')" class="pv-btn" data-mode="tablet" style="padding:4px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:transparent;color:#fff;font-size:11px;cursor:pointer;font-family:inherit">📱 Tablet</button>
    <button onclick="setPreviewMode('desktop')" class="pv-btn active" data-mode="desktop" style="padding:4px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.15);color:#fff;font-size:11px;cursor:pointer;font-family:inherit">🖥️ Desktop</button>
</div>
<style>
.pv-btn:hover,.pv-btn.active{background:rgba(255,255,255,.15)!important}
.preview-frame{margin:0 auto;transition:max-width .3s,box-shadow .3s;width:100%}
.preview-frame.mobile-view{max-width:375px;box-shadow:0 0 0 8px #334155,0 0 40px rgba(0,0,0,.3);border-radius:0 0 20px 20px}
.preview-frame.tablet-view{max-width:768px;box-shadow:0 0 0 8px #334155,0 0 40px rgba(0,0,0,.2);border-radius:0 0 12px 12px}
</style>
<script>
function setPreviewMode(mode){
    document.querySelectorAll('.pv-btn').forEach(b=>{b.classList.toggle('active',b.dataset.mode===mode)});
    const wrap=document.querySelector('.preview-frame')||document.querySelector('.lp-wrap');
    if(!wrap)return;
    wrap.classList.remove('mobile-view','tablet-view');
    if(mode==='mobile')wrap.classList.add('mobile-view');
    else if(mode==='tablet')wrap.classList.add('tablet-view');
}
</script>
<?php endif; ?>

<!-- LP Custom Fonts -->
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fH) ?>:wght@400;600;700;800;900&family=<?= urlencode($fB) ?>:wght@300;400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* LP-specific overrides */
.lp-wrap{font-family:'<?= $fB ?>','Noto Sans Bengali',sans-serif;color:#1a1a2e;overflow-x:hidden}
.lp-wrap,.lp-wrap *,.lp-wrap *::before,.lp-wrap *::after{box-sizing:border-box}
.lp-wrap h1,.lp-wrap h2,.lp-wrap h3,.lp-wrap h4{font-family:'<?= $fH ?>','Noto Sans Bengali',serif;line-height:1.3}
.lp-wrap img{max-width:100%;height:auto;display:block}
.ct{max-width:1100px;margin:0 auto;padding:0 16px}
.lp-btn{display:inline-block;background:<?= $pc ?>;color:#fff;padding:14px 32px;border-radius:12px;font-weight:700;font-size:clamp(14px,3.5vw,16px);text-decoration:none;border:none;cursor:pointer;transition:all .2s;font-family:inherit;-webkit-tap-highlight-color:transparent;text-align:center}
.lp-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px <?= $pc ?>40;filter:brightness(1.1)}
.lp-btn:active{transform:translateY(0)}

/* HERO */
.hero-wrap{display:flex;flex-direction:column;gap:24px;align-items:center}
.hero-txt{order:2;text-align:center;max-width:100%}.hero-img{order:1;width:100%}
.hero-split .hero-txt{text-align:left}
.hero-badge{display:inline-block;padding:6px 16px;border-radius:50px;font-size:12px;font-weight:700;margin-bottom:12px}
.lp-wrap .hero-h{font-size:clamp(24px,6vw,52px);font-weight:900;margin:0 0 12px;letter-spacing:-.5px;word-break:break-word}
.hero-sub{font-size:clamp(14px,3.5vw,19px);opacity:.85;margin:0 0 24px}
.hero-pic{border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12)}
.hero-pic img{width:100%;object-fit:cover;display:block}

/* TRUST */
.trust{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:16px 0}
.trust-item{display:flex;align-items:center;gap:8px;justify-content:center;padding:10px 8px;min-width:0}
.trust-icon{font-size:24px;flex-shrink:0;line-height:1}.trust-text{font-weight:700;font-size:clamp(11px,2.5vw,14px);overflow:hidden;text-overflow:ellipsis}

/* PRODUCTS */
.pgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.pcard{background:#fff;border-radius:14px;overflow:hidden;transition:all .3s;cursor:pointer;position:relative;border:1px solid rgba(0,0,0,.06);-webkit-tap-highlight-color:transparent;display:flex;flex-direction:column}
.pcard:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(0,0,0,.1)}
.pcard:active{transform:translateY(0)}
.pimg{aspect-ratio:1;overflow:hidden;background:#f1f5f9;position:relative}
.pimg img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pcard:hover .pimg img{transform:scale(1.04)}
.pbadge{position:absolute;top:8px;left:8px;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;z-index:2}
.pinfo{padding:12px;flex:1;display:flex;flex-direction:column;gap:4px}
.pname{font-weight:700;font-size:clamp(13px,3vw,16px);margin:0;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.pdesc{font-size:12px;opacity:.7;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pprice-row{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px;margin-top:auto;padding-top:4px}
.pprice{font-size:clamp(16px,3.8vw,22px);font-weight:900;white-space:nowrap}
.pcomp{font-size:clamp(11px,2.5vw,14px);text-decoration:line-through;opacity:.5;white-space:nowrap}
.pcta{width:100%;padding:10px;border:none;border-radius:10px;font-weight:700;font-size:clamp(12px,2.8vw,14px);cursor:pointer;transition:all .2s;margin-top:8px;font-family:inherit;-webkit-tap-highlight-color:transparent}
.pcta:hover{filter:brightness(1.1)}

/* Mobile column overrides */
@media(max-width:767px){
/* Products: mc1 = horizontal list cards, mc2 = default 2-col grid */
.pgrid.mc1{grid-template-columns:1fr}
.pgrid.mc1 .pcard{flex-direction:row;border-radius:12px}
.pgrid.mc1 .pimg{aspect-ratio:1;width:120px;min-width:120px;flex-shrink:0;border-radius:12px 0 0 12px}
.pgrid.mc1 .pinfo{padding:10px 14px;justify-content:center}
.pgrid.mc1 .pname{-webkit-line-clamp:1;font-size:14px}
.pgrid.mc1 .pdesc{-webkit-line-clamp:1;font-size:11px}
.pgrid.mc1 .pprice{font-size:16px}
.pgrid.mc1 .pcomp{font-size:11px}
.pgrid.mc1 .pcta{padding:8px;font-size:12px;border-radius:8px}
/* Features: mc1 = stacked full width */
.fgrid.mc1{grid-template-columns:1fr}
/* Testimonials: mc1 = default full width, mc2 = 2 cols */
.tgrid.mc2{grid-template-columns:repeat(2,1fr);gap:10px}
.tgrid.mc2 .tcard{padding:14px}
.tgrid.mc2 .ttext{font-size:12px;line-height:1.5;-webkit-line-clamp:4;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden}
.tgrid.mc2 .tstar{font-size:12px}
.tgrid.mc2 .tname{font-size:12px}
/* Trust: mc2 = 2 cols (default), mc3 = 3 cols, mc4 = 4 cols */
.trust.mc2{grid-template-columns:repeat(2,1fr)}
.trust.mc3{grid-template-columns:repeat(3,1fr)}
.trust.mc3 .trust-text{font-size:10px}
.trust.mc4{grid-template-columns:repeat(4,1fr)}
.trust.mc4 .trust-item{flex-direction:column;gap:4px;padding:8px 4px}
.trust.mc4 .trust-icon{font-size:20px}
.trust.mc4 .trust-text{font-size:9px;text-align:center}
}

/* FEATURES */
.fgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.fcard{text-align:center;padding:20px 14px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(0,0,0,.04);display:flex;flex-direction:column;align-items:center}
.fcard-icon{font-size:32px;margin-bottom:10px;display:block;line-height:1}
.fcard h3{font-weight:700;font-size:clamp(13px,3vw,17px);margin:0 0 4px;line-height:1.3}
.fcard p{font-size:clamp(12px,2.5vw,14px);opacity:.75;margin:0;line-height:1.5}

/* TESTIMONIALS */
.tgrid{display:grid;grid-template-columns:1fr;gap:12px}
.tcard{padding:20px;border-radius:14px;background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column}
.tstar{color:#fbbf24;font-size:14px;margin-bottom:8px;line-height:1}
.ttext{font-size:clamp(13px,3vw,15px);font-style:italic;line-height:1.7;margin:0 0 12px;flex:1}
.tname{font-weight:700;font-size:13px;margin:0}.tloc{font-size:11px;opacity:.6;margin:0}

/* FAQ */
.faqbox{border:1px solid rgba(0,0,0,.08);border-radius:12px;margin-bottom:8px;overflow:hidden}
.faqq{padding:14px 16px;font-weight:700;font-size:clamp(13px,3vw,15px);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px;-webkit-tap-highlight-color:transparent;line-height:1.4;min-height:48px}
.faqq:hover{background:rgba(0,0,0,.03)}
.faqa{padding:0 16px;max-height:0;overflow:hidden;transition:all .3s;font-size:clamp(13px,2.8vw,14px);line-height:1.8}
.faqbox.on .faqa{padding:0 16px 14px;max-height:500px}
.faqbox.on .faqarr{transform:rotate(180deg)}
.faqarr{transition:transform .3s;flex-shrink:0;font-size:12px}

/* COUNTDOWN */
.cd-row{display:flex;gap:8px;justify-content:center;margin:16px 0;flex-wrap:wrap}
.cd-box{background:rgba(0,0,0,.2);backdrop-filter:blur(10px);border-radius:10px;padding:10px 14px;min-width:56px;text-align:center;flex-shrink:0}
.cd-num{font-size:clamp(22px,5.5vw,32px);font-weight:900;line-height:1}
.cd-lbl{font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-top:3px}

/* VIDEO */
.vid{position:relative;aspect-ratio:16/9;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12);cursor:pointer;max-width:100%}
.vid iframe{width:100%;height:100%;border:0}
.vid-poster{width:100%;height:100%;object-fit:cover}
.vid-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)}
.vid-play:hover{background:rgba(0,0,0,.5)}
.vid-btn{width:60px;height:60px;background:<?= $pc ?>;border-radius:50%;display:flex;align-items:center;justify-content:center}
.vid-btn::after{content:'';border:solid #fff;border-width:0 3px 3px 0;display:inline-block;padding:7px;transform:rotate(-45deg);margin-left:3px}

/* BEFORE/AFTER */
.ba{position:relative;border-radius:14px;overflow:hidden;aspect-ratio:16/9;touch-action:none;max-width:100%}
.ba img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0}
.ba-bf{clip-path:inset(0 50% 0 0)}
.ba-sl{position:absolute;top:0;bottom:0;left:50%;width:4px;background:#fff;cursor:ew-resize;z-index:10}
.ba-sl::after{content:'⇔';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.ba-lb{position:absolute;top:12px;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;z-index:5;white-space:nowrap}

/* ORDER CTA SECTION */
.lp-order-cta{text-align:center;padding:48px 16px;background:linear-gradient(135deg,<?= $pc ?>10 0%,<?= $pc ?>05 100%);border-top:2px solid <?= $pc ?>20}
.lp-order-cta h2{font-size:clamp(20px,5vw,34px);font-weight:900;margin:0 0 8px}
.lp-order-cta p{font-size:clamp(13px,3vw,16px);opacity:.7;margin:0 auto 24px;max-width:500px}
.lp-order-btn{display:inline-block;padding:16px 36px;border-radius:14px;font-weight:800;font-size:clamp(15px,3.8vw,20px);border:none;cursor:pointer;box-shadow:0 8px 28px <?= $pc ?>40;transition:all .3s;font-family:inherit;-webkit-tap-highlight-color:transparent;text-decoration:none;color:#fff;background:<?= $pc ?>;max-width:100%;word-break:break-word}
.lp-order-btn:hover{transform:translateY(-3px);box-shadow:0 12px 36px <?= $pc ?>50}
.lp-order-btn:active{transform:translateY(0)}

/* FLOATING CTA */
.lp-fcta{position:fixed;bottom:16px;left:16px;right:16px;z-index:100;padding:14px;border-radius:14px;font-weight:800;font-size:15px;cursor:pointer;border:none;box-shadow:0 8px 28px rgba(0,0,0,.25);transition:opacity .4s,transform .4s;font-family:inherit;text-decoration:none;text-align:center;-webkit-tap-highlight-color:transparent;color:#fff;opacity:0;pointer-events:none;transform:translateY(20px)}
.lp-fcta.vis{opacity:1;pointer-events:auto;transform:translateY(0)}
/* Hide floating CTA when any popup/modal is open */
body.overflow-hidden .lp-fcta,body.overflow-hidden .lp-wa{opacity:0!important;pointer-events:none!important;transform:translateY(20px)!important}

/* SECTION BASE */
.sec{padding:40px 0}
.sec-h{font-size:clamp(20px,5vw,36px);font-weight:800;text-align:center;margin:0 0 6px;word-break:break-word}
.sec-sub{text-align:center;font-size:clamp(13px,3vw,16px);opacity:.7;margin:0 auto 28px;max-width:560px}

/* MOBILE CAROUSEL */
@media(max-width:767px){
.mcr{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;gap:12px;padding-bottom:12px;scrollbar-width:none;scroll-behavior:smooth}
.mcr::-webkit-scrollbar{display:none}
.mcr>*{scroll-snap-align:start;flex-shrink:0}
.mcr.mcr-products>*{width:70vw;max-width:260px}
.mcr.mcr-features>*{width:75vw;max-width:280px}
.mcr.mcr-testimonials>*{width:82vw;max-width:320px}
.mcr.mcr-trust>*{width:auto;min-width:120px}
.mcr-dots{display:flex;justify-content:center;gap:6px;margin-top:10px}
.mcr-dot{width:8px;height:8px;border-radius:50%;background:rgba(0,0,0,.15);transition:all .3s}
.mcr-dot.on{background:<?= $pc ?>;width:20px;border-radius:4px}
}

/* ─── TABLET ─── */
@media(min-width:640px){.ct{padding:0 24px}}
@media(min-width:768px){
.hero-split{flex-direction:row;gap:40px}
.hero-split .hero-txt{order:1;flex:1;text-align:left}.hero-split .hero-img{order:2;flex:1}
.trust{grid-template-columns:repeat(auto-fit,minmax(120px,1fr))}
.pgrid,.fgrid{gap:20px}
.tgrid{grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
.sec{padding:60px 0}
.lp-fcta{left:50%;right:auto;max-width:360px;border-radius:50px;transform:translateX(-50%) translateY(20px)}
.lp-fcta.vis{transform:translateX(-50%) translateY(0)}
.lp-fcta.vis:hover{transform:translateX(-50%) translateY(-3px)}
}
@media(min-width:1024px){
.pgrid.c3{grid-template-columns:repeat(3,1fr)}
.pgrid.c4{grid-template-columns:repeat(4,1fr)}
.fgrid.c3{grid-template-columns:repeat(3,1fr)}
.fgrid.c4{grid-template-columns:repeat(4,1fr)}
.tgrid.c3{grid-template-columns:repeat(3,1fr)}
}
</style>

<!-- ═══ LANDING PAGE CONTENT ═══ -->
<?php if ($isPreview): ?><div class="preview-frame"><?php endif; ?>
<div class="lp-wrap">

<?php
$gpi = 0;
foreach ($sections as $sec):
if (!($sec['enabled'] ?? true)) continue;
$c = $sec['content'] ?? []; $st = $sec['settings'] ?? [];
$bg = $st['bg_color'] ?? '#ffffff'; $tx = $st['text_color'] ?? '#1a1a2e';
$ac = $st['accent_color'] ?? $pc; $sid = $sec['id'] ?? '';
$cols = intval($st['columns'] ?? 3);
?>

<?php if ($sec['type'] === 'hero'): $layout = $st['layout'] ?? 'split'; ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>;padding:<?= $st['padding'] ?? '60px' ?> 0">
<div class="ct hero-wrap <?= $layout === 'split' ? 'hero-split' : '' ?>">
<div class="hero-txt">
<?php if (!empty($c['badge'])): ?><span class="hero-badge" style="background:<?= $ac ?>;color:#fff"><?= htmlspecialchars($c['badge']) ?></span><?php endif; ?>
<h1 class="hero-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h1>
<p class="hero-sub"><?= htmlspecialchars($c['subheadline'] ?? '') ?></p>
<a href="javascript:void(0)" onclick="lpCheckout()" class="lp-btn" style="background:<?= $ac ?>"><?= htmlspecialchars($c['cta_text'] ?? 'অর্ডার করুন') ?></a>
</div>
<?php if (!empty($c['image'])): ?><div class="hero-img"><div class="hero-pic"><img src="<?= htmlspecialchars($c['image']) ?>" alt="" loading="eager"></div></div><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'trust_badges'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>;padding:16px 0">
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); $mcols = intval($st['mobile_columns'] ?? 2); ?>
<div class="ct"><div class="trust mc<?= $mcols ?><?= $isMcr ? ' mcr mcr-trust' : '' ?>"<?= $cols > 2 && !$isMcr ? " style=\"grid-template-columns:repeat({$cols},1fr)\"" : '' ?> <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?>>
<?php foreach (($c['badges'] ?? []) as $b): ?>
<div class="trust-item"><span class="trust-icon"><?= $b['icon'] ?? '🛡️' ?></span><span class="trust-text"><?= htmlspecialchars($b['text'] ?? '') ?></span></div>
<?php endforeach; ?>
</div>
<?php if ($isMcr): ?><div class="mcr-dots" data-for="trust"></div><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'products'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct">
<?php if (!empty($c['headline'])): ?><h2 class="sec-h"><?= htmlspecialchars($c['headline']) ?></h2><?php endif; ?>
<?php if (!empty($c['subheadline'])): ?><p class="sec-sub"><?= htmlspecialchars($c['subheadline']) ?></p><?php endif; ?>
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); $mcols = intval($st['mobile_columns'] ?? 2); ?>
<div class="pgrid c<?= $cols ?> mc<?= $mcols ?><?= $isMcr ? ' mcr mcr-products' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?>>
<?php foreach (($c['products'] ?? []) as $p):
$hd = !empty($p['compare_price']) && $p['compare_price'] > ($p['price'] ?? 0);
$rpid = intval($p['real_product_id'] ?? 0);
?>
<div class="pcard" onclick="lpProductClick(<?= $gpi ?>,<?= $rpid ?>)" data-i="<?= $gpi ?>">
<div class="pimg">
<?php if (!empty($p['badge'])): ?><span class="pbadge" style="background:<?= $ac ?>;color:#fff"><?= htmlspecialchars($p['badge']) ?></span><?php endif; ?>
<?php if (!empty($p['image'])): ?><img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name'] ?? '') ?>" loading="lazy">
<?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:40px;opacity:.2">📦</div><?php endif; ?>
</div>
<div class="pinfo">
<h3 class="pname"><?= htmlspecialchars($p['name'] ?? '') ?></h3>
<?php if (!empty($p['description'])): ?><p class="pdesc"><?= htmlspecialchars($p['description']) ?></p><?php endif; ?>
<div class="pprice-row"><span class="pprice" style="color:<?= $ac ?>">৳<?= number_format($p['price'] ?? 0) ?></span><?php if ($hd): ?><span class="pcomp">৳<?= number_format($p['compare_price']) ?></span><?php endif; ?></div>
<button class="pcta" style="background:<?= $ac ?>;color:#fff">অর্ডার করুন</button>
</div>
</div>
<?php $gpi++; endforeach; ?>
</div>
<?php if ($isMcr): ?><div class="mcr-dots" data-for="pgrid"></div><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'features'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct">
<h2 class="sec-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h2>
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); $mcols = intval($st['mobile_columns'] ?? 2); ?>
<div class="fgrid c<?= $cols ?> mc<?= $mcols ?><?= $isMcr ? ' mcr mcr-features' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?> style="margin-top:24px">
<?php foreach (($c['features'] ?? []) as $f): ?>
<div class="fcard"><span class="fcard-icon"><?= $f['icon'] ?? '⭐' ?></span><h3><?= htmlspecialchars($f['title'] ?? '') ?></h3><p><?= htmlspecialchars($f['desc'] ?? '') ?></p></div>
<?php endforeach; ?>
</div>
<?php if ($isMcr): ?><div class="mcr-dots" data-for="fgrid"></div><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'testimonials'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct">
<h2 class="sec-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h2>
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); $mcols = intval($st['mobile_columns'] ?? 1); ?>
<div class="tgrid c<?= $cols ?> mc<?= $mcols ?><?= $isMcr ? ' mcr mcr-testimonials' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?> style="margin-top:24px">
<?php foreach (($c['items'] ?? []) as $t): ?>
<div class="tcard">
<div class="tstar"><?= str_repeat('★',$t['rating']??5).str_repeat('☆',5-($t['rating']??5)) ?></div>
<p class="ttext">"<?= htmlspecialchars($t['text'] ?? '') ?>"</p>
<div class="tname"><?= htmlspecialchars($t['name'] ?? '') ?></div>
<div class="tloc"><?= htmlspecialchars($t['location'] ?? '') ?></div>
</div>
<?php endforeach; ?>
</div>
<?php if ($isMcr): ?><div class="mcr-dots" data-for="tgrid"></div><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'countdown'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>;text-align:center">
<div class="ct">
<h2 class="sec-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h2>
<?php if (!empty($c['subheadline'])): ?><p class="sec-sub"><?= htmlspecialchars($c['subheadline']) ?></p><?php endif; ?>
<div class="cd-row" data-end="<?= htmlspecialchars($c['end_date'] ?? '') ?>">
<div class="cd-box"><div class="cd-num" id="cdd<?= $sid ?>">00</div><div class="cd-lbl">দিন</div></div>
<div class="cd-box"><div class="cd-num" id="cdh<?= $sid ?>">00</div><div class="cd-lbl">ঘণ্টা</div></div>
<div class="cd-box"><div class="cd-num" id="cdm<?= $sid ?>">00</div><div class="cd-lbl">মিনিট</div></div>
<div class="cd-box"><div class="cd-num" id="cds<?= $sid ?>">00</div><div class="cd-lbl">সেকেন্ড</div></div>
</div>
<?php if (!empty($c['cta_text'])): ?><a href="javascript:void(0)" onclick="lpCheckout()" class="lp-btn" style="background:<?= $tx==='#ffffff'?'#fff':$ac ?>;color:<?= $tx==='#ffffff'?$bg:'#fff' ?>"><?= htmlspecialchars($c['cta_text']) ?></a><?php endif; ?>
</div>
</section>

<?php elseif ($sec['type'] === 'video'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct">
<?php if (!empty($c['headline'])): ?><h2 class="sec-h" style="margin-bottom:20px"><?= htmlspecialchars($c['headline']) ?></h2><?php endif; ?>
<div class="vid" onclick="this.innerHTML='<iframe src=\'https://www.youtube.com/embed/<?= htmlspecialchars($c['youtube_id'] ?? '') ?>?autoplay=1\' allow=\'autoplay;encrypted-media\' allowfullscreen></iframe>'">
<?php if (!empty($c['poster_image'])): ?><img src="<?= htmlspecialchars($c['poster_image']) ?>" class="vid-poster"><?php else: ?><div style="width:100%;height:100%;background:#000"></div><?php endif; ?>
<div class="vid-play"><div class="vid-btn"></div></div>
</div>
</div>
</section>

<?php elseif ($sec['type'] === 'before_after'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct">
<h2 class="sec-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h2>
<div class="ba" style="margin-top:20px;max-width:700px;margin-left:auto;margin-right:auto">
<?php if (!empty($c['after_image'])): ?><img src="<?= htmlspecialchars($c['after_image']) ?>"><?php endif; ?>
<?php if (!empty($c['before_image'])): ?><img src="<?= htmlspecialchars($c['before_image']) ?>" class="ba-bf" id="bab<?= $sid ?>"><?php endif; ?>
<div class="ba-sl" id="bas<?= $sid ?>"></div>
<span class="ba-lb" style="left:12px;background:rgba(0,0,0,.6);color:#fff"><?= htmlspecialchars($c['before_label'] ?? 'Before') ?></span>
<span class="ba-lb" style="right:12px;background:rgba(255,255,255,.9);color:#1a1a2e"><?= htmlspecialchars($c['after_label'] ?? 'After') ?></span>
</div>
</div>
</section>

<?php elseif ($sec['type'] === 'faq'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>;color:<?= $tx ?>">
<div class="ct" style="max-width:700px">
<h2 class="sec-h"><?= htmlspecialchars($c['headline'] ?? '') ?></h2>
<div style="margin-top:20px">
<?php foreach (($c['items'] ?? []) as $q): ?>
<div class="faqbox"><div class="faqq" onclick="this.parentElement.classList.toggle('on')"><span><?= htmlspecialchars($q['q'] ?? '') ?></span><span class="faqarr">▼</span></div><div class="faqa"><?= htmlspecialchars($q['a'] ?? '') ?></div></div>
<?php endforeach; ?>
</div>
</div>
</section>

<?php elseif ($sec['type'] === 'custom_html'): ?>
<section class="sec" data-s="<?= $sid ?>" style="background:<?= $bg ?>">
<div class="ct"><?= $c['html'] ?? '' ?></div>
</section>
<?php endif; endforeach; ?>

<!-- ═══ ORDER SECTION ═══ -->
<?php
$of = $settings['order_form'] ?? [];
$ofTitle = $of['title'] ?? 'অর্ডার করুন';
$ofSubtitle = $of['subtitle'] ?? '';
$ofBtnText = $of['button_text'] ?? 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন';
$ofBtnColor = $of['button_color'] ?? $pc;
$ofSuccessTitle = $of['success_title'] ?? 'অর্ডার সফল হয়েছে!';
$ofSuccessMsg = $of['success_message'] ?? 'শীঘ্রই আমাদের টিম আপনার সাথে যোগাযোগ করবে।';

// Delivery charges: LP-specific or site-wide based on checkout mode
if ($checkoutMode === 'landing') {
    $ofDelCharges = $of['delivery_charges'] ?? [];
    $ofDhaka = intval($ofDelCharges['inside_dhaka'] ?? 70);
    $ofSub = intval($ofDelCharges['dhaka_sub'] ?? 100);
    $ofOut = intval($ofDelCharges['outside_dhaka'] ?? 130);
} else {
    // Regular mode — use site-wide delivery charges
    $ofDhaka = intval(getSetting('shipping_inside_dhaka', 60));
    $ofSub = intval(getSetting('shipping_dhaka_sub', 100));
    $ofOut = intval(getSetting('shipping_outside_dhaka', 120));
}

// Load custom checkout fields config
// Priority: LP-specific override → site-wide config → hardcoded defaults
$_lpCf = $settings['checkout_fields'] ?? null;
if (!$_lpCf || !is_array($_lpCf)) {
    $_lpCfJson = getSetting('checkout_fields', '');
    $_lpCf = $_lpCfJson ? json_decode($_lpCfJson, true) : null;
}
if (!$_lpCf) {
    $_lpCf = [
        ['key'=>'product_selector','label'=>'পণ্য নির্বাচন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
        ['key'=>'name','label'=>'আপনার নাম','type'=>'text','enabled'=>true,'required'=>true,'placeholder'=>'সম্পূর্ণ নাম লিখুন'],
        ['key'=>'phone','label'=>'মোবাইল নম্বর','type'=>'tel','enabled'=>true,'required'=>true,'placeholder'=>'01XXXXXXXXX'],
        ['key'=>'email','label'=>'ইমেইল','type'=>'email','enabled'=>false,'required'=>false,'placeholder'=>'your@email.com'],
        ['key'=>'address','label'=>'সম্পূর্ণ ঠিকানা','type'=>'textarea','enabled'=>true,'required'=>true,'placeholder'=>'বাসা/রোড নং, এলাকা, থানা, জেলা'],
        ['key'=>'shipping_area','label'=>'ডেলিভারি এরিয়া','type'=>'radio','enabled'=>true,'required'=>true,'placeholder'=>''],
        ['key'=>'lp_upsells','label'=>'এটাও নিতে পারেন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''],
        ['key'=>'notes','label'=>'অতিরিক্ত নোট','type'=>'text','enabled'=>false,'required'=>false,'placeholder'=>'বিশেষ কোনো নির্দেশনা থাকলে লিখুন'],
    ];
}
// Ensure LP fields exist in saved config
$_lpKeys = array_column($_lpCf, 'key');
if (!in_array('product_selector', $_lpKeys)) {
    array_unshift($_lpCf, ['key'=>'product_selector','label'=>'পণ্য নির্বাচন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>'']);
}
if (!in_array('lp_upsells', $_lpKeys)) {
    $_lpCf[] = ['key'=>'lp_upsells','label'=>'এটাও নিতে পারেন','type'=>'system','enabled'=>true,'required'=>false,'placeholder'=>''];
}
// Filter to LP-usable fields (skip system widgets like cart_summary, progress_bar, coupon, etc.)
$_lpSkipKeys = ['cart_summary','progress_bar','coupon','store_credit','upsells','order_total'];
$_lpFormFields = [];
foreach ($_lpCf as $_cf) {
    if (!($_cf['enabled'] ?? true)) continue;
    if (in_array($_cf['key'] ?? '', $_lpSkipKeys)) continue;
    $_lpFormFields[] = $_cf;
}
?>
<?php if ($checkoutMode !== 'hidden'): ?>
<section class="lp-order-cta" id="order" style="padding:40px 16px;background:linear-gradient(135deg,<?= $pc ?>08 0%,<?= $pc ?>03 100%);border-top:2px solid <?= $pc ?>15">
<div style="max-width:500px;margin:0 auto">
    <h2 style="color:<?= $sc ?>;font-size:clamp(20px,5vw,30px);font-weight:900;text-align:center;margin:0 0 4px"><?= htmlspecialchars($ofTitle) ?></h2>
    <?php if ($ofSubtitle): ?><p style="text-align:center;opacity:.6;margin:0 0 20px;font-size:14px"><?= htmlspecialchars($ofSubtitle) ?></p><?php else: ?><div style="height:16px"></div><?php endif; ?>

    <form id="lpInlineForm" onsubmit="return lpSubmitInline(event)" style="background:#fff;border-radius:16px;padding:24px 20px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.06)">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="channel" value="landing_page">
        <input type="hidden" name="lp_page_id" value="<?= $pageId ?>">
        <div id="lpFormProduct" style="display:none;background:<?= $pc ?>08;border-radius:12px;padding:12px;margin-bottom:16px;border:1px solid <?= $pc ?>15">
            <div style="display:flex;align-items:center;gap:12px">
                <img id="lpFormProdImg" src="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0" onerror="this.style.display='none'">
                <div style="flex:1;min-width:0">
                    <div id="lpFormProdName" style="font-weight:700;font-size:14px;color:#1a1a2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
                        <span id="lpFormProdPrice" style="font-weight:800;color:<?= $pc ?>;font-size:16px"></span>
                        <span id="lpFormProdQty" style="font-size:12px;color:#888"></span>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
<?php
$_lpIS = "width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border .2s";
foreach ($_lpFormFields as $_cf):
    $_k = $_cf['key'] ?? '';
    $_l = htmlspecialchars($_cf['label'] ?? '');
    $_p = htmlspecialchars($_cf['placeholder'] ?? '');
    $_req = !empty($_cf['required']);
    $_ra = $_req ? 'required' : '';
    $_star = $_req ? ' <span style="color:#ef4444">*</span>' : '';
?>
<?php if ($_k === 'product_selector'): ?>
            <!-- Product Selector with Quantity -->
            <?php if (count($allProducts) > 0): ?>
            <div id="lpProdSelector">
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:6px"><?= $_l ?></label>
                <div id="lpProdList" style="display:flex;flex-direction:column;gap:6px">
                <?php foreach ($allProducts as $_pi => $_pp): 
                    $_ppId = intval($_pp['real_product_id'] ?? 0);
                    $_ppName = htmlspecialchars($_pp['name'] ?? 'পণ্য '.($_pi+1));
                    $_ppPrice = floatval($_pp['price'] ?? 0);
                    $_ppImg = $_pp['image'] ?? '';
                    $_ppDefault = ($_pi === ($settings['default_product'] ?? -1));
                    // Use real_product_id if available, otherwise use negative index as placeholder
                    $_ppVal = $_ppId > 0 ? $_ppId : -($_pi + 1);
                ?>
                    <label class="lp-prod-opt<?= $_ppDefault ? ' lp-prod-active' : '' ?>" data-pid="<?= $_ppVal ?>" data-price="<?= $_ppPrice ?>" data-idx="<?= $_pi ?>" onclick="lpPickProduct(this)" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid <?= $_ppDefault ? $pc : '#e5e7eb' ?>;border-radius:10px;cursor:pointer;transition:all .2s;background:<?= $_ppDefault ? $pc.'08' : '#fff' ?>">
                        <input type="radio" name="lp_product" value="<?= $_ppVal ?>" <?= $_ppDefault ? 'checked' : '' ?> style="accent-color:<?= $pc ?>;flex-shrink:0">
                        <?php if ($_ppImg): ?><img src="<?= htmlspecialchars($_ppImg) ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;flex-shrink:0" onerror="this.style.display='none'"><?php endif; ?>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;font-size:13px;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $_ppName ?></div>
                            <div style="font-weight:800;color:<?= $pc ?>;font-size:15px">৳<?= number_format($_ppPrice) ?></div>
                        </div>
                        <div class="lp-qty-ctrl" style="display:flex;align-items:center;gap:2px;border:1px solid #e5e7eb;border-radius:8px;padding:2px;background:#fff" onclick="event.stopPropagation()">
                            <button type="button" onclick="lpQtyChange(this,-1)" style="width:28px;height:28px;border:none;background:#f1f5f9;border-radius:6px;font-size:15px;cursor:pointer;color:#6b7280;font-weight:700;display:flex;align-items:center;justify-content:center">−</button>
                            <span class="lp-qty-val" style="width:28px;text-align:center;font-size:14px;font-weight:700">1</span>
                            <button type="button" onclick="lpQtyChange(this,1)" style="width:28px;height:28px;border:none;background:#f1f5f9;border-radius:6px;font-size:15px;cursor:pointer;color:#6b7280;font-weight:700;display:flex;align-items:center;justify-content:center">+</button>
                        </div>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
<?php elseif ($_k === 'lp_upsells'): ?>
            <!-- Upsell Products -->
            <div id="lpUpsellWrap" style="display:none">
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:6px"><span style="color:#f97316">🔥</span> <?= $_l ?></label>
                <div id="lpUpsellList" style="display:flex;flex-direction:column;gap:6px"></div>
            </div>
<?php elseif ($_k === 'name'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px"><?= $_l ?><?= $_star ?></label>
                <input type="text" name="name" <?= $_ra ?> placeholder="<?= $_p ?>" style="<?= $_lpIS ?>" onfocus="this.style.borderColor='<?= $pc ?>'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
<?php elseif ($_k === 'phone'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px"><?= $_l ?><?= $_star ?></label>
                <input type="tel" name="phone" <?= $_ra ?> placeholder="<?= $_p ?>" pattern="01[0-9]{9}" style="<?= $_lpIS ?>" onfocus="this.style.borderColor='<?= $pc ?>'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
<?php elseif ($_k === 'email'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px"><?= $_l ?><?= $_star ?></label>
                <input type="email" name="email" <?= $_ra ?> placeholder="<?= $_p ?>" style="<?= $_lpIS ?>" onfocus="this.style.borderColor='<?= $pc ?>'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
<?php elseif ($_k === 'address'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px"><?= $_l ?><?= $_star ?></label>
                <textarea name="address" <?= $_ra ?> placeholder="<?= $_p ?>" rows="2" style="<?= $_lpIS ?>;resize:vertical" onfocus="this.style.borderColor='<?= $pc ?>'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
            </div>
<?php elseif ($_k === 'shipping_area'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:6px"><?= $_l ?><?= $_star ?></label>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <label style="flex:1;min-width:0;display:flex;align-items:center;gap:6px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:13px;transition:all .2s" class="lp-area-opt" onclick="lpAreaPick(this)">
                        <input type="radio" name="shipping_area" value="inside_dhaka" style="accent-color:<?= $pc ?>"> ঢাকা<br><strong style="color:<?= $pc ?>">৳<?= $ofDhaka ?></strong>
                    </label>
                    <label style="flex:1;min-width:0;display:flex;align-items:center;gap:6px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:13px;transition:all .2s" class="lp-area-opt" onclick="lpAreaPick(this)">
                        <input type="radio" name="shipping_area" value="dhaka_sub" style="accent-color:<?= $pc ?>"> ঢাকা উপশহর<br><strong style="color:<?= $pc ?>">৳<?= $ofSub ?></strong>
                    </label>
                    <label style="flex:1;min-width:0;display:flex;align-items:center;gap:6px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:13px;transition:all .2s" class="lp-area-opt" onclick="lpAreaPick(this)">
                        <input type="radio" name="shipping_area" value="outside_dhaka" checked style="accent-color:<?= $pc ?>"> বাংলাদেশ<br><strong style="color:<?= $pc ?>">৳<?= $ofOut ?></strong>
                    </label>
                </div>
            </div>
<?php elseif ($_k === 'notes'): ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px"><?= $_l ?><?= $_star ?></label>
                <input type="text" name="notes" <?= $_ra ?> placeholder="<?= $_p ?>" style="<?= $_lpIS ?>" onfocus="this.style.borderColor='<?= $pc ?>'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
<?php endif; endforeach; ?>
            <div id="lpFormTotal" style="display:none;background:#f8fafc;border-radius:10px;padding:12px 16px;margin-top:4px">
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:4px"><span>সাবটোটাল</span><span id="lpTotSub">৳0</span></div>
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:4px"><span>ডেলিভারি</span><span id="lpTotDel">৳<?= $ofOut ?></span></div>
                <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:<?= $sc ?>;border-top:1px solid #e5e7eb;padding-top:8px;margin-top:4px"><span>মোট</span><span id="lpTotAll">৳0</span></div>
            </div>
            <button type="submit" id="lpFormBtn" style="width:100%;padding:14px;border:none;border-radius:12px;font-size:16px;font-weight:800;color:#fff;background:<?= $ofBtnColor ?>;cursor:pointer;font-family:inherit;box-shadow:0 6px 20px <?= $ofBtnColor ?>40;transition:all .3s;-webkit-tap-highlight-color:transparent">
                <?= htmlspecialchars($ofBtnText) ?>
            </button>
            <p id="lpFormErr" style="display:none;color:#ef4444;font-size:12px;text-align:center;margin:0"></p>
        </div>
    </form>

    <div id="lpFormSuccess" style="display:none;text-align:center;background:#fff;border-radius:16px;padding:32px 24px;box-shadow:0 4px 24px rgba(0,0,0,.06)">
        <div style="width:64px;height:64px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
            <svg width="32" height="32" fill="none" stroke="#fff" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 style="font-size:22px;font-weight:800;color:#1a1a2e;margin:0 0 8px"><?= htmlspecialchars($ofSuccessTitle) ?></h3>
        <p style="color:#6b7280;font-size:14px;margin:0 0 8px"><?= htmlspecialchars($ofSuccessMsg) ?></p>
        <p id="lpFormOrderNum" style="font-weight:700;color:<?= $pc ?>;font-size:15px;margin:0"></p>
    </div>
</div>
</section>
<?php endif; ?>

<!-- LP Checkout Popup (for product click action = landing_popup) -->
<div id="lpCheckoutPopup" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:flex-end;justify-content:center" onclick="if(event.target===this)lpClosePopup()">
<div style="background:#fff;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;border-radius:20px 20px 0 0;padding:0;margin:0 auto" onclick="event.stopPropagation()">
    <div style="display:flex;align-items:center;justify-content:between;padding:16px 20px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:#fff;z-index:1;border-radius:20px 20px 0 0">
        <h3 style="font-size:18px;font-weight:800;flex:1;margin:0"><?= htmlspecialchars($ofTitle) ?></h3>
        <button type="button" onclick="lpClosePopup()" style="width:32px;height:32px;border:none;background:#f3f4f6;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280">✕</button>
    </div>
    <form id="lpPopupForm" onsubmit="return lpSubmitPopup(event)" style="padding:20px;display:flex;flex-direction:column;gap:12px">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="channel" value="landing_page">
        <input type="hidden" name="lp_page_id" value="<?= $pageId ?>">
        <div id="lpPopupProduct" style="display:none;background:<?= $pc ?>08;border-radius:12px;padding:12px;border:1px solid <?= $pc ?>15">
            <div style="display:flex;align-items:center;gap:12px">
                <img id="lpPopupProdImg" src="" style="width:48px;height:48px;border-radius:8px;object-fit:cover" onerror="this.style.display='none'">
                <div style="flex:1;min-width:0">
                    <div id="lpPopupProdName" style="font-weight:700;font-size:14px"></div>
                    <span id="lpPopupProdPrice" style="font-weight:800;color:<?= $pc ?>;font-size:15px"></span>
                </div>
                <div style="display:flex;align-items:center;gap:2px;border:1px solid #e5e7eb;border-radius:8px;padding:2px;background:#fff">
                    <button type="button" onclick="lpPopupQtyChange(-1)" style="width:28px;height:28px;border:none;background:#f1f5f9;border-radius:6px;font-size:15px;cursor:pointer;color:#6b7280;font-weight:700;display:flex;align-items:center;justify-content:center">−</button>
                    <span id="lpPopupQtyVal" style="width:28px;text-align:center;font-size:14px;font-weight:700">1</span>
                    <button type="button" onclick="lpPopupQtyChange(1)" style="width:28px;height:28px;border:none;background:#f1f5f9;border-radius:6px;font-size:15px;cursor:pointer;color:#6b7280;font-weight:700;display:flex;align-items:center;justify-content:center">+</button>
                </div>
            </div>
        </div>
        <input type="text" id="lpPName" name="name" required placeholder="আপনার পুরো নাম" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none">
        <input type="tel" id="lpPPhone" name="phone" required placeholder="মোবাইল নম্বর (01XXXXXXXXX)" pattern="01[0-9]{9}" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none">
        <textarea id="lpPAddr" name="address" required placeholder="সম্পূর্ণ ঠিকানা" rows="2" style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none;resize:vertical"></textarea>
<?php foreach ($_lpFormFields as $_cf):
    $_k = $_cf['key'] ?? '';
    if (in_array($_k, ['name','phone','address'])) continue; // already rendered above
    $_l = htmlspecialchars($_cf['label'] ?? '');
    $_p = htmlspecialchars($_cf['placeholder'] ?? '');
    $_req = !empty($_cf['required']);
    $_ra = $_req ? 'required' : '';
    $_pS = "width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none";
?>
<?php if ($_k === 'email'): ?>
        <input type="email" name="email" <?= $_ra ?> placeholder="<?= $_l ?>" style="<?= $_pS ?>">
<?php elseif ($_k === 'lp_upsells'): ?>
        <div id="lpPopupUpsellWrap" style="display:none">
            <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:6px"><span style="color:#f97316">🔥</span> <?= $_l ?></label>
            <div id="lpPopupUpsellList" style="display:flex;flex-direction:column;gap:6px"></div>
        </div>
<?php elseif ($_k === 'product_selector'): ?>
        <?php /* product_selector not needed in popup — product already selected */ ?>
<?php elseif ($_k === 'shipping_area'): ?>
        <div style="display:flex;gap:6px">
            <label style="flex:1;display:flex;align-items:center;gap:4px;padding:8px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:12px"><input type="radio" name="shipping_area" value="inside_dhaka" style="accent-color:<?= $pc ?>">ঢাকা ৳<?= $ofDhaka ?></label>
            <label style="flex:1;display:flex;align-items:center;gap:4px;padding:8px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:12px"><input type="radio" name="shipping_area" value="dhaka_sub" style="accent-color:<?= $pc ?>">উপশহর ৳<?= $ofSub ?></label>
            <label style="flex:1;display:flex;align-items:center;gap:4px;padding:8px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:12px"><input type="radio" name="shipping_area" value="outside_dhaka" checked style="accent-color:<?= $pc ?>">বাংলাদেশ ৳<?= $ofOut ?></label>
        </div>
<?php elseif ($_k === 'notes'): ?>
        <input type="text" name="notes" placeholder="<?= $_l ?>" style="<?= $_pS ?>">
<?php endif; endforeach; ?>
        <button type="submit" id="lpPopupBtn" style="width:100%;padding:14px;border:none;border-radius:12px;font-size:16px;font-weight:800;color:#fff;background:<?= $ofBtnColor ?>;cursor:pointer;font-family:inherit"><?= htmlspecialchars($ofBtnText) ?></button>
        <p id="lpPopupErr" style="display:none;color:#ef4444;font-size:12px;text-align:center;margin:0"></p>
    </form>
    <div id="lpPopupSuccess" style="display:none;text-align:center;padding:32px 24px">
        <div style="width:56px;height:56px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px"><svg width="28" height="28" fill="none" stroke="#fff" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
        <h3 style="font-size:20px;font-weight:800;margin:0 0 6px"><?= htmlspecialchars($ofSuccessTitle) ?></h3>
        <p style="color:#6b7280;font-size:14px;margin:0 0 6px"><?= htmlspecialchars($ofSuccessMsg) ?></p>
        <p id="lpPopupOrderNum" style="font-weight:700;color:<?= $pc ?>;font-size:14px;margin:0"></p>
    </div>
</div>
</div>

</div><!-- /.lp-wrap -->
<?php if ($isPreview): ?></div><!-- /.preview-frame --><?php endif; ?>

<?php if (!empty($wa['enabled']) && !empty($wa['number']) && getSetting('fab_enabled','0') !== '1'): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wa['number']) ?>" target="_blank" rel="noopener" class="lp-wa" style="position:fixed;bottom:80px;right:16px;width:52px;height:52px;background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:99;box-shadow:0 4px 16px rgba(37,211,102,.4);text-decoration:none;transition:transform .2s,opacity .4s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
<svg width="26" height="26" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
<?php endif; ?>

<?php if ($fc['enabled'] ?? true): ?>
<a href="javascript:void(0)" onclick="lpCheckout()" class="lp-fcta" id="lpFCta" style="background:<?= $fc['color'] ?? $pc ?>"><?= htmlspecialchars($fc['text'] ?? 'এখনই অর্ডার করুন') ?></a>
<?php endif; ?>

<script>
(function(){
    const LP_PID = <?= $pageId ?>;
    const LP_V = '<?= $variant ?>';
    const LP_API = '<?= SITE_URL ?>/api/landing-pages.php';
    const LP_P = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
    const LP_DEF = <?= $defaultProduct ?>;
    const LP_ISP = <?= $isPreview ? 'true' : 'false' ?>;
    const LP_PCA = '<?= $pca ?>';
    const LP_CM = '<?= $checkoutMode ?>';
    const LP_DEL = {inside_dhaka:<?= $ofDhaka ?? 70 ?>,dhaka_sub:<?= $ofSub ?? 100 ?>,outside_dhaka:<?= $ofOut ?? 130 ?>};
    const LP_UP = <?php 
        // LP-specific upsell products
        $_lpUp = $settings['lp_upsell_products'] ?? [];
        if (!empty($_lpUp)) {
            // Enrich from DB
            $_upEnriched = [];
            foreach ($_lpUp as $_u) {
                $_uid = intval($_u['id'] ?? 0);
                if (!$_uid) continue;
                $_uRow = $db->fetch("SELECT id, name, name_bn, featured_image, regular_price, sale_price, is_on_sale FROM products WHERE id = ? AND is_active = 1", [$_uid]);
                if ($_uRow) {
                    $_upEnriched[] = [
                        'id' => $_uRow['id'],
                        'name' => $_uRow['name_bn'] ?: $_uRow['name'],
                        'featured_image' => $_uRow['featured_image'] ?? '',
                        'regular_price' => floatval($_uRow['regular_price']),
                        'sale_price' => floatval($_uRow['sale_price'] ?? 0),
                    ];
                }
            }
            echo json_encode($_upEnriched, JSON_UNESCAPED_UNICODE);
        } else {
            echo '[]';
        }
    ?>;
    const LP_REDIR = <?= $redirectEnabled ? 'true' : 'false' ?>;
    const LP_REDIR_URL = '<?= htmlspecialchars($redirectUrl, ENT_QUOTES) ?>';
    
    let sid = ''; try { sid = localStorage.getItem('lps'+LP_PID) || ('s'+Math.random().toString(36).substr(2,12)); localStorage.setItem('lps'+LP_PID, sid); } catch(e){ sid = 's'+Math.random().toString(36).substr(2,12); }

    // ═══ LP ANALYTICS ═══
    function tk(t,d){if(LP_ISP)return;try{var f=new FormData();f.append('action','track');f.append('page_id',LP_PID);f.append('session_id',sid);f.append('variant',LP_V);f.append('event_type',t);f.append('event_data',JSON.stringify(d||{}));navigator.sendBeacon?navigator.sendBeacon(LP_API,f):fetch(LP_API,{method:'POST',body:f})}catch(e){}}
    tk('view');
    var mxS=0;window.addEventListener('scroll',function(){var p=Math.round((scrollY+innerHeight)/document.body.scrollHeight*100);if(p>mxS+10){mxS=p;tk('scroll',{depth:mxS})}},{passive:true});
    try{var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting)e.target._t=Date.now();else if(e.target._t)tk('section_view',{section_id:e.target.dataset.s,time:Math.round((Date.now()-e.target._t)/1000)})})},{threshold:.3});document.querySelectorAll('[data-s]').forEach(function(el){io.observe(el)})}catch(e){}
    document.addEventListener('click',function(e){tk('click',{x:((e.clientX/innerWidth)*100).toFixed(1),y:((e.pageY/document.body.scrollHeight)*100).toFixed(1),s:e.target.closest('[data-s]')?.dataset.s||''})});
    var _st=Date.now();window.addEventListener('beforeunload',function(){tk('time_spent',{sec:Math.round((Date.now()-_st)/1000)})});

    // ═══ FEATURE 4: ENSURE PRODUCT ID (temp product if needed) ═══
    function ensureProductId(idx, callback) {
        var p = LP_P[idx];
        if (!p) return callback(0);
        if (p.real_product_id) return callback(p.real_product_id);
        // No real product — create temp product via API
        var f = new FormData();
        f.append('action', 'create_temp_product');
        f.append('page_id', LP_PID);
        f.append('name', p.name || 'LP Product');
        f.append('price', p.price || 0);
        f.append('compare_price', p.compare_price || 0);
        f.append('image', p.image || '');
        f.append('description', p.description || '');
        fetch(LP_API, {method:'POST', body:f})
        .then(function(r){return r.json()})
        .then(function(d){
            if (d.success && d.product_id) {
                LP_P[idx].real_product_id = d.product_id;
                callback(d.product_id);
            } else {
                callback(0);
            }
        }).catch(function(){ callback(0); });
    }

    // ═══ INJECT LP FIELDS INTO CHECKOUT FORM ═══
    function injectLpFields() {
        var form = document.getElementById('checkout-form');
        if (!form) return;
        form.querySelectorAll('.lp-hidden-field').forEach(function(el){el.remove()});
        var ch = document.createElement('input');
        ch.type='hidden'; ch.name='channel'; ch.value='landing_page'; ch.className='lp-hidden-field';
        form.appendChild(ch);
        var lp = document.createElement('input');
        lp.type='hidden'; lp.name='lp_page_id'; lp.value=LP_PID; lp.className='lp-hidden-field';
        form.appendChild(lp);
    }

    // ═══ FEATURE 6: WATCH CHECKOUT SUCCESS → REDIRECT ═══
    var successDiv = document.getElementById('checkout-success');
    if (successDiv) {
        var obs = new MutationObserver(function(mutations){
            mutations.forEach(function(m){
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    if (!successDiv.classList.contains('hidden')) {
                        var orderNum = document.getElementById('success-order-number')?.textContent || '';
                        tk('order_complete', {order_number: orderNum});
                        // Update LP stats
                        try {
                            var f = new FormData();
                            f.append('action','lp_order_track');
                            f.append('page_id', LP_PID);
                            f.append('order_number', orderNum);
                            fetch(LP_API, {method:'POST', body:f});
                        } catch(e){}
                        // Redirect if enabled
                        if (LP_REDIR && LP_REDIR_URL) {
                            setTimeout(function(){
                                window.location.href = LP_REDIR_URL;
                            }, 2000);
                        }
                    }
                }
            });
        });
        obs.observe(successDiv, {attributes:true, attributeFilter:['class']});
    }

    // ═══ INLINE FORM + PRODUCT TRACKING ═══
    var _lpSelProduct = null;
    var _lpSelQty = 1;
    var _lpUpsells = []; // [{pid, price, qty}]
    var _lpPopupUpsells = [];
    var _lpHasSelector = document.querySelectorAll('#lpProdList .lp-prod-opt').length > 0;

    // Hide old single-product display if product selector is present
    if (_lpHasSelector) {
        var _oldPd = document.getElementById('lpFormProduct');
        if (_oldPd) _oldPd.style.display = 'none';
        // Auto-select default or first product from selector
        var defOpt = document.querySelector('#lpProdList .lp-prod-active');
        if (!defOpt) {
            defOpt = document.querySelector('#lpProdList .lp-prod-opt');
            if (defOpt) lpPickProduct(defOpt);
        }
        if (defOpt) {
            _lpSelProduct = parseInt(defOpt.dataset.pid) || null;
            _lpSelQty = parseInt(defOpt.querySelector('.lp-qty-val')?.textContent) || 1;
            var _initTot = document.getElementById('lpFormTotal');
            if (_initTot) _initTot.style.display = 'block';
            lpRecalcTotal();
            lpLoadUpsells(_lpSelProduct, 'inline');
        }
    }

    // ── Product Selector Pick ──
    window.lpPickProduct = function(el) {
        var radio = el.querySelector('input[type=radio]');
        if (radio) radio.checked = true;
        // Style all options
        document.querySelectorAll('#lpProdList .lp-prod-opt').forEach(function(o) {
            o.style.borderColor = '#e5e7eb';
            o.style.background = '#fff';
            o.classList.remove('lp-prod-active');
        });
        el.style.borderColor = '<?= $pc ?>';
        el.style.background = '<?= $pc ?>08';
        el.classList.add('lp-prod-active');
        _lpSelProduct = parseInt(el.dataset.pid) || null;
        _lpSelQty = parseInt(el.querySelector('.lp-qty-val')?.textContent) || 1;
        lpRecalcTotal();
        lpLoadUpsells(_lpSelProduct, 'inline');
    };

    // ── Quantity +/- (inline product selector) ──
    window.lpQtyChange = function(btn, delta) {
        var label = btn.closest('.lp-prod-opt');
        var valEl = label.querySelector('.lp-qty-val');
        var cur = parseInt(valEl.textContent) || 1;
        cur = Math.max(1, Math.min(10, cur + delta));
        valEl.textContent = cur;
        // Auto-select this product
        if (!label.classList.contains('lp-prod-active')) {
            lpPickProduct(label);
        } else {
            _lpSelQty = cur;
            lpRecalcTotal();
        }
    };

    // ── Popup Quantity ──
    window.lpPopupQtyChange = function(delta) {
        var valEl = document.getElementById('lpPopupQtyVal');
        if (!valEl) return;
        var cur = parseInt(valEl.textContent) || 1;
        cur = Math.max(1, Math.min(10, cur + delta));
        valEl.textContent = cur;
        _lpPopupQty = cur;
    };

    // ── Load Upsells ──
    function lpLoadUpsells(pid, target) {
        var wrapId = target === 'popup' ? 'lpPopupUpsellWrap' : 'lpUpsellWrap';
        var listId = target === 'popup' ? 'lpPopupUpsellList' : 'lpUpsellList';
        var wrap = document.getElementById(wrapId);
        var list = document.getElementById(listId);
        if (!wrap || !list) return;
        
        // Use LP-specific upsell products if configured
        if (LP_UP && LP_UP.length > 0) {
            var filtered = LP_UP.filter(function(u){ return u.id != pid; });
            _renderUpsells(filtered, wrap, list, target);
            return;
        }
        
        // Fallback: fetch auto-upsells from API (only for real product IDs)
        if (!pid || pid < 0) { wrap.style.display = 'none'; return; }
        
        fetch(SITE_URL + '/api/cart.php?action=get_upsells&product_ids=' + pid + '&limit=4')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success || !d.upsells || d.upsells.length === 0) {
                    wrap.style.display = 'none';
                    if (target === 'inline') { _lpUpsells = []; lpRecalcTotal(); }
                    else { _lpPopupUpsells = []; }
                    return;
                }
                _renderUpsells(d.upsells, wrap, list, target);
            })
            .catch(function() { wrap.style.display = 'none'; });
    }

    function _renderUpsells(items, wrap, list, target) {
        if (!items || items.length === 0) {
            wrap.style.display = 'none';
            if (target === 'inline') { _lpUpsells = []; lpRecalcTotal(); }
            else { _lpPopupUpsells = []; }
            return;
        }
        wrap.style.display = 'block';
        var html = '';
        items.forEach(function(u) {
            var price = parseFloat(u.price || ((u.sale_price && parseFloat(u.sale_price) < parseFloat(u.regular_price)) ? u.sale_price : u.regular_price) || 0);
            var img = u.image || (u.featured_image ? SITE_URL + '/uploads/products/' + u.featured_image.split('/').pop() : '') || u.image_url || '';
            html += '<label class="lp-upsell-item" data-upid="' + u.id + '" data-upprice="' + price + '" onclick="lpToggleUpsell(this,\'' + target + '\')" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .2s;background:#fff">';
            if (img) html += '<img src="' + img + '" style="width:40px;height:40px;border-radius:8px;object-fit:cover;flex-shrink:0" onerror="this.style.display=\'none\'">';
            else html += '<div style="width:40px;height:40px;background:#f1f5f9;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center"><i class="fas fa-box" style="color:#94a3b8;font-size:12px"></i></div>';
            html += '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:12px;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + (u.name_bn || u.name) + '</div><div style="font-weight:700;font-size:13px;color:#f97316">৳' + Math.round(price) + '</div></div>';
            html += '<div class="lp-up-check" style="width:24px;height:24px;border:1.5px solid #e5e7eb;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s"><i class="fas fa-plus" style="color:#cbd5e1;font-size:10px"></i></div>';
            html += '</label>';
        });
        list.innerHTML = html;
        if (target === 'inline') { _lpUpsells = []; lpRecalcTotal(); }
        else { _lpPopupUpsells = []; }
    }

    // ── Toggle Upsell Item ──
    window.lpToggleUpsell = function(el, target) {
        var pid = parseInt(el.dataset.upid);
        var price = parseFloat(el.dataset.upprice);
        var check = el.querySelector('.lp-up-check');
        var arr = target === 'popup' ? _lpPopupUpsells : _lpUpsells;
        var idx = -1;
        for (var i = 0; i < arr.length; i++) { if (arr[i].pid === pid) { idx = i; break; } }
        
        if (idx >= 0) {
            // Remove
            arr.splice(idx, 1);
            el.style.borderColor = '#e5e7eb';
            el.style.background = '#fff';
            if (check) check.innerHTML = '<i class="fas fa-plus" style="color:#cbd5e1;font-size:10px"></i>';
            check.style.borderColor = '#e5e7eb';
            check.style.background = '#fff';
        } else {
            // Add
            arr.push({pid: pid, price: price, qty: 1});
            el.style.borderColor = '#f97316';
            el.style.background = '#fff7ed';
            if (check) check.innerHTML = '<i class="fas fa-check" style="color:#fff;font-size:10px"></i>';
            check.style.borderColor = '#f97316';
            check.style.background = '#f97316';
        }
        if (target === 'inline') lpRecalcTotal();
    };

    // ── Recalculate Total (includes upsells) ──
    function lpRecalcTotal() {
        var form = document.getElementById('lpInlineForm');
        var area = form ? form.querySelector('input[name="shipping_area"]:checked') : null;
        var del = area ? (LP_DEL[area.value] || 130) : 130;
        
        // Main product subtotal - get price from selected DOM element
        var mainPrice = 0;
        if (_lpSelProduct) {
            var selEl = document.querySelector('#lpProdList .lp-prod-opt.lp-prod-active');
            if (selEl) {
                mainPrice = parseFloat(selEl.dataset.price) || 0;
            } else {
                // Fallback: search LP_P
                for (var i = 0; i < LP_P.length; i++) {
                    if (LP_P[i].real_product_id == _lpSelProduct) { mainPrice = LP_P[i].price || 0; break; }
                }
            }
        }
        var sub = mainPrice * (_lpSelQty || 1);
        
        // Upsell subtotal
        var upTotal = 0;
        for (var j = 0; j < _lpUpsells.length; j++) {
            upTotal += (_lpUpsells[j].price || 0) * (_lpUpsells[j].qty || 1);
        }
        
        var tot = sub + upTotal + del;
        var totEl = document.getElementById('lpFormTotal');
        if (totEl && (_lpHasSelector || sub > 0 || upTotal > 0)) totEl.style.display = 'block';
        var se = document.getElementById('lpTotSub'); if(se) se.textContent = '৳' + Math.round(sub + upTotal);
        var de = document.getElementById('lpTotDel'); if(de) de.textContent = '৳' + del;
        var te = document.getElementById('lpTotAll'); if(te) te.textContent = '৳' + Math.round(tot);
    }

    function lpUpdateFormProduct(productId, qty, target) {
        // target: 'inline' or 'popup'
        var prefix = target === 'popup' ? 'lpPopup' : 'lpForm';
        var el = document.getElementById(prefix + 'Product');
        var totEl = document.getElementById(prefix === 'lpForm' ? 'lpFormTotal' : null);
        if (!el) return;
        var pInfo = null;
        for (var i = 0; i < LP_P.length; i++) {
            if (LP_P[i].real_product_id == productId) { pInfo = LP_P[i]; break; }
        }
        if (pInfo) {
            el.style.display = 'block';
            if (totEl) totEl.style.display = 'block';
            var img = document.getElementById(prefix + 'ProdImg');
            if (img) { img.src = pInfo.image || ''; img.style.display = pInfo.image ? 'block' : 'none'; }
            var nm = document.getElementById(prefix + 'ProdName');
            if (nm) nm.textContent = pInfo.name || '';
            var pr = document.getElementById(prefix + 'ProdPrice');
            if (pr) pr.textContent = '৳' + (pInfo.price || 0);
            var qt = document.getElementById(prefix + 'ProdQty');
            if (qt) qt.textContent = qty > 1 ? ('×' + qty) : '';
            if (target === 'inline') lpUpdateTotal(pInfo.price || 0, qty || 1);
        } else {
            el.style.display = 'none';
            if (totEl) totEl.style.display = 'none';
        }
    }

    function lpUpdateTotal(price, qty) {
        // Backward compat: if product_selector is present, use lpRecalcTotal which includes upsells
        if (_lpHasSelector) { lpRecalcTotal(); return; }
        // Legacy path: no product selector, simple calc
        var form = document.getElementById('lpInlineForm');
        var area = form ? form.querySelector('input[name="shipping_area"]:checked') : null;
        var del = area ? (LP_DEL[area.value] || 130) : 130;
        var sub = price * (qty || 1);
        var upTotal = 0;
        for (var j = 0; j < _lpUpsells.length; j++) { upTotal += (_lpUpsells[j].price || 0); }
        var tot = sub + upTotal + del;
        var totEl = document.getElementById('lpFormTotal');
        if (totEl && (_lpHasSelector || sub > 0 || upTotal > 0)) totEl.style.display = 'block';
        var se = document.getElementById('lpTotSub'); if(se) se.textContent = '৳' + Math.round(sub + upTotal);
        var de = document.getElementById('lpTotDel'); if(de) de.textContent = '৳' + del;
        var te = document.getElementById('lpTotAll'); if(te) te.textContent = '৳' + Math.round(tot);
    }

    window.lpAreaPick = function(el) {
        // Only style area options within the same form
        var parentForm = el.closest('form') || el.closest('section');
        if (parentForm) {
            parentForm.querySelectorAll('.lp-area-opt').forEach(function(o){o.style.borderColor='#e5e7eb';o.style.background='transparent'});
        }
        el.style.borderColor = '<?= $pc ?>';
        el.style.background = '<?= $pc ?>08';
        lpRecalcTotal();
    };

    // ═══ LP POPUP (for product_click_action = landing_popup) ═══
    var _lpPopupProduct = null;
    var _lpPopupQty = 1;

    window.lpOpenPopup = function(productId, qty) {
        _lpPopupProduct = productId;
        _lpPopupQty = qty || 1;
        _lpPopupUpsells = [];
        var popup = document.getElementById('lpCheckoutPopup');
        if (!popup) return;
        popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        var qv = document.getElementById('lpPopupQtyVal');
        if (qv) qv.textContent = _lpPopupQty;
        lpUpdateFormProduct(productId, qty, 'popup');
        lpLoadUpsells(productId, 'popup');
    };

    window.lpClosePopup = function() {
        var popup = document.getElementById('lpCheckoutPopup');
        if (popup) popup.style.display = 'none';
        document.body.style.overflow = '';
    };

    // ═══ INLINE FORM SUBMIT ═══
    function lpDoSubmit(formId, pid, qty, errEl, btn, successId, orderNumId, upsells) {
        btn.disabled = true;
        btn.textContent = 'অর্ডার প্রসেস হচ্ছে...';
        
        function cartAdd(productId, productQty, clearFirst) {
            return fetch(SITE_URL+'/api/cart.php',{
                method:'POST',headers:{'Content-Type':'application/json'},
                body:JSON.stringify({action:'add',product_id:productId,quantity:productQty||1,clear_first:!!clearFirst})
            }).then(function(r){return r.json()});
        }

        // Step 1: Add main product (clear cart first)
        var chain = pid ? cartAdd(pid, qty, true) : Promise.resolve({success:true});
        
        // Step 2: Add each upsell product
        var ups = upsells || [];
        ups.forEach(function(u) {
            chain = chain.then(function() {
                return cartAdd(u.pid, u.qty || 1, false);
            });
        });

        // Step 3: Submit order
        chain.then(function(){
            var formEl = document.getElementById(formId);
            var formData = new FormData(formEl);
            return fetch(SITE_URL+'/api/order.php',{
                method:'POST',
                body: formData
            });
        }).then(function(r){return r.text()}).then(function(text){
            var d;
            try { d = JSON.parse(text); } catch(e) {
                console.error('Order API non-JSON:', text.substring(0,500));
                throw new Error('Server error');
            }
            if(d.success){
                tk('order_complete',{order_number:d.order_number||''});
                var form = document.getElementById(formId);
                if(form) form.style.display='none';
                var suc = document.getElementById(successId);
                if(suc) suc.style.display='block';
                var on = document.getElementById(orderNumId);
                if(on && d.order_number) on.textContent='অর্ডার নম্বর: #'+d.order_number;
                if(LP_REDIR && LP_REDIR_URL){setTimeout(function(){window.location.href=LP_REDIR_URL},2000);}
            } else {
                if(errEl){errEl.textContent=d.message||'অর্ডার ব্যর্থ হয়েছে';errEl.style.display='block';}
                btn.disabled=false;
                btn.textContent='<?= htmlspecialchars($ofBtnText) ?>';
            }
        }).catch(function(e){
            if(errEl){errEl.textContent='সার্ভার এরর, আবার চেষ্টা করুন';errEl.style.display='block';}
            btn.disabled=false;
            btn.textContent='<?= htmlspecialchars($ofBtnText) ?>';
        });
    }

    function lpValidate(name, phone, addr, errEl) {
        if (!name || !phone || !addr) {
            if(errEl){errEl.textContent='সকল ফিল্ড পূরণ করুন';errEl.style.display='block';}
            return false;
        }
        if (!/^01[0-9]{9}$/.test(phone)) {
            if(errEl){errEl.textContent='সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)';errEl.style.display='block';}
            return false;
        }
        if(errEl) errEl.style.display='none';
        return true;
    }

    function lpResolveProduct() {
        var pid = _lpSelProduct;
        if (!pid && LP_DEF >= 0 && LP_P[LP_DEF]) pid = LP_P[LP_DEF].real_product_id;
        if (!pid) { for (var i = 0; i < LP_P.length; i++) { if (LP_P[i].real_product_id) { pid = LP_P[i].real_product_id; break; } } }
        return pid;
    }

    window.lpSubmitInline = function(e) {
        e.preventDefault();
        var errEl = document.getElementById('lpFormErr');
        var btn = document.getElementById('lpFormBtn');
        var formEl = document.getElementById('lpInlineForm');
        var name = (formEl.querySelector('[name="name"]') || {}).value || '';
        var phone = (formEl.querySelector('[name="phone"]') || {}).value || '';
        var addr = (formEl.querySelector('[name="address"]') || {}).value || '';
        if (!lpValidate(name.trim(), phone.trim(), addr.trim(), errEl)) return false;
        // Use product from selector if present
        var pid = _lpHasSelector ? _lpSelProduct : lpResolveProduct();
        var qty = _lpHasSelector ? _lpSelQty : (_lpSelQty || 1);
        if (_lpHasSelector && !pid) {
            if(errEl){errEl.textContent='পণ্য নির্বাচন করুন';errEl.style.display='block';}
            return false;
        }
        // If pid is negative, it's an unlinked product — resolve via ensureProductId first
        if (pid < 0) {
            var idx = Math.abs(pid) - 1;
            btn.disabled = true;
            btn.textContent = 'পণ্য যাচাই হচ্ছে...';
            ensureProductId(idx, function(realPid) {
                if (!realPid) {
                    if(errEl){errEl.textContent='পণ্য তৈরি ব্যর্থ, আবার চেষ্টা করুন';errEl.style.display='block';}
                    btn.disabled = false;
                    btn.textContent = '<?= htmlspecialchars($ofBtnText) ?>';
                    return;
                }
                _lpSelProduct = realPid;
                lpDoSubmit('lpInlineForm', realPid, qty, errEl, btn, 'lpFormSuccess', 'lpFormOrderNum', _lpUpsells);
            });
            return false;
        }
        lpDoSubmit('lpInlineForm', pid, qty, errEl, btn, 'lpFormSuccess', 'lpFormOrderNum', _lpUpsells);
        return false;
    };

    window.lpSubmitPopup = function(e) {
        e.preventDefault();
        var errEl = document.getElementById('lpPopupErr');
        var btn = document.getElementById('lpPopupBtn');
        var formEl = document.getElementById('lpPopupForm');
        var name = (formEl.querySelector('[name="name"]') || {}).value || '';
        var phone = (formEl.querySelector('[name="phone"]') || {}).value || '';
        var addr = (formEl.querySelector('[name="address"]') || {}).value || '';
        if (!lpValidate(name.trim(), phone.trim(), addr.trim(), errEl)) return false;
        lpDoSubmit('lpPopupForm', _lpPopupProduct, _lpPopupQty, errEl, btn, 'lpPopupSuccess', 'lpPopupOrderNum', _lpPopupUpsells);
        return false;
    };

    // ═══ PRODUCT CLICK — USES PCA SETTING ONLY ═══
    window.lpProductClick = function(idx, realProductId) {
        tk('product_click', {i: idx, n: LP_P[idx]?.name || ''});

        function openSitePopup(pid) {
            if (!pid) { document.getElementById('order')?.scrollIntoView({behavior:'smooth'}); return; }
            fetch(SITE_URL + '/api/cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action:'add', product_id: pid, quantity: 1, clear_first: true})
            }).then(function(r){return r.json()}).then(function(d){
                if (d.success) document.querySelectorAll('.cart-count').forEach(function(el){el.textContent = d.cart_count});
                if (typeof openCheckoutPopup === 'function') {
                    openCheckoutPopup();
                    setTimeout(injectLpFields, 300);
                    setTimeout(injectLpFields, 800);
                } else {
                    window.location.href = SITE_URL + '/cart';
                }
            });
        }

        function openLpPopup(pid) {
            if (!pid) { document.getElementById('order')?.scrollIntoView({behavior:'smooth'}); return; }
            lpOpenPopup(pid, 1);
        }

        function scrollToForm(pid) {
            if (pid) {
                _lpSelProduct = pid;
                _lpSelQty = 1;
                if (_lpHasSelector) {
                    // Highlight matching product in selector
                    var match = document.querySelector('#lpProdList .lp-prod-opt[data-pid="'+pid+'"]');
                    if (match) lpPickProduct(match);
                    else lpRecalcTotal();
                } else {
                    lpUpdateFormProduct(pid, 1, 'inline');
                }
            }
            document.getElementById('order')?.scrollIntoView({behavior:'smooth'});
        }

        switch (LP_PCA) {
            case 'regular_checkout':
                if (realProductId > 0) { openSitePopup(realProductId); }
                else { ensureProductId(idx, function(pid){ openSitePopup(pid); }); }
                break;
            case 'landing_popup':
                if (realProductId > 0) { openLpPopup(realProductId); }
                else { ensureProductId(idx, function(pid){ openLpPopup(pid); }); }
                break;
            case 'scroll_to_order':
                if (realProductId > 0) scrollToForm(realProductId);
                else ensureProductId(idx, function(pid){ scrollToForm(pid); });
                break;
            case 'product_link':
                var link = LP_P[idx]?.product_link;
                if (link) window.location.href = link;
                else scrollToForm(realProductId);
                break;
            default:
                if (realProductId > 0) { openLpPopup(realProductId); }
                else { ensureProductId(idx, function(pid){ openLpPopup(pid); }); }
                break;
        }
    };

    // ═══ MAIN CTA / FLOATING CTA — ALWAYS SCROLL TO INLINE FORM ═══
    window.lpCheckout = function() {
        tk('cta_click', {action: 'checkout'});
        if (_lpHasSelector) {
            // Product selector handles product display; just scroll
            if (!_lpSelProduct) {
                var first = document.querySelector('#lpProdList .lp-prod-opt');
                if (first) lpPickProduct(first);
            }
        } else {
            // Legacy: select default product for the old single-product display
            var pid = null;
            if (LP_DEF >= 0 && LP_P[LP_DEF]) {
                pid = LP_P[LP_DEF].real_product_id;
                if (!pid) { ensureProductId(LP_DEF, function(p){ _lpSelProduct=p; _lpSelQty=1; lpUpdateFormProduct(p,1,'inline'); }); }
            }
            if (!pid) { for (var i=0;i<LP_P.length;i++){if(LP_P[i].real_product_id){pid=LP_P[i].real_product_id;break;}} }
            if (pid) { _lpSelProduct=pid; _lpSelQty=1; lpUpdateFormProduct(pid,1,'inline'); }
        }
        document.getElementById('order')?.scrollIntoView({behavior:'smooth'});
    };

    // ═══ FLOATING CTA ═══
    var fcBtn = document.getElementById('lpFCta');
    if (fcBtn) {
        var orderSec = document.getElementById('order');
        var fcVis = false;
        window.addEventListener('scroll', function(){
            var show = scrollY > 400;
            var hide = false;
            if (orderSec) { var r = orderSec.getBoundingClientRect(); hide = r.top < window.innerHeight && r.bottom > 0; }
            var shouldShow = show && !hide;
            if (shouldShow !== fcVis) { fcVis = shouldShow; fcBtn.classList.toggle('vis', fcVis); }
        }, {passive:true});
    }

    // ═══ FEATURE 1: MOBILE CAROUSEL — INFINITE AUTO-SLIDE ═══
    function initCarousels() {
        var isMobile = window.innerWidth < 768;
        document.querySelectorAll('.mcr').forEach(function(track){
            if (!isMobile) {
                // Desktop: revert to grid
                track.style.cssText = '';
                track._autoSlide && clearInterval(track._autoSlide);
                return;
            }
            var speed = parseInt(track.dataset.mcrSpeed) || 3000;
            var children = Array.from(track.children);
            var count = children.length;
            if (count < 2) return;

            // Build dot indicators
            var dotsEl = track.nextElementSibling;
            if (dotsEl && dotsEl.classList.contains('mcr-dots')) {
                dotsEl.innerHTML = '';
                for (var d = 0; d < count; d++) {
                    var dot = document.createElement('span');
                    dot.className = 'mcr-dot' + (d === 0 ? ' on' : '');
                    dot.dataset.i = d;
                    dotsEl.appendChild(dot);
                }
            }

            var curIdx = 0;
            function updateDots() {
                if (!dotsEl) return;
                dotsEl.querySelectorAll('.mcr-dot').forEach(function(d,i){
                    d.classList.toggle('on', i === curIdx);
                });
            }

            function scrollToIdx(i) {
                curIdx = ((i % count) + count) % count;
                var child = track.children[curIdx];
                if (child) {
                    track.scrollTo({left: child.offsetLeft - track.offsetLeft, behavior: 'smooth'});
                }
                updateDots();
            }

            // Auto-slide infinite loop
            track._autoSlide && clearInterval(track._autoSlide);
            track._autoSlide = setInterval(function(){ scrollToIdx(curIdx + 1); }, speed);

            // Pause on touch
            var touching = false;
            track.addEventListener('touchstart', function(){ touching = true; clearInterval(track._autoSlide); }, {passive:true});
            track.addEventListener('touchend', function(){
                touching = false;
                // Detect which slide is closest after swipe
                var closest = 0, minDist = Infinity;
                for (var c = 0; c < track.children.length; c++) {
                    var dist = Math.abs(track.children[c].offsetLeft - track.offsetLeft - track.scrollLeft);
                    if (dist < minDist) { minDist = dist; closest = c; }
                }
                curIdx = closest;
                updateDots();
                track._autoSlide = setInterval(function(){ scrollToIdx(curIdx + 1); }, speed);
            }, {passive:true});

            // Dot click
            if (dotsEl) {
                dotsEl.addEventListener('click', function(e){
                    var dot = e.target.closest('.mcr-dot');
                    if (dot) scrollToIdx(parseInt(dot.dataset.i));
                });
            }
        });
    }
    initCarousels();
    window.addEventListener('resize', function(){ clearTimeout(window._mcrResize); window._mcrResize = setTimeout(initCarousels, 200); });

    // ═══ COUNTDOWN TIMERS ═══
    document.querySelectorAll('.cd-row').forEach(function(el){
        var end=new Date(el.dataset.end).getTime(), id=el.closest('[data-s]')?.dataset.s||'';
        setInterval(function(){
            var d=Math.max(0,end-Date.now()),z=function(n){return String(n).padStart(2,'0')};
            var a=document.getElementById('cdd'+id);if(a)a.textContent=z(Math.floor(d/864e5));
            var b=document.getElementById('cdh'+id);if(b)b.textContent=z(Math.floor(d%864e5/36e5));
            var c=document.getElementById('cdm'+id);if(c)c.textContent=z(Math.floor(d%36e5/6e4));
            var e=document.getElementById('cds'+id);if(e)e.textContent=z(Math.floor(d%6e4/1e3));
        },1000);
    });

    // ═══ BEFORE/AFTER SLIDERS ═══
    document.querySelectorAll('.ba-sl').forEach(function(sl){
        var dr=false,ct=sl.parentElement,bf=ct.querySelector('.ba-bf');
        function mv(x){var r=ct.getBoundingClientRect();var p=Math.max(0,Math.min(100,((x-r.left)/r.width)*100));sl.style.left=p+'%';if(bf)bf.style.clipPath='inset(0 '+(100-p)+'% 0 0)'}
        sl.addEventListener('mousedown',function(){dr=true});
        sl.addEventListener('touchstart',function(){dr=true},{passive:true});
        document.addEventListener('mousemove',function(e){if(dr)mv(e.clientX)});
        document.addEventListener('touchmove',function(e){if(dr)mv(e.touches[0].clientX)},{passive:true});
        document.addEventListener('mouseup',function(){dr=false});
        document.addEventListener('touchend',function(){dr=false});
    });

    // ═══ AUTO-ADD DEFAULT PRODUCT ON PAGE LOAD ═══
    // Only auto-add to cart when there's no inline form (hidden mode)
    if (LP_CM === 'hidden' && LP_DEF >= 0 && LP_P[LP_DEF]) {
        var defPid = LP_P[LP_DEF].real_product_id;
        if (defPid) {
            fetch(SITE_URL + '/api/cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action:'add', product_id: defPid, quantity: 1})
            }).then(function(r){return r.json()}).then(function(d){
                if (d.success) document.querySelectorAll('.cart-count').forEach(function(el){el.textContent = d.cart_count});
            }).catch(function(){});
        } else {
            // Create temp product first, then add to cart
            ensureProductId(LP_DEF, function(pid){
                if (pid) {
                    fetch(SITE_URL + '/api/cart.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({action:'add', product_id: pid, quantity: 1})
                    }).then(function(r){return r.json()}).then(function(d){
                        if (d.success) document.querySelectorAll('.cart-count').forEach(function(el){el.textContent = d.cart_count});
                    }).catch(function(){});
                }
            });
        }
    }

    // Auto-populate inline form with default product (landing + regular modes)
    if (LP_CM !== 'hidden' && LP_DEF >= 0 && LP_P[LP_DEF]) {
        if (_lpHasSelector) {
            // Product selector auto-init already happened above; just ensure total visible
            if (_lpSelProduct) {
                var totEl = document.getElementById('lpFormTotal');
                if (totEl) totEl.style.display = 'block';
            }
        } else {
            _lpSelProduct = LP_P[LP_DEF].real_product_id || null;
            _lpSelQty = 1;
            if (_lpSelProduct) lpUpdateFormProduct(_lpSelProduct, 1, 'inline');
        }
    }
})();
</script>

<?php
// Track LP view in analytics table
if (!$isPreview) {
    try { $db->query("UPDATE landing_pages SET views = views + 1 WHERE id = ?", [$pageId]); } catch (\Throwable $e) {}
}

// Include site footer (checkout popup, cart slide, all JS, footer HTML)
if ($showFooter !== false) {
    include ROOT_PATH . 'includes/footer.php';
} elseif ($pca === 'regular_checkout') {
    // Footer hidden but regular_checkout PCA needs site popup JS — include with hidden visuals
    echo '<style>footer,.site-footer,.footer-wrap{display:none!important}</style>';
    include ROOT_PATH . 'includes/footer.php';
} else {
    // Minimal close without footer HTML
    echo '</body></html>';
}
?>
