<?php
/**
 * NexusCore OS — header.php
 * Included at the top of every public-facing page.
 * Outputs <head>, navigation bar, and opens the <body>.
 */

// Ensure config is loaded (safe to include multiple times)
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/config.php';
}

$siteName   = h(getSetting('site_name', 'NexusCore OS'));
$seoTitle   = h(getSetting('seo_title', $siteName));
$seoDesc    = h(getSetting('seo_description', ''));
$seoKw      = h(getSetting('seo_keywords', ''));
$neonColor  = h(getSetting('neon_color', '#00ff99'));
$logoPath   = getSetting('site_logo', '');
$faviconPath = getSetting('site_favicon', '');

// Cart item count (only when shop is active)
$cartCount = 0;
if (SHOP_ENABLED && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

// Current page for active-nav detection
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $seoTitle ?></title>
    <meta name="description" content="<?= $seoDesc ?>">
    <meta name="keywords"    content="<?= $seoKw ?>">

    <!-- Favicon -->
    <?php if ($faviconPath): ?>
    <link rel="icon" href="<?= h($faviconPath) ?>" type="image/x-icon">
    <?php else: ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌌</text></svg>">
    <?php endif; ?>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- AOS.js (scroll animations) -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">

    <!-- Custom stylesheet -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Dynamic neon CSS variable -->
    <style>
        :root { --neon: <?= $neonColor ?>; }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 font-mono overflow-x-hidden">

<!-- ============================================================
     NAVIGATION BAR
     ============================================================ -->
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 bg-gray-900/80 backdrop-blur-md border-b border-gray-800 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Logo / Site name -->
            <a href="/" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <?php if ($logoPath): ?>
                    <img src="<?= h($logoPath) ?>" alt="Logo" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <span class="text-2xl">🌌</span>
                <?php endif; ?>
                <span class="text-lg font-bold" style="color: var(--neon)"><?= $siteName ?></span>
            </a>

            <!-- Desktop nav links -->
            <div class="hidden md:flex items-center gap-6 text-sm">
                <a href="/#hero"     class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">Home</a>
                <a href="/#about"    class="nav-link">About</a>
                <a href="/#projects" class="nav-link">Projects</a>
                <a href="/#gallery"  class="nav-link">Gallery</a>
                <?php if (SHOP_ENABLED): ?>
                <a href="/#shop"     class="nav-link">Shop</a>
                <?php endif; ?>
                <a href="/#contact"  class="nav-link">Contact</a>

                <?php if (SHOP_ENABLED): ?>
                <!-- Cart icon with badge -->
                <a href="/modules/shop/cart.php" class="relative hover:text-green-400 transition-colors" title="Cart">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.4 5h12.8M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z"/>
                    </svg>
                    <?php if ($cartCount > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-green-500 text-black text-xs font-bold rounded-full h-4 w-4 flex items-center justify-center">
                        <?= min($cartCount, 99) ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Mobile hamburger -->
            <button id="hamburger" class="md:hidden text-gray-400 hover:text-white focus:outline-none" aria-label="Toggle menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="ham-open"   stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path id="ham-close"  stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" class="hidden"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-gray-900 border-t border-gray-800 px-4 pb-4 pt-2 space-y-2 text-sm">
        <a href="/#hero"     class="block nav-link py-1">Home</a>
        <a href="/#about"    class="block nav-link py-1">About</a>
        <a href="/#projects" class="block nav-link py-1">Projects</a>
        <a href="/#gallery"  class="block nav-link py-1">Gallery</a>
        <?php if (SHOP_ENABLED): ?>
        <a href="/#shop"     class="block nav-link py-1">Shop</a>
        <a href="/modules/shop/cart.php" class="block nav-link py-1">
            🛒 Cart <?= $cartCount > 0 ? "({$cartCount})" : '' ?>
        </a>
        <?php endif; ?>
        <a href="/#contact"  class="block nav-link py-1">Contact</a>
    </div>
</nav>

<!-- Spacer for fixed nav -->
<div class="h-16"></div>

<!-- ============================================================
     AOS + hamburger init (inline; main.js handles the rest)
     ============================================================ -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
