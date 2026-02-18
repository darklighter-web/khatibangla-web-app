<?php
/**
 * Courier Fraud Checker v12 — Own DB + Direct Courier APIs
 * 
 * Data sources (per courier):
 *   OWN DB  → orders table with courier_name/shipping_method (primary — always has real counts)
 *   Pathao  → merchant.pathao.com (cross-merchant rating)
 *   Steadfast → API/web scrape (cross-merchant counts — may fail with 403)
 *   RedX    → api.redx.com.bd (cross-merchant counts)
 *
 * Usage: GET /api/fraud-checker.php?phone=01XXXXXXXXX
 */
error_reporting(0); ini_set('display_errors', 0);
header('Content-Type: application/json');
ob_start();
try { require_once __DIR__.'/../includes/session.php'; require_once __DIR__.'/../includes/functions.php'; } catch(Exception $e) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }
ob_end_clean();
if (empty($_SESSION['admin_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$action = $_GET['action'] ?? 'check';
$phone  = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? $_POST['phone'] ?? '');
if (substr($phone,0,2)==='88') $phone = substr($phone,2);
if (strlen($phone)===10 && $phone[0]!=='0') $phone = '0'.$phone;

// ── Save credentials ──
if ($action === 'save_credentials') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $db = Database::getInstance();
    foreach (['steadfast_merchant_email'=>'Steadfast Email','steadfast_merchant_password'=>'Steadfast Password','redx_phone'=>'RedX Phone','redx_password'=>'RedX Password'] as $key=>$label) {
        if (isset($input[$key])) {
            $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            if ($exists) $db->update('site_settings', ['setting_value'=>$input[$key]], 'setting_key = ?', [$key]);
            else $db->insert('site_settings', ['setting_key'=>$key,'setting_value'=>$input[$key],'setting_type'=>'text','setting_group'=>'courier','label'=>$label]);
        }
    }
    echo json_encode(['success'=>true,'message'=>'Saved']); exit;
}

if (!preg_match('/^01[3-9][0-9]{8}$/', $phone)) { echo json_encode(['success'=>false,'error'=>'Invalid BD phone (01XXXXXXXXX)']); exit; }
$db = Database::getInstance();

// ══════════════════════════════════════════
// 1) OWN DATABASE — per-courier breakdown (primary source, always works)
// ══════════════════════════════════════════
$local     = _checkLocal($phone, $db);
$localByCourier = _checkLocalByCourier($phone, $db);

// ══════════════════════════════════════════
// 2) EXTERNAL APIs — cross-merchant data (supplement)
// ══════════════════════════════════════════
$pathaoApi    = _checkPathao($phone);
$steadfastApi = _checkSteadfast($phone);
$redxApi      = _checkRedx($phone);

// ══════════════════════════════════════════
// 3) MERGE: Own DB counts + External API data
// ══════════════════════════════════════════
$dbPathao    = $localByCourier['pathao'] ?? ['total'=>0,'delivered'=>0,'cancelled'=>0];
$dbSteadfast = $localByCourier['steadfast'] ?? ['total'=>0,'delivered'=>0,'cancelled'=>0];
$dbRedx      = $localByCourier['redx'] ?? ['total'=>0,'delivered'=>0,'cancelled'=>0];

// Pathao: show API rating + own DB counts
$pathao = [
    'total'           => intval($dbPathao['total']),
    'success'         => intval($dbPathao['delivered']),
    'cancel'          => intval($dbPathao['cancelled']),
    'customer_rating' => $pathaoApi['customer_rating'] ?? null,
    'show_count'      => true, // we always show counts from own DB
    'api_show_count'  => $pathaoApi['show_count'] ?? true,
    'source'          => 'own_db + pathao_api',
];
// If API also has counts, add cross-merchant note
if (!isset($pathaoApi['error']) && ($pathaoApi['show_count']??true)===true && intval($pathaoApi['total']??0) > 0) {
    $pathao['cross_merchant_total']   = intval($pathaoApi['total']);
    $pathao['cross_merchant_success'] = intval($pathaoApi['success']);
    $pathao['cross_merchant_cancel']  = intval($pathaoApi['cancel']);
}

// Steadfast: own DB counts (primary) + API cross-merchant (if available)
$steadfast = [
    'total'   => intval($dbSteadfast['total']),
    'success' => intval($dbSteadfast['delivered']),
    'cancel'  => intval($dbSteadfast['cancelled']),
    'source'  => 'own_db',
];
if (!isset($steadfastApi['error']) && intval($steadfastApi['total']??0) > 0) {
    $steadfast['cross_merchant_total']   = intval($steadfastApi['total']);
    $steadfast['cross_merchant_success'] = intval($steadfastApi['success']);
    $steadfast['cross_merchant_cancel']  = intval($steadfastApi['cancel']);
    $steadfast['source'] = 'own_db + steadfast_api';
} elseif (isset($steadfastApi['error'])) {
    $steadfast['api_note'] = $steadfastApi['error'];
}

// RedX: own DB counts + API cross-merchant
$redx = [
    'total'   => intval($dbRedx['total']),
    'success' => intval($dbRedx['delivered']),
    'cancel'  => intval($dbRedx['cancelled']),
    'source'  => 'own_db',
];
if (!isset($redxApi['error']) && intval($redxApi['total']??0) > 0) {
    $redx['cross_merchant_total']   = intval($redxApi['total']);
    $redx['cross_merchant_success'] = intval($redxApi['success']);
    $redx['cross_merchant_cancel']  = intval($redxApi['cancel']);
    $redx['source'] = 'own_db + redx_api';
} elseif (isset($redxApi['error'])) {
    $redx['api_note'] = $redxApi['error'];
}

$results = ['phone'=>$phone, 'pathao'=>$pathao, 'steadfast'=>$steadfast, 'redx'=>$redx, 'local'=>$local];

// ── Combined stats ──
// Use BOTH own DB totals AND cross-merchant API data
$ownTotal   = intval($local['total']??0);
$ownSuccess = intval($local['delivered']??0);
$ownCancel  = intval($local['cancelled']??0);

// Cross-merchant totals (from APIs, for customers who ordered from OTHER shops too)
$xTotal = $xSuccess = $xCancel = 0;
foreach (['pathao'=>$pathaoApi, 'redx'=>$redxApi, 'steadfast'=>$steadfastApi] as $src=>$d) {
    if (isset($d['error'])) continue;
    if ($src==='pathao' && ($d['show_count']??true)===false) continue;
    $xTotal   += intval($d['total']??0);
    $xSuccess += intval($d['success']??0);
    $xCancel  += intval($d['cancel']??0);
}

// Grand total: own + cross-merchant (avoid double-counting own orders)
$grandTotal   = max($ownTotal, $xTotal);
$grandSuccess = max($ownSuccess, $xSuccess);
$grandCancel  = max($ownCancel, $xCancel);
$grandRate    = $grandTotal > 0 ? round(($grandSuccess / $grandTotal) * 100) : 0;

$pr = $pathaoApi['customer_rating'] ?? null;

// If only Pathao rating available (no counts anywhere)
if ($grandTotal === 0 && $pr) {
    $rr = ['excellent_customer'=>95,'good_customer'=>80,'moderate_customer'=>55,'risky_customer'=>25,'new_customer'=>0];
    $grandRate = $rr[$pr] ?? 50;
}

// Risk assessment
if ($grandTotal === 0 && !$pr) {
    $risk = 'new'; $rl = 'New Customer';
} elseif ($grandTotal === 0 && $pr) {
    $rm = ['excellent_customer'=>['low','Excellent (Pathao)'],'good_customer'=>['low','Good (Pathao)'],'moderate_customer'=>['medium','Moderate (Pathao)'],'risky_customer'=>['high','High Risk (Pathao)'],'new_customer'=>['new','New Customer']];
    [$risk, $rl] = $rm[$pr] ?? ['new','New Customer'];
} elseif ($grandTotal > 0) {
    if ($grandRate >= 70) { $risk='low'; $rl='Trusted Customer'; }
    elseif ($grandRate >= 40) { $risk='medium'; $rl='Moderate Risk'; }
    else { $risk='high'; $rl='High Risk'; }
    if ($pr === 'excellent_customer' && $risk === 'medium') { $risk='low'; $rl .= ' ✓ Pathao Excellent'; }
} else {
    $risk = 'new'; $rl = 'New Customer';
}
if (!empty($local['is_blocked'])) { $risk='blocked'; $rl='BLOCKED: '.($local['block_reason']??''); }

$results['combined'] = [
    'total'           => $grandTotal,
    'success'         => $grandSuccess,
    'cancel'          => $grandCancel,
    'rate'            => $grandRate,
    'risk'            => $risk,
    'risk_label'      => $rl,
    'pathao_rating'   => $pr,
    'own_total'       => $ownTotal,
    'cross_total'     => $xTotal,
];
$results['success'] = true;
echo json_encode($results, JSON_PRETTY_PRINT);
exit;

// ═══════════════════════════════════════════════════
// LOCAL: Total across all couriers
// ═══════════════════════════════════════════════════
function _checkLocal($phone, $db) {
    $pl = '%' . substr($phone, -10) . '%';
    try {
        $r = $db->fetch("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered,
                   SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                   SUM(CASE WHEN order_status='returned' THEN 1 ELSE 0 END) as returned,
                   SUM(CASE WHEN order_status='delivered' THEN total ELSE 0 END) as total_spent,
                   MIN(created_at) as first_order, MAX(created_at) as last_order
            FROM orders WHERE customer_phone LIKE ? AND order_status NOT IN ('incomplete')
        ", [$pl]);

        $bl = null;
        try { $bl = $db->fetch("SELECT reason FROM blocked_phones WHERE phone LIKE ?", [$pl]); } catch(\Throwable $e) {}
        $ar = [];
        try { $ar = $db->fetchAll("
            SELECT COALESCE(NULLIF(customer_district,''), COALESCE(NULLIF(customer_city,''),'Unknown')) as area, COUNT(*) as cnt
            FROM orders WHERE customer_phone LIKE ? GROUP BY area ORDER BY cnt DESC LIMIT 5
        ", [$pl]); } catch(\Throwable $e) {}

        return [
            'total'        => intval($r['total']??0),
            'delivered'    => intval($r['delivered']??0),
            'cancelled'    => intval($r['cancelled']??0),
            'returned'     => intval($r['returned']??0),
            'total_spent'  => floatval($r['total_spent']??0),
            'first_order'  => $r['first_order'] ?? null,
            'last_order'   => $r['last_order'] ?? null,
            'is_blocked'   => !empty($bl),
            'block_reason' => $bl['reason'] ?? null,
            'areas'        => $ar,
        ];
    } catch (\Throwable $e) {
        return ['error'=>'DB: '.$e->getMessage(), 'total'=>0];
    }
}

// ═══════════════════════════════════════════════════
// LOCAL: Per-courier breakdown from own orders
// ═══════════════════════════════════════════════════
function _checkLocalByCourier($phone, $db) {
    $pl = '%' . substr($phone, -10) . '%';
    $result = ['pathao'=>['total'=>0,'delivered'=>0,'cancelled'=>0], 'steadfast'=>['total'=>0,'delivered'=>0,'cancelled'=>0], 'redx'=>['total'=>0,'delivered'=>0,'cancelled'=>0]];

    $courierPatterns = [
        'pathao'    => ['pathao'],
        'steadfast' => ['steadfast','packzy'],
        'redx'      => ['redx','red-x','red x'],
    ];

    foreach ($courierPatterns as $key => $patterns) {
        // Build OR conditions for matching courier name
        $conditions = [];
        $params = [$pl];
        foreach ($patterns as $p) {
            $conditions[] = "LOWER(COALESCE(courier_name,'')) LIKE ?";
            $conditions[] = "LOWER(COALESCE(shipping_method,'')) LIKE ?";
            $params[] = '%'.$p.'%';
            $params[] = '%'.$p.'%';
        }
        $where = implode(' OR ', $conditions);

        try {
            $r = $db->fetch("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered,
                       SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM orders
                WHERE customer_phone LIKE ?
                  AND order_status NOT IN ('incomplete')
                  AND ($where)
            ", $params);

            $result[$key] = [
                'total'     => intval($r['total']??0),
                'delivered' => intval($r['delivered']??0),
                'cancelled' => intval($r['cancelled']??0),
            ];
        } catch (\Throwable $e) {
            // Keep defaults (0)
        }
    }

    return $result;
}

// ═══════════════════════════════════════════════════
// PATHAO API — cross-merchant rating
// ═══════════════════════════════════════════════════
function _checkPathao($phone) {
    $u = getSetting('pathao_username','');
    $p = getSetting('pathao_password','');
    if (!$u || !$p) return ['error'=>'Pathao credentials not set'];

    $ch = curl_init('https://merchant.pathao.com/api/v1/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['username'=>$u,'password'=>$p]),
    ]);
    $r = curl_exec($ch); $c = curl_getinfo($ch,CURLINFO_HTTP_CODE); $e = curl_error($ch); curl_close($ch);
    if ($e) return ['error'=>"Pathao curl: $e"];
    $d = json_decode($r,true);
    $t = trim($d['access_token']??'');
    if (!$t) return ['error'=>"Pathao login failed (HTTP $c)"];

    $ch = curl_init('https://merchant.pathao.com/api/v1/user/success');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.$t],
        CURLOPT_POSTFIELDS=>json_encode(['phone'=>$phone]),
    ]);
    $r = curl_exec($ch); $c = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($c>=400) return ['error'=>"Pathao HTTP $c"];

    $res = json_decode($r,true);
    $cu  = $res['data']['customer'] ?? $res['data'] ?? [];

    return [
        'success'         => intval($cu['successful_delivery']??0),
        'cancel'          => intval(($cu['total_delivery']??0) - ($cu['successful_delivery']??0)),
        'total'           => intval($cu['total_delivery']??0),
        'show_count'      => $cu['show_count'] ?? true,
        'customer_rating' => $cu['customer_rating'] ?? null,
        'source'          => 'pathao_api',
    ];
}

// ═══════════════════════════════════════════════════
// STEADFAST API — try API key first, then web scrape
// ═══════════════════════════════════════════════════
function _checkSteadfast($phone) {
    $apiResult = _steadfastApiCheck($phone);
    if ($apiResult && !isset($apiResult['error'])) return $apiResult;

    $webResult = _steadfastWebScrape($phone);
    if ($webResult && !isset($webResult['error'])) return $webResult;

    $apiErr = $apiResult['error'] ?? 'N/A';
    $webErr = $webResult['error'] ?? 'N/A';
    return ['error' => "Cross-merchant unavailable (API: $apiErr)"];
}

function _steadfastApiCheck($phone) {
    $apiKey = getSetting('steadfast_api_key','');
    $secretKey = getSetting('steadfast_secret_key','');
    if (!$apiKey || !$secretKey) return ['error'=>'API keys not set'];

    $headers = ['Api-Key: '.$apiKey, 'Secret-Key: '.$secretKey, 'Content-Type: application/json', 'Accept: application/json'];
    $bases = ['https://portal.steadfast.com.bd/api/v1', 'https://portal.packzy.com/api/v1'];
    $endpoints = ['/courier_score/','/fraud_check/','/courier-score/','/fraud-check/'];

    foreach ($bases as $base) {
        foreach ($endpoints as $ep) {
            $ch = curl_init($base . $ep . urlencode($phone));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>true]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($code === 404 || $code === 405 || $code === 0) continue;
            if ($code === 200 && $resp) {
                $data = json_decode($resp, true);
                if (!$data) continue;
                $flat = array_merge($data, $data['data'] ?? []);
                foreach (['total_delivered','delivered','success_count','success'] as $dk) {
                    if (isset($flat[$dk])) {
                        $del = intval($flat[$dk]);
                        $can = intval($flat['total_cancelled'] ?? $flat['cancelled'] ?? $flat['cancel'] ?? 0);
                        return ['success'=>$del,'cancel'=>$can,'total'=>$del+$can,'source'=>'steadfast_api'];
                    }
                }
            }
        }
    }
    return ['error'=>'No API endpoint found'];
}

function _steadfastWebScrape($phone) {
    $em = getSetting('steadfast_merchant_email','') ?: getSetting('steadfast_email','');
    $pw = getSetting('steadfast_merchant_password','') ?: getSetting('steadfast_password','');
    if (!$em || !$pw) return ['error'=>'Web login not configured'];

    $cf = sys_get_temp_dir().'/sf_'.md5($em.time()).'.txt';
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $bh = ["User-Agent: $ua",'Accept-Language: en-US,en;q=0.9','Sec-Ch-Ua: "Chromium";v="131"','Sec-Ch-Ua-Mobile: ?0','Sec-Ch-Ua-Platform: "Windows"'];

    $ch = curl_init('https://steadfast.com.bd/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIEJAR=>$cf,CURLOPT_COOKIEFILE=>$cf,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',CURLOPT_HTTPHEADER=>array_merge($bh,['Accept: text/html,*/*'])]);
    $lp = curl_exec($ch); curl_close($ch);

    $csrf = '';
    if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/', $lp, $m)) $csrf = $m[1];
    elseif (preg_match('/<input[^>]+name=["\']_token["\'][^>]+value=["\']([^"\']+)["\']/', $lp, $m)) $csrf = $m[1];
    if (!$csrf) { @unlink($cf); return ['error'=>'CSRF not found']; }

    $ch = curl_init('https://steadfast.com.bd/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIEJAR=>$cf,CURLOPT_COOKIEFILE=>$cf,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',
        CURLOPT_HTTPHEADER=>array_merge($bh,['Content-Type: application/x-www-form-urlencoded','Accept: text/html,*/*','Referer: https://steadfast.com.bd/login','Origin: https://steadfast.com.bd']),
        CURLOPT_POSTFIELDS=>http_build_query(['_token'=>$csrf,'email'=>$em,'password'=>$pw]),
    ]);
    $lr = curl_exec($ch); $lu = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL); curl_close($ch);

    if (strpos($lu,'/login')!==false && strpos($lr,'credentials')!==false) { @unlink($cf); return ['error'=>'Wrong password']; }

    $csrf2=$csrf;
    if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/', $lr, $m)) $csrf2=$m[1];

    $ch = curl_init('https://steadfast.com.bd/user/frauds/check');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIEJAR=>$cf,CURLOPT_COOKIEFILE=>$cf,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',
        CURLOPT_HTTPHEADER=>array_merge($bh,['Accept: text/html,*/*','Referer: https://steadfast.com.bd/user/dashboard']),
    ]);
    $fp = curl_exec($ch); curl_close($ch);
    if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/', $fp, $m)) $csrf2=$m[1];
    $tk = $csrf2 ?: $csrf;

    // Try AJAX GET
    $ch = curl_init('https://steadfast.com.bd/user/frauds/check/'.urlencode($phone));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIEJAR=>$cf,CURLOPT_COOKIEFILE=>$cf,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',
        CURLOPT_HTTPHEADER=>array_merge($bh,['Accept: application/json, */*; q=0.01','X-Requested-With: XMLHttpRequest','X-CSRF-TOKEN: '.$tk,'Referer: https://steadfast.com.bd/user/frauds/check','Sec-Fetch-Dest: empty','Sec-Fetch-Mode: cors','Sec-Fetch-Site: same-origin']),
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    if ($code===200 && $resp) {
        $data = json_decode($resp, true);
        if ($data) {
            $del = intval($data['total_delivered'] ?? $data['delivered'] ?? 0);
            $can = intval($data['total_cancelled'] ?? $data['cancelled'] ?? 0);
            if ($del+$can > 0) { @unlink($cf); return ['success'=>$del,'cancel'=>$can,'total'=>$del+$can,'source'=>'steadfast_web']; }
        }
    }

    // Try POST
    $ch = curl_init('https://steadfast.com.bd/user/frauds/check');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIEJAR=>$cf,CURLOPT_COOKIEFILE=>$cf,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',
        CURLOPT_HTTPHEADER=>array_merge($bh,['Accept: application/json, */*; q=0.01','Content-Type: application/x-www-form-urlencoded','X-Requested-With: XMLHttpRequest','X-CSRF-TOKEN: '.$tk,'Referer: https://steadfast.com.bd/user/frauds/check','Sec-Fetch-Dest: empty','Sec-Fetch-Mode: cors','Sec-Fetch-Site: same-origin']),
        CURLOPT_POSTFIELDS=>http_build_query(['_token'=>$tk,'phone'=>$phone]),
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    @unlink($cf);

    if ($code===200 && $resp) {
        $data = json_decode($resp, true);
        if ($data) {
            $del = intval($data['total_delivered'] ?? 0);
            $can = intval($data['total_cancelled'] ?? 0);
            if ($del+$can > 0) return ['success'=>$del,'cancel'=>$can,'total'=>$del+$can,'source'=>'steadfast_web'];
        }
    }

    return ['error'=>'HTTP 403 — blocked'];
}

// ═══════════════════════════════════════════════════
// REDX API — cross-merchant data
// ═══════════════════════════════════════════════════
function _checkRedx($phone) {
    $rp  = getSetting('redx_phone','');
    $rpw = getSetting('redx_password','');
    if (!$rp || !$rpw) return ['error'=>'RedX credentials not configured'];

    $loginPhone = '88' . ltrim(preg_replace('/[^0-9]/', '', $rp), '0');
    $ch = curl_init('https://api.redx.com.bd/v4/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['phone'=>$loginPhone,'password'=>$rpw]),
    ]);
    $r = curl_exec($ch); curl_close($ch);
    $d = json_decode($r,true);
    $t = $d['data']['accessToken'] ?? '';
    if (!$t) return ['error'=>'RedX login failed: '.($d['message']??'no token')];

    $queryPhone = '88' . ltrim($phone, '0');
    $ch = curl_init('https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=' . urlencode($queryPhone));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.$t],
    ]);
    $r = curl_exec($ch); $c = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $res = json_decode($r,true);
    if (!$res || $c >= 400) return ['error'=>"RedX HTTP $c"];

    $data = $res['data'] ?? $res;
    $delivered = intval($data['deliveredParcels'] ?? 0);
    $total     = intval($data['totalParcels'] ?? 0);
    return ['success'=>$delivered,'cancel'=>$total-$delivered,'total'=>$total,'source'=>'redx_api'];
}
