<?php
/**
 * NexusCore OS — modules/admin/chatbot.php
 * Configure the AI chatbot: provider, API key, auto-response script.
 * Manage keyword-based responses (CRUD).
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

$msg  = '';
$type = 'success';

// ---------------------------------------------------------------
// POST: save chatbot settings
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_settings'])) {
        $provider   = in_array($_POST['chatbot_provider'] ?? '', ['auto','openai','gemini'])
                      ? $_POST['chatbot_provider'] : 'auto';
        $apiKey     = trim((string)($_POST['chatbot_api_key'] ?? ''));
        $autoScript = trim((string)($_POST['chatbot_auto_script'] ?? ''));

        setSetting('chatbot_provider',    $provider);
        setSetting('chatbot_api_key',     $apiKey);
        setSetting('chatbot_auto_script', $autoScript);
        $msg = 'Chatbot settings saved.';
    }

    if (isset($_POST['save_response'])) {
        $respId   = (int)($_POST['response_id'] ?? 0);
        $keywords = trim((string)($_POST['keywords'] ?? ''));
        $respText = trim((string)($_POST['response_text'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($keywords !== '' && $respText !== '' && $db) {
            if ($respId > 0) {
                $stmt = $db->prepare(
                    'UPDATE chatbot_responses SET keywords=?, response_text=?, is_active=? WHERE id=?'
                );
                $stmt->bind_param('ssii', $keywords, $respText, $isActive, $respId);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO chatbot_responses (keywords, response_text, is_active) VALUES (?,?,?)'
                );
                $stmt->bind_param('ssi', $keywords, $respText, $isActive);
            }
            $msg  = $stmt->execute() ? 'Response saved.' : 'Save failed.';
            $type = $stmt->affected_rows < 0 ? 'error' : 'success';
            $stmt->close();
        } else {
            $msg  = 'Keywords and response text are required.';
            $type = 'error';
        }
    }

    if (isset($_POST['delete_response'])) {
        $id   = (int)($_POST['response_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM chatbot_responses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $msg  = $stmt->execute() ? 'Response deleted.' : 'Delete failed.';
        $stmt->close();
    }
}

// Load editing row
$editing = null;
if (isset($_GET['edit_resp'])) {
    $id   = (int)$_GET['edit_resp'];
    $stmt = $db->prepare('SELECT * FROM chatbot_responses WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Load responses
$responses = [];
if ($db) {
    $res = $db->query('SELECT * FROM chatbot_responses ORDER BY id DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $responses[] = $row;
        $res->free();
    }
}

$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$provider       = h(getSetting('chatbot_provider', 'auto'));
$apiKey         = h(getSetting('chatbot_api_key', ''));
$autoScript     = h(getSetting('chatbot_auto_script', ''));
$currentSection = 'chatbot';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot — <?= $siteName ?></title>
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
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Chatbot Settings</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <!-- Provider settings -->
            <div class="glass-card p-5 mb-8">
                <h2 class="font-bold text-white mb-4">AI Provider Configuration</h2>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="space-y-4">
                    <div>
                        <label class="form-label">Provider</label>
                        <select name="chatbot_provider" class="form-input">
                            <option value="auto"   <?= $provider === 'auto'   ? 'selected' : '' ?>>Auto-Response (Keyword-based)</option>
                            <option value="openai" <?= $provider === 'openai' ? 'selected' : '' ?>>OpenAI (GPT)</option>
                            <option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">API Key (OpenAI / Gemini)</label>
                        <input type="password" name="chatbot_api_key" class="form-input"
                               value="<?= $apiKey ?>" placeholder="sk-… or AIza…" autocomplete="off">
                    </div>
                    <div>
                        <label class="form-label">Default Auto-Response (fallback message)</label>
                        <textarea name="chatbot_auto_script" rows="3" class="form-input"><?= $autoScript ?></textarea>
                    </div>
                    <button type="submit" name="save_settings" class="btn-neon px-5 py-2 rounded-lg text-sm font-bold">
                        💾 Save Settings
                    </button>
                </form>
            </div>

            <!-- Keyword responses CRUD -->
            <div class="glass-card p-5 mb-8">
                <h2 class="font-bold text-white mb-4"><?= $editing ? 'Edit Response' : 'Add Keyword Response' ?></h2>
                <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="space-y-4">
                    <input type="hidden" name="response_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
                    <div>
                        <label class="form-label">Keywords (comma-separated)</label>
                        <input type="text" name="keywords" class="form-input"
                               placeholder="hello, hi, halo"
                               value="<?= $editing ? h($editing['keywords']) : '' ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Response Text</label>
                        <textarea name="response_text" rows="3" class="form-input" required><?= $editing ? h($editing['response_text']) : '' ?></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="resp_active" name="is_active"
                               class="accent-green-500 h-4 w-4"
                               <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
                        <label for="resp_active" class="text-sm text-gray-300">Active</label>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="save_response" class="btn-neon px-5 py-2 rounded-lg text-sm font-bold">
                            💾 <?= $editing ? 'Update' : 'Add' ?>
                        </button>
                        <?php if ($editing): ?>
                        <a href="<?= h($_SERVER['PHP_SELF']) ?>"
                           class="px-5 py-2 rounded-lg text-sm border border-gray-600 text-gray-400 hover:border-gray-400 transition-colors">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Response list -->
            <div class="glass-card p-5">
                <h2 class="font-bold text-white mb-4">Saved Responses</h2>
                <?php if (empty($responses)): ?>
                <p class="text-gray-500 text-sm">No responses yet.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($responses as $r): ?>
                    <div class="flex items-start justify-between gap-4 p-3 rounded-lg bg-gray-800/40 border border-gray-700">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-400 mb-1">
                                Keywords: <span style="color: var(--neon)"><?= h($r['keywords']) ?></span>
                            </p>
                            <p class="text-sm text-gray-300 line-clamp-2"><?= h($r['response_text']) ?></p>
                        </div>
                        <div class="flex gap-2 text-xs shrink-0">
                            <a href="?edit_resp=<?= (int)$r['id'] ?>"
                               class="px-2 py-1 rounded border border-gray-600 text-gray-300 hover:border-green-500 hover:text-green-400 transition-colors">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete?');" class="inline">
                                <input type="hidden" name="response_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="delete_response"
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
</body>
</html>
