/**
 * BALENTO Wishlist Module
 * Manages wishlist toggling, slide-out drawer, badge updates, and LocalStorage persistence
 */

let wishlist = new Set();

function loadWishlistState() {
    try {
        const savedWish = localStorage.getItem('balento_wishlist');
        if (savedWish) wishlist = new Set(JSON.parse(savedWish));
    } catch (e) {
        console.warn('LocalStorage access restricted:', e);
    }
    updateWishlistUI();
}

function saveWishlistState() {
    try {
        localStorage.setItem('balento_wishlist', JSON.stringify(Array.from(wishlist)));
    } catch (e) {}
    updateWishlistUI();
}

function toggleWishlist(productId, event) {
    if (event) event.stopPropagation();
    const product = PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    if (wishlist.has(productId)) {
        wishlist.delete(productId);
        showToast(`Removed ${product.name} from Wishlist`, 'favorite_border');
    } else {
        wishlist.add(productId);
        showToast(`♥ Added ${product.name} to Wishlist`, 'favorite');
    }
    saveWishlistState();
    renderProductGrid();
}

function toggleWishlistDrawer(isOpen) {
    const backdrop = document.getElementById('wishlist-backdrop');
    const drawer = document.getElementById('wishlist-drawer');
    if (!backdrop || !drawer) return;

    if (isOpen) {
        renderWishlistDrawerItems();
        backdrop.classList.add('active');
        drawer.classList.add('active');
        document.body.classList.add('overflow-hidden');
    } else {
        backdrop.classList.remove('active');
        drawer.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

function renderWishlistDrawerItems() {
    const container = document.getElementById('wishlist-items-container');
    const headerCount = document.getElementById('wishlist-header-count');
    if (!container) return;

    const savedItems = PRODUCTS.filter(p => wishlist.has(p.id));
    if (headerCount) headerCount.textContent = `(${savedItems.length} ${savedItems.length === 1 ? 'item' : 'items'})`;

    if (savedItems.length === 0) {
        container.innerHTML = `
            <div class="flex-1 flex flex-col items-center justify-center text-center p-6 my-auto">
                <span class="material-symbols-outlined text-[48px] text-tertiary font-light mb-3">favorite_border</span>
                <p class="font-headline-sm text-[20px] text-on-surface mb-2 uppercase">No items in Wishlist</p>
                <p class="font-body-md text-secondary text-sm mb-6">Save your favorite silhouettes to review anytime.</p>
                <button onclick="toggleWishlistDrawer(false); filterProducts('all');" class="bg-on-surface text-surface font-label-caps text-[11px] uppercase px-6 py-3 border border-on-surface hover:bg-surface hover:text-on-surface transition-all">
                    Browse Collection
                </button>
            </div>
        `;
    } else {
        container.innerHTML = savedItems.map(p => `
            <div class="flex gap-4 pb-4 border-b border-outline-variant/60 items-center">
                <img src="${p.images.primary}" alt="${p.name}" class="w-20 h-24 object-cover border border-outline-variant flex-shrink-0 bg-surface-container" />
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <h4 class="font-label-caps text-[13px] uppercase text-on-surface truncate">${p.name}</h4>
                        <button onclick="toggleWishlist('${p.id}'); renderWishlistDrawerItems();" aria-label="Remove item" class="text-secondary hover:text-on-surface p-1">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                    <p class="font-body-md text-[13px] text-secondary mb-3">₹${p.price.toLocaleString('en-IN')}</p>
                    <button onclick="toggleWishlistDrawer(false); quickAddById('${p.id}');" class="w-full bg-on-surface text-surface font-label-caps text-[10px] uppercase py-2 hover:bg-surface hover:text-on-surface border border-on-surface transition-all">
                        Move to Bag
                    </button>
                </div>
            </div>
        `).join('');
    }
}

function updateWishlistUI() {
    const badge = document.getElementById('wishlist-count-badge');
    const icon = document.getElementById('nav-wishlist-icon');
    if (wishlist.size > 0) {
        if (badge) {
            badge.textContent = wishlist.size;
            badge.classList.remove('opacity-0');
        }
        if (icon) icon.style.fontVariationSettings = "'FILL' 1";
    } else {
        if (badge) badge.classList.add('opacity-0');
        if (icon) icon.style.fontVariationSettings = "'FILL' 0";
    }
}
