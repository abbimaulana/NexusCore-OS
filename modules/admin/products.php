<?php
/**
 * NexusCore OS — modules/admin/products.php
 * CRUD panel for products: add, edit, delete.
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

$msg  = '';
$type = 'success';

$allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$uploadDir    = __DIR__ . '/../../assets/uploads/';

// ---------------------------------------------------------------
// POST: create / update / delete
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- DELETE ---
    if (isset($_POST['delete_product'])) {
        $id   = (int)($_POST['product_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $msg  = $stmt->execute() ? 'Product deleted.' : 'Delete failed.';
        $type = $stmt->execute() === false ? 'error' : 'success';
        $stmt->close();
    }

    // --- CREATE / UPDATE ---
    if (isset($_POST['save_product'])) {
        $id          = (int)($_POST['product_id'] ?? 0);
        $name        = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $price       = (float)($_POST['price'] ?? 0);
        $stockType   = in_array($_POST['stock_type'] ?? '', ['unlimited','finite']) ? $_POST['stock_type'] : 'unlimited';
        $stockQty    = (int)($_POST['stock_qty'] ?? 0);
        $category    = trim((string)($_POST['category'] ?? ''));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $flashPrice  = $_POST['flash_sale_price'] !== '' ? (float)$_POST['flash_sale_price'] : null;
        $flashEnd    = trim((string)($_POST['flash_sale_end'] ?? '')) ?: null;
        $slug        = slugify($name);

        // Handle image upload
        $imagePath = trim((string)($_POST['existing_image'] ?? ''));
        if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['image']['tmp_name'];
            $mime = mime_content_type($tmp);
            $ext  = strtolower(pathinfo(basename($_FILES['image']['name']), PATHINFO_EXTENSION));
            if (in_array($mime, $allowedMimes, true) && in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) {
                $newName   = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                    $imagePath = '/assets/uploads/' . $newName;
                }
            } else {
                $msg  = 'Invalid image file type.';
                $type = 'error';
            }
        }

        if ($type !== 'error') {
            if ($id > 0) {
                // UPDATE
                $stmt = $db->prepare(
                    'UPDATE products SET name=?, slug=?, description=?, price=?, stock_type=?, stock_qty=?,
                     image=?, category=?, is_active=?, flash_sale_price=?, flash_sale_end=? WHERE id=?'
                );
                $stmt->bind_param('sssdsississi',
                    $name, $slug, $description, $price, $stockType, $stockQty,
                    $imagePath, $category, $isActive, $flashPrice, $flashEnd, $id
                );
            } else {
                // INSERT
                $stmt = $db->prepare(
                    'INSERT INTO products (name, slug, description, price, stock_type, stock_qty,
                     image, category, is_active, flash_sale_price, flash_sale_end) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->bind_param('sssdsississ',
                    $name, $slug, $description, $price, $stockType, $stockQty,
                    $imagePath, $category, $isActive, $flashPrice, $flashEnd
                );
            }
            $msg  = $stmt->execute() ? ($id > 0 ? 'Product updated.' : 'Product created.') : 'Save failed: ' . h($db->error);
            $type = $stmt->affected_rows < 0 ? 'error' : 'success';
            $stmt->close();
        }
    }
}

// ---------------------------------------------------------------
// Edit mode: load single product
// ---------------------------------------------------------------
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Load all products
$products = [];
if ($db) {
    $res = $db->query('SELECT * FROM products ORDER BY created_at DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $products[] = $row;
        $res->free();
    }
}

$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$currentSection = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — <?= $siteName ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= $neonColor ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Products</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <!-- Add / Edit Form -->
            <div class="glass-card p-5 mb-8">
                <h2 class="font-bold text-white mb-4"><?= $editing ? 'Edit Product' : 'Add New Product' ?></h2>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="product_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
                    <?php if ($editing): ?>
                    <input type="hidden" name="existing_image" value="<?= h($editing['image']) ?>">
                    <?php endif; ?>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-input" required
                                   value="<?= $editing ? h($editing['name']) : '' ?>">
                        </div>
                        <div>
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-input"
                                   value="<?= $editing ? h($editing['category']) : '' ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-input"><?= $editing ? h($editing['description']) : '' ?></textarea>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">Price (Rp) *</label>
                            <input type="number" name="price" class="form-input" min="0" step="0.01" required
                                   value="<?= $editing ? h($editing['price']) : '' ?>">
                        </div>
                        <div>
                            <label class="form-label">Stock Type</label>
                            <select name="stock_type" class="form-input">
                                <option value="unlimited" <?= (!$editing || $editing['stock_type']==='unlimited') ? 'selected' : '' ?>>Unlimited</option>
                                <option value="finite"    <?= ($editing && $editing['stock_type']==='finite') ? 'selected' : '' ?>>Finite</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Stock Qty</label>
                            <input type="number" name="stock_qty" class="form-input" min="0"
                                   value="<?= $editing ? (int)$editing['stock_qty'] : 0 ?>">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Flash Sale Price (leave blank to disable)</label>
                            <input type="number" name="flash_sale_price" class="form-input" min="0" step="0.01"
                                   value="<?= ($editing && $editing['flash_sale_price']) ? h($editing['flash_sale_price']) : '' ?>">
                        </div>
                        <div>
                            <label class="form-label">Flash Sale End (datetime)</label>
                            <input type="datetime-local" name="flash_sale_end" class="form-input"
                                   value="<?= ($editing && $editing['flash_sale_end']) ? h(date('Y-m-d\TH:i', strtotime($editing['flash_sale_end']))) : '' ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Product Image</label>
                        <?php if ($editing && $editing['image']): ?>
                        <img src="<?= h($editing['image']) ?>" alt="" class="h-16 mb-2 object-contain rounded">
                        <?php endif; ?>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_active" name="is_active"
                               class="accent-green-500 h-4 w-4"
                               <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
                        <label for="is_active" class="text-sm text-gray-300">Active (visible on site)</label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="save_product" class="btn-neon px-5 py-2 rounded-lg text-sm font-bold">
                            💾 <?= $editing ? 'Update' : 'Add' ?> Product
                        </button>
                        <?php if ($editing): ?>
                        <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="px-5 py-2 rounded-lg text-sm border border-gray-600 text-gray-400 hover:border-gray-400 hover:text-white transition-colors">
                            Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Products table -->
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-4">All Products</h2>
                <?php if (empty($products)): ?>
                <p class="text-gray-500 text-sm">No products yet.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 text-xs uppercase border-b border-gray-800">
                                <th class="text-left pb-2">Name</th>
                                <th class="text-left pb-2">Price</th>
                                <th class="text-left pb-2">Stock</th>
                                <th class="text-left pb-2">Active</th>
                                <th class="text-left pb-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="py-2"><?= h($p['name']) ?></td>
                                <td class="py-2"><?= formatRupiah((float)$p['price']) ?></td>
                                <td class="py-2"><?= $p['stock_type'] === 'unlimited' ? '∞' : h($p['stock_qty']) ?></td>
                                <td class="py-2">
                                    <span class="<?= $p['is_active'] ? 'text-green-400' : 'text-gray-500' ?>">
                                        <?= $p['is_active'] ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td class="py-2 flex gap-2">
                                    <a href="?edit=<?= (int)$p['id'] ?>"
                                       class="text-xs px-2 py-1 rounded border border-gray-600 text-gray-300 hover:border-green-500 hover:text-green-400 transition-colors">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Delete this product?');" class="inline">
                                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" name="delete_product"
                                                class="text-xs px-2 py-1 rounded border border-gray-600 text-red-400 hover:border-red-400 transition-colors">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
