<?php
/**
 * NexusCore OS — modules/admin/partials/sidebar.php
 * Admin sidebar navigation. Included in every admin page.
 * Requires $siteName, $neonColor, and $currentSection to be set by the parent.
 */

$navItems = [
    ['slug' => 'overview',   'label' => 'Overview',         'icon' => '🏠', 'url' => '/dashboard.php'],
    ['slug' => 'branding',   'label' => 'Branding',         'icon' => '🎨', 'url' => '/modules/admin/branding.php'],
    ['slug' => 'products',   'label' => 'Products',         'icon' => '📦', 'url' => '/modules/admin/products.php'],
    ['slug' => 'orders',     'label' => 'Orders',           'icon' => '🛒', 'url' => '/modules/admin/orders.php'],
    ['slug' => 'payment',    'label' => 'Payment Methods',  'icon' => '💳', 'url' => '/modules/admin/payment.php'],
    ['slug' => 'chatbot',    'label' => 'Chatbot Settings', 'icon' => '🤖', 'url' => '/modules/admin/chatbot.php'],
    ['slug' => 'settings',   'label' => 'Settings',         'icon' => '⚙️',  'url' => '/modules/admin/settings.php'],
];
$currentSection = $currentSection ?? 'overview';
?>
<aside class="w-60 min-h-screen bg-gray-900 border-r border-gray-800 flex flex-col">
    <!-- Logo -->
    <div class="px-4 py-5 border-b border-gray-800">
        <a href="/dashboard.php" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
            <span class="text-xl">🌌</span>
            <span class="font-bold text-sm" style="color: var(--neon)"><?= $siteName ?></span>
        </a>
        <p class="text-xs text-gray-600 mt-1">Admin Panel</p>
    </div>

    <!-- Nav items -->
    <nav class="flex-1 px-3 py-4 space-y-1">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= h($item['url']) ?>"
           class="sidebar-link <?= $currentSection === $item['slug'] ? 'active' : '' ?>">
            <span><?= $item['icon'] ?></span>
            <span><?= h($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="px-3 py-4 border-t border-gray-800">
        <a href="/logout.php" class="sidebar-link text-red-400 hover:text-red-300 hover:bg-red-900/20">
            <span>🚪</span><span>Logout</span>
        </a>
    </div>
</aside>
