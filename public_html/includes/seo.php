<?php
/**
 * SEO Helper - khatibangla.com
 * 
 * Generates: canonical URLs, OG tags, Twitter cards, JSON-LD structured data
 * 
 * Usage: Set $seo array before including header.php
 * $seo = ['type'=>'product', 'title'=>'...', 'description'=>'...', ...]
 */

// ── Build Canonical URL ──
function seoCanonical(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'khatibangla.com';
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return rtrim($scheme . '://' . $host . $path, '/');
}

// ── Render all SEO <head> tags ──
function seoRenderHead(array $seo = []): string {
    $siteName = getSetting('site_name', 'KhatiBangla');
    $siteUrl = rtrim(SITE_URL, '/');
    $defaultOgImage = getSetting('og_image', '');
    $metaKeywords = getSetting('meta_keywords', '');
    $gaId = getSetting('google_analytics_id', '');
    $fbPixel = getSetting('facebook_pixel_id', '');
    $ttPixel = getSetting('tiktok_pixel_id', '');
    $gscVerify = getSetting('google_site_verification', '');
    $bingVerify = getSetting('bing_site_verification', '');
    
    $type = $seo['type'] ?? 'website';
    $title = $seo['title'] ?? getSetting('meta_title', $siteName);
    $desc = $seo['description'] ?? getSetting('meta_description', '');
    $image = $seo['image'] ?? $defaultOgImage;
    $url = $seo['url'] ?? seoCanonical();
    $keywords = $seo['keywords'] ?? $metaKeywords;
    $noindex = $seo['noindex'] ?? false;
    $publishedTime = $seo['published_time'] ?? '';
    $modifiedTime = $seo['modified_time'] ?? '';
    $author = $seo['author'] ?? '';
    $price = $seo['price'] ?? '';
    $currency = $seo['currency'] ?? 'BDT';
    $locale = $seo['locale'] ?? 'bn_BD';
    
    // Ensure absolute image URL
    if ($image && !str_starts_with($image, 'http')) {
        $image = $siteUrl . '/' . ltrim($image, '/');
    }
    
    $html = '';
    
    // Canonical
    $html .= '    <link rel="canonical" href="' . htmlspecialchars($url) . '">' . "\n";
    
    // Robots
    if ($noindex) {
        $html .= '    <meta name="robots" content="noindex, nofollow">' . "\n";
    } else {
        $html .= '    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
    }
    
    // Keywords
    if ($keywords) {
        $html .= '    <meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
    }
    
    // Author
    if ($author) {
        $html .= '    <meta name="author" content="' . htmlspecialchars($author) . '">' . "\n";
    }
    
    // ── Open Graph ──
    $ogType = match($type) {
        'product' => 'product',
        'article', 'blog' => 'article',
        default => 'website'
    };
    $html .= '    <meta property="og:type" content="' . $ogType . '">' . "\n";
    $html .= '    <meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '    <meta property="og:description" content="' . htmlspecialchars($desc) . '">' . "\n";
    $html .= '    <meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    $html .= '    <meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">' . "\n";
    $html .= '    <meta property="og:locale" content="' . $locale . '">' . "\n";
    if ($image) {
        $html .= '    <meta property="og:image" content="' . htmlspecialchars($image) . '">' . "\n";
        $html .= '    <meta property="og:image:width" content="1200">' . "\n";
        $html .= '    <meta property="og:image:height" content="630">' . "\n";
    }
    if ($ogType === 'product' && $price) {
        $html .= '    <meta property="product:price:amount" content="' . $price . '">' . "\n";
        $html .= '    <meta property="product:price:currency" content="' . $currency . '">' . "\n";
    }
    if ($ogType === 'article') {
        if ($publishedTime) $html .= '    <meta property="article:published_time" content="' . $publishedTime . '">' . "\n";
        if ($modifiedTime) $html .= '    <meta property="article:modified_time" content="' . $modifiedTime . '">' . "\n";
    }
    
    // ── Twitter Card ──
    $html .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '    <meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '    <meta name="twitter:description" content="' . htmlspecialchars($desc) . '">' . "\n";
    if ($image) {
        $html .= '    <meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . "\n";
    }
    
    // ── Verification Tags ──
    if ($gscVerify) {
        $html .= '    <meta name="google-site-verification" content="' . htmlspecialchars($gscVerify) . '">' . "\n";
    }
    if ($bingVerify) {
        $html .= '    <meta name="msvalidate.01" content="' . htmlspecialchars($bingVerify) . '">' . "\n";
    }
    
    // ── Preconnect for performance ──
    $html .= '    <link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    $html .= '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    
    // ── Google Analytics ──
    if ($gaId) {
        $html .= '    <script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($gaId) . '"></script>' . "\n";
        $html .= '    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","' . htmlspecialchars($gaId) . '");</script>' . "\n";
    }
    
    // ── Facebook Pixel ──
    if ($fbPixel) {
        $html .= "    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . htmlspecialchars($fbPixel) . "');fbq('track','PageView');</script>\n";
        $html .= '    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . htmlspecialchars($fbPixel) . '&ev=PageView&noscript=1"></noscript>' . "\n";
    }
    
    // ── TikTok Pixel ──
    if ($ttPixel) {
        $html .= "    <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement('script');o.type='text/javascript';o.async=!0;o.src=i+'?sdkid='+e+'&lib='+t;var a=document.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};ttq.load('" . htmlspecialchars($ttPixel) . "');ttq.page()}(window,document,'ttq');</script>\n";
    }
    
    return $html;
}

// ── JSON-LD Structured Data ──

function seoSchemaOrganization(): array {
    $siteName = getSetting('site_name', 'KhatiBangla');
    $siteUrl = rtrim(SITE_URL, '/');
    $logo = getSetting('site_logo', '');
    if ($logo && !str_starts_with($logo, 'http')) $logo = $siteUrl . '/' . ltrim($logo, '/');
    $phone = getSetting('site_phone', '') ?: getSetting('hotline_number', '');
    $email = getSetting('site_email', '') ?: getSetting('order_notification_email', '');
    $address = getSetting('site_address', '');
    
    $org = [
        '@type' => 'Organization',
        '@id' => $siteUrl . '/#organization',
        'name' => $siteName,
        'url' => $siteUrl,
    ];
    if ($logo) $org['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
    if ($phone) $org['telephone'] = $phone;
    if ($email) $org['email'] = $email;
    if ($address) {
        $org['address'] = [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Dhaka',
            'addressCountry' => 'BD',
            'streetAddress' => $address,
        ];
    }
    
    $social = [];
    $fb = getSetting('social_facebook', '');
    $ig = getSetting('social_instagram', '');
    $yt = getSetting('social_youtube', '');
    if ($fb) $social[] = $fb;
    if ($ig) $social[] = $ig;
    if ($yt) $social[] = $yt;
    if ($social) $org['sameAs'] = $social;
    
    return $org;
}

function seoSchemaWebSite(): array {
    $siteUrl = rtrim(SITE_URL, '/');
    return [
        '@type' => 'WebSite',
        '@id' => $siteUrl . '/#website',
        'name' => getSetting('site_name', 'KhatiBangla'),
        'url' => $siteUrl,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $siteUrl . '/search?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function seoSchemaProduct(array $product): array {
    $siteUrl = rtrim(SITE_URL, '/');
    $name = $product['name_bn'] ?: $product['name'];
    $desc = strip_tags($product['short_description'] ?? $product['description'] ?? '');
    if (mb_strlen($desc) > 200) $desc = mb_substr($desc, 0, 200) . '...';
    
    $img = '';
    if (!empty($product['featured_image'])) {
        $img = $product['featured_image'];
        if (!str_starts_with($img, 'http')) $img = $siteUrl . '/uploads/products/' . basename($img);
    }
    
    $price = ($product['sale_price'] ?? 0) > 0 && $product['sale_price'] < $product['regular_price'] 
        ? $product['sale_price'] : $product['regular_price'];
    
    $availability = match($product['stock_status'] ?? 'in_stock') {
        'out_of_stock' => 'https://schema.org/OutOfStock',
        'pre_order' => 'https://schema.org/PreOrder',
        default => 'https://schema.org/InStock',
    };
    
    $schema = [
        '@type' => 'Product',
        'name' => $name,
        'url' => $siteUrl . '/product/' . ($product['slug'] ?? ''),
        'description' => $desc,
        'sku' => $product['sku'] ?? '',
        'brand' => ['@type' => 'Brand', 'name' => getSetting('site_name', 'KhatiBangla')],
        'offers' => [
            '@type' => 'Offer',
            'url' => $siteUrl . '/product/' . ($product['slug'] ?? ''),
            'priceCurrency' => 'BDT',
            'price' => number_format((float)$price, 2, '.', ''),
            'availability' => $availability,
            'seller' => ['@type' => 'Organization', 'name' => getSetting('site_name', 'KhatiBangla')],
        ],
    ];
    
    if ($img) $schema['image'] = [$img];
    
    // Add category as category
    if (!empty($product['category_name'])) {
        $schema['category'] = $product['category_name'];
    }
    
    // Aggregate rating if available
    if (!empty($product['avg_rating']) && $product['avg_rating'] > 0 && !empty($product['review_count']) && $product['review_count'] > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float)$product['avg_rating'], 1),
            'reviewCount' => (int)$product['review_count'],
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }
    
    return $schema;
}

function seoSchemaBreadcrumb(array $items): array {
    $list = [];
    foreach ($items as $i => $item) {
        $list[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => $item['url'] ?? null,
        ];
    }
    // Last item shouldn't have URL (current page)
    if (!empty($list)) unset($list[count($list) - 1]['item']);
    
    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

function seoSchemaArticle(array $post): array {
    $siteUrl = rtrim(SITE_URL, '/');
    $title = $post['title_bn'] ?: ($post['title'] ?? '');
    $desc = strip_tags($post['excerpt'] ?? $post['content'] ?? '');
    if (mb_strlen($desc) > 200) $desc = mb_substr($desc, 0, 200) . '...';
    
    $img = '';
    if (!empty($post['featured_image'])) {
        $img = $post['featured_image'];
        if (!str_starts_with($img, 'http')) $img = $siteUrl . '/uploads/blog/' . basename($img);
    }
    
    $schema = [
        '@type' => 'Article',
        'headline' => $title,
        'description' => $desc,
        'url' => $siteUrl . '/blog/' . ($post['slug'] ?? ''),
        'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?? 'Admin'],
        'publisher' => [
            '@type' => 'Organization',
            'name' => getSetting('site_name', 'KhatiBangla'),
        ],
    ];
    
    if ($img) $schema['image'] = [$img];
    if (!empty($post['published_at'])) $schema['datePublished'] = date('c', strtotime($post['published_at']));
    if (!empty($post['updated_at'])) $schema['dateModified'] = date('c', strtotime($post['updated_at']));
    
    return $schema;
}

// ── Render JSON-LD block ──
function seoRenderJsonLd(array $seo = []): string {
    $graphs = [];
    $type = $seo['type'] ?? 'website';
    
    // Always include Organization + WebSite on homepage
    if ($type === 'home' || $type === 'website') {
        $graphs[] = seoSchemaOrganization();
        $graphs[] = seoSchemaWebSite();
    }
    
    // Product schema
    if ($type === 'product' && !empty($seo['product'])) {
        $graphs[] = seoSchemaProduct($seo['product']);
    }
    
    // Article schema
    if ($type === 'article' && !empty($seo['article'])) {
        $graphs[] = seoSchemaArticle($seo['article']);
    }
    
    // Breadcrumbs
    if (!empty($seo['breadcrumbs'])) {
        $graphs[] = seoSchemaBreadcrumb($seo['breadcrumbs']);
    }
    
    if (empty($graphs)) return '';
    
    $jsonLd = ['@context' => 'https://schema.org'];
    if (count($graphs) === 1) {
        $jsonLd = array_merge($jsonLd, $graphs[0]);
    } else {
        $jsonLd['@graph'] = $graphs;
    }
    
    return '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}
