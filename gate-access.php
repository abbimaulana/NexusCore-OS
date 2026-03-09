<?php
/**
 * NexusCore OS — gate-access.php
 * Custom admin login page with brute-force protection.
 * Access via the URL configured in ADMIN_LOGIN_PATH.
 */

session_start();
require_once __DIR__ . '/config.php';

// Already logged in? Go to dashboard.
if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$error   = '';
$success = '';

// ---------------------------------------------------------------
// Handle login POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif ($db) {
        // Fetch user record
        $stmt = $db->prepare(
            'SELECT id, password, failed_attempts, locked_until FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Invalid credentials.';
        } else {
            // Check lock
            $lockedUntil = $user['locked_until'];
            if ($lockedUntil && strtotime($lockedUntil) > time()) {
                $minutesLeft = ceil((strtotime($lockedUntil) - time()) / 60);
                $error = "Account locked due to too many failed attempts. Try again in {$minutesLeft} minute(s).";
            } else {
                if (password_verify($password, $user['password'])) {
                    // SUCCESS — reset counters, set session
                    $resetStmt = $db->prepare(
                        'UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?'
                    );
                    $resetStmt->bind_param('i', $user['id']);
                    $resetStmt->execute();
                    $resetStmt->close();

                    $_SESSION['nexus_admin']    = true;
                    $_SESSION['nexus_admin_id'] = $user['id'];
                    $_SESSION['nexus_admin_user'] = $username;

                    redirect('dashboard.php');
                } else {
                    // FAILURE — increment counter
                    $attempts = $user['failed_attempts'] + 1;
                    $lockUntil = null;

                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS);
                        $error     = 'Too many failed attempts. Account locked for ' . (LOGIN_LOCKOUT_SECONDS / 60) . ' minutes.';
                    } else {
                        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                        $error     = "Invalid credentials. {$remaining} attempt(s) remaining before lockout.";
                    }

                    $upStmt = $db->prepare(
                        'UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?'
                    );
                    $upStmt->bind_param('isi', $attempts, $lockUntil, $user['id']);
                    $upStmt->execute();
                    $upStmt->close();
                }
            }
        }
    } else {
        $error = 'Database connection unavailable.';
    }
}

$siteName  = getSetting('site_name', 'NexusCore OS');
$neonColor = getSetting('neon_color', '#00ff99');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= h($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>:root { --neon: <?= h($neonColor) ?>; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-4">
    <!-- Logo / title -->
    <div class="text-center mb-8">
        <div class="text-5xl mb-3">🌌</div>
        <h1 class="text-2xl font-extrabold" style="color: var(--neon)"><?= h($siteName) ?></h1>
        <p class="text-gray-500 text-sm mt-1">Admin Access Panel</p>
    </div>

    <!-- Error / success messages -->
    <?php if ($error): ?>
    <div class="alert alert-error mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="glass-card p-6 space-y-5">
        <div>
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username"
                   class="form-input"
                   value="<?= h(isset($_POST['username']) ? $_POST['username'] : '') ?>"
                   autocomplete="username"
                   required autofocus>
        </div>
        <div>
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password"
                   class="form-input"
                   autocomplete="current-password"
                   required>
        </div>

        <button type="submit" class="btn-neon w-full py-3 rounded-xl font-bold text-sm">
            🔑 Login
        </button>
    </form>

    <p class="text-center text-xs text-gray-700 mt-6">
        &gt; <?= h($siteName) ?> v<?= SITE_VERSION ?> — Authorised access only.
    </p>
</div>

<script src="/assets/js/main.js"></script>
</body>
</html>
