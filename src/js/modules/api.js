/**
 * BALENTO Frontend REST API Client
 * High-performance fetch wrapper with error handling, base URL detection, and JSON parsing.
 */
const BalentoAPI = (() => {
    const API_BASE = window.location.origin.includes(':8000') 
        ? `${window.location.origin}/api` 
        : 'http://localhost:8000/api';

    async function request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const json = await response.json();
            if (!response.ok) {
                const errorMsg = json.message || 'An error occurred during the request.';
                throw new Error(errorMsg);
            }

            return json;
        } catch (err) {
            console.error(`[BalentoAPI Error] ${endpoint}:`, err.message);
            throw err;
        }
    }

    return {
        // Product Catalog
        async getProducts(params = {}) {
            const query = new URLSearchParams(params).toString();
            const endpoint = query ? `/products?${query}` : '/products';
            return request(endpoint, { method: 'GET' });
        },

        async getProductDetail(slugOrId) {
            return request(`/products/${encodeURIComponent(slugOrId)}`, { method: 'GET' });
        },

        // Pincode & Delivery Serviceability
        async checkPincode(pincode) {
            return request('/pincode/check', {
                method: 'POST',
                body: JSON.stringify({ pincode: pincode.trim() })
            });
        },

        // Coupon Validation & Pricing Breakdown
        async validateCoupon(code, subtotal) {
            return request('/coupons/validate', {
                method: 'POST',
                body: JSON.stringify({ code: code.trim(), subtotal })
            });
        },

        // Atomic Checkout
        async checkout(orderPayload, idempotencyKey = null) {
            const headers = {};
            if (idempotencyKey) {
                headers['X-Idempotency-Key'] = idempotencyKey;
            }

            return request('/orders/checkout', {
                method: 'POST',
                headers,
                body: JSON.stringify(orderPayload)
            });
        },

        // Public Order Tracking
        async trackOrder(orderNumber) {
            return request(`/orders/track/${encodeURIComponent(orderNumber.trim())}`, {
                method: 'GET'
            });
        },

        // Newsletter Subscription
        async subscribeNewsletter(email, source = 'footer') {
            return request('/newsletter/subscribe', {
                method: 'POST',
                body: JSON.stringify({ email: email.trim(), source })
            });
        },

        // Editorial Lookbook
        async getLookbook() {
            return request('/lookbook', { method: 'GET' });
        }
    };
})();
