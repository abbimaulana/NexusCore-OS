<?php
/**
 * NexusCore OS — modules/shop/checkout.php
 * Collects buyer info, selects payment method, saves order, notifies via Telegram.
 */

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../api/telegram.php';

// Shop must be enabled
if (!SHOP_ENABLED) {
    redirect('/');
}

// Cart must not be empty
if (empty($_SESSION['cart'])) {
    redirect('/modules/shop/cart.php');
}

$msg  = '';
$type = 'error';

// ---------------------------------------------------------------
// Load active payment methods
// ---------------------------------------------------------------
$paymentMethods = [];
if ($db) {
    $res = $db->query('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY type, id');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['detail'] = json_decode($row['detail_json'], true) ?? [];
            $paymentMethods[] = $row;
        }
        $res->free();
    }
}

// ---------------------------------------------------------------
// Load cart items
// ---------------------------------------------------------------
$cartItems = [];
$cartTotal = 0.0;
$now       = time();

if ($db && !empty($_SESSION['cart'])) {
    $ids          = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types        = str_repeat('i', count($ids));

    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $qty    = (int)($_SESSION['cart'][$row['id']] ?? 0);
        $isFlash = !empty($row['flash_sale_price'])
                   && !empty($row['flash_sale_end'])
                   && strtotime($row['flash_sale_end']) > $now;
        $price  = $isFlash ? (float)$row['flash_sale_price'] : (float)$row['price'];
        $cartItems[] = [
            'product'  => $row,
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $price * $qty,
        ];
        $cartTotal += $price * $qty;
    }
    $stmt->close();
}

if (empty($cartItems)) {
    redirect('/modules/shop/cart.php');
}

// ---------------------------------------------------------------
// Handle checkout POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $buyerName    = trim((string)($_POST['buyer_name']    ?? ''));
    $buyerEmail   = trim((string)($_POST['buyer_email']   ?? ''));
    $buyerWa      = trim((string)($_POST['buyer_wa']      ?? ''));
    $serviceDetail = trim((string)($_POST['service_detail'] ?? ''));
    $paymentId    = (int)($_POST['payment_method_id'] ?? 0);

    // Validate
    $errors = [];
    if ($buyerName === '')          $errors[] = 'Buyer name is required.';
    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($buyerWa === '')            $errors[] = 'WhatsApp number is required.';
    if ($serviceDetail === '')      $errors[] = 'Service detail is required.';
    if ($paymentId < 1)             $errors[] = 'Please select a payment method.';

    if (empty($errors)) {
        // Get payment method label
        $pmLabel = '';
        foreach ($paymentMethods as $pm) {
            if ((int)$pm['id'] === $paymentId) {
                $pmLabel = $pm['label'];
                break;
            }
        }
        if ($pmLabel === '') {
            $errors[] = 'Selected payment method is unavailable.';
        }
    }

    if (!empty($errors)) {
        $msg  = implode(' ', $errors);
        $type = 'error';
    } else {
        // Generate order code and save
        $orderCode = generateOrderCode();

        if ($db) {
            $db->begin_transaction();
            try {
                // Insert order
                $stmt = $db->prepare(
                    'INSERT INTO orders (order_code, buyer_name, buyer_email, buyer_wa,
                     service_detail, payment_method, total_amount) VALUES (?,?,?,?,?,?,?)'
                );
                $stmt->bind_param('ssssssd',
                    $orderCode, $buyerName, $buyerEmail, $buyerWa,
                    $serviceDetail, $pmLabel, $cartTotal
                );
                $stmt->execute();
                $orderId = $db->insert_id;
                $stmt->close();

                // Insert order items
                $itemsStmt = $db->prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?,?,?,?,?)'
                );
                foreach ($cartItems as $item) {
                    $pid   = (int)$item['product']['id'];
                    $pname = $item['product']['name'];
                    $price = $item['price'];
                    $qty   = $item['qty'];
                    $itemsStmt->bind_param('iisdi', $orderId, $pid, $pname, $price, $qty);
                    $itemsStmt->execute();

                    // Decrement finite stock
                    if ($item['product']['stock_type'] === 'finite') {
                        $decStmt = $db->prepare(
                            'UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?'
                        );
                        $decStmt->bind_param('ii', $qty, $pid);
                        $decStmt->execute();
                        $decStmt->close();
                    }
                }
                $itemsStmt->close();

                $db->commit();

                // Clear cart
                $_SESSION['cart'] = [];

                // Send Telegram notification
                $orderData = [
                    'order_code'     => $orderCode,
                    'buyer_name'     => $buyerName,
                    'buyer_email'    => $buyerEmail,
                    'buyer_wa'       => $buyerWa,
                    'service_detail' => $serviceDetail,
                    'payment_method' => $pmLabel,
                    'total_amount'   => $cartTotal,
                    'created_at'     => date('Y-m-d H:i:s'),
                ];
                $itemsData = array_map(fn($i) => [
                    'product_name' => $i['product']['name'],
                    'qty'          => $i['qty'],
                    'price'        => $i['price'],
                ], $cartItems);

                sendOrderNotification($orderData, $itemsData);

                // Redirect to success page
                $_SESSION['last_order_code'] = $orderCode;
                redirect('/modules/shop/order_success.php');

            } catch (\Throwable $e) {
                $db->rollback();
                $msg  = 'Order failed. Please try again.';
                $type = 'error';
            }
        } else {
            $msg  = 'Database unavailable.';
            $type = 'error';
        }
    }
}

$siteName  = h(getSetting('site_name', 'NexusCore OS'));
$neonColor = h(getSetting('neon_color', '#00ff99'));
require_once __DIR__ . '/../../header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-extrabold mb-8" style="color: var(--neon)">🧾 Checkout</h1>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?> mb-6"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="grid md:grid-cols-2 gap-8">
        <!-- Checkout form -->
        <div>
            <?php if (empty($paymentMethods)): ?>
            <div class="alert alert-warning mb-4">
                ⚠️ Payment Unavailable — no payment methods have been configured. Please contact the admin.
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="glass-card p-6 space-y-4">
                <div>
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="buyer_name" class="form-input" required
                           value="<?= h($_POST['buyer_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Email *</label>
                    <input type="email" name="buyer_email" class="form-input" required
                           value="<?= h($_POST['buyer_email'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">WhatsApp Number *</label>
                    <input type="tel" name="buyer_wa" class="form-input" required
                           placeholder="+62812345678"
                           value="<?= h($_POST['buyer_wa'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Service / Order Detail *</label>
                    <textarea name="service_detail" rows="3" class="form-input" required
                              placeholder="e.g. Setup VPS Ubuntu 22.04 with Nginx and SSL"><?= h($_POST['service_detail'] ?? '') ?></textarea>
                </div>

                <!-- Payment method selector -->
                <div>
                    <label class="form-label">Payment Method *</label>
                    <?php if (empty($paymentMethods)): ?>
                    <p class="text-gray-500 text-sm">No payment methods available.</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php
                        $typeIcons = ['bank' => '🏦', 'ewallet' => '📱', 'qris' => '🔳'];
                        foreach ($paymentMethods as $pm):
                            $det = $pm['detail'];
                        ?>
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-700 cursor-pointer hover:border-green-500 transition-colors has-[:checked]:border-green-500">
                            <input type="radio" name="payment_method_id"
                                   value="<?= (int)$pm['id'] ?>"
                                   class="mt-0.5 accent-green-500"
                                   required
                                   <?= isset($_POST['payment_method_id']) && (int)$_POST['payment_method_id'] === (int)$pm['id'] ? 'checked' : '' ?>>
                            <div>
                                <p class="font-semibold text-sm"><?= $typeIcons[$pm['type']] ?? '' ?> <?= h($pm['label']) ?></p>
                                <?php if ($pm['type'] === 'bank' && !empty($det['account_no'])): ?>
                                <p class="text-xs text-gray-400">
                                    <?= h($det['bank_name'] ?? '') ?> — <?= h($det['account_no']) ?>
                                    (<?= h($det['account_name'] ?? '') ?>)
                                </p>
                                <?php elseif ($pm['type'] === 'ewallet' && !empty($det['number'])): ?>
                                <p class="text-xs text-gray-400">
                                    <?= h($det['provider'] ?? '') ?> — <?= h($det['number']) ?>
                                </p>
                                <?php elseif ($pm['type'] === 'qris' && $pm['qr_image']): ?>
                                <img src="<?= h($pm['qr_image']) ?>" alt="QRIS" class="h-24 mt-2 rounded object-contain">
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($paymentMethods)): ?>
                <button type="submit" name="place_order" class="btn-neon w-full py-3 rounded-xl text-sm font-bold">
                    ✅ Place Order
                </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Order summary -->
        <div>
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-4">Order Summary</h2>
                <div class="space-y-3 mb-4">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-300">
                            <?= h($item['product']['name']) ?> <span class="text-gray-500">×<?= $item['qty'] ?></span>
                        </span>
                        <span><?= formatRupiah($item['subtotal']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-700 pt-3 flex justify-between font-bold">
                    <span>Total</span>
                    <span style="color: var(--neon)"><?= formatRupiah($cartTotal) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
