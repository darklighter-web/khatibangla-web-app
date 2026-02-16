<?php
/**
 * Steadfast Courier API Integration
 * Base URL: https://portal.packzy.com/api/v1
 */
class SteadfastAPI {
    private $baseUrl = 'https://portal.packzy.com/api/v1';
    private $apiKey;
    private $secretKey;
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->apiKey   = $this->setting('steadfast_api_key');
        $this->secretKey = $this->setting('steadfast_secret_key');
    }

    public function setting($key) {
        try {
            $row = $this->db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
            return $row ? $row['setting_value'] : '';
        } catch (Exception $e) { return ''; }
    }

    public function saveSetting($key, $value) {
        try {
            $exists = $this->db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            if ($exists) { $this->db->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]); }
            else { $this->db->insert('site_settings', ['setting_key'=>$key,'setting_value'=>$value,'setting_type'=>'text','setting_group'=>'steadfast','label'=>ucwords(str_replace(['steadfast_','_'],['','  '],$key))]); }
        } catch (Exception $e) {}
    }

    public function isConfigured() { return !empty($this->apiKey) && !empty($this->secretKey); }

    public function createOrder($data) { return $this->http('POST', '/create_order', $data); }
    public function bulkCreateOrder($orders) { return $this->http('POST', '/create_order/bulk-order', $orders); }
    public function getStatusByCid($consignmentId) { return $this->http('GET', '/status_by_cid/' . $consignmentId); }
    public function getStatusByInvoice($invoice) { return $this->http('GET', '/status_by_invoice/' . $invoice); }
    public function getStatusByTrackingCode($code) { return $this->http('GET', '/status_by_trackingcode/' . $code); }
    public function getBalance() { return $this->http('GET', '/get_balance'); }

    private function http($method, $path, $data = []) {
        $url = $this->baseUrl . $path;
        $headers = [
            'Api-Key: ' . $this->apiKey,
            'Secret-Key: ' . $this->secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
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
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) throw new Exception("Steadfast API error: {$error}");
        return json_decode($response, true) ?: [];
    }
}
