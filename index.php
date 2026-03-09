<?php
/**
 * NexusCore OS — index.php
 * Main public homepage: preloader, hero, about, projects, gallery, shop, chat, contact.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/api/telegram.php';

// ---------------------------------------------------------------
// Handle contact form POST (AJAX)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    header('Content-Type: application/json; charset=utf-8');

    $name    = trim((string)($_POST['contact_name']    ?? ''));
    $email   = trim((string)($_POST['contact_email']   ?? ''));
    $message = trim((string)($_POST['contact_message'] ?? ''));

    if ($name === '' || $email === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    $success = false;
    if ($db) {
        $stmt = $db->prepare(
            'INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('sss', $name, $email, $message);
            $success = $stmt->execute();
            $stmt->close();
        }
    }

    if ($success) {
        // Send Telegram notification
        $msgData = [
            'name'       => $name,
            'email'      => $email,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        sendContactNotification($msgData);
        echo json_encode(['success' => true, 'message' => '✅ Message sent! We will get back to you soon.']);
    } else {
        echo json_encode(['success' => false, 'message' => '❌ Failed to save message. Please try again.']);
    }
    exit;
}

// ---------------------------------------------------------------
// Load public data for page rendering
// ---------------------------------------------------------------

// Projects
$projects = [];
if ($db) {
    $res = $db->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $projects[] = $row;
        $res->free();
    }
}

// Gallery
$gallery = [];
if ($db) {
    $res = $db->query('SELECT * FROM gallery ORDER BY sort_order ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $gallery[] = $row;
        $res->free();
    }
}

// Products (only if shop enabled)
$products = [];
if (SHOP_ENABLED && $db) {
    $res = $db->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) $products[] = $row;
        $res->free();
    }
}

$siteName  = getSetting('site_name', 'NexusCore OS');
$neonColor = getSetting('neon_color', '#00ff99');

require_once __DIR__ . '/header.php';
?>

<!-- ============================================================
     TERMINAL PRELOADER
     ============================================================ -->
<div id="preloader">
    <div class="terminal-line">&gt; Initializing <?= h($siteName) ?>...</div>
    <div class="terminal-line">&gt; Loading modules... [OK]</div>
    <div class="terminal-line">&gt; Connecting to database... <?= $db ? '[OK]' : '[WARN: No DB]' ?></div>
    <div class="terminal-line">&gt; Rendering interface... [OK]</div>
    <div class="terminal-line">&gt; Welcome.</div>
</div>

<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<section id="hero" class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <!-- Animated grid background -->
    <div class="absolute inset-0 opacity-10"
         style="background-image: linear-gradient(var(--neon) 1px, transparent 1px),
                                  linear-gradient(90deg, var(--neon) 1px, transparent 1px);
                background-size: 40px 40px;">
    </div>

    <div class="relative z-10 text-center px-4">
        <p class="text-sm tracking-widest uppercase mb-4 opacity-60" data-aos="fade-down">
            Welcome to <?= h($siteName) ?>
        </p>
        <h1 class="text-5xl md:text-7xl font-extrabold mb-4" data-aos="fade-up">
            <span style="color: var(--neon)">I'm a</span><br>
            <span id="typed-output"
                  data-roles='["Full-Stack Developer","CMS Builder","Open Source Contributor","Linux Enthusiast"]'
                  class="text-white"></span>
        </h1>
        <p class="text-gray-400 max-w-xl mx-auto mb-8" data-aos="fade-up" data-aos-delay="200">
            Building modular, scalable, and beautiful web experiences with PHP, MySQL &amp; modern front-end tech.
        </p>
        <div class="flex flex-wrap gap-4 justify-center" data-aos="fade-up" data-aos-delay="350">
            <a href="#projects" class="btn-neon px-6 py-3 rounded-xl text-sm font-bold">View Projects</a>
            <?php if (SHOP_ENABLED): ?>
            <a href="#shop" class="border border-current px-6 py-3 rounded-xl text-sm font-bold hover:bg-white/5 transition-colors"
               style="color: var(--neon)">Shop Services</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     ABOUT SECTION
     ============================================================ -->
<section id="about" class="py-24 px-4">
    <div class="max-w-4xl mx-auto">
        <h2 class="section-title" data-aos="fade-right">About Me</h2>
        <div class="mt-10 grid md:grid-cols-2 gap-10 items-center">
            <div data-aos="fade-right" data-aos-delay="100">
                <p class="text-gray-300 leading-relaxed mb-6">
                    I'm a passionate full-stack developer and system administrator who loves building
                    efficient, modular solutions. I specialise in PHP/MySQL backends, Linux server
                    management, and creating rich front-end experiences.
                </p>
                <p class="text-gray-400 leading-relaxed">
                    <span style="color: var(--neon)">NexusCore OS</span> is my personal CMS &amp; marketplace
                    platform — designed to be fully controllable from a single dashboard.
                </p>
            </div>
            <div data-aos="fade-left" data-aos-delay="200">
                <p class="text-sm text-gray-500 uppercase tracking-widest mb-4">Core Skills</p>
                <div class="flex flex-wrap gap-2">
                    <?php
                    $skills = ['PHP 8', 'MySQL', 'JavaScript', 'Linux', 'Nginx', 'Docker',
                               'Tailwind CSS', 'REST APIs', 'Telegram Bots', 'Shell Scripting'];
                    foreach ($skills as $skill):
                    ?>
                    <span class="skill-badge"><?= h($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     PROJECTS SECTION
     ============================================================ -->
<section id="projects" class="py-24 px-4 bg-gray-900/40">
    <div class="max-w-6xl mx-auto">
        <h2 class="section-title" data-aos="fade-right">Projects</h2>
        <?php if (empty($projects)): ?>
        <p class="text-gray-500 mt-6">No projects yet. Add some from the admin dashboard.</p>
        <?php else: ?>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($projects as $proj): ?>
            <div class="glass-card p-5 product-card" data-aos="fade-up">
                <?php if ($proj['image']): ?>
                <img src="<?= h($proj['image']) ?>" alt="<?= h($proj['title']) ?>"
                     class="w-full h-40 object-cover rounded-lg mb-4">
                <?php endif; ?>
                <h3 class="font-bold text-white mb-1"><?= h($proj['title']) ?></h3>
                <p class="text-gray-400 text-sm mb-3 line-clamp-2"><?= h($proj['description']) ?></p>
                <!-- Tech stack tags -->
                <div class="flex flex-wrap gap-1 mb-4">
                    <?php foreach (explode(',', $proj['tech_stack']) as $tag): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-800 text-gray-300">
                        <?= h(trim($tag)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-3 text-sm">
                    <?php if ($proj['github_url'] && $proj['github_url'] !== '#'): ?>
                    <a href="<?= h($proj['github_url']) ?>" target="_blank" rel="noopener"
                       class="text-gray-400 hover:text-white transition-colors">GitHub ↗</a>
                    <?php endif; ?>
                    <?php if ($proj['demo_url'] && $proj['demo_url'] !== '#'): ?>
                    <a href="<?= h($proj['demo_url']) ?>" target="_blank" rel="noopener"
                       style="color: var(--neon)" class="hover:opacity-80 transition-opacity">Live Demo ↗</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     GALLERY SECTION
     ============================================================ -->
<section id="gallery" class="py-24 px-4">
    <div class="max-w-6xl mx-auto">
        <h2 class="section-title" data-aos="fade-right">Gallery</h2>
        <?php if (empty($gallery)): ?>
        <p class="text-gray-500 mt-6">No gallery images yet.</p>
        <?php else: ?>
        <div class="gallery-grid mt-10">
            <?php foreach ($gallery as $img): ?>
            <figure data-aos="zoom-in">
                <img src="<?= h($img['image_path']) ?>" alt="<?= h($img['caption']) ?>"
                     loading="lazy">
                <?php if ($img['caption']): ?>
                <figcaption class="text-xs text-gray-500 text-center -mt-2 mb-2"><?= h($img['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     SHOP SECTION (conditional)
     ============================================================ -->
<?php if (SHOP_ENABLED): ?>
<section id="shop" class="py-24 px-4 bg-gray-900/40">
    <div class="max-w-6xl mx-auto">
        <h2 class="section-title" data-aos="fade-right">Shop &amp; Services</h2>
        <p class="text-gray-400 text-sm mt-2 mb-10" data-aos="fade-right">
            Professional services delivered directly to you.
        </p>

        <?php if (empty($products)): ?>
        <p class="text-gray-500">No products listed yet.</p>
        <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($products as $p):
                $now          = time();
                $isFlash      = !empty($p['flash_sale_price'])
                                && !empty($p['flash_sale_end'])
                                && strtotime($p['flash_sale_end']) > $now;
                $displayPrice = $isFlash ? (float)$p['flash_sale_price'] : (float)$p['price'];
            ?>
            <div class="glass-card product-card overflow-hidden" data-aos="fade-up">
                <?php if ($p['image']): ?>
                <img src="<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>">
                <?php else: ?>
                <div class="w-full h-44 bg-gray-800 flex items-center justify-center text-5xl">📦</div>
                <?php endif; ?>

                <div class="p-4">
                    <!-- Flash sale badge -->
                    <?php if ($isFlash): ?>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="flash-badge">⚡ Flash Sale</span>
                        <span class="flash-countdown" data-end="<?= h($p['flash_sale_end']) ?>"></span>
                    </div>
                    <?php endif; ?>

                    <span class="text-xs text-gray-500 uppercase tracking-wider"><?= h($p['category']) ?></span>
                    <h3 class="font-bold text-white mt-1 mb-1"><?= h($p['name']) ?></h3>
                    <p class="text-gray-400 text-xs mb-3 line-clamp-2"><?= h($p['description']) ?></p>

                    <!-- Price -->
                    <div class="flex items-baseline gap-2 mb-3">
                        <span class="text-lg font-bold" style="color: var(--neon)">
                            <?= formatRupiah($displayPrice) ?>
                        </span>
                        <?php if ($isFlash): ?>
                        <span class="text-sm text-gray-500 line-through">
                            <?= formatRupiah((float)$p['price']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stock indicator -->
                    <?php if ($p['stock_type'] === 'finite'): ?>
                    <p class="text-xs mb-3 <?= $p['stock_qty'] > 0 ? 'text-green-400' : 'text-red-400' ?>">
                        <?= $p['stock_qty'] > 0 ? "In stock: {$p['stock_qty']} left" : 'Out of stock' ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($p['stock_type'] === 'unlimited' || $p['stock_qty'] > 0): ?>
                    <button class="btn-neon w-full py-2 rounded-lg text-sm add-to-cart-btn"
                            data-product-id="<?= (int)$p['id'] ?>">
                        🛒 Add to Cart
                    </button>
                    <?php else: ?>
                    <button class="w-full py-2 rounded-lg text-sm bg-gray-700 text-gray-500 cursor-not-allowed" disabled>
                        Out of Stock
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="/modules/shop/cart.php" class="btn-neon px-6 py-3 rounded-xl text-sm font-bold">
                🛒 View Cart &amp; Checkout
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================
     CONTACT SECTION
     ============================================================ -->
<section id="contact" class="py-24 px-4">
    <div class="max-w-2xl mx-auto">
        <h2 class="section-title" data-aos="fade-right">Contact</h2>
        <p class="text-gray-400 text-sm mt-2 mb-8" data-aos="fade-right">
            Have a question or project in mind? Send a message!
        </p>

        <div id="contact-status" class="alert hidden"></div>

        <form id="contact-form" action="/?action=contact" method="POST"
              data-aos="fade-up" class="glass-card p-6 space-y-4">
            <input type="hidden" name="action" value="contact">

            <div>
                <label class="form-label" for="contact_name">Name</label>
                <input type="text" id="contact_name" name="contact_name"
                       class="form-input" placeholder="Your name" required>
            </div>
            <div>
                <label class="form-label" for="contact_email">Email</label>
                <input type="email" id="contact_email" name="contact_email"
                       class="form-input" placeholder="your@email.com" required>
            </div>
            <div>
                <label class="form-label" for="contact_message">Message</label>
                <textarea id="contact_message" name="contact_message" rows="5"
                          class="form-input" placeholder="Your message…" required></textarea>
            </div>

            <button type="submit" class="btn-neon w-full py-3 rounded-xl text-sm font-bold">
                Send Message
            </button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
