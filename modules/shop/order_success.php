<?php
/**
 * NexusCore OS — modules/shop/order_success.php
 * Displays a success message and order summary after checkout.
 */

session_start();
require_once __DIR__ . '/../../config.php';

if (!SHOP_ENABLED) {
    redirect('/');
}

// Retrieve order code from session
$orderCode = $_SESSION['last_order_code'] ?? '';
if ($orderCode === '') {
    redirect('/');
}
// Clear it so it can't be revisited
unset($_SESSION['last_order_code']);

// Load order details
$order     = null;
$orderItems = [];

if ($db && $orderCode !== '') {
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_code = ? LIMIT 1');
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $orderItems[] = $row;
        $stmt->close();
    }
}

$successMessage = getSetting('checkout_success_message',
    'Thank you for your order! We will process it shortly and contact you via WhatsApp.');
$siteName  = h(getSetting('site_name', 'NexusCore OS'));
$neonColor = h(getSetting('neon_color', '#00ff99'));

require_once __DIR__ . '/../../header.php';
?>

<div class="max-w-2xl mx-auto px-4 py-16 text-center">
    <!-- Success icon -->
    <div class="text-7xl mb-6">🎉</div>

    <h1 class="text-3xl font-extrabold mb-4" style="color: var(--neon)">Order Placed!</h1>

    <p class="text-gray-300 mb-2"><?= h($successMessage) ?></p>

    <?php if ($order): ?>
    <p class="text-sm text-gray-500 mb-8">
        Order code: <strong style="color: var(--neon)"><?= h($order['order_code']) ?></strong>
    </p>

    <!-- Order summary card -->
    <div class="glass-card p-6 text-left mb-8">
        <h2 class="font-bold text-white mb-4">Order Details</h2>

        <div class="grid sm:grid-cols-2 gap-4 text-sm mb-4">
            <div>
                <p class="text-gray-500 text-xs">Buyer</p>
                <p class="text-white"><?= h($order['buyer_name']) ?></p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">Email</p>
                <p class="text-white"><?= h($order['buyer_email']) ?></p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">WhatsApp</p>
                <p class="text-white"><?= h($order['buyer_wa']) ?></p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">Payment Method</p>
                <p class="text-white"><?= h($order['payment_method']) ?></p>
            </div>
        </div>

        <div class="border-t border-gray-700 pt-4">
            <p class="text-gray-500 text-xs mb-2">Items Ordered</p>
            <div class="space-y-2">
                <?php foreach ($orderItems as $item): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-300">
                        <?= h($item['product_name']) ?> <span class="text-gray-500">×<?= (int)$item['qty'] ?></span>
                    </span>
                    <span><?= formatRupiah((float)$item['price'] * (int)$item['qty']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between font-bold mt-3 pt-3 border-t border-gray-800">
                <span>Total</span>
                <span style="color: var(--neon)"><?= formatRupiah((float)$order['total_amount']) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <a href="/" class="btn-neon px-8 py-3 rounded-xl text-sm font-bold">← Back to Home</a>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
