<?php
/**
 * NexusCore OS — modules/admin/orders.php
 * List and manage orders; update status.
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

$msg  = '';
$type = 'success';

// ---------------------------------------------------------------
// POST: update order status
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId   = (int)($_POST['order_id']  ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');
    $allowed   = ['pending', 'paid', 'processing', 'completed', 'cancelled'];

    if (in_array($newStatus, $allowed, true) && $orderId > 0 && $db) {
        $stmt = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $orderId);
        $ok   = $stmt->execute();
        $msg  = $ok ? 'Order status updated.' : 'Update failed.';
        $type = $ok ? 'success' : 'error';
        $stmt->close();
    } else {
        $msg  = 'Invalid input.';
        $type = 'error';
    }
}

// ---------------------------------------------------------------
// Filter & pagination
// ---------------------------------------------------------------
$statusFilter = $_GET['status'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$orders = [];
$total  = 0;

if ($db) {
    // Count — use prepared statement when filter is applied
    if ($statusFilter) {
        $cntStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM orders WHERE status = ?');
        $cntStmt->bind_param('s', $statusFilter);
        $cntStmt->execute();
        $total = (int)$cntStmt->get_result()->fetch_assoc()['cnt'];
        $cntStmt->close();
    } else {
        $countRes = $db->query('SELECT COUNT(*) AS cnt FROM orders');
        if ($countRes) {
            $total = (int)$countRes->fetch_assoc()['cnt'];
            $countRes->free();
        }
    }

    // Rows — use prepared statement
    if ($statusFilter) {
        $stmt = $db->prepare('SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('sii', $statusFilter, $perPage, $offset);
    } else {
        $stmt = $db->prepare('SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();
}

$totalPages = max(1, (int)ceil($total / $perPage));
$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$currentSection = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — <?= $siteName ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= $neonColor ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Orders</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <!-- Status filter -->
            <div class="flex flex-wrap gap-2 mb-6 text-sm">
                <?php foreach (['', 'pending', 'paid', 'processing', 'completed', 'cancelled'] as $s): ?>
                <a href="?status=<?= h($s) ?>"
                   class="px-3 py-1 rounded-full border <?= $statusFilter === $s ? 'border-green-500 text-green-400' : 'border-gray-700 text-gray-400' ?> hover:border-green-500 transition-colors">
                    <?= $s === '' ? 'All' : ucfirst($s) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Orders table -->
            <div class="glass-card p-5 overflow-x-auto">
                <?php if (empty($orders)): ?>
                <p class="text-gray-500 text-sm">No orders found.</p>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs uppercase border-b border-gray-800">
                            <th class="text-left pb-2">Code</th>
                            <th class="text-left pb-2">Buyer</th>
                            <th class="text-left pb-2">Total</th>
                            <th class="text-left pb-2">Payment</th>
                            <th class="text-left pb-2">Status</th>
                            <th class="text-left pb-2">Date</th>
                            <th class="text-left pb-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($orders as $ord):
                            $statusColors = [
                                'pending'    => 'text-yellow-400',
                                'paid'       => 'text-blue-400',
                                'processing' => 'text-purple-400',
                                'completed'  => 'text-green-400',
                                'cancelled'  => 'text-red-400',
                            ];
                            $sc = $statusColors[$ord['status']] ?? 'text-gray-400';
                        ?>
                        <tr>
                            <td class="py-2 font-mono text-xs" style="color: var(--neon)"><?= h($ord['order_code']) ?></td>
                            <td class="py-2">
                                <div><?= h($ord['buyer_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= h($ord['buyer_email']) ?></div>
                            </td>
                            <td class="py-2"><?= formatRupiah((float)$ord['total_amount']) ?></td>
                            <td class="py-2 text-xs"><?= h($ord['payment_method']) ?></td>
                            <td class="py-2 <?= $sc ?> capitalize"><?= h($ord['status']) ?></td>
                            <td class="py-2 text-gray-500 text-xs"><?= h(date('d M Y', strtotime($ord['created_at']))) ?></td>
                            <td class="py-2">
                                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="flex items-center gap-1">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                    <select name="status" class="form-input py-0.5 text-xs w-28">
                                        <?php foreach (['pending','paid','processing','completed','cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $ord['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status"
                                            class="text-xs px-2 py-1 btn-neon rounded">✓</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex gap-2 mt-4 text-xs">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?status=<?= h($statusFilter) ?>&page=<?= $i ?>"
                       class="px-3 py-1 rounded border <?= $page === $i ? 'border-green-500 text-green-400' : 'border-gray-700 text-gray-400' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
