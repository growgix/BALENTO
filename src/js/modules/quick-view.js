/**
 * BALENTO Quick View & Product Card Module
 * Manages dynamic catalog rendering, category filtering, lightbox details, and PIN delivery check
 */

let selectedProductColors = {};
let currentFilter = 'all';

function renderProductGrid() {
    const grid = document.getElementById('product-grid');
    if (!grid) return;

    const filtered = currentFilter === 'all' 
        ? PRODUCTS 
        : PRODUCTS.filter(p => p.category === currentFilter);

    grid.innerHTML = filtered.map(product => {
        const selectedColor = selectedProductColors[product.id] || product.colors[0].name;
        const isWishlisted = wishlist.has(product.id);

        return `
            <div class="group cursor-pointer flex flex-col" data-product-id="${product.id}">
                <!-- Image Container with Aspect Ratio (Prevents CLS) -->
                <div class="aspect-[4/5] bg-surface-container overflow-hidden relative mb-4 image-hover-wrapper border border-outline-variant/50" onclick="openQuickView('${product.id}')">
                    <img 
                        src="${product.images.primary}" 
                        alt="${product.name} in premium leather" 
                        class="w-full h-full object-cover object-center primary-image"
                        loading="lazy"
                        decoding="async"
                        width="900"
                        height="1125"
                    />
                    <img 
                        src="${product.images.hover}" 
                        alt="${product.name} lifestyle detail" 
                        class="w-full h-full object-cover object-center absolute inset-0 hover-image opacity-0"
                        loading="lazy"
                        decoding="async"
                        width="900"
                        height="1125"
                    />
                    
                    <!-- Wishlist Heart Button -->
                    <button 
                        onclick="toggleWishlist('${product.id}', event)" 
                        aria-label="Add ${product.name} to wishlist" 
                        class="absolute top-3 right-3 text-secondary hover:text-on-surface z-20 p-1.5 bg-surface/70 backdrop-blur-xs transition-colors"
                    >
                        <span class="material-symbols-outlined text-[20px] wishlist-heart ${isWishlisted ? 'text-on-surface' : ''}" style="${isWishlisted ? "font-variation-settings: 'FILL' 1;" : ''}">
                            ${isWishlisted ? 'favorite' : 'favorite_border'}
                        </span>
                    </button>

                    <!-- Quick Add Overlay (Desktop Hover + Touch Friendly) -->
                    <div class="absolute bottom-0 left-0 w-full p-2.5 opacity-0 group-hover:opacity-100 sm:translate-y-2 group-hover:translate-y-0 transition-all duration-300 bg-surface/90 backdrop-blur-md flex justify-center z-20 border-t border-outline-variant">
                        <button 
                            onclick="quickAddById('${product.id}', event)" 
                            class="w-full py-2 font-label-caps text-[11px] uppercase tracking-[0.15em] text-on-surface hover:text-surface hover:bg-on-surface transition-all font-medium text-center"
                        >
                            Quick Add • ₹${product.price.toLocaleString('en-IN')}
                        </button>
                    </div>
                </div>

                <!-- Product Information -->
                <div class="text-center flex flex-col items-center">
                    <h3 onclick="openQuickView('${product.id}')" class="font-label-caps text-[13px] uppercase tracking-wider mb-1 text-on-surface hover:underline cursor-pointer">${product.name}</h3>
                    <p class="font-headline-sm text-[18px] text-secondary mb-2.5">₹${product.price.toLocaleString('en-IN')}</p>
                    
                    <!-- Color Swatches -->
                    <div class="flex justify-center gap-1.5" aria-label="Available Colors">
                        ${product.colors.map(col => `
                            <button 
                                onclick="changeProductColor('${product.id}', '${col.name}', event)"
                                aria-label="${col.name}"
                                title="${col.name}"
                                class="w-4 h-4 rounded-full border ${selectedColor === col.name ? 'ring-1 ring-on-surface ring-offset-1 border-transparent' : 'border-outline-variant'} transition-all"
                                style="background-color: ${col.hex};"
                            ></button>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function filterProducts(category) {
    currentFilter = category;
    
    // Update filter button styling
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.dataset.filter === category) {
            btn.classList.add('active', 'bg-on-surface', 'text-surface', 'border-on-surface');
            btn.classList.remove('text-secondary', 'border-outline-variant');
        } else {
            btn.classList.remove('active', 'bg-on-surface', 'text-surface', 'border-on-surface');
            btn.classList.add('text-secondary', 'border-outline-variant');
        }
    });

    renderProductGrid();

    // Smoothly scroll to collection section if clicked from menu/footer
    const collectionSection = document.getElementById('collection');
    if (collectionSection && (window.scrollY > collectionSection.offsetTop + 400 || window.scrollY < collectionSection.offsetTop - 200)) {
        collectionSection.scrollIntoView({ behavior: 'smooth' });
    }
}

function changeProductColor(productId, colorName, event) {
    if (event) event.stopPropagation();
    selectedProductColors[productId] = colorName;
    renderProductGrid();
}

function openQuickView(productId) {
    const product = PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    const selectedColor = selectedProductColors[productId] || product.colors[0].name;
    const isWishlisted = wishlist.has(product.id);
    const content = document.getElementById('quick-view-content');

    content.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <!-- Image Showcase with Thumbnail toggle -->
            <div class="flex flex-col gap-3">
                <div class="aspect-[4/5] bg-surface-container overflow-hidden border border-outline-variant">
                    <img id="qv-main-img" src="${product.images.primary}" alt="${product.name}" class="w-full h-full object-cover" />
                </div>
                <div class="flex gap-2">
                    <button onclick="document.getElementById('qv-main-img').src='${product.images.primary}'" class="w-16 h-20 border border-on-surface overflow-hidden">
                        <img src="${product.images.primary}" alt="${product.name} Front" class="w-full h-full object-cover"/>
                    </button>
                    <button onclick="document.getElementById('qv-main-img').src='${product.images.hover}'" class="w-16 h-20 border border-outline-variant overflow-hidden hover:border-on-surface">
                        <img src="${product.images.hover}" alt="${product.name} Detail" class="w-full h-full object-cover"/>
                    </button>
                </div>
            </div>

            <!-- Details & Purchase Actions -->
            <div class="flex flex-col text-left">
                <span class="font-label-caps text-[10px] text-tertiary uppercase tracking-widest mb-1">${product.category} • ${product.tag}</span>
                <h3 id="qv-title" class="font-display-sm text-[28px] uppercase mb-2 leading-tight">${product.name}</h3>
                <p class="font-headline-sm text-[22px] text-on-surface mb-4 font-semibold">₹${product.price.toLocaleString('en-IN')}</p>
                
                <p class="font-body-md text-secondary text-sm mb-6 leading-relaxed font-light">${product.description}</p>
                
                <!-- Color Options -->
                <div class="mb-6">
                    <span class="font-label-caps text-[11px] uppercase tracking-wider text-secondary block mb-2">Selected Color: <strong class="text-on-surface" id="qv-selected-color">${selectedColor}</strong></span>
                    <div class="flex gap-2">
                        ${product.colors.map(col => `
                            <button 
                                onclick="changeProductColor('${product.id}', '${col.name}'); document.getElementById('qv-selected-color').textContent='${col.name}';"
                                class="w-6 h-6 rounded-full border ${selectedColor === col.name ? 'ring-2 ring-on-surface ring-offset-2 border-transparent' : 'border-outline-variant'}"
                                style="background-color: ${col.hex};"
                                title="${col.name}"
                            ></button>
                        `).join('')}
                    </div>
                </div>

                <!-- Specifications List -->
                <div class="bg-surface-container-low p-4 border border-outline-variant/60 mb-4 text-xs space-y-1.5 font-body-md text-secondary">
                    <p><strong>Dimensions:</strong> ${product.dimensions}</p>
                    <p><strong>Weight:</strong> ${product.weight}</p>
                    <p><strong>Highlights:</strong> ${product.features.join(' • ')}</p>
                    <p class="text-accent-sage font-medium">✓ In Stock • Express Dispatch in 24 Hours</p>
                </div>

                <!-- Pincode Delivery Checker -->
                <div class="mb-5 pb-4 border-b border-outline-variant/60">
                    <label class="font-label-caps text-[10px] uppercase tracking-wider block text-tertiary mb-1 font-semibold">Delivery &amp; COD Estimator</label>
                    <div class="flex gap-2">
                        <input id="qv-pincode-input" type="text" maxlength="6" placeholder="Enter 6-digit PIN (e.g. 560034)" class="flex-1 bg-surface-container-lowest border border-outline-variant px-3 py-1.5 text-xs focus:outline-none focus:border-on-surface" />
                        <button onclick="checkPincodeDelivery()" class="bg-surface-container border border-outline-variant px-3 py-1.5 font-label-caps text-[10px] uppercase hover:border-on-surface">Check</button>
                    </div>
                    <p id="qv-pincode-result" class="text-xs font-body-md mt-1.5 hidden"></p>
                </div>

                <!-- CTA buttons -->
                <div class="flex gap-3">
                    <button onclick="quickAddById('${product.id}'); closeQuickView();" class="flex-1 bg-on-surface text-surface font-label-caps text-label-caps uppercase py-3.5 border border-on-surface hover:bg-surface hover:text-on-surface transition-all text-center">
                        Add to Bag • ₹${product.price.toLocaleString('en-IN')}
                    </button>
                    <button onclick="toggleWishlist('${product.id}'); closeQuickView();" aria-label="Wishlist" class="p-3.5 border border-outline-variant hover:border-on-surface text-secondary hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">${isWishlisted ? 'favorite' : 'favorite_border'}</span>
                    </button>
                </div>
            </div>
        </div>
    `;

    const backdrop = document.getElementById('quick-view-backdrop');
    const panel = document.getElementById('quick-view-panel');
    if (backdrop && panel) {
        backdrop.classList.add('active');
        panel.classList.add('active');
        document.body.classList.add('overflow-hidden');
    }
}

function closeQuickView() {
    const backdrop = document.getElementById('quick-view-backdrop');
    const panel = document.getElementById('quick-view-panel');
    if (backdrop && panel) {
        backdrop.classList.remove('active');
        panel.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

function checkPincodeDelivery() {
    const input = document.getElementById('qv-pincode-input');
    const result = document.getElementById('qv-pincode-result');
    if (!input || !result) return;

    const pin = input.value.trim();
    if (pin.length === 6 && /^\d+$/.test(pin)) {
        result.className = "text-xs font-body-md text-accent-sage mt-1.5";
        result.innerHTML = `✓ <strong>Express Delivery:</strong> 2 business days to PIN ${pin} via Air Cargo • COD &amp; 7-Day Returns Available.`;
        result.classList.remove('hidden');
    } else {
        result.className = "text-xs font-body-md text-[#b3261e] mt-1.5";
        result.textContent = "Please enter a valid 6-digit Indian PIN Code.";
        result.classList.remove('hidden');
    }
}
