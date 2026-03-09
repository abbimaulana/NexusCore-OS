<?php
/**
 * NexusCore OS — dashboard.php
 * Admin overview dashboard — requires authentication.
 */

session_start();
require_once __DIR__ . '/config.php';
requireAdmin();

// ---------------------------------------------------------------
// Load overview stats
// ---------------------------------------------------------------
$totalOrders   = 0;
$totalRevenue  = 0.0;
$pendingOrders = 0;
$activeProducts = 0;
$recentOrders  = [];

if ($db) {
    // Total orders
    $res = $db->query('SELECT COUNT(*) AS cnt, SUM(total_amount) AS rev FROM orders');
    if ($res && $row = $res->fetch_assoc()) {
        $totalOrders  = (int)$row['cnt'];
        $totalRevenue = (float)($row['rev'] ?? 0);
        $res->free();
    }

    // Pending orders
    $res = $db->query("SELECT COUNT(*) AS cnt FROM orders WHERE status = 'pending'");
    if ($res && $row = $res->fetch_assoc()) {
        $pendingOrders = (int)$row['cnt'];
        $res->free();
    }

    // Active products
    $res = $db->query('SELECT COUNT(*) AS cnt FROM products WHERE is_active = 1');
    if ($res && $row = $res->fetch_assoc()) {
        $activeProducts = (int)$row['cnt'];
        $res->free();
    }

    // Recent 5 orders
    $res = $db->query(
        'SELECT order_code, buyer_name, total_amount, status, created_at
         FROM orders ORDER BY created_at DESC LIMIT 5'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) $recentOrders[] = $row;
        $res->free();
    }
}

$siteName  = h(getSetting('site_name', 'NexusCore OS'));
$neonColor = h(getSetting('neon_color', '#00ff99'));
$adminUser = h($_SESSION['nexus_admin_user'] ?? 'Admin');

// Current section (sidebar highlight)
$currentSection = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= $siteName ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= $neonColor ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono">

<div class="flex min-h-screen">
    <!-- ====================================================
         SIDEBAR
         ==================================================== -->
    <?php include __DIR__ . '/modules/admin/partials/sidebar.php'; ?>

    <!-- ====================================================
         MAIN CONTENT
         ==================================================== -->
    <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-5xl mx-auto">

            <div class="mb-8">
                <h1 class="text-2xl font-extrabold" style="color: var(--neon)">Dashboard Overview</h1>
                <p class="text-gray-500 text-sm mt-1">Welcome back, <?= $adminUser ?>.</p>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
                <?php
                $cards = [
                    ['label' => 'Total Orders',    'value' => number_format($totalOrders),  'icon' => '📦'],
                    ['label' => 'Total Revenue',   'value' => formatRupiah($totalRevenue),  'icon' => '💰'],
                    ['label' => 'Pending Orders',  'value' => number_format($pendingOrders),'icon' => '⏳'],
                    ['label' => 'Active Products', 'value' => number_format($activeProducts),'icon' => '🛍'],
                ];
                foreach ($cards as $card): ?>
                <div class="glass-card p-4">
                    <div class="text-2xl mb-2"><?= $card['icon'] ?></div>
                    <p class="text-2xl font-extrabold" style="color: var(--neon)"><?= h($card['value']) ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= h($card['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent orders -->
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-4">Recent Orders</h2>
                <?php if (empty($recentOrders)): ?>
                <p class="text-gray-500 text-sm">No orders yet.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 text-xs uppercase border-b border-gray-800">
                                <th class="text-left pb-2">Order Code</th>
                                <th class="text-left pb-2">Buyer</th>
                                <th class="text-left pb-2">Amount</th>
                                <th class="text-left pb-2">Status</th>
                                <th class="text-left pb-2">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <?php foreach ($recentOrders as $ord):
                                $statusColors = [
                                    'pending'    => 'text-yellow-400',
                                    'paid'       => 'text-blue-400',
                                    'processing' => 'text-purple-400',
                                    'completed'  => 'text-green-400',
                                    'cancelled'  => 'text-red-400',
                                ];
                                $statusClass = $statusColors[$ord['status']] ?? 'text-gray-400';
                            ?>
                            <tr>
                                <td class="py-2 font-mono text-xs" style="color: var(--neon)"><?= h($ord['order_code']) ?></td>
                                <td class="py-2"><?= h($ord['buyer_name']) ?></td>
                                <td class="py-2"><?= formatRupiah((float)$ord['total_amount']) ?></td>
                                <td class="py-2 <?= $statusClass ?> capitalize"><?= h($ord['status']) ?></td>
                                <td class="py-2 text-gray-500"><?= h(date('d M Y', strtotime($ord['created_at']))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="/modules/admin/orders.php" class="text-xs mt-3 inline-block hover:underline"
                   style="color: var(--neon)">View all orders →</a>
                <?php endif; ?>
            </div>

            <!-- Module status -->
            <div class="mt-6 glass-card p-5">
                <h2 class="font-bold text-white mb-4">Module Status</h2>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full <?= SHOP_ENABLED ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                        <span class="text-gray-300">Shop Module: <strong><?= SHOP_ENABLED ? 'Active' : 'Disabled' ?></strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full <?= CHATBOT_ENABLED ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                        <span class="text-gray-300">Chatbot Module: <strong><?= CHATBOT_ENABLED ? 'Active' : 'Disabled' ?></strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full <?= $db ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                        <span class="text-gray-300">Database: <strong><?= $db ? 'Connected' : 'Disconnected' ?></strong></span>
                    </div>
                </div>
                <a href="/modules/admin/settings.php" class="text-xs mt-3 inline-block hover:underline"
                   style="color: var(--neon)">Manage settings →</a>
            </div>
        </div>
    </main>
</div>

<script src="/assets/js/main.js"></script>
</body>
</html>
