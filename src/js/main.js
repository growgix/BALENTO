/**
 * BALENTO Application Bootstrap & Main Entry Point
 * Initializes observers, event listeners, drawers, and global utilities
 */

// Application Initialization
document.addEventListener('DOMContentLoaded', () => {
    initScrollObserver();
    initHeaderScroll();
    initStickyBarScroll();
    loadCartState();
    loadWishlistState();
    renderProductGrid();
    initDrawersAndModals();
    syncProductsFromBackend();
});

async function syncProductsFromBackend() {
    if (typeof BalentoAPI !== 'undefined') {
        try {
            const res = await BalentoAPI.getProducts({ limit: 100 });
            if (res.success && res.data && res.data.products && res.data.products.length > 0) {
                PRODUCTS = res.data.products.map(p => ({
                    id: p.slug,
                    numeric_id: p.id,
                    name: p.name,
                    category: p.category_slug || (p.category_name ? p.category_name.toLowerCase() : 'tote'),
                    price: Math.round(p.price),
                    compare_at_price: p.compare_at_price ? Math.round(p.compare_at_price) : null,
                    description: p.description,
                    dimensions: p.dimensions || '38cm (W) × 30cm (H) × 14cm (D)',
                    weight: p.weight || '680 grams',
                    images: {
                        primary: p.images?.find(i => i.image_type === 'primary')?.image_url || p.images?.[0]?.image_url || 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=900&q=80',
                        hover: p.images?.find(i => i.image_type === 'hover')?.image_url || p.images?.[1]?.image_url || p.images?.[0]?.image_url || 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=900&q=80'
                    },
                    colors: (p.variants && p.variants.length > 0) ? p.variants.map(v => ({
                        name: v.color_name,
                        hex: v.color_hex,
                        variant_id: v.id,
                        stock: v.stock_quantity
                    })) : [
                        { name: "Black", hex: "#1c1b1b" },
                        { name: "Cognac", hex: "#8B5A2B" },
                        { name: "Coffee Brown", hex: "#4A3B32" }
                    ],
                    features: (p.features && p.features.length > 0) ? p.features.map(f => f.feature_text) : ["14\" Laptop Sleeve", "Magnetic Closure"],
                    tag: p.tag
                }));
                if (typeof renderProductGrid === 'function') {
                    renderProductGrid();
                }
            }
        } catch (e) {
            // Graceful fallback to default catalog
        }
    }
}

/* -------------------------------------------------------------
   Scroll Observer & Reveal Animations
   ------------------------------------------------------------- */
function initScrollObserver() {
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -40px 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in-section').forEach((el) => {
        observer.observe(el);
    });
}

function initHeaderScroll() {
    const header = document.getElementById('main-header');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            header.classList.add('py-1', 'border-b', 'border-outline-variant/80', 'shadow-xs');
        } else {
            header.classList.remove('py-1', 'shadow-xs');
        }
    }, { passive: true });
}

function initStickyBarScroll() {
    const stickyBar = document.getElementById('sticky-product-bar');
    const hero = document.querySelector('main section:first-of-type');
    if (!stickyBar) return;

    window.addEventListener('scroll', () => {
        const heroHeight = hero ? hero.offsetHeight : 600;
        if (window.scrollY > heroHeight + 300) {
            stickyBar.classList.add('visible');
        } else {
            stickyBar.classList.remove('visible');
        }
    }, { passive: true });
}

/* -------------------------------------------------------------
   Toast Notification System
   ------------------------------------------------------------- */
let toastTimeout;
function showToast(message, icon = 'check_circle') {
    const toast = document.getElementById('toast-notification');
    const msgEl = document.getElementById('toast-message');
    const iconEl = document.getElementById('toast-icon');
    if (!toast || !msgEl) return;

    msgEl.textContent = message;
    if (iconEl) iconEl.textContent = icon;

    toast.classList.add('active');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.classList.remove('active');
    }, 3500);
}

/* -------------------------------------------------------------
   Lookbook Street Style Modal
   ------------------------------------------------------------- */
function openLookModal(lookKey) {
    const look = LOOKBOOK_DATA[lookKey];
    if (!look) return;

    const content = document.getElementById('look-modal-content');
    if (!content) return;

    content.innerHTML = `
        <div class="w-full sm:w-1/2 aspect-[3/4] bg-surface-container overflow-hidden border border-outline-variant">
            <img src="${look.image}" alt="${look.person}" class="w-full h-full object-cover" />
        </div>
        <div class="w-full sm:w-1/2 text-left flex flex-col justify-between">
            <div>
                <span class="font-label-caps text-[10px] text-tertiary uppercase tracking-widest block mb-1">${look.city}</span>
                <h3 class="font-display-sm text-[22px] uppercase text-on-surface mb-2">${look.person}</h3>
                <blockquote class="font-body-md text-secondary text-sm italic font-light leading-relaxed mb-4 border-l-2 border-outline-variant pl-3">
                    "${look.quote}"
                </blockquote>
            </div>
            <div class="pt-4 border-t border-outline-variant">
                <span class="font-label-caps text-[11px] uppercase tracking-wider text-secondary block mb-1">Featured Bag:</span>
                <p class="font-headline-sm text-[18px] text-on-surface mb-3 font-semibold">${look.bagName} • ${look.price}</p>
                <button onclick="closeLookModal(); openQuickView('${look.bagId}');" class="w-full bg-on-surface text-surface font-label-caps text-[11px] uppercase py-3 border border-on-surface hover:bg-surface hover:text-on-surface transition-all text-center">
                    Shop This Bag →
                </button>
            </div>
        </div>
    `;

    const backdrop = document.getElementById('look-modal-backdrop');
    const panel = document.getElementById('look-modal-panel');
    if (backdrop && panel) {
        backdrop.classList.add('active');
        panel.classList.add('active');
        document.body.classList.add('overflow-hidden');
    }
}

function closeLookModal() {
    const backdrop = document.getElementById('look-modal-backdrop');
    const panel = document.getElementById('look-modal-panel');
    if (backdrop && panel) {
        backdrop.classList.remove('active');
        panel.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

/* -------------------------------------------------------------
   Information / Customer Care Modals
   ------------------------------------------------------------- */
function toggleInfoModal(isOpen, topicKey = 'faq') {
    const backdrop = document.getElementById('info-modal-backdrop');
    const panel = document.getElementById('info-modal-panel');
    const titleEl = document.getElementById('info-modal-title');
    const bodyEl = document.getElementById('info-modal-body');

    if (isOpen && INFO_CONTENT[topicKey]) {
        if (titleEl) titleEl.textContent = INFO_CONTENT[topicKey].title;
        if (bodyEl) bodyEl.innerHTML = INFO_CONTENT[topicKey].html;
        if (backdrop && panel) {
            backdrop.classList.add('active');
            panel.classList.add('active');
            document.body.classList.add('overflow-hidden');
        }
    } else {
        if (backdrop && panel) {
            backdrop.classList.remove('active');
            panel.classList.remove('active');
            document.body.classList.remove('overflow-hidden');
        }
    }
}

/* -------------------------------------------------------------
   Mobile Curtain Navigation
   ------------------------------------------------------------- */
function toggleMobileMenu(isOpen) {
    const backdrop = document.getElementById('mobile-nav-backdrop');
    const curtain = document.getElementById('mobile-nav-curtain');
    const btn = document.getElementById('mobile-menu-btn');
    if (!backdrop || !curtain) return;

    if (isOpen) {
        backdrop.classList.add('active');
        curtain.classList.add('active');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    } else {
        backdrop.classList.remove('active');
        curtain.classList.remove('active');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    }
}

/* -------------------------------------------------------------
   Interactive Color Story Selector
   ------------------------------------------------------------- */
function selectColorStory(shadeKey) {
    const data = COLOR_STORY_DATA[shadeKey];
    if (!data) return;

    document.querySelectorAll('.color-story-btn').forEach(btn => {
        const circle = btn.querySelector('div');
        if (btn.dataset.color === shadeKey) {
            btn.classList.add('active');
            if (circle) {
                circle.classList.add('border-on-surface');
                circle.classList.remove('border-transparent');
            }
            const label = btn.querySelector('span');
            if (label) {
                label.classList.add('text-on-surface', 'font-semibold');
                label.classList.remove('text-secondary');
            }
        } else {
            btn.classList.remove('active');
            if (circle) {
                circle.classList.remove('border-on-surface');
                circle.classList.add('border-transparent');
            }
            const label = btn.querySelector('span');
            if (label) {
                label.classList.remove('text-on-surface', 'font-semibold');
                label.classList.add('text-secondary');
            }
        }
    });

    const titleEl = document.getElementById('color-story-title');
    const textEl = document.getElementById('color-story-text');
    if (titleEl && textEl) {
        titleEl.textContent = data.title;
        textEl.textContent = data.text;
    }
}

/* -------------------------------------------------------------
   Newsletter Handling
   ------------------------------------------------------------- */
async function handleNewsletterSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('newsletter-email');
    const feedback = document.getElementById('newsletter-feedback');
    if (input && input.value) {
        const email = input.value.trim();
        try {
            if (typeof BalentoAPI !== 'undefined') {
                await BalentoAPI.subscribeNewsletter(email, 'footer');
            }
        } catch (err) {
            console.warn('API subscription fallback:', err);
        }

        if (feedback) feedback.classList.remove('hidden');
        showToast("✓ Welcome to the Balento Inner Circle");
        input.value = '';
        if (feedback) setTimeout(() => feedback.classList.add('hidden'), 5000);
    }
}

/* -------------------------------------------------------------
   Global Event Listeners Setup
   ------------------------------------------------------------- */
function initDrawersAndModals() {
    // Cart events
    document.getElementById('cart-open-btn')?.addEventListener('click', () => toggleCart(true));
    document.getElementById('cart-close-btn')?.addEventListener('click', () => toggleCart(false));
    document.getElementById('cart-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'cart-backdrop') toggleCart(false);
    });

    // Wishlist events
    document.getElementById('wishlist-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'wishlist-backdrop') toggleWishlistDrawer(false);
    });

    // Quick View backdrop click
    document.getElementById('quick-view-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'quick-view-backdrop') closeQuickView();
    });

    // Checkout backdrop click
    document.getElementById('checkout-modal-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'checkout-modal-backdrop') closeCheckoutModal();
    });

    // Info modal backdrop click
    document.getElementById('info-modal-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'info-modal-backdrop') toggleInfoModal(false);
    });

    // Lookbook modal backdrop click
    document.getElementById('look-modal-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'look-modal-backdrop') closeLookModal();
    });

    // Search events
    document.getElementById('search-open-btn')?.addEventListener('click', () => toggleSearch(true));
    document.getElementById('search-close-btn')?.addEventListener('click', () => toggleSearch(false));
    document.getElementById('search-modal-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'search-modal-backdrop') toggleSearch(false);
    });
    document.getElementById('search-input')?.addEventListener('input', (e) => performSearch(e.target.value));

    // Mobile nav events
    document.getElementById('mobile-menu-btn')?.addEventListener('click', () => toggleMobileMenu(true));
    document.getElementById('mobile-nav-close')?.addEventListener('click', () => toggleMobileMenu(false));
    document.getElementById('mobile-nav-backdrop')?.addEventListener('click', (e) => {
        if (e.target.id === 'mobile-nav-backdrop') toggleMobileMenu(false);
    });

    // Keyboard shortcuts (Escape & Cmd+K)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            toggleCart(false);
            toggleWishlistDrawer(false);
            closeQuickView();
            closeCheckoutModal();
            toggleInfoModal(false);
            closeLookModal();
            toggleSearch(false);
            toggleMobileMenu(false);
        }
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            toggleSearch(true);
        }
    });
}
