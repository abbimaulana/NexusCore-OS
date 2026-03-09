<?php
/**
 * NexusCore OS — footer.php
 * Included at the bottom of every public-facing page.
 * Outputs the site footer and closes </body></html>.
 */

if (!function_exists('getSetting')) {
    require_once __DIR__ . '/config.php';
}

$siteName  = h(getSetting('site_name', 'NexusCore OS'));
$neonColor = h(getSetting('neon_color', '#00ff99'));
$year      = date('Y');

// Social links could be stored in settings; hardcoded defaults here
$socials = [
    ['label' => 'GitHub',   'url' => '#', 'icon' => '🐙'],
    ['label' => 'Telegram', 'url' => '#', 'icon' => '✈️'],
    ['label' => 'LinkedIn', 'url' => '#', 'icon' => '💼'],
];
?>
<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="mt-24 border-t border-gray-800 bg-gray-950/90 backdrop-blur-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">

            <!-- Brand -->
            <div class="text-center md:text-left">
                <p class="text-lg font-bold" style="color: var(--neon)">
                    <?= $siteName ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    © <?= $year ?> All rights reserved.
                    Powered by <span style="color: var(--neon)">NexusCore OS</span> v<?= SITE_VERSION ?>.
                </p>
            </div>

            <!-- Social links -->
            <div class="flex items-center gap-4">
                <?php foreach ($socials as $social): ?>
                <a href="<?= h($social['url']) ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="text-gray-400 hover:text-white transition-colors text-lg"
                   title="<?= h($social['label']) ?>">
                    <?= $social['icon'] ?>
                    <span class="sr-only"><?= h($social['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Subtle terminal tagline -->
        <p class="text-center text-xs text-gray-700 mt-6 font-mono">
            &gt; system.shutdown() &nbsp;— &nbsp;<span style="color:var(--neon)">goodbye, operator.</span>
        </p>
    </div>
</footer>

<!-- AI Chatbot floating widget (shown when enabled) -->
<?php if (CHATBOT_ENABLED): ?>
<div id="chatbot-widget" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <!-- Chat window (hidden by default) -->
    <div id="chat-window" class="hidden glass-card w-80 max-h-96 flex flex-col rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700"
             style="background: rgba(0,0,0,0.6)">
            <span class="font-bold text-sm" style="color: var(--neon)">🤖 NexusBot</span>
            <button id="chat-close" class="text-gray-400 hover:text-white text-lg leading-none">&times;</button>
        </div>
        <div id="chat-messages" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm bg-gray-900/80"></div>
        <div class="flex gap-2 p-2 border-t border-gray-700 bg-gray-900/80">
            <input id="chat-input" type="text" placeholder="Type a message…"
                   class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-100 focus:outline-none focus:border-green-500">
            <button id="chat-send" class="btn-neon px-3 py-1.5 rounded-lg text-sm font-bold">Send</button>
        </div>
    </div>

    <!-- Toggle button -->
    <button id="chat-toggle"
            class="h-14 w-14 rounded-full shadow-lg flex items-center justify-center text-2xl transition-transform hover:scale-110"
            style="background: var(--neon); color: #000"
            title="Chat with NexusBot">
        💬
    </button>
</div>
<?php endif; ?>

<!-- Main JavaScript -->
<script src="/assets/js/main.js"></script>
</body>
</html>
