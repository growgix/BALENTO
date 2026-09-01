/**
 * BALENTO Express Checkout Module
 * Manages 1-step checkout modal, promotional code validation (WELCOME10), and order confirmation
 */

let discountMultiplier = 1; // 1 = full price, 0.9 = 10% off

function openCheckoutModal() {
    if (cart.length === 0) {
        showToast("Your shopping bag is currently empty.");
        return;
    }
    toggleCart(false);
    const backdrop = document.getElementById('checkout-modal-backdrop');
    const panel = document.getElementById('checkout-modal-panel');
    const successView = document.getElementById('order-success-view');
    const form = document.getElementById('checkout-form');

    if (successView) successView.classList.add('hidden');
    if (form) form.classList.remove('hidden');

    updateCheckoutTotal();
    if (backdrop && panel) {
        backdrop.classList.add('active');
        panel.classList.add('active');
        document.body.classList.add('overflow-hidden');
    }
}

function closeCheckoutModal() {
    const backdrop = document.getElementById('checkout-modal-backdrop');
    const panel = document.getElementById('checkout-modal-panel');
    if (backdrop && panel) {
        backdrop.classList.remove('active');
        panel.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
    }
}

function updateCheckoutTotal() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const total = Math.round(subtotal * discountMultiplier);
    const totalEl = document.getElementById('checkout-final-total');
    if (totalEl) totalEl.textContent = `₹${total.toLocaleString('en-IN')}`;
}

function applyPromoCode() {
    const input = document.getElementById('checkout-promo-input');
    const feedback = document.getElementById('promo-feedback');
    if (!input) return;
    const val = input.value.trim().toUpperCase();

    if (val === 'WELCOME10' || val === 'BALENTO') {
        discountMultiplier = 0.9;
        if (feedback) {
            feedback.classList.remove('hidden');
            feedback.textContent = '✓ 10% Promotional Privilege Applied!';
        }
        updateCheckoutTotal();
        showToast("✓ 10% Discount Applied Successfully");
    } else {
        showToast("Invalid code. Try 'WELCOME10'", 'error');
    }
}

function handleCheckoutSubmit(e) {
    e.preventDefault();
    const form = document.getElementById('checkout-form');
    const successView = document.getElementById('order-success-view');
    const orderIdEl = document.getElementById('confirmed-order-id');

    const randomId = 'BAL-2026-' + Math.floor(1000 + Math.random() * 9000);
    if (orderIdEl) orderIdEl.textContent = randomId;

    // Clear cart
    cart = [];
    saveCartState();

    if (form) form.classList.add('hidden');
    if (successView) successView.classList.remove('hidden');
}
