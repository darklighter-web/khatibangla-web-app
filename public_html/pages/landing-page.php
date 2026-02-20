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
include ROOT_PATH . 'includes/header.php';
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
.lp-wrap h1,.lp-wrap h2,.lp-wrap h3,.lp-wrap h4{font-family:'<?= $fH ?>','Noto Sans Bengali',serif;line-height:1.3}
.lp-wrap img{max-width:100%;height:auto;display:block}
.ct{max-width:1100px;margin:0 auto;padding:0 16px}
.lp-btn{display:inline-block;background:<?= $pc ?>;color:#fff;padding:14px 32px;border-radius:12px;font-weight:700;font-size:clamp(14px,3.5vw,16px);text-decoration:none;border:none;cursor:pointer;transition:all .2s;font-family:inherit;-webkit-tap-highlight-color:transparent;text-align:center}
.lp-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px <?= $pc ?>40;filter:brightness(1.1)}
.lp-btn:active{transform:translateY(0)}

/* HERO */
.hero-wrap{display:flex;flex-direction:column;gap:24px;align-items:center}
.hero-txt{order:2;text-align:center}.hero-img{order:1;width:100%}
.hero-split .hero-txt{text-align:left}
.hero-badge{display:inline-block;padding:6px 16px;border-radius:50px;font-size:12px;font-weight:700;margin-bottom:12px}
.lp-wrap .hero-h{font-size:clamp(24px,6vw,52px);font-weight:900;margin-bottom:12px;letter-spacing:-.5px}
.hero-sub{font-size:clamp(14px,3.5vw,19px);opacity:.85;margin-bottom:24px}
.hero-pic{border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12)}
.hero-pic img{width:100%;object-fit:cover}

/* TRUST */
.trust{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:16px 0}
.trust-item{display:flex;align-items:center;gap:8px;justify-content:center;padding:10px 8px}
.trust-icon{font-size:24px;flex-shrink:0}.trust-text{font-weight:700;font-size:clamp(11px,2.5vw,14px)}

/* PRODUCTS */
.pgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.pcard{background:#fff;border-radius:14px;overflow:hidden;transition:all .3s;cursor:pointer;position:relative;border:1px solid rgba(0,0,0,.06);-webkit-tap-highlight-color:transparent;display:flex;flex-direction:column}
.pcard:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(0,0,0,.1)}
.pcard:active{transform:translateY(0)}
.pimg{aspect-ratio:1;overflow:hidden;background:#f1f5f9;position:relative}
.pimg img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pcard:hover .pimg img{transform:scale(1.04)}
.pbadge{position:absolute;top:8px;left:8px;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;z-index:2}
.pinfo{padding:12px;flex:1;display:flex;flex-direction:column}
.pname{font-weight:700;font-size:clamp(13px,3vw,16px);margin-bottom:4px;line-height:1.3}
.pdesc{font-size:12px;opacity:.7;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pprice{font-size:clamp(18px,4vw,22px);font-weight:900}
.pcomp{font-size:clamp(12px,2.5vw,14px);text-decoration:line-through;opacity:.5;margin-left:6px}
.pcta{width:100%;padding:10px;border:none;border-radius:10px;font-weight:700;font-size:clamp(12px,2.8vw,14px);cursor:pointer;transition:all .2s;margin-top:auto;font-family:inherit;-webkit-tap-highlight-color:transparent}
.pcta:hover{filter:brightness(1.1)}

/* FEATURES */
.fgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.fcard{text-align:center;padding:20px 14px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(0,0,0,.04)}
.fcard-icon{font-size:32px;margin-bottom:10px;display:block}
.fcard h3{font-weight:700;font-size:clamp(14px,3vw,17px);margin-bottom:4px}
.fcard p{font-size:clamp(12px,2.5vw,14px);opacity:.75}

/* TESTIMONIALS */
.tgrid{display:grid;grid-template-columns:1fr;gap:12px}
.tcard{padding:20px;border-radius:14px;background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1)}
.tstar{color:#fbbf24;font-size:14px;margin-bottom:8px}
.ttext{font-size:clamp(13px,3vw,15px);font-style:italic;line-height:1.7;margin-bottom:12px}
.tname{font-weight:700;font-size:13px}.tloc{font-size:11px;opacity:.6}

/* FAQ */
.faqbox{border:1px solid rgba(0,0,0,.08);border-radius:12px;margin-bottom:8px;overflow:hidden}
.faqq{padding:14px 16px;font-weight:700;font-size:clamp(13px,3vw,15px);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px;-webkit-tap-highlight-color:transparent}
.faqq:hover{background:rgba(0,0,0,.03)}
.faqa{padding:0 16px;max-height:0;overflow:hidden;transition:all .3s;font-size:clamp(13px,2.8vw,14px);line-height:1.8}
.faqbox.on .faqa{padding:0 16px 14px;max-height:500px}
.faqbox.on .faqarr{transform:rotate(180deg)}
.faqarr{transition:transform .3s;flex-shrink:0}

/* COUNTDOWN */
.cd-row{display:flex;gap:8px;justify-content:center;margin:16px 0}
.cd-box{background:rgba(0,0,0,.2);backdrop-filter:blur(10px);border-radius:10px;padding:10px 14px;min-width:60px;text-align:center}
.cd-num{font-size:clamp(24px,6vw,32px);font-weight:900;line-height:1}
.cd-lbl{font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-top:3px}

/* VIDEO */
.vid{position:relative;aspect-ratio:16/9;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12);cursor:pointer}
.vid iframe{width:100%;height:100%;border:0}
.vid-poster{width:100%;height:100%;object-fit:cover}
.vid-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)}
.vid-play:hover{background:rgba(0,0,0,.5)}
.vid-btn{width:60px;height:60px;background:<?= $pc ?>;border-radius:50%;display:flex;align-items:center;justify-content:center}
.vid-btn::after{content:'';border:solid #fff;border-width:0 3px 3px 0;display:inline-block;padding:7px;transform:rotate(-45deg);margin-left:3px}

/* BEFORE/AFTER */
.ba{position:relative;border-radius:14px;overflow:hidden;aspect-ratio:16/9;touch-action:none}
.ba img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0}
.ba-bf{clip-path:inset(0 50% 0 0)}
.ba-sl{position:absolute;top:0;bottom:0;left:50%;width:4px;background:#fff;cursor:ew-resize;z-index:10}
.ba-sl::after{content:'⇔';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.ba-lb{position:absolute;top:12px;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;z-index:5}

/* ORDER CTA SECTION */
.lp-order-cta{text-align:center;padding:48px 16px;background:linear-gradient(135deg,<?= $pc ?>10 0%,<?= $pc ?>05 100%);border-top:2px solid <?= $pc ?>20}
.lp-order-cta h2{font-size:clamp(22px,5vw,34px);font-weight:900;margin-bottom:8px}
.lp-order-cta p{font-size:clamp(13px,3vw,16px);opacity:.7;margin-bottom:24px;max-width:500px;margin-left:auto;margin-right:auto}
.lp-order-btn{display:inline-block;padding:16px 48px;border-radius:14px;font-weight:800;font-size:clamp(16px,4vw,20px);border:none;cursor:pointer;box-shadow:0 8px 28px <?= $pc ?>40;transition:all .3s;font-family:inherit;-webkit-tap-highlight-color:transparent;text-decoration:none;color:#fff;background:<?= $pc ?>}
.lp-order-btn:hover{transform:translateY(-3px);box-shadow:0 12px 36px <?= $pc ?>50}
.lp-order-btn:active{transform:translateY(0)}

/* FLOATING CTA */
.lp-fcta{position:fixed;bottom:16px;left:16px;right:16px;z-index:100;padding:14px;border-radius:14px;font-weight:800;font-size:15px;cursor:pointer;border:none;box-shadow:0 8px 28px rgba(0,0,0,.25);transition:opacity .4s,transform .4s;font-family:inherit;text-decoration:none;text-align:center;-webkit-tap-highlight-color:transparent;color:#fff;opacity:0;pointer-events:none;transform:translateY(20px)}
.lp-fcta.vis{opacity:1;pointer-events:auto;transform:translateY(0)}

/* SECTION BASE */
.sec{padding:40px 0}
.sec-h{font-size:clamp(22px,5vw,36px);font-weight:800;text-align:center;margin-bottom:6px}
.sec-sub{text-align:center;font-size:clamp(13px,3vw,16px);opacity:.7;margin-bottom:28px;max-width:560px;margin-left:auto;margin-right:auto}

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
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); ?>
<div class="ct"><div class="trust<?= $isMcr ? ' mcr mcr-trust' : '' ?>"<?= $cols > 2 && !$isMcr ? " style=\"grid-template-columns:repeat({$cols},1fr)\"" : '' ?> <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?>>
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
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); ?>
<div class="pgrid c<?= $cols ?><?= $isMcr ? ' mcr mcr-products' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?>>
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
<div><span class="pprice" style="color:<?= $ac ?>">৳<?= number_format($p['price'] ?? 0) ?></span><?php if ($hd): ?><span class="pcomp">৳<?= number_format($p['compare_price']) ?></span><?php endif; ?></div>
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
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); ?>
<div class="fgrid c<?= $cols ?><?= $isMcr ? ' mcr mcr-features' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?> style="margin-top:24px">
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
<?php $isMcr = !empty($st['mobile_carousel']); $mcrSpeed = intval($st['carousel_speed'] ?? 3000); ?>
<div class="tgrid c<?= $cols ?><?= $isMcr ? ' mcr mcr-testimonials' : '' ?>" <?= $isMcr ? "data-mcr-speed=\"{$mcrSpeed}\"" : '' ?> style="margin-top:24px">
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

<!-- ═══ ORDER CTA SECTION ═══ -->
<section class="lp-order-cta" id="order">
    <h2 style="color:<?= $sc ?>">এখনই অর্ডার করুন</h2>
    <p>ক্যাশ অন ডেলিভারিতে অর্ডার করুন — সারা বাংলাদেশে ডেলিভারি</p>
    <button class="lp-order-btn" onclick="lpCheckout()">🛒 অর্ডার করুন</button>
</section>

</div><!-- /.lp-wrap -->
<?php if ($isPreview): ?></div><!-- /.preview-frame --><?php endif; ?>

<?php if (!empty($wa['enabled']) && !empty($wa['number'])): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wa['number']) ?>" target="_blank" rel="noopener" style="position:fixed;bottom:80px;right:16px;width:52px;height:52px;background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:99;box-shadow:0 4px 16px rgba(37,211,102,.4);text-decoration:none;transition:transform .2s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
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

    // ═══ FEATURE 3: CLEAR CART + ADD PRODUCT → OPEN CHECKOUT ═══
    function lpOpenCheckout(productId, qty) {
        if (typeof openCheckoutPopup === 'function') {
            // clear_first is handled by openCheckoutPopup → cart API
            openCheckoutPopup(productId || undefined, qty || 1);
            setTimeout(injectLpFields, 300);
            setTimeout(injectLpFields, 800);
        } else {
            if (productId) {
                fetch(SITE_URL + '/api/cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action:'add', product_id: productId, quantity: qty||1, clear_first: true})
                }).then(function(){ window.location.href = SITE_URL + '/cart'; });
            } else {
                window.location.href = SITE_URL + '/cart';
            }
        }
    }

    // ═══ FEATURE 2 & 3: PRODUCT CLICK — RESPECTS PCA SETTING ═══
    window.lpProductClick = function(idx, realProductId) {
        tk('product_click', {i: idx, n: LP_P[idx]?.name || ''});

        // FEATURE 3: Clear cart first so default product is removed, then add clicked product
        function addAndOpen(pid) {
            if (!pid) { document.getElementById('order')?.scrollIntoView({behavior:'smooth'}); return; }
            // Clear cart first, then add the clicked product
            fetch(SITE_URL + '/api/cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action:'add', product_id: pid, quantity: 1, clear_first: true})
            }).then(function(r){return r.json()}).then(function(d){
                if (d.success) document.querySelectorAll('.cart-count').forEach(function(el){el.textContent = d.cart_count});
                if (typeof openCheckoutPopup === 'function') {
                    // Open checkout WITHOUT adding again (already in cart)
                    openCheckoutPopup();
                    setTimeout(injectLpFields, 300);
                    setTimeout(injectLpFields, 800);
                } else {
                    window.location.href = SITE_URL + '/cart';
                }
            });
        }

        // FEATURE 2: Handle product click action modes
        switch (LP_PCA) {
            case 'regular_checkout':
                // Use real checkout — ensure product exists first
                if (realProductId > 0) {
                    addAndOpen(realProductId);
                } else {
                    // FEATURE 4: Create temp product if needed
                    ensureProductId(idx, function(pid){ addAndOpen(pid); });
                }
                break;
            case 'scroll_to_order':
                document.getElementById('order')?.scrollIntoView({behavior:'smooth'});
                break;
            case 'product_link':
                var link = LP_P[idx]?.product_link;
                if (link) window.location.href = link;
                else document.getElementById('order')?.scrollIntoView({behavior:'smooth'});
                break;
            case 'landing_popup':
            default:
                // Same as regular_checkout (since we use real checkout now)
                if (realProductId > 0) {
                    addAndOpen(realProductId);
                } else {
                    ensureProductId(idx, function(pid){ addAndOpen(pid); });
                }
                break;
        }
    };

    // ═══ MAIN ORDER CTA BUTTON ═══
    window.lpCheckout = function() {
        tk('cta_click', {action: 'checkout'});
        if (LP_DEF >= 0 && LP_P[LP_DEF]) {
            var defPid = LP_P[LP_DEF].real_product_id;
            if (defPid) {
                lpOpenCheckout(defPid, 1);
            } else {
                ensureProductId(LP_DEF, function(pid){ lpOpenCheckout(pid, 1); });
            }
        } else {
            // Find first product with real_product_id
            var firstReal = null;
            for (var i = 0; i < LP_P.length; i++) {
                if (LP_P[i].real_product_id) { firstReal = LP_P[i].real_product_id; break; }
            }
            if (firstReal) {
                lpOpenCheckout(firstReal, 1);
            } else if (LP_P.length > 0) {
                ensureProductId(0, function(pid){ lpOpenCheckout(pid, 1); });
            } else {
                lpOpenCheckout(null, null);
            }
        }
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
    if (LP_DEF >= 0 && LP_P[LP_DEF]) {
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
})();
</script>

<?php
// Track LP view in analytics table
if (!$isPreview) {
    try { $db->query("UPDATE landing_pages SET views = views + 1 WHERE id = ?", [$pageId]); } catch (\Throwable $e) {}
}

// Include site footer (checkout popup, cart slide, all JS, footer HTML)
include ROOT_PATH . 'includes/footer.php';
?>
