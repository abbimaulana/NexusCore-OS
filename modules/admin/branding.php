<?php
/**
 * NexusCore OS — modules/admin/branding.php
 * Site branding: name, logo, favicon, SEO meta, neon colour.
 */

session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();

$msg  = '';
$type = 'success';

// ---------------------------------------------------------------
// POST: save branding settings
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branding'])) {
    // Text fields
    $fields = ['site_name', 'seo_title', 'seo_description', 'seo_keywords', 'neon_color'];
    foreach ($fields as $field) {
        $val = trim((string)($_POST[$field] ?? ''));
        setSetting($field, $val);
    }

    // File upload helper
    $allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $uploadDir    = __DIR__ . '/../../assets/uploads/';

    foreach (['site_logo', 'site_favicon'] as $fileKey) {
        if (!empty($_FILES[$fileKey]['tmp_name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $tmp      = $_FILES[$fileKey]['tmp_name'];
            $origName = basename($_FILES[$fileKey]['name']);
            $mime     = mime_content_type($tmp);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($mime, $allowedMimes, true) || !in_array($ext, ['png','jpg','jpeg','gif','webp','ico'], true)) {
                $msg  = "Invalid file type for {$fileKey}.";
                $type = 'error';
                continue;
            }

            $newName = $fileKey . '_' . time() . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                setSetting($fileKey, '/assets/uploads/' . $newName);
            } else {
                $msg  = "Failed to upload {$fileKey}.";
                $type = 'error';
            }
        }
    }

    if ($type === 'success') {
        $msg = 'Branding settings saved.';
    }
}

$siteName       = h(getSetting('site_name', 'NexusCore OS'));
$neonColor      = h(getSetting('neon_color', '#00ff99'));
$seoTitle       = h(getSetting('seo_title', ''));
$seoDesc        = h(getSetting('seo_description', ''));
$seoKw          = h(getSetting('seo_keywords', ''));
$currentSection = 'branding';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branding — <?= $siteName ?></title>
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
            <h1 class="text-2xl font-extrabold mb-6" style="color: var(--neon)">Site Branding</h1>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $type ?> mb-4"><?= h($msg) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" class="glass-card p-6 space-y-5">
                <div>
                    <label class="form-label">Site Name</label>
                    <input type="text" name="site_name" class="form-input"
                           value="<?= $siteName ?>" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Logo (image)</label>
                        <?php $logo = getSetting('site_logo'); if ($logo): ?>
                        <img src="<?= h($logo) ?>" alt="Logo" class="h-10 mb-2 object-contain">
                        <?php endif; ?>
                        <input type="file" name="site_logo" class="form-input" accept="image/*">
                    </div>
                    <div>
                        <label class="form-label">Favicon (image)</label>
                        <?php $fav = getSetting('site_favicon'); if ($fav): ?>
                        <img src="<?= h($fav) ?>" alt="Favicon" class="h-8 mb-2 object-contain">
                        <?php endif; ?>
                        <input type="file" name="site_favicon" class="form-input" accept="image/*">
                    </div>
                </div>

                <div>
                    <label class="form-label">Neon Accent Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="neon_color"
                               value="<?= $neonColor ?>"
                               class="h-10 w-16 rounded cursor-pointer border border-gray-700 bg-transparent">
                        <span class="text-xs text-gray-500">Current: <strong><?= $neonColor ?></strong></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">SEO Title</label>
                    <input type="text" name="seo_title" class="form-input" value="<?= $seoTitle ?>">
                </div>
                <div>
                    <label class="form-label">SEO Description</label>
                    <textarea name="seo_description" rows="2" class="form-input"><?= $seoDesc ?></textarea>
                </div>
                <div>
                    <label class="form-label">SEO Keywords</label>
                    <input type="text" name="seo_keywords" class="form-input"
                           placeholder="keyword1, keyword2, …"
                           value="<?= $seoKw ?>">
                </div>

                <button type="submit" name="save_branding" class="btn-neon px-5 py-2.5 rounded-lg text-sm font-bold">
                    💾 Save Branding
                </button>
            </form>
        </div>
    </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
