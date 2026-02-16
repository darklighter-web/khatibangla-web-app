<?php
/**
 * Pathao API AJAX Endpoint
 * All admin JS calls go through here
 */

// Suppress PHP errors from appearing as HTML - catch them as JSON instead
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header FIRST before any output
header('Content-Type: application/json');

// Start output buffering to catch any stray output
ob_start();

try {
    require_once __DIR__ . '/../includes/session.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/pathao.php';
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server include error: ' . $e->getMessage()]);
    exit;
}

// Discard any output from includes (warnings, notices etc)
ob_end_clean();

// Auth check - must be logged in as admin
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to admin.']);
    exit;
}

$__rawInput = file_get_contents('php://input');
$__jsonInput = json_decode($__rawInput, true) ?: [];
$action = $_GET['action'] ?? $_POST['action'] ?? $__jsonInput['action'] ?? '';

try {
    $pathao = new PathaoAPI();

    switch ($action) {

        case 'test_connection':
            echo json_encode($pathao->testConnection());
            break;

        case 'save_config':
            $data = $__jsonInput ?: $_POST;
            if (empty($data)) {
                echo json_encode(['success' => false, 'message' => 'No data received']);
                break;
            }
            $pathao->saveConfig($data);
            // Recreate with new config and test
            $pathao = new PathaoAPI();
            $result = $pathao->testConnection();
            $result['saved'] = true;
            echo json_encode($result);
            break;

        case 'get_cities':
            $r = $pathao->getCities();
            echo json_encode(['success' => true, 'data' => $r['data'] ?? $r]);
            break;

        case 'get_zones':
            $cityId = intval($_GET['city_id'] ?? 0);
            if (!$cityId) { echo json_encode(['success' => false, 'message' => 'city_id required']); break; }
            $r = $pathao->getZones($cityId);
            echo json_encode(['success' => true, 'data' => $r['data'] ?? $r]);
            break;

        case 'get_areas':
            $zoneId = intval($_GET['zone_id'] ?? 0);
            if (!$zoneId) { echo json_encode(['success' => false, 'message' => 'zone_id required']); break; }
            $r = $pathao->getAreas($zoneId);
            echo json_encode(['success' => true, 'data' => $r['data'] ?? $r]);
            break;

        case 'get_stores':
            $r = $pathao->getStores();
            echo json_encode(['success' => true, 'data' => $r['data'] ?? $r]);
            break;

        case 'price_calculation':
            $data = $__jsonInput ?: $_POST;
            $r = $pathao->getPriceCalculation($data);
            echo json_encode(['success' => true, 'data' => $r]);
            break;

        case 'create_pathao_order':
            $data = $__jsonInput ?: $_POST;
            $r = $pathao->createOrder($data);
            echo json_encode(['success' => !empty($r['data']), 'data' => $r]);
            break;

        case 'check_customer':
            $phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            if (empty($phone)) { echo json_encode(['success' => false, 'message' => 'Phone required']); break; }
            $profile = $pathao->getCustomerProfile($phone);
            echo json_encode(['success' => true, 'data' => $profile]);
            break;

        case 'verify_address':
            $data = $__jsonInput ?: $_POST;
            $result = $pathao->verifyAddress(
                intval($data['city_id'] ?? 0),
                intval($data['zone_id'] ?? 0),
                intval($data['area_id'] ?? 0)
            );
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'area_stats':
            $days = intval($_GET['days'] ?? 90);
            $stats = $pathao->getAreaStats($days);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        case 'save_order_location':
            $data = $__jsonInput ?: $_POST;
            $orderId = intval($data['order_id'] ?? 0);
            $cityId = intval($data['city_id'] ?? 0);
            $zoneId = intval($data['zone_id'] ?? 0);
            $areaId = intval($data['area_id'] ?? 0);
            if (!$orderId) { echo json_encode(['success' => false, 'message' => 'order_id required']); break; }
            $db = Database::getInstance();
            $updates = [];
            if ($cityId) $updates['pathao_city_id'] = $cityId;
            if ($zoneId) $updates['pathao_zone_id'] = $zoneId;
            if ($areaId) $updates['pathao_area_id'] = $areaId;
            if (!empty($updates)) {
                // Add columns if they don't exist yet (safe migration)
                foreach (['pathao_city_id', 'pathao_zone_id', 'pathao_area_id'] as $col) {
                    try { $db->query("ALTER TABLE orders ADD COLUMN {$col} INT DEFAULT NULL"); } catch (Exception $e) {}
                }
                $updates['updated_at'] = date('Y-m-d H:i:s');
                $db->update('orders', $updates, 'id = ?', [$orderId]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'save_steadfast':
            $data = $__jsonInput;
            $db = Database::getInstance();
            foreach (['api_key','secret_key','webhook_token'] as $k) {
                if (isset($data[$k])) {
                    $key = 'steadfast_' . $k;
                    $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                    if ($exists) { $db->update('site_settings', ['setting_value' => $data[$k]], 'setting_key = ?', [$key]); }
                    else { $db->insert('site_settings', ['setting_key'=>$key,'setting_value'=>$data[$k],'setting_type'=>'text','setting_group'=>'steadfast','label'=>ucwords(str_replace('_',' ',$k))]); }
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'test_steadfast':
            require_once __DIR__ . '/steadfast.php';
            $sf = new SteadfastAPI();
            if (!$sf->isConfigured()) { echo json_encode(['success'=>false,'error'=>'Not configured']); break; }
            try {
                $resp = $sf->getBalance();
                echo json_encode(['success'=>true,'balance'=>$resp['current_balance']??0]);
            } catch (\Throwable $e) {
                echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
            }
            break;

        case 'save_carrybee':
            $data = $__jsonInput;
            $db = Database::getInstance();
            foreach (['api_key','secret_key'] as $k) {
                if (isset($data[$k])) {
                    $key = 'carrybee_' . $k;
                    $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                    if ($exists) { $db->update('site_settings', ['setting_value' => $data[$k]], 'setting_key = ?', [$key]); }
                    else { $db->insert('site_settings', ['setting_key'=>$key,'setting_value'=>$data[$k],'setting_type'=>'text','setting_group'=>'carrybee','label'=>ucwords(str_replace('_',' ',$k))]); }
                }
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Fatal: ' . $e->getMessage()]);
}
