<?php
/**
 * NexusCore OS — modules/shop/cart.php
 * Session-based cart: view, add, update quantity, remove.
 * Also handles JSON AJAX responses for add-to-cart button.
 */

session_start();
require_once __DIR__ . '/../../config.php';

// Shop must be enabled
if (!SHOP_ENABLED) {
    header('Location: /');
    exit;
}

// ---------------------------------------------------------------
// Cart helpers
// ---------------------------------------------------------------
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * addToCart(int $productId, int $qty)
 * Returns ['success' => bool, 'message' => string, 'cartCount' => int]
 */
function addToCart(int $productId, int $qty = 1): array
{
    global $db;
    if (!$db) return ['success' => false, 'message' => 'DB unavailable.', 'cartCount' => 0];

    $stmt = $db->prepare('SELECT id, stock_type, stock_qty, is_active FROM products WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product || !$product['is_active']) {
        return ['success' => false, 'message' => 'Product not found.', 'cartCount' => array_sum($_SESSION['cart'])];
    }

    $currentQty = (int)($_SESSION['cart'][$productId] ?? 0);
    $newQty     = $currentQty + $qty;

    // Stock check
    if ($product['stock_type'] === 'finite') {
        if ($newQty > (int)$product['stock_qty']) {
            return [
                'success'   => false,
                'message'   => 'Insufficient stock. Only ' . $product['stock_qty'] . ' available.',
                'cartCount' => array_sum($_SESSION['cart']),
            ];
        }
    }

    $_SESSION['cart'][$productId] = $newQty;
    return ['success' => true, 'message' => 'Added to cart.', 'cartCount' => array_sum($_SESSION['cart'])];
}

// ---------------------------------------------------------------
// Handle AJAX POST: add
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add') {
        $result = addToCart($productId);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($action === 'update') {
        // Update quantities from cart page form
        foreach ($_POST as $key => $val) {
            if (str_starts_with($key, 'qty_')) {
                $pid = (int)substr($key, 4);
                $qty = (int)$val;
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$pid]);
                } else {
                    $_SESSION['cart'][$pid] = $qty;
                }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'remove') {
        unset($_SESSION['cart'][$productId]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ---------------------------------------------------------------
// Load cart items from DB
// ---------------------------------------------------------------
$cartItems   = [];
$cartTotal   = 0.0;
$now         = time();

if ($db && !empty($_SESSION['cart'])) {
    $ids         = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types        = str_repeat('i', count($ids));

    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $qty     = (int)($_SESSION['cart'][$row['id']] ?? 0);
        $isFlash = !empty($row['flash_sale_price'])
                   && !empty($row['flash_sale_end'])
                   && strtotime($row['flash_sale_end']) > $now;
        $price   = $isFlash ? (float)$row['flash_sale_price'] : (float)$row['price'];

        // Stock warning
        $stockWarning = '';
        if ($row['stock_type'] === 'finite' && $qty > (int)$row['stock_qty']) {
            $stockWarning = "Only {$row['stock_qty']} available.";
            $qty = max(1, (int)$row['stock_qty']);
            $_SESSION['cart'][$row['id']] = $qty;
        }

        $cartItems[] = [
            'product'       => $row,
            'qty'           => $qty,
            'price'         => $price,
            'subtotal'      => $price * $qty,
            'is_flash'      => $isFlash,
            'stock_warning' => $stockWarning,
        ];
        $cartTotal += $price * $qty;
    }
    $stmt->close();
}

$siteName  = h(getSetting('site_name', 'NexusCore OS'));
$neonColor = h(getSetting('neon_color', '#00ff99'));
require_once __DIR__ . '/../../header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-extrabold mb-8" style="color: var(--neon)">🛒 Your Cart</h1>

    <?php if (empty($cartItems)): ?>
    <div class="glass-card p-8 text-center">
        <p class="text-gray-400 text-lg mb-4">Your cart is empty.</p>
        <a href="/#shop" class="btn-neon px-6 py-2 rounded-lg text-sm font-bold">Browse Products</a>
    </div>
    <?php else: ?>

    <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>">
        <input type="hidden" name="action" value="update">
        <div class="glass-card overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-800 text-gray-500 text-xs uppercase">
                        <th class="text-left p-4">Product</th>
                        <th class="text-center p-4">Qty</th>
                        <th class="text-right p-4">Price</th>
                        <th class="text-right p-4">Subtotal</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php foreach ($cartItems as $item):
                        $p = $item['product'];
                    ?>
                    <tr>
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <?php if ($p['image']): ?>
                                <img src="<?= h($p['image']) ?>" alt="" class="h-12 w-12 object-cover rounded-lg">
                                <?php else: ?>
                                <div class="h-12 w-12 bg-gray-800 rounded-lg flex items-center justify-center text-xl">📦</div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-medium text-white"><?= h($p['name']) ?></p>
                                    <?php if ($item['is_flash']): ?>
                                    <span class="flash-badge text-xs">⚡ Flash</span>
                                    <?php endif; ?>
                                    <?php if ($item['stock_warning']): ?>
                                    <p class="text-xs text-yellow-400 mt-0.5">⚠️ <?= h($item['stock_warning']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" onclick="updateCartQty(<?= (int)$p['id'] ?>, -1)"
                                        class="h-6 w-6 rounded bg-gray-700 hover:bg-gray-600 text-white">−</button>
                                <input type="number" name="qty_<?= (int)$p['id'] ?>"
                                       value="<?= $item['qty'] ?>" min="0" max="999"
                                       class="w-14 text-center form-input py-1">
                                <button type="button" onclick="updateCartQty(<?= (int)$p['id'] ?>, 1)"
                                        class="h-6 w-6 rounded bg-gray-700 hover:bg-gray-600 text-white">+</button>
                            </div>
                        </td>
                        <td class="p-4 text-right"><?= formatRupiah($item['price']) ?></td>
                        <td class="p-4 text-right font-bold" style="color: var(--neon)"><?= formatRupiah($item['subtotal']) ?></td>
                        <td class="p-4 text-center">
                            <button type="submit" form="remove-form-<?= (int)$p['id'] ?>"
                                    class="text-gray-500 hover:text-red-400 transition-colors text-lg">×</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center mb-4">
            <button type="submit" class="px-4 py-2 rounded-lg border border-gray-600 text-gray-300 text-sm hover:border-green-500 hover:text-green-400 transition-colors">
                🔄 Update Cart
            </button>
        </div>
    </form>

    <!-- Individual remove forms (outside main form) -->
    <?php foreach ($cartItems as $item): ?>
    <form id="remove-form-<?= (int)$item['product']['id'] ?>" method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
    </form>
    <?php endforeach; ?>

    <!-- Order summary -->
    <div class="glass-card p-5 max-w-sm ml-auto">
        <h2 class="font-bold text-white mb-4">Order Summary</h2>
        <div class="flex justify-between text-sm text-gray-400 mb-2">
            <span>Subtotal</span>
            <span><?= formatRupiah($cartTotal) ?></span>
        </div>
        <div class="border-t border-gray-700 pt-3 mt-3 flex justify-between font-bold">
            <span>Total</span>
            <span style="color: var(--neon)"><?= formatRupiah($cartTotal) ?></span>
        </div>
        <a href="/modules/shop/checkout.php"
           class="btn-neon w-full py-3 rounded-xl text-sm font-bold mt-5 text-center block">
            Proceed to Checkout →
        </a>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
