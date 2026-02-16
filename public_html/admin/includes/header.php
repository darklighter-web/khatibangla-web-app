<?php
/**
 * Admin Header & Sidebar Layout - Dark/Light Theme + View-as-Role
 */
requireAdmin();
$stats = getDashboardStats();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$siteName = getSetting('site_name', 'E-Commerce');
$db = Database::getInstance();

// ── Theme System ──
$adminTheme = 'light';
try {
    $t = $db->fetch("SELECT admin_theme FROM admin_users WHERE id = ?", [getAdminId()]);
    if ($t && !empty($t['admin_theme'])) $adminTheme = $t['admin_theme'];
} catch (\Throwable $e) {}
$isDark = ($adminTheme === 'dark');

// ── View-as-Role System (super admin only) ──
$viewAsRole = null;
$viewAsRoleName = '';
$viewAsPermsCount = 0;
$realPermissions = $_SESSION['admin_permissions'] ?? [];
if (isSuperAdmin() && !empty($_SESSION['view_as_role_id'])) {
    $vrole = $db->fetch("SELECT * FROM admin_roles WHERE id = ?", [$_SESSION['view_as_role_id']]);
    if ($vrole) {
        $viewAsRole = $vrole;
        $viewAsRoleName = $vrole['role_name'];
        // Temporarily apply that role's permissions for sidebar rendering
        $_SESSION['_real_permissions'] = $realPermissions;
        $rolePerms = json_decode($vrole['permissions'], true);
        if (!is_array($rolePerms)) $rolePerms = [];
        $_SESSION['admin_permissions'] = $rolePerms;
        $viewAsPermsCount = count($rolePerms);
    }
}

// Roles for dropdown
$allRoles = [];
if (isSuperAdmin() || $viewAsRole) {
    try { $allRoles = $db->fetchAll("SELECT * FROM admin_roles ORDER BY id"); } catch (\Throwable $e) {}
}

function navLink($page, $label, $icon, $badge = 0) {
    global $currentPage, $isDark;
    if (!canViewPage($page)) return '';
    $active = ($currentPage === $page)
        ? ($isDark ? 'bg-gray-700 text-white' : 'bg-blue-700 text-white')
        : ($isDark ? 'text-gray-300 hover:bg-gray-700/60' : 'text-blue-100 hover:bg-blue-700/50');
    $href = adminUrl("pages/{$page}.php");
    $html = '<a href="' . $href . '" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition ' . $active . '">';
    $html .= $icon . '<span>' . $label . '</span>';
    if ($badge > 0) $html .= '<span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">' . $badge . '</span>';
    $html .= '</a>';
    return $html;
}

function navSection($title) {
    global $isDark;
    $color = $isDark ? 'text-gray-500' : 'text-blue-300/60';
    return '<div class="nav-section-header px-4 pt-5 pb-2"><p class="text-xs font-semibold ' . $color . ' uppercase tracking-wider">' . $title . '</p></div>';
}

function navGroup($id, $label, $icon, $children, $badge = 0) {
    global $currentPage, $isDark;
    // Filter children by permission
    $visibleChildren = array_filter($children, function($child) {
        return canViewPage($child['page']);
    });
    if (empty($visibleChildren)) return ''; // Hide entire group if no children visible
    
    $isOpen = false;
    foreach ($visibleChildren as $child) { if ($currentPage === $child['page']) { $isOpen = true; break; } }
    $openClass = $isOpen ? '' : 'hidden';
    $arrowClass = $isOpen ? 'rotate-90' : '';
    if ($isDark) {
        $btnClass = $isOpen ? 'bg-gray-700/60 text-white' : 'text-gray-300 hover:bg-gray-700/60';
        $borderClass = 'border-gray-600/40';
        $childActive = 'text-white font-semibold';
        $childNormal = 'text-gray-400 hover:text-white';
    } else {
        $btnClass = $isOpen ? 'bg-blue-700/50 text-white' : 'text-blue-100 hover:bg-blue-700/50';
        $borderClass = 'border-blue-600/40';
        $childActive = 'text-white font-semibold';
        $childNormal = 'text-blue-200/70 hover:text-white';
    }
    $html = '<div class="nav-group">';
    $html .= '<button onclick="toggleNav(\'' . $id . '\')" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition ' . $btnClass . '">';
    $html .= $icon . '<span>' . $label . '</span>';
    if ($badge > 0) $html .= '<span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full mr-1">' . $badge . '</span>';
    $html .= '<svg class="w-4 h-4 ml-auto transition-transform duration-200 nav-arrow-' . $id . ' ' . $arrowClass . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
    $html .= '</button>';
    $html .= '<div id="nav-' . $id . '" class="ml-6 mt-1 space-y-0.5 border-l ' . $borderClass . ' pl-3 ' . $openClass . '">';
    foreach ($visibleChildren as $child) {
        $active = ($currentPage === $child['page']) ? $childActive : $childNormal;
        $href = adminUrl("pages/{$child['page']}.php");
        $html .= '<a href="' . $href . '" class="block px-3 py-1.5 text-sm rounded-lg transition ' . $active . '">' . $child['label'];
        if (!empty($child['badge'])) $html .= '<span class="ml-2 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">' . $child['badge'] . '</span>';
        $html .= '</a>';
    }
    $html .= '</div></div>';
    return $html;
}

function subItem($page, $label, $badge = 0) { return ['page' => $page, 'label' => $label, 'badge' => $badge]; }

$icons = [
    'dashboard' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
    'orders' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
    'products' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
    'categories' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
    'customers' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'inventory' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>',
    'courier' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>',
    'reports' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'settings' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'accounting' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'tasks' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
    'expenses' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
    'returns' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>',
    'pages' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>',
    'banners' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    'coupons' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
    'employees' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    'media' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $adminTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
        <?php if ($isDark): ?>
        /* Dark Theme */
        body { background: #111827 !important; color: #e5e7eb; }
        .bg-gray-50 { background: #111827 !important; }
        .bg-white { background: #1f2937 !important; }
        .border-gray-100, .border-gray-200 { border-color: #374151 !important; }
        .border { border-color: #374151 !important; }
        .text-gray-800 { color: #f3f4f6 !important; }
        .text-gray-700 { color: #d1d5db !important; }
        .text-gray-600 { color: #9ca3af !important; }
        .text-gray-500 { color: #9ca3af !important; }
        .text-gray-400 { color: #6b7280 !important; }
        .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.3) !important; }
        .border-b { border-color: #374151 !important; }
        .hover\:bg-gray-50:hover { background: #374151 !important; }
        .hover\:bg-gray-100:hover { background: #374151 !important; }
        .bg-gray-100 { background: #374151 !important; }
        .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #374151; }
        .divide-gray-100 > :not([hidden]) ~ :not([hidden]) { border-color: #374151; }
        input, select, textarea { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        table thead { background: #1a2332 !important; }
        .rounded-xl { border-color: #374151; }
        <?php endif; ?>
    </style>
</head>
<body class="<?= $isDark ? 'bg-gray-900 text-gray-200' : 'bg-gray-50' ?>">

<!-- ═══ TOP QUICK ACCESS BAR ═══ -->
<div id="quickBar" class="fixed top-0 left-0 right-0 z-[55] <?= $isDark ? 'bg-gray-950 border-gray-700' : 'bg-white border-gray-200' ?> border-b shadow-sm" style="height:44px">
    <div class="flex items-center h-[44px] px-2 lg:px-4 gap-1">
        <!-- Logo/Brand -->
        <a href="<?= adminUrl('pages/dashboard.php') ?>" class="flex items-center gap-1.5 px-2 py-1 rounded-lg <?= $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-100' ?> mr-1 shrink-0">
            <div class="w-6 h-6 bg-blue-600 rounded-md flex items-center justify-center"><span class="text-white text-xs font-bold"><?= strtoupper(substr($siteName,0,1)) ?></span></div>
            <span class="hidden md:inline text-sm font-bold <?= $isDark ? 'text-gray-200' : 'text-gray-800' ?>"><?= e($siteName) ?></span>
        </a>

        <!-- Quick Nav Links -->
        <div class="flex items-center gap-0.5 shrink-0">
            <a href="<?= adminUrl('pages/search.php') ?>" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $currentPage==='search' ? ($isDark?'bg-blue-600 text-white':'bg-blue-600 text-white') : ($isDark?'text-gray-300 hover:bg-gray-800':'text-gray-600 hover:bg-gray-100') ?> transition">Search</a>
            <a href="<?= adminUrl('pages/order-add.php') ?>" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $currentPage==='order-add' ? ($isDark?'bg-blue-600 text-white':'bg-blue-600 text-white') : ($isDark?'text-gray-300 hover:bg-gray-800':'text-gray-600 hover:bg-gray-100') ?> transition hidden sm:inline-block">NewOrder</a>
            <a href="<?= adminUrl('pages/order-management.php') ?>" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $currentPage==='order-management' ? ($isDark?'bg-blue-600 text-white':'bg-blue-600 text-white') : ($isDark?'text-gray-300 hover:bg-gray-800':'text-gray-600 hover:bg-gray-100') ?> transition hidden sm:inline-block">Orders</a>
            <a href="<?= adminUrl('pages/order-management.php?status=processing') ?>" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $isDark?'text-gray-300 hover:bg-gray-800':'text-gray-600 hover:bg-gray-100' ?> transition hidden md:inline-block">Processing</a>
            <a href="<?= adminUrl('pages/incomplete-orders.php') ?>" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $currentPage==='incomplete-orders' ? ($isDark?'bg-blue-600 text-white':'bg-blue-600 text-white') : ($isDark?'text-gray-300 hover:bg-gray-800':'text-gray-600 hover:bg-gray-100') ?> transition hidden md:inline-block">Incomplete</a>
        </div>

        <!-- Global Search -->
        <div class="flex-1 max-w-md mx-2 relative" id="globalSearchWrap">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 <?= $isDark ? 'text-gray-500' : 'text-gray-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="globalSearchInput" placeholder="Search orders, customers, phone..." 
                    class="w-full pl-8 pr-8 py-1.5 text-xs <?= $isDark ? 'bg-gray-800 border-gray-700 text-gray-200 placeholder-gray-500' : 'bg-gray-50 border-gray-200 text-gray-700 placeholder-gray-400' ?> border rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none transition"
                    autocomplete="off" onkeydown="if(event.key==='Enter'){event.preventDefault();goSearch()}" oninput="liveSearch(this.value)">
                <kbd class="absolute right-2 top-1/2 -translate-y-1/2 text-[9px] <?= $isDark ? 'text-gray-500 bg-gray-700 border-gray-600' : 'text-gray-400 bg-gray-100 border-gray-300' ?> border px-1 py-0.5 rounded font-mono hidden sm:inline">/</kbd>
            </div>
            <!-- Live Results Dropdown -->
            <div id="globalSearchResults" class="hidden absolute top-full left-0 right-0 mt-1 <?= $isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200' ?> border rounded-xl shadow-2xl max-h-[400px] overflow-y-auto z-[100]"></div>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-1 shrink-0">
            <button onclick="toggleTheme()" class="p-1.5 rounded-lg <?= $isDark ? 'hover:bg-gray-800 text-yellow-400' : 'hover:bg-gray-100 text-gray-400' ?> transition" title="Toggle theme">
                <?php if ($isDark): ?><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <?php else: ?><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg><?php endif; ?>
            </button>
            <a href="<?= adminUrl('pages/notifications.php') ?>" class="relative p-1.5 rounded-lg <?= $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-100' ?> transition">
                <svg class="w-4 h-4 <?= $isDark ? 'text-gray-400' : 'text-gray-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if ($stats['unread_notifications'] > 0): ?><span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[9px] w-4 h-4 rounded-full flex items-center justify-center"><?= $stats['unread_notifications'] > 9 ? '9+' : $stats['unread_notifications'] ?></span><?php endif; ?>
            </a>
            <div class="relative">
                <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="flex items-center gap-1.5 px-2 py-1 rounded-lg <?= $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-100' ?> transition">
                    <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-white text-[10px] font-bold"><?= strtoupper(substr(getAdminName(), 0, 1)) ?></div>
                    <svg class="w-3 h-3 <?= $isDark ? 'text-gray-500' : 'text-gray-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="hidden absolute right-0 mt-2 w-48 <?= $isDark ? 'bg-gray-800 border-gray-700' : 'bg-white' ?> rounded-lg shadow-lg border py-1 z-[100]">
                    <p class="px-4 py-1.5 text-[10px] font-semibold <?= $isDark ? 'text-gray-500' : 'text-gray-400' ?> uppercase"><?= e(getAdminName()) ?></p>
                    <a href="<?= adminUrl('pages/profile.php') ?>" class="block px-4 py-2 text-sm <?= $isDark ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-user-circle mr-2 text-gray-400"></i>Profile</a>
                    <a href="<?= adminUrl('pages/settings.php') ?>" class="block px-4 py-2 text-sm <?= $isDark ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-700 hover:bg-gray-50' ?>"><i class="fas fa-cog mr-2 text-gray-400"></i>Settings</a>
                    <?php if (isSuperAdmin() || $viewAsRole): ?>
                    <hr class="my-1 <?= $isDark ? 'border-gray-700' : '' ?>">
                    <p class="px-4 py-1 text-[10px] font-semibold <?= $isDark ? 'text-gray-500' : 'text-gray-400' ?>">VIEW AS ROLE</p>
                    <?php foreach ($allRoles as $r): ?>
                    <a href="<?= adminUrl('pages/profile.php?action=view_as&role_id=' . $r['id']) ?>" class="block px-4 py-1.5 text-xs <?= $isDark ? 'text-gray-300 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-50' ?> <?= ($viewAsRole && $viewAsRole['id'] == $r['id']) ? 'font-bold text-blue-500' : '' ?>"><?= e($r['role_name']) ?><?php if ($viewAsRole && $viewAsRole['id'] == $r['id']): ?> ✓<?php endif; ?></a>
                    <?php endforeach; ?>
                    <?php if ($viewAsRole): ?>
                    <a href="<?= adminUrl('pages/profile.php?action=exit_view_as') ?>" class="block px-4 py-1.5 text-xs text-amber-600 font-semibold hover:bg-amber-50">✕ Exit Preview</a>
                    <?php endif; ?>
                    <?php endif; ?>
                    <hr class="my-1 <?= $isDark ? 'border-gray-700' : '' ?>">
                    <a href="<?= adminUrl('index.php?action=logout') ?>" class="block px-4 py-2 text-sm text-red-500 <?= $isDark ? 'hover:bg-red-900/30' : 'hover:bg-red-50' ?>"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Global Search JS -->
<script>
let _gsTimer=null,_gsAbort=null;
function goSearch(){const v=document.getElementById('globalSearchInput').value.trim();if(v)window.location.href='<?= adminUrl("pages/search.php") ?>?q='+encodeURIComponent(v);}
function liveSearch(q){
    clearTimeout(_gsTimer);
    const box=document.getElementById('globalSearchResults');
    if(q.trim().length<2){box.classList.add('hidden');return;}
    _gsTimer=setTimeout(()=>{
        if(_gsAbort)_gsAbort.abort();_gsAbort=new AbortController();
        fetch('<?= adminUrl("api/search.php") ?>?q='+encodeURIComponent(q.trim())+'&limit=8',{signal:_gsAbort.signal})
        .then(r=>r.json()).then(d=>{
            if(!d.results||!d.results.length){box.innerHTML='<p class="p-4 text-sm text-gray-400 text-center">No results</p>';box.classList.remove('hidden');return;}
            let h='';
            d.results.forEach(r=>{
                const badge=r.status_badge||'bg-gray-100 text-gray-600';
                h+=`<a href="${r.url}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-<?= $isDark?'gray-700':'gray-50' ?> border-b <?= $isDark?'border-gray-700':'border-gray-100' ?> transition">
                    <div class="shrink-0 w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 text-xs font-bold">#</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold <?= $isDark?'text-gray-200':'text-gray-800' ?> truncate">${r.order_number} — ${r.customer_name||'Unknown'}</p>
                        <p class="text-[11px] <?= $isDark?'text-gray-400':'text-gray-500' ?>">${r.customer_phone||''} · ৳${r.total} · ${r.date||''}</p>
                    </div>
                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold ${badge}">${(r.status||'').toUpperCase()}</span>
                </a>`;
            });
            if(d.total>8) h+=`<a href="<?= adminUrl("pages/search.php") ?>?q=${encodeURIComponent(q.trim())}" class="block text-center py-2.5 text-xs text-blue-600 font-semibold hover:bg-blue-50">View all ${d.total} results →</a>`;
            box.innerHTML=h;box.classList.remove('hidden');
        }).catch(()=>{});
    },300);
}
document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)){e.preventDefault();document.getElementById('globalSearchInput').focus();}});
document.addEventListener('click',e=>{if(!document.getElementById('globalSearchWrap')?.contains(e.target))document.getElementById('globalSearchResults')?.classList.add('hidden');});
</script>

<?php if ($viewAsRole): ?>
<!-- View-as-Role Banner -->
<div class="fixed top-0 left-0 right-0 z-[60] bg-amber-500 text-amber-950 text-center py-1.5 text-sm font-semibold shadow-lg">
    <i class="fas fa-eye mr-1"></i>Viewing as: <strong><?= e($viewAsRoleName) ?></strong> role (<?= $viewAsPermsCount ?> permissions)
    <a href="<?= adminUrl('pages/profile.php?action=exit_view_as') ?>" class="ml-3 px-2 py-0.5 bg-amber-700 text-white rounded text-xs hover:bg-amber-800">Exit Preview</a>
</div>
<style>#quickBar{top:36px !important}[style*="padding-top:44px"]{padding-top:80px !important}[style*="top:44px"]{top:80px !important}</style>
<?php endif; ?>

<div class="flex min-h-screen" style="padding-top:44px">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 <?= $isDark ? 'bg-gradient-to-b from-gray-800 to-gray-900' : 'bg-gradient-to-b from-blue-800 to-blue-900' ?> transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" style="top:44px">
        <div class="flex items-center gap-3 px-5 py-4 border-b <?= $isDark ? 'border-gray-700/50' : 'border-blue-700/50' ?>">
            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm"><?= e($siteName) ?></h1>
                <p class="<?= $isDark ? 'text-gray-400' : 'text-blue-300' ?> text-xs">Admin Panel</p>
            </div>
        </div>
        <nav class="sidebar-scroll overflow-y-auto h-[calc(100vh-174px)] py-3 px-3 space-y-0.5"><?php /* 174px = 44px quickbar + 130px header/footer */ ?>
            <?= navLink('dashboard', 'Dashboard', $icons['dashboard']) ?>
            
            <?php 
            // Sales section
            $salesItems = navGroup('order-management', 'Order Management', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', [
                subItem('order-management', 'All Orders'),
                subItem('order-add', 'New Order'),
                subItem('incomplete-orders', 'Incomplete Orders', $stats['incomplete_orders']),
                subItem('returns', 'Returns'),
            ], $stats['pending_orders'] + $stats['approved_orders']);
            $salesItems .= navLink('customers', 'Customers', $icons['customers']);
            $salesItems .= navLink('coupons', 'Coupons', $icons['coupons']);
            $salesItems .= navLink('visitors', 'Visitors', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>');
            if (trim($salesItems)): ?>
            <?= navSection('Sales') ?>
            <?= $salesItems ?>
            <?php endif;
            
            // Catalog section
            $catalogItems = navGroup('inventory', 'Inventory', $icons['inventory'], [
                subItem('product-form', 'Add New Product'),
                subItem('products', 'Product List'),
                subItem('categories', 'Categories & Brands'),
                subItem('inventory', 'Stock Management'),
            ], $stats['low_stock']);
            $catalogItems .= navLink('media', 'Media Gallery', $icons['media']);
            if (trim($catalogItems)): ?>
            <?= navSection('Catalog') ?>
            <?= $catalogItems ?>
            <?php endif;
            
            // Shipping section
            $shippingItems = navLink('courier', 'Delivery Methods', $icons['courier']);
            if (trim($shippingItems)): ?>
            <?= navSection('Shipping') ?>
            <?= $shippingItems ?>
            <?php endif;
            
            // Finance section
            $financeItems = navGroup('finance', 'Accounting', $icons['accounting'], [
                subItem('accounting', 'Accounts'),
                subItem('expenses', 'Expenses'),
                subItem('reports', 'Reports & AI'),
            ]);
            if (trim($financeItems)): ?>
            <?= navSection('Finance') ?>
            <?= $financeItems ?>
            <?php endif;
            
            // Content section
            $contentItems = navLink('page-builder', 'Page Builder', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>');
            $contentItems .= navLink('shop-design', 'Shop Design', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>');
            $contentItems .= navLink('checkout-fields', 'Checkout Fields', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>');
            $contentItems .= navLink('banners', 'Banners', $icons['banners']);
            $contentItems .= navLink('cms-pages', 'Pages', $icons['pages']);
            if (trim($contentItems)): ?>
            <?= navSection('Content') ?>
            <?= $contentItems ?>
            <?php endif;
            
            // Support section
            $supportItems = navGroup('live-chat-nav', 'Live Chat', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>', [
                subItem('live-chat', 'Chat Console'),
                subItem('chat-settings', 'Settings & Training'),
            ], $stats['chat_waiting'] ?? 0);
            if (trim($supportItems)): ?>
            <?= navSection('Support') ?>
            <?= $supportItems ?>
            <?php endif;
            
            // Team section
            $teamItems = navGroup('team', 'HRM', $icons['employees'], [
                subItem('employees', 'Employees'),
                subItem('tasks', 'Tasks & Follow-up'),
            ], $stats['pending_tasks']);
            if (trim($teamItems)): ?>
            <?= navSection('Team') ?>
            <?= $teamItems ?>
            <?php endif;
            
            // System section
            $systemItems = navLink('settings', 'Settings', $icons['settings']);
            $systemItems .= navLink('speed', 'Speed & Cache', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>');
            $systemItems .= navLink('security', 'Security Center', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>');
            if (trim($systemItems)): ?>
            <?= navSection('System') ?>
            <?= $systemItems ?>
            <?php endif; ?>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 p-3 border-t <?= $isDark ? 'border-gray-700/50' : 'border-blue-700/50' ?>">
            <a href="<?= url() ?>" target="_blank" class="flex items-center gap-2 <?= $isDark ? 'text-gray-400 hover:text-white' : 'text-blue-200 hover:text-white' ?> text-xs px-3 py-2 rounded-lg <?= $isDark ? 'hover:bg-gray-700/50' : 'hover:bg-blue-700/50' ?> transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                View Store
            </a>
        </div>
    </aside>
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <div class="flex-1 lg:ml-64">
        <header class="sticky z-30 <?= $isDark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200' ?> border-b px-4 lg:px-6 py-2.5" style="top:44px">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg <?= $isDark ? 'hover:bg-gray-700' : 'hover:bg-gray-100' ?>">
                        <svg class="w-5 h-5 <?= $isDark ? 'text-gray-300' : 'text-gray-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h2 class="text-lg font-semibold <?= $isDark ? 'text-gray-100' : 'text-gray-800' ?>"><?= $pageTitle ?? 'Dashboard' ?></h2>
                </div>
            </div>
        </header>
        <main class="p-4 lg:p-6">
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}
function toggleNav(id){const el=document.getElementById('nav-'+id);const arrow=document.querySelector('.nav-arrow-'+id);el.classList.toggle('hidden');arrow?.classList.toggle('rotate-90');}
function toggleTheme(){
    fetch('<?= SITE_URL ?>/api/admin-theme.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'theme=<?= $isDark ? "light" : "dark" ?>'})
    .then(()=>location.reload()).catch(()=>location.reload());
}
</script>

<?php
// Restore real permissions after sidebar rendering
if ($viewAsRole && !empty($_SESSION['_real_permissions'])) {
    $_SESSION['admin_permissions'] = $_SESSION['_real_permissions'];
    unset($_SESSION['_real_permissions']);
}
?>
