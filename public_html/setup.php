<?php
/**
 * Admin Setup Script
 * Upload to public_html/ and visit in browser to set admin credentials
 * DELETE THIS FILE AFTER USE!
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');

    if (empty($username) || empty($password) || strlen($password) < 6) {
        $error = 'Username and password (min 6 chars) are required.';
    } else {
        $db = Database::getInstance();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Check if admin user exists
        $existing = $db->fetch("SELECT id FROM admin_users WHERE username = ?", [$username]);

        if ($existing) {
            // Update existing
            $db->update('admin_users', [
                'password' => $hash,
                'email' => $email ?: 'admin@myshop.com',
                'full_name' => $fullName ?: 'Super Admin',
                'is_active' => 1,
            ], 'id = ?', [$existing['id']]);
            $success = "Admin user '{$username}' password updated! You can now login.";
        } else {
            // Ensure role exists
            $role = $db->fetch("SELECT id FROM admin_roles WHERE id = 1");
            if (!$role) {
                $db->query("INSERT INTO admin_roles (id, role_name, permissions) VALUES (1, 'Super Admin', '{\"all\": true}')");
            }

            // Insert new admin
            $db->insert('admin_users', [
                'username' => $username,
                'email' => $email ?: 'admin@myshop.com',
                'password' => $hash,
                'full_name' => $fullName ?: 'Super Admin',
                'role_id' => 1,
                'is_active' => 1,
            ]);
            $success = "Admin user '{$username}' created! You can now login.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-2">Admin Setup</h1>
        <p class="text-gray-500 text-center text-sm mb-6">Create or reset admin credentials</p>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="/admin/login.php" class="font-bold underline">→ Go to Admin Login</a>
            <br><br>
            <strong class="text-red-600">⚠️ DELETE this setup.php file from your server now!</strong>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" value="admin" required
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="admin@myshop.com"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="full_name" value="Super Admin"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="text" name="password" value="admin123" required
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Minimum 6 characters. Change from default after login.</p>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
                Create / Reset Admin
            </button>
        </form>
    </div>
</body>
</html>
