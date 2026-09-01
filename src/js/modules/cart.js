/**
 * BALENTO Cart Module
 * Manages slide-out shopping bag, line items, quantity, shipping threshold progress, and gifting
 */

let cart = [];

function loadCartState() {
    try {
        const savedCart = localStorage.getItem('balento_cart');
        if (savedCart) cart = JSON.parse(savedCart);
    } catch (e) {
        console.warn('LocalStorage access restricted:', e);
    }
    updateCartUI();
}

function saveCartState() {
    try {
        localStorage.setItem('balento_cart', JSON.stringify(cart));
    } catch (e) {}
    updateCartUI();
}

function toggleCart(isOpen) {
    const backdrop = document.getElementById('cart-backdrop');
    const drawer = document.getElementById('cart-drawer');
    if (!backdrop || !drawer) return;

    if (isOpen) {
        backdrop.classList.add('active');
        drawer.classList.add('active');
        document.body.classList.add('overflow-hidden');
    } else {
        backdrop.classList.remove('active');
        drawer.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

function quickAddById(productId, event) {
    if (event) event.stopPropagation();
    const product = PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    const selectedColor = selectedProductColors[productId] || product.colors[0].name;
    const existingIndex = cart.findIndex(item => item.id === productId && item.color === selectedColor);

    if (existingIndex > -1) {
        cart[existingIndex].quantity += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            color: selectedColor,
            image: product.images.primary,
            quantity: 1
        });
    }

    saveCartState();
    showToast(`✓ Added ${product.name} (${selectedColor}) to Bag`);
    toggleCart(true);
}

function updateCartQuantity(index, delta) {
    if (!cart[index]) return;
    cart[index].quantity += delta;
    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }
    saveCartState();
}

function removeCartItem(index) {
    if (!cart[index]) return;
    const removed = cart.splice(index, 1)[0];
    saveCartState();
    if (removed) showToast(`Removed ${removed.name} from Bag`, 'delete');
}

function updateCartUI() {
    const container = document.getElementById('cart-items-container');
    const countBadge = document.getElementById('cart-count-badge');
    const headerCount = document.getElementById('cart-header-count');
    const subtotalEl = document.getElementById('cart-subtotal');
    const progressText = document.getElementById('shipping-progress-text');
    const progressBar = document.getElementById('shipping-progress-bar');
    const shippingStatus = document.getElementById('cart-shipping-status');

    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    // Badge count
    if (countBadge) {
        if (totalItems > 0) {
            countBadge.textContent = totalItems;
            countBadge.classList.remove('opacity-0');
        } else {
            countBadge.classList.add('opacity-0');
        }
    }

    if (headerCount) headerCount.textContent = `(${totalItems} ${totalItems === 1 ? 'item' : 'items'})`;
    if (subtotalEl) subtotalEl.textContent = `₹${subtotal.toLocaleString('en-IN')}`;

    // Free shipping threshold (₹2,000)
    const threshold = 2000;
    if (progressBar && progressText) {
        if (subtotal >= threshold) {
            progressBar.style.width = '100%';
            progressBar.classList.add('bg-accent-sage');
            progressBar.classList.remove('bg-on-surface');
            progressText.innerHTML = `<span class="text-accent-sage font-semibold">✓ You've unlocked Complimentary Shipping!</span>`;
            if (shippingStatus) shippingStatus.textContent = "FREE";
        } else {
            const diff = threshold - subtotal;
            const percentage = Math.min(100, Math.round((subtotal / threshold) * 100));
            progressBar.style.width = `${percentage}%`;
            progressBar.classList.remove('bg-accent-sage');
            progressBar.classList.add('bg-on-surface');
            progressText.textContent = `Add ₹${diff.toLocaleString('en-IN')} more to unlock Complimentary Shipping`;
            if (shippingStatus) shippingStatus.textContent = "₹150 (Free over ₹2,000)";
        }
    }

    // Render Items
    if (!container) return;
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="flex-1 flex flex-col items-center justify-center text-center p-6 my-auto">
                <span class="material-symbols-outlined text-[48px] text-tertiary font-light mb-3">shopping_bag</span>
                <p class="font-headline-sm text-[20px] text-on-surface mb-2 uppercase">Your bag is empty</p>
                <p class="font-body-md text-secondary text-sm mb-6">Discover timeless pieces designed for your everyday wardrobe.</p>
                <button onclick="toggleCart(false); filterProducts('all');" class="bg-on-surface text-surface font-label-caps text-[11px] uppercase px-6 py-3 border border-on-surface hover:bg-surface hover:text-on-surface transition-all">
                    Explore Collection
                </button>
            </div>
        `;
    } else {
        container.innerHTML = cart.map((item, index) => `
            <div class="flex gap-4 pb-4 border-b border-outline-variant/60 items-center">
                <img src="${item.image}" alt="${item.name}" class="w-20 h-24 object-cover border border-outline-variant flex-shrink-0 bg-surface-container" />
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <h4 class="font-label-caps text-[13px] uppercase text-on-surface truncate">${item.name}</h4>
                        <button onclick="removeCartItem(${index})" aria-label="Remove item" class="text-secondary hover:text-on-surface p-1">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                    <p class="font-body-md text-[13px] text-secondary mb-2">Color: ${item.color}</p>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center border border-outline-variant bg-surface-container-lowest">
                            <button onclick="updateCartQuantity(${index}, -1)" class="px-2 py-1 text-secondary hover:text-on-surface text-xs font-semibold focus:outline-none">-</button>
                            <span class="px-2.5 py-1 text-xs font-medium">${item.quantity}</span>
                            <button onclick="updateCartQuantity(${index}, 1)" class="px-2 py-1 text-secondary hover:text-on-surface text-xs font-semibold focus:outline-none">+</button>
                        </div>
                        <span class="font-body-md text-sm font-semibold text-on-surface">₹${(item.price * item.quantity).toLocaleString('en-IN')}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

function toggleGiftOptions(isChecked) {
    const wrapper = document.getElementById('gift-note-wrapper');
    if (wrapper) {
        if (isChecked) {
            wrapper.classList.remove('hidden');
            showToast("✓ Added complimentary gift box & card");
        } else {
            wrapper.classList.add('hidden');
        }
    }
}
