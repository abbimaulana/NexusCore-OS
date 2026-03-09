/**
 * NexusCore OS — assets/js/main.js
 * Handles: terminal preloader, typed-text hero, mobile nav,
 *          flash-sale countdown timers, cart helpers, chatbot widget.
 */

'use strict';

// ---------------------------------------------------------------
// 1. AOS — scroll animation init
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    if (typeof AOS !== 'undefined') {
        AOS.init({ once: true, duration: 700, easing: 'ease-out-quart' });
    }

    initHamburger();
    initFlashCountdowns();
    initChatbot();
    initContactForm();
    initAddToCart();
});

// ---------------------------------------------------------------
// 2. TERMINAL PRELOADER
// ---------------------------------------------------------------
(function initPreloader() {
    const preloader = document.getElementById('preloader');
    if (!preloader) return;

    const lines = [
        '> Initializing NexusCore OS...',
        '> Loading modules... [OK]',
        '> Connecting to database... [OK]',
        '> Rendering interface... [OK]',
        '> Welcome.',
    ];

    let index = 0;
    const lineEls = preloader.querySelectorAll('.terminal-line');

    function showNext() {
        if (index < lineEls.length) {
            lineEls[index].classList.add('show');
            index++;
            setTimeout(showNext, 480);
        } else {
            // Fade out preloader
            setTimeout(() => {
                preloader.style.transition = 'opacity 0.6s';
                preloader.style.opacity   = '0';
                setTimeout(() => {
                    preloader.style.display = 'none';
                    // Trigger hero typed text
                    initTyped();
                }, 600);
            }, 400);
        }
    }

    showNext();
})();

// ---------------------------------------------------------------
// 3. TYPED TEXT (hero section)
// ---------------------------------------------------------------
function initTyped() {
    const el = document.getElementById('typed-output');
    if (!el) return;

    const roles = el.dataset.roles
        ? JSON.parse(el.dataset.roles)
        : ['Full-Stack Developer', 'CMS Builder', 'Open Source Contributor', 'Linux Enthusiast'];

    let roleIdx  = 0;
    let charIdx  = 0;
    let deleting = false;

    function tick() {
        const current = roles[roleIdx];

        if (!deleting) {
            charIdx++;
            el.textContent = current.slice(0, charIdx);
            if (charIdx === current.length) {
                deleting = true;
                setTimeout(tick, 1800);
                return;
            }
        } else {
            charIdx--;
            el.textContent = current.slice(0, charIdx);
            if (charIdx === 0) {
                deleting  = false;
                roleIdx   = (roleIdx + 1) % roles.length;
                setTimeout(tick, 300);
                return;
            }
        }

        setTimeout(tick, deleting ? 50 : 90);
    }

    tick();
}

// ---------------------------------------------------------------
// 4. MOBILE HAMBURGER MENU
// ---------------------------------------------------------------
function initHamburger() {
    const btn    = document.getElementById('hamburger');
    const menu   = document.getElementById('mobile-menu');
    const open   = document.getElementById('ham-open');
    const close  = document.getElementById('ham-close');
    if (!btn || !menu) return;

    btn.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden', isOpen);
        open?.classList.toggle('hidden',  !isOpen);
        close?.classList.toggle('hidden',  isOpen);
    });
}

// ---------------------------------------------------------------
// 5. FLASH SALE COUNTDOWN TIMERS
// ---------------------------------------------------------------
function initFlashCountdowns() {
    document.querySelectorAll('[data-end]').forEach(el => {
        const endTime = new Date(el.dataset.end).getTime();

        function update() {
            const diff = endTime - Date.now();
            if (diff <= 0) {
                el.textContent = 'SALE ENDED';
                return;
            }
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        }

        update();
        setInterval(update, 1000);
    });
}

// ---------------------------------------------------------------
// 6. ADD-TO-CART (AJAX)
// ---------------------------------------------------------------
function initAddToCart() {
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const productId = btn.dataset.productId;
            if (!productId) return;

            try {
                const res  = await fetch('/modules/shop/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    `action=add&product_id=${encodeURIComponent(productId)}`,
                });
                const data = await res.json();

                if (data.success) {
                    // Update cart badge
                    const badge = document.querySelector('#cart-badge');
                    if (badge) badge.textContent = data.cartCount;
                    btn.textContent = '✓ Added';
                    btn.classList.add('opacity-75');
                    setTimeout(() => {
                        btn.textContent = '🛒 Add to Cart';
                        btn.classList.remove('opacity-75');
                    }, 1500);
                } else {
                    alert(data.message || 'Could not add item to cart.');
                }
            } catch (err) {
                console.error('Cart error:', err);
            }
        });
    });
}

// ---------------------------------------------------------------
// 7. CHATBOT WIDGET
// ---------------------------------------------------------------
function initChatbot() {
    const toggle   = document.getElementById('chat-toggle');
    const window_  = document.getElementById('chat-window');
    const closeBtn = document.getElementById('chat-close');
    const input    = document.getElementById('chat-input');
    const sendBtn  = document.getElementById('chat-send');
    const messages = document.getElementById('chat-messages');

    if (!toggle || !window_) return;

    toggle.addEventListener('click', () => window_.classList.toggle('hidden'));
    closeBtn?.addEventListener('click', () => window_.classList.add('hidden'));

    function appendMessage(text, role) {
        const div    = document.createElement('div');
        div.className = `chat-msg-${role} mb-1`;
        const bubble = document.createElement('span');
        bubble.className = 'bubble';
        bubble.textContent = text;
        div.appendChild(bubble);
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage() {
        const msg = input.value.trim();
        if (!msg) return;

        appendMessage(msg, 'user');
        input.value = '';
        sendBtn.disabled = true;

        try {
            const res  = await fetch('/modules/api/chatbot_api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ message: msg }),
            });
            const data = await res.json();
            appendMessage(data.reply || 'Sorry, no response.', 'bot');
        } catch {
            appendMessage('⚠️ Connection error.', 'bot');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    sendBtn?.addEventListener('click', sendMessage);
    input?.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

    // Greet user on first open
    let greeted = false;
    toggle.addEventListener('click', () => {
        if (!greeted && !window_.classList.contains('hidden')) {
            greeted = true;
            setTimeout(() => appendMessage('👋 Hello! How can I help you today?', 'bot'), 400);
        }
    });
}

// ---------------------------------------------------------------
// 8. CONTACT FORM (AJAX)
// ---------------------------------------------------------------
function initContactForm() {
    const form = document.getElementById('contact-form');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn     = form.querySelector('[type=submit]');
        const status  = document.getElementById('contact-status');
        btn.disabled  = true;
        btn.textContent = 'Sending…';

        try {
            const res  = await fetch(form.action || '/', {
                method:  'POST',
                body:    new FormData(form),
            });
            const data = await res.json();

            if (status) {
                status.textContent  = data.message || (data.success ? 'Message sent!' : 'Error sending.');
                status.className    = data.success ? 'alert alert-success' : 'alert alert-error';
                status.style.display = 'block';
            }

            if (data.success) form.reset();
        } catch {
            if (status) {
                status.textContent   = '⚠️ Network error. Please try again.';
                status.className     = 'alert alert-error';
                status.style.display = 'block';
            }
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Send Message';
        }
    });
}

// ---------------------------------------------------------------
// 9. ADMIN — quantity stepper helpers (used on cart page)
// ---------------------------------------------------------------
function updateCartQty(productId, delta) {
    const input = document.querySelector(`input[name="qty_${productId}"]`);
    if (!input) return;
    const newVal = Math.max(0, parseInt(input.value, 10) + delta);
    input.value = newVal;
}
