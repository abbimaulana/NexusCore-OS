<?php
/**
 * NexusCore OS — modules/admin/payment.php
 * Manage payment methods: Bank Transfer, E-Wallet, QRIS.
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

$msg  = '';
$type = 'success';

$allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$uploadDir    = __DIR__ . '/../../assets/uploads/';

// ---------------------------------------------------------------
// POST handlers
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Toggle active status
    if (isset($_POST['toggle_method'])) {
        $id    = (int)($_POST['method_id'] ?? 0);
        $state = (int)($_POST['new_state'] ?? 0);
        if ($db && $id > 0) {
            $stmt = $db->prepare('UPDATE payment_methods SET is_active = ? WHERE id = ?');
            $stmt->bind_param('ii', $state, $id);
            $msg  = $stmt->execute() ? 'Status updated.' : 'Update failed.';
            $stmt->close();
        }
    }

    // Delete
    if (isset($_POST['delete_method'])) {
        $id = (int)($_POST['method_id'] ?? 0);
        if ($db && $id > 0) {
            $stmt = $db->prepare('DELETE FROM payment_methods WHERE id = ?');
            $stmt->bind_param('i', $id);
            $msg  = $stmt->execute() ? 'Method deleted.' : 'Delete failed.';
            $stmt->close();
        }
    }

    // Save (create / update)
    if (isset($_POST['save_method'])) {
        $methodId = (int)($_POST['method_id'] ?? 0);
        $mType    = in_array($_POST['type'] ?? '', ['bank','ewallet','qris']) ? $_POST['type'] : 'bank';
        $label    = trim((string)($_POST['label'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Build detail JSON based on type
        $detail = [];
        if ($mType === 'bank') {
            $detail = [
                'bank_name'    => trim((string)($_POST['bank_name'] ?? '')),
                'account_no'   => trim((string)($_POST['account_no'] ?? '')),
                'account_name' => trim((string)($_POST['account_name'] ?? '')),
            ];
        } elseif ($mType === 'ewallet') {
            $detail = [
                'provider'     => trim((string)($_POST['ewallet_provider'] ?? '')),
                'number'       => trim((string)($_POST['ewallet_number'] ?? '')),
                'account_name' => trim((string)($_POST['ewallet_name'] ?? '')),
            ];
        }

        $detailJson = json_encode($detail);
        $qrImage    = trim((string)($_POST['existing_qr'] ?? ''));

        // QR upload (for QRIS)
        if ($mType === 'qris' && !empty($_FILES['qr_image']['tmp_name']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['qr_image']['tmp_name'];
            $mime = mime_content_type($tmp);
            $ext  = strtolower(pathinfo(basename($_FILES['qr_image']['name']), PATHINFO_EXTENSION));
            if (in_array($mime, $allowedMimes, true) && in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) {
                $newName = 'qris_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                    $qrImage = '/assets/uploads/' . $newName;
                }
            } else {
                $msg  = 'Invalid QR image type.';
                $type = 'error';
            }
        }

        if ($type !== 'error' && $label !== '' && $db) {
            if ($methodId > 0) {
                $stmt = $db->prepare(
                    'UPDATE payment_methods SET type=?, label=?, detail_json=?, qr_image=?, is_active=? WHERE id=?'
                );
                $stmt->bind_param('ssssii', $mType, $label, $detailJson, $qrImage, $isActive, $methodId);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO payment_methods (type, label, detail_json, qr_image, is_active) VALUES (?,?,?,?,?)'
                );
                $stmt->bind_param('ssssi', $mType, $label, $detailJson, $qrImage, $isActive);
            }
            $msg  = $stmt->execute() ? 'Payment method saved.' : 'Save failed.';
            $type = $stmt->affected_rows < 0 ? 'error' : 'success';
            $stmt->close();
        }
    }
}

// Edit mode
$editing = null;
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM payment_methods WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editing) {
        $editing['detail'] = json_decode($editing['detail_json'], true) ?? [];
    }
}

// Load all methods
$methods = [];
if ($db) {
    $res = $db->query('SELECT * FROM payment_methods ORDER BY type, id');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['detail'] = json_decode($row['detail_json'], true) ?? [];
            $methods[]     = $row;
        }
        $res->free();
    }
}

$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$currentSection = 'payment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods — <?= $siteName ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= $neonColor ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Payment Methods</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <!-- Add / Edit form -->
            <div class="glass-card p-5 mb-8">
                <h2 class="font-bold text-white mb-4"><?= $editing ? 'Edit Method' : 'Add Payment Method' ?></h2>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" class="space-y-4"
                      id="payment-form">
                    <input type="hidden" name="method_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
                    <?php if ($editing): ?>
                    <input type="hidden" name="existing_qr" value="<?= h($editing['qr_image']) ?>">
                    <?php endif; ?>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Type *</label>
                            <select name="type" id="pm-type" class="form-input" onchange="togglePaymentFields()">
                                <option value="bank"    <?= (!$editing || $editing['type']==='bank')    ? 'selected' : '' ?>>Bank Transfer</option>
                                <option value="ewallet" <?= ($editing && $editing['type']==='ewallet')  ? 'selected' : '' ?>>E-Wallet</option>
                                <option value="qris"    <?= ($editing && $editing['type']==='qris')     ? 'selected' : '' ?>>QRIS</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Label / Display Name *</label>
                            <input type="text" name="label" class="form-input" required
                                   value="<?= $editing ? h($editing['label']) : '' ?>"
                                   placeholder="e.g. BCA — Savings">
                        </div>
                    </div>

                    <!-- Bank fields -->
                    <div id="fields-bank" class="space-y-4">
                        <div class="grid sm:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-input"
                                       value="<?= ($editing && $editing['type']==='bank') ? h($editing['detail']['bank_name'] ?? '') : '' ?>">
                            </div>
                            <div>
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_no" class="form-input"
                                       value="<?= ($editing && $editing['type']==='bank') ? h($editing['detail']['account_no'] ?? '') : '' ?>">
                            </div>
                            <div>
                                <label class="form-label">Account Name</label>
                                <input type="text" name="account_name" class="form-input"
                                       value="<?= ($editing && $editing['type']==='bank') ? h($editing['detail']['account_name'] ?? '') : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- E-wallet fields -->
                    <div id="fields-ewallet" class="hidden space-y-4">
                        <div class="grid sm:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Provider (e.g. OVO)</label>
                                <input type="text" name="ewallet_provider" class="form-input"
                                       value="<?= ($editing && $editing['type']==='ewallet') ? h($editing['detail']['provider'] ?? '') : '' ?>">
                            </div>
                            <div>
                                <label class="form-label">Number</label>
                                <input type="text" name="ewallet_number" class="form-input"
                                       value="<?= ($editing && $editing['type']==='ewallet') ? h($editing['detail']['number'] ?? '') : '' ?>">
                            </div>
                            <div>
                                <label class="form-label">Account Name</label>
                                <input type="text" name="ewallet_name" class="form-input"
                                       value="<?= ($editing && $editing['type']==='ewallet') ? h($editing['detail']['account_name'] ?? '') : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- QRIS fields -->
                    <div id="fields-qris" class="hidden">
                        <label class="form-label">QR Code Image</label>
                        <?php if ($editing && $editing['type']==='qris' && $editing['qr_image']): ?>
                        <img src="<?= h($editing['qr_image']) ?>" alt="QRIS" class="h-32 mb-2 object-contain">
                        <?php endif; ?>
                        <input type="file" name="qr_image" class="form-input" accept="image/*">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="pm_active" name="is_active"
                               class="accent-green-500 h-4 w-4"
                               <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
                        <label for="pm_active" class="text-sm text-gray-300">Active</label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" name="save_method" class="btn-neon px-5 py-2 rounded-lg text-sm font-bold">
                            💾 <?= $editing ? 'Update' : 'Add' ?> Method
                        </button>
                        <?php if ($editing): ?>
                        <a href="<?= h($_SERVER['PHP_SELF']) ?>"
                           class="px-5 py-2 rounded-lg text-sm border border-gray-600 text-gray-400 hover:border-gray-400 transition-colors">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Methods list -->
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-4">All Payment Methods</h2>
                <?php if (empty($methods)): ?>
                <p class="text-gray-500 text-sm">No payment methods configured.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($methods as $m):
                        $typeLabels = ['bank' => '🏦', 'ewallet' => '📱', 'qris' => '🔳'];
                    ?>
                    <div class="flex items-start justify-between gap-4 p-3 rounded-lg bg-gray-800/40 border border-gray-700">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm">
                                <?= $typeLabels[$m['type']] ?? '' ?> <?= h($m['label']) ?>
                                <span class="ml-2 text-xs <?= $m['is_active'] ? 'text-green-400' : 'text-gray-500' ?>">
                                    <?= $m['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </p>
                            <?php if ($m['type'] === 'bank' && !empty($m['detail']['account_no'])): ?>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <?= h($m['detail']['bank_name'] ?? '') ?> — <?= h($m['detail']['account_no']) ?> (<?= h($m['detail']['account_name'] ?? '') ?>)
                            </p>
                            <?php elseif ($m['type'] === 'ewallet' && !empty($m['detail']['number'])): ?>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <?= h($m['detail']['provider'] ?? '') ?> — <?= h($m['detail']['number']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 text-xs shrink-0">
                            <a href="?edit=<?= (int)$m['id'] ?>"
                               class="px-2 py-1 rounded border border-gray-600 text-gray-300 hover:border-green-500 hover:text-green-400 transition-colors">Edit</a>
                            <!-- Toggle active -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="method_id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="new_state" value="<?= $m['is_active'] ? 0 : 1 ?>">
                                <button type="submit" name="toggle_method"
                                        class="px-2 py-1 rounded border border-gray-600 text-yellow-400 hover:border-yellow-400 transition-colors">
                                    <?= $m['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete?');" class="inline">
                                <input type="hidden" name="method_id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" name="delete_method"
                                        class="px-2 py-1 rounded border border-gray-600 text-red-400 hover:border-red-400 transition-colors">Del</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="/assets/js/main.js"></script>
<script>
// Show / hide payment type specific fields
function togglePaymentFields() {
    const type = document.getElementById('pm-type').value;
    document.getElementById('fields-bank').classList.toggle('hidden',    type !== 'bank');
    document.getElementById('fields-ewallet').classList.toggle('hidden', type !== 'ewallet');
    document.getElementById('fields-qris').classList.toggle('hidden',    type !== 'qris');
}
// Init on load
document.addEventListener('DOMContentLoaded', togglePaymentFields);
</script>
</body>
</html>
