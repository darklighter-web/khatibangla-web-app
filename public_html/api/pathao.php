<?php
/**
 * Pathao Courier Merchant API Integration
 * Production: https://api-hermes.pathao.com
 * Sandbox:    https://hermes-api.p-stageenv.xyz
 */
class PathaoAPI {
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $username;
    private $password;
    private $accessToken;
    private $tokenExpiry;
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->clientId     = $this->setting('pathao_client_id');
        $this->clientSecret = $this->setting('pathao_client_secret');
        $this->username     = $this->setting('pathao_username');
        $this->password     = $this->setting('pathao_password');
        $this->accessToken  = $this->setting('pathao_access_token');
        $this->tokenExpiry  = intval($this->setting('pathao_token_expiry'));
        $env = $this->setting('pathao_environment') ?: 'production';
        $this->baseUrl = $env === 'sandbox'
            ? 'https://hermes-api.p-stageenv.xyz'
            : 'https://api-hermes.pathao.com';
    }

    private function setting($key) {
        try {
            $row = $this->db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
            return $row ? $row['setting_value'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function saveSetting($key, $value, $group = 'pathao') {
        try {
            $exists = $this->db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                $this->db->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                $this->db->insert('site_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => 'text',
                    'setting_group' => $group,
                    'label' => ucwords(str_replace(['pathao_','_'], ['','  '], $key))
                ]);
            }
        } catch (Exception $e) {
            error_log("PathaoAPI saveSetting error: " . $e->getMessage());
        }
    }

    public function saveConfig($data) {
        $fields = ['client_id','client_secret','username','password','environment','store_id'];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $this->saveSetting('pathao_' . $f, $data[$f]);
            }
        }
        // Reload credentials after save
        $this->clientId     = $data['client_id'] ?? $this->clientId;
        $this->clientSecret = $data['client_secret'] ?? $this->clientSecret;
        $this->username     = $data['username'] ?? $this->username;
        $this->password     = $data['password'] ?? $this->password;
        $env = $data['environment'] ?? 'production';
        $this->baseUrl = $env === 'sandbox'
            ? 'https://hermes-api.p-stageenv.xyz'
            : 'https://api-hermes.pathao.com';
    }

    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret)
            && !empty($this->username) && !empty($this->password);
    }

    public function isConnected() {
        return $this->isConfigured() && !empty($this->accessToken) && $this->tokenExpiry > time();
    }

    // ========================
    // AUTH - Issue Token
    // ========================
    public function getAccessToken($force = false) {
        if (!$force && $this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }
        if (!$this->isConfigured()) {
            throw new Exception('Pathao API not configured. Fill in all credential fields.');
        }
        $res = $this->http('POST', '/aladdin/api/v1/issue-token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username'      => $this->username,
            'password'      => $this->password,
            'grant_type'    => 'password',
        ], false);

        if (!empty($res['access_token'])) {
            $this->accessToken = $res['access_token'];
            $this->tokenExpiry = time() + ($res['expires_in'] ?? 86400);
            $this->saveSetting('pathao_access_token',  $this->accessToken);
            $this->saveSetting('pathao_token_expiry',  (string)$this->tokenExpiry);
            $this->saveSetting('pathao_refresh_token', $res['refresh_token'] ?? '');
            return $this->accessToken;
        }

        $errMsg = $res['message'] ?? $res['error'] ?? 'Unknown authentication error';
        if (isset($res['errors'])) {
            $errMsg .= ' - ' . json_encode($res['errors']);
        }
        throw new Exception('Pathao auth failed: ' . $errMsg);
    }

    public function testConnection() {
        try {
            $token = $this->getAccessToken(true);
            return $token
                ? ['success' => true,  'message' => 'Connected to Pathao successfully! Token is valid.']
                : ['success' => false, 'message' => 'Authentication failed. Check your credentials.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ========================
    // LOCATION ENDPOINTS
    // ========================
    public function getCities()          { return $this->authed('GET', '/aladdin/api/v1/countries/1/city-list'); }
    public function getZones($cityId)    { return $this->authed('GET', "/aladdin/api/v1/cities/{$cityId}/zone-list"); }
    public function getAreas($zoneId)    { return $this->authed('GET', "/aladdin/api/v1/zones/{$zoneId}/area-list"); }

    // ========================
    // STORES
    // ========================
    public function getStores($page = 1) { return $this->authed('GET', "/aladdin/api/v1/stores?page={$page}"); }

    // ========================
    // ORDERS
    // ========================
    public function createOrder($data)               { return $this->authed('POST', '/aladdin/api/v1/orders', $data); }
    public function getOrderDetails($consignmentId)   { return $this->authed('GET', "/aladdin/api/v1/orders/{$consignmentId}"); }
    public function getPriceCalculation($data)        { return $this->authed('POST', '/aladdin/api/v1/merchant/price-plan', $data); }

    // ========================
    // CUSTOMER VERIFICATION (Pathao API)
    // ========================
    public function checkCustomerPhone($phone) {
        $phone = self::normalizePhone($phone);
        try {
            return $this->authed('POST', '/aladdin/api/v1/merchant/customer-check', ['phone' => $phone]);
        } catch (Exception $e) {
            return null;
        }
    }

    // ========================
    // CUSTOMER PROFILE (local DB + Pathao combined)
    // ========================
    public function getCustomerProfile($phone) {
        $phone = self::normalizePhone($phone);
        $phoneLike = '%' . substr($phone, -10) . '%';

        try {
            $local = $this->db->fetch("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN order_status='returned'  THEN 1 ELSE 0 END) as returned,
                    SUM(CASE WHEN order_status='delivered' THEN total ELSE 0 END) as total_spent,
                    MIN(created_at) as first_order,
                    MAX(created_at) as last_order
                FROM orders WHERE customer_phone LIKE ?
            ", [$phoneLike]);
        } catch (Exception $e) {
            $local = ['total_orders'=>0,'delivered'=>0,'cancelled'=>0,'returned'=>0,'total_spent'=>0,'first_order'=>null,'last_order'=>null];
        }

        $total     = intval($local['total_orders'] ?? 0);
        $delivered = intval($local['delivered'] ?? 0);
        $cancelled = intval($local['cancelled'] ?? 0);
        $returned  = intval($local['returned'] ?? 0);
        $rate      = $total > 0 ? round(($delivered / $total) * 100) : 0;

        if ($total === 0)       { $risk = 'new';    $label = 'New Customer';    }
        elseif ($rate >= 80)    { $risk = 'low';    $label = 'Trusted Customer'; }
        elseif ($rate >= 50)    { $risk = 'medium'; $label = 'Moderate Risk';    }
        else                    { $risk = 'high';   $label = 'High Risk';        }

        // Check blocked - safe if table missing
        $blocked = null;
        try {
            $blocked = $this->db->fetch("SELECT id, reason FROM blocked_phones WHERE phone LIKE ?", [$phoneLike]);
        } catch (Exception $e) {}

        // Pathao data
        $pathao = null;
        if ($this->isConfigured()) {
            try {
                $pd = $this->checkCustomerPhone($phone);
                if ($pd && isset($pd['data'])) $pathao = $pd['data'];
            } catch (Exception $e) {}
        }

        // Order areas
        $areas = [];
        try {
            $areas = $this->db->fetchAll("
                SELECT COALESCE(NULLIF(customer_district,''), COALESCE(NULLIF(customer_city,''),'Unknown')) as area_name,
                       COUNT(*) as cnt
                FROM orders WHERE customer_phone LIKE ?
                GROUP BY area_name ORDER BY cnt DESC LIMIT 5
            ", [$phoneLike]);
        } catch (Exception $e) {}

        return [
            'phone' => $phone,
            'total_orders' => $total,
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'returned' => $returned,
            'success_rate' => $rate,
            'total_spent' => floatval($local['total_spent'] ?? 0),
            'first_order' => $local['first_order'] ?? null,
            'last_order' => $local['last_order'] ?? null,
            'risk_level' => $risk,
            'risk_label' => $label,
            'is_blocked' => !empty($blocked),
            'block_reason' => $blocked['reason'] ?? null,
            'pathao_data' => $pathao,
            'areas' => $areas,
        ];
    }

    // ========================
    // AREA STATS FOR DASHBOARD
    // ========================
    public function getAreaStats($days = 90) {
        try {
            return $this->db->fetchAll("
                SELECT 
                    COALESCE(NULLIF(customer_district,''), COALESCE(NULLIF(customer_city,''),'Unknown')) as area_name,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_status='delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN order_status IN ('cancelled','returned') THEN 1 ELSE 0 END) as failed,
                    SUM(total) as revenue
                FROM orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY area_name
                ORDER BY total_orders DESC
                LIMIT 25
            ", [$days]);
        } catch (Exception $e) {
            return [];
        }
    }

    // ========================
    // ADDRESS VERIFICATION
    // ========================
    public function verifyAddress($cityId, $zoneId = 0, $areaId = 0) {
        $errors = [];
        $cityName = $zoneName = $areaName = '';

        if ($cityId) {
            $cities = $this->getCities();
            $cityList = $cities['data']['data'] ?? $cities['data'] ?? [];
            $found = false;
            foreach ($cityList as $c) {
                if (($c['city_id'] ?? 0) == $cityId) { $found = true; $cityName = $c['city_name'] ?? ''; break; }
            }
            if (!$found) $errors[] = 'Invalid city ID';
        }

        if ($cityId && $zoneId && empty($errors)) {
            $zones = $this->getZones($cityId);
            $zoneList = $zones['data']['data'] ?? $zones['data'] ?? [];
            $found = false;
            foreach ($zoneList as $z) {
                if (($z['zone_id'] ?? 0) == $zoneId) { $found = true; $zoneName = $z['zone_name'] ?? ''; break; }
            }
            if (!$found) $errors[] = 'Invalid zone for selected city';
        }

        if ($zoneId && $areaId && empty($errors)) {
            $areas = $this->getAreas($zoneId);
            $areaList = $areas['data']['data'] ?? $areas['data'] ?? [];
            $found = false;
            foreach ($areaList as $a) {
                if (($a['area_id'] ?? 0) == $areaId) { $found = true; $areaName = $a['area_name'] ?? ''; break; }
            }
            if (!$found) $errors[] = 'Invalid area for selected zone';
        }

        return [
            'verified' => empty($errors),
            'errors' => $errors,
            'city_name' => $cityName,
            'zone_name' => $zoneName,
            'area_name' => $areaName,
        ];
    }

    // ========================
    // HELPERS
    // ========================
    public static function normalizePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) === '88') $phone = substr($phone, 2);
        if (substr($phone, 0, 1) !== '0') $phone = '0' . $phone;
        return $phone;
    }

    private function authed($method, $path, $data = []) {
        $token = $this->getAccessToken();
        if (!$token) throw new Exception('Pathao auth failed');
        return $this->http($method, $path, $data, true);
    }

    private function http($method, $path, $data = [], $auth = true) {
        $url = $this->baseUrl . $path;
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($auth && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Curl error: {$error}");

        // Safe log - silently fail if table doesn't exist
        try {
            $this->db->insert('pathao_api_logs', [
                'method' => $method,
                'endpoint' => $path,
                'http_code' => $httpCode,
                'response_summary' => substr($response ?? '', 0, 500),
            ]);
        } catch (Exception $e) {
            // Table may not exist yet - that's OK
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && !empty($response)) {
            throw new Exception("Invalid JSON from Pathao (HTTP {$httpCode}): " . substr($response, 0, 200));
        }

        return $decoded ?: [];
    }
}
