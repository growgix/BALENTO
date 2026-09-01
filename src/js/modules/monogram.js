/**
 * BALENTO Monogramming Atelier Module
 * Manages live embossed tag customization, foil finish selection, and cart addition
 */

let monogramFoil = 'gold';

function updateMonogramPreview() {
    const input = document.getElementById('monogram-input');
    const render = document.getElementById('monogram-render');
    if (!input || !render) return;

    const text = input.value.trim().toUpperCase() || 'BM';
    render.textContent = text.split('').join('.');
}

function setMonogramFoil(type) {
    monogramFoil = type;
    const render = document.getElementById('monogram-render');
    if (!render) return;

    render.className = `font-display-lg text-[44px] uppercase my-auto tracking-widest monogram-preview-${type}`;

    document.querySelectorAll('.monogram-foil-btn').forEach(btn => {
        if (btn.textContent.toLowerCase().includes(type)) {
            btn.classList.add('active', 'bg-on-surface', 'text-surface', 'border-on-surface');
            btn.classList.remove('text-secondary', 'border-outline-variant');
        } else {
            btn.classList.remove('active', 'bg-on-surface', 'text-surface', 'border-on-surface');
            btn.classList.add('text-secondary', 'border-outline-variant');
        }
    });
}

function addMonogrammedTagToCart() {
    const input = document.getElementById('monogram-input');
    const initials = input ? (input.value.trim().toUpperCase() || 'BM') : 'BM';

    cart.push({
        id: "monogram-tag",
        name: `Bespoke Monogrammed Tag (${initials})`,
        price: 0,
        color: `${monogramFoil.toUpperCase()} FOIL`,
        image: "https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=300&q=80",
        quantity: 1
    });

    saveCartState();
    showToast(`✓ Added Bespoke Tag (${initials}) to Bag`);
    toggleCart(true);
}
