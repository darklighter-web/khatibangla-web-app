<?php
require_once __DIR__ . '/../../includes/session.php';
$pageTitle = 'Courier Management';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_provider') {
        $pid = intval($_POST['provider_id'] ?? 0);
        $data = ['name'=>sanitize($_POST['name']),'api_url'=>sanitize($_POST['api_url']??''),'api_key'=>sanitize($_POST['api_key']??''),'api_secret'=>sanitize($_POST['api_secret']??''),'is_active'=>isset($_POST['is_active'])?1:0];
        if ($pid) { $db->update('courier_providers', $data, 'id = ?', [$pid]); }
        else { $data['code'] = strtolower(preg_replace('/[^a-z0-9]+/','_',strtolower($data['name']))); $e=$db->fetch("SELECT id FROM courier_providers WHERE code=?",[$data['code']]); if($e)$data['code'].='_'.time(); $db->insert('courier_providers',$data); }
        redirect(adminUrl('pages/courier.php?tab=providers&msg=saved'));
    }
    if ($action === 'update_shipment') { try{$db->update('shipments',['status'=>sanitize($_POST['status'])],'id=?',[intval($_POST['shipment_id'])]);}catch(Exception$e){} redirect(adminUrl('pages/courier.php?tab=shipments&msg=updated')); }
    if ($action === 'delete_provider') { $db->delete('courier_providers','id=?',[intval($_POST['provider_id'])]); redirect(adminUrl('pages/courier.php?tab=providers&msg=deleted')); }
}

$tab = $_GET['tab'] ?? 'pathao';
$providers = []; try{$providers=$db->fetchAll("SELECT cp.*, (SELECT COUNT(*) FROM shipments s WHERE s.courier_provider_id=cp.id) as shipment_count FROM courier_providers cp ORDER BY cp.name");}catch(Exception$e){try{$providers=$db->fetchAll("SELECT*,0 as shipment_count FROM courier_providers ORDER BY name");}catch(Exception$e2){}}
$shipments = []; try{$shipments=$db->fetchAll("SELECT s.*,o.order_number,o.customer_name,o.customer_phone,o.total,cp.name as courier_name FROM shipments s LEFT JOIN orders o ON o.id=s.order_id LEFT JOIN courier_providers cp ON cp.id=s.courier_provider_id ORDER BY s.created_at DESC LIMIT 50");}catch(Exception$e){}
$editProvider = isset($_GET['edit']) ? $db->fetch("SELECT * FROM courier_providers WHERE id=?",[intval($_GET['edit'])]) : null;

$pc = [
    'client_id'     => getSetting('pathao_client_id',''),
    'client_secret' => getSetting('pathao_client_secret',''),
    'username'      => getSetting('pathao_username',''),
    'password'      => getSetting('pathao_password',''),
    'environment'   => getSetting('pathao_environment','production'),
    'store_id'      => getSetting('pathao_store_id',''),
];
$tokenExp = intval(getSetting('pathao_token_expiry','0'));
$connected = !empty($pc['client_id']) && !empty(getSetting('pathao_access_token','')) && $tokenExp > time();

// Steadfast settings
$sf = [
    'api_key'    => getSetting('steadfast_api_key',''),
    'secret_key' => getSetting('steadfast_secret_key',''),
    'webhook_token' => getSetting('steadfast_webhook_token',''),
];
$sfConnected = !empty($sf['api_key']) && !empty($sf['secret_key']);

// CarryBee settings
$cb = [
    'api_key'    => getSetting('carrybee_api_key',''),
    'secret_key' => getSetting('carrybee_secret_key',''),
];
$cbConnected = !empty($cb['api_key']);

require_once __DIR__ . '/../includes/header.php';
?>

<style>.tab-on{background:#2563eb;color:#fff}.pulse-d{animation:pd 2s infinite}@keyframes pd{0%,100%{opacity:1}50%{opacity:.4}}</style>

<?php if(isset($_GET['msg'])): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">✅ Action completed.</div><?php endif; ?>

<!-- TABS -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach(['pathao'=>'🚀 Pathao API','steadfast'=>'📦 Steadfast','carrybee'=>'🐝 CarryBee','webhooks'=>'🔗 Webhooks','customer_check'=>'🔍 Customer Verify','area_map'=>'📊 Area Analytics','providers'=>'📦 Providers','shipments'=>'🚚 Shipments'] as $k=>$v): ?>
    <a href="?tab=<?=$k?>" class="px-4 py-2 rounded-lg text-sm font-medium <?=$tab===$k?'tab-on':'bg-gray-100 text-gray-600 hover:bg-gray-200'?>"><?=$v?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'pathao'): ?>
<!-- ========================================= -->
<!-- PATHAO API CONNECTION -->
<!-- ========================================= -->
<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Connection Card -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center"><span class="text-2xl font-bold text-red-600">P</span></div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg">Pathao Merchant API</h3>
                        <p class="text-xs text-gray-500">Get credentials from <a href="https://merchant.pathao.com/courier/developer-api" target="_blank" class="text-blue-600 underline">merchant.pathao.com → Developer API</a></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full <?=$connected?'bg-green-50 border border-green-200':'bg-gray-50 border'?>">
                    <span class="w-2.5 h-2.5 rounded-full <?=$connected?'bg-green-500 pulse-d':'bg-gray-300'?>"></span>
                    <span class="text-xs font-semibold <?=$connected?'text-green-700':'text-gray-500'?>"><?=$connected?'Connected':'Disconnected'?></span>
                </div>
            </div>

            <!-- Credential Fields -->
            <div class="bg-gray-50 rounded-xl p-5 mb-5 border border-dashed border-gray-300">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">🔑 Merchant API Credentials</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Client ID <span class="text-red-500">*</span></label>
                        <input type="text" id="p_client_id" value="<?=e($pc['client_id'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" placeholder="e.g. 267">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Client Secret <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="p_client_secret" value="<?=e($pc['client_secret'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono bg-white pr-10" placeholder="Your client secret key">
                            <button onclick="togglePass(this)" class="absolute right-2 top-2.5 text-gray-400 hover:text-gray-600">👁</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 rounded-xl p-5 mb-5 border border-dashed border-blue-200">
                <h4 class="text-sm font-semibold text-blue-700 mb-3">👤 Merchant Login Credentials</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Merchant Email <span class="text-red-500">*</span></label>
                        <input type="email" id="p_username" value="<?=e($pc['username'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm bg-white" placeholder="your@merchant-email.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="p_password" value="<?=e($pc['password'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm bg-white pr-10" placeholder="Merchant password">
                            <button onclick="togglePass(this)" class="absolute right-2 top-2.5 text-gray-400 hover:text-gray-600">👁</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 rounded-xl p-5 mb-5 border border-dashed border-yellow-200">
                <h4 class="text-sm font-semibold text-yellow-700 mb-3">⚙️ Configuration</h4>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Environment</label>
                        <select id="p_environment" class="w-full px-3 py-2.5 border rounded-lg text-sm bg-white">
                            <option value="production" <?=($pc['environment']??'')==='production'?'selected':''?>>🟢 Production — api-hermes.pathao.com</option>
                            <option value="sandbox" <?=($pc['environment']??'')==='sandbox'?'selected':''?>>🟡 Sandbox — hermes-api.p-stageenv.xyz</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Default Store ID</label>
                        <div class="flex gap-2">
                            <input type="text" id="p_store_id" value="<?=e($pc['store_id'])?>" class="flex-1 px-3 py-2.5 border rounded-lg text-sm font-mono bg-white" placeholder="Auto-fetched">
                            <button onclick="fetchStores()" class="px-3 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50 font-medium" title="Fetch stores">🏪 Fetch</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3">
                <button onclick="savePathaoConfig()" id="saveBtn" class="bg-blue-600 text-white px-8 py-3 rounded-lg text-sm font-semibold hover:bg-blue-700 shadow-sm">💾 Save & Connect</button>
                <button onclick="testConn()" class="bg-white border-2 border-gray-200 text-gray-700 px-6 py-3 rounded-lg text-sm font-semibold hover:bg-gray-50">🔌 Test Connection</button>
            </div>
            <div id="connMsg" class="hidden mt-4 px-4 py-3 rounded-lg text-sm font-medium"></div>
        </div>

        <!-- Available Endpoints -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h4 class="font-semibold text-gray-800 mb-4">📡 API Endpoints Integrated</h4>
            <div class="grid md:grid-cols-2 gap-2 text-sm">
                <?php foreach([
                    ['POST','Issue Token','OAuth2 Authentication','✅'],
                    ['GET','City List','All BD cities','✅'],
                    ['GET','Zone List','Zones per city','✅'],
                    ['GET','Area List','Areas per zone','✅'],
                    ['GET','Store List','Your pickup stores','✅'],
                    ['POST','Create Order','Book courier pickup','✅'],
                    ['POST','Price Plan','Delivery cost calc','✅'],
                    ['POST','Customer Check','Phone verification','✅'],
                ] as $ep): ?>
                <div class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-lg">
                    <span class="px-2 py-0.5 <?=$ep[0]==='GET'?'bg-green-100 text-green-700':'bg-blue-100 text-blue-700'?> rounded text-xs font-mono font-bold"><?=$ep[0]?></span>
                    <span class="text-gray-800 font-medium"><?=$ep[1]?></span>
                    <span class="ml-auto text-xs text-gray-400"><?=$ep[2]?></span>
                    <span class="text-green-500"><?=$ep[3]?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-5">
        <!-- Connection Status -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-4">🔗 Connection Status</h4>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">API</span><span class="font-semibold <?=$connected?'text-green-600':'text-red-500'?>"><?=$connected?'✅ Active':'❌ Inactive'?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Environment</span><span class="font-medium"><?=ucfirst($pc['environment']?:'N/A')?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Store ID</span><span class="font-mono text-xs"><?=$pc['store_id']?:'—'?></span></div>
                <?php $daysLeft = $tokenExp > time() ? max(0,round(($tokenExp-time())/86400)) : 0; ?>
                <div class="flex justify-between"><span class="text-gray-500">Token Expires</span><span class="font-medium <?=$daysLeft<3?'text-red-600':'text-green-600'?>"><?=$tokenExp?$daysLeft.'d left':'Not set'?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">Base URL</span><span class="text-xs text-gray-400 truncate max-w-[160px]"><?=($pc['environment']??'')==='sandbox'?'hermes-api.p-stageenv.xyz':'api-hermes.pathao.com'?></span></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-3">⚡ Quick Actions</h4>
            <div class="space-y-2">
                <button onclick="fetchStores()" class="w-full text-left px-3 py-2.5 bg-gray-50 rounded-lg text-sm hover:bg-gray-100 transition">🏪 Fetch Stores</button>
                <button onclick="loadCities()" class="w-full text-left px-3 py-2.5 bg-gray-50 rounded-lg text-sm hover:bg-gray-100 transition">🏙️ Load City List</button>
                <button onclick="testConn()" class="w-full text-left px-3 py-2.5 bg-gray-50 rounded-lg text-sm hover:bg-gray-100 transition">🔄 Refresh Token</button>
            </div>
        </div>

        <!-- Stores -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-3">🏪 Your Pathao Stores</h4>
            <div id="storesList" class="space-y-2 text-sm text-gray-400"><p>Click "Fetch Stores" to load</p></div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'steadfast'): ?>
<!-- ========================================= -->
<!-- STEADFAST API -->
<!-- ========================================= -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center"><span class="text-2xl font-bold text-blue-600">S</span></div>
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">Steadfast Courier API</h3>
                    <p class="text-xs text-gray-500">Get credentials from <a href="https://portal.steadfast.com.bd" target="_blank" class="text-blue-600 underline">portal.steadfast.com.bd</a></p>
                </div>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full <?=$sfConnected?'bg-green-50 border border-green-200':'bg-gray-50 border'?>">
                <span class="w-2.5 h-2.5 rounded-full <?=$sfConnected?'bg-green-500 pulse-d':'bg-gray-300'?>"></span>
                <span class="text-xs font-semibold <?=$sfConnected?'text-green-700':'text-gray-500'?>"><?=$sfConnected?'Configured':'Not Configured'?></span>
            </div>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">API Key <span class="text-red-500">*</span></label>
                <input type="text" id="sf_api_key" value="<?=e($sf['api_key'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="Your Steadfast API Key">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Secret Key <span class="text-red-500">*</span></label>
                <input type="password" id="sf_secret_key" value="<?=e($sf['secret_key'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="Your Steadfast Secret Key">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Webhook Bearer Token (optional)</label>
                <input type="text" id="sf_webhook_token" value="<?=e($sf['webhook_token'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="Token for webhook authentication">
                <p class="text-[10px] text-gray-400 mt-1">Set this in Steadfast dashboard → Webhook → Auth Token (Bearer)</p>
            </div>
            <div class="flex gap-3">
                <button onclick="saveSteadfast()" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700">💾 Save</button>
                <button onclick="testSteadfast()" class="bg-gray-100 text-gray-700 px-4 py-2.5 rounded-lg text-sm hover:bg-gray-200">🔌 Test Connection</button>
            </div>
            <div id="sf_result" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
        </div>
    </div>
</div>
<script>
function saveSteadfast(){
    fetch('<?=SITE_URL?>/api/pathao-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_steadfast',api_key:document.getElementById('sf_api_key').value,secret_key:document.getElementById('sf_secret_key').value,webhook_token:document.getElementById('sf_webhook_token').value})}).then(r=>r.json()).then(d=>{const el=document.getElementById('sf_result');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-green-50 text-green-700';el.textContent='✅ Steadfast settings saved!';setTimeout(()=>location.reload(),1000)}).catch(e=>{const el=document.getElementById('sf_result');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';el.textContent='Error: '+e.message});
}
function testSteadfast(){
    fetch('<?=SITE_URL?>/api/pathao-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'test_steadfast'})}).then(r=>r.json()).then(d=>{const el=document.getElementById('sf_result');el.classList.remove('hidden');if(d.success){el.className='mt-3 p-3 rounded-lg text-sm bg-green-50 text-green-700';el.textContent='✅ Connected! Balance: ৳'+d.balance}else{el.className='mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';el.textContent='❌ '+(d.error||'Connection failed')}}).catch(e=>{const el=document.getElementById('sf_result');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';el.textContent='Error: '+e.message});
}
</script>

<?php elseif ($tab === 'carrybee'): ?>
<!-- ========================================= -->
<!-- CARRYBEE -->
<!-- ========================================= -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center"><span class="text-2xl">🐝</span></div>
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">CarryBee Courier</h3>
                    <p class="text-xs text-gray-500">Configure CarryBee API credentials</p>
                </div>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full <?=$cbConnected?'bg-green-50 border border-green-200':'bg-gray-50 border'?>">
                <span class="w-2.5 h-2.5 rounded-full <?=$cbConnected?'bg-green-500':'bg-gray-300'?>"></span>
                <span class="text-xs font-semibold <?=$cbConnected?'text-green-700':'text-gray-500'?>"><?=$cbConnected?'Configured':'Not Configured'?></span>
            </div>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">API Key</label>
                <input type="text" id="cb_api_key" value="<?=e($cb['api_key'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="CarryBee API Key">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Secret Key</label>
                <input type="password" id="cb_secret_key" value="<?=e($cb['secret_key'])?>" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono" placeholder="CarryBee Secret Key">
            </div>
            <button onclick="saveCarryBee()" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700">💾 Save</button>
            <div id="cb_result" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
        </div>
    </div>
</div>
<script>
function saveCarryBee(){
    fetch('<?=SITE_URL?>/api/pathao-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_carrybee',api_key:document.getElementById('cb_api_key').value,secret_key:document.getElementById('cb_secret_key').value})}).then(r=>r.json()).then(d=>{const el=document.getElementById('cb_result');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-green-50 text-green-700';el.textContent='✅ CarryBee settings saved!';setTimeout(()=>location.reload(),1000)}).catch(e=>{const el=document.getElementById('cb_result');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';el.textContent='Error: '+e.message});
}
</script>

<?php elseif ($tab === 'webhooks'): ?>
<!-- ========================================= -->
<!-- WEBHOOK URLS & AUTO-SYNC -->
<!-- ========================================= -->
<div class="max-w-3xl space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 text-lg mb-2">🔗 Courier Webhook URLs</h3>
        <p class="text-sm text-gray-500 mb-6">Set these URLs in each courier's merchant dashboard to receive automatic status updates. When a courier delivers, returns, or cancels — your order status updates automatically.</p>
        
        <div class="space-y-4">
            <?php 
            $baseUrl = SITE_URL . '/api/courier-webhook.php';
            $webhooks = [
                ['Pathao', 'pathao', 'bg-red-50 border-red-200', 'text-red-600', 'Merchant Dashboard → Developer API → Webhook URL'],
                ['Steadfast', 'steadfast', 'bg-blue-50 border-blue-200', 'text-blue-600', 'Steadfast Dashboard → API → Webhook → Callback URL'],
                ['CarryBee', 'carrybee', 'bg-green-50 border-green-200', 'text-green-600', 'CarryBee Dashboard → Settings → Webhook URL'],
            ];
            foreach ($webhooks as [$name, $code, $bg, $tc, $hint]): 
                $url = $baseUrl . '?courier=' . $code;
            ?>
            <div class="<?= $bg ?> rounded-xl border p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold <?= $tc ?>"><?= $name ?> Webhook</span>
                    <button onclick="copyUrl('<?= e($url) ?>', this)" class="text-xs bg-white px-2 py-1 rounded border hover:bg-gray-50">📋 Copy</button>
                </div>
                <code class="block text-xs font-mono text-gray-700 bg-white px-3 py-2 rounded-lg border break-all"><?= e($url) ?></code>
                <p class="text-[10px] text-gray-500 mt-1.5">📌 <?= $hint ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 text-lg mb-2">🔄 Auto-Sync (Polling)</h3>
        <p class="text-sm text-gray-500 mb-4">As a backup to webhooks, you can also poll courier APIs for status updates. Use the "Sync Courier Status" button in Order Management, or set up a cron job:</p>
        
        <div class="bg-gray-50 rounded-xl border border-dashed p-4 mb-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Cron Job URL</label>
            <?php $cronKey = getSetting('courier_sync_key',''); if(empty($cronKey)){$cronKey=bin2hex(random_bytes(16)); try{$db->query("INSERT INTO site_settings (setting_key,setting_value,setting_type,setting_group,label) VALUES ('courier_sync_key',?,'text','courier','Courier Sync Key') ON DUPLICATE KEY UPDATE setting_value=?",[$cronKey,$cronKey]);}catch(\Throwable $e){}} ?>
            <code class="block text-xs font-mono text-gray-700 bg-white px-3 py-2 rounded-lg border break-all mb-2"><?= e(SITE_URL) ?>/api/courier-sync.php?key=<?= e($cronKey) ?></code>
            <p class="text-[10px] text-gray-500">Set this as a cron job every 30 minutes: <code class="bg-white px-1 rounded">*/30 * * * * curl -s "<?= e(SITE_URL) ?>/api/courier-sync.php?key=<?= e($cronKey) ?>" > /dev/null</code></p>
        </div>
        
        <button onclick="runSync()" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">🔄 Run Sync Now</button>
        <div id="syncResult" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 text-lg mb-4">📊 Status Flow</h3>
        <p class="text-sm text-gray-500 mb-4">After an order is marked <strong>Shipped</strong>, courier APIs automatically update the status:</p>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                <p class="text-sm font-bold text-green-700 mb-1">📦 Delivered</p>
                <p class="text-xs text-green-600">Courier confirms delivery → Auto-updated + credits awarded</p>
            </div>
            <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                <p class="text-sm font-bold text-amber-700 mb-1">🔄 Pending Return</p>
                <p class="text-xs text-amber-600">Courier marks return → Stays here for staff to confirm manually</p>
            </div>
            <div class="bg-pink-50 rounded-lg p-3 border border-pink-200">
                <p class="text-sm font-bold text-pink-700 mb-1">⏳ Pending Cancel</p>
                <p class="text-xs text-pink-600">Courier cancels delivery → Stays here for staff to confirm</p>
            </div>
            <div class="bg-cyan-50 rounded-lg p-3 border border-cyan-200">
                <p class="text-sm font-bold text-cyan-700 mb-1">📦½ Partial Delivered</p>
                <p class="text-xs text-cyan-600">Partial delivery by courier → Staff decides next step</p>
            </div>
        </div>
    </div>
</div>
<script>
function copyUrl(url,btn){navigator.clipboard.writeText(url);btn.textContent='✅ Copied!';setTimeout(()=>btn.textContent='📋 Copy',2000)}
function runSync(){
    const el=document.getElementById('syncResult');el.classList.remove('hidden');el.className='mt-3 p-3 rounded-lg text-sm bg-blue-50 text-blue-700';el.textContent='🔄 Syncing...';
    fetch('<?=SITE_URL?>/api/courier-sync.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({limit:50})})
    .then(r=>r.json()).then(d=>{
        el.className='mt-3 p-3 rounded-lg text-sm bg-green-50 text-green-700';
        el.innerHTML='✅ Synced '+d.total+' orders: <strong>'+d.updated+'</strong> updated, '+d.skipped+' skipped, '+d.errors+' errors'+(d.details?.length?'<br><small>'+d.details.join('<br>')+'</small>':'');
    }).catch(e=>{el.className='mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';el.textContent='Error: '+e.message});
}
</script>

<?php elseif ($tab === 'customer_check'): ?>
<!-- ========================================= -->
<!-- CUSTOMER VERIFICATION -->
<!-- ========================================= -->
<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="font-bold text-gray-800 text-lg mb-1">🔍 Customer Delivery Verification</h3>
            <p class="text-sm text-gray-500 mb-5">Check customer's courier delivery history using phone number. Combines your local order data + Pathao courier records to build a success rate profile.</p>
            <div class="flex gap-3">
                <div class="relative flex-1">
                    <span class="absolute left-3 top-3.5 text-gray-400">📱</span>
                    <input type="tel" id="checkPhone" placeholder="01XXXXXXXXX" class="w-full pl-10 pr-4 py-3 border-2 rounded-xl text-lg font-mono focus:border-blue-500 focus:ring-2 focus:ring-blue-200" onkeydown="if(event.key==='Enter')checkCustomer()">
                </div>
                <button onclick="checkCustomer()" id="checkBtn" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700 shadow-sm whitespace-nowrap">🔍 Verify</button>
            </div>
        </div>
        <div id="customerResult" class="hidden"></div>
    </div>
    <div class="space-y-5">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-4">📊 Top Order Areas <span class="text-xs text-gray-400 font-normal">(90 days)</span></h4>
            <div id="areaStats" class="space-y-2 text-sm"><p class="text-gray-400">Loading...</p></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h4 class="font-semibold text-gray-800 mb-3">ℹ️ How It Works</h4>
            <div class="text-xs text-gray-500 space-y-2">
                <p>1. Enter customer phone number</p>
                <p>2. System checks your local order history for success/fail rates</p>
                <p>3. If Pathao API is connected, fetches their courier delivery rating</p>
                <p>4. Combined risk score: <span class="text-green-600 font-medium">Low</span>, <span class="text-yellow-600 font-medium">Medium</span>, <span class="text-red-600 font-medium">High</span>, or <span class="text-blue-600 font-medium">New</span></p>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'area_map'): ?>
<!-- ========================================= -->
<!-- AREA ANALYTICS -->
<!-- ========================================= -->
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-bold text-gray-800 text-lg">📊 Order Area Analytics</h3>
                <p class="text-sm text-gray-500">See which areas most orders come from, with delivery success rates</p>
            </div>
            <select id="areaDays" onchange="loadAreaChart()" class="px-3 py-2 border rounded-lg text-sm">
                <option value="30">Last 30 days</option>
                <option value="90" selected>Last 90 days</option>
                <option value="180">Last 180 days</option>
                <option value="365">Last year</option>
            </select>
        </div>
        <div id="areaChartContainer">
            <div class="text-center py-8 text-gray-400">Loading area data...</div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h4 class="font-semibold text-gray-800 mb-4">📈 Top Performing Areas</h4>
            <div id="topAreas" class="space-y-3 text-sm"><p class="text-gray-400">Loading...</p></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h4 class="font-semibold text-gray-800 mb-4">⚠️ Highest Failure Rate</h4>
            <div id="worstAreas" class="space-y-3 text-sm"><p class="text-gray-400">Loading...</p></div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'providers'): ?>
<!-- ========================================= -->
<!-- PROVIDERS -->
<!-- ========================================= -->
<div class="grid lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h4 class="font-semibold text-gray-800 mb-4"><?=$editProvider?'Edit':'Add'?> Provider</h4>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_provider"><input type="hidden" name="provider_id" value="<?=$editProvider['id']??0?>">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Name *</label><input type="text" name="name" value="<?=e($editProvider['name']??'')?>" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">API URL</label><input type="url" name="api_url" value="<?=e($editProvider['api_url']??'')?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">API Key</label><input type="text" name="api_key" value="<?=e($editProvider['api_key']??'')?>" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">API Secret</label><input type="password" name="api_secret" value="<?=e($editProvider['api_secret']??'')?>" class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></div>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?=($editProvider['is_active']??1)?'checked':''?> class="rounded"><span class="text-sm">Active</span></label>
            <button class="w-full bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700"><?=$editProvider?'Update':'Add Provider'?></button>
        </form>
    </div>
    <div class="lg:col-span-2">
        <div class="grid md:grid-cols-2 gap-4">
            <?php foreach($providers as $p): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <div class="flex items-center justify-between mb-2"><h5 class="font-semibold"><?=e($p['name'])?></h5><span class="px-2 py-0.5 text-xs rounded-full <?=$p['is_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500'?>"><?=$p['is_active']?'Active':'Off'?></span></div>
                <p class="text-sm text-gray-600 mb-3">Shipments: <strong><?=$p['shipment_count']?></strong></p>
                <div class="flex gap-3"><a href="?tab=providers&edit=<?=$p['id']?>" class="text-blue-600 text-sm hover:underline">Edit</a>
                <form method="POST" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_provider"><input type="hidden" name="provider_id" value="<?=$p['id']?>"><button class="text-red-600 text-sm hover:underline">Delete</button></form></div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($providers)):?><div class="col-span-2 text-center py-8 text-gray-400">No providers</div><?php endif;?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ========================================= -->
<!-- SHIPMENTS -->
<!-- ========================================= -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50"><tr>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Order</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Customer</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Courier</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Tracking</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">COD</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
            <th class="px-4 py-3 text-left font-medium text-gray-600">Date</th>
        </tr></thead>
        <tbody class="divide-y">
            <?php foreach($shipments as $sh): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><a href="<?=adminUrl('pages/order-view.php?id='.$sh['order_id'])?>" class="text-blue-600 hover:underline">#<?=e($sh['order_number'])?></a></td>
                <td class="px-4 py-3"><?=e($sh['customer_name'])?><br><span class="text-xs text-gray-400"><?=e($sh['customer_phone'])?></span></td>
                <td class="px-4 py-3"><?=e($sh['courier_name'])?></td>
                <td class="px-4 py-3 font-mono text-xs"><?=e($sh['tracking_number'])?></td>
                <td class="px-4 py-3 font-medium">৳<?=number_format($sh['cod_amount'])?></td>
                <td class="px-4 py-3">
                    <form method="POST"><input type="hidden" name="action" value="update_shipment"><input type="hidden" name="shipment_id" value="<?=$sh['id']?>">
                    <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border">
                        <?php foreach(['pending','picked_up','in_transit','out_for_delivery','delivered','returned','cancelled'] as $st):?>
                        <option value="<?=$st?>" <?=$sh['status']===$st?'selected':''?>><?=ucfirst(str_replace('_',' ',$st))?></option>
                        <?php endforeach;?>
                    </select></form>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500"><?=date('M d, h:i A',strtotime($sh['created_at']))?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($shipments)):?><tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">No shipments</td></tr><?php endif;?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<script>
const PAPI = '<?=SITE_URL?>/api/pathao-api.php';

function togglePass(btn) { const i = btn.previousElementSibling; i.type = i.type==='password'?'text':'password'; }

// ========== PATHAO CONFIG ==========
async function savePathaoConfig() {
    const btn = document.getElementById('saveBtn');
    btn.disabled=true; btn.textContent='⏳ Connecting...';
    try {
        const res = await fetch(PAPI+'?action=save_config', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                client_id: el('p_client_id').value,
                client_secret: el('p_client_secret').value,
                username: el('p_username').value,
                password: el('p_password').value,
                environment: el('p_environment').value,
                store_id: el('p_store_id').value,
            })
        });
        const j=await res.json();
        showMsg(j.success, j.message||(j.success?'✅ Connected!':'❌ Failed'));
        if(j.success) setTimeout(()=>location.reload(), 1500);
    } catch(e) { showMsg(false, e.message); }
    btn.disabled=false; btn.textContent='💾 Save & Connect';
}

async function testConn() {
    showMsg(null,'⏳ Testing connection...');
    try { const r=await(await fetch(PAPI+'?action=test_connection')).json(); showMsg(r.success, r.message); }
    catch(e) { showMsg(false, e.message); }
}

function showMsg(ok, msg) {
    const e=el('connMsg'); e.classList.remove('hidden');
    e.className='mt-4 px-4 py-3 rounded-lg text-sm font-medium border '+(ok===null?'bg-blue-50 text-blue-700 border-blue-200':ok?'bg-green-50 text-green-700 border-green-200':'bg-red-50 text-red-700 border-red-200');
    e.textContent=msg;
}

async function fetchStores() {
    const sl=el('storesList'); sl.innerHTML='<p class="text-gray-400">Loading...</p>';
    try {
        const j=await(await fetch(PAPI+'?action=get_stores')).json();
        const stores=j.data?.data||j.data||[];
        if(!stores.length){sl.innerHTML='<p class="text-gray-400">No stores found</p>';return;}
        sl.innerHTML=stores.map(s=>`<div class="p-2.5 bg-gray-50 rounded-lg cursor-pointer hover:bg-blue-50 transition border" onclick="el('p_store_id').value='${s.store_id}'">
            <p class="font-medium text-gray-800 text-xs">${s.store_name}</p><p class="text-xs text-gray-400">ID: ${s.store_id} · ${s.store_address||''}</p></div>`).join('');
    } catch(e) { sl.innerHTML=`<p class="text-red-500 text-xs">${e.message}</p>`; }
}

async function loadCities() {
    try { const j=await(await fetch(PAPI+'?action=get_cities')).json(); const c=j.data?.data||j.data||[]; alert('✅ Loaded '+c.length+' cities from Pathao API'); }
    catch(e) { alert('Error: '+e.message); }
}

function el(id){return document.getElementById(id);}

// ========== CUSTOMER CHECK ==========
async function checkCustomer() {
    const phone=el('checkPhone').value.trim();
    if(!phone||phone.length<10){alert('Enter valid phone number');return;}
    const btn=el('checkBtn'); btn.disabled=true; btn.innerHTML='⏳ Checking...';
    const div=el('customerResult'); div.classList.remove('hidden');
    div.innerHTML='<div class="bg-white rounded-xl border p-8 text-center text-gray-400"><div class="animate-pulse">Checking customer history...</div></div>';
    try {
        const j=await(await fetch(PAPI+'?action=check_customer&phone='+encodeURIComponent(phone))).json();
        if(!j.success||!j.data){div.innerHTML='<div class="bg-white rounded-xl border p-8 text-center text-gray-400">No data found</div>';return;}
        const d=j.data;
        const colors={low:'green',medium:'yellow',high:'red',new:'blue'};
        const rc=colors[d.risk_level]||'gray';
        div.innerHTML=`
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r ${d.risk_level==='high'?'from-red-50 to-red-100/50':d.risk_level==='medium'?'from-yellow-50 to-yellow-100/50':d.risk_level==='new'?'from-blue-50 to-blue-100/50':'from-green-50 to-green-100/50'} border-b flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="font-bold text-gray-800 text-lg">📋 ${d.phone}</h4>
                    <p class="text-xs text-gray-500 mt-0.5">First order: ${d.first_order?new Date(d.first_order).toLocaleDateString():'None'} · Latest: ${d.last_order?new Date(d.last_order).toLocaleDateString():'Never'}</p>
                </div>
                <div class="flex gap-2 items-center">
                    ${d.is_blocked?'<span class="bg-red-600 text-white px-3 py-1.5 rounded-full text-xs font-bold">🚫 BLOCKED</span>':''}
                    <span class="bg-${rc}-100 text-${rc}-800 px-4 py-2 rounded-full text-sm font-bold border border-${rc}-200">${d.risk_label}</span>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                    <div class="text-center p-4 bg-gray-50 rounded-xl border"><p class="text-3xl font-bold text-gray-800">${d.total_orders}</p><p class="text-xs text-gray-500 mt-1">Total Orders</p></div>
                    <div class="text-center p-4 bg-green-50 rounded-xl border border-green-100"><p class="text-3xl font-bold text-green-600">${d.delivered}</p><p class="text-xs text-gray-500 mt-1">Delivered ✅</p></div>
                    <div class="text-center p-4 bg-red-50 rounded-xl border border-red-100"><p class="text-3xl font-bold text-red-600">${d.cancelled}</p><p class="text-xs text-gray-500 mt-1">Cancelled ❌</p></div>
                    <div class="text-center p-4 bg-orange-50 rounded-xl border border-orange-100"><p class="text-3xl font-bold text-orange-600">${d.returned}</p><p class="text-xs text-gray-500 mt-1">Returned 🔄</p></div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-100"><p class="text-3xl font-bold text-blue-600">৳${Number(d.total_spent).toLocaleString()}</p><p class="text-xs text-gray-500 mt-1">Total Spent</p></div>
                </div>
                <div class="mb-5">
                    <div class="flex justify-between text-sm mb-2"><span class="text-gray-700 font-semibold">Delivery Success Rate</span><span class="text-lg font-bold ${d.success_rate>=70?'text-green-600':d.success_rate>=40?'text-yellow-600':'text-red-600'}">${d.success_rate}%</span></div>
                    <div class="w-full h-4 bg-gray-200 rounded-full overflow-hidden"><div class="h-full rounded-full transition-all duration-500 ${d.success_rate>=70?'bg-green-500':d.success_rate>=40?'bg-yellow-500':'bg-red-500'}" style="width:${d.success_rate}%"></div></div>
                </div>
                ${d.is_blocked?`<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 font-medium">🚫 <strong>Blocked Customer:</strong> ${d.block_reason||'No reason specified'}</div>`:''}
                ${d.pathao_data?`<div class="bg-purple-50 border border-purple-200 px-4 py-3 rounded-xl text-sm mb-4"><p class="font-semibold text-purple-800">📦 Pathao Courier Record</p><p class="text-purple-600 mt-1">Rating: <strong>${d.pathao_data.customer_rating||d.pathao_data.rating||'N/A'}</strong> · Risk Level: <strong>${d.pathao_data.risk_level||'N/A'}</strong></p>${d.pathao_data.total?`<p class="text-purple-600">Total: ${d.pathao_data.total} · Success: ${d.pathao_data.success} · Cancel: ${d.pathao_data.cancel}</p>`:''}</div>`:''}
                ${d.areas?.length?`<div class="mt-4"><p class="text-sm font-semibold text-gray-700 mb-2">📍 Delivery Areas Used</p><div class="flex flex-wrap gap-2">${d.areas.map(a=>`<span class="bg-gray-100 px-3 py-1.5 rounded-full text-xs font-medium border">${a.area_name} <span class="text-gray-400">(${a.cnt})</span></span>`).join('')}</div></div>`:''}
            </div>
        </div>`;
    } catch(e) { div.innerHTML=`<div class="bg-red-50 border rounded-xl p-6 text-red-600">${e.message}</div>`; }
    btn.disabled=false; btn.innerHTML='🔍 Verify';
}

// ========== AREA STATS (sidebar & analytics) ==========
async function loadAreaStats(target='areaStats', days=90) {
    try {
        const j=await(await fetch(PAPI+'?action=area_stats&days='+days)).json();
        const e=document.getElementById(target);
        if(!e) return j.data;
        if(j.data?.length) {
            const mx=Math.max(...j.data.map(d=>parseInt(d.total_orders)));
            e.innerHTML=j.data.slice(0,15).map(a=>{
                const p=Math.round((a.total_orders/mx)*100);
                const s=a.total_orders>0?Math.round((a.delivered/a.total_orders)*100):0;
                return `<div class="p-2 rounded-lg hover:bg-gray-50 transition">
                    <div class="flex justify-between mb-1"><span class="font-medium text-gray-700 text-xs truncate" style="max-width:140px">${a.area_name}</span><span class="text-xs text-gray-500 font-medium">${a.total_orders} orders</span></div>
                    <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500 rounded-full" style="width:${p}%"></div></div>
                    <div class="flex justify-between mt-0.5"><span class="text-xs ${s>=70?'text-green-600':s>=40?'text-yellow-600':'text-red-600'}">${s}% success</span><span class="text-xs text-gray-400">৳${Number(a.revenue||0).toLocaleString()}</span></div>
                </div>`;
            }).join('');
        } else e.innerHTML='<p class="text-gray-400">No data</p>';
        return j.data;
    } catch(e) { console.error(e); return []; }
}

<?php if($tab==='customer_check'):?>loadAreaStats();<?php endif;?>

<?php if($tab==='area_map'):?>
async function loadAreaChart() {
    const days=el('areaDays').value;
    const data = await loadAreaStats(null, days);
    if(!data?.length){el('areaChartContainer').innerHTML='<p class="text-center py-8 text-gray-400">No data</p>';return;}
    const mx=Math.max(...data.map(d=>parseInt(d.total_orders)));
    el('areaChartContainer').innerHTML=`
        <div class="space-y-2">${data.map(a=>{
            const pct=Math.round((a.total_orders/mx)*100);
            const s=a.total_orders>0?Math.round((a.delivered/a.total_orders)*100):0;
            const f=a.total_orders>0?Math.round((a.failed/a.total_orders)*100):0;
            return `<div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                <span class="w-32 text-sm font-medium text-gray-700 truncate">${a.area_name}</span>
                <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden flex">
                    <div class="h-full bg-green-500 transition-all" style="width:${s*pct/100}%" title="${s}% success"></div>
                    <div class="h-full bg-red-400 transition-all" style="width:${f*pct/100}%" title="${f}% failed"></div>
                </div>
                <span class="text-sm font-bold text-gray-800 w-16 text-right">${a.total_orders}</span>
                <span class="text-xs w-20 text-right ${s>=70?'text-green-600':s>=40?'text-yellow-600':'text-red-600'}">${s}% ✓</span>
                <span class="text-xs text-gray-400 w-24 text-right">৳${Number(a.revenue||0).toLocaleString()}</span>
            </div>`;
        }).join('')}</div>`;
    // Top & Worst areas
    const sorted=[...data].filter(a=>a.total_orders>=2);
    sorted.sort((a,b)=>{const sa=a.total_orders>0?(a.delivered/a.total_orders):0;const sb=b.total_orders>0?(b.delivered/b.total_orders):0;return sb-sa;});
    el('topAreas').innerHTML=sorted.slice(0,8).map(a=>{const s=Math.round((a.delivered/a.total_orders)*100);return `<div class="flex justify-between items-center"><span class="text-gray-700">${a.area_name}</span><div class="flex items-center gap-2"><span class="text-green-600 font-bold">${s}%</span><span class="text-xs text-gray-400">${a.total_orders} orders</span></div></div>`;}).join('');
    sorted.reverse();
    el('worstAreas').innerHTML=sorted.slice(0,8).map(a=>{const f=Math.round((a.failed/a.total_orders)*100);return `<div class="flex justify-between items-center"><span class="text-gray-700">${a.area_name}</span><div class="flex items-center gap-2"><span class="text-red-600 font-bold">${f}% fail</span><span class="text-xs text-gray-400">${a.total_orders} orders</span></div></div>`;}).join('');
}
loadAreaChart();
<?php endif;?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
