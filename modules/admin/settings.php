<?php
/**
 * NexusCore OS — modules/admin/settings.php
 * General settings: module toggles (shop, chatbot) and password change.
 * Password change is gated by RECOVERY_EMAIL.
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

global $RECOVERY_EMAIL;

$msg  = '';
$type = 'success';

// ---------------------------------------------------------------
// POST: toggle shop/chatbot modules
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['toggle_modules'])) {
        $shopEnabled    = isset($_POST['shop_enabled'])    ? '1' : '0';
        $chatbotEnabled = isset($_POST['chatbot_enabled']) ? '1' : '0';
        setSetting('shop_enabled',    $shopEnabled);
        setSetting('chatbot_enabled', $chatbotEnabled);
        $msg = 'Module settings updated.';
    }

    if (isset($_POST['change_password'])) {
        $email    = trim((string)($_POST['recovery_email'] ?? ''));
        $newPass  = (string)($_POST['new_password']   ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        // Log attempt regardless
        if ($db) {
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
            $logStmt  = $db->prepare(
                'INSERT INTO recovery_log (attempted_email, ip_address) VALUES (?, ?)'
            );
            $logStmt->bind_param('ss', $email, $ip);
            $logStmt->execute();
            $logStmt->close();
        }

        if ($email !== $RECOVERY_EMAIL) {
            $msg  = 'Recovery email does not match. Attempt has been logged.';
            $type = 'error';
        } elseif (strlen($newPass) < 8) {
            $msg  = 'Password must be at least 8 characters.';
            $type = 'error';
        } elseif ($newPass !== $confirm) {
            $msg  = 'Passwords do not match.';
            $type = 'error';
        } elseif ($db) {
            $hashed    = password_hash($newPass, PASSWORD_BCRYPT);
            $adminId   = (int)$_SESSION['nexus_admin_id'];
            $upStmt    = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upStmt->bind_param('si', $hashed, $adminId);
            if ($upStmt->execute()) {
                $msg = 'Password updated successfully.';
            } else {
                $msg  = 'Database error. Password not changed.';
                $type = 'error';
            }
            $upStmt->close();
        }
    }
}

$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$shopEnabled    = getSetting('shop_enabled',    '1') === '1';
$chatbotEnabled = getSetting('chatbot_enabled', '1') === '1';
$currentSection = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — <?= $siteName ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= $neonColor ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono">
<div class="flex min-h-screen">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Settings</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <!-- Module Toggles -->
            <div class="glass-card p-5 mb-6">
                <h2 class="font-bold text-white mb-4">Module Toggles</h2>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>">
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="shop_enabled" name="shop_enabled"
                               class="accent-green-500 h-4 w-4" <?= $shopEnabled ? 'checked' : '' ?>>
                        <label for="shop_enabled" class="text-sm text-gray-300">Enable Shop Module</label>
                    </div>
                    <div class="flex items-center gap-3 mb-5">
                        <input type="checkbox" id="chatbot_enabled" name="chatbot_enabled"
                               class="accent-green-500 h-4 w-4" <?= $chatbotEnabled ? 'checked' : '' ?>>
                        <label for="chatbot_enabled" class="text-sm text-gray-300">Enable AI Chatbot Widget</label>
                    </div>
                    <button type="submit" name="toggle_modules" class="btn-neon px-4 py-2 rounded-lg text-sm">Save Toggles</button>
                </form>
            </div>

            <!-- Change Password (recovery-gated) -->
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-1">Change Password</h2>
                <p class="text-xs text-gray-500 mb-4">
                    You must enter the Recovery Email defined in <code>config.php</code> to proceed.
                </p>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Recovery Email</label>
                            <input type="email" name="recovery_email" class="form-input"
                                   placeholder="recovery@email.com" required>
                        </div>
                        <div>
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input"
                                   placeholder="Minimum 8 characters" required minlength="8">
                        </div>
                        <div>
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-input"
                                   placeholder="Repeat new password" required>
                        </div>
                        <button type="submit" name="change_password"
                                class="btn-neon px-4 py-2 rounded-lg text-sm">
                            🔐 Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
