<?php
/**
 * Customer Login & Registration
 */
$pageTitle = 'লগইন / রেজিস্ট্রেশন';
require_once __DIR__ . '/../includes/functions.php';

$redirectTo = sanitize($_GET['redirect'] ?? $_POST['redirect'] ?? url());
$error = '';
$success = '';
$tab = $_POST['tab'] ?? 'login';

// Handle logout FIRST (before login check)
if (($_GET['action'] ?? '') === 'logout') {
    customerLogout();
    redirect(url('login'));
}

// Already logged in
if (isCustomerLoggedIn()) {
    redirect(url('account'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $phone = sanitize($_POST['phone']);
        $password = $_POST['password'];
        
        if (empty($phone) || empty($password)) {
            $error = 'ফোন নম্বর ও পাসওয়ার্ড দিন।';
        } elseif (isPhoneBlocked($phone)) {
            $error = 'এই ফোন নম্বর ব্লক করা হয়েছে।';
        } else {
            $customer = customerLogin($phone, $password);
            if ($customer) {
                redirect($redirectTo);
            } else {
                $error = 'ফোন নম্বর বা পাসওয়ার্ড ভুল।';
            }
        }
    }

    if ($action === 'register') {
        $tab = 'register';
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($name) || empty($phone) || empty($password)) {
            $error = 'সকল প্রয়োজনীয় তথ্য দিন।';
        } elseif (strlen($password) < 6) {
            $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
        } elseif ($password !== $confirmPassword) {
            $error = 'পাসওয়ার্ড মিলছে না।';
        } elseif (isPhoneBlocked($phone)) {
            $error = 'এই ফোন নম্বর ব্লক করা হয়েছে।';
        } else {
            $regData = ['name' => $name, 'phone' => $phone, 'email' => $email, 'password' => $password];
            // Collect extra dynamic fields
            $extraFields = ['address', 'city', 'district', 'alt_phone'];
            foreach ($extraFields as $ef) {
                if (isset($_POST[$ef]) && $_POST[$ef] !== '') {
                    $regData[$ef] = sanitize($_POST[$ef]);
                }
            }
            $result = customerRegister($regData);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                redirect($redirectTo);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
        <!-- Tabs -->
        <div class="grid grid-cols-2 border-b">
            <button onclick="switchTab('login')" id="tab-login" class="py-3.5 text-sm font-semibold text-center transition border-b-2 border-blue-600 text-blue-600">লগইন</button>
            <button onclick="switchTab('register')" id="tab-register" class="py-3.5 text-sm font-semibold text-center transition border-b-2 border-transparent text-gray-400">রেজিস্ট্রেশন</button>
        </div>

        <?php if ($error): ?>
        <div class="mx-6 mt-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?= $error ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="form-login" class="p-6 <?= $tab !== 'login' ? 'hidden' : '' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ফোন নম্বর</label>
                        <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" required 
                               class="w-full border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="01XXXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">পাসওয়ার্ড</label>
                        <input type="password" name="password" required 
                               class="w-full border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="••••••">
                    </div>
                    <button type="submit" class="w-full btn-primary py-3 rounded-xl text-sm font-semibold">লগইন করুন</button>
                </div>
            </form>
            <div class="flex items-center justify-between mt-4">
                <a href="<?= url('forgot-password') ?>" class="text-sm text-blue-600 font-medium hover:text-blue-800">পাসওয়ার্ড ভুলে গেছেন?</a>
                <button onclick="switchTab('register')" class="text-sm text-gray-500 hover:text-gray-700">রেজিস্ট্রেশন করুন →</button>
            </div>
        </div>

        <!-- Register Form -->
        <div id="form-register" class="p-6 <?= $tab !== 'register' ? 'hidden' : '' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="tab" value="register">
                <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <div class="space-y-4">
                    <?php
                    $regFieldsJson = getSetting('registration_fields', '');
                    $regFields = $regFieldsJson ? json_decode($regFieldsJson, true) : null;
                    if (!$regFields) {
                        $regFields = [
                            ['key'=>'name','label'=>'নাম','type'=>'text','enabled'=>true,'required'=>true,'placeholder'=>'আপনার নাম'],
                            ['key'=>'phone','label'=>'ফোন নম্বর','type'=>'tel','enabled'=>true,'required'=>true,'placeholder'=>'01XXXXXXXXX'],
                            ['key'=>'email','label'=>'ইমেইল','type'=>'email','enabled'=>true,'required'=>false,'placeholder'=>'email@example.com'],
                            ['key'=>'password','label'=>'পাসওয়ার্ড','type'=>'password','enabled'=>true,'required'=>true,'placeholder'=>'কমপক্ষে ৬ অক্ষর'],
                            ['key'=>'confirm_password','label'=>'পাসওয়ার্ড নিশ্চিত করুন','type'=>'password','enabled'=>true,'required'=>true,'placeholder'=>'আবার পাসওয়ার্ড দিন'],
                        ];
                    }
                    // Deduplicate
                    $_rseen = [];
                    $regFields = array_values(array_filter($regFields, function($f) use (&$_rseen) {
                        $k = $f['key'] ?? ''; if (isset($_rseen[$k])) return false; $_rseen[$k] = true; return true;
                    }));
                    
                    foreach ($regFields as $rf):
                        if (!($rf['enabled'] ?? true)) continue;
                        $key = $rf['key'];
                        $label = $rf['label'] ?? $key;
                        $placeholder = $rf['placeholder'] ?? '';
                        $required = ($rf['required'] ?? false);
                        $reqAttr = $required ? 'required' : '';
                        $star = $required ? ' *' : '';
                        $inputType = match($key) {
                            'email' => 'email',
                            'phone', 'alt_phone' => 'tel',
                            'password', 'confirm_password' => 'password',
                            default => 'text'
                        };
                        $minLength = in_array($key, ['password', 'confirm_password']) ? 'minlength="6"' : '';
                        $postVal = e($_POST[$key] ?? '');
                        // Don't prefill password fields
                        if (in_array($key, ['password', 'confirm_password'])) $postVal = '';
                    ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($label) ?><?= $star ?></label>
                        <?php if ($key === 'address'): ?>
                        <textarea name="<?= $key ?>" <?= $reqAttr ?> placeholder="<?= e($placeholder) ?>" rows="2"
                                  class="w-full border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= $postVal ?></textarea>
                        <?php else: ?>
                        <input type="<?= $inputType ?>" name="<?= $key ?>" value="<?= $postVal ?>" <?= $reqAttr ?> <?= $minLength ?>
                               class="w-full border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="<?= e($placeholder) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="w-full btn-primary py-3 rounded-xl text-sm font-semibold">রেজিস্ট্রেশন করুন</button>
                </div>
            </form>
            <p class="text-center text-sm text-gray-500 mt-4">একাউন্ট আছে? <button onclick="switchTab('login')" class="text-blue-600 font-medium">লগইন করুন</button></p>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('form-login').classList.toggle('hidden', tab !== 'login');
    document.getElementById('form-register').classList.toggle('hidden', tab !== 'register');
    document.getElementById('tab-login').classList.toggle('border-blue-600', tab === 'login');
    document.getElementById('tab-login').classList.toggle('text-blue-600', tab === 'login');
    document.getElementById('tab-login').classList.toggle('border-transparent', tab !== 'login');
    document.getElementById('tab-login').classList.toggle('text-gray-400', tab !== 'login');
    document.getElementById('tab-register').classList.toggle('border-blue-600', tab === 'register');
    document.getElementById('tab-register').classList.toggle('text-blue-600', tab === 'register');
    document.getElementById('tab-register').classList.toggle('border-transparent', tab !== 'register');
    document.getElementById('tab-register').classList.toggle('text-gray-400', tab !== 'register');
}
<?php if ($tab === 'register'): ?>switchTab('register');<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
