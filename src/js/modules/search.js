/**
 * BALENTO Live Search Module
 * Manages live search modal, debounced querying across silhouettes & materials, and quick tag filters
 */

function toggleSearch(isOpen) {
    const backdrop = document.getElementById('search-modal-backdrop');
    const panel = document.getElementById('search-modal-panel');
    const input = document.getElementById('search-input');
    if (!backdrop || !panel) return;

    if (isOpen) {
        backdrop.classList.add('active');
        panel.classList.add('active');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => input && input.focus(), 100);
        performSearch('');
    } else {
        backdrop.classList.remove('active');
        panel.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

function performSearch(query) {
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;
    const q = query.trim().toLowerCase();

    const matches = PRODUCTS.filter(p => {
        if (!q) return true;
        return p.name.toLowerCase().includes(q) ||
               p.category.toLowerCase().includes(q) ||
               p.description.toLowerCase().includes(q) ||
               p.colors.some(c => c.name.toLowerCase().includes(q));
    });

    if (matches.length === 0) {
        resultsContainer.innerHTML = `
            <div class="text-center py-6 text-secondary text-sm">
                No matches found for "<span class="text-on-surface font-medium">${query}</span>". Try searching for "Tote" or "Cognac".
            </div>
        `;
    } else {
        resultsContainer.innerHTML = matches.map(p => `
            <div class="flex items-center justify-between p-2.5 hover:bg-surface-container transition-colors border border-outline-variant/40">
                <div class="flex items-center gap-3 cursor-pointer" onclick="toggleSearch(false); openQuickView('${p.id}');">
                    <img src="${p.images.primary}" alt="${p.name}" class="w-12 h-14 object-cover border border-outline-variant" />
                    <div>
                        <h4 class="font-label-caps text-[12px] uppercase text-on-surface">${p.name}</h4>
                        <span class="font-body-md text-xs text-secondary">₹${p.price.toLocaleString('en-IN')} • ${p.category}</span>
                    </div>
                </div>
                <button onclick="toggleSearch(false); quickAddById('${p.id}');" class="bg-on-surface text-surface text-[10px] font-label-caps uppercase px-3 py-1.5 hover:bg-surface-tint">
                    Add to Bag
                </button>
            </div>
        `).join('');
    }
}

function applySearchQuery(text) {
    const input = document.getElementById('search-input');
    if (input) {
        input.value = text;
        performSearch(text);
    }
}
