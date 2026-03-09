<?php
/**
 * NexusCore OS — config.php
 * Central configuration: DB credentials, constants, settings loader, helpers.
 * Edit this file once after installation; never hard-code credentials elsewhere.
 */

// ---------------------------------------------------------------
// 1. DATABASE CREDENTIALS
// ---------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'nexuscore_os');

// ---------------------------------------------------------------
// 2. SECURITY / RECOVERY
// ---------------------------------------------------------------
/** Only this e-mail is allowed to trigger password / critical-data changes. */
$RECOVERY_EMAIL = 'admin@nexuscore.os';

// ---------------------------------------------------------------
// 3. SITE CONSTANTS
// ---------------------------------------------------------------
define('SITE_VERSION',     '1.0.0');
define('ADMIN_LOGIN_PATH', 'gate-access.php');

// ---------------------------------------------------------------
// 4. DATABASE CONNECTION (MySQLi)
// ---------------------------------------------------------------
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    // During setup the DB may not exist yet — fail gracefully
    $db = null;
}
if ($db) {
    $db->set_charset('utf8mb4');
}

// ---------------------------------------------------------------
// 5. SETTINGS LOADER
// ---------------------------------------------------------------

/**
 * getSetting($key, $default)
 * Reads a value from the settings table. Results are cached in $GLOBALS.
 */
function getSetting(string $key, string $default = ''): string
{
    global $db, $_nexus_settings_cache;

    // Populate cache once per request
    if (!isset($_nexus_settings_cache)) {
        $_nexus_settings_cache = [];
        if ($db) {
            $res = $db->query('SELECT setting_key, setting_value FROM settings');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $_nexus_settings_cache[$row['setting_key']] = $row['setting_value'];
                }
                $res->free();
            }
        }
    }

    return $_nexus_settings_cache[$key] ?? $default;
}

/**
 * setSetting($key, $value)
 * Upserts a value in the settings table.
 */
function setSetting(string $key, string $value): bool
{
    global $db;
    if (!$db) return false;
    $stmt = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    // Bust cache
    global $_nexus_settings_cache;
    $_nexus_settings_cache = null;
    return $result;
}

// ---------------------------------------------------------------
// 6. MODULE TOGGLES (loaded from DB, with safe defaults)
// ---------------------------------------------------------------
if ($db) {
    define('SHOP_ENABLED',    getSetting('shop_enabled',    '1') === '1');
    define('CHATBOT_ENABLED', getSetting('chatbot_enabled', '1') === '1');
} else {
    define('SHOP_ENABLED',    false);
    define('CHATBOT_ENABLED', false);
}

// ---------------------------------------------------------------
// 7. UTILITY HELPERS
// ---------------------------------------------------------------

/**
 * h($str) — shorthand for htmlspecialchars with ENT_QUOTES + UTF-8.
 * Always use this when echoing user-supplied or DB data into HTML.
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * redirect($url) — safe redirect with exit.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * isAdminLoggedIn() — checks the session flag.
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['nexus_admin']) && $_SESSION['nexus_admin'] === true;
}

/**
 * requireAdmin() — redirects to login if not authenticated.
 */
function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        redirect(ADMIN_LOGIN_PATH);
    }
}

/**
 * generateOrderCode() — creates a unique order reference.
 */
function generateOrderCode(): string
{
    return 'NXS-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

/**
 * formatRupiah($amount) — formats a number as Indonesian Rupiah.
 */
function formatRupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * slugify($text) — converts a string to a URL-safe slug.
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}
